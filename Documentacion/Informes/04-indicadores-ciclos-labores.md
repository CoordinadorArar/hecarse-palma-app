# Ciclos de labores

**Módulo:** Indicadores · **Clave:** `ciclos-labores` · **Estado:** Planificado

## Propósito
Muestra, para cada lote y labor agronómica, cuántos ciclos (pases) se han ejecutado en el año y los compara con los ciclos programados de la labor (`aNovedad.ciclos`). Sirve para controlar el cumplimiento del programa de mantenimiento (plateo, poda, control de malezas, etc.).

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaHojaVidaLoteLabores` (@empresa, @fi, @ff). Devuelve la cantidad por labor × lote × año/mes desde `vTransaccionAgronomico`. No hay un procedimiento que se llame "ciclos".
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad` (`ciclos`, `grupo`, `uMedida`, `porHaNeta`/`porHaBruta`), `aLotes`, `aFinca`.
- Lógica clave:
  - Labores TLA (y TLC si se incluye cosecha) con `anulado = 0`, dentro del rango de fechas. Se excluyen las transacciones que estén en `aTransaccionEliminada`.
  - Cantidad ejecutada por lote × labor × mes = `SUM(aTransaccionTercero.cantidad)`.
  - Ciclos ejecutados en el año: se propone contar los **meses con labor** (`COUNT(DISTINCT mes)`) por lote × labor. Como alternativa, cantidad acumulada / cantidad de un pase completo (palmas o ha del lote según `aNovedad.uMedida`).
  - Ciclos programados = `aNovedad.ciclos`, prorrateados al corte: `ciclos × mesCorte / 12`.
  - % cumplimiento = ciclos ejecutados / ciclos programados al corte.
  - Respaldo cuando no hay meta: si `aNovedad.ciclos` es 0 o NULL, Ciclos programados y % cumplimiento quedan en "—" y solo se muestran los pases reales. Esas filas no entran en el KPI de cumplimiento.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select | Sí | |
| Mes corte | Select 1–12 | No | Por defecto, el mes actual |
| Finca | Select (aFinca) | No | |
| Grupo de labor | Select (aGrupoNovedad) | No | |
| Labor | Select (aNovedad) | No | Depende del grupo |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | Código |
| Año siembra | |
| Labor | Código y descripción |
| U. medida | `aNovedad.uMedida` |
| Ene … Dic | Cantidad ejecutada en el mes |
| Cantidad total | Suma del año |
| Ciclos ejecutados | Meses con labor (o cantidad / pase completo) |
| Ciclos programados | `aNovedad.ciclos` (prorrateado) |
| % cumplimiento | |

Totales de pie: cantidad por mes y total.

## Indicadores (KPIs)
- % de cumplimiento promedio de ciclos (solo en las labores con meta).
- Labores con meta / labores ejecutadas.
- Lote × labor atrasados (cumplimiento < 100 %).
- Labores sin ejecución en el periodo.

## Gráfica
- Tipo: mapa de calor.
- Filas: lote (o lote-labor si se eligen varias labores). Columnas: meses. Intensidad: cantidad ejecutada.
- Por qué: es una matriz lote × mes, y el mapa de calor deja ver los meses sin pase.

## Supuestos a validar
- Falta definir qué es un "ciclo": un mes con labor o un pase completo del lote (cantidad ≥ palmas o ha). Hay que confirmarlo con William u operaciones.
- `aNovedad.ciclos` es la meta anual de pases. Dato verificado en la BD: solo 28 de 143 labores activas (empresa 1) tienen `ciclos > 0`. Para las demás se aplica el respaldo: sin meta, solo los pases reales. Hay que confirmar con operaciones si se completan las metas en el catálogo de labores.
- Falta decidir si se incluyen las labores de cosecha (claseLabor 2) o solo las de mantenimiento (TLA).
