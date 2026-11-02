/* ============================================================================
   Configuración del sitio con la identidad ELAEIX
   (Administración › Configuración del sitio)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Deja vigentes (FechaFinalizacion IS NULL) en ConfiguracionSitio:
       nombre_app       = 'ELAEIX'
       color_primary    = '#287d55'
       color_secundario = '#192d46'
       logo             = 'public/assets/img/elaeix_logo.png'
       logo_claro       = 'public/assets/img/elaeix_logo_claro.png'
       favicon          = 'public/assets/img/elaeix_favicon_64.png'

     Si la clave ya tiene fila vigente se actualiza Valor, IdUsuario y
     FechaModificacion (solo cuando el valor cambia). Si no existe, se inserta.

   IDEMPOTENTE: se puede ejecutar varias veces.

   Ejecutar contra la base de datos AppPalma (SQL Server), desde SSMS o con
   "sqlcmd -f 65001 -i actualizar_configuracion_sitio_elaeix.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario INT = 1;       -- <-- Ajustar al Id real del usuario administrador
DECLARE @Fecha     DATETIME = GETDATE();

DECLARE @Valores TABLE (Nombre VARCHAR(100) PRIMARY KEY, Valor NVARCHAR(500));

INSERT INTO @Valores (Nombre, Valor)
VALUES ('nombre_app',       N'ELAEIX'),
       ('color_primary',    N'#287d55'),
       ('color_secundario', N'#192d46'),
       ('logo',             N'public/assets/img/elaeix_logo.png'),
       ('logo_claro',       N'public/assets/img/elaeix_logo_claro.png'),
       ('favicon',          N'public/assets/img/elaeix_favicon_64.png');

BEGIN TRY

    BEGIN TRAN;

    UPDATE cs
       SET cs.Valor             = v.Valor,
           cs.IdUsuario         = @IdUsuario,
           cs.FechaModificacion = @Fecha
      FROM ConfiguracionSitio cs
      JOIN @Valores v ON v.Nombre = cs.Nombre
     WHERE cs.FechaFinalizacion IS NULL
       AND ISNULL(cs.Valor, N'') <> v.Valor;

    PRINT CONCAT(N'Filas actualizadas: ', @@ROWCOUNT);

    INSERT INTO ConfiguracionSitio (Nombre, Valor, IdUsuario, FechaInicio)
    SELECT v.Nombre, v.Valor, @IdUsuario, @Fecha
      FROM @Valores v
     WHERE NOT EXISTS (SELECT 1 FROM ConfiguracionSitio cs
                        WHERE cs.Nombre = v.Nombre AND cs.FechaFinalizacion IS NULL);

    PRINT CONCAT(N'Filas insertadas: ', @@ROWCOUNT);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

/* Verificación: configuración vigente */
SELECT Id, Nombre, Valor, IdUsuario, FechaInicio, FechaModificacion
FROM ConfiguracionSitio
WHERE FechaFinalizacion IS NULL
ORDER BY Nombre;
