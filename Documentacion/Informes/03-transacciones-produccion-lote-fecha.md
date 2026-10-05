# Producción lote fecha

**Módulo:** Transacciones · **Clave:** `produccion-lote-fecha` · **Estado:** Implementado

## Propósito
Muestra la fruta cosechada por lote en un rango de fechas (kilos, racimos, peso promedio y toneladas por hectárea). Responde qué lotes están produciendo más y con qué rendimiento, para agronomía y gerencia.

## Fuente de datos
- Procedimiento legado probable: ninguno con rango de fechas; se toma la lógica de `spSeleccionaIndicadorAgronomico` (racimos, kilos, peso promedio, ton/ha por lote). Se descarta `SpSeleccionaInformeProduccionFinal` (producción de planta extractora).
- Tablas base: `aTransaccionNovedad` (línea: lote y racimos) + `aTransaccion` + `aTransaccionTercero` (kilos por trabajador) + `aNovedad` + `aLotes` (`añoSiembra`, `hNetas`, `palmasProduccion`, `variedad`, `finca`) + `aVariedad` + `aFinca`.
- Lógica clave:
  - Labor de cosecha: `aNovedad.claseLabor = 2` y `uMedida = 'KG'`, tomada de `aTransaccionTercero.novedad`.
  - Por cada línea de `aTransaccionNovedad` con trabajadores de cosecha: kilos = `SUM(aTransaccionTercero.cantidad)`; racimos = `aTransaccionNovedad.racimos` una sola vez por línea.
  - Fecha de cosecha: `CONVERT(date, ISNULL(fechaNovedad, fecha de la línea))` entre fi y ff.
  - Signo: `aTransaccionNovedad.signo = 2` resta.
  - `anulado = 0`; excluir `aTransaccionEliminada` si existe.
  - Peso promedio = kilos / racimos; Ton/ha = kilos / 1000 / `hNetas`; Kg/palma = kilos / `palmasProduccion` (calculados en la app).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes. Fecha de cosecha |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Máximo 1 año de rango |
| Finca | select2 (aFinca) | No | Finca del lote. Todas por defecto |
| Variedad | select2 (aVariedad) | No | Variedad del lote. Todas por defecto |
| Año de siembra | select (años de aLotes) | No | Todos por defecto |

## Resultado (tabla)
Una fila por lote con producción, agrupada por finca. Fincas en orden alfabético y, dentro de cada una, lotes por código.

| Columna | Descripción |
|---|---|
| Finca | código – nombre (agrupación) |
| Lote | código – nombre |
| Variedad | `aVariedad.descripcion` |
| Año siembra | `aLotes.añoSiembra` |
| Ha netas | `aLotes.hNetas` |
| Palmas prod. | `aLotes.palmasProduccion` |
| Racimos | suma por línea |
| Kilos | suma de trabajadores |
| Peso prom. (kg) | kilos / racimos; subtotal y total ponderados |
| Ton/ha | kilos / 1000 / Ha netas; ponderado, excluye lotes sin Ha netas |
| Kg/palma | kilos / palmas; ponderado, excluye lotes sin palmas |
| Días cosecha | fechas distintas con cosecha en el lote |

Totales de pie: Ha netas, Palmas, Racimos, Kilos; Peso prom., Ton/ha y Kg/palma ponderados.

## Indicadores (KPIs)
- Toneladas cosechadas (lotes, fincas y días con cosecha).
- Racimos cosechados (kilos).
- Peso promedio por racimo (rango por lote; lotes sin racimos excluidos).
- Ton/ha promedio (ponderado por Ha netas; máximo por lote; lotes sin Ha netas excluidos).

## Gráfica
- Tipo: combinada barras + línea.
- Eje X: lote (top 20 por kilos); barras: toneladas (eje izquierdo); línea: peso promedio racimo (eje secundario).
- Detalle por lote: finca, racimos, Ha netas, Ton/ha y Kg/palma.
- Por qué: compara lotes en dos magnitudes distintas (volumen y calidad del racimo).

## Supuestos resueltos
- Se filtra por fecha de cosecha `ISNULL(fechaNovedad, fecha de la línea)` (`fechaNovedad` es nula en ~31% de los registros), no por la fecha de la transacción.
- Desde 2022 `aTransaccionNovedad.novedad` viene vacía; la labor se toma de `aTransaccionTercero.novedad`.
- Los racimos se toman de `aTransaccionNovedad.racimos` una vez por línea; `aTransaccionTercero.racimos` repite el valor de la línea en cada trabajador y no se suma.
- `SUM(aTransaccionTercero.cantidad)` por línea coincide con `aTransaccionNovedad.cantidad`; todos los trabajadores de una línea tienen la misma fecha.
- Cosecha = `claseLabor = 2` y `uMedida = 'KG'` (existe una labor clase 2 en jornales que se excluye). No hay clase 3 en TLC.
- Lotes con `hNetas = 0` muestran Ton/ha vacío y no entran en el ponderado; igual los lotes sin palmas para Kg/palma y sin racimos para el peso promedio. Se avisa cuando ocurre.
- Se consulta sobre las tablas base; se excluyen anuladas y, si existe, `aTransaccionEliminada`.
