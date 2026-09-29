# Lote linea palmas

**Módulo:** Administración · **Clave:** `lote-linea-palmas` · **Estado:** Implementado

## Propósito
Detalle de palmas por línea de cada lote (censo), incluidas erradicadas. Permite cuadrar el censo contra las palmas declaradas del lote; para agronomía y sanidad.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLoteDetalle` (@empresa, @codigo lote). Totales usados en `spSeleccionaLotesPorFincaSeccion` (`SUM(aLotesDetalle.noPalma)`).
- Tablas/vistas: `aLotesDetalle`, `aLotes`, `aFinca`.
- Lógica clave:
  - `aLotesDetalle` (lote, linea, finca, noPalma, izquierda, palmaErradicada) JOIN `aLotes` (lote, empresa).
  - Por lote: líneas = COUNT, palmas censo = SUM(noPalma), erradicadas = SUM(palmaErradicada).
  - Diferencia = `aLotes.palmasProduccion` − SUM(noPalma); `aLotes.NoLineas` vs líneas registradas.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Finca | Select (aFinca) | No | |
| Lote | Select dependiente | No | Sin lote → vista resumen por lote |
| Solo con diferencias | Check | No | Lotes cuyo censo no cuadra |

## Resultado (tabla)
Resumen por lote (sin lote seleccionado):

| Columna | Descripción |
|---|---|
| Finca | Nombre |
| Lote | Código – nombre |
| Líneas declaradas | `aLotes.NoLineas` |
| Líneas registradas | COUNT(linea) |
| Palmas censo | SUM(noPalma) |
| Palmas erradicadas | SUM(palmaErradicada) |
| Palmas producción (lote) | `palmasProduccion` |
| Diferencia | Producción − censo |

Detalle (lote seleccionado): Línea, Lado (Izquierda/Derecha según `izquierda`), Nº palmas, Erradicadas. Totales de pie: palmas y erradicadas.

## Indicadores (KPIs)
- Palmas en censo.
- Palmas erradicadas.
- Lotes con diferencia de censo.
- Promedio palmas por línea.

## Gráfica
- Tipo: barras verticales (solo con lote seleccionado).
- Eje X: número de línea; eje Y: palmas (serie adicional apilada: erradicadas).
- Por qué: muestra la distribución de palmas a lo largo del lote e identifica líneas anómalas; en vista resumen, sin gráfica (listado maestro).

## Supuestos a validar
- Significado de `izquierda` (lado de la línea) y si una línea puede tener dos registros (izq./der.).
- Si `palmaErradicada` es cantidad o indicador.
