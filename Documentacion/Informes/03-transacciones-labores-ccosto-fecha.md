# Labores por centro de costo en fecha

**Módulo:** Transacciones · **Clave:** `labores-ccosto-fecha` · **Estado:** Implementado

## Propósito
Totaliza cantidades y valor de labores por centro de costo en un rango de fechas. Sirve a contabilidad y dirección para distribuir el costo de mano de obra de campo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresCcostoFecha` (@fi, @ff, @empresa).
- Tablas/vistas: `vSeleccionaTransaccionesAgronomico` (centro de costo tomado de `nContratos.ccosto` → `cCentrosCosto`). Alternativa: `vTransaccionAgronomico` (centro de costo de `aTransaccionTercero.ccosto`).
- Lógica clave:
  - Agrupa por `codCCosto`, `nombreCCosto`, `codLabor`, `nombreLabor`, `uMedida`, `precioLabor`: `SUM(cantidadLabor)` y `SUM(cantidadLabor) * precioLabor` como total.
  - Añadir los filtros que el legado declara pero no aplica: fechas, empresa y `anulado = 0`.
  - Preferir `SUM(valorTotalTercero)` como valor, coherente con los demás informes.
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Fecha de ejecución de la labor; por defecto el primer día del mes. |
| Fecha final | fecha | Sí | Por defecto el último día del mes; rango máximo de 1 año. |
| Finca | select2 | No | Finca de la línea. |
| Centro de costo | select / select2 (cCentrosCosto) | No | Centro de costo de la línea o, si no tiene, el del lote. Opción "Sin centro de costo" (sin centro por ninguna de las dos vías) si hay registros así. select2 si hay más de 15 opciones. |
| Grupo de labor | select2 (aGrupoNovedad) | No | |

## Resultado (tabla)
Una fila por centro de costo + labor + unidad de medida, agrupada por centro de costo (código — nombre). Orden: centros por valor descendente, "Sin centro de costo" al final; dentro de cada centro, labores por valor descendente.

| Columna | Descripción |
|---|---|
| Grupo labor | Código — nombre del grupo, o "Sin grupo". |
| Labor | Código — nombre de la labor. |
| U. medida | Unidad de medida de la labor. |
| Ejecución | Nómina, Contratista o Mixta según los trabajadores de la labor. |
| Cantidad | Suma por labor; no se totaliza porque mezcla unidades. |
| Precio prom. | Valor / cantidad. |
| Días | Fechas distintas con esta labor en el centro de costo. |
| Valor | Suma de `valorTotal` con signo. |

Ocultas en pantalla, incluidas en Excel: Centro de costo, Finca, Valor contratista.

Totales de pie: Valor (total general y subtotal por centro de costo).

## Indicadores (KPIs)
- Valor total ejecutado (ayuda: ajustes negativos incluidos, si los hay).
- Centros de costo con movimiento, sin contar "Sin centro de costo" (ayuda: fincas, registros sin centro de costo y registros con centro de costo del lote).
- Centro de costo de mayor valor: nombre (ayuda: valor, % del total y labores).
- Labores distintas (ayuda: grupos de labor y días con labor).

## Gráfica
- Tipo: barras horizontales, una serie "Valor".
- Eje Y: top 10 centros de costo por valor (de mayor a menor) + una barra "Otros" con el resto de centros y lo sin centro de costo; si el resto es solo lo sin centro de costo, la barra se llama "Sin centro de costo". Eje X: valor.
- Tooltip: % del total, labores, días, fincas y valor de contratistas; en "Otros", número de centros agrupados.
- Por qué: comparación de categorías con etiquetas largas.

## Supuestos resueltos
- Se aplican los filtros de fecha, empresa y `anulado = 0` que el legado declaraba sin aplicar; se excluye `aTransaccionEliminada` si existe.
- Centro de costo = el de la línea (`aTransaccionTercero.ccosto`) y, si está vacío, el del lote (`aTransaccionNovedad.lote` → `aLotes.ccosto`, por empresa). `aTransaccionTercero.contrato` siempre es NULL, por lo que `nContratos.ccosto` no aplica; y `c.ccosto` solo lo traen cosecha (309901) y ganadería (400101), heredado de `aGrupoNovedad.ccosto`. Sin centro por ninguna vía → grupo "Sin centro de costo" al final.
- Valor = `SUM(valorTotal)` con signo (las transacciones con signo 2 restan); el precio es el promedio valor / cantidad.
- El rango se filtra por la fecha de ejecución de la labor (`ISNULL(fechaNovedad, fecha de la línea)`), máximo 1 año.
