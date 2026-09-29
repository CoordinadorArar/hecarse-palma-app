# Finca - Lote metros de canal

**Módulo:** Administración · **Clave:** `finca-lote-metros-canal` · **Estado:** Implementado

## Propósito
Metros de canal registrados por lote y tipo de canal, agrupados por finca. Sirve para planear y valorar labores de limpieza/mantenimiento de canales.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLoteCanalInforme` (@empresa). Por lote individual: `spSeleccionaLoteCanal`.
- Tablas/vistas: `aLotes`, `aLotesCanal`, `aTipoCanal`, `aFinca`.
- Lógica clave:
  - `aLotes` JOIN `aLotesCanal` (lote, empresa) JOIN `aTipoCanal` (tipoCanal) JOIN `aFinca` (finca).
  - Metros = `aLotesCanal.metros`; subtotales por finca y por tipo de canal.
  - Densidad de canal (m/Ha) = metros / `aLotes.hNetas`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Finca | Select (aFinca) | No | Todas |
| Tipo de canal | Select (aTipoCanal) | No | Todos |
| Estado del lote | Select | No | Default Activos |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Finca | Código – nombre |
| Sección | `aLotes.seccion` |
| Lote | Código – nombre |
| Año siembra | `añoSiembra` |
| Ha netas | `hNetas` |
| Tipo de canal | Código – descripción |
| Metros | `metros` |
| m/Ha | Calculado |

Totales de pie: metros; subtotales por finca.

## Indicadores (KPIs)
- Metros totales de canal.
- Lotes con canal.
- m/Ha promedio.
- Tipo de canal predominante.

## Gráfica
- Tipo: barras apiladas horizontales.
- Eje Y: finca; eje X: metros; series: tipo de canal.
- Por qué: compara fincas y muestra la composición por tipo de canal a la vez.

## Supuestos a validar
- Si deben listarse lotes sin canal (el legado usa JOIN interno y los excluye).
- Unidades de `metros` (float) y registros duplicados por `registro`.
