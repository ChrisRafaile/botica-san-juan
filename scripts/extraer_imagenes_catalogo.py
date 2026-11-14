#!/usr/bin/env python3
"""
Extractor y emparejador conservador de imágenes del catálogo desde boticasperu.pe.

Cumple las reglas de extracción ética:
- Lee y respeta robots.txt antes de cualquier solicitud.
- Usa scrapling.fetchers.FetcherSession en modo HTTP normal (sin stealth ni evasión).
- Una sola petición secuencial a la vez, con pausa >= 2.1 s entre solicitudes y
  reintentos con retroceso exponencial ante errores transitorios.
- Detiene la ejecución inmediatamente si detecta bloqueo o CAPTCHA.
- Cachea respuestas e imágenes originales en disco para no repetir descargas.
- Empareja de forma conservadora por nombre, concentración, forma farmacéutica
  y presentación: ante la duda, deja el producto sin foto.
- Optimiza las imágenes descargadas a WebP (máx. 600x600) en
  botica-san-juan-backend/storage/app/public/productos/ y genera el archivo
  versionado database/data/correspondencia_imagenes.json.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import re
import sys
import time
import unicodedata
import urllib.robotparser
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

import psycopg2
from PIL import Image, ImageOps
from scrapling import Selector
from scrapling.fetchers import FetcherSession

BASE_URL = "https://www.boticasperu.pe"
GRID_ENDPOINT = "/on/demandware.store/Sites-BoticasPeru-Site/es_PE/Search-UpdateGrid"
USER_AGENT = "Mozilla/5.0 (compatible; BoticaSanJuanCatalogoSync/1.0)"
ESPERA_MINIMA_SEG = 2.1
TAMANO_PAGINA_GRID = 200
MAX_REINTENTOS = 3
UMBRAL_CONSERVADOR = 72.0
LADO_MAXIMO_WEBP = 600
CALIDAD_WEBP = 85

RAIZ_REPO = Path(__file__).resolve().parent.parent
BACKEND_DIR = RAIZ_REPO / "botica-san-juan-backend"
CACHE_DIR = BACKEND_DIR / "storage" / "app" / "private" / "scraping_cache"
SALIDA_IMAGENES_DIR = BACKEND_DIR / "storage" / "app" / "public" / "productos"
CORRESPONDENCIA_PATH = BACKEND_DIR / "database" / "data" / "correspondencia_imagenes.json"
EXCLUSIONES_PATH = Path(__file__).resolve().parent / "exclusiones_imagenes.json"

SENALES_BLOQUEO = (
    "cf-turnstile",
    "attention required! | cloudflare",
    "verify you are human",
    "g-recaptcha",
    "hcaptcha",
    "just a moment...",
)

# Palabras de forma/envase/conector que no cambian la identidad del medicamento.
STOPWORDS_FORMA = {
    "a", "de", "del", "la", "el", "en", "con", "sin", "para", "y", "o", "x", "un", "una",
    "oral", "topica", "topico", "uso", "externo",
    "tableta", "tabletas", "tab", "comprimido", "comprimidos", "comp", "gragea", "grageas",
    "capsula", "capsulas", "cap", "blanda", "blandas", "dura", "duras",
    "recubierta", "recubiertas", "masticable", "masticables", "efervescente", "efervescentes",
    "dispersable", "dispersables", "sublingual",
    "jarabe", "jbe", "suspension", "susp", "emulsion", "solucion", "sol", "elixir",
    "gota", "gotas", "gotero", "oftalmica", "oftalmico", "otica", "otico", "nasal",
    "crema", "unguento", "pomada", "gel", "emulgel", "jalea", "locion",
    "aerosol", "spray", "inhalador", "atomizador", "nebulizador",
    "inyectable", "iny", "ampolla", "ampollas", "vial", "viales", "jeringa", "prellenada",
    "polvo", "granulo", "granulos", "sobre", "sobres",
    "ovulo", "ovulos", "vaginal", "vaginales", "supositorio", "supositorios", "enema",
    "frasco", "fco", "caja", "blister", "tubo", "bolsa", "sachet", "envase", "dispensador",
    "l", "ml", "mg", "mcg", "ug", "g", "gr", "kg", "ui", "iu",
}

SINGULARES = {
    "fresas": "fresa",
    "naranjas": "naranja",
    "limones": "limon",
    "mentas": "menta",
    "uvas": "uva",
    "adultos": "adulto",
    "ninos": "nino",
    "infantil": "infantil",
    "pediatrica": "pediatrico",
}


class BloqueoSitioError(RuntimeError):
    """Se lanza si robots.txt prohíbe la ruta o el sitio responde con bloqueo/captcha."""


class ClienteHttpRespetuoso:
    """Cliente HTTP secuencial sobre FetcherSession estándar (sin stealth ni evasión)."""

    def __init__(self, cache_dir: Path, espera_seg: float = ESPERA_MINIMA_SEG) -> None:
        self.cache_dir = cache_dir
        self.cache_dir.mkdir(parents=True, exist_ok=True)
        self.espera_seg = max(2.0, espera_seg)
        self._ultima_peticion = 0.0
        self._sesion_cm = FetcherSession(
            impersonate=None,
            stealthy_headers=False,
            retries=1,
            headers={
                "User-Agent": USER_AGENT,
                "Accept-Language": "es-PE,es;q=0.9",
            },
        )
        self.sesion = self._sesion_cm.__enter__()
        self.robot_parser = urllib.robotparser.RobotFileParser()
        self._cargar_robots()

    def cerrar(self) -> None:
        try:
            self._sesion_cm.__exit__(None, None, None)
        except Exception:
            pass

    def _cargar_robots(self) -> None:
        robots_cache = self.cache_dir / "robots.txt"
        if robots_cache.exists():
            contenido = robots_cache.read_text(encoding="utf-8", errors="replace")
        else:
            resp = self._peticion_cruda(f"{BASE_URL}/robots.txt")
            if resp.status != 200:
                raise BloqueoSitioError(
                    f"No se pudo leer robots.txt (HTTP {resp.status}); se detiene por seguridad."
                )
            contenido = resp.body.decode("utf-8", errors="replace") if isinstance(resp.body, bytes) else str(resp.body)
            robots_cache.write_text(contenido, encoding="utf-8")
        self.robot_parser.parse(contenido.splitlines())

    def verificar_permitido(self, ruta_o_url: str) -> None:
        url = ruta_o_url if ruta_o_url.startswith("http") else f"{BASE_URL}{ruta_o_url}"
        if not self.robot_parser.can_fetch(USER_AGENT, url) or not self.robot_parser.can_fetch("*", url):
            raise BloqueoSitioError(f"robots.txt prohíbe acceder a: {url}")

    def _esperar_turno(self) -> None:
        transcurrido = time.monotonic() - self._ultima_peticion
        if self._ultima_peticion > 0 and transcurrido < self.espera_seg:
            time.sleep(self.espera_seg - transcurrido)

    @staticmethod
    def _content_type(resp: Any) -> str:
        for k, v in (resp.headers or {}).items():
            if str(k).lower() == "content-type":
                return str(v).lower()
        return ""

    def _peticion_cruda(self, url: str, params: dict[str, Any] | None = None):
        ultimo_error: Exception | None = None
        for intento in range(1, MAX_REINTENTOS + 1):
            self._esperar_turno()
            try:
                resp = self.sesion.get(url, params=params, timeout=30)
                self._ultima_peticion = time.monotonic()
            except Exception as exc:
                self._ultima_peticion = time.monotonic()
                ultimo_error = exc
                time.sleep(self.espera_seg * (2 ** intento))
                continue

            cuerpo_bytes = resp.body if isinstance(resp.body, bytes) else str(resp.body).encode("utf-8", errors="replace")
            texto_min = cuerpo_bytes[:4000].decode("utf-8", errors="ignore").lower()

            if resp.status in (403, 429, 503):
                if resp.status == 403 or any(s in texto_min for s in SENALES_BLOQUEO):
                    raise BloqueoSitioError(
                        f"El sitio respondió HTTP {resp.status} con posible bloqueo/captcha en {url}"
                    )
                time.sleep(self.espera_seg * (2 ** intento))
                continue

            if resp.status >= 500:
                time.sleep(self.espera_seg * (2 ** intento))
                continue

            ctype = self._content_type(resp)
            if "text/html" in ctype and any(s in texto_min for s in SENALES_BLOQUEO):
                raise BloqueoSitioError(f"Se detectó desafío anti-bot/captcha en {url}")

            return resp

        raise RuntimeError(f"Fallo tras {MAX_REINTENTOS} intentos en {url}: {ultimo_error}")

    def obtener_texto(self, clave_cache: str, url: str, params: dict[str, Any] | None = None) -> str:
        self.verificar_permitido(url)
        ruta_cache = self.cache_dir / clave_cache
        ruta_cache.parent.mkdir(parents=True, exist_ok=True)
        if ruta_cache.exists():
            return ruta_cache.read_text(encoding="utf-8", errors="replace")

        resp = self._peticion_cruda(url, params=params)
        if resp.status != 200:
            raise RuntimeError(f"HTTP {resp.status} al solicitar {url}")
        texto = resp.body.decode("utf-8", errors="replace") if isinstance(resp.body, bytes) else str(resp.body)
        ruta_cache.write_text(texto, encoding="utf-8")
        return texto

    def obtener_binario(self, clave_cache: str, url: str) -> bytes | None:
        self.verificar_permitido(url)
        ruta_cache = self.cache_dir / clave_cache
        ruta_cache.parent.mkdir(parents=True, exist_ok=True)
        if ruta_cache.exists():
            return ruta_cache.read_bytes()

        resp = self._peticion_cruda(url)
        if resp.status == 404:
            return None
        if resp.status != 200:
            raise RuntimeError(f"HTTP {resp.status} al descargar imagen {url}")
        ctype = self._content_type(resp)
        if not ctype.startswith("image/"):
            return None
        datos = resp.body if isinstance(resp.body, bytes) else bytes(resp.body)
        ruta_cache.write_bytes(datos)
        return datos


@dataclass
class ProductoExterno:
    pid: str
    titulo: str
    marca: str
    href: str
    imagen_url: str


def indexar_catalogo_externo(cliente: ClienteHttpRespetuoso) -> list[ProductoExterno]:
    """Recorre Search-UpdateGrid?cgid=root con caché en disco y extrae los productos."""
    indice_path = CACHE_DIR / "indice_externo.json"
    if indice_path.exists():
        datos = json.loads(indice_path.read_text(encoding="utf-8"))
        return [ProductoExterno(**d) for d in datos]

    productos: dict[str, ProductoExterno] = {}
    start = 0
    while start <= 20000:
        html = cliente.obtener_texto(
            f"grid/root_{start}.html",
            f"{BASE_URL}{GRID_ENDPOINT}",
            params={"cgid": "root", "start": start, "sz": TAMANO_PAGINA_GRID},
        )
        sel = Selector(html)
        tiles = sel.css(".product")
        if not tiles:
            break

        nuevos = 0
        for tile in tiles:
            pid = (tile.attrib.get("data-pid") or "").strip()
            titulo = (tile.css(".pdp-link a::text").get() or "").strip()
            href = (tile.css(".pdp-link a::attr(href)").get() or "").strip()
            img_src = (
                tile.css("img.tile-image::attr(src)").get()
                or tile.css("img::attr(src)").get()
                or ""
            ).strip()
            marca = ""
            ga_raw = tile.css(".pdp-link a::attr(data-ga)").get()
            if ga_raw:
                try:
                    ga = json.loads(ga_raw)
                    items = ga.get("ecommerce", {}).get("items", [])
                    if items:
                        marca = str(items[0].get("item_brand") or "").strip()
                except Exception:
                    pass

            if not pid or not titulo or not img_src:
                continue

            if pid not in productos:
                productos[pid] = ProductoExterno(
                    pid=pid,
                    titulo=titulo,
                    marca=marca,
                    href=href,
                    imagen_url=img_src,
                )
                nuevos += 1

        print(f"  Indexado start={start}: {len(tiles)} tarjetas ({nuevos} nuevas, total={len(productos)})", flush=True)
        if len(tiles) < TAMANO_PAGINA_GRID or nuevos == 0:
            break
        start += TAMANO_PAGINA_GRID

    lista = list(productos.values())
    indice_path.write_text(
        json.dumps([asdict(p) for p in lista], ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    return lista


def norm(texto: str | None) -> str:
    if not texto:
        return ""
    s = unicodedata.normalize("NFKD", texto).encode("ascii", "ignore").decode("ascii").lower()
    s = re.sub(r"(\d)\s+(\d{3})\b", r"\1\2", s)  # "1 200" -> "1200"
    s = s.replace(",", ".")
    s = re.sub(r"[^a-z0-9.%+/° ]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def _tiene_palabra(texto: str, patron: str) -> bool:
    return bool(re.search(rf"\b(?:{patron})\b", texto))


def grupo_forma(tipo_o_texto: str) -> str | None:
    """Clasifica la forma farmacéutica distinguiendo presentaciones incompatibles."""
    t = norm(tipo_o_texto)
    if not t:
        return None
    if _tiene_palabra(t, r"efervescent\w*"):
        return "efervescente"
    if _tiene_palabra(t, r"capsulas?\s+blandas?|softgels?|perlas?"):
        return "capsula_blanda"
    if _tiene_palabra(t, r"aerosol\w*|sprays?|inhalador\w*|atomizador\w*|nebulizador\w*"):
        return "aerosol"
    if _tiene_palabra(t, r"inyect\w*|ampollas?|vial\w*|jeringas?"):
        return "inyectable"
    if _tiene_palabra(t, r"oftalmic[oa]s?|otic[oa]s?|goteros?|gotas?"):
        return "gotas"
    if _tiene_palabra(t, r"ovulos?"):
        return "ovulo"
    if _tiene_palabra(t, r"supositorios?"):
        return "supositorio"
    if _tiene_palabra(t, r"emulgeles?|gel(?:es)?|jaleas?"):
        return "gel"
    if _tiene_palabra(t, r"unguentos?|pomadas?"):
        return "unguento"
    if _tiene_palabra(t, r"cremas?"):
        return "crema"
    if _tiene_palabra(t, r"locion\w*"):
        return "locion"
    if _tiene_palabra(t, r"jarabes?|jbe|suspension\w*|susp|emulsion\w*|elixires?|solucion(?:es)?\s+oral(?:es)?"):
        return "jarabe"
    if _tiene_palabra(t, r"solucion\w*|enemas?"):
        return "solucion"
    if _tiene_palabra(t, r"polvos?|granulos?"):
        return "polvo"
    if _tiene_palabra(t, r"masticabl\w*"):
        return "tableta_masticable"
    if _tiene_palabra(t, r"tabletas?|tab|comprimidos?|comp|grageas?"):
        return "tableta"
    if _tiene_palabra(t, r"capsulas?|cap"):
        return "capsula"
    return None


def detectar_envase(texto: str) -> str | None:
    """Identifica el tipo de envase primario más específico declarado en el texto."""
    t = norm(texto)
    if not t:
        return None
    if _tiene_palabra(t, r"jeringas?\s*prellenadas?|jeringas?"):
        return "jeringa_prellenada"
    if _tiene_palabra(t, r"monodosis|unidosis"):
        return "monodosis"
    if _tiene_palabra(t, r"atomizador\w*|sprays?|inhalador\w*|aerosol\w*"):
        return "atomizador"
    if _tiene_palabra(t, r"goteros?"):
        return "gotero"
    if _tiene_palabra(t, r"ampollas?"):
        return "ampolla"
    if _tiene_palabra(t, r"vial\w*"):
        return "vial"
    if _tiene_palabra(t, r"tubos?|pomos?"):
        return "tubo"
    if _tiene_palabra(t, r"sobres?|sachets?"):
        return "sobre"
    if _tiene_palabra(t, r"frascos?|fco|botellas?"):
        return "frasco"
    if _tiene_palabra(t, r"blister\w*"):
        return "blister"
    if _tiene_palabra(t, r"cajas?"):
        return "caja"
    return None


def _quitar_frases_envase(t: str) -> str:
    return re.sub(
        r"\b(?:frasco|fco|tubo|sobre|sachet|caja|blister|bolsa|ampolla|vial|envase|jeringa(?:\s+prellenada)?)\s*(?:x\s*)?\d+(?:\.\d+)?\s*(?:ml|g|gr|un|l|monodosis|dosis)\b",
        " ",
        t,
    )


def extraer_concentracion(texto: str) -> tuple[tuple[float, str, float, str], ...]:
    """Extrae pares completos (valor, unidad, denom_valor, denom_unidad) sin colapsar denominadores."""
    t = norm(texto).split(" - ")[0]
    t = _quitar_frases_envase(t)
    patron = re.compile(
        r"(\d+(?:\.\d+)?)\s*(mg|mcg|ug|g|gr|ui|iu|%)(?:\s*/\s*(\d+(?:\.\d+)?)?\s*(ml|l|g|gr)\b)?",
    )
    encontrados: list[tuple[float, str, float, str]] = []
    for m in patron.finditer(t):
        val = float(m.group(1))
        u = m.group(2)
        if u in ("g", "gr") and val <= 5.0 and m.group(4) is None:
            val, u = val * 1000.0, "mg"
        elif u in ("g", "gr") and val <= 5.0 and m.group(4) is not None:
            val, u = val * 1000.0, "mg"
        elif u == "gr":
            u = "g"
        elif u == "ug":
            u = "mcg"
        elif u == "iu":
            u = "ui"

        if m.group(4) is not None:
            dval = float(m.group(3)) if m.group(3) else 1.0
            du = m.group(4)
            if du == "l":
                dval, du = dval * 1000.0, "ml"
            elif du == "gr":
                du = "g"
            denom = (round(dval, 4), du)
        else:
            denom = (0.0, "")

        encontrados.append((round(val, 4), u, denom[0], denom[1]))

    # En combinaciones ("400 mg + 30 mg / 5 mL"), el denominador final aplica al grupo completo.
    if encontrados and any(d != (0.0, "") for _, _, *d in encontrados):
        ultimo_denom = next(
            ((dv, du) for _, _, dv, du in reversed(encontrados) if du != ""),
            (0.0, ""),
        )
        if ultimo_denom[1] != "":
            encontrados = [
                (v, u, ultimo_denom[0] if du == "" else dv, ultimo_denom[1] if du == "" else du)
                for v, u, dv, du in encontrados
            ]

    return tuple(sorted(set(encontrados)))


def extraer_contenido_envase(texto: str, desde_presentacion: bool = False) -> tuple[frozenset[tuple[float, str]], frozenset[float]]:
    """Extrae contenido físico exacto ((valor, ml|g|monodosis|dosis)) y conteo explícito (un)."""
    bruto = texto or ""
    if not desde_presentacion and " - " in bruto:
        # En títulos externos ("Nombre 1 Gr - Tubo 20 G"), el envase siempre va después de " - ".
        segmento = norm(bruto.split(" - ", 1)[1])
    else:
        segmento = norm(bruto)

    # Solo quitar concentraciones explícitas (mg, mcg, %, ui o razones con "/"):
    # nunca eliminar gramos de tubo ("Tubo x 10 g") ni volúmenes de frasco ("Frasco x 120 mL").
    segmento = re.sub(
        r"\d+(?:\.\d+)?\s*(?:mg|mcg|ug|ui|iu|%)(?:\s*/\s*(?:\d+(?:\.\d+)?)?\s*(?:ml|l|g|gr)\b)?",
        " ",
        segmento,
    )
    segmento = re.sub(r"\d+(?:\.\d+)?\s*(?:g|gr)\s*/\s*(?:\d+(?:\.\d+)?)?\s*(?:ml|l|g|gr)\b", " ", segmento)

    fisicos: set[tuple[float, str]] = set()
    unidades: set[float] = set()

    for m in re.finditer(r"(\d+(?:\.\d+)?)\s*(ml|l|g|gr|monodosis|unidosis|dosis|un|tabletas?|capsulas?)\b", segmento):
        num = float(m.group(1))
        u = m.group(2)
        if u == "l":
            fisicos.add((round(num * 1000.0, 4), "ml"))
        elif u in ("g", "gr"):
            fisicos.add((round(num, 4), "g"))
        elif u == "ml":
            fisicos.add((round(num, 4), "ml"))
        elif u in ("monodosis", "unidosis"):
            fisicos.add((round(num, 4), "monodosis"))
        elif u == "dosis":
            fisicos.add((round(num, 4), "dosis"))
        else:
            unidades.add(round(num, 4))

    return frozenset(fisicos), frozenset(unidades)


def extraer_volumen_envase(texto: str, desde_presentacion: bool = False) -> set[str]:
    fisicos, _ = extraer_contenido_envase(texto, desde_presentacion=desde_presentacion)
    return {
        f"{int(v) if v.is_integer() else v:g}{u}"
        for v, u in fisicos
    }


def _envases_compatibles(loc_env: str | None, ext_env: str | None) -> bool:
    """Exige compatibilidad estricta de envase primario cuando el catálogo declara uno específico."""
    especificos = {"jeringa_prellenada", "monodosis", "atomizador", "ampolla", "vial", "tubo", "sobre", "frasco"}
    if loc_env in especificos:
        return ext_env == loc_env
    if ext_env in especificos and loc_env is not None and loc_env != ext_env:
        return False
    if loc_env == "gotero" and ext_env not in ("gotero", "frasco"):
        return False
    if loc_env == "blister" and ext_env == "sobre":
        return False
    return True


def palabras_clave_nombre(texto: str) -> list[str]:
    t = norm(texto).split(" - ")[0]
    tokens = re.findall(r"[a-z]+|\d+", t)
    res: list[str] = []
    for tok in tokens:
        if tok.isdigit():
            continue
        tok = SINGULARES.get(tok, tok)
        if tok in STOPWORDS_FORMA:
            continue
        res.append(tok)
    return res


def _forma_local_canonica(prod: dict[str, Any]) -> str | None:
    """Determina la forma farmacéutica local priorizando señales específicas (efervescente, cápsula blanda, aerosol, gotas)."""
    tipo = prod.get("tipo") or ""
    pres = prod.get("presentacion") or ""
    nom = prod.get("nombre") or ""
    f_nom = grupo_forma(nom)
    f_pres = grupo_forma(pres)
    f_tipo = grupo_forma(tipo)

    # Señales más específicas tienen prioridad aunque la columna `tipo` sea genérica
    for especial in ("efervescente", "capsula_blanda", "tableta_masticable", "aerosol", "gotas"):
        if especial in (f_nom, f_pres, f_tipo):
            return especial

    # Evitar contradicción directa entre tipo y presentación (p. ej. tipo TABLETA vs pres Cápsulas)
    if f_tipo and f_pres and f_tipo != f_pres:
        return f_pres
    return f_pres or f_tipo or f_nom


def cargar_exclusiones_manuales(ruta: Path = EXCLUSIONES_PATH) -> tuple[dict[int, str], dict[str, str]]:
    """Carga exclusiones manuales versionadas por producto_id y fuente_pid con su motivo."""
    if not ruta.exists():
        return {}, {}
    datos = json.loads(ruta.read_text(encoding="utf-8"))
    por_producto: dict[int, str] = {}
    por_fuente: dict[str, str] = {}
    for item in datos.get("exclusiones", []):
        motivo = str(item.get("motivo") or "Excluido manualmente").strip()
        if item.get("producto_id") is not None:
            por_producto[int(item["producto_id"])] = motivo
        if item.get("fuente_pid"):
            por_fuente[str(item["fuente_pid"]).strip()] = motivo
    return por_producto, por_fuente


def emparejar_productos(
    locales: list[dict[str, Any]],
    externos: list[ProductoExterno],
    exclusiones_producto: dict[int, str] | None = None,
    exclusiones_fuente: dict[str, str] | None = None,
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    """Empareja productos locales contra externos usando reglas conservadoras."""
    if exclusiones_producto is None or exclusiones_fuente is None:
        excl_prod_def, excl_fuente_def = cargar_exclusiones_manuales()
        if exclusiones_producto is None:
            exclusiones_producto = excl_prod_def
        if exclusiones_fuente is None:
            exclusiones_fuente = excl_fuente_def

    ext_prep = []
    for e in externos:
        if e.pid in exclusiones_fuente:
            continue
        parte_titulo = e.titulo.split(" - ")[0]
        fisicos_ext, unidades_ext = extraer_contenido_envase(e.titulo)
        ext_prep.append({
            "obj": e,
            "claves": set(palabras_clave_nombre(e.titulo)),
            "conc": extraer_concentracion(e.titulo),
            "forma": grupo_forma(e.titulo),
            "envase": detectar_envase(e.titulo),
            "fisicos": fisicos_ext,
            "unidades": unidades_ext,
            "vols": extraer_volumen_envase(e.titulo),
            "nums": set(re.findall(r"(?<!\d)\d+(?!\d)", norm(parte_titulo))),
        })

    emparejados: list[dict[str, Any]] = []
    sin_coincidencia: list[dict[str, Any]] = []

    for prod in locales:
        if int(prod["id"]) in exclusiones_producto:
            sin_coincidencia.append(prod)
            continue

        loc_claves = set(palabras_clave_nombre(prod["nombre"]))
        if not loc_claves:
            sin_coincidencia.append(prod)
            continue

        conc_txt = str(prod.get("concentracion") or "").strip()
        if re.fullmatch(r"0?\.\d+", conc_txt):
            pct = float(conc_txt) * 100.0
            conc_txt = f"{pct:g}%"

        # Separar con "|" evita que dígitos al final del nombre y al inicio de
        # concentración ("600" + "600") se fusionen en "600600".
        loc_conc = extraer_concentracion(f"{prod['nombre']} | {conc_txt}")
        pres_txt = prod.get("presentacion") or ""
        loc_forma = _forma_local_canonica(prod)
        loc_envase = detectar_envase(pres_txt)
        loc_fisicos, loc_unidades = extraer_contenido_envase(pres_txt, desde_presentacion=True)
        loc_vols = extraer_volumen_envase(pres_txt, desde_presentacion=True)
        # Números propios en el nombre (ej. 1200 en "AB-BRONCOL NF 1200", 70 en "ALCOHOL 70")
        loc_nums_nombre = set(re.findall(r"(?<!\d)\d+(?!\d)", norm(prod["nombre"])))

        mejor = None
        mejor_score = 0.0
        for ext in ext_prep:
            # 1. Identidad léxica exacta de palabras clave del medicamento
            if loc_claves != ext["claves"]:
                continue

            # 2. Si el nombre incluye un número identificador (ej. 1200, 600, 250, 70), debe existir
            if loc_nums_nombre and not loc_nums_nombre.issubset(ext["nums"]):
                continue

            # 3. Forma farmacéutica obligatoria y exacta (si origen o local la omiten o difieren, rechazar)
            if not loc_forma or not ext["forma"] or loc_forma != ext["forma"]:
                continue

            # 4. Tipo de envase primario compatible (no mezclar jeringa prellenada con ampolla, etc.)
            if not _envases_compatibles(loc_envase, ext["envase"]):
                continue

            # 5. Contenido físico exacto (mL, g, monodosis, dosis): si uno lo declara y difiere, rechazar
            if loc_fisicos or ext["fisicos"]:
                if loc_fisicos != ext["fisicos"]:
                    continue

            # 5b. Si ambos declaran conteo explícito de unidades de envase, debe coincidir
            if loc_unidades and ext["unidades"] and loc_unidades != ext["unidades"]:
                continue

            # 6. Concentración completa estricta (numerador + denominador)
            score = 60.0
            if loc_conc and ext["conc"]:
                if loc_conc == ext["conc"]:
                    score += 25.0
                else:
                    continue
            elif not loc_conc and not ext["conc"]:
                score += 10.0
            else:
                # Si uno declara concentración y el otro no (sin importar la forma), no arriesgar
                continue

            score += 10.0  # forma exacta garantizada
            if loc_vols and ext["vols"] and (loc_vols & ext["vols"]):
                score += 10.0

            if score > mejor_score:
                mejor_score = score
                mejor = ext["obj"]

        if mejor is not None and mejor_score >= UMBRAL_CONSERVADOR:
            emparejados.append({
                "producto_id": prod["id"],
                "nombre": prod["nombre"],
                "concentracion": prod.get("concentracion"),
                "presentacion": prod.get("presentacion"),
                "tipo": prod.get("tipo"),
                "fuente_pid": mejor.pid,
                "fuente_titulo": mejor.titulo,
                "fuente_href": mejor.href,
                "fuente_imagen_url": mejor.imagen_url,
                "puntaje": round(mejor_score, 1),
            })
        else:
            sin_coincidencia.append(prod)

    return emparejados, sin_coincidencia


def leer_env_backend() -> dict[str, str]:
    env_path = BACKEND_DIR / ".env"
    valores: dict[str, str] = {}
    for linea in env_path.read_text(encoding="utf-8").splitlines():
        linea = linea.strip()
        if not linea or linea.startswith("#") or "=" not in linea:
            continue
        k, v = linea.split("=", 1)
        valores[k.strip()] = v.strip().strip("\"'")
    return valores


def cargar_productos_locales(ruta_json: Path | None = None) -> list[dict[str, Any]]:
    if ruta_json and ruta_json.exists():
        return json.loads(ruta_json.read_text(encoding="utf-8"))

    env = leer_env_backend()
    conn = psycopg2.connect(
        host=env.get("DB_HOST", "127.0.0.1"),
        port=int(env.get("DB_PORT", "5432")),
        dbname=env.get("DB_DATABASE", "botica_san_juan"),
        user=env.get("DB_USERNAME", "postgres"),
        password=env.get("DB_PASSWORD", ""),
    )
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT p.id, p.nombre, p.concentracion, p.adicional, p.laboratorio,
                       p.presentacion, p.tipo, COALESCE(p.principio_activo, pa.nombre) AS principio_activo,
                       p.imagen
                FROM productos p
                LEFT JOIN principios_activos pa ON pa.id = p.principio_activo_id
                ORDER BY p.id
                """
            )
            cols = [d[0] for d in cur.description]
            return [dict(zip(cols, fila)) for fila in cur.fetchall()]
    finally:
        conn.close()


