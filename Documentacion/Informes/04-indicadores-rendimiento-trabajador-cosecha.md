# Rendimiento trabajador cosecha

**Módulo:** Indicadores · **Clave:** `rendimiento-trabajador-cosecha` · **Estado:** Implementado

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

## Supuestos resueltos
- Implementado en `app/Libraries/Informes/Indicadores/RendimientoTrabajadorCosecha.php` con la misma base que Producción lote fecha: labores `claseLabor = 2` y `uMedida = 'KG'`, anuladas fuera y, si existe, `aTransaccionEliminada`; solo líneas con lote existente. Filtra por empresa.
- Fecha = fecha de cosecha de la línea `ISNULL(MIN(fechaNovedad), fecha de la línea)`; rango máximo de 1 año. Finca = finca del lote.
- Kilos = `aTransaccionTercero.cantidad` del trabajador; jornales = `aTransaccionTercero.jornales`; si suman 0 en el rango (hoy siempre) la columna Jornales no se envía. Las líneas con `signo = 2` restan.
- **Racimos:** `aTransaccionTercero.racimos` repite el racimo de la línea en cada trabajador y no sirve. Se reparte `aTransaccionNovedad.racimos` de la línea en proporción a los kilos del trabajador (`racimos × kilos trabajador / kilos de la línea`; si la línea no tiene kilos, en partes iguales). La suma cuadra con Producción lote fecha (jun-2025: 456.580 kg y 45.448 racimos). Los racimos se envían con decimales (la tabla los muestra enteros) para que filas, subtotales, total y KPI cuadren.
- Tarea = `aNovedad.tarea` ponderada por kilos solo en labores con tarea > 0 (hoy solo la 439, 380 kg). % cumplimiento = (kilos en labores con tarea / días con tarea) ÷ tarea; sin tarea queda vacío y se avisa.
- Kg/día = kilos / días con cosecha (fechas distintas). Se incluyen todos los trabajadores (propios y de contratistas).
- Gráfica: top 20 por kg/día entre trabajadores con 3 días o más; la referencia es la tarea si todos los trabajadores tienen la misma, si no el promedio kg por trabajador-día.
