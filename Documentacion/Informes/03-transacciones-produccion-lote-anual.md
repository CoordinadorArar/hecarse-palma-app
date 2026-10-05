# Producción lote Anual

**Módulo:** Transacciones · **Clave:** `produccion-lote-anual` · **Estado:** Implementado

## Propósito
Matriz de producción por lote y mes de un año (kilos, toneladas, racimos o t/ha), con acumulado, comparación con el mismo periodo del año anterior y proyección anual. Para agronomía y gerencia en el seguimiento de la curva productiva.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaIndicadorAgronomico` (racimos, kilos y t/ha por lote, mes y acumulado año, año actual vs. anterior y proyección `((kilos/hNetas/1000)/mes)*12`). Se descarta `spSeleccionaProduccionAnualN` (producción de planta).
- Tablas base: `aTransaccionNovedad` (línea: lote y racimos) + `aTransaccion` + `aTransaccionTercero` (kilos por trabajador) + `aNovedad` + `aLotes` (`añoSiembra`, `hNetas`, `variedad`, `finca`) + `aVariedad` + `aFinca`.
- Lógica clave (misma base que `produccion-lote-fecha`):
  - Labor de cosecha: `aNovedad.claseLabor = 2` y `uMedida = 'KG'`, tomada de `aTransaccionTercero.novedad`.
  - Por línea de `aTransaccionNovedad`: kilos = `SUM(aTransaccionTercero.cantidad)`; racimos = `aTransaccionNovedad.racimos` una sola vez.
  - Fecha de cosecha: `CONVERT(date, ISNULL(MIN(fechaNovedad), fecha de la línea))`; año y mes se toman de esta fecha.
  - Signo: `aTransaccionNovedad.signo = 2` resta. `anulado = 0`; excluir `aTransaccionEliminada` si existe.
  - Una sola consulta trae el año elegido y el anterior agrupados por lote, año y mes.
  - Meses transcurridos (M): año actual = último mes con cosecha en el año (con los filtros aplicados); años pasados = 12.
  - Proyección t/ha = (t/ha acumulado / M) × 12.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | anio (años con TLC no anuladas) | Sí | Año actual por defecto si existe; si no, el más reciente |
| Medida | select (Kilos, Toneladas, Racimos, Ton/ha) | Sí | Kilos por defecto |
| Finca | select2 (aFinca) | No | Todas por defecto |
| Variedad | select2 (aVariedad) | No | Todas por defecto |

## Resultado (tabla)
Una fila por lote con producción en el año elegido o en el mismo rango de meses del año anterior, agrupada por finca. Fincas en orden alfabético; dentro de cada una, lotes por año de siembra y código.

| Columna | Descripción |
|---|---|
| Finca | código – nombre (agrupación) |
| Lote | código – nombre |
| Variedad | `aVariedad.descripcion` |
| Año siembra | `aLotes.añoSiembra` |
| Ha netas | `aLotes.hNetas` |
| Ene … Dic | Medida del mes; vacío en meses no transcurridos (> M), 0 si no hubo cosecha |
| Total año | Medida acumulada de los meses 1..M (Ton/ha: acumulado / Ha netas) |
| Año anterior | Mismo rango de meses del año anterior; vacío si el lote no produjo |
| Var. % | (Total − Anterior) / Anterior en kilos (racimos si la medida es racimos) |
| Proyección t/ha | (t/ha acumulado / M) × 12 |

Totales de pie: Ha netas, meses y totales (sumados; en Ton/ha ponderados por Ha netas); Var. % y Proyección ponderadas.

## Indicadores (KPIs)
- Toneladas acumuladas (rango de meses y lotes con producción).
- Variación vs. año anterior (mismo periodo, en kilos).
- Ton/ha acumulado (ponderado por Ha netas).
- Proyección t/ha del año.

## Gráfica
- Línea: toneladas por mes del año elegido vs. año anterior completo (mismos filtros).
- Mapa de calor: filas lotes con producción en el año (por año de siembra y código), columnas meses, color = medida elegida; centinelas -2 = mes no transcurrido, -1 = sin cosecha.
- Por qué: la matriz lote × mes se lee mejor como mapa de calor; la línea muestra la estacionalidad y la comparación interanual.

## Supuestos resueltos
- El mes se toma de la fecha de cosecha (no de `aTransaccion.mes`; difieren 21 de 19.499 líneas) para cuadrar con `produccion-lote-fecha` (2025: 7.975.460 kg en ambos).
- El peso promedio se calcula kilos / racimos; no se usa `aLotePesosPeriodo`.
- No se filtra `aLotes.activo`: ningún lote inactivo tiene cosecha.
- Lotes con `hNetas = 0` muestran Ton/ha y proyección vacíos y no entran en los ponderados; se avisa cuando ocurre.
- Sin producción en el año anterior no se calcula variación y se avisa.
- Se consulta sobre las tablas base; se excluyen anuladas y, si existe, `aTransaccionEliminada`.
