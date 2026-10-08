/* ============================================================================
   Migración Infos -> AppPalma: reparar 11 vistas que referencian dbo.sUsuarios
   ----------------------------------------------------------------------------
   OBLIGATORIO. En AppPalma la tabla sUsuarios se renombró a sUsuariosNO y
   estas vistas fallan con "El nombre de objeto 'dbo.sUsuarios' no es válido"
   (en Infos todas funcionan):
     vInformacionAgronomicoSiesaTotal, vInformacionAgronomicoSiesa,
     vTransaccionAgronomico, vTransaccionAgronomico1,
     vSeleccionaTransaccionesAgronomico, vSeleccionaRegistroLabores,
     vSeleccionaTransaccionCompletaLabores, vSeleccionaRegistroNovadesNomina,
     v_powerbiSeleccionaRegistroLabores, v_powerbiProduccion,
     v_powerbiFertilizacion.
   vInformacionAgronomicoSiesaTotal la usan InformeModel, FinancieroModel y
   ReporteModel: sin este script esos informes no funcionan contra AppPalma.

   Solución: crear un sinónimo dbo.sUsuarios -> dbo.sUsuariosNO. Idempotente.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i 03_reparar_vistas_sUsuarios.sql".
   ============================================================================ */

SET NOCOUNT ON;

IF OBJECT_ID(N'dbo.sUsuarios') IS NULL
    EXEC (N'CREATE SYNONYM dbo.sUsuarios FOR dbo.sUsuariosNO');

SELECT TOP 0 * FROM dbo.vInformacionAgronomicoSiesaTotal;
SELECT TOP 0 * FROM dbo.vInformacionAgronomicoSiesa;
SELECT TOP 0 * FROM dbo.vTransaccionAgronomico;
SELECT TOP 0 * FROM dbo.vTransaccionAgronomico1;
SELECT TOP 0 * FROM dbo.vSeleccionaTransaccionesAgronomico;
SELECT TOP 0 * FROM dbo.vSeleccionaRegistroLabores;
SELECT TOP 0 * FROM dbo.vSeleccionaTransaccionCompletaLabores;
SELECT TOP 0 * FROM dbo.vSeleccionaRegistroNovadesNomina;
SELECT TOP 0 * FROM dbo.v_powerbiSeleccionaRegistroLabores;
SELECT TOP 0 * FROM dbo.v_powerbiProduccion;
SELECT TOP 0 * FROM dbo.v_powerbiFertilizacion;
PRINT N'Las 11 vistas responden.';
