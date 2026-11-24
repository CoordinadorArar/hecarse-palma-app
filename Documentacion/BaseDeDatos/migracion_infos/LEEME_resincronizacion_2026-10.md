# Resincronización Infos -> AppPalma (octubre 2026)

> **OBSOLETO desde 2026-11-04: Infos ya no se usa.** Todo este procedimiento quedó sin efecto y **no debe ejecutarse**: sincronizar desde Infos borraría datos actuales de AppPalma. Los scripts 02, 04 y 06 y `app/Database/sql/resincronizar_tlc_desde_4371.sql` están bloqueados (RAISERROR + `SET NOEXEC ON`).

AppPalma debe quedar igual a Infos (Infos manda), incluidos números de transacción y consecutivos. Hay dos excepciones decididas a propósito: la proyección de `aLotePesosPeriodo` y la tabla `aLotes`.

## 1. Requisitos

- Respaldo completo de **Infos** y de **AppPalma** antes de empezar.
- Nadie registrando en Infos ni en AppPalma mientras dure el proceso. Hoy el usuario `operativo` registra en las dos; hay que avisarle y esperar a que termine.
- Ninguna actividad de planeación vinculada a transacciones. Verificar antes con `SELECT COUNT(*) FROM AppPalma.dbo.pPlaneacionLinea WHERE numeroTransaccion IS NOT NULL` (debe dar 0). Si hay alguna, el 02 se detiene con el error 50001 sin cambiar nada.
- Ejecutar conectado a AppPalma, con un usuario que lea Infos y tenga permiso para `DBCC CHECKIDENT` (lo necesita el 04).
- Ejecutar siempre desde SSMS o con `sqlcmd -f 65001 -i <archivo>.sql`, porque los archivos son UTF-8 y tienen columnas `[año]`.

## 2. Orden de ejecución

| Paso | Script | Cómo |
|------|--------|------|
| 1 | `01_diagnostico_diferencias.sql` | Solo lectura. Guardar el resultado como foto del "antes". |
| 2 | `02_sincronizar_infos_a_apppalma.sql` | Primero con `@Aplicar = 0` y revisar los conteos; si están bien, ejecutar con `@Aplicar = 1`. Dejar `@ConservarPesosSoloAppPalma = 1`. |
| 3 | `04_limpieza_y_consecutivos.sql` | `@Aplicar = 0`, revisar; luego `@Aplicar = 1`. |
| 4 | `06_complemento_resincronizacion.sql` | `@Aplicar = 0`, revisar; luego `@Aplicar = 1`. |
| 5 | `01_diagnostico_diferencias.sql` | Volver a ejecutarlo para comprobar el resultado. |

Con `@Aplicar = 0` todo se revierte (ROLLBACK). Si aparece cualquier error, el script revierte todo y no queda ningún cambio.

Qué hace cada uno:

- **02**: deja 15 tablas de AppPalma idénticas a Infos (transacciones, terceros, funcionarios, consecutivos de `gTipoTransaccion`, `sLogRegistros`, etc.). Con `@ConservarPesosSoloAppPalma = 1` **no borra** las 250 filas de `aLotePesosPeriodo` que solo existen en AppPalma. No toca `aLotes`.
- **04**: borra los documentos de prueba y alinea los IDENTITY con Infos.
- **06**: pone en NULL `fechaModificacion` y `usuarioModificacion` de `aTransaccion` (columnas que solo tiene AppPalma) y borra de `Auditoria` los registros de `aTransaccion` con `Accion` UPDATE o DELETE. Se conservan los INSERT y la auditoría de las demás tablas. La aplicación sigue auditando todo como hasta ahora.

## 3. Resultado esperado del 01 final

La cabecera del 01 dice que después de migrar la primera consulta "debe devolver CERO filas". Esa indicación queda reemplazada por esta tabla de excepciones. En la primera consulta solo deben quedar estas diferencias:

| Tabla | Diferencia esperada | Motivo |
|-------|---------------------|--------|
| `aLotePesosPeriodo` | soloApp = 250 | Proyección de los lotes CGL10-22 a CGL14-22 (finca 003, nov-2026 a dic-2030), cargada a propósito. |
| `aLotes` | distintos = 2 | Cambios hechos en Palma que se conservan. |

Todo lo demás debe dar 0. Si después aparece algo en `sLogRegistros`, debe ser solo en Infos (soloInfos): esa tabla sigue creciendo en Infos con el uso normal.

Las otras consultas del 01:

- `sUsuarios -> sUsuariosNO`: distintosInfos = 0 y distintosApp = 0.
- Última consulta (`aTransaccion` solo en AppPalma o con usuario fuera de `sUsuarios`): 0 filas.

El 06 también muestra su propia verificación: `Auditoria` de `aTransaccion` solo con `INSERT` y 0 transacciones con datos de modificación.

Nota: los registros INSERT de `Auditoria` de `aTransaccion` que se conservan pueden referirse a números que, tras la sincronización, ya no existen o corresponden a otro documento de Infos. Es una decisión aceptada.

## 4. Nota sobre el reparto de kg de los TLC

El script `Documentacion/BaseDeDatos/corregir_reparto_kg_trabajadores_tlc.sql` **ya NO se debe ejecutar**: los TLC quedan con los datos de Infos. La corrección en el código del reparto de kg sigue vigente y se aplicará cuando Palma pase a producción.
