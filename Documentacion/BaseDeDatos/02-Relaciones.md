# Mapa de Relaciones (Claves Foráneas)

Se detectaron **187 constraints de llave foránea** declaradas explícitamente en `AppPalma` (232 tablas). El detalle completo, tabla por tabla, está en cada archivo de `Diccionario/` (sección "Claves foráneas" / "Tablas que referencian a esta"). Este documento resume las tablas "hub" — las más referenciadas — que conviene entender primero porque casi todo el modelo cuelga de ellas.

## Tablas más referenciadas (entidades núcleo)

| Tabla | Veces referenciada | Rol en el modelo |
|---|---:|---|
| `gEmpresa` | 41 | **Multi-empresa real a nivel de esquema**, no solo de convención: 41 tablas tienen FK explícita a `gEmpresa`, y decenas más (sin FK declarada) filtran por una columna `empresa` (int). `gEmpresa` tiene hoy **2 filas** → el sistema opera 2 compañías sobre la misma base de datos. |
| `cTercero` | 18 | Entidad universal de "tercero" (persona/empresa): empleados, contratistas, clientes, proveedores. La usan Nómina, Contabilidad, Agro (pagos de labores) y Cuentas por Pagar/Cobrar. |
| `aFinca` | 8 | Raíz de la jerarquía agropecuaria: Finca → Sección → Lote. |
| `iItems` | 8 | Catálogo de ítems/productos, usado por Inventario, Báscula y Agro. |
| `nPeriodoDetalle` | 8 | Períodos de nómina — ancla temporal de toda la liquidación de nómina. |
| `nLiquidacionNominaDetalle` | 8 | Detalle de la liquidación de nómina ya calculada (resultado, no catálogo). |
| `gUnidadMedida`, `gTipoTransaccion`, `cCentrosCosto`, `nConcepto` | 6 c/u | Catálogos generales transversales (unidades, tipos de transacción/consecutivos, centros de costo contables, conceptos de nómina). |

## Lectura arquitectónica

- **No hay separación de esquema por módulo** — las 232 tablas viven todas en `dbo`; el "módulo" solo existe por convención de prefijo en el nombre (`a`, `b`, `c`, `n`, `p`, `s`, …, ver leyenda en el `README.md` de este directorio).
- **La integridad referencial es parcial**: 187 FKs declaradas es poco para 232 tablas con este volumen de relaciones evidentes en los procedimientos (p. ej. `aTransaccionTercero` se une a `cTercero`, `aTransaccion`, `aTransaccionNovedad` en varios procedimientos sin que todas esas relaciones tengan un constraint FK). Antes de cualquier refactor o migración, no confiar solo en los FKs declarados para entender qué tablas dependen de cuáles — hay que revisar también los procedimientos (ver `deps` usado para generar `03-Vistas.md` y `05-Procesos-Criticos-Liquidacion.md`).
- **Multi-empresa por convención + FK**: cualquier historia de usuario o migración debe asumir que *todas* las consultas de negocio necesitan filtrar por `empresa`; no hacerlo mezclaría datos de las 2 compañías.
