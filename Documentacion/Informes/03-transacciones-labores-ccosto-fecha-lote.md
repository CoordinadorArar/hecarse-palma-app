# Labores por centro de costo en fecha con lote

**Módulo:** Transacciones · **Clave:** `labores-ccosto-fecha-lote` · **Estado:** Implementado

## Propósito
Igual que "Labores por centro de costo en fecha" pero abierto por lote: muestra qué lotes y labores componen el costo de cada centro de costo. Para contabilidad de costos y agronomía.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFechaMes` (@fi, @ff, @empresa); agrupa por centro de costo + lote. Complemento: `spSeleccionaLaboresLoteFechaMesConcepto` (lista de labores del rango para columnas dinámicas).
- Tablas: `aTransaccionTercero` + `aTransaccionNovedad` + `aTransaccion` + `aNovedad`, con `aGrupoNovedad`, `aFinca`, `aLotes` (añoSiembra, palmasProduccion, ccosto) y `cCentrosCosto`.
- Lógica clave:
  - Fecha de ejecución de la labor (`ISNULL(fechaNovedad, fecha de la línea)`) entre las fechas; `anulado = 0`; excluye `aTransaccionEliminada` si existe.
  - Centro de costo igual que en `labores-ccosto-fecha`: el de la línea y, si está vacío, el del lote.
  - Valor = `SUM(valorTotal)` con signo (signo 2 resta); ejecución Nómina/Contratista/Mixta según `nFuncionario.contratista`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Fecha de ejecución de la labor; por defecto el primer día del mes. |
| Fecha final | fecha | Sí | Por defecto el último día del mes; rango máximo de 1 año. |
| Centro de costo | select / select2 (cCentrosCosto) | No | Centro de costo de la línea o, si no tiene, el del lote. Opción "Sin centro de costo" si hay registros así. select2 si hay más de 15 opciones. |
| Finca | select2 | No | Finca de la línea. |
| Lote | select2 dependiente de finca | No | Valor `finca\|lote`. Si se elige finca y un lote de otra finca: "El lote no pertenece a la finca seleccionada." |
| Grupo de labor | select2 (aGrupoNovedad) | No | |

## Resultado (tabla)
Una fila por centro de costo + lote + [mes] + labor + unidad de medida, agrupada por centro de costo. El mes solo se abre (y la columna Mes solo se devuelve) si el rango abarca más de un mes calendario. Orden: centros por valor descendente ("Sin centro de costo" al final); dentro, lotes por valor del lote en el centro descendente ("Sin lote" al final); luego mes ascendente; luego labores por valor descendente.

| Columna | Descripción |
|---|---|
| Lote | Código — nombre, o "Sin lote". |
| Año siembra | Año de siembra del lote. |
| Mes | "Jul 2026" (solo si el rango abarca más de un mes). |
| Labor | Código — nombre de la labor. |
| U. medida | Unidad de medida de la labor. |
| Palmas prod. | Palmas en producción del lote (0 sin lote). |
| Cantidad | Suma por labor; no se totaliza porque mezcla unidades. |
| Precio prom. | Valor / cantidad. |
| Valor | Suma de `valorTotal` con signo. |
| Costo/palma | Valor / palmas en producción del lote. Subtotal por centro ponderado (valor con palmas / palmas de sus lotes); sin pie: el promedio global está en el indicador. |

Ocultas en pantalla, incluidas en Excel: Centro de costo, Finca, Grupo labor, Ejecución, Días, Palmas peso, Valor con palmas, Valor contratista, Valor lote, % del centro (valor del lote / valor del centro).

Totales: Valor (total general y subtotal por centro de costo); `totales.costoPalma` = valor con palmas / palmas de lotes distintos (Excel).

## Indicadores (KPIs)
- Valor total ejecutado (ayuda: ajustes negativos incluidos, si los hay).
- Centros de costo con movimiento, sin contar "Sin centro de costo" (ayuda: centros con varios lotes, registros sin centro de costo y registros con centro de costo del lote).
- Lotes intervenidos (ayuda: fincas y registros sin lote).
- Costo promedio por palma: ponderado sobre las palmas de lotes distintos; lotes sin palmas excluidos; máximo por lote.

## Gráfica
- Si hay centros de costo (sin contar "Sin centro de costo") con más de un lote: barras horizontales, una serie "Valor", "Top 15 lotes en centros de costo con varios lotes". Eje Y: los 15 pares centro · lote de mayor valor dentro de esos centros (etiqueta del lote; "Sin lote" permitido), de mayor a menor, y una barra "Otros lotes" con el resto si lo hay; la suma de barras = valor de esos centros. Tooltip: centro, % del centro, palmas y costo por palma (si aplican) y labores; en "Otros lotes", número de lotes agrupados y labores.
- Si no: barras horizontales "Top 10 centros de costo por valor ejecutado" + "Otros" (o "Sin centro de costo"). Tooltip: % del total, palmas, costo por palma, labores y, en "Otros", centros agrupados.
- Por qué: muestra qué lotes concentran el costo de los centros de costo que se reparten entre varios lotes.

## Supuestos resueltos
- "Con lote" corresponde a `spSeleccionaLaboresLoteFechaMes`; el mes se abre solo cuando el rango cruza meses.
- Se excluyen anuladas y `aTransaccionEliminada` (el legado no lo hacía); el rango se filtra por la fecha de ejecución de la labor, máximo 1 año.
- Centro de costo = el de la línea (`aTransaccionTercero.ccosto`) y, si está vacío, el del lote (`aLotes.ccosto`); sin ninguno → "Sin centro de costo" al final. Centros que no existen en el maestro → "Centro de costo no encontrado".
- Registros sin lote y lotes sin palmas en producción no entran en el costo por palma. Algunos lotes de distribución (p. ej. DCR26-01) tienen 1 palma registrada, lo que dispara su costo por palma.
