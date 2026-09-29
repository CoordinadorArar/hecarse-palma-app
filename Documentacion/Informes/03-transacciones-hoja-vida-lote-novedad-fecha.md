# Hoja de Vida Lote Novedad y fecha

**Módulo:** Transacciones · **Clave:** `hoja-vida-lote-novedad-fecha` · **Estado:** Planificado

## Propósito
Historial de un lote: qué labores (novedades) se le hicieron, cuándo y en qué cantidad, en un rango de fechas. Responde "qué se le ha hecho a este lote", para agronomía y auditoría de campo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaHojaVidaLoteLabores` (@empresa, @fi, @ff).
- Tablas/vistas: `vTransaccionAgronomico` (datos de aLotes: palmasBrutas, palmasProduccion, añoSiembra, hNetas, hBrutas).
- Lógica clave:
  - `codEmpresa = @empresa`, `fechaTransaccion BETWEEN fi AND ff`; agregar `anulado = 0` (el legado no lo filtra) y excluir `aTransaccionEliminada`.
  - Agrupa por lote, labor, unidad, año, mes: `SUM(cantidadTercero)`.
  - Se añade valor (`SUM(valorTotalTercero)`), última fecha de labor y dosis por palma = cantidad / `palmasProduccion`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Finca | select | No | |
| Lote | select dependiente | No | Recomendado |
| Labor (novedad) | select (aNovedad) | No | |
| Grupo de labor | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | Código – nombre |
| Año siembra | `añoSiembra` |
| Palmas brutas / prod. | Palmas |
| Ha brutas / netas | Hectáreas |
| Labor | Código – nombre |
| Año / Mes | Periodo |
| Cantidad | Suma |
| U. medida | `uMedida` |
| Valor | Suma |
| Última fecha | `MAX(fechaLabor)` |
| Cantidad/palma | Cantidad / palmas producción |

Totales de pie: Valor.

## Indicadores (KPIs)
- Labores distintas aplicadas.
- Valor total invertido.
- Costo por hectárea neta.
- Días desde la última labor (si se filtra un lote y una labor).

## Gráfica
- Tipo: mapa de calor.
- Filas: labor; columnas: mes; color: cantidad (o valor). Si no se filtra lote, filas = lote con la labor seleccionada.
- Por qué: matriz labor/lote × mes que muestra frecuencia y ciclos de las labores.

## Supuestos a validar
- El legado no excluye anuladas; se asume que deben excluirse.
- Filtro por fecha de transacción (legado) vs. fecha de labor.
- Sumar cantidades de labores con unidades distintas no es válido; la gráfica usa valor cuando no se filtra una labor.
