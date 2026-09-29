# Peso promedio racimos por lote

**Módulo:** Administración · **Clave:** `peso-promedio-racimos-lote` · **Estado:** Planificado

## Propósito
Muestra el peso promedio de racimo (kg/racimo) por lote y periodo, usado para liquidar cosecha y estimar producción. Para jefes de campo y liquidación.

## Fuente de datos
- Procedimiento legado probable: lectura directa de `aLotePesosPeriodo` (lo consultan `spSeleccionaPesoLoteRacimosPeriodo` y `spSeleccionaPesoJerarquiaRacimosPeriodo`). `spSeleccionaPesoPromedioLotePeriodoAutomatico` lo **calcula y escribe** (INSERT/UPDATE): NO se usa aquí; solo se replica su fórmula como columna de control.
- Tablas/vistas: `aLotePesosPeriodo`, `aLotes`, `aFinca`, `aSecciones`; para el cálculo de control `aTransaccion`, `aTransaccionTercero`, `aTransaccionNovedad`, `aNovedad`, `cPeriodo`.
- Lógica clave:
  - Peso registrado: `aLotePesosPeriodo.pesoRacimo` por (año, mes, finca, lote), con `automatico` y vigencia `fechaInicial`–`fechaFinal`.
  - Peso calculado (control) = SUM(`aTransaccionTercero.cantidad`, labores `claseLabor = 2`) / SUM(`aTransaccionNovedad.racimos`, tipo `TLC`) en el rango de `cPeriodo` del mes; excluye `anulado = 1`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select | Sí | Default año actual |
| Mes desde / hasta | Select | No | Default todos |
| Finca | Select (aFinca) | No | |
| Lote | Select dependiente | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Año / Mes | Periodo |
| Finca | Nombre |
| Sección | Código |
| Lote | Código – nombre |
| Peso racimo (kg) | `pesoRacimo` |
| Origen | Automático / Manual (`automatico`) |
| Vigencia | `fechaInicial` – `fechaFinal` |
| Kg cosechados | Control |
| Racimos | Control |
| Peso calculado | Kg / racimos |

Pie: promedio ponderado (Σ kg / Σ racimos).

## Indicadores (KPIs)
- Peso promedio general (kg/racimo).
- Lote con mayor y menor peso.
- Registros manuales (%).

## Gráfica
- Tipo: línea (evolución mensual) — una serie por lote seleccionado, o promedio general si no hay lote; si se elige un solo mes, barras horizontales por lote (top N).
- Ejes: X mes, Y kg/racimo.
- Por qué: el peso de racimo es una serie temporal; la matriz lote×mes completa puede ofrecerse como mapa de calor opcional.

## Supuestos a validar
- Que `aLotePesosPeriodo` sea la fuente oficial (vs recálculo en línea).
- `aLotePesosPeriodo.año/mes` corresponde al mes cosechado, pero su vigencia se aplica al mes siguiente (según el proc automático).
- Mostrar o no las columnas de control del cálculo.
