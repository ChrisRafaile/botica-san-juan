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


def grupo_forma(tipo_o_texto: str) -> str | None:
    t = norm(tipo_o_texto)
    if any(w in t for w in ("inyect", "ampolla", "vial", "jeringa")):
        return "inyectable"
    if any(w in t for w in ("oftalm", "otica", "otico", "gotero", "gotas", "gota")):
        return "gotas"
    if any(w in t for w in ("jarabe", "jbe", "suspension", "susp", "emulsion")):
        return "liquido_oral"
    if any(w in t for w in ("solucion", "enema", "locion")):
        return "solucion"
    if any(w in t for w in ("crema", "unguento", "pomada", "gel", "emulgel", "jalea")):
        return "topico"
    if any(w in t for w in ("aerosol", "spray", "inhalador")):
        return "aerosol"
    if any(w in t for w in ("ovulo", "supositorio")):
        return "ovulo_sup"
    if any(w in t for w in ("polvo", "granulo")):
        return "polvo_sobre"
    if any(w in t for w in ("tableta", "comprimido", "gragea")):
        return "tableta"
    if any(w in t for w in ("capsula",)):
        return "capsula"
    return None


def extraer_concentracion(texto: str) -> list[tuple[float, str]]:
    """Extrae pares canónicos (valor, unidad) de principios activos, sin volúmenes de envase."""
    t = norm(texto).split(" - ")[0]
    t = re.sub(
        r"\b(?:frasco|fco|tubo|sobre|caja|blister|bolsa|ampolla|vial)\s*(?:x\s*)?\d+(?:\.\d+)?\s*(?:ml|g|gr|un|l)\b",
        " ",
        t,
    )
    pares: list[tuple[float, str]] = []
    # Capturar primero expresiones con denominador /5 ml o /ml (ej. 250 mg + 62.5 mg/5 mL)
    # Normalizamos cada cantidad de mg/mcg/g/ui/% individualmente, y guardamos denominador si existe
    for m in re.finditer(r"(\d+(?:\.\d+)?)\s*(mg|mcg|ug|g|gr|ui|iu|%)", t):
        val = float(m.group(1))
        u = m.group(2)
        if u in ("g", "gr") and val <= 5.0:
            val, u = val * 1000.0, "mg"
        elif u == "ug":
            u = "mcg"
        elif u == "iu":
            u = "ui"
        pares.append((round(val, 4), u))
    return sorted(set(pares))


def extraer_volumen_envase(texto: str) -> set[str]:
    t = norm(texto)
    vols = set()
    for m in re.finditer(r"(?:frasco|fco|tubo|sobre|ampolla|bolsa|sachet)?\s*(?:x\s*)?(\d+(?:\.\d+)?)\s*(ml|g|gr|l)\b", t):
        num = float(m.group(1))
        u = "g" if m.group(2) == "gr" else m.group(2)
        num_str = str(int(num)) if num.is_integer() else f"{num:g}"
        vols.add(f"{num_str}{u}")
    return vols


def extraer_volumenes_ml_o_g(texto: str) -> list[tuple[float, str]]:
    """Extrae volúmenes o pesos de envase en ml o g normalizados para validar tamaño."""
    t = norm(texto)
    salida: list[tuple[float, str]] = []
    for m in re.finditer(r"(?:frasco|fco|tubo|sobre|ampolla|bolsa|sachet|envase)?\s*(?:x\s*)?(\d+(?:\.\d+)?)\s*(ml|g|gr|l)\b", t):
        num = float(m.group(1))
        u = m.group(2)
        if u == "l":
            num, u = num * 1000.0, "ml"
        elif u == "gr":
            u = "g"
        salida.append((num, u))
    return salida


def volumenes_incompatibles(loc_txt: str, ext_txt: str) -> bool:
    """Rechaza diferencias extremas de tamaño de envase (p. ej. 1 L frente a 100 mL)."""
    loc_v = extraer_volumenes_ml_o_g(loc_txt)
    ext_v = extraer_volumenes_ml_o_g(ext_txt)
    if not loc_v or not ext_v:
        return False
    for lv, lu in loc_v:
        for ev, eu in ext_v:
            if lu == eu and lv > 0 and ev > 0:
                ratio = max(lv, ev) / min(lv, ev)
                if ratio <= 2.5:
                    return False
    # Si ninguno comparte unidad compatible dentro del rango 2.5x y ambos tienen unidades comparables
    unidades_loc = {u for _, u in loc_v}
    unidades_ext = {u for _, u in ext_v}
    return bool(unidades_loc & unidades_ext)


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


