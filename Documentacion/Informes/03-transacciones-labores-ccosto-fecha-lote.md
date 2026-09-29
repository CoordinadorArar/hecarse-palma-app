# Labores por centro de costo en fecha con lote

**Módulo:** Transacciones · **Clave:** `labores-ccosto-fecha-lote` · **Estado:** Planificado

## Propósito
Igual que "Labores por centro de costo en fecha" pero abierto por lote: muestra qué lotes y labores componen el costo de cada centro de costo. Para contabilidad de costos y agronomía.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFechaMes` (@fi, @ff, @empresa); agrupa por centro de costo + lote. Complemento: `spSeleccionaLaboresLoteFechaMesConcepto` (lista de labores del rango para columnas dinámicas).
- Tablas/vistas: `vSeleccionaTransaccionesAgronomico` JOIN `aLotes` (añoSiembra, palmasBrutas, palmasProduccion).
- Lógica clave:
  - `fechaLabor BETWEEN fi AND ff`, `codEmpresa = @empresa`; agregar `anulado = 0` (el legado no lo filtra).
  - Agrupa por `codCCosto`, `codLote`, `codLabor`, `uMedida`, `precioLabor`, `fechaLabor`: `SUM(cantidadTercero)`, `SUM(cantidadTercero) * precioLabor`.
  - Mes de la labor (`DATENAME/DATEPART(month, fechaLabor)`) para agrupar por mes.
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Centro de costo | select | No | |
| Finca | select | No | |
| Lote | select dependiente | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Centro de costo | `codCCosto` – `nombreCCosto` |
| Lote | `codLote` – `nombreLote` |
| Año siembra | `añoSiembra` |
| Palmas prod. | `palmasProduccion` |
| Mes | `nombreMes` |
| Labor | `codLabor` – `nombreLabor` |
| Cantidad | Suma |
| U. medida | `uMedida` |
| Precio | `precioLabor` |
| Valor | Cantidad × precio |

Totales de pie: Valor; subtotales por centro de costo y lote.

## Indicadores (KPIs)
- Valor total.
- Centros de costo con movimiento.
- Lotes intervenidos.
- Costo promedio por palma.

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: centro de costo; eje X: valor; series: lote (top 8 por valor + "Otros").
- Por qué: muestra la composición de cada centro de costo por lote.

## Supuestos a validar
- Que "con lote" corresponde a `spSeleccionaLaboresLoteFechaMes` (única variante del legado que agrupa ccosto + lote).
- El legado filtra por `fechaLabor` y no excluye anuladas; se asume que deben excluirse.
- Mismo punto sobre el origen del centro de costo que en `labores-ccosto-fecha`.
