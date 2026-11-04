# Figura 11 · Modelo de datos conceptual (versión corregida)

Para el informe final. El APF3 entregado **no se ha modificado**.

---

## Qué problema resuelve este archivo

La Figura 11 del APF3 lleva rotulado **«26 migraciones versionadas» dentro del
PNG**. No es un pie de figura que se pueda editar en Word: el texto está
incrustado en la imagen, y por eso la observación **O-B2** quedó abierta
entregable tras entregable.

Mientras tanto la cifra se desalineó por tres sitios a la vez:

| Dónde | Qué dice |
|---|---|
| El PNG de la Figura 11 | 26 migraciones |
| La fila O-B2 del propio APF3 | «hoy son 49» |
| El repositorio, contando archivos | **54** |

Las tres cifras son distintas y ninguna se corregía a la otra, porque las tres
estaban escritas a mano en sitios diferentes.

---

## Qué hay aquí

| Archivo | Qué es |
|---|---|
| `Figura11-modelo-de-datos.png` | La figura regenerada, con **54 migraciones**. |
| `figura11-original.png` | La imagen tal como está incrustada en el APF3, para poder comparar. |

El generador es `docs/scripts/generar-figura11.py`. Para rehacerla:

```powershell
python docs/scripts/generar-figura11.py
```

---

## Por qué se regeneró en lugar de retocar el PNG

Retocar los dos dígitos habría dejado el mismo problema para la próxima vez:
un número escrito a mano dentro de una imagen que nadie puede regenerar.

Ahora **la cifra se cuenta, no se teclea**. El script lee
`botica-san-juan-backend/database/migrations/*.php` y escribe el resultado en
el subtítulo. Para que vuelva a quedarse vieja haría falta que alguien ejecute
el script y no mire lo que sale.

---

## Dos cosas que conviene saber antes de insertarla

**1. El diagrama sigue mostrando las trece entidades del MVP, no el esquema
completo.** Hoy el sistema tiene más tablas —recetas, lotes, eventos de
operación, conteos por ciclos, comisiones de cobro— y dibujarlas todas
convertiría un diagrama conceptual en un plano ilegible. El subtítulo dice
cuántas migraciones hay *en total*; el dibujo muestra el *núcleo*. Las dos
cosas son ciertas y no se contradicen, pero si el informe final afirma que la
figura representa el esquema completo, esa afirmación sí sería falsa.

**2. No es idéntica píxel a píxel a la original.** Se rehízo con la misma
disposición, los mismos colores —muestreados del PNG original— y las mismas
entidades, pero la tipografía del original no se pudo identificar: ninguna de
las fuentes disponibles coincidía por encima de un 38 % de solape. Se usó la
que mejor encajaba. Si el informe final va a llevar las dos figuras juntas, se
notará la diferencia de fuente.

También se corrigieron tres defectos de maquetación que tenía la primera
versión generada: el título `COMPROBANTES_ELECTRONICOS` se salía de su caja,
`PEDIDO_DETALLES` pisaba la leyenda, y las flechas entre cajas de distinto
ancho salían en diagonal cruzando por encima de otras entidades.

---

## Al montar el informe final

1. Sustituir la imagen de la Figura 11 por `Figura11-modelo-de-datos.png`.
2. Volver a ejecutar el script antes de entregar, por si el esquema creció
   entre medias.
3. Dar por cerrada la observación **O-B2**, y corregir de paso la cifra que esa
   misma fila cita («hoy son 49»), que también está desactualizada.
