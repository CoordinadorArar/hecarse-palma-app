# Lotes por variedad

**Módulo:** Administración · **Clave:** `lotes-por-variedad` · **Estado:** Implementado

## Propósito
Muestra la composición del cultivo por variedad de palma (lotes, hectáreas y palmas). Apoya decisiones agronómicas y de renovación.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLotesPorFincaSeccion` (devuelve `variedad`, `nombreVariedad`, `procedenciaVariedad`); no hay proc específico por variedad.
- Tablas/vistas: `aLotes`, `aVariedad`, `aFinca`.
- Lógica clave:
  - `aLotes` LEFT JOIN `aVariedad` ON `aVariedad.codigo = aLotes.variedad AND empresa`.
  - Resumen: GROUP BY variedad → COUNT lotes, SUM hNetas, SUM palmasProduccion, % Ha sobre el total.
  - Detalle: lista de lotes por variedad.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Finca | Select (aFinca) | No | Todas |
| Variedad | Select (aVariedad) | No | Todas |
| Estado del lote | Select | No | Default Activos |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Variedad | Código – descripción |
| Procedencia | `aVariedad.procedencia` |
| Finca | Nombre |
| Lote | Código – nombre |
| Siembra | AAAA-MM |
| Ha netas | `aLotes.hNetas` |
| Palmas producción | `aLotes.palmasProduccion` |
| Densidad | `aLotes.densidad` |

Totales de pie: lotes, Ha netas, palmas.

## Indicadores (KPIs)
- Variedades en uso.
- Variedad predominante (% Ha).
- Hectáreas netas totales.
- Palmas en producción.

## Gráfica
- Tipo: barras horizontales (si hay ≤4 variedades se admite dona).
- Eje Y: variedad; eje X: hectáreas netas; etiqueta con % del total. Orden de mayor a menor.
- Por qué: comparación de composición entre pocas categorías.

## Supuestos a validar
- Lotes con `variedad` vacía o inexistente en `aVariedad` (mostrar "Sin variedad").
- Si la métrica principal debe ser Ha netas o palmas.
