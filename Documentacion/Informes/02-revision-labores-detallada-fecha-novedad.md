# Labores detallada por fecha por novedad

**Módulo:** Revisión Labores · **Clave:** `labores-detallada-fecha-novedad` · **Estado:** Planificado

## Propósito
Detalle de lo ejecutado por labor (novedad) en un rango de fechas: dónde (finca/lote), cuándo, quién, cuánto y a qué costo. Para supervisores que revisan una labor específica.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFecha` (@fi, @ff, @empresa: agrupa por grupo de labor, lote, labor, fecha) sobre `vTransaccionAgronomico`; lista de labores del rango con `spSeleccionaLaboresLoteFechaMesConcepto`.
- Tablas/vistas: `vTransaccionAgronomico`, `aLotes`.
- Lógica clave:
  - `CONVERT(date, fechaTransaccion) BETWEEN @fi AND @ff`, `codEmpresa`, `anulado = 0`.
  - Filtro por `codLabor` (novedad) y opcional `codGrupoLabor`.
  - Detalle por registro (trabajador) o agregado por labor + lote + fechaLabor: SUM(cantidadTercero), SUM(valorTotalTercero), precioLabor.
  - Rendimiento opcional: cantidad / jornales vs `rendimiento` (`aNovedad.tarea`).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial / final | Fecha | Sí | Default mes actual |
| Grupo de labor | Select (aGrupoNovedad) | No | |
| Labor (novedad) | Select dependiente (labores con movimiento en el rango) | No | |
| Finca | Select (aFinca) | No | |
| Lote | Select dependiente | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Grupo | `nombreGrupoLabor` |
| Labor | `codLabor` – `nombreLabor` |
| Fecha labor | `fechaLabor` |
| Finca | `nombreFinca` |
| Lote | `codLote` – `nombreLote` |
| Año siembra | `añoSiembra` |
| Trabajador | `codTercero` – `nombreTercero` |
| Contratista | Sí/No (`contratista`) |
| U. medida | `uMedida` |
| Cantidad | `cantidadTercero` |
| Jornales | `jornalTercero` |
| Precio | `precioLabor` |
| Valor | `valorTotalTercero` |
| Transacción | `codtransaccion`-`numeroTransaccion` |

Totales de pie: cantidad, jornales, valor; subtotales por labor.

## Indicadores (KPIs)
- Cantidad ejecutada (en la unidad de la labor).
- Valor total.
- Lotes intervenidos.
- Rendimiento promedio (cantidad/jornal).

## Gráfica
- Tipo: combinada barras+línea.
- Eje X: fecha labor (agrupar por semana si el rango > 45 días); barras: cantidad; línea (eje secundario): valor.
- Por qué: evolución en el tiempo de dos magnitudes distintas de la labor.

## Supuestos a validar
- Si "por novedad" se refiere a labor agronómica (`aNovedad`) y no a novedades de nómina (`nNovedadesDetalle`, `spSeleccionaNovedadesTerceroFecha`).
- Mezcla de unidades si no se filtra labor: totalizar cantidad solo con una labor seleccionada.
- Filtro por `fechaTransaccion` (legado) vs `fechaLabor`.
