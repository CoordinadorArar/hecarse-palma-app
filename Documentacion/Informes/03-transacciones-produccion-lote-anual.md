# Producción lote Anual

**Módulo:** Transacciones · **Clave:** `produccion-lote-anual` · **Estado:** Planificado

## Propósito
Matriz de producción por lote y mes de un año (kilos o toneladas), con acumulado, comparación con el año anterior y proyección anual. Para agronomía y gerencia en el seguimiento de la curva productiva.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaIndicadorAgronomico` (@empresa, @mes, @año): racimos, kilos, peso promedio y t/ha por lote, mes y acumulado año, año actual vs. anterior y proyección `((kilos/hNetas/1000)/mes)*12`. Se descarta `spSeleccionaProduccionAnualN`, que es producción de planta (`vTransaccionesProduccion`).
- Tablas/vistas: `aTransaccion` (TLC), `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad` (`claseLabor = 2`), `aLotes`, `aVariedad`, `aLotePesosPeriodo` (peso promedio del periodo).
- Lógica clave:
  - `anulado = 0`, excluir `aTransaccionEliminada`, `a.año = @año` (y `@año - 1` para comparar).
  - Kilos por lote y mes = `SUM(aTransaccionTercero.cantidad)` agrupado por `aTransaccion.mes`; pivote de 12 columnas + total.
  - Racimos = `SUM(aTransaccionNovedad.racimos)`; peso promedio = kilos / racimos.
  - Proyección año = (t/ha acumulado / meses transcurridos) × 12.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | select | Sí | Año actual por defecto |
| Medida | select (kilos, toneladas, racimos, t/ha) | Sí | Kilos por defecto |
| Finca | select | No | |
| Variedad | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | Código – nombre |
| Año siembra | `añoSiembra` |
| Ha netas | `hNetas` |
| Ene … Dic | Medida elegida por mes |
| Total año | Suma de meses |
| Año anterior | Total del mismo rango del año anterior |
| Var. % | (Total − Anterior) / Anterior |
| Proyección t/ha | Proyección anual |

Totales de pie: por mes y total general.

## Indicadores (KPIs)
- Toneladas acumuladas del año.
- Variación % vs. mismo periodo del año anterior.
- Ton/ha acumulado.
- Proyección t/ha año.

## Gráfica
- Tipo: mapa de calor (lote × mes) + línea.
- Mapa: filas lotes (ordenados por año de siembra), columnas meses, color = medida elegida. Línea complementaria: toneladas totales por mes, año actual vs. año anterior.
- Por qué: la matriz lote × mes se lee mejor como mapa de calor; la línea muestra la estacionalidad y la comparación interanual.

## Supuestos a validar
- Mes por `aTransaccion.mes` (legado) o por mes de `fechaNovedad`.
- El legado filtra `aLotes.activo = 1`; confirmar si se muestran lotes inactivos con producción histórica.
- Si el peso promedio debe venir de `aLotePesosPeriodo` (legado) o calcularse kilos/racimos.
