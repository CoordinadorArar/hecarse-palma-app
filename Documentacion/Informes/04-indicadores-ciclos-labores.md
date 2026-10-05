# Ciclos de labores

**Módulo:** Indicadores · **Clave:** `ciclos-labores` · **Estado:** Implementado

## Propósito
Muestra, para cada lote y labor agronómica, cuántos ciclos (pases) se han ejecutado en el año y los compara con los ciclos programados de la labor (`aNovedad.ciclos`). Sirve para controlar el cumplimiento del programa de mantenimiento (plateo, poda, control de malezas, etc.).

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaHojaVidaLoteLabores` (@empresa, @fi, @ff). Se reemplaza por consulta directa a tablas base.
- Tablas: `aTransaccionTercero`, `aTransaccionNovedad`, `aTransaccion`, `aNovedad` (`ciclos`, `grupo`, `uMedida`), `aLotes` (`palmasProduccion`, `hNetas`), `aFinca`.
- Lógica clave:
  - Todas las transacciones con labores que no son de cosecha: `ISNULL(aNovedad.claseLabor, 0) <> 2`, `anulado = 0`; se excluye `aTransaccionEliminada` si existe. En la BD esto abarca TLA (clases 1, 5, 6 y la labor 1000 sin maestro) y RLF (fertilización, clases 1 y 6); TLC solo tiene cosecha y queda fuera. Se incluyen las labores sin meta.
  - Fecha de ejecución = `ISNULL(aTransaccionTercero.fechaNovedad, aTransaccionNovedad.fecha)`; rango 1 de enero → fin del mes de corte.
  - Cantidad por lote × labor × mes = `SUM(aTransaccionTercero.cantidad)` (signo 2 resta). Lote = `aTransaccionNovedad.lote`; los registros sin lote se excluyen y se avisa.
  - Pases ejecutados (pase completo): cantidad acumulada / `palmasProduccion` (unidad PAL) o / `hNetas` (unidad HA). Otras unidades (JOR, UND, MT, KG, sin maestro) o lote sin palmas/ha: meses con labor (se marca `porMeses`).
  - Programados al corte = días transcurridos del año hasta el último día del mes de corte (u hoy si es antes) / `aNovedad.ciclos`.
  - % cumplimiento = pases / programados; null sin meta (`ciclos` 0).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | anio (años con TLA/RLF) | Sí | Año actual por defecto |
| Mes de corte | mes | Sí | Mes actual si el año es el actual; si no, diciembre |
| Finca | select2 (aFinca) | No | Finca del lote |
| Grupo de labor | select2 (aGrupoNovedad) | No | |
| Labor | select2 (aNovedad no cosecha) | No | Depende del grupo (`opciones('labor', {grupo})`); sin grupo incluye las labores con registros que no están en el maestro (p. ej. 1000) |
| Meta | select Todas / Solo con meta / Solo atrasadas | Sí | Todas por defecto; solo filtra filas |

## Resultado (tabla)
Agrupada por labor (`labor` = 'COD — Nombre'); una fila por lote con la labor en el periodo; lotes por finca, `numero`, `añoSiembra`, `letra`.

| Clave | Columna | Descripción |
|---|---|---|
| `labor` | Labor | Agrupación |
| `lote` | Lote | código — nombre |
| `finca`, `anioSiembra`, `palmas`, `hNetas`, `uMedida` | | Para el subtexto |
| `m1` … `m12` | Ene … Dic | Cantidad del mes; meses posteriores al corte o no transcurridos: null, `futuro: true` |
| `total` | Total | Cantidad acumulada |
| `pases` | Pases ejec. | Decimal 2 |
| `programados` | Prog. al corte | null sin meta |
| `cumplimiento` | % cumpl. | Fracción; null sin meta; total ponderado `pasesMeta / progMeta` |

Auxiliares: `ciclos`, `pasesAnio`, `porMeses`, `atrasada`, `pasesMeta`, `progMeta`, `cumplimientoLabor`, `atrasadosLabor`, `lotesLabor`.

Totales: cantidades por mes y total solo si hay una labor filtrada; % cumplimiento ponderado.

## Indicadores (KPIs)
- Cumplimiento promedio (ponderado sobre lote-labor con meta).
- Labores con meta / labores ejecutadas ("N / M").
- Lote-labor atrasados (cumplimiento < 100 %).
- Labores con meta sin ejecución (labores activas del catálogo con ciclo, según grupo/labor, sin registros en el periodo).

## Gráfica
- `graficas[0]`: barras horizontales del % de cumplimiento por labor con meta (ascendente, máx. 15) con línea de referencia 100 %.
- `graficas[1]` (solo con una labor filtrada): mapa de calor lote × mes de la cantidad; centinelas -1 = sin labor, -2 = mes no transcurrido. Sin labor filtrada se avisa 'Elija una labor para ver el mapa por lote y los totales de cantidad.'.

## Supuestos resueltos
- Decisión de William: `aNovedad.ciclos` son DÍAS entre pases (12, 30, 120, 180, 360, 365), no pases por año. Programados al corte = días transcurridos al corte / ciclos.
- Decisión de William: un pase ejecutado es un pase completo del lote (cantidad / palmas en producción o / Ha netas según la unidad); en otras unidades o lotes sin palmas/ha se cuentan los meses con labor.
- Se incluyen todas las labores no cosecha (TLA y RLF), con o sin meta; la cosecha (claseLabor 2) queda fuera, incluidas las 4 líneas de cosecha en TLA.
- La labor 1000 no existe en `aNovedad`: se muestra como 'Sin descripción en el maestro', sin meta y con pases por meses.
- Solo se muestran los lote-labor con registros en el periodo; las labores con meta sin ningún registro se cuentan en el KPI.
- KPIs y gráficas se calculan sobre todas las filas; el filtro Meta solo reduce las filas de la tabla y el mapa.
