/* ============================================================================
   Columnas adicionales del representante legal en cTercero
   ----------------------------------------------------------------------------
   El formulario de alta de "Maestros > Empresas" captura dos datos del
   representante que HOY no tienen columna donde guardarse:

     - Actividad economica
     - Notas y observaciones

   No se pueden reutilizar columnas existentes: cTercero.descripcion es el
   nombre para mostrar del tercero (coincide con el nombre en 357 de las 406
   filas), no un campo de notas.

   Este script agrega ambas columnas como NULLABLE, de modo que no afecta las
   filas existentes ni los INSERT actuales.

   *** ADVERTENCIA: ESTE SCRIPT NO SE HA EJECUTADO. ***
   Queda documentado para revision y aprobacion. Una vez ejecutado contra la
   base de Contabilidad (SQL Server), basta con agregar 'actividadEconomica'
   y 'notas' a la constante COLUMNAS_REPRESENTANTE de
   app/Models/EmpresasModel.php para que el alta empiece a persistirlas; el
   controlador ya envia ambos valores.
   ============================================================================ */

SET NOCOUNT ON;

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.cTercero') AND name = 'actividadEconomica'
)
BEGIN
    ALTER TABLE dbo.cTercero ADD actividadEconomica varchar(250) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.cTercero') AND name = 'notas'
)
BEGIN
    ALTER TABLE dbo.cTercero ADD notas varchar(1000) NULL;
END
GO
