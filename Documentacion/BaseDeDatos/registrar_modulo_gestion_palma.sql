/* ============================================================================
   Registro de la loseta "Gestión Palma" en el árbol de permisos (tabla Modulos)
   ----------------------------------------------------------------------------
   Crea 26 registros:
     1) La Loseta de nivel superior "Gestión Palma" (IdLoseta = NULL, IdModulo = NULL)
     2) 10 módulos dentro de esa loseta (IdLoseta = <Id Gestión Palma>, IdModulo = NULL).
        El primero es "Onboarding", que replica la convención de las cuatro losetas
        ya existentes: mismo nombre, icono 'bi bi-list' y la ruta de onboarding de
        la loseta. Sin él, el sidebar no tiene enlace para volver al onboarding y el
        ordenamiento de App\Libraries\InformacionMenus, que deja "Onboarding" de
        primero en el menú, queda sin efecto.
     3) 15 submódulos colgando de su módulo padre (IdLoseta + IdModulo)

   Los 7 módulos contenedores (Contabilidad, Nómina, Parámetros agronómicos,
   Lotes, Labores, Lista de precios y Características) quedan con Ruta = ''
   porque en el sidebar su enlace es solo un desplegable y no navega a ninguna
   parte. Únicamente "Onboarding", los 2 módulos hoja (Fincas y Secciones / Bloques)
   y los 15 submódulos llevan ruta real.

   Las rutas se guardan LIMPIAS, sin el Id de la loseta al final
   (ej. 'gestion-palma/fincas', NO 'gestion-palma/fincas/30'), porque el sidebar
   normaliza la ruta y le agrega el Id de la loseta automáticamente.

   Ejecutar UNA SOLA VEZ contra la base de datos AppPalma (SQL Server). La tabla
   Modulos no tiene índice ni restricción única que impida duplicados, así que el
   script empieza con una guarda de idempotencia: si ya existe la loseta o alguna
   ruta 'gestion-palma%' aborta sin insertar nada. Toda la inserción va dentro de
   una transacción con XACT_ABORT ON y TRY/CATCH, de modo que un fallo a mitad
   hace ROLLBACK y no deja la transacción abierta en la sesión.

   IMPORTANTE: reemplaza @IdUsuario por el Id del usuario administrador que
   quede registrado como creador de los módulos (columna IdUsuario en Modulos).

   NOTA: después de ejecutar este script hay que asignar la loseta
   "Gestión Palma" y sus módulos a los roles que deban verlos, desde la pantalla
   existente "Administración > Roles". Si no se asignan, nadie los verá.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @IdUsuario INT = 1; -- <-- Ajustar al Id real del usuario administrador
DECLARE @Fecha     DATETIME = GETDATE();

DECLARE @IdLoseta          INT;
DECLARE @IdContabilidad    INT;
DECLARE @IdNomina          INT;
DECLARE @IdParametros      INT;
DECLARE @IdLotes           INT;
DECLARE @IdLabores         INT;
DECLARE @IdListaPrecios    INT;
DECLARE @IdCaracteristicas INT;

IF EXISTS (SELECT 1 FROM Modulos WHERE Nombre = N'Gestión Palma' OR Ruta LIKE N'gestion-palma%')
BEGIN
    RAISERROR (N'La loseta "Gestión Palma" ya está registrada en la tabla Modulos. El script no se ejecuta de nuevo para no duplicar filas.', 16, 1);
    RETURN;
END;

BEGIN TRY

    BEGIN TRAN;

    -- 1) Loseta "Gestión Palma"
    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Gestión Palma', N'gestion-palma/onboarding', N'bi bi-tree', NULL, NULL, @IdUsuario, @Fecha);

    SET @IdLoseta = SCOPE_IDENTITY();

    -- 2) Módulos de la loseta "Gestión Palma"
    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Onboarding', N'gestion-palma/onboarding', N'bi bi-list', @IdLoseta, NULL, @IdUsuario, @Fecha);

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Contabilidad', N'', N'bi bi-journal-bookmark', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdContabilidad = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Nómina', N'', N'bi bi-wallet2', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdNomina = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Parámetros agronómicos', N'', N'bi bi-sliders2', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdParametros = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Fincas', N'gestion-palma/fincas', N'bi bi-map', @IdLoseta, NULL, @IdUsuario, @Fecha);

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Secciones / Bloques', N'gestion-palma/secciones', N'bi bi-grid-1x2', @IdLoseta, NULL, @IdUsuario, @Fecha);

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Lotes', N'', N'bi bi-bounding-box', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdLotes = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Labores', N'', N'bi bi-tools', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdLabores = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Lista de precios', N'', N'bi bi-cash-stack', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdListaPrecios = SCOPE_IDENTITY();

    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES (N'Características', N'', N'bi bi-card-checklist', @IdLoseta, NULL, @IdUsuario, @Fecha);

    SET @IdCaracteristicas = SCOPE_IDENTITY();

    -- 3) Submódulos de la loseta "Gestión Palma"
    INSERT INTO Modulos (Nombre, Ruta, Icono, IdLoseta, IdModulo, IdUsuario, FechaInicio)
    VALUES
        (N'Periodos contables', N'gestion-palma/contabilidad/periodos', N'bi bi-calendar3', @IdLoseta, @IdContabilidad, @IdUsuario, @Fecha),
        (N'Terceros', N'gestion-palma/contabilidad/terceros', N'bi bi-person-vcard', @IdLoseta, @IdContabilidad, @IdUsuario, @Fecha),
        (N'Periodos de nómina', N'gestion-palma/nomina/periodos', N'bi bi-calendar-week', @IdLoseta, @IdNomina, @IdUsuario, @Fecha),
        (N'Empleados', N'gestion-palma/nomina/empleados', N'bi bi-people', @IdLoseta, @IdNomina, @IdUsuario, @Fecha),
        (N'Variedad', N'gestion-palma/parametros/variedad', N'bi bi-flower2', @IdLoseta, @IdParametros, @IdUsuario, @Fecha),
        (N'Unidad de medida', N'gestion-palma/parametros/unidad-medida', N'bi bi-rulers', @IdLoseta, @IdParametros, @IdUsuario, @Fecha),
        (N'Registro de lotes', N'gestion-palma/lotes/registro', N'bi bi-card-list', @IdLoseta, @IdLotes, @IdUsuario, @Fecha),
        (N'Peso RFF por lotes', N'gestion-palma/lotes/peso-rff', N'bi bi-speedometer2', @IdLoseta, @IdLotes, @IdUsuario, @Fecha),
        (N'Grupos de labor', N'gestion-palma/labores/grupos', N'bi bi-collection', @IdLoseta, @IdLabores, @IdUsuario, @Fecha),
        (N'Registro de labores', N'gestion-palma/labores/registro', N'bi bi-clipboard-check', @IdLoseta, @IdLabores, @IdUsuario, @Fecha),
        (N'Ítems', N'gestion-palma/labores/items', N'bi bi-box-seam', @IdLoseta, @IdLabores, @IdUsuario, @Fecha),
        (N'Precios por labor', N'gestion-palma/precios/por-labor', N'bi bi-tag', @IdLoseta, @IdListaPrecios, @IdUsuario, @Fecha),
        (N'Precios labor por lote', N'gestion-palma/precios/por-lote', N'bi bi-pin-map', @IdLoseta, @IdListaPrecios, @IdUsuario, @Fecha),
        (N'Reliquidar precios', N'gestion-palma/precios/reliquidar', N'bi bi-arrow-repeat', @IdLoseta, @IdListaPrecios, @IdUsuario, @Fecha),
        (N'Registro de características', N'gestion-palma/caracteristicas/registro', N'bi bi-ui-checks', @IdLoseta, @IdCaracteristicas, @IdUsuario, @Fecha);

    COMMIT TRAN;

END TRY
BEGIN CATCH

    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;

END CATCH;

SELECT Id, Nombre, Ruta, Icono, IdLoseta, IdModulo
FROM Modulos
WHERE Id = @IdLoseta OR IdLoseta = @IdLoseta
ORDER BY Id;
