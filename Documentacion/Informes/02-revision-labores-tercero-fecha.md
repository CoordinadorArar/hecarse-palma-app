# Labores tercero por fecha

**Módulo:** Revisión Labores · **Clave:** `labores-tercero-fecha` · **Estado:** Planificado

## Propósito
Resumen de lo ejecutado y devengado por cada trabajador en un rango de fechas (cantidad, jornales, valor por labor). Para supervisores y nómina antes de liquidar.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresTerceroFecha` (@fi, @ff, @empresa, @trabajador) sobre `vTransaccionAgronomico`, agregado por tercero y labor. Relacionado: `spSeleccionaResumenLaboresTerceroFecha` (resumen mensual por grupo).
- Tablas/vistas: `vTransaccionAgronomico` (= `aTransaccion` + `aTransaccionNovedad` + `aTransaccionTercero` + `aNovedad` + `cTercero` + `aLotes`…).
- Lógica clave:
  - `CONVERT(date, fechaTransaccion) BETWEEN @fi AND @ff`, `codEmpresa = @empresa`, `anulado = 0`.
  - Búsqueda de trabajador: `codTercero LIKE %x% OR nombreTercero LIKE %x%`.
  - GROUP BY idTercero, codTercero, nombreTercero, codLabor, nombreLabor, uMedida → SUM(cantidadTercero), SUM(jornalTercero), SUM(valorTotalTercero). La vista ya aplica signo negativo cuando `signo = 2`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial / final | Fecha | Sí | Default mes actual |
| Trabajador | Texto (código o nombre) | No | LIKE |
| Finca | Select (aFinca) | No | `codFinca` |
| Grupo de labor | Select (aGrupoNovedad) | No | `codGrupoLabor` |
| Tipo trabajador | Select (Todos / Nómina / Contratista) | No | `contratista` |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Código | `codTercero` |
| Trabajador | `nombreTercero` |
| Cargo | `nombreCargo` |
| Labor | `codLabor` – `nombreLabor` |
| U. medida | `uMedida` |
| Cantidad | SUM(cantidadTercero) |
| Jornales | SUM(jornalTercero) |
| Valor total | SUM(valorTotalTercero) |
| Días laborados | COUNT(DISTINCT fechaLabor) |

Totales de pie: jornales y valor total.

## Indicadores (KPIs)
- Trabajadores con labores.
- Jornales totales.
- Valor total devengado.
- Valor promedio por trabajador.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: trabajador (top 15 por valor); eje X: valor total.
- Por qué: compara trabajadores (muchas categorías, nombres largos).

## Supuestos a validar
- Filtrar por `fechaTransaccion` (legado) o por `fechaLabor`.
- Si se incluyen labores no ejecutadas en nómina (`ejecutadoNomina`).
- `vTransaccionAgronomico` usa `SELECT DISTINCT`: validar que no colapse filas idénticas legítimas.
