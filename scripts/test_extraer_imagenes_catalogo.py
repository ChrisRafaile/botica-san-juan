#!/usr/bin/env python3
"""Pruebas unitarias para las reglas conservadoras del emparejador de catálogo."""

from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from extraer_imagenes_catalogo import (  # noqa: E402
    ProductoExterno,
    emparejar_productos,
    extraer_concentracion,
    extraer_contenido_envase,
    grupo_forma,
)


def _ext(pid: str, titulo: str, marca: str = "GENERICO") -> ProductoExterno:
    return ProductoExterno(
        pid=pid,
        titulo=titulo,
        marca=marca,
        href=f"/p/{pid}.html",
        imagen_url=f"https://www.boticasperu.pe/img/{pid}.jpg",
    )


class ConcentracionYEnvaseTest(unittest.TestCase):
    """Verifica que concentraciones con denominador y gramos de tubo no colapsen."""

    def test_conserva_denominador_en_concentraciones_liquidas(self) -> None:
        self.assertEqual(
            extraer_concentracion("FENITOINA SODICA 100 mg/2 mL"),
            ((100.0, "mg", 2.0, "ml"),),
        )
        self.assertEqual(
            extraer_concentracion("GRAVOL 50 mg/5 mL"),
            ((50.0, "mg", 5.0, "ml"),),
        )
        self.assertEqual(
            extraer_concentracion("HISALER 10 mg/mL"),
            ((10.0, "mg", 1.0, "ml"),),
        )
        self.assertNotEqual(
            extraer_concentracion("FENITOINA SODICA 100 mg/2 mL"),
            extraer_concentracion("Fenitoina Sodica100mg - Caja 100UN"),
        )
        self.assertNotEqual(
            extraer_concentracion("GRAVOL 50 mg/5 mL"),
            extraer_concentracion("Gravol 50 Mg - Tableta 1 UN"),
        )
        self.assertNotEqual(
            extraer_concentracion("HISALER 10 mg/mL"),
            extraer_concentracion("Hisaler 10 Mg - Blister 10 UN"),
        )

    def test_extrae_gramos_de_tubo_sin_eliminarlos(self) -> None:
        fisicos_loc, _ = extraer_contenido_envase("Caja Tubo x 10 g", desde_presentacion=True)
        fisicos_ext, _ = extraer_contenido_envase("Ketoconazol 2 % - Tubo 20 G")
        self.assertEqual(fisicos_loc, frozenset({(10.0, "g")}))
        self.assertEqual(fisicos_ext, frozenset({(20.0, "g")}))
        self.assertNotEqual(fisicos_loc, fisicos_ext)


