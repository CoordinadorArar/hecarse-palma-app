# Rendimiento trabajador cosecha

**Módulo:** Indicadores · **Clave:** `rendimiento-trabajador-cosecha` · **Estado:** Planificado

## Propósito
Mide los kilos cosechados por cada trabajador, por día y en total, y los compara con la tarea o rendimiento esperado de la labor de cosecha. Es para supervisores de cosecha y nómina.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaRendimientoLabores` (@empresa, @fi, @ff).
- Tablas/vistas: `vTransaccionAgronomico` (aTransaccion, aTransaccionNovedad, aTransaccionTercero, aNovedad, cTercero, aLotes).
- Lógica clave:
  - `anulado = 0`, `aNovedad.claseLabor = 2` (cosecha) y la fecha de la transacción entre @fi y @ff. El legado **no filtra por empresa**; la versión nueva sí debe hacerlo.
  - Por trabajador × fecha de labor: cantidad = `SUM(aTransaccionTercero.cantidad)` (kilos). Rendimiento esperado = `aNovedad.tarea`.
  - Derivadas: días trabajados (fechas distintas), kilos promedio por día, % sobre la tarea (kilos día / tarea), racimos (`aTransaccionTercero.racimos`) y jornales.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | Fecha | Sí | |
| Fecha final | Fecha | Sí | |
| Finca | Select (aFinca) | No | |
| Trabajador | Autocompletar (cTercero) | No | |
| Detalle | Radio Resumen / Por día | No | Por defecto, Resumen |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Documento | NIT |
| Trabajador | Nombre |
| Fecha labor | Solo en la vista Por día |
| Días trabajados | Solo en Resumen |
| Racimos | Suma |
| Kilos | Suma |
| Jornales | Suma |
| Kilos/día | Kilos / días (o / jornales) |
| Tarea (kg) | `aNovedad.tarea` |
| % cumplimiento | Kilos/día ÷ tarea |

Totales de pie: racimos, kilos, jornales y kilos/día promedio ponderado.

## Indicadores (KPIs)
- Kilos cosechados totales.
- Kilos promedio por trabajador-día.
- % de trabajadores que cumplen la tarea.
- Trabajadores activos en el rango.

## Gráfica
- Tipo: barras horizontales con línea de referencia.
- Eje Y: trabajador (top 20 por kilos/día, de mayor a menor). Eje X: kilos/día. Una línea vertical marca la tarea.
- Por qué: compara muchos trabajadores con nombres largos contra una meta fija.

## Supuestos a validar
- Si `aNovedad.tarea` es el rendimiento diario esperado (kg/día) para la cosecha, o si hay que tomar las tareas por año de siembra (`añoDesde`/`añoHasta`, ver `spSeleccionaNovedadLoteRangoSiembra`).
- Las labores de cosecha pueden tener varias novedades según la edad del lote. En ese caso la tarea se promedia ponderada por kilos.
- La unidad de `cantidad` en cosecha es kilos (TLC con báscula). Hay que confirmarlo.
