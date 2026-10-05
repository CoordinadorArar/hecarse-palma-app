# Indicadores Año

**Módulo:** Indicadores · **Clave:** `indicadores-anio` · **Estado:** Implementado

## Propósito
Compara, lote por lote, la producción del mes elegido y lo acumulado en el año contra el mismo periodo del año anterior: racimos, kilos, peso promedio del racimo, toneladas por hectárea y proyección anual. Está pensado para la gerencia agronómica y los jefes de finca.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaIndicadorAgronomico` (@empresa, @mes, @año).
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aLotes`, `aVariedad`, `aLotePesosPeriodo`.
- Lógica clave:
  - Solo labores de cosecha: `aNovedad.claseLabor = 2`, `aTransaccion.anulado = 0` y lotes `aLotes.activo = 1`. En la práctica son transacciones TLC.
  - Años comparados: `@año-1` y `@año`. Hay dos periodos: el **mes** (`a.mes = @mes`) y el **acumulado** (`a.mes <= @mes`).
  - Racimos = `SUM(aTransaccionNovedad.racimos)`. Kilos = `SUM(aTransaccionTercero.cantidad)`, uniendo `aTransaccionTercero.registroNovedad = aTransaccionNovedad.registro`.
  - Peso promedio del racimo = `AVG(aLotePesosPeriodo.pesoRacimo)` por lote, año y mes (o meses ≤ @mes).
  - Ton/ha = `SUM(kilos) / aLotes.hNetas / 1000`. Proyección del año = `(ton/ha acumulada / @mes) * 12`, solo para el año actual.
  - El legado devuelve las filas en formato largo (`tipo`, `codTipo` 0–4, `codTipoPeriodo` 0 = mes y 1 = acumulado). Aquí se pasan a columnas por lote.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select (cPeriodo / años con datos) | Sí | Siempre se compara con el año anterior |
| Mes | Select 1–12 | Sí | Define el mes y el corte del acumulado |
| Finca | Select (aFinca) | No | Por defecto, todas |
| Periodo | Radio Mes / Acumulado | No | Por defecto, Acumulado |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | `aLotes.codigo` / descripción |
| Año siembra | `aLotes.añoSiembra` |
| Variedad | `aVariedad.descripcion` |
| Ha netas | `aLotes.hNetas` |
| Palmas producción | `aLotes.palmasProduccion` |
| Racimos año ant. / año act. | Suma de racimos en el periodo |
| Kilos año ant. / año act. | Suma de kilos en el periodo |
| Var. kilos % | (act − ant) / ant |
| Peso prom. racimo ant. / act. | kg por racimo |
| Ton/ha ant. / act. | Toneladas por hectárea neta |
| Proyección ton/ha año | Solo en Acumulado |

Totales de pie: suma de racimos, kilos y ha. Los valores de ton/ha y peso promedio del total se recalculan como cocientes de los totales (sin promediar promedios).

## Indicadores (KPIs)
- Kilos del periodo (año actual) con su variación % frente al año anterior.
- Ton/ha del periodo (total de la empresa o finca).
- Peso promedio del racimo (kilos / racimos).
- Proyección de ton/ha del año.

## Gráfica
- Tipo: barras agrupadas (horizontales).
- Eje Y: lote (top N por kilos del año actual, de mayor a menor). Eje X: ton/ha. Dos series: año anterior y año actual.
- Por qué: se comparan dos años entre muchas categorías (lotes), y como son más de 8 las barras van en horizontal.

## Supuestos resueltos
- Kilos = `SUM(aTransaccionTercero.cantidad)` de las labores de cosecha (`aNovedad.claseLabor = 2`) con unidad `KG`, unidas por `registroNovedad = aTransaccionNovedad.registro`; no se usa báscula. Racimos = `aTransaccionNovedad.racimos` una vez por línea. Las líneas con `signo = 2` restan.
- Peso promedio = kilos / racimos (no `AVG(aLotePesosPeriodo.pesoRacimo)`). Los lotes con kilos sin racimos no entran en el peso y se avisa.
- El mes y el año se toman de la fecha de cosecha (`aTransaccionTercero.fechaNovedad`, o la de la línea si falta) y no de `aTransaccion.año`/`mes`. Así el informe cuadra con `produccion-lote-fecha` y `produccion-lote-anual` (2025 acumulado a diciembre: 7.975.460 kg en los tres).
- Periodo Mes = solo el mes elegido; Acumulado = enero hasta el mes elegido. El año anterior se compara en el mismo periodo.
- Lotes: se incluyen los que tienen cosecha en el periodo en cualquiera de los dos años (no se filtra `aLotes.activo`).
- Proyección t/ha (solo Acumulado) = (t/ha acumulado / meses) × 12. Si el año es el actual y el mes elegido es posterior al último mes con cosecha, se divide por el último mes con cosecha (igual que Producción lote anual) y se avisa. En Mes la columna no se envía.
- Ton/ha, peso promedio, variación y proyección de subtotales y totales son ponderados (cocientes de sumas). Igual que en Producción lote anual, el t/ha y la proyección de cada año dividen solo entre las Ha netas de los lotes con producción en ese periodo y año (2026 a agosto: 12,24 t/ha y proyección 18,36 en ambos informes). Un lote con Ha netas y sin cosecha en un año muestra 0 t/ha en ese año. Lotes sin Ha netas no entran en t/ha ni en la proyección y se avisa.
- Se excluyen las anuladas y, si existe, `aTransaccionEliminada`.
