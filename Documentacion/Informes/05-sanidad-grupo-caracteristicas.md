# Grupo de características

**Módulo:** Sanidad · **Clave:** `grupo-caracteristicas` · **Estado:** Planificado

## Propósito
Es el listado maestro de los grupos de características sanitarias y las características que contiene cada uno. Sirve de referencia para los registros de sanidad y para revisar la configuración del catálogo.

## Fuente de datos
- Procedimiento legado probable: `spInformeGrupoCaracteristica` (@empresa).
- Tablas/vistas: `aGrupoCaracteristica`, `aCaracteristica`.
- Lógica clave:
  - `aGrupoCaracteristica a JOIN aCaracteristica b ON b.grupoCaracteristica = a.codigo AND b.empresa = a.empresa WHERE a.empresa = @empresa`.
  - Se agregan `activo` de las dos tablas y `aCaracteristica.manejaCaractistica`.
  - Opcional: número de registros de sanidad por característica (`aSanidadDetalle.caracteristica`) para ver cuáles se usan.
  - Se usa LEFT JOIN para que aparezcan también los grupos sin características.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Grupo | Select (aGrupoCaracteristica) | No | |
| Estado | Select Activos / Inactivos / Todos | No | Por defecto, Activos |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Cód. grupo | `aGrupoCaracteristica.codigo` |
| Grupo | Descripción |
| Cód. característica | `aCaracteristica.codigo` |
| Característica | Descripción |
| Maneja característica | Sí/No |
| Activo | Sí/No (badge) |

Sin totales.

## Indicadores (KPIs)
- Grupos (activos).
- Características (activas).

## Gráfica
- Tipo: sin gráfica.
- Por qué: es un catálogo maestro sin una dimensión que analizar. Los conteos por grupo ya aparecen en los KPIs y en la tabla.

## Supuestos a validar
- Datos verificados en la BD: `aCaracteristica` tiene 100 características, pero casi no se usan, porque `aSanidadDetalle` tiene solo 2 filas. La columna opcional "N.º de usos" saldrá prácticamente en 0. El valor del informe es consultar la configuración del catálogo, no analizar su uso.
- Se incluyen los inactivos cuando se filtra por "Todos". El legado no filtra por `activo`.
- La columna se llama `manejaCaractistica` en el esquema (con la errata original).
