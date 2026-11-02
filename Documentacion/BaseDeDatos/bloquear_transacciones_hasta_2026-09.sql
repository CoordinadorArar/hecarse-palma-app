/* ============================================================================
   Bloqueo por fecha de corte de transacciones liquidadas en nómina
   ----------------------------------------------------------------------------
   *** NO SE HA EJECUTADO. Lo ejecuta William manualmente. ***

   QUÉ HACE
   --------
     Marca como liquidadas en nómina (aTransaccionTercero.ejecutado = 1) todas
     las líneas de las transacciones de Labores (TLA), Producción (TLC) y
     Registro de fertilización (RLF) con fecha <= @FechaCorte (por defecto
     2026-09-30, septiembre incluido) y no anuladas (ISNULL(anulado, 0) = 0).
     Con esa marca la aplicación las considera liquidadas (ProduccionModel::
     liquidadaSql) y bloquea editarlas o eliminarlas, salvo habilitación de un
     solo uso (aTransaccionHabilitacion).
       1) Crea, si no existe, la tabla de respaldo dbo.aTransaccionTerceroBloqueo
          con la llave de cada línea marcada, la fecha de corte, la fecha de la
          marca y el Lote (identificador de esta ejecución, p. ej.
          BLQ-20261030-153012).
       2) Inserta en el respaldo las líneas que va a cambiar y luego hace el
          UPDATE, en la misma transacción.
       3) Registra una fila en Auditoria (operación 'bloqueo-por-corte') con
          los conteos por tipo.
     Mismo criterio que la marca por documento de nómina Siesa
     (LiquidacionSiesaModel::cambiarMarca): solo aTransaccionTercero.ejecutado;
     aTransaccionNovedad no se toca. Las transacciones de octubre de 2026 en
     adelante NO se tocan. Las transacciones sin líneas en aTransaccionTercero
     no se pueden marcar y se listan al inicio (informativo). También se marcan
     las eliminadas lógicamente (aTransaccionEliminada), que la aplicación ya
     no muestra.

   POR QUÉ
   -------
     Infos no deja ninguna marca de liquidación en estas transacciones
     (0 líneas con ejecutado = 1): la nómina real se liquida en Siesa. Por eso
     el bloqueo de los meses ya liquidados se hace por fecha de corte.

   IDEMPOTENTE
   -----------
     Solo toma líneas con ISNULL(ejecutado, 0) = 0; volver a ejecutarlo no
     cambia nada más (0 líneas, no inserta respaldo ni auditoría).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta todo dentro de una transacción
     (incluida la creación de la tabla de respaldo), muestra los conteos por
     tipo y hace ROLLBACK. No queda ningún cambio.
     @Aplicar = 1: lo mismo pero hace COMMIT. Anotar el Lote que se imprime.
     Cualquier error revierte TODO.
     @Empresa = NULL: todas las empresas; un número limita a esa empresa.

   CÓMO REVERTIR
   -------------
     Bloque comentado al final: vuelve ejecutado a 0 solo para las líneas del
     Lote indicado (tabla dbo.aTransaccionTerceroBloqueo), marca FechaReversion
     y audita ('revertir-bloqueo-por-corte'). Ojo: también desmarcaría líneas
     del lote que luego se hubieran marcado por liquidación Siesa.

   ADVERTENCIA
   -----------
     NO volver a ejecutar migracion_infos/02_sincronizar_infos_a_apppalma.sql
     después de este script: Infos manda y devolvería ejecutado a 0 en todas
     estas líneas (y eliminaría o reescribiría las transacciones).

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
     Ejecutar conectado a la base AppPalma (SQL Server). Este archivo es UTF-8:
     ejecutarlo SOLO desde SSMS o con
     "sqlcmd -f 65001 -i bloquear_transacciones_hasta_2026-09.sql".
     Primero con @Aplicar = 0, revisar conteos, y luego con @Aplicar = 1.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @FechaCorte DATE = '2026-09-30';
DECLARE @Aplicar    BIT  = 0;
DECLARE @Empresa    INT  = NULL;
DECLARE @IdUsuario  INT  = NULL;

DECLARE @FechaMarca DATETIME    = GETDATE();
DECLARE @Lote       VARCHAR(30) = CONCAT('BLQ-', REPLACE(REPLACE(REPLACE(CONVERT(varchar(19), @FechaMarca, 120), '-', ''), ':', ''), ' ', '-'));
DECLARE @lineas     INT = 0;
DECLARE @actualizadas INT = 0;
DECLARE @json       NVARCHAR(MAX);
DECLARE @resumen    TABLE (tipo VARCHAR(50) PRIMARY KEY, transacciones INT, lineas INT);

SELECT N'Sin líneas en aTransaccionTercero (no se pueden marcar)' AS informativo, a.empresa, a.tipo, a.numero, a.fecha
  FROM aTransaccion a
 WHERE a.tipo IN ('TLA', 'TLC', 'RLF') AND a.fecha <= @FechaCorte AND ISNULL(a.anulado, 0) = 0
   AND (@Empresa IS NULL OR a.empresa = @Empresa)
   AND NOT EXISTS (SELECT 1 FROM aTransaccionTercero tt WHERE tt.empresa = a.empresa AND tt.tipo = a.tipo AND tt.numero = a.numero)
 ORDER BY a.tipo, a.fecha, a.numero;

BEGIN TRY

    BEGIN TRAN;

    IF OBJECT_ID('dbo.aTransaccionTerceroBloqueo', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.aTransaccionTerceroBloqueo (
            Id              INT          IDENTITY(1, 1) NOT NULL,
            Lote            VARCHAR(30)  NOT NULL,
            FechaCorte      DATE         NOT NULL,
            FechaMarca      DATETIME     NOT NULL,
            empresa         INT          NOT NULL,
            tipo            VARCHAR(50)  NOT NULL,
            numero          VARCHAR(50)  NOT NULL,
            novedad         VARCHAR(50)  NOT NULL,
            registro        INT          NOT NULL,
            registroNovedad INT          NOT NULL,
            tercero         VARCHAR(50)  NOT NULL,
            FechaReversion  DATETIME     NULL,
            CONSTRAINT PK_aTransaccionTerceroBloqueo PRIMARY KEY (Id)
        );

        CREATE INDEX IX_aTransaccionTerceroBloqueo_lote ON dbo.aTransaccionTerceroBloqueo (Lote);

        PRINT N'Tabla aTransaccionTerceroBloqueo creada.';
    END

    INSERT INTO dbo.aTransaccionTerceroBloqueo (Lote, FechaCorte, FechaMarca, empresa, tipo, numero, novedad, registro, registroNovedad, tercero)
    SELECT @Lote, @FechaCorte, @FechaMarca, tt.empresa, tt.tipo, tt.numero, tt.novedad, tt.registro, tt.registroNovedad, tt.tercero
      FROM aTransaccionTercero tt
      JOIN aTransaccion a ON a.empresa = tt.empresa AND a.tipo = tt.tipo AND a.numero = tt.numero
     WHERE a.tipo IN ('TLA', 'TLC', 'RLF') AND a.fecha <= @FechaCorte AND ISNULL(a.anulado, 0) = 0
       AND (@Empresa IS NULL OR a.empresa = @Empresa)
       AND ISNULL(tt.ejecutado, 0) = 0;

    SET @lineas = @@ROWCOUNT;

    UPDATE tt SET ejecutado = 1
      FROM aTransaccionTercero tt
      JOIN dbo.aTransaccionTerceroBloqueo b ON b.empresa = tt.empresa AND b.tipo = tt.tipo AND b.numero = tt.numero
                                           AND b.novedad = tt.novedad AND b.registro = tt.registro AND b.registroNovedad = tt.registroNovedad
     WHERE b.Lote = @Lote AND ISNULL(tt.ejecutado, 0) = 0;

    SET @actualizadas = @@ROWCOUNT;

    IF @actualizadas <> @lineas
        THROW 50002, N'Las líneas actualizadas no coinciden con las respaldadas; se revierte todo.', 1;

    INSERT INTO @resumen (tipo, transacciones, lineas)
    SELECT t.tipo, ISNULL(x.transacciones, 0), ISNULL(x.lineas, 0)
      FROM (VALUES ('TLA'), ('TLC'), ('RLF')) t (tipo)
      LEFT JOIN (SELECT b.tipo, COUNT(DISTINCT CONCAT(b.empresa, '|', b.numero)) AS transacciones, COUNT(*) AS lineas
                   FROM dbo.aTransaccionTerceroBloqueo b
                  WHERE b.Lote = @Lote
                  GROUP BY b.tipo) x ON x.tipo = t.tipo;

    SELECT @json = CONCAT(N'{"operacion":"bloqueo-por-corte","fechaCorte":"', CONVERT(char(10), @FechaCorte, 23),
                          N'","lote":"', @Lote, N'","empresa":', ISNULL(CAST(@Empresa AS nvarchar(10)), N'null'),
                          N',"transacciones":{"TLA":', SUM(CASE WHEN tipo = 'TLA' THEN transacciones ELSE 0 END),
                          N',"TLC":', SUM(CASE WHEN tipo = 'TLC' THEN transacciones ELSE 0 END),
                          N',"RLF":', SUM(CASE WHEN tipo = 'RLF' THEN transacciones ELSE 0 END),
                          N'},"lineas":{"TLA":', SUM(CASE WHEN tipo = 'TLA' THEN lineas ELSE 0 END),
                          N',"TLC":', SUM(CASE WHEN tipo = 'TLC' THEN lineas ELSE 0 END),
                          N',"RLF":', SUM(CASE WHEN tipo = 'RLF' THEN lineas ELSE 0 END),
                          N'},"lineasTotal":', @lineas, N'}')
      FROM @resumen;

    IF @lineas > 0
        INSERT INTO Auditoria (TablaAfectada, Accion, CambiosRealizados, IdUsuario, FechaRegistro)
        VALUES ('aTransaccionTercero', 'UPDATE', @json, @IdUsuario, @FechaMarca);

    SELECT @Lote AS lote, tipo, transacciones AS transaccionesMarcadas, lineas AS lineasMarcadas
      FROM @resumen
     ORDER BY tipo;

    PRINT CONCAT(N'Lote: ', @Lote, N' - Líneas marcadas: ', @lineas);

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados. Guarde el Lote para una eventual reversión.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: no se guardó ningún cambio. Revise los conteos y ejecute con @Aplicar = 1.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    SELECT ERROR_NUMBER() AS numero, ERROR_MESSAGE() AS mensaje, ERROR_LINE() AS linea;
    PRINT N'ERROR: se revirtió todo, no quedó ningún cambio.';
END CATCH;

SELECT a.tipo,
       CASE WHEN a.fecha <= @FechaCorte THEN N'hasta el corte' ELSE N'posterior al corte' END AS periodo,
       COUNT(*) AS transacciones,
       SUM(CASE WHEN ISNULL(m.marcadas, 0) > 0 THEN 1 ELSE 0 END) AS bloqueadas,
       SUM(CASE WHEN ISNULL(m.lineas, 0) > 0 AND ISNULL(m.marcadas, 0) = 0 THEN 1 ELSE 0 END) AS sinBloquear,
       SUM(CASE WHEN ISNULL(m.lineas, 0) = 0 THEN 1 ELSE 0 END) AS sinLineas
  FROM aTransaccion a
  LEFT JOIN (SELECT empresa, tipo, numero, COUNT(*) AS lineas, SUM(CAST(ISNULL(ejecutado, 0) AS int)) AS marcadas
               FROM aTransaccionTercero
              WHERE tipo IN ('TLA', 'TLC', 'RLF')
              GROUP BY empresa, tipo, numero) m ON m.empresa = a.empresa AND m.tipo = a.tipo AND m.numero = a.numero
 WHERE a.tipo IN ('TLA', 'TLC', 'RLF') AND ISNULL(a.anulado, 0) = 0
   AND (@Empresa IS NULL OR a.empresa = @Empresa)
 GROUP BY a.tipo, CASE WHEN a.fecha <= @FechaCorte THEN N'hasta el corte' ELSE N'posterior al corte' END
 ORDER BY a.tipo, periodo;

IF OBJECT_ID('dbo.aTransaccionTerceroBloqueo', 'U') IS NOT NULL
    SELECT @Lote AS lote, COUNT(*) AS lineasDelLotePosterioresAlCorte
      FROM dbo.aTransaccionTerceroBloqueo b
      JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero
     WHERE b.Lote = @Lote AND a.fecha > @FechaCorte;

/* ----------------------------------------------------------------------------
   REVERSIÓN DE UN LOTE. Ajustar @Lote (el que imprimió la ejecución; ver
   SELECT DISTINCT Lote, FechaMarca FROM dbo.aTransaccionTerceroBloqueo),
   descomentar y ejecutar primero con @Aplicar = 0 y luego con @Aplicar = 1.
   ----------------------------------------------------------------------------
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Lote      VARCHAR(30) = 'BLQ-AAAAMMDD-HHMMSS';
DECLARE @Aplicar   BIT = 0;
DECLARE @IdUsuario INT = NULL;
DECLARE @Fecha     DATETIME = GETDATE();
DECLARE @lineas    INT = 0;

BEGIN TRY

    BEGIN TRAN;

    UPDATE tt SET ejecutado = 0
      FROM aTransaccionTercero tt
      JOIN dbo.aTransaccionTerceroBloqueo b ON b.empresa = tt.empresa AND b.tipo = tt.tipo AND b.numero = tt.numero
                                           AND b.novedad = tt.novedad AND b.registro = tt.registro AND b.registroNovedad = tt.registroNovedad
     WHERE b.Lote = @Lote AND b.FechaReversion IS NULL AND tt.ejecutado = 1;

    SET @lineas = @@ROWCOUNT;

    UPDATE dbo.aTransaccionTerceroBloqueo SET FechaReversion = @Fecha
     WHERE Lote = @Lote AND FechaReversion IS NULL;

    IF @lineas > 0
        INSERT INTO Auditoria (TablaAfectada, Accion, CambiosRealizados, IdUsuario, FechaRegistro)
        VALUES ('aTransaccionTercero', 'UPDATE',
                CONCAT(N'{"operacion":"revertir-bloqueo-por-corte","lote":"', @Lote, N'","lineas":', @lineas, N'}'),
                @IdUsuario, @Fecha);

    SELECT @Lote AS lote, @lineas AS lineasDesmarcadas;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: reversión confirmada.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: no se guardó ningún cambio.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    SELECT ERROR_NUMBER() AS numero, ERROR_MESSAGE() AS mensaje, ERROR_LINE() AS linea;
    PRINT N'ERROR: se revirtió todo, no quedó ningún cambio.';
END CATCH;
---------------------------------------------------------------------------- */
