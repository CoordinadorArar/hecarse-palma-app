/* ============================================================================
   OBSOLETO desde 2026-11-04: Infos ya no se usa
   Resincronización de las TLC desde TLC000000004371 (Infos -> AppPalma)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Deja en AppPalma las transacciones TLC de la empresa @Empresa con numero
     >= @Desde EXACTAMENTE iguales a Infos (mismos códigos y mismos datos) en:
       aTransaccion, aTransaccionBascula, aTransaccionNovedad,
       aTransaccionTercero
     y deja gTipoTransaccion.actual (TLC) igual al de Infos.

   POR QUÉ
   -------
     Hasta la TLC 4370 ambas bases coinciden. Desde la 4371 divergen:
       - AppPalma tiene 4371-4375; la 4372-4375 se digitaron en AppPalma con
         datos distintos a las TLC de Infos con el mismo número.
       - Infos tiene 4371, 4372, 4374-4380 (no existe 4373; 4376 y 4377
         anuladas) y la 4371 se modificó en Infos después de copiarse.
     Infos manda: lo que hoy existe en AppPalma en ese rango se ELIMINA (queda
     en las tablas de respaldo resp_TLC4371_*) y se reemplaza por lo de Infos.
     Las fechaModificacion / usuarioModificacion de AppPalma quedan en NULL.

   ORDEN
   -----
     1) Validaciones previas (no cambian nada; si fallan, THROW):
          Infos accesible; sin pPlaneacionLinea, aTransaccionHabilitacion ni
          aTransaccionTerceroBloqueo del rango; en Infos todas las hijas del
          rango tienen encabezado; no existen respaldos resp_TLC4371_*.
     2) Respaldo (SELECT INTO) del rango y de la fila TLC de gTipoTransaccion:
          dbo.resp_TLC4371_aTransaccion, dbo.resp_TLC4371_aTransaccionBascula,
          dbo.resp_TLC4371_aTransaccionNovedad,
          dbo.resp_TLC4371_aTransaccionTercero,
          dbo.resp_TLC4371_gTipoTransaccion
     3) Borrado del rango en AppPalma (hijas primero).
     4) Inserción del rango desde Infos (encabezado primero).
     5) gTipoTransaccion.actual = Infos.
     6) Verificación: conteos del rango en ambas bases y diferencias (EXCEPT en
        ambos sentidos) sobre TODO el tipo TLC de la empresa. Si hay alguna
        diferencia en el rango o el consecutivo no coincide: THROW (ROLLBACK).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta todo dentro de una transacción, muestra
     los resultados y hace ROLLBACK (tampoco quedan las tablas de respaldo).
     @Aplicar = 1: lo mismo pero hace COMMIT.
     Cualquier error revierte TODO.

   REQUISITOS
   ----------
     - Nadie registrando TLC en AppPalma ni en Infos durante la ejecución.
     - Ejecutar con un usuario que lea Infos y escriba/cree tablas en AppPalma.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
     Archivo UTF-8 con columnas [año] y [añofer]. Ejecutarlo SOLO desde SSMS o:
     sqlcmd -S <servidor> -d AppPalma -f 65001 -i resincronizar_tlc_desde_4371.sql

   REVERSIÓN
   ---------
     Ver el bloque comentado al final: restaura el rango y el consecutivo desde
     las tablas resp_TLC4371_*. Borrar esas tablas cuando ya no se necesiten.
   ============================================================================ */

RAISERROR (N'OBSOLETO: Infos dejó de usarse el 2026-11-04. Este script sincroniza Infos -> AppPalma y borraría datos actuales de AppPalma. No ejecutar.', 16, 1);
SET NOEXEC ON;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;
DECLARE @Empresa INT = 1;
DECLARE @Tipo    VARCHAR(50) = 'TLC';
DECLARE @Desde   VARCHAR(50) = 'TLC000000004371';
DECLARE @Patron  VARCHAR(51) = @Tipo + '%';

DECLARE @ActualAppPalma INT;
DECLARE @ActualInfos    INT;

DECLARE @log    TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, accion NVARCHAR(20), filas INT);
DECLARE @conteo TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, filasAppPalma INT, filasInfos INT);
DECLARE @dif    TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, sentido NVARCHAR(20), diferenciasTipo INT, diferenciasRango INT);