def url_alta_resolucion(imagen_url: str) -> str:
    """Solicita variante 600x600 nítida del CDN Demandware manteniendo el mismo recurso."""
    base = imagen_url.split("?")[0]
    return f"{base}?sw=600&sh=600"


def optimizar_a_webp(datos_crudos: bytes, destino: Path) -> tuple[int, int, int] | None:
    """Convierte y optimiza a WebP <= 600x600 con fondo limpio; devuelve (w, h, bytes) o None."""
    try:
        with Image.open(io.BytesIO(datos_crudos)) as img:
            img = ImageOps.exif_transpose(img)
            if img.width < 100 or img.height < 100:
                return None

            # Conservar transparencia si existe; si viene en modo paleta/CMYK, pasar a RGBA/RGB
            if img.mode in ("RGBA", "LA") or (img.mode == "P" and "transparency" in img.info):
                img = img.convert("RGBA")
            else:
                img = img.convert("RGB")

            img.thumbnail((LADO_MAXIMO_WEBP, LADO_MAXIMO_WEBP), Image.Resampling.LANCZOS)
            destino.parent.mkdir(parents=True, exist_ok=True)
            img.save(destino, format="WEBP", quality=CALIDAD_WEBP, method=6)
            return img.width, img.height, destino.stat().st_size
    except Exception:
        return None


