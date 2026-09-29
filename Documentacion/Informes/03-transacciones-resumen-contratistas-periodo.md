# Resumen contratistas por periodo

**Módulo:** Transacciones · **Clave:** `resumen-contratistas-periodo` · **Estado:** Planificado

## Propósito
Resumen de alto nivel del valor ejecutado por contratistas por mes y grupo de labor, comparado con el personal propio. Responde cuánto del costo de campo se hace por contratistas y en qué labores. Para gerencia.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaResumenLaboresTerceroFecha` (@fechaI, @fechaF, @empresa): valor por mes (1–12) × grupo de labor (`aGrupoNovedad`) separado por `nFuncionario.contratista`. Variante: `spSeleccionaResumenLaboresTerceroFechaPagada`.
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aGrupoNovedad`, `cTercero`, `nFuncionario` (contratista, proveedor), `aLotes` (desarrollo).
- Lógica clave:
  - `anulado = 0`, `empresa = @empresa`, excluir `aTransaccionEliminada`.
  - Contratistas (`contratista = 1`): fecha de transacción en rango; propios: fecha de labor en rango y `aTransaccionTercero.ejecutado = 1` (como el legado).
  - Agrupa por mes, grupo de labor, contratista (Sí/No), desarrollo del lote: `SUM(valorTotal)`, `COUNT(DISTINCT tercero)`.
  - Vista adicional por contratista (proveedor): `vSeleccionaLiquidacionContratista` agrupado por `nit`/`razonSocial` y mes.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | Rango dentro de un mismo año (columnas por mes) |
| Agrupar por | select (grupo de labor, contratista) | Sí | Grupo de labor por defecto |
| Incluir personal propio | casilla | No | Para comparar |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Grupo de labor / Contratista | Según agrupación |
| Tipo | Contratista / Propio |
| Ene … Dic | Valor por mes |
| Total | Suma |
| % del total | Participación |

Totales de pie: por mes y total general.

## Indicadores (KPIs)
- Valor ejecutado por contratistas.
- % contratistas sobre el total de labores.
- Contratistas activos en el periodo.
- Grupo de labor con mayor valor por contratistas.

## Gráfica
- Tipo: barras apiladas.
- Eje X: mes; eje Y: valor; series: contratista vs. propio (o grupos de labor si se agrupa así, máx. 6 + "Otros").
- Por qué: evolución mensual con composición de dos o pocas partes.

## Supuestos a validar
- Que "Resumen contratistas" corresponde a `spSeleccionaResumenLaboresTerceroFecha` (resume propios y contratistas); podría ser un resumen por proveedor de `vSeleccionaLiquidacionContratista`, cubierto por la agrupación "contratista".
- Criterios distintos de fecha y de `ejecutado` entre propios y contratistas en el legado.