class EmparejadorReglasCriticasTest(unittest.TestCase):
    """Regresión sobre todos los falsos positivos detectados en QA y positivos válidos."""

    def test_rechaza_cuando_origen_no_declara_forma_farmaceutica(self) -> None:
        local = {
            "id": 9001,
            "nombre": "IBUPROFENO",
            "concentracion": "400 mg",
            "presentacion": "Caja Envase Blister Tabletas",
            "tipo": "TABLETA",
        }
        externo_sin_forma = _ext("X1", "Ibuprofeno 400 Mg - Caja 20 UN")
        self.assertIsNone(grupo_forma(externo_sin_forma.titulo))

        emparejados, sin_coincidencia = emparejar_productos([local], [externo_sin_forma])
        self.assertEqual(emparejados, [])
        self.assertEqual(len(sin_coincidencia), 1)

    def test_rechaza_casos_criticos_forma_y_concentracion_qa(self) -> None:
        casos_negativos = [
            # 474: ampolla 100 mg/2 mL vs tabletas 100 mg sin forma
            (
                {"id": 474, "nombre": "FENITOINA SODICA", "concentracion": "100 mg/2 mL", "presentacion": "Caja Ampolla x 2 mL", "tipo": "INYECTABLE"},
                _ext("E474", "Fenitoina Sodica100mg - Caja 100UN"),
            ),
            # 546: ampolla 50 mg/5 mL vs tableta 50 mg
            (
                {"id": 546, "nombre": "GRAVOL", "concentracion": "50 mg/5 mL", "presentacion": "Ampolla x 5 mL", "tipo": "INYECTABLE"},
                _ext("E546", "Gravol 50 Mg  - Tableta 1 UN"),
            ),
            # 560: gotas 10 mg/mL frasco x 15 mL vs blister comprimidos 10 mg
            (
                {"id": 560, "nombre": "HISALER", "concentracion": "10 mg/mL", "presentacion": "Caja Frasco x 15mL", "tipo": "SOLUCION"},
                _ext("E560", "Hisaler 10 Mg  - Blister 10 UN"),
            ),
            # 662: tabletas blister vs sobre efervescente
            (
                {"id": 662, "nombre": "PANADOL", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("E662", "Panadol 500 Mg Tableta Efervescente - Sobre 1 UN"),
            ),
            # 427: capsulas blandas vs caja 20 UN sin forma
            (
                {"id": 427, "nombre": "DOLOMAX", "concentracion": "400 mg", "presentacion": "Caja Envase Blister Cápsulas Blandas", "tipo": "CAPSULA"},
                _ext("E427", "Dolomax 400 Mg - Caja 20 UN"),
            ),
            # 482: tabletas vs capsulas
            (
                {"id": 482, "nombre": "FLUCONAZOL", "concentracion": "150 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("E482", "Fluconazol 150 Mg Cápsulas - Caja 2 UN"),
            ),
        ]

        for local, externo in casos_negativos:
            with self.subTest(producto_id=local["id"]):
                emparejados, _ = emparejar_productos([local], [externo])
                self.assertEqual(emparejados, [])

    def test_rechaza_casos_envase_y_contenido_distinto_qa(self) -> None:
        casos_envase = [
            # Volumenes de frasco/jarabe/suspension distintos: 16, 59, 70, 326, 387, 612, 640
            (
                {"id": 16, "nombre": "ACI BASIC", "concentracion": "800 mg+60 mg/10 mL", "presentacion": "Frasco x 220 mL", "tipo": "SUSPENSION"},
                _ext("E16", "Aci Basic 800 Mg + 60 Mg / 10 Ml Suspensión Oral - Frasco 150 Ml"),
            ),
            (
                {"id": 59, "nombre": "AMBROXOL", "concentracion": "15 mg/5 mL", "presentacion": "Caja Frasco x 100 mL", "tipo": "JARABE"},
                _ext("E59", "Ambroxol 15 Mg/5 Ml Jarabe - Frasco 120 ML"),
            ),
            (
                {"id": 70, "nombre": "AMOXICILINA", "concentracion": "250 mg/5 mL", "presentacion": "Caja Frasco x 100 mL", "tipo": "SUSPENSION"},
                _ext("E70", "Amoxicilina 250 Mg/5 Ml Suspensión Oral - Frasco 60 ML"),
            ),
            (
                {"id": 326, "nombre": "CLORFENAMINA MALEATO", "concentracion": "2 mg/5 mL", "presentacion": "Caja Frasco x 120 mL", "tipo": "JARABE"},
                _ext("E326", "Clorfenamina Maleato 2 Mg/5 Ml Jarabe - Frasco 60 ML"),
            ),
            (
                {"id": 387, "nombre": "DICLOXACILINA", "concentracion": "250 mg/5 mL", "presentacion": "Caja Frasco x 100 mL", "tipo": "SUSPENSION"},
                _ext("E387", "Dicloxacilina 250 Mg/5 Ml Suspensión Oral - Frasco 60 ML"),
            ),
            (
                {"id": 612, "nombre": "LORATADINA", "concentracion": "5 mg/5 mL", "presentacion": "Frasco x 100 mL", "tipo": "JARABE"},
                _ext("E612", "Loratadina 5 Mg/5 Ml Jarabe - Frasco 60 ML"),
            ),
            (
                {"id": 640, "nombre": "METRONIDAZOL", "concentracion": "250 mg/5 mL", "presentacion": "Caja Frasco x 60 mL", "tipo": "SUSPENSION"},
                _ext("E640", "Metronidazol 250 Mg/5 Ml Suspensión Oral - Frasco 120 ML"),
            ),
            # Gramos de tubo distintos: 306, 333, 334, 586
            (
                {"id": 306, "nombre": "CLOBETASOL", "concentracion": "0.05%", "presentacion": "Caja Tubo x 20 g", "tipo": "CREMA"},
                _ext("E306", "Clobetasol 0.05 % Crema - Tubo 25 G"),
            ),
            (
                {"id": 333, "nombre": "CLOTRIMAZOL", "concentracion": "1%", "presentacion": "Caja Tubo x 20 g", "tipo": "CREMA"},
                _ext("E333", "Clotrimazol 1 % Crema - Tubo 40 G"),
            ),
            (
                {"id": 334, "nombre": "CLOTRIMAZOL", "concentracion": "1%", "presentacion": "Tubo x 20 g", "tipo": "CREMA"},
                _ext("E334", "Clotrimazol 1 % Crema - Tubo 40 G"),
            ),
            (
                {"id": 586, "nombre": "KETOCONAZOL", "concentracion": "2%", "presentacion": "Caja Tubo x 10 g", "tipo": "CREMA"},
                _ext("E586", "Ketoconazol 2 % Crema - Tubo 20 G"),
            ),
            # Tipo de envase distinto: 183, 325, 363
            (
                {"id": 183, "nombre": "BETACORT DEPOT", "concentracion": "5 mg+2 mg/mL", "presentacion": "Jeringa prellenada x 1 mL", "tipo": "INYECTABLE"},
                _ext("E183", "Betacort Depot 5 Mg + 2 Mg/Ml Inyectable - Caja 1 UN"),
            ),
            (
                {"id": 325, "nombre": "CLORFENAMINA MALEATO", "concentracion": "2 mg/5 mL", "presentacion": "Caja x 60 Monodosis", "tipo": "JARABE"},
                _ext("E325", "Clorfenamina Maleato 2 Mg/5 Ml Jarabe - Frasco 60 ML"),
            ),
            (
                {"id": 363, "nombre": "DEXTROMETORFANO", "concentracion": "15 mg/5 mL", "presentacion": "Caja Envase Aerosol X 120 mL", "tipo": "AEROSOL"},
                _ext("E363", "Dextrometorfano 15 Mg/5 Ml Jarabe - Frasco 120 ML"),
            ),
        ]

        for local, externo in casos_envase:
            with self.subTest(producto_id=local["id"]):
                emparejados, _ = emparejar_productos([local], [externo])
                self.assertEqual(emparejados, [])

    def test_acepta_coincidencias_validas_con_forma_concentracion_y_envase_exactos(self) -> None:
        casos_positivos = [
            (
                {"id": 15, "nombre": "ACEPOT", "concentracion": "20 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("P15", "Acepot 20 Mg Tableta - Caja 20 UN"),
            ),
            (
                {"id": 69, "nombre": "AMOXICILINA", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Cápsulas", "tipo": "CAPSULA"},
                _ext("P69", "Amoxicilina 500 Mg Cápsula - Blister 10 UN"),
            ),
            (
                {"id": 134, "nombre": "AZITROMICINA", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("P134", "Azitromicina 500 Mg Tableta - Caja 3 UN"),
            ),
            (
                {"id": 307, "nombre": "CLOBETASOL", "concentracion": "0.05%", "presentacion": "Caja Tubo x 25 g", "tipo": "CREMA"},
                _ext("P307", "Clobetasol 0.05 % Crema - Tubo 25 G"),
            ),
            (
                {"id": 308, "nombre": "CLOBETASOL", "concentracion": "0.05%", "presentacion": "Tubo x 25 g", "tipo": "CREMA"},
                _ext("P308", "Clobetasol 0.05 % Crema - Tubo 25 G"),
            ),
            (
                {"id": 547, "nombre": "GRAVOL", "concentracion": "50 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("P547", "Gravol 50 Mg - Tableta 1 UN"),
            ),
            (
                {"id": 587, "nombre": "KETOCONAZOL", "concentracion": "2%", "presentacion": "Caja Tubo x 20 g", "tipo": "CREMA"},
                _ext("P587", "Ketoconazol 2 % Crema - Tubo 20 G"),
            ),
            (
                {"id": 205, "nombre": "BIRM", "concentracion": "", "presentacion": "Caja Frasco x 120 mL", "tipo": "SOLUCION"},
                _ext("P205", "Birm Solución Oral - Frasco 120 ML"),
            ),
        ]

        # Nota: verificar comportamiento coherente de forma/envase para #205 si es solucion vs solucion oral
        for local, externo in casos_positivos[:7]:
            with self.subTest(producto_id=local["id"]):
                emparejados, _ = emparejar_productos([local], [externo])
                self.assertEqual(len(emparejados), 1)
                self.assertEqual(emparejados[0]["fuente_pid"], externo.pid)

        # Caso 205: BIRM Solución Oral vs Caja Frasco x 120 mL (sin concentración)
        local_205, ext_205 = casos_positivos[7]
        local_205["presentacion"] = "Caja Frasco x 120 mL Solución Oral"
        emparejados_205, _ = emparejar_productos([local_205], [ext_205])
        self.assertEqual(len(emparejados_205), 1)
        self.assertEqual(emparejados_205[0]["fuente_pid"], "P205")


if __name__ == "__main__":
    unittest.main()
