/* ============================================================================
   Columna Orden en Modulos
   ----------------------------------------------------------------------------
   Agrega la columna Orden (INT NULL) para permitir ordenar losetas, modulos y
   submodulos entre hermanos (mismo IdLoseta e IdModulo).

   La inicializacion respeta el comportamiento actual: "Onboarding" primero y
   luego por Id. Solo se actualizan filas con Orden NULL.

   *** ADVERTENCIA: ESTE SCRIPT NO SE HA EJECUTADO. ***
   ============================================================================ */

SET NOCOUNT ON;

IF COL_LENGTH('Modulos', 'Orden') IS NULL
BEGIN
    ALTER TABLE Modulos ADD Orden INT NULL;
END
GO

;WITH OrdenInicial AS (
    SELECT Id,
           ROW_NUMBER() OVER (
               PARTITION BY IdLoseta, IdModulo
               ORDER BY CASE WHEN Nombre = 'Onboarding' THEN 0 ELSE 1 END, Id
           ) AS NuevoOrden
    FROM Modulos
)
UPDATE m
SET m.Orden = o.NuevoOrden
FROM Modulos m
INNER JOIN OrdenInicial o ON o.Id = m.Id
WHERE m.Orden IS NULL;
GO