BEGIN TRY

    /* ---------- 1. VALIDACIONES PREVIAS ---------- */

    IF DB_ID(N'Infos') IS NULL OR OBJECT_ID(N'Infos.dbo.aTransaccion', N'U') IS NULL
        THROW 50001, N'La base Infos no está accesible desde esta conexión.', 1;

    IF NOT EXISTS (SELECT 1 FROM Infos.dbo.[gTipoTransaccion] WHERE [empresa] = @Empresa AND [codigo] = @Tipo)
       OR NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[gTipoTransaccion] WHERE [empresa] = @Empresa AND [codigo] = @Tipo)
        THROW 50002, N'No existe el tipo de transacción en gTipoTransaccion de alguna de las dos bases.', 1;

    IF NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
        THROW 50003, N'Infos no tiene transacciones en el rango; revisar @Empresa, @Tipo y @Desde.', 1;

    IF EXISTS (SELECT 1 FROM AppPalma.dbo.[pPlaneacionLinea] WHERE [tipoTransaccion] = @Tipo AND [numeroTransaccion] >= @Desde AND [numeroTransaccion] LIKE @Patron)
        THROW 50004, N'Hay actividades de planeación (pPlaneacionLinea) vinculadas a transacciones del rango; resolver antes.', 1;

    IF EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionHabilitacion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
        THROW 50005, N'Hay habilitaciones (aTransaccionHabilitacion) de transacciones del rango; resolver antes.', 1;

    IF EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionTerceroBloqueo] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
        THROW 50006, N'Hay bloqueos (aTransaccionTerceroBloqueo) de transacciones del rango; resolver antes.', 1;

    IF EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionBascula] h WHERE h.[empresa] = @Empresa AND h.[tipo] = @Tipo AND h.[numero] >= @Desde AND h.[numero] LIKE @Patron
               AND NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccion] t WHERE t.[empresa] = h.[empresa] AND t.[tipo] = h.[tipo] AND t.[numero] = h.[numero]))
       OR EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionNovedad] h WHERE h.[empresa] = @Empresa AND h.[tipo] = @Tipo AND h.[numero] >= @Desde AND h.[numero] LIKE @Patron
               AND NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccion] t WHERE t.[empresa] = h.[empresa] AND t.[tipo] = h.[tipo] AND t.[numero] = h.[numero]))
       OR EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionTercero] h WHERE h.[empresa] = @Empresa AND h.[tipo] = @Tipo AND h.[numero] >= @Desde AND h.[numero] LIKE @Patron
               AND NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccion] t WHERE t.[empresa] = h.[empresa] AND t.[tipo] = h.[tipo] AND t.[numero] = h.[numero]))
        THROW 50007, N'En Infos hay filas hijas del rango sin encabezado en aTransaccion; resolver antes.', 1;

    IF OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccion', N'U') IS NOT NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionBascula', N'U') IS NOT NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionNovedad', N'U') IS NOT NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionTercero', N'U') IS NOT NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_gTipoTransaccion', N'U') IS NOT NULL
        THROW 50008, N'Ya existen tablas de respaldo resp_TLC4371_*; revisarlas y borrarlas antes de volver a ejecutar.', 1;

    BEGIN TRAN;

    /* ---------- 2. RESPALDO ---------- */

    SELECT * INTO AppPalma.dbo.[resp_TLC4371_aTransaccion]
    FROM AppPalma.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'resp_TLC4371_aTransaccion', N'respaldadas', @@ROWCOUNT);

    SELECT * INTO AppPalma.dbo.[resp_TLC4371_aTransaccionBascula]
    FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'resp_TLC4371_aTransaccionBascula', N'respaldadas', @@ROWCOUNT);

    SELECT * INTO AppPalma.dbo.[resp_TLC4371_aTransaccionNovedad]
    FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'resp_TLC4371_aTransaccionNovedad', N'respaldadas', @@ROWCOUNT);

    SELECT * INTO AppPalma.dbo.[resp_TLC4371_aTransaccionTercero]
    FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'resp_TLC4371_aTransaccionTercero', N'respaldadas', @@ROWCOUNT);

    SELECT * INTO AppPalma.dbo.[resp_TLC4371_gTipoTransaccion]
    FROM AppPalma.dbo.[gTipoTransaccion] WHERE [empresa] = @Empresa AND [codigo] = @Tipo;
    INSERT @log VALUES (N'resp_TLC4371_gTipoTransaccion', N'respaldadas', @@ROWCOUNT);

    /* ---------- 3. BORRADO DEL RANGO EN APPPALMA (hijas primero) ---------- */

    DELETE FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionTercero', N'eliminadas', @@ROWCOUNT);

    DELETE FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionNovedad', N'eliminadas', @@ROWCOUNT);

    DELETE FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionBascula', N'eliminadas', @@ROWCOUNT);

    DELETE FROM AppPalma.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccion', N'eliminadas', @@ROWCOUNT);

    /* ---------- 4. INSERCIÓN DEL RANGO DESDE INFOS (encabezado primero) ---------- */

    INSERT INTO AppPalma.dbo.[aTransaccion] ([empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer],[fechaModificacion],[usuarioModificacion])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[fecha], s.[referencia], s.[finca], s.[jornal], s.[racimos], s.[cantidad], s.[precio], s.[valorTotal], s.[fechaFinal], s.[remision], s.[observacion], s.[fechaRegistro], s.[usuarioRegistro], s.[anulado], s.[usuarioAnulado], s.[fechaAnulado], s.[periodo], s.[cerrado], s.[añofer], s.[mesIfer], s.[mesFfer], NULL, NULL
    FROM Infos.dbo.[aTransaccion] s
    WHERE s.[empresa] = @Empresa AND s.[tipo] = @Tipo AND s.[numero] >= @Desde AND s.[numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccion', N'insertadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionBascula] ([empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora])
    SELECT s.[empresa], s.[tipo], s.[numero], s.[planta], s.[tiquete], s.[fecha], s.[pesoBruto], s.[pesoTara], s.[pesoNeto], s.[sacos], s.[racimos], s.[codigoConductor], s.[nombreConductor], s.[vehiculo], s.[remolque], s.[interno], s.[empresaExtractora], s.[terceroExtractrora]
    FROM Infos.dbo.[aTransaccionBascula] s
    WHERE s.[empresa] = @Empresa AND s.[tipo] = @Tipo AND s.[numero] >= @Desde AND s.[numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionBascula', N'insertadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionNovedad] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[uMedida], s.[finca], s.[seccion], s.[lote], s.[fecha], s.[cantidad], s.[jornales], s.[racimos], s.[pesoRacimo], s.[saldo], s.[ejecutado], s.[signo], s.[precioLabor], s.[concepto], s.[periodo], s.[registroNovedad], s.[sacos]
    FROM Infos.dbo.[aTransaccionNovedad] s
    WHERE s.[empresa] = @Empresa AND s.[tipo] = @Tipo AND s.[numero] >= @Desde AND s.[numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionNovedad', N'insertadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionTercero] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[registroNovedad], s.[finca], s.[seccion], s.[lote], s.[tercero], s.[cantidad], s.[jornales], s.[saldo], s.[ejecutado], s.[zCuadrilla], s.[precioLabor], s.[valorTotal], s.[ccosto], s.[contrato], s.[periodo], s.[contratista], s.[proveedor], s.[racimos], s.[fechaNovedad]
    FROM Infos.dbo.[aTransaccionTercero] s
    WHERE s.[empresa] = @Empresa AND s.[tipo] = @Tipo AND s.[numero] >= @Desde AND s.[numero] LIKE @Patron;
    INSERT @log VALUES (N'aTransaccionTercero', N'insertadas', @@ROWCOUNT);

    /* ---------- 5. CONSECUTIVO ---------- */

    UPDATE d SET d.[actual] = s.[actual]
    FROM AppPalma.dbo.[gTipoTransaccion] d
    JOIN Infos.dbo.[gTipoTransaccion] s ON s.[empresa] = d.[empresa] AND s.[codigo] = d.[codigo]
    WHERE d.[empresa] = @Empresa AND d.[codigo] = @Tipo;
    INSERT @log VALUES (N'gTipoTransaccion', N'actualizadas', @@ROWCOUNT);

    SELECT tabla, accion, filas FROM @log ORDER BY orden;

    /* ---------- 6. VERIFICACIÓN ---------- */

    INSERT @conteo (tabla, filasAppPalma, filasInfos)
    SELECT N'aTransaccion',
           (SELECT COUNT(*) FROM AppPalma.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron),
           (SELECT COUNT(*) FROM Infos.dbo.[aTransaccion]    WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
    UNION ALL
    SELECT N'aTransaccionBascula',
           (SELECT COUNT(*) FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron),
           (SELECT COUNT(*) FROM Infos.dbo.[aTransaccionBascula]    WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
    UNION ALL
    SELECT N'aTransaccionNovedad',
           (SELECT COUNT(*) FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron),
           (SELECT COUNT(*) FROM Infos.dbo.[aTransaccionNovedad]    WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron)
    UNION ALL
    SELECT N'aTransaccionTercero',
           (SELECT COUNT(*) FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron),
           (SELECT COUNT(*) FROM Infos.dbo.[aTransaccionTercero]    WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron);

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccion', N'solo AppPalma', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer] FROM AppPalma.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer] FROM Infos.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccion', N'solo Infos', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer] FROM Infos.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer] FROM AppPalma.dbo.[aTransaccion] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionBascula', N'solo AppPalma', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora] FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora] FROM Infos.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionBascula', N'solo Infos', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora] FROM Infos.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora] FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionNovedad', N'solo AppPalma', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos] FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos] FROM Infos.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionNovedad', N'solo Infos', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos] FROM Infos.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos] FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionTercero', N'solo AppPalma', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad] FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad] FROM Infos.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    INSERT @dif (tabla, sentido, diferenciasTipo, diferenciasRango)
    SELECT N'aTransaccionTercero', N'solo Infos', COUNT(*), ISNULL(SUM(CASE WHEN x.[numero] >= @Desde AND x.[numero] LIKE @Patron THEN 1 ELSE 0 END), 0)
    FROM (SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad] FROM Infos.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo
          EXCEPT
          SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad] FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo) x;

    SELECT @ActualAppPalma = [actual] FROM AppPalma.dbo.[gTipoTransaccion] WHERE [empresa] = @Empresa AND [codigo] = @Tipo;
    SELECT @ActualInfos    = [actual] FROM Infos.dbo.[gTipoTransaccion]    WHERE [empresa] = @Empresa AND [codigo] = @Tipo;

    SELECT tabla, filasAppPalma, filasInfos FROM @conteo ORDER BY orden;
    SELECT tabla, sentido, diferenciasTipo, diferenciasRango FROM @dif ORDER BY orden;
    SELECT @ActualAppPalma AS actualAppPalma, @ActualInfos AS actualInfos;

    IF EXISTS (SELECT 1 FROM @dif WHERE diferenciasTipo > diferenciasRango)
        PRINT N'ADVERTENCIA: hay diferencias en TLC anteriores a ' + @Desde + N' (fuera del alcance de este script). Revisar el detalle.';

    IF EXISTS (SELECT 1 FROM @dif WHERE diferenciasRango <> 0)
       OR EXISTS (SELECT 1 FROM @conteo WHERE filasAppPalma <> filasInfos)
       OR ISNULL(@ActualAppPalma, -1) <> ISNULL(@ActualInfos, -2)
        THROW 50010, N'La verificación encontró diferencias en el rango o en el consecutivo; se revierte todo.', 1;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados. Respaldos en dbo.resp_TLC4371_*.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: no se guardó ningún cambio. Revise los resultados y ejecute con @Aplicar = 1.';
    END

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK;

    PRINT N'ERROR: se revirtió todo, no quedó ningún cambio.';

    THROW;

