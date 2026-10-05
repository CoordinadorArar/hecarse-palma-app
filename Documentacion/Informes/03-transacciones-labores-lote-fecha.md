# Labores por lote en fecha

**Módulo:** Transacciones · **Clave:** `labores-lote-fecha` · **Estado:** Implementado

## Propósito
Resume, por lote y labor, las cantidades y el costo ejecutados en un rango de fechas. Responde cuánto se invirtió en cada lote y en qué labores, para agronomía y control de costos.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFecha` (@fi, @ff, @empresa).
- Tablas base: `aTransaccionTercero` + `aTransaccionNovedad` + `aTransaccion` + `aNovedad` + `aGrupoNovedad` + `aFinca` + `aLotes` (`añoSiembra`, `palmasProduccion`); `nFuncionario.contratista` para la ejecución.
- Lógica clave:
  - `CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN fi AND ff`, `anulado = 0`, empresa de la sesión.
  - Cantidad y valor con signo (`aTransaccionNovedad.signo = 2` resta).
  - Costo por palma = valor / `palmasProduccion` (calculado en la app).
  - Excluir `aTransaccionEliminada` si existe.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes. Fecha de ejecución de la labor |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Máximo 1 año de rango |
| Finca | select2 (aFinca) | No | Todas por defecto |
| Lote | select2 dependiente de finca (valor `finca\|lote`) | No | Se valida que pertenezca a la finca elegida |
| Grupo de labor | select2 (aGrupoNovedad) | No | Todos por defecto |

## Resultado (tabla)
Una fila por lote + labor + unidad, agrupada por lote. Lotes por valor total descendente y "Sin lote" al final; dentro del lote, filas por valor descendente.

| Columna | Descripción |
|---|---|
| Lote | código – nombre (agrupación); "Sin lote" si la línea no tiene lote |
| Finca | `aFinca.descripcion` |
| Año siembra | `aLotes.añoSiembra` |
| Palmas prod. | `aLotes.palmasProduccion` |
| Grupo labor | código – nombre, o "Sin grupo" |
| Labor | código – nombre |
| U. medida | `aNovedad.uMedida` |
| Ejecución | Nómina, Contratista o Mixta según los trabajadores (`nFuncionario.contratista = 1`) |
| Cantidad | suma con signo (no se totaliza: mezcla unidades) |
| Precio prom. | valor / cantidad |
| Días | fechas distintas con la labor en el lote |
| Valor | suma con signo |
| Costo/palma | valor / palmas en producción; subtotal y total ponderados |

Totales de pie: Valor y Costo/palma (ponderado).

## Indicadores (KPIs)
- Valor total ejecutado (ajustes negativos).
- Lotes intervenidos (fincas y registros sin lote).
- Labores distintas (grupos de labor y días con labor).
- Costo promedio por palma en producción (ponderado; lotes sin palmas excluidos).

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: lote (top 15 por valor, de mayor a menor, sin "Sin lote"); eje X: valor; series: grupo de labor (máximo 5 + "Otros grupos" si hay más de 6).
- Detalle por lote: finca, palmas, costo por palma, labores y días.
- Por qué: compara lotes y muestra la composición del costo por tipo de labor.

## Supuestos resueltos
- El rango se filtra por la fecha de ejecución de la labor (`ISNULL(fechaNovedad, fecha de la línea)`), no por la fecha de la transacción.
- El resumen es por lote + labor + unidad (no por fecha); los días con labor se muestran como columna.
- Las líneas sin lote se muestran en un grupo aparte "Sin lote" y no entran en el costo por palma; tampoco los lotes sin palmas en producción.
- El contratista se determina por `nFuncionario.contratista = 1` del trabajador.
- Se consulta sobre las tablas base; se excluyen anuladas y, si existe, `aTransaccionEliminada`.
- Límite de 20.000 filas; si se supera se avisa.
