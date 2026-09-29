# Lotes por fincas

**Módulo:** Administración · **Clave:** `lotes-por-fincas` · **Estado:** Implementado

## Propósito
Maestro de fincas con sus secciones y lotes (área, palmas, siembra, variedad). Responde "¿qué lotes tiene cada finca y cuánta área/palmas suman?" para administración agronómica y dirección.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLotesPorFincaSeccion` (@empresa, @tipo 'S' socios / 'P' propias / 'T' todas, @activo). Versión antigua: `spSeleccionaLotesPorFinca`.
- Tablas/vistas: `aFinca`, `aLotes`, `aSecciones`, `aVariedad`, `gCiudad`, `cTercero` (propietario = `aFinca.proveedor`), `aLotesDetalle`.
- Lógica clave:
  - `aFinca` LEFT JOIN `aLotes` (finca, empresa) LEFT JOIN `aSecciones` (finca + `aLotes.seccion`).
  - Siembra = `añoSiembra + '-' + mesSiembra` (mes a 2 dígitos).
  - Palmas por detalle = `ISNULL(SUM(aLotesDetalle.noPalma), aLotes.palmasProduccion)`.
  - Tipo de finca: `aFinca.socio = 1` (socios) / `0` (propias). El filtro `@activo` está comentado en el legado; aquí se aplicará sobre `aLotes.activo`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Tipo de finca | Select (Todas / Propias / Socios) | Sí | Default Todas (`aFinca.socio`) |
| Finca | Select (aFinca) | No | Todas por defecto |
| Estado del lote | Select (Activos / Inactivos / Todos) | No | Default Activos (`aLotes.activo`) |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Finca | Código – nombre |
| Propietario | `cTercero.razonSocial` |
| Ciudad / Zona | `gCiudad.nombre`, `aFinca.zonaGeografica` |
| Sección | Código – nombre |
| Lote | Código – nombre |
| Siembra | AAAA-MM |
| Variedad | `aVariedad.descripcion` |
| Ha brutas / Ha netas | `aLotes.hBrutas`, `hNetas` |
| Palmas brutas / producción | `palmasBrutas`, `palmasProduccion` |
| Palmas (detalle) | Suma `aLotesDetalle.noPalma` |
| Densidad | `aLotes.densidad` |
| Estado | Activo / Inactivo |

Totales de pie: Ha brutas, Ha netas, palmas brutas, palmas producción.

## Indicadores (KPIs)
- Fincas (distintas).
- Lotes.
- Hectáreas netas totales.
- Palmas en producción totales.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: finca; eje X: hectáreas netas (tooltip con nº de lotes y palmas). Ordenadas de mayor a menor.
- Por qué: compara el tamaño entre fincas (categorías con nombres largos).

## Supuestos a validar
- Si fincas sin lotes deben aparecer (el legado usa LEFT JOIN y las muestra con lote vacío).
- `aFinca.hectareas` vs suma de `aLotes.hBrutas`: cuál es el área oficial de la finca.
- Semántica exacta de `socio` (1 = finca de socio/proveedor externo).
