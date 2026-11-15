#!/usr/bin/env python3
"""Pruebas unitarias para las reglas conservadoras del emparejador de catálogo."""

from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from extraer_imagenes_catalogo import (  # noqa: E402
    ProductoExterno,
    cargar_exclusiones_manuales,
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
                {"id": 565, "nombre": "IBUPROFENO", "concentracion": "800 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("20235", "Ibuprofeno 800 Mg Tabletas - Caja 100 UN"),
            ),
            (
                {"id": 587, "nombre": "KETOCONAZOL", "concentracion": "2%", "presentacion": "Caja Tubo x 20 g", "tipo": "CREMA"},
                _ext("P587", "Ketoconazol 2 % Crema - Tubo 20 G"),
            ),
            (
                {"id": 8801, "nombre": "BIRM", "concentracion": "", "presentacion": "Caja Frasco x 120 mL Solución Oral", "tipo": "SOLUCION"},
                _ext("P8801", "Birm Solución Oral - Frasco 120 ML"),
            ),
        ]

        for local, externo in casos_positivos:
            with self.subTest(producto_id=local["id"]):
                emparejados, _ = emparejar_productos([local], [externo])
                self.assertEqual(len(emparejados), 1)
                self.assertEqual(emparejados[0]["fuente_pid"], externo.pid)

    def test_rechaza_exclusiones_manuales_por_foto_origen_incorrecta(self) -> None:
        por_producto, por_fuente = cargar_exclusiones_manuales()
        for producto_id in (191, 205, 547):
            self.assertIn(producto_id, por_producto)
            self.assertTrue(por_producto[producto_id])

        casos_excluidos = [
            (
                {"id": 191, "nombre": "BETAPLUSS", "concentracion": " 0.05 %", "presentacion": "caja tubo x 20 g", "tipo": "CREMA"},
                _ext("08976", "Betapluss 0.05% Crema - Tubo 20 G"),
            ),
            (
                {"id": 205, "nombre": "BISMUTOL", "concentracion": "262 mg", "presentacion": "Caja Sobre x 2 Tabletas Masticables", "tipo": "TABLETA"},
                _ext("43664", "Bismutol 262 Mg Tableta Masticable - Sobre 2 UN"),
            ),
            (
                {"id": 547, "nombre": "GRAVOL", "concentracion": "50 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("28640-TABLETA", "Gravol 50 Mg  - Tableta 1 UN"),
            ),
        ]

        for local, externo in casos_excluidos:
            with self.subTest(producto_id=local["id"]):
                self.assertIn(externo.pid, por_fuente)
                emparejados, sin_coincidencia = emparejar_productos([local], [externo])
                self.assertEqual(emparejados, [])
                self.assertEqual(len(sin_coincidencia), 1)

                # Incluso bajo otro producto_id, la imagen de origen excluida no debe usarse.
                clon_local = {**local, "id": 99000 + local["id"]}
                emparejados_por_fuente, _ = emparejar_productos([clon_local], [externo])
                self.assertEqual(emparejados_por_fuente, [])

                # Tampoco debe saltarse exclusiones al activar la segunda pasada.
                emp_p2, _ = emparejar_productos([local], [externo], segunda_pasada=True)
                self.assertEqual(emp_p2, [])
                emp_clon_p2, _ = emparejar_productos([clon_local], [externo], segunda_pasada=True)
                self.assertEqual(emp_clon_p2, [])

    def test_segunda_pasada_controlada_solo_se_activa_con_parametro_y_resuelve_variantes_seguras(self) -> None:
        casos_segunda_pasada = [
            # Variación de adjetivo de forma ("recubierto") + blister vs caja
            (
                {"id": 345, "nombre": "DAFLON", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("38370-CAJA", "Daflon 500 Mg Comprimido Recubierto - Caja 30 UN"),
            ),
            # Sal farmacéutica omitida en nombre comercial
            (
                {"id": 635, "nombre": "METFORMINA CLORHIDRATO", "concentracion": "850 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                _ext("13902", "Metformina 850 Mg Tableta - Caja 30 UN"),
            ),
            # Solución oral DIGEMID sin palabra "oral" frente a Jarabe en igual frasco y concentración
            (
                {"id": 58, "nombre": "AMBROXOL", "concentracion": "15 mg/5 mL", "presentacion": "Frasco x 120 mL", "tipo": "SOLUCION"},
                _ext("14763", "Ambroxol 15mg/5ml Jarabe - Frasco 120 ML"),
            ),
            # Inyectable donde el volumen está en la concentración y el título externo termina en Caja N UN
            (
                {"id": 63, "nombre": "AMIKACINA", "concentracion": "500 mg/2 mL", "presentacion": "Ampolla x 2mL", "tipo": "INYECTABLE"},
                _ext("41915", "Amikacina 500Mg/2Ml Ampolla - Caja 10 UN"),
            ),
            # Gotero en caja vs Caja 15 ML con misma concentración y sabor
            (
                {"id": 33, "nombre": "AERO OM SABOR A FRESAS", "concentracion": "100 mg/mL", "presentacion": "Caja Frasco gotero x 15mL", "tipo": "SUSPENSION"},
                _ext("04458", "Gotas Aero OM 100 mg/mL Sabor Fresa - Caja 15 ML"),
            ),
            # Título externo sin palabra de forma cuando envase blister fija sólido oral sin ambigüedad
            (
                {"id": 275, "nombre": "CELECOXIB", "concentracion": "200 mg", "presentacion": "Caja Envase Blister Cápsula", "tipo": "CAPSULA"},
                _ext("16690-BLISTER", "Celecoxib 200 mg - Blister 10 UN"),
            ),
        ]

        for local, externo in casos_segunda_pasada:
            with self.subTest(producto_id=local["id"]):
                # Conservador por defecto: no empareja sin el flag explícito.
                emp_p1, _ = emparejar_productos([local], [externo])
                self.assertEqual(emp_p1, [])

                # Con segunda_pasada=True: empareja con puntaje y marca pasada=2.
                emp_p2, _ = emparejar_productos([local], [externo], segunda_pasada=True)
                self.assertEqual(len(emp_p2), 1)
                self.assertEqual(emp_p2[0]["fuente_pid"], externo.pid)
                self.assertEqual(emp_p2[0]["pasada"], 2)

    def test_segunda_pasada_rechaza_falsos_positivos_concentracion_forma_sal_y_ambiguedad(self) -> None:
        casos_rechazo = [
            # Concentraciones distintas
            (
                [{"id": 9001, "nombre": "AMOXICILINA", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"}],
                [_ext("X9001", "Amoxicilina 1 G Tableta - Caja 10 UN")],
            ),
            # Formas farmacéuticas distintas (jarabe vs tableta, cápsula vs tableta)
            (
                [{"id": 9002, "nombre": "AMOXICILINA", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"}],
                [_ext("X9002", "Amoxicilina 500 Mg Cápsulas - Caja 100 UN")],
            ),
            (
                [{"id": 9003, "nombre": "PARACETAMOL", "concentracion": "120 mg/5 mL", "presentacion": "Frasco x 60 mL", "tipo": "SOLUCION"}],
                [_ext("X9003", "Paracetamol 120 Mg/5 Ml Tabletas - Caja 20 UN")],
            ),
            # Solución tópica nunca se empareja con jarabe
            (
                [{"id": 9004, "nombre": "CLOTRIMAZOL", "concentracion": "1%", "presentacion": "Frasco x 20 mL Tópica", "tipo": "SOLUCION"}],
                [_ext("X9004", "Clotrimazol 1% Jarabe - Frasco 20 ML")],
            ),
            # Sales farmacéuticas distintas en conflicto (sódico vs potásico)
            (
                [{"id": 9005, "nombre": "DICLOFENACO SODICO", "concentracion": "50 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"}],
                [_ext("X9005", "Diclofenaco Potásico 50 Mg Tabletas - Caja 20 UN")],
            ),
            # Variante comercial distinta ("Forte", "Plus", otro sabor/número)
            (
                [{"id": 9006, "nombre": "PANADOL", "concentracion": "500 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"}],
                [_ext("X9006", "Panadol Forte 500 Mg - Sobre 2 UN")],
            ),
            (
                [{"id": 9007, "nombre": "FRUTTIFLEX 50 SABOR A FRESA", "concentracion": "ELECTROLITOS", "presentacion": "Frasco x 1L", "tipo": "SOLUCION"}],
                [_ext("X9007", "Fruttiflex 60 sabor Uva - Frasco 1000 ML")],
            ),
            # Ambigüedad en catálogo local (tableta y cápsula blanda comparten nombre/conc y externo omite forma)
            (
                [
                    {"id": 424, "nombre": "DOLOMAX", "concentracion": "400 mg", "presentacion": "Caja Envase Blister Tabletas", "tipo": "TABLETA"},
                    {"id": 427, "nombre": "DOLOMAX", "concentracion": "400 mg", "presentacion": "Caja Envase Blister Cápsulas Blandas", "tipo": "CAPSULA"},
                ],
                [_ext("33013-CAJA", "Dolomax 400 Mg - Caja 20 UN")],
            ),
            # Mismo frasco pero distinto volumen físico
            (
                [{"id": 677, "nombre": "PARACETAMOL", "concentracion": "120 mg/5 mL", "presentacion": "Frasco X 120 mL", "tipo": "SOLUCION"}],
                [_ext("12975", "Paracetamol Jarabe 120 mg/5ml- Frasco 60 ML")],
            ),
        ]

        for locales, externos in casos_rechazo:
            with self.subTest(producto_id=locales[0]["id"]):
                emparejados, _ = emparejar_productos(locales, externos, segunda_pasada=True)
                self.assertEqual(emparejados, [])


if __name__ == "__main__":
    unittest.main()