def main() -> int:
    parser = argparse.ArgumentParser(description="Extractor de imágenes del catálogo Botica San Juan")
    parser.add_argument("--solo-indexar", action="store_true", help="Solo indexa el catálogo público")
    parser.add_argument("--solo-emparejar", action="store_true", help="Solo evalúa coincidencias sin descargar imágenes")
    parser.add_argument("--productos-json", type=Path, default=None, help="JSON opcional con productos locales")
    args = parser.parse_args()

    cliente: ClienteHttpRespetuoso | None = None
    try:
        cliente = ClienteHttpRespetuoso(CACHE_DIR)
        externos = indexar_catalogo_externo(cliente)
        print(f"Catálogo externo indexado: {len(externos)} productos")

        if args.solo_indexar:
            return 0

        locales = cargar_productos_locales(args.productos_json)
        emparejados, sin_coincidencia = emparejar_productos(locales, externos)
        print(
            f"Emparejamiento conservador (umbral >= {UMBRAL_CONSERVADOR}): "
            f"{len(emparejados)}/{len(locales)} productos emparejados "
            f"({len(sin_coincidencia)} conservan respaldo)."
        )

        if args.solo_emparejar:
            for m in emparejados:
                print(
                    f"  [OK {m['puntaje']}] #{m['producto_id']} {m['nombre']} | conc={m['concentracion']} | "
                    f"pres={m['presentacion']} | tipo={m['tipo']} -> {m['fuente_titulo']} [{m['fuente_pid']}]"
                )
            return 0

        # Descargar y optimizar imágenes únicas por pid externo
        SALIDA_IMAGENES_DIR.mkdir(parents=True, exist_ok=True)
        cache_pid_archivo: dict[str, str] = {}
        correspondencias: list[dict[str, Any]] = []
        fallidas = 0

        pids_unicos = list(dict.fromkeys(m["fuente_pid"] for m in emparejados))
        print(f"Descargando/optimizando {len(pids_unicos)} imágenes únicas...")

        for idx, m in enumerate(emparejados, start=1):
            pid_ext = m["fuente_pid"]
            if pid_ext not in cache_pid_archivo:
                slug_pid = re.sub(r"[^a-zA-Z0-9_-]+", "-", pid_ext).strip("-").lower()
                nombre_webp = f"{slug_pid}.webp"
                ruta_salida = SALIDA_IMAGENES_DIR / nombre_webp

                if not ruta_salida.exists():
                    url_img = url_alta_resolucion(m["fuente_imagen_url"])
                    ext_orig = m["fuente_imagen_url"].split("?")[0].split(".")[-1].lower()
                    if ext_orig not in ("jpg", "jpeg", "png", "webp"):
                        ext_orig = "img"
                    crudos = cliente.obtener_binario(f"raw/{slug_pid}.{ext_orig}", url_img)
                    if crudos is None:
                        cache_pid_archivo[pid_ext] = ""
                    else:
                        meta = optimizar_a_webp(crudos, ruta_salida)
                        cache_pid_archivo[pid_ext] = f"storage/productos/{nombre_webp}" if meta else ""
                else:
                    cache_pid_archivo[pid_ext] = f"storage/productos/{nombre_webp}"

                if len(cache_pid_archivo) % 25 == 0:
                    print(f"  Procesadas {len(cache_pid_archivo)}/{len(pids_unicos)} imágenes únicas...", flush=True)

            ruta_rel = cache_pid_archivo[pid_ext]
            if not ruta_rel:
                fallidas += 1
                continue

            correspondencias.append({
                "producto_id": m["producto_id"],
                "nombre": m["nombre"],
                "concentracion": m["concentracion"],
                "presentacion": m["presentacion"],
                "imagen": ruta_rel,
                "fuente_pid": pid_ext,
                "fuente_titulo": m["fuente_titulo"],
                "fuente_url": f"{BASE_URL}{m['fuente_href']}" if m["fuente_href"].startswith("/") else m["fuente_href"],
                "puntaje": m["puntaje"],
            })

        archivos_activos = {Path(c["imagen"]).name for c in correspondencias}
        for archivo_webp in SALIDA_IMAGENES_DIR.glob("*.webp"):
            if archivo_webp.name not in archivos_activos:
                archivo_webp.unlink(missing_ok=True)

        CORRESPONDENCIA_PATH.parent.mkdir(parents=True, exist_ok=True)
        payload = {
            "fuente": BASE_URL,
            "total_catalogo": len(locales),
            "total_emparejados": len(correspondencias),
            "total_sin_coincidencia": len(locales) - len(correspondencias),
            "correspondencias": correspondencias,
        }
        CORRESPONDENCIA_PATH.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        print(
            f"Listo: {len(correspondencias)} productos con foto WebP en {SALIDA_IMAGENES_DIR} "
            f"(fallidas={fallidas}). Correspondencia guardada en {CORRESPONDENCIA_PATH}."
        )
        return 0
    except BloqueoSitioError as exc:
        print(f"DETENIDO POR SEGURIDAD: {exc}", file=sys.stderr)
        return 2
    finally:
        if cliente is not None:
            cliente.cerrar()


if __name__ == "__main__":
    raise SystemExit(main())
