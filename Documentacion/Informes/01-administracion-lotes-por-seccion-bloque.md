# Lotes por sec/bloq

**Módulo:** Administración · **Clave:** `lotes-por-seccion-bloque` · **Estado:** Implementado

## Propósito
Agrupa los lotes por finca y sección (bloque) mostrando área y palmas por sección. Sirve a administración de campo para ver la distribución interna de cada finca.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLotesPorFincaSeccion` (mismo dataset que Lotes por fincas, agrupado por sección). Catálogo de secciones: `spSeleccionaSecionFinca` / `spSeleccionaSesionesFinca`.
- Tablas/vistas: `aFinca`, `aSecciones`, `aLotes`, `aVariedad`, `aLotesDetalle`.
- Lógica clave:
  - `aLotes` LEFT JOIN `aSecciones` ON `aSecciones.finca = aLotes.finca AND aSecciones.codigo = aLotes.seccion AND empresa`.
  - Lotes con `manejaSeccion = 0` o `seccion` nula se agrupan como "Sin sección".
  - Agregado por sección: COUNT lotes, SUM hBrutas, SUM hNetas, SUM palmasProduccion; `aSecciones.hBrutas` como área declarada de la sección.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Finca | Select (aFinca) | No | Todas por defecto |
| Sección | Select dependiente de finca | No | |
| Estado del lote | Select (Activos / Inactivos / Todos) | No | Default Activos |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Finca | Código – nombre |
| Sección/Bloque | Código – nombre (`aSecciones`) |
| Ha sección (declaradas) | `aSecciones.hBrutas` |
| Lote | Código – nombre |
| Siembra | AAAA-MM |
| Variedad | `aVariedad.descripcion` |
| Ha brutas / netas | Del lote |
| Palmas producción | Del lote |

Agrupación visual finca → sección con subtotales (lotes, Ha netas, palmas). Totales de pie generales.

## Indicadores (KPIs)
- Secciones.
- Lotes.
- Hectáreas netas.
- Lotes sin sección asignada.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: sección (con prefijo de finca); eje X: hectáreas netas; top 15 de mayor a menor.
- Por qué: comparar tamaño entre secciones, normalmente más de 8 categorías.

## Supuestos a validar
- Si "bloque" es sinónimo de `aSecciones` o corresponde a `aLotes.numero`/`letra`.
- Diferencias entre `aSecciones.hBrutas` y la suma de lotes: ¿mostrar la diferencia?