def emparejar_productos(
    locales: list[dict[str, Any]],
    externos: list[ProductoExterno],
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    """Empareja productos locales contra externos usando reglas conservadoras."""
    ext_prep = []
    for e in externos:
        ext_prep.append({
            "obj": e,
            "claves": set(palabras_clave_nombre(e.titulo)),
            "conc": extraer_concentracion(e.titulo),
            "forma": grupo_forma(e.titulo),
            "vols": extraer_volumen_envase(e.titulo),
            "nums": set(re.findall(r"(?<!\d)\d+(?!\d)", norm(e.titulo).split(" - ")[0])),
        })

    emparejados: list[dict[str, Any]] = []
    sin_coincidencia: list[dict[str, Any]] = []

    for prod in locales:
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
        loc_forma_tipo = grupo_forma(prod.get("tipo") or "")
        loc_forma_pres = grupo_forma(pres_txt)
        loc_forma = loc_forma_tipo or loc_forma_pres
        loc_formas_compatibles = {f for f in (loc_forma_tipo, loc_forma_pres) if f}
        if {"solucion", "liquido_oral"} & loc_formas_compatibles:
            loc_formas_compatibles.update({"solucion", "liquido_oral"})
        if "gotas" in loc_formas_compatibles:
            loc_formas_compatibles.add("liquido_oral")
        loc_vols = extraer_volumen_envase(pres_txt)
        # Números propios en el nombre (ej. 1200 en "AB-BRONCOL NF 1200", 70 en "ALCOHOL 70")
        loc_nums_nombre = set(re.findall(r"(?<!\d)\d+(?!\d)", norm(prod["nombre"])))
        suma_conc_mg = round(sum(v for v, u in loc_conc if u == "mg"), 4) if loc_conc else 0.0
        es_combinacion_generica = "+" in (prod.get("nombre") or "")

        mejor = None
        mejor_score = 0.0
        for ext in ext_prep:
            # 1. Identidad léxica exacta de palabras clave del medicamento
            if loc_claves != ext["claves"]:
                continue

            # 2. Si el nombre incluye un número identificador (ej. 1200, 600, 250, 70), debe existir
            if loc_nums_nombre and not loc_nums_nombre.issubset(ext["nums"]):
                continue

            # 3. Forma farmacéutica compatible (no mezclar tabletas con jarabes/inyectables/cremas)
            if loc_formas_compatibles and ext["forma"] and ext["forma"] not in loc_formas_compatibles:
                continue

            # 3b. Evitar diferencias extremas de tamaño (p. ej. envase de 1 L frente a frasco de 100 mL)
            if volumenes_incompatibles(pres_txt, ext["obj"].titulo):
                continue

            # 4. Concentración estricta
            score = 60.0
            if loc_conc and ext["conc"]:
                if loc_conc == ext["conc"]:
                    score += 25.0
                elif len(ext["conc"]) == 1 and ext["conc"][0][1] == "mg" and suma_conc_mg > 0 and ext["conc"][0][0] == suma_conc_mg:
                    # Caso AB-BRONCOL NF 1200: 1000mg + 200mg = 1200mg en título externo
                    score += 20.0
                else:
                    continue
            elif not loc_conc and not ext["conc"]:
                score += 10.0
            elif loc_conc and not ext["conc"]:
                # Combinaciones genéricas ("A + B") sin concentración declarada en fuente externa
                # pueden corresponder a múltiples dosis (p. ej. 125 vs 250 vs 400): no arriesgar.
                if es_combinacion_generica:
                    continue
                score += 2.0
            else:
                score -= 5.0

            if loc_forma and ext["forma"] and (loc_forma == ext["forma"] or ext["forma"] in loc_formas_compatibles):
                score += 10.0

            if loc_vols and ext["vols"]:
                if loc_vols & ext["vols"]:
                    score += 10.0
                else:
                    score -= 10.0

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
            for m in emparejados[:25]:
                print(
                    f"  [OK {m['puntaje']}] #{m['producto_id']} {m['nombre']} ({m['concentracion']}) "
                    f"-> {m['fuente_titulo']} [{m['fuente_pid']}]"
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