END CATCH;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL (solo si se ejecutó con @Aplicar = 1 y hay que deshacerlo).
   Restaura el rango y el consecutivo TLC desde las tablas resp_TLC4371_*.
   Descomentar y ejecutar con sqlcmd -f 65001 o desde SSMS.
   ----------------------------------------------------------------------------
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Empresa INT = 1;
DECLARE @Tipo    VARCHAR(50) = 'TLC';
DECLARE @Desde   VARCHAR(50) = 'TLC000000004371';
DECLARE @Patron  VARCHAR(51) = @Tipo + '%';

DECLARE @Nuevas         INT;
DECLARE @ActualAppPalma INT;
DECLARE @ActualInfos    INT;
DECLARE @Msg            NVARCHAR(2048);

BEGIN TRY

    IF OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccion', N'U') IS NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionBascula', N'U') IS NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionNovedad', N'U') IS NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_aTransaccionTercero', N'U') IS NULL
       OR OBJECT_ID(N'AppPalma.dbo.resp_TLC4371_gTipoTransaccion', N'U') IS NULL
        THROW 50021, N'Falta alguna de las tablas de respaldo resp_TLC4371_*; no se puede revertir.', 1;

    SELECT @Nuevas = COUNT(*)
    FROM (SELECT [numero] FROM AppPalma.dbo.[aTransaccion]        WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron
          UNION
          SELECT [numero] FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron
          UNION
          SELECT [numero] FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron
          UNION
          SELECT [numero] FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron
          EXCEPT
          SELECT [numero] FROM Infos.dbo.[aTransaccion]           WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron) x;

    IF @Nuevas > 0
    BEGIN
        SET @Msg = N'Hay ' + CAST(@Nuevas AS NVARCHAR(20)) + N' TLC en AppPalma desde ' + @Desde + N' que no existen en Infos (digitadas después de la sincronización); la reversión las borraría sin respaldo. Resolver antes.';
        THROW 50022, @Msg, 1;
    END

    SELECT @ActualAppPalma = [actual] FROM AppPalma.dbo.[gTipoTransaccion] WHERE [empresa] = @Empresa AND [codigo] = @Tipo;
    SELECT @ActualInfos    = [actual] FROM Infos.dbo.[gTipoTransaccion]    WHERE [empresa] = @Empresa AND [codigo] = @Tipo;

    IF ISNULL(@ActualAppPalma, -1) <> ISNULL(@ActualInfos, -2)
    BEGIN
        SET @Msg = N'El consecutivo TLC de AppPalma (' + ISNULL(CAST(@ActualAppPalma AS NVARCHAR(20)), N'NULL') + N') es distinto al de Infos (' + ISNULL(CAST(@ActualInfos AS NVARCHAR(20)), N'NULL') + N'); se generaron consecutivos después de la sincronización. No se puede revertir.';
        THROW 50023, @Msg, 1;
    END

    BEGIN TRAN;

    DELETE FROM AppPalma.dbo.[aTransaccionTercero] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    DELETE FROM AppPalma.dbo.[aTransaccionNovedad] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    DELETE FROM AppPalma.dbo.[aTransaccionBascula] WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;
    DELETE FROM AppPalma.dbo.[aTransaccion]        WHERE [empresa] = @Empresa AND [tipo] = @Tipo AND [numero] >= @Desde AND [numero] LIKE @Patron;

    INSERT INTO AppPalma.dbo.[aTransaccion] ([empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer],[fechaModificacion],[usuarioModificacion])
    SELECT [empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer],[fechaModificacion],[usuarioModificacion]
    FROM AppPalma.dbo.[resp_TLC4371_aTransaccion];

    INSERT INTO AppPalma.dbo.[aTransaccionBascula] ([empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora])
    SELECT [empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora]
    FROM AppPalma.dbo.[resp_TLC4371_aTransaccionBascula];

    INSERT INTO AppPalma.dbo.[aTransaccionNovedad] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos])
    SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos]
    FROM AppPalma.dbo.[resp_TLC4371_aTransaccionNovedad];

    INSERT INTO AppPalma.dbo.[aTransaccionTercero] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad])
    SELECT [empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad]
    FROM AppPalma.dbo.[resp_TLC4371_aTransaccionTercero];

    UPDATE d SET d.[actual] = r.[actual]
    FROM AppPalma.dbo.[gTipoTransaccion] d
    JOIN AppPalma.dbo.[resp_TLC4371_gTipoTransaccion] r ON r.[empresa] = d.[empresa] AND r.[codigo] = d.[codigo];

    COMMIT;
    PRINT N'REVERTIDO: rango y consecutivo restaurados desde resp_TLC4371_*.';

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */

/* ----------------------------------------------------------------------------
   LIMPIEZA DE RESPALDOS (cuando ya no se necesiten). Descomentar y ejecutar.
   ----------------------------------------------------------------------------
DROP TABLE AppPalma.dbo.[resp_TLC4371_aTransaccionTercero];
DROP TABLE AppPalma.dbo.[resp_TLC4371_aTransaccionNovedad];
DROP TABLE AppPalma.dbo.[resp_TLC4371_aTransaccionBascula];
DROP TABLE AppPalma.dbo.[resp_TLC4371_aTransaccion];
DROP TABLE AppPalma.dbo.[resp_TLC4371_gTipoTransaccion];
---------------------------------------------------------------------------- */
