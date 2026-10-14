/* ============================================================================
   Corrección de IdLoseta en submódulos
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Lista los submódulos (IdModulo NOT NULL) cuyo IdLoseta no coincide con
        el IdLoseta de su módulo padre.
     2) Actualiza IdLoseta de esos submódulos con el IdLoseta del padre.
     3) Lista de nuevo para verificar (debe quedar vacío).

   No modifica ModulosRoles, Orden ni ningún otro campo.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i corregir_idloseta_submodulos.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

SELECT s.Id, s.Nombre, s.IdModulo AS IdModuloPadre, s.IdLoseta AS IdLosetaActual, p.IdLoseta AS IdLosetaCorrecta
FROM Modulos s
JOIN Modulos p ON p.Id = s.IdModulo
WHERE p.IdLoseta IS NOT NULL
  AND (s.IdLoseta IS NULL OR s.IdLoseta <> p.IdLoseta)
ORDER BY s.IdModulo, s.Id;

BEGIN TRY

    BEGIN TRAN;

    UPDATE s
       SET s.IdLoseta = p.IdLoseta
      FROM Modulos s
      JOIN Modulos p ON p.Id = s.IdModulo
     WHERE p.IdLoseta IS NOT NULL
       AND (s.IdLoseta IS NULL OR s.IdLoseta <> p.IdLoseta);

    PRINT CONCAT(N'Submodulos corregidos: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT s.Id, s.Nombre, s.IdModulo AS IdModuloPadre, s.IdLoseta AS IdLosetaActual, p.IdLoseta AS IdLosetaCorrecta
FROM Modulos s
JOIN Modulos p ON p.Id = s.IdModulo
WHERE p.IdLoseta IS NOT NULL
  AND (s.IdLoseta IS NULL OR s.IdLoseta <> p.IdLoseta)
ORDER BY s.IdModulo, s.Id;
