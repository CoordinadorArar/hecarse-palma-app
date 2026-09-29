# Indicadores Año

**Módulo:** Indicadores · **Clave:** `indicadores-anio` · **Estado:** Planificado

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

## Supuestos a validar
- Los kilos que se cuentan son los de `aTransaccionTercero.cantidad`, no el peso neto de báscula (`aTransaccionBascula`). Hay que confirmar si los kilos oficiales deben salir de báscula.
- Para el peso promedio el legado usa `AVG(pesoRacimo)` por mes y no kilos/racimos. Hay que definir cuál se muestra.
- El legado filtra por `a.año` y `a.mes` (el periodo contable de la transacción), no por la fecha de la labor. Así se mantiene.
- El join de kilos del legado usa `registroNovedad = e.registro`. Hay que confirmar que coincide con la convención actual.
