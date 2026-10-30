/* ============================================================================
   Vista de inicio por rol
   ----------------------------------------------------------------------------
   Agrega la columna IdModuloInicio (INT NULL) en Roles. Indica la loseta o
   modulo (Modulos.Id) al que entra automaticamente el usuario al iniciar
   sesion. NULL = menu de losetas (comportamiento actual).

   Sin llave foranea: Modulos.Id no es llave primaria. La aplicacion valida
   que el modulo exista, este vigente y el rol tenga permiso.

   No modifica filas existentes: todos los roles quedan en NULL.

   *** ADVERTENCIA: ESTE SCRIPT NO SE HA EJECUTADO. ***
   ============================================================================ */

SET NOCOUNT ON;

IF COL_LENGTH('Roles', 'IdModuloInicio') IS NULL
BEGIN
    ALTER TABLE Roles ADD IdModuloInicio INT NULL;
END
GO
