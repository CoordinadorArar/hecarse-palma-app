# Labores por trabajador en fechas

**Módulo:** Transacciones · **Clave:** `labores-trabajador-fechas` · **Estado:** Planificado

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
