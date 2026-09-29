# Lotes por año siembra

**Módulo:** Administración · **Clave:** `lotes-por-anio-siembra` · **Estado:** Implementado

## Propósito
Distribuye lotes, hectáreas y palmas por año de siembra (edad del cultivo). Clave para proyectar producción y planear renovación.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLotesPorFincaSeccion` (columnas `añoSiembra`, `siembra`). Relacionado: `spSeleccionaNovedadLoteRangoSiembra` (edad = año de la labor − `añoSiembra`).
- Tablas/vistas: `aLotes`, `aFinca`, `aVariedad`.
- Lógica clave:
  - GROUP BY `aLotes.añoSiembra` → COUNT lotes, SUM hNetas, SUM palmasProduccion.
  - Edad (años) = `YEAR(GETDATE()) - añoSiembra`.
  - Detalle por lote con mes de siembra.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Finca | Select (aFinca) | No | Todas |
| Año siembra desde / hasta | Número | No | Rango |
| Variedad | Select (aVariedad) | No | |
| Estado del lote | Select | No | Default Activos |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Año siembra | `añoSiembra` |
| Edad (años) | Calculada |
| Finca | Nombre |
| Lote | Código – nombre |
| Mes siembra | `mesSiembra` |
| Variedad | Descripción |
| Ha netas | `hNetas` |
| Palmas producción | `palmasProduccion` |
| Estado cultivo | Desarrollo / Producción (`aLotes.desarrollo`) |

Totales de pie: lotes, Ha netas, palmas.

## Indicadores (KPIs)
- Edad promedio ponderada por Ha.
- Año de siembra más antiguo / más reciente.
- Ha en desarrollo vs en producción.
- Total Ha netas.

## Gráfica
- Tipo: barras verticales (orden cronológico por año).
- Eje X: año de siembra; eje Y: hectáreas netas; tooltip con lotes y palmas.
- Por qué: distribución sobre una dimensión temporal ordinal con pocos valores.

## Supuestos a validar
- Si `aLotes.desarrollo` es el indicador oficial de lote en desarrollo.
- Tratamiento de lotes con `añoSiembra = 0`.
