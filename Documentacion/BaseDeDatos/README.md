# Documentación de Base de Datos — `AppPalma`

Levantada por lectura directa contra el servidor **172.28.254.26**, base **`AppPalma`**, con el usuario de solo consulta **`consultaweb`** (ver [Documentacion/HistoriasUsuario](../HistoriasUsuario/) para el contexto de por qué se cambiaron las credenciales). Todo lo aquí documentado se extrajo con metadatos del propio motor (`sys.tables`, `sys.columns`, `sys.foreign_keys`, `sys.sql_modules`, `sys.sql_expression_dependencies`) — es un reflejo fiel del estado actual de la base, no una interpretación de diagramas antiguos.

**Motor:** SQL Server 2017 (14.0.2105.1).

## Tamaño real del modelo

| Objeto | Cantidad |
|---|---:|
| Tablas | 232 |
| Vistas | 80 |
| Procedimientos almacenados | 1.648 |
| Funciones | 18 |
| Relaciones FK declaradas | 187 |

## Cómo navegar esta carpeta

| Archivo/carpeta | Contenido |
|---|---|
| [Diccionario/](Diccionario/) | Diccionario de columnas de las 232 tablas, **agrupado por módulo** (un `.md` por módulo, ver leyenda abajo). Cada tabla incluye columnas, tipos, PK, FKs entrantes y salientes, y su conteo de filas actual. |
| [tablas_columnas.csv](tablas_columnas.csv) | Las mismas 3.096 columnas en una sola tabla plana, para Excel/BI. |
| [02-Relaciones.md](02-Relaciones.md) | Tablas "hub" del modelo (más referenciadas) y lectura arquitectónica de las relaciones. |
| [03-Vistas.md](03-Vistas.md) | Catálogo de las 80 vistas con las tablas que consulta cada una. |
| [vistas_definiciones.sql](vistas_definiciones.sql) | SQL completo de las 80 vistas. |
| [04-Procedimientos.md](04-Procedimientos.md) | Cómo se clasificaron los 1.648 procedimientos (por verbo/operación y por módulo) + hallazgos notables. |
| [procedimientos_catalogo.csv](procedimientos_catalogo.csv) | Catálogo completo de los 1.648 procedimientos (nombre, módulo, operación, parámetros, fechas) — importable a Jira/Confluence/Excel. |
| [05-Procesos-Criticos-Liquidacion.md](05-Procesos-Criticos-Liquidacion.md) | **La parte más valiosa de este análisis**: cómo funcionan realmente los procesos de negocio grandes (báscula/tiquetes, liquidación de precios de labores, replicación entre empresas, liquidación y contabilización de nómina), leídos directamente del código T-SQL. Resuelve varios puntos que habían quedado pendientes (⚠️) en el backlog de historias de usuario. |
| [sql_criticos/](sql_criticos/) | Código fuente completo (`.sql`) de los ~21 procedimientos más importantes citados en el punto anterior, tal como viven hoy en la base. |
| [06-Funciones.md](06-Funciones.md) | Las 18 funciones del sistema, con su código completo (son pocas, se documentan enteras). |

## Leyenda de módulos (por prefijo de nombre de tabla)

El esquema es un único `dbo`, sin separación real por schema — el "módulo" es una convención de nombre:

| Prefijo | Módulo | Ejemplo |
|---|---|---|
| `a` | Agro (fincas, lotes, transacciones de campo) | `aFinca`, `aTransaccion` |
| `b` | Báscula y transporte | `bRegistroBascula`, `bVehiculo` |
| `c` | Contabilidad | `cPuc`, `cTercero` |
| `cxc` | Cuentas por cobrar | `cxcCliente` |
| `cxp` | Cuentas por pagar | `cxpProveedor` |
| `f` | Mercado | `fMercado` |
| `g` | Catálogos generales | `gEmpresa`, `gCiudad` |
| `h_` | Histórico / legado | `h_Usuarios` |
| `i` | Inventario / ítems / bodega | `iItems`, `iBodega` |
| `l` | Laboratorio (calidad, tanques) | `lAnalisis`, `lTanque` |
| `log` | Logística de despacho | `logDespacho` |
| `n` | Nómina | `nFuncionario`, `nContratos` |
| `p` | Planta / proceso de extracción de palma (fruta, pepa, tanques) | `pTransaccion`, `pDensidad` |
| `s` | Seguridad y sistema | `sUsuarios`, `sPerfiles` |
| `sys` | Metadatos de sistema (motor dinámico interno) | `sysEntidadCampo` |
| `tmp` | Temporales / staging de procesos batch | `tmpliquidacionNomina` |

Confirma y refina lo que el análisis de la aplicación web ya sugería: **es un sistema agroindustrial** (finca/ganado + extracción de aceite de palma — "fruta"/"pepa"/"racimos"/"canal" son términos del proceso de beneficio de palma africana) con nómina y contabilidad colombianas completas (seguridad social, embargos, cesantías, integración con SIESA).

## Hallazgo arquitectónico principal

**La lógica de negocio vive en los procedimientos almacenados, no en el código .NET.** Varios superan las 1.000–2.000 líneas (el mayor, `spPrecontabilizaNominaTipoPeriodo`, tiene ~141.000 caracteres). Cualquier plan de mantenimiento, modernización o reescritura debe presupuestar la migración/relectura de esta capa como el esfuerzo principal — el front-end Web Forms es, comparativamente, la parte más simple del sistema.

## Nota de seguridad sobre este levantamiento

Esta documentación se generó con el usuario `consultaweb`, que según lo solicitado **solo tiene acceso a la base `AppPalma`** (no se intentó ni se pudo consultar ninguna otra base del servidor 172.28.254.26). Los scripts usados para generar este catálogo quedaron en el scratchpad de la sesión, no en este repositorio — si se quiere repetir este levantamiento (por ejemplo, tras cambios de esquema), avisar para regenerarlo.
