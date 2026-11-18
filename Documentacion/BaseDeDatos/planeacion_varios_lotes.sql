/* ============================================================================
   Planeación: cierre de una actividad ejecutada en varios lotes
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Agrega, si no existe, dbo.pPlaneacionLinea.division INT NULL:
        en las líneas hermanas creadas al cerrar una actividad en varios lotes
        guarda el id de la línea madre (la del lote planeado). No reutiliza
        "origen", que sigue siendo exclusivo del traslado de pendientes.
          - FK_pPlaneacionLinea_division -> dbo.pPlaneacionLinea (id), sin cascada
          - IX_pPlaneacionLinea_division
     2) Agrega, si no existe, dbo.pPlaneacionTrabajador.cantidadEjecutada
        DECIMAL(18, 3) NULL: cantidad ejecutada por el trabajador en esa línea
        (si es NULL, el registro reparte la ejecutada de la línea como hasta hoy).
          - CK_pPlaneacionTrabajador_cantidadEjecutada (NULL o >= 0)

   Requiere haber ejecutado antes crear_planeacion_labores.sql y
   planeacion_ajustes_noviembre.sql.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i planeacion_varios_lotes.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.pPlaneacionLinea', 'U') IS NULL OR OBJECT_ID('dbo.pPlaneacionTrabajador', 'U') IS NULL
        THROW 50001, N'Faltan las tablas de planeación: ejecute antes crear_planeacion_labores.sql.', 1;

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'division') IS NULL
    BEGIN
        ALTER TABLE dbo.pPlaneacionLinea ADD division INT NULL;

        PRINT N'Columna dbo.pPlaneacionLinea.division creada.';
    END;

    IF OBJECT_ID('dbo.FK_pPlaneacionLinea_division', 'F') IS NULL
    BEGIN
        EXEC sp_executesql N'ALTER TABLE dbo.pPlaneacionLinea WITH CHECK ADD CONSTRAINT FK_pPlaneacionLinea_division
                               FOREIGN KEY (division) REFERENCES dbo.pPlaneacionLinea (id)';

        PRINT N'FK_pPlaneacionLinea_division creada.';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pPlaneacionLinea_division' AND object_id = OBJECT_ID('dbo.pPlaneacionLinea'))
    BEGIN
        EXEC sp_executesql N'CREATE INDEX IX_pPlaneacionLinea_division ON dbo.pPlaneacionLinea (division)';

        PRINT N'Índice IX_pPlaneacionLinea_division creado.';
    END;

    IF COL_LENGTH('dbo.pPlaneacionTrabajador', 'cantidadEjecutada') IS NULL
    BEGIN
        ALTER TABLE dbo.pPlaneacionTrabajador ADD cantidadEjecutada DECIMAL(18, 3) NULL;

        PRINT N'Columna dbo.pPlaneacionTrabajador.cantidadEjecutada creada.';
    END;

    IF OBJECT_ID('dbo.CK_pPlaneacionTrabajador_cantidadEjecutada', 'C') IS NULL
    BEGIN
        EXEC sp_executesql N'ALTER TABLE dbo.pPlaneacionTrabajador WITH CHECK ADD CONSTRAINT CK_pPlaneacionTrabajador_cantidadEjecutada
                               CHECK (cantidadEjecutada IS NULL OR cantidadEjecutada >= 0)';

        PRINT N'CK_pPlaneacionTrabajador_cantidadEjecutada creada.';
    END;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT OBJECT_NAME(c.object_id) AS tabla, c.name AS columna, TYPE_NAME(c.system_type_id) AS tipo,
       c.precision, c.scale, c.is_nullable
  FROM sys.columns c
 WHERE (c.object_id = OBJECT_ID('dbo.pPlaneacionLinea') AND c.name = 'division')
    OR (c.object_id = OBJECT_ID('dbo.pPlaneacionTrabajador') AND c.name = 'cantidadEjecutada')
 ORDER BY tabla, columna;

SELECT name AS objeto, type_desc AS tipo
  FROM sys.objects
 WHERE name IN ('FK_pPlaneacionLinea_division', 'CK_pPlaneacionTrabajador_cantidadEjecutada')
UNION ALL
SELECT name, type_desc
  FROM sys.indexes
 WHERE name = 'IX_pPlaneacionLinea_division' AND object_id = OBJECT_ID('dbo.pPlaneacionLinea');

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   Antes de quitar "division" hay que eliminar (o desvincular) las líneas
   hermanas que dependan de una línea madre.
   ----------------------------------------------------------------------------
SET XACT_ABORT ON;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.CK_pPlaneacionTrabajador_cantidadEjecutada', 'C') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionTrabajador DROP CONSTRAINT CK_pPlaneacionTrabajador_cantidadEjecutada;

    IF COL_LENGTH('dbo.pPlaneacionTrabajador', 'cantidadEjecutada') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionTrabajador DROP COLUMN cantidadEjecutada;

    IF OBJECT_ID('dbo.FK_pPlaneacionLinea_division', 'F') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionLinea DROP CONSTRAINT FK_pPlaneacionLinea_division;

    IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pPlaneacionLinea_division' AND object_id = OBJECT_ID('dbo.pPlaneacionLinea'))
        DROP INDEX IX_pPlaneacionLinea_division ON dbo.pPlaneacionLinea;

    IF COL_LENGTH('dbo.pPlaneacionLinea', 'division') IS NOT NULL
        ALTER TABLE dbo.pPlaneacionLinea DROP COLUMN division;

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;
---------------------------------------------------------------------------- */
