# Labores fecha lote

**Módulo:** Fertilización · **Clave:** `labores-fecha-lote` · **Estado:** Planificado

## Propósito
Resume, para un rango de fechas, las labores de fertilización ejecutadas por lote y fecha: cantidad aplicada, insumo usado y valor de la mano de obra. Sirve para saber cuándo y con qué se fertilizó cada lote.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFechaFertilizacion` (@fi, @ff, @empresa). La versión genérica es `spSeleccionaLaboresLoteFecha`.
- Tablas/vistas: `vTransaccionAgronomico` (aTransaccion + aTransaccionNovedad + aTransaccionTercero + aNovedad + aGrupoNovedad + aLotes), `aTransaccionItem`, `iItems`.
- Lógica clave:
  - `aTransaccion.tipo = 'RLF'` (labores de fertilización), `anulado = 0`, fecha de la transacción entre @fi y @ff.
  - Labor × lote × fecha de labor: cantidad = `SUM(aTransaccionTercero.cantidad)` y valor = `SUM(aTransaccionTercero.valorTotal)`, con precio de la labor.
  - Insumos: `aTransaccionItem` tipo RLF del mismo número y lote, unido a la línea por `registror` (el legado une solo por lote, y eso duplica filas cuando hay varias líneas en el mismo lote).
  - Datos del lote: año de siembra, palmas brutas y en producción. Dosis real = cantidad del insumo / palmas.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | Fecha | Sí | |
| Fecha final | Fecha | Sí | |
| Finca | Select (aFinca) | No | |
| Lote | Select (aLotes) | No | |
| Labor | Select (aNovedad grupo 02) | No | |
| Insumo | Select (iItems) | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha labor | |
| Número RLF | |
| Lote | Código y nombre |
| Año siembra | |
| Palmas producción | |
| Labor | Código y nombre |
| Cantidad labor | Con su unidad de medida |
| Precio | |
| Valor mano de obra | |
| Insumo | |
| Cantidad insumo | Con su unidad de medida |
| Dosis real (/palma) | Cantidad insumo / palmas |

Totales de pie: valor de la mano de obra. La cantidad de insumo se totaliza por insumo cuando se filtra uno.

## Indicadores (KPIs)
- Lotes fertilizados en el rango.
- Valor total de la mano de obra.
- Cantidad total de insumo aplicado (cuando se filtra un insumo).
- Número de registros RLF.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: lote (top 15 por cantidad de insumo). Eje X: cantidad de insumo, o valor de mano de obra si no se filtra insumo.
- Por qué: compara lotes (más de 8 categorías) en una sola magnitud.

## Supuestos a validar
- La relación entre la línea de labor y el insumo es `aTransaccionItem.registror = aTransaccionNovedad.registro`. En el legado era solo por lote.
- Falta decidir si se incluyen las labores del grupo 02 registradas como TLA además de las RLF.
- La columna `nombreCCosto` del legado es en realidad el grupo de labor.
