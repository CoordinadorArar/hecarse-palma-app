# Labores por trabajador en fechas

**Módulo:** Transacciones · **Clave:** `labores-trabajador-fechas` · **Estado:** Implementado

## Propósito
Consulta las labores realizadas por un trabajador en un rango de fechas: qué hizo, dónde y cuánto devengó. Lo usan nómina, supervisores y la atención de reclamos.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresTerceroFecha` (@fi, @ff, @empresa, @trabajador).
- Tablas/vistas: `vTransaccionAgronomico` (aTransaccion, aTransaccionNovedad, aTransaccionTercero, aNovedad, aGrupoNovedad, cTercero, aLotes, nContratos, cCentrosCosto).
- Lógica clave:
  - `CONVERT(date, fechaTransaccion) BETWEEN fi AND ff`, `anulado = 0`, `codEmpresa = @empresa`.
  - Trabajador: `codTercero LIKE %texto%` o `nombreTercero LIKE %texto%` (cédula formateada o nombre).
  - Cantidad y valor con signo (`signo = 2` resta).
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Trabajador | autocompletar (cTercero / nFuncionario) | Sí | Cédula o nombre |
| Grupo de labor | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha labor | `fechaLabor` |
| Día | `diaSemana` |
| Tipo / Número | Documento |
| Trabajador | `codTercero` – `nombreTercero` |
| Finca / Lote | `nombreFinca` / `codLote` |
| Labor | `nombreLabor` |
| Cantidad | `cantidadTercero` |
| U. medida | `uMedida` |
| Jornales | `jornalTercero` |
| Precio | `precioLabor` |
| Valor | `valorTotalTercero` |
| Pagada en nómina | `ejecutadoNomina` (Sí/No) |

Totales de pie: Jornales, Valor.

## Indicadores (KPIs)
- Valor total devengado.
- Jornales trabajados.
- Días con labor (fechas distintas).
- Valor pendiente de pago (`ejecutadoNomina = 0`).

## Gráfica
- Tipo: barras horizontales.
- Eje Y: labor; eje X: valor total; ordenado de mayor a menor, top 10.
- Por qué: compara cuánto aportó cada labor al devengado del trabajador.

## Supuestos a validar
- Si la búsqueda debe exigir un único trabajador (id) en lugar del LIKE del legado.
- Si `ejecutadoNomina` (aTransaccionTercero.ejecutado) refleja realmente el pago en nómina.

## Supuestos resueltos
- Se exige un único trabajador, elegido en un select con los terceros que tienen labores registradas (no anuladas); se filtra exacto por `aTransaccionTercero.tercero`, no con el LIKE del legado.
- El rango se filtra por la fecha de ejecución de la labor (`ISNULL(fechaNovedad, aTransaccionNovedad.fecha)`), no por la de registro. Sin límite de rango; tope de 20.000 registros.
- `aTransaccionTercero.ejecutado` vale 0 en el 100 % de los registros (2019–2026) y `nLiquidacionNomina` está vacía, así que no refleja el pago en nómina: se omite la columna Pagada y el KPI 4 es "Promedio por día".
- `periodo` es el número de periodo de nómina dentro del año (`nPeriodoDetalle.noPeriodo`), vacío/0 en ~46 % del histórico y ~75 % en 2025–2026 (casi todo TLA). Por eso la agrupación se decide por resultado: si más de la mitad de las filas tiene periodo se agrupa por periodo ("7/2026 — dd/mm – dd/mm", con "S/P — Sin periodo asignado" (el periodo no indica liquidación) al final); si no, por semana lunes–domingo ("Semana — dd/mm – dd/mm", recortada al rango consultado, con año dd/mm/aaaa si la semana o el rango cruza años).
- Se consulta sobre las tablas base. Se excluyen anuladas y, si existe, `aTransaccionEliminada`. Cantidad y valor con signo (`signo = 2` resta).
