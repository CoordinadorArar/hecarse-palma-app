# Labores por lote en fecha

**Módulo:** Transacciones · **Clave:** `labores-lote-fecha` · **Estado:** Planificado

## Propósito
Resume, por lote y labor, las cantidades y el costo ejecutados en un rango de fechas. Responde cuánto se invirtió en cada lote y en qué labores, para agronomía y control de costos.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFecha` (@fi, @ff, @empresa).
- Tablas/vistas: `vTransaccionAgronomico` (incluye `añoSiembra`, `palmasBrutas`, `palmasProduccion` de aLotes y el grupo de labor de aGrupoNovedad).
- Lógica clave:
  - `CONVERT(date, fechaTransaccion) BETWEEN fi AND ff`, `anulado = 0`, `codEmpresa = @empresa`.
  - Agrupa por grupo de labor, lote, labor, unidad, precio, fecha de labor, contratista: `SUM(cantidadTercero)`, `SUM(valorTotalTercero)`.
  - Costo por palma = valor / `palmasProduccion` (calculado en la app).
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Finca | select | No | |
| Lote | select dependiente de finca | No | |
| Grupo de labor | select (aGrupoNovedad) | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Grupo labor | `nombreGrupoLabor` |
| Lote | `codLote` – `nombreLote` |
| Año siembra | `añoSiembra` |
| Palmas prod. | `palmasProduccion` |
| Labor | `codLabor` – `nombreLabor` |
| Fecha labor | `fechaLabor` |
| Cantidad | `SUM(cantidadTercero)` |
| U. medida | `uMedida` |
| Precio | `precioLabor` |
| Valor | `SUM(valorTotalTercero)` |
| Contratista | Sí/No |

Totales de pie: Valor.

## Indicadores (KPIs)
- Valor total ejecutado.
- Lotes intervenidos.
- Labores distintas.
- Costo promedio por palma en producción.

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: lote (top 15 por valor, de mayor a menor); eje X: valor; series: grupo de labor.
- Por qué: compara lotes y muestra la composición del costo por tipo de labor.

## Supuestos a validar
- Si debe filtrarse por fecha de transacción (legado) o por `fechaLabor`.
- Labores sin lote aparecen como `NA` / "NO APLICA"; confirmar si se muestran o se excluyen.
