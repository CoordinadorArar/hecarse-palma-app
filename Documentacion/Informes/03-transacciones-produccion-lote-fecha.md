# Producción lote fecha

**Módulo:** Transacciones · **Clave:** `produccion-lote-fecha` · **Estado:** Planificado

## Propósito
Muestra la fruta cosechada por lote en un rango de fechas (kilos, racimos, peso promedio y toneladas por hectárea). Responde qué lotes están produciendo más y con qué rendimiento, para agronomía y gerencia.

## Fuente de datos
- Procedimiento legado probable: ninguno con rango de fechas; se toma la lógica de `spSeleccionaIndicadorAgronomico` (@empresa, @mes, @año: racimos, kilos, peso promedio, ton/ha por lote). Se descarta `SpSeleccionaInformeProduccionFinal`, que es producción de planta extractora (`vTransaccionesProduccion`).
- Tablas/vistas: `aTransaccion` (tipo TLC), `aTransaccionNovedad` (racimos por lote), `aTransaccionTercero` (kilos por lote y trabajador), `aNovedad` (`claseLabor = 2` cosecha), `aLotes` (añoSiembra, hNetas, palmasProduccion), `aVariedad`, `aFinca`.
- Lógica clave:
  - `anulado = 0`, excluir `aTransaccionEliminada`, `aNovedad.claseLabor = 2`.
  - Fecha: `aTransaccionTercero.fechaNovedad` (fecha de cosecha) entre fi y ff.
  - Kilos = `SUM(aTransaccionTercero.cantidad)` por lote; racimos = `SUM(aTransaccionNovedad.racimos)` por lote (sumar por separado para no multiplicar por trabajadores).
  - Peso promedio racimo = kilos / racimos; t/ha = kilos / 1000 / `hNetas`; kg/palma = kilos / `palmasProduccion`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Finca | select | No | |
| Variedad | select (aVariedad) | No | |
| Año de siembra | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Finca | `aFinca.descripcion` |
| Lote | Código – nombre |
| Variedad | `aVariedad.descripcion` |
| Año siembra | `añoSiembra` |
| Ha netas | `hNetas` |
| Palmas prod. | `palmasProduccion` |
| Racimos | Suma |
| Kilos | Suma |
| Peso prom. racimo | kg |
| Ton/ha | Toneladas por hectárea neta |
| Kg/palma | Kilos por palma |

Totales de pie: Racimos, Kilos; peso promedio y t/ha ponderados.

## Indicadores (KPIs)
- Toneladas totales cosechadas.
- Racimos totales.
- Peso promedio por racimo.
- Ton/ha promedio (ponderado por hectáreas).

## Gráfica
- Tipo: combinada barras + línea.
- Eje X: lote (ordenado por kilos, top 20); barras: toneladas (eje izquierdo); línea: peso promedio racimo (eje secundario).
- Por qué: compara lotes en dos magnitudes distintas (volumen y calidad del racimo).

## Supuestos a validar
- Fecha a usar: `fechaNovedad` (cosecha) vs. `aTransaccion.fecha` (el indicador legado usa año/mes de la transacción).
- Que todas las labores de cosecha tienen `claseLabor = 2` (la 3 corresponde a cargue).
- `hNetas` en cero o nulo: mostrar t/ha vacío.
