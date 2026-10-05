# Indicadores Año por año de siembra

**Módulo:** Indicadores · **Clave:** `indicadores-anio-siembra` · **Estado:** Implementado

## Propósito
Muestra los indicadores de producción (racimos, kilos, peso promedio, ton/ha, proyección) agrupados por año de siembra (edad de la palma) y los compara con el año anterior. Sirve para evaluar la curva productiva de cada cohorte de siembra.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaIndicadorAgronomico` (el mismo de "Indicadores Año"), reagrupando sus filas por `añoSiembra`. No hay un procedimiento propio.
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aLotes`, `aLotePesosPeriodo`.
- Lógica clave:
  - Mismo universo que Indicadores Año: `claseLabor = 2`, `anulado = 0`, lotes activos, años `@año-1` y `@año`, periodo Mes o Acumulado.
  - Agrupación por `aLotes.añoSiembra`. Racimos y kilos se suman. Ha netas y palmas en producción se suman sobre los lotes distintos del grupo.
  - Ton/ha del grupo = `SUM(kilos) / SUM(hNetas) / 1000`, calculado sobre totales y no como promedio de lotes.
  - Peso promedio = `SUM(kilos) / SUM(racimos)`, o el promedio ponderado de `aLotePesosPeriodo.pesoRacimo`.
  - Edad = `@año − añoSiembra`.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select | Sí | Se compara con el año anterior |
| Mes | Select 1–12 | Sí | Corte del mes / acumulado |
| Finca | Select (aFinca) | No | |
| Periodo | Radio Mes / Acumulado | No | Por defecto, Acumulado |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Año siembra | `aLotes.añoSiembra` |
| Edad (años) | @año − añoSiembra |
| N.º lotes | Lotes del grupo |
| Ha netas | Suma de hNetas |
| Palmas producción | Suma |
| Racimos ant. / act. | |
| Kilos ant. / act. | |
| Var. kilos % | |
| Peso prom. racimo ant. / act. | kg/racimo |
| Ton/ha ant. / act. | |
| Proyección ton/ha año | Solo en Acumulado |

Totales de pie: suma de lotes, ha, palmas, racimos y kilos. Ton/ha y peso promedio del total se calculan como cocientes.

## Indicadores (KPIs)
- Kilos del periodo con su variación % frente al año anterior.
- Ton/ha total.
- Año de siembra con mayor ton/ha.
- Proyección de ton/ha del año.

## Gráfica
- Tipo: combinada, barras + línea con eje secundario.
- Eje X: año de siembra, en orden cronológico. Barras: ton/ha año anterior y año actual (eje izquierdo). Línea: peso promedio del racimo del año actual (eje derecho).
- Por qué: combina dos magnitudes distintas (t/ha y kg/racimo) sobre una dimensión ordenada (edad) que tiene pocas categorías.

## Supuestos resueltos
- Usa la misma consulta y agregación por lote de Indicadores Año y la reagrupa por `aLotes.añoSiembra` (lotes sin año → "Sin año", al final de la tabla, fuera de la gráfica y del KPI de mejor siembra, con aviso). La suma por año de siembra es igual a la suma por lote.
- Kilos = `SUM(aTransaccionTercero.cantidad)` de las labores de cosecha (`aNovedad.claseLabor = 2`) con unidad `KG`; no se usa báscula. Racimos = `aTransaccionNovedad.racimos` una vez por línea. Las líneas con `signo = 2` restan.
- Peso promedio del grupo = kilos / racimos (no `aLotePesosPeriodo`).
- El mes y el año se toman de la fecha de cosecha y no de `aTransaccion.año`/`mes`, para cuadrar con `produccion-lote-anual` (2025 acumulado a diciembre: 7.975.460 kg).
- Periodo Mes = solo el mes elegido; Acumulado = enero hasta el mes elegido. El año anterior se compara en el mismo periodo.
- Lotes: los que tienen cosecha en el periodo en cualquiera de los dos años. Ha netas y palmas se suman sobre esos lotes; edad = año − año de siembra.
- Ton/ha y proyección del grupo = cociente de sumas: toneladas / Ha netas de los lotes del grupo con producción en ese periodo y año, igual que en Producción lote anual. La columna Ha netas muestra la suma de todos los lotes del grupo. Proyección (solo Acumulado) = (t/ha acumulado / meses) × 12; en el año actual, si el mes elegido es posterior al último mes con cosecha, se divide por ese último mes.
- La gráfica muestra barras de t/ha y línea de kg/racimo del año elegido; el año anterior va en el detalle del tooltip.
- Se excluyen las anuladas y, si existe, `aTransaccionEliminada`.
