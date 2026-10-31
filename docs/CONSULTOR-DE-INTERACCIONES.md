# Consultor de interacciones y contraindicaciones

> Estado: implementado, **sin reglas aprobadas**. No muestra nada a ningún
> cliente hasta que un químico farmacéutico firme la tabla.

## 1. Qué se decidió no hacer, y por qué

El planteamiento inicial era un **consultorio con triaje clínico**: recibir
síntomas y orientar sobre qué tomar. Se descartó.

Un asistente que recibe síntomas y sugiere medicamentos, en una botica real y
con usuarios reales, es acto farmacéutico. No es una cuestión de calidad del
modelo: es que la responsabilidad de esa recomendación recae sobre alguien
con título, y el sistema no puede asumirla por él.

Lo que sí se construyó es más estrecho y defendible: **un consultor que avisa
cuando dos productos del carrito tienen una interacción registrada y deriva
al químico farmacéutico**. No orienta sobre qué tomar; avisa sobre lo que la
persona ya eligió.

## 2. La regla que gobierna todo el módulo

> **Puede decir «ojo con estos dos». Nunca puede decir «esto es seguro».**

No es prudencia retórica. La ausencia de una regla en la tabla significa «no
lo sé», no «no hay interacción». Con 25 principios activos sembrados, el 15,7 %
del catálogo mapeado y una tabla todavía sin aprobar, la mayor parte de las
combinaciones posibles cae fuera de lo que el sistema conoce.

Hay una prueba automatizada que falla si alguna respuesta contiene «es
seguro», «sin riesgo», «puede tomarlos» o «no hay interacción»:
`ConsultorInteraccionesTest::test_nunca_dice_que_algo_sea_seguro`.

## 3. Cobertura real, medida

| Dato | Valor |
|---|---|
| Productos en el catálogo | 884 |
| Con principio activo mapeado | **139 (15,7 %)** |
| Principios activos sembrados | 25 |
| Reglas de interacción candidatas | 11 |
| Reglas **aprobadas** | **0** |

Los 745 productos restantes son nombres comerciales —AB-MOKS, ACICLAV, ACI
BASIC— que no dicen cuál es su principio activo. El consultor los declara como
no revisados, con su nombre, y añade: *«Los productos con nombre comercial no
mapeado requieren validación directa del químico farmacéutico»*.

## 4. Por qué no se deducen los nombres comerciales

«ACICLAV» se parece a «ACICLOVIR». Un emparejamiento por similitud los uniría,
y son cosas distintas: ACICLAV es amoxicilina con ácido clavulánico.

En un catálogo de medicamentos un falso positivo no es un resultado peor: es
un aviso equivocado sobre lo que alguien va a tomar. El normalizador exige
coincidencia **exacta por palabra completa** contra la lista curada. Lo que no
casa se queda sin identificar, y se dice en voz alta.

Hay pruebas que fijan los tres casos: que `ACICLAV` no case con `ACICLOVIR`,
que `PARACETAMOLINA` no case con `PARACETAMOL`, y que las tildes no impidan
reconocer `ÁCIDO ACETILSALICÍLICO`.

## 5. Las reglas son dato revisable, no código

`interacciones_farmacologicas` guarda cada regla con:

- el par de principios, **siempre con el id menor primero** (sin esa
  convención la misma interacción entraría dos veces y el índice único no
  serviría);
- severidad, efecto y recomendación, redactados;
- **la fuente**, porque una regla sin fuente no es revisable;
- `aprobada_en` y `aprobada_por_id`.

**Mientras `aprobada_en` sea nula, la regla no sale del servidor.** Las 11
candidatas sembradas llevan en su campo `fuente` el texto:

> *Candidata sembrada por el sistema a partir de interacciones ampliamente
> descritas. PENDIENTE de contraste con la ficha técnica vigente y de
> aprobación por el químico farmacéutico.*

Eso es exactamente lo que son. Las sembró un programa, no un profesional
sanitario.

## 6. Qué ve el cliente

La pantalla enseña **tres cosas, siempre las tres**:

1. Los avisos, lo grave primero.
2. La cobertura: «Revisados 2 de 3 productos», también cuando no hay avisos.
3. La derivación al químico farmacéutico, como parte del resultado y no como
   enlace al pie.

El aviso legal viaja **con la respuesta de la API**, no solo en la página: quien
consuma el endpoint desde otro sitio no puede mostrar el veredicto sin él.

## 7. Cómo se completa la cobertura

```bash
# Reconoce los productos cuyo nombre ES el principio activo
php artisan clinico:mapear

# Carga el padrón oficial cuando el químico farmacéutico lo aporte
php artisan clinico:importar-padron padron.csv --simular   # ensayo
php artisan clinico:importar-padron padron.csv             # aplicar
```

El importador empareja por `codigo_digemid` y, si no lo hay, por nombre exacto
normalizado. **No empareja por parecido**: unir dos fichas que no son la misma
es peor que dejar una sin emparejar. Con `--simular` recorre el archivo y dice
qué haría sin escribir nada.

## 8. Lo que falta antes de que esto sea visible al público

1. **Que un químico farmacéutico revise las 11 reglas candidatas** contra la
   ficha técnica vigente y las apruebe una a una.
2. **Cargar el padrón oficial** para subir la cobertura del 15,7 %.
3. Decidir si el aviso aparece también en el punto de venta del mostrador,
   donde hay un profesional delante y el contexto es distinto.

Hasta el punto 1, el módulo funciona y no muestra nada. Es el estado correcto.

## 9. Reserva

Este documento describe decisiones de ingeniería, no criterio farmacológico.
Las reglas sembradas, su severidad y su redacción son un punto de partida para
que alguien con título las revise, y no una recomendación del sistema ni de
quien lo escribió.
