/* ============================================================================
   OBSOLETO desde 2026-11-04: Infos ya no se usa
   Migración Infos -> AppPalma: sincronización de datos
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Deja las 15 tablas siguientes de AppPalma idénticas a Infos (Infos manda):
       sUsuarios (-> sUsuariosNO), gTipoTransaccion, cPeriodo, nPeriodoDetalle,
       cTercero, nFuncionario, aNovedad, aNovedadLotePrecio, aLotePesosPeriodo,
       aTransaccion, aTransaccionNovedad, aTransaccionTercero, aTransaccionItem,
       aTransaccionBascula, sLogRegistros.
     Fase A: borra en AppPalma las filas cuya llave primaria no está en Infos
             (incluye las transacciones de prueba de desarrollo03 y versiones
             viejas de registros que luego se editaron en Infos). Hijos primero.
             Excepción: con @ConservarPesosSoloAppPalma = 1 NO borra las filas
             de aLotePesosPeriodo que solo existen en AppPalma (proyección
             CGL10-22 a CGL14-22, finca 003, nov-2026 a dic-2030).
     Fase B: actualiza las filas que cambiaron e inserta las que faltan.
             Padres primero.
     Las demás tablas comunes ya eran idénticas al 07-oct-2026 (verificarlo el
     día del corte con 01_diagnostico_diferencias.sql).

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta todo dentro de una transacción, muestra
     cuántas filas tocaría por tabla y hace ROLLBACK. No queda ningún cambio.
     @Aplicar = 1: lo mismo pero hace COMMIT.
     Cualquier error (por ejemplo una llave foránea) revierte TODO.
     @ConservarPesosSoloAppPalma = 1 (por defecto): conserva en AppPalma las
     filas de aLotePesosPeriodo que no existen en Infos (0 eliminadas); las
     de Infos se actualizan e insertan igual.
     @ConservarPesosSoloAppPalma = 0: también las elimina (Infos manda).

   REQUISITOS
   ----------
     - Infos congelada (sin usuarios registrando) y respaldos de ambas bases.
     - Nadie usando AppPalma para registrar transacciones reales: lo que exista
       solo en AppPalma en estas tablas se ELIMINA (salvo aLotePesosPeriodo
       con @ConservarPesosSoloAppPalma = 1).
     - Ninguna actividad de planeación vinculada a transacciones
       (pPlaneacionLinea.numeroTransaccion IS NOT NULL). Si existe alguna, el
       script se detiene con error 50001 antes de abrir la transacción y no
       cambia nada.
     - Ejecutar conectado a AppPalma con un usuario que lea Infos.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 y contiene columnas [año]. Ejecutarlo SOLO desde SSMS
   o con "sqlcmd -f 65001 -i 02_sincronizar_infos_a_apppalma.sql".
   ============================================================================ */

RAISERROR (N'OBSOLETO: Infos dejó de usarse el 2026-11-04. Este script sincroniza Infos -> AppPalma y borraría datos actuales de AppPalma. No ejecutar.', 16, 1);
SET NOEXEC ON;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar BIT = 0;
DECLARE @ConservarPesosSoloAppPalma BIT = 1;
DECLARE @log TABLE (orden INT IDENTITY(1,1), tabla SYSNAME, accion NVARCHAR(20), filas INT);

BEGIN TRY

    IF EXISTS (SELECT 1 FROM AppPalma.dbo.pPlaneacionLinea WHERE numeroTransaccion IS NOT NULL) THROW 50001, N'Hay actividades de planeación vinculadas a transacciones; resolver antes de sincronizar.', 1;

    BEGIN TRAN;

    /* ---------- FASE A: eliminar en AppPalma lo que ya no existe en Infos (hijos primero) ---------- */

    DELETE d FROM AppPalma.dbo.[sLogRegistros] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[sLogRegistros] s WHERE d.[usuario] = s.[usuario] AND d.[fecha] = s.[fecha] AND d.[operacion] = s.[operacion]);
    INSERT @log VALUES (N'sLogRegistros', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aTransaccionBascula] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionBascula] s WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[tiquete] = s.[tiquete]);
    INSERT @log VALUES (N'aTransaccionBascula', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aTransaccionItem] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionItem] s WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[registro] = s.[registro] AND d.[lote] = s.[lote]);
    INSERT @log VALUES (N'aTransaccionItem', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aTransaccionTercero] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionTercero] s WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro] AND d.[registroNovedad] = s.[registroNovedad]);
    INSERT @log VALUES (N'aTransaccionTercero', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aTransaccionNovedad] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccionNovedad] s WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]);
    INSERT @log VALUES (N'aTransaccionNovedad', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aTransaccion] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aTransaccion] s WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero]);
    INSERT @log VALUES (N'aTransaccion', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aLotePesosPeriodo] d
    WHERE @ConservarPesosSoloAppPalma = 0 AND NOT EXISTS (SELECT 1 FROM Infos.dbo.[aLotePesosPeriodo] s WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[finca] = s.[finca] AND d.[lote] = s.[lote]);
    INSERT @log VALUES (N'aLotePesosPeriodo', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aNovedadLotePrecio] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aNovedadLotePrecio] s WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]);
    INSERT @log VALUES (N'aNovedadLotePrecio', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[aNovedad] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[aNovedad] s WHERE d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo] AND d.[concepto] = s.[concepto]);
    INSERT @log VALUES (N'aNovedad', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[nFuncionario] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[nFuncionario] s WHERE d.[empresa] = s.[empresa] AND d.[tercero] = s.[tercero]);
    INSERT @log VALUES (N'nFuncionario', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[cTercero] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[cTercero] s WHERE d.[empresa] = s.[empresa] AND d.[id] = s.[id]);
    INSERT @log VALUES (N'cTercero', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[nPeriodoDetalle] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[nPeriodoDetalle] s WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[noPeriodo] = s.[noPeriodo]);
    INSERT @log VALUES (N'nPeriodoDetalle', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[cPeriodo] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[cPeriodo] s WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes]);
    INSERT @log VALUES (N'cPeriodo', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[gTipoTransaccion] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[gTipoTransaccion] s WHERE d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo]);
    INSERT @log VALUES (N'gTipoTransaccion', N'eliminadas', @@ROWCOUNT);

    DELETE d FROM AppPalma.dbo.[sUsuariosNO] d
    WHERE NOT EXISTS (SELECT 1 FROM Infos.dbo.[sUsuarios] s WHERE d.[usuario] = s.[usuario]);
    INSERT @log VALUES (N'sUsuariosNO', N'eliminadas', @@ROWCOUNT);

    /* ---------- FASE B: actualizar e insertar desde Infos (padres primero) ---------- */

    UPDATE d SET
       d.[descripcion] = s.[descripcion],
       d.[clave] = s.[clave],
       d.[activo] = s.[activo],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[email] = s.[email]
    FROM AppPalma.dbo.[sUsuariosNO] d
    JOIN Infos.dbo.[sUsuarios] s ON d.[usuario] = s.[usuario]
    WHERE EXISTS (SELECT s.[usuario], s.[descripcion], s.[clave], s.[activo], s.[fechaRegistro], s.[email] EXCEPT SELECT d.[usuario], d.[descripcion], d.[clave], d.[activo], d.[fechaRegistro], d.[email]);
    INSERT @log VALUES (N'sUsuariosNO', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[sUsuariosNO] ([usuario],[descripcion],[clave],[activo],[fechaRegistro],[email])
    SELECT s.[usuario], s.[descripcion], s.[clave], s.[activo], s.[fechaRegistro], s.[email]
    FROM Infos.dbo.[sUsuarios] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[sUsuariosNO] d WHERE d.[usuario] = s.[usuario]);
    INSERT @log VALUES (N'sUsuariosNO', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[descripcion] = s.[descripcion],
       d.[numeracion] = s.[numeracion],
       d.[actual] = s.[actual],
       d.[prefijo] = s.[prefijo],
       d.[longitud] = s.[longitud],
       d.[naturaleza] = s.[naturaleza],
       d.[modulo] = s.[modulo],
       d.[modoAnulacion] = s.[modoAnulacion],
       d.[referencia] = s.[referencia],
       d.[vistaDs] = s.[vistaDs],
       d.[activo] = s.[activo],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[forma] = s.[forma],
       d.[formato] = s.[formato]
    FROM AppPalma.dbo.[gTipoTransaccion] d
    JOIN Infos.dbo.[gTipoTransaccion] s ON d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo]
    WHERE EXISTS (SELECT s.[empresa], s.[codigo], s.[descripcion], s.[numeracion], s.[actual], s.[prefijo], s.[longitud], s.[naturaleza], s.[modulo], s.[modoAnulacion], s.[referencia], s.[vistaDs], s.[activo], s.[fechaRegistro], s.[forma], s.[formato] EXCEPT SELECT d.[empresa], d.[codigo], d.[descripcion], d.[numeracion], d.[actual], d.[prefijo], d.[longitud], d.[naturaleza], d.[modulo], d.[modoAnulacion], d.[referencia], d.[vistaDs], d.[activo], d.[fechaRegistro], d.[forma], d.[formato]);
    INSERT @log VALUES (N'gTipoTransaccion', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[gTipoTransaccion] ([empresa],[codigo],[descripcion],[numeracion],[actual],[prefijo],[longitud],[naturaleza],[modulo],[modoAnulacion],[referencia],[vistaDs],[activo],[fechaRegistro],[forma],[formato])
    SELECT s.[empresa], s.[codigo], s.[descripcion], s.[numeracion], s.[actual], s.[prefijo], s.[longitud], s.[naturaleza], s.[modulo], s.[modoAnulacion], s.[referencia], s.[vistaDs], s.[activo], s.[fechaRegistro], s.[forma], s.[formato]
    FROM Infos.dbo.[gTipoTransaccion] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[gTipoTransaccion] d WHERE d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo]);
    INSERT @log VALUES (N'gTipoTransaccion', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[descripcion] = s.[descripcion],
       d.[periodo] = s.[periodo],
       d.[cerrado] = s.[cerrado],
       d.[fechaInicial] = s.[fechaInicial],
       d.[fechaFinal] = s.[fechaFinal]
    FROM AppPalma.dbo.[cPeriodo] d
    JOIN Infos.dbo.[cPeriodo] s ON d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[descripcion], s.[periodo], s.[cerrado], s.[fechaInicial], s.[fechaFinal] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[descripcion], d.[periodo], d.[cerrado], d.[fechaInicial], d.[fechaFinal]);
    INSERT @log VALUES (N'cPeriodo', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[cPeriodo] ([empresa],[año],[mes],[descripcion],[periodo],[cerrado],[fechaInicial],[fechaFinal])
    SELECT s.[empresa], s.[año], s.[mes], s.[descripcion], s.[periodo], s.[cerrado], s.[fechaInicial], s.[fechaFinal]
    FROM Infos.dbo.[cPeriodo] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[cPeriodo] d WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes]);
    INSERT @log VALUES (N'cPeriodo', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[fechaInicial] = s.[fechaInicial],
       d.[fechaFinal] = s.[fechaFinal],
       d.[fechaCorte] = s.[fechaCorte],
       d.[fechaPago] = s.[fechaPago],
       d.[cerrado] = s.[cerrado],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[usuario] = s.[usuario],
       d.[tipoNomina] = s.[tipoNomina],
       d.[diasNomina] = s.[diasNomina],
       d.[agronomico] = s.[agronomico],
       d.[ejecutaLabores] = s.[ejecutaLabores],
       d.[nombrePeriodo] = s.[nombrePeriodo]
    FROM AppPalma.dbo.[nPeriodoDetalle] d
    JOIN Infos.dbo.[nPeriodoDetalle] s ON d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[noPeriodo] = s.[noPeriodo]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[noPeriodo], s.[fechaInicial], s.[fechaFinal], s.[fechaCorte], s.[fechaPago], s.[cerrado], s.[fechaRegistro], s.[usuario], s.[tipoNomina], s.[diasNomina], s.[agronomico], s.[ejecutaLabores], s.[nombrePeriodo] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[noPeriodo], d.[fechaInicial], d.[fechaFinal], d.[fechaCorte], d.[fechaPago], d.[cerrado], d.[fechaRegistro], d.[usuario], d.[tipoNomina], d.[diasNomina], d.[agronomico], d.[ejecutaLabores], d.[nombrePeriodo]);
    INSERT @log VALUES (N'nPeriodoDetalle', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[nPeriodoDetalle] ([empresa],[año],[mes],[noPeriodo],[fechaInicial],[fechaFinal],[fechaCorte],[fechaPago],[cerrado],[fechaRegistro],[usuario],[tipoNomina],[diasNomina],[agronomico],[ejecutaLabores],[nombrePeriodo])
    SELECT s.[empresa], s.[año], s.[mes], s.[noPeriodo], s.[fechaInicial], s.[fechaFinal], s.[fechaCorte], s.[fechaPago], s.[cerrado], s.[fechaRegistro], s.[usuario], s.[tipoNomina], s.[diasNomina], s.[agronomico], s.[ejecutaLabores], s.[nombrePeriodo]
    FROM Infos.dbo.[nPeriodoDetalle] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[nPeriodoDetalle] d WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[noPeriodo] = s.[noPeriodo]);
    INSERT @log VALUES (N'nPeriodoDetalle', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[codigo] = s.[codigo],
       d.[tipoDocumento] = s.[tipoDocumento],
       d.[tipo] = s.[tipo],
       d.[nit] = s.[nit],
       d.[dv] = s.[dv],
       d.[razonSocial] = s.[razonSocial],
       d.[apellido1] = s.[apellido1],
       d.[apellido2] = s.[apellido2],
       d.[nombre1] = s.[nombre1],
       d.[nombre2] = s.[nombre2],
       d.[descripcion] = s.[descripcion],
       d.[activo] = s.[activo],
       d.[ciudad] = s.[ciudad],
       d.[cliente] = s.[cliente],
       d.[proveedor] = s.[proveedor],
       d.[empleado] = s.[empleado],
       d.[accionista] = s.[accionista],
       d.[contratista] = s.[contratista],
       d.[extractora] = s.[extractora],
       d.[foto] = s.[foto],
       d.[contacto] = s.[contacto],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[telefono] = s.[telefono],
       d.[direccion] = s.[direccion],
       d.[barrio] = s.[barrio],
       d.[fax] = s.[fax],
       d.[email] = s.[email],
       d.[comercializadora] = s.[comercializadora],
       d.[departamento] = s.[departamento],
       d.[codigoEquivalencia] = s.[codigoEquivalencia]
    FROM AppPalma.dbo.[cTercero] d
    JOIN Infos.dbo.[cTercero] s ON d.[empresa] = s.[empresa] AND d.[id] = s.[id]
    WHERE EXISTS (SELECT s.[empresa], s.[id], s.[codigo], s.[tipoDocumento], s.[tipo], s.[nit], s.[dv], s.[razonSocial], s.[apellido1], s.[apellido2], s.[nombre1], s.[nombre2], s.[descripcion], s.[activo], s.[ciudad], s.[cliente], s.[proveedor], s.[empleado], s.[accionista], s.[contratista], s.[extractora], s.[foto], s.[contacto], s.[fechaRegistro], s.[telefono], s.[direccion], s.[barrio], s.[fax], s.[email], s.[comercializadora], s.[departamento], s.[codigoEquivalencia] EXCEPT SELECT d.[empresa], d.[id], d.[codigo], d.[tipoDocumento], d.[tipo], d.[nit], d.[dv], d.[razonSocial], d.[apellido1], d.[apellido2], d.[nombre1], d.[nombre2], d.[descripcion], d.[activo], d.[ciudad], d.[cliente], d.[proveedor], d.[empleado], d.[accionista], d.[contratista], d.[extractora], d.[foto], d.[contacto], d.[fechaRegistro], d.[telefono], d.[direccion], d.[barrio], d.[fax], d.[email], d.[comercializadora], d.[departamento], d.[codigoEquivalencia]);
    INSERT @log VALUES (N'cTercero', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[cTercero] ([empresa],[id],[codigo],[tipoDocumento],[tipo],[nit],[dv],[razonSocial],[apellido1],[apellido2],[nombre1],[nombre2],[descripcion],[activo],[ciudad],[cliente],[proveedor],[empleado],[accionista],[contratista],[extractora],[foto],[contacto],[fechaRegistro],[telefono],[direccion],[barrio],[fax],[email],[comercializadora],[departamento],[codigoEquivalencia])
    SELECT s.[empresa], s.[id], s.[codigo], s.[tipoDocumento], s.[tipo], s.[nit], s.[dv], s.[razonSocial], s.[apellido1], s.[apellido2], s.[nombre1], s.[nombre2], s.[descripcion], s.[activo], s.[ciudad], s.[cliente], s.[proveedor], s.[empleado], s.[accionista], s.[contratista], s.[extractora], s.[foto], s.[contacto], s.[fechaRegistro], s.[telefono], s.[direccion], s.[barrio], s.[fax], s.[email], s.[comercializadora], s.[departamento], s.[codigoEquivalencia]
    FROM Infos.dbo.[cTercero] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[cTercero] d WHERE d.[empresa] = s.[empresa] AND d.[id] = s.[id]);
    INSERT @log VALUES (N'cTercero', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[sexo] = s.[sexo],
       d.[codigo] = s.[codigo],
       d.[proveedor] = s.[proveedor],
       d.[cliente] = s.[cliente],
       d.[descripcion] = s.[descripcion],
       d.[rh] = s.[rh],
       d.[fechaNacimiento] = s.[fechaNacimiento],
       d.[ciduadNacimiento] = s.[ciduadNacimiento],
       d.[nivelEducativo] = s.[nivelEducativo],
       d.[activo] = s.[activo],
       d.[validaTurno] = s.[validaTurno],
       d.[conductor] = s.[conductor],
       d.[operadorLogistico] = s.[operadorLogistico],
       d.[extranjero] = s.[extranjero],
       d.[declarante] = s.[declarante],
       d.[contratista] = s.[contratista],
       d.[otros] = s.[otros],
       d.[foto] = s.[foto],
       d.[salario] = s.[salario],
       d.[fechaIngreso] = s.[fechaIngreso]
    FROM AppPalma.dbo.[nFuncionario] d
    JOIN Infos.dbo.[nFuncionario] s ON d.[empresa] = s.[empresa] AND d.[tercero] = s.[tercero]
    WHERE EXISTS (SELECT s.[empresa], s.[tercero], s.[sexo], s.[codigo], s.[proveedor], s.[cliente], s.[descripcion], s.[rh], s.[fechaNacimiento], s.[ciduadNacimiento], s.[nivelEducativo], s.[activo], s.[validaTurno], s.[conductor], s.[operadorLogistico], s.[extranjero], s.[declarante], s.[contratista], s.[otros], s.[foto], s.[salario], s.[fechaIngreso] EXCEPT SELECT d.[empresa], d.[tercero], d.[sexo], d.[codigo], d.[proveedor], d.[cliente], d.[descripcion], d.[rh], d.[fechaNacimiento], d.[ciduadNacimiento], d.[nivelEducativo], d.[activo], d.[validaTurno], d.[conductor], d.[operadorLogistico], d.[extranjero], d.[declarante], d.[contratista], d.[otros], d.[foto], d.[salario], d.[fechaIngreso]);
    INSERT @log VALUES (N'nFuncionario', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[nFuncionario] ([empresa],[tercero],[sexo],[codigo],[proveedor],[cliente],[descripcion],[rh],[fechaNacimiento],[ciduadNacimiento],[nivelEducativo],[activo],[validaTurno],[conductor],[operadorLogistico],[extranjero],[declarante],[contratista],[otros],[foto],[salario],[fechaIngreso])
    SELECT s.[empresa], s.[tercero], s.[sexo], s.[codigo], s.[proveedor], s.[cliente], s.[descripcion], s.[rh], s.[fechaNacimiento], s.[ciduadNacimiento], s.[nivelEducativo], s.[activo], s.[validaTurno], s.[conductor], s.[operadorLogistico], s.[extranjero], s.[declarante], s.[contratista], s.[otros], s.[foto], s.[salario], s.[fechaIngreso]
    FROM Infos.dbo.[nFuncionario] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[nFuncionario] d WHERE d.[empresa] = s.[empresa] AND d.[tercero] = s.[tercero]);
    INSERT @log VALUES (N'nFuncionario', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[descripcion] = s.[descripcion],
       d.[desCorta] = s.[desCorta],
       d.[grupo] = s.[grupo],
       d.[uMedida] = s.[uMedida],
       d.[ciclos] = s.[ciclos],
       d.[tarea] = s.[tarea],
       d.[naturaleza] = s.[naturaleza],
       d.[impuesto] = s.[impuesto],
       d.[equivalencia] = s.[equivalencia],
       d.[grupoIR] = s.[grupoIR],
       d.[manejaLote] = s.[manejaLote],
       d.[manejaSaldo] = s.[manejaSaldo],
       d.[manejaCanal] = s.[manejaCanal],
       d.[manejaLinea] = s.[manejaLinea],
       d.[manejaPalma] = s.[manejaPalma],
       d.[tipoCanal] = s.[tipoCanal],
       d.[manejaRacimo] = s.[manejaRacimo],
       d.[manejaJornal] = s.[manejaJornal],
       d.[porHaNeta] = s.[porHaNeta],
       d.[porHaBruta] = s.[porHaBruta],
       d.[porHaProduccion] = s.[porHaProduccion],
       d.[manejaBascula] = s.[manejaBascula],
       d.[manejaFecha] = s.[manejaFecha],
       d.[manejaRango] = s.[manejaRango],
       d.[añoDesde] = s.[añoDesde],
       d.[añoHasta] = s.[añoHasta],
       d.[activo] = s.[activo],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[usuario] = s.[usuario],
       d.[claseLabor] = s.[claseLabor],
       d.[manejaDecimal] = s.[manejaDecimal],
       d.[noPrestacional] = s.[noPrestacional],
       d.[manejaCaracteristica] = s.[manejaCaracteristica],
       d.[muestraInforme] = s.[muestraInforme],
       d.[muestraInformeContratista] = s.[muestraInformeContratista],
       d.[calculaJornal] = s.[calculaJornal]
    FROM AppPalma.dbo.[aNovedad] d
    JOIN Infos.dbo.[aNovedad] s ON d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo] AND d.[concepto] = s.[concepto]
    WHERE EXISTS (SELECT s.[empresa], s.[codigo], s.[descripcion], s.[desCorta], s.[grupo], s.[uMedida], s.[ciclos], s.[tarea], s.[naturaleza], s.[impuesto], s.[equivalencia], s.[concepto], s.[grupoIR], s.[manejaLote], s.[manejaSaldo], s.[manejaCanal], s.[manejaLinea], s.[manejaPalma], s.[tipoCanal], s.[manejaRacimo], s.[manejaJornal], s.[porHaNeta], s.[porHaBruta], s.[porHaProduccion], s.[manejaBascula], s.[manejaFecha], s.[manejaRango], s.[añoDesde], s.[añoHasta], s.[activo], s.[fechaRegistro], s.[usuario], s.[claseLabor], s.[manejaDecimal], s.[noPrestacional], s.[manejaCaracteristica], s.[muestraInforme], s.[muestraInformeContratista], s.[calculaJornal] EXCEPT SELECT d.[empresa], d.[codigo], d.[descripcion], d.[desCorta], d.[grupo], d.[uMedida], d.[ciclos], d.[tarea], d.[naturaleza], d.[impuesto], d.[equivalencia], d.[concepto], d.[grupoIR], d.[manejaLote], d.[manejaSaldo], d.[manejaCanal], d.[manejaLinea], d.[manejaPalma], d.[tipoCanal], d.[manejaRacimo], d.[manejaJornal], d.[porHaNeta], d.[porHaBruta], d.[porHaProduccion], d.[manejaBascula], d.[manejaFecha], d.[manejaRango], d.[añoDesde], d.[añoHasta], d.[activo], d.[fechaRegistro], d.[usuario], d.[claseLabor], d.[manejaDecimal], d.[noPrestacional], d.[manejaCaracteristica], d.[muestraInforme], d.[muestraInformeContratista], d.[calculaJornal]);
    INSERT @log VALUES (N'aNovedad', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aNovedad] ([empresa],[codigo],[descripcion],[desCorta],[grupo],[uMedida],[ciclos],[tarea],[naturaleza],[impuesto],[equivalencia],[concepto],[grupoIR],[manejaLote],[manejaSaldo],[manejaCanal],[manejaLinea],[manejaPalma],[tipoCanal],[manejaRacimo],[manejaJornal],[porHaNeta],[porHaBruta],[porHaProduccion],[manejaBascula],[manejaFecha],[manejaRango],[añoDesde],[añoHasta],[activo],[fechaRegistro],[usuario],[claseLabor],[manejaDecimal],[noPrestacional],[manejaCaracteristica],[muestraInforme],[muestraInformeContratista],[calculaJornal])
    SELECT s.[empresa], s.[codigo], s.[descripcion], s.[desCorta], s.[grupo], s.[uMedida], s.[ciclos], s.[tarea], s.[naturaleza], s.[impuesto], s.[equivalencia], s.[concepto], s.[grupoIR], s.[manejaLote], s.[manejaSaldo], s.[manejaCanal], s.[manejaLinea], s.[manejaPalma], s.[tipoCanal], s.[manejaRacimo], s.[manejaJornal], s.[porHaNeta], s.[porHaBruta], s.[porHaProduccion], s.[manejaBascula], s.[manejaFecha], s.[manejaRango], s.[añoDesde], s.[añoHasta], s.[activo], s.[fechaRegistro], s.[usuario], s.[claseLabor], s.[manejaDecimal], s.[noPrestacional], s.[manejaCaracteristica], s.[muestraInforme], s.[muestraInformeContratista], s.[calculaJornal]
    FROM Infos.dbo.[aNovedad] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aNovedad] d WHERE d.[empresa] = s.[empresa] AND d.[codigo] = s.[codigo] AND d.[concepto] = s.[concepto]);
    INSERT @log VALUES (N'aNovedad', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[precioDestajo] = s.[precioDestajo],
       d.[precioContratistas] = s.[precioContratistas],
       d.[precioOtros] = s.[precioOtros],
       d.[porcentaje] = s.[porcentaje],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[usuario] = s.[usuario],
       d.[modificado] = s.[modificado],
       d.[baseSueldo] = s.[baseSueldo]
    FROM AppPalma.dbo.[aNovedadLotePrecio] d
    JOIN Infos.dbo.[aNovedadLotePrecio] s ON d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[novedad], s.[registro], s.[precioDestajo], s.[precioContratistas], s.[precioOtros], s.[porcentaje], s.[fechaRegistro], s.[usuario], s.[modificado], s.[baseSueldo] EXCEPT SELECT d.[empresa], d.[año], d.[novedad], d.[registro], d.[precioDestajo], d.[precioContratistas], d.[precioOtros], d.[porcentaje], d.[fechaRegistro], d.[usuario], d.[modificado], d.[baseSueldo]);
    INSERT @log VALUES (N'aNovedadLotePrecio', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aNovedadLotePrecio] ([empresa],[año],[novedad],[registro],[precioDestajo],[precioContratistas],[precioOtros],[porcentaje],[fechaRegistro],[usuario],[modificado],[baseSueldo])
    SELECT s.[empresa], s.[año], s.[novedad], s.[registro], s.[precioDestajo], s.[precioContratistas], s.[precioOtros], s.[porcentaje], s.[fechaRegistro], s.[usuario], s.[modificado], s.[baseSueldo]
    FROM Infos.dbo.[aNovedadLotePrecio] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aNovedadLotePrecio] d WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]);
    INSERT @log VALUES (N'aNovedadLotePrecio', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[seccion] = s.[seccion],
       d.[pesoRacimo] = s.[pesoRacimo],
       d.[automatico] = s.[automatico],
       d.[fechaInicial] = s.[fechaInicial],
       d.[fechaFinal] = s.[fechaFinal]
    FROM AppPalma.dbo.[aLotePesosPeriodo] d
    JOIN Infos.dbo.[aLotePesosPeriodo] s ON d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[finca] = s.[finca] AND d.[lote] = s.[lote]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[finca], s.[seccion], s.[lote], s.[pesoRacimo], s.[automatico], s.[fechaInicial], s.[fechaFinal] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[finca], d.[seccion], d.[lote], d.[pesoRacimo], d.[automatico], d.[fechaInicial], d.[fechaFinal]);
    INSERT @log VALUES (N'aLotePesosPeriodo', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aLotePesosPeriodo] ([empresa],[año],[mes],[finca],[seccion],[lote],[pesoRacimo],[automatico],[fechaInicial],[fechaFinal])
    SELECT s.[empresa], s.[año], s.[mes], s.[finca], s.[seccion], s.[lote], s.[pesoRacimo], s.[automatico], s.[fechaInicial], s.[fechaFinal]
    FROM Infos.dbo.[aLotePesosPeriodo] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aLotePesosPeriodo] d WHERE d.[empresa] = s.[empresa] AND d.[año] = s.[año] AND d.[mes] = s.[mes] AND d.[finca] = s.[finca] AND d.[lote] = s.[lote]);
    INSERT @log VALUES (N'aLotePesosPeriodo', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[año] = s.[año],
       d.[mes] = s.[mes],
       d.[fecha] = s.[fecha],
       d.[referencia] = s.[referencia],
       d.[finca] = s.[finca],
       d.[jornal] = s.[jornal],
       d.[racimos] = s.[racimos],
       d.[cantidad] = s.[cantidad],
       d.[precio] = s.[precio],
       d.[valorTotal] = s.[valorTotal],
       d.[fechaFinal] = s.[fechaFinal],
       d.[remision] = s.[remision],
       d.[observacion] = s.[observacion],
       d.[fechaRegistro] = s.[fechaRegistro],
       d.[usuarioRegistro] = s.[usuarioRegistro],
       d.[anulado] = s.[anulado],
       d.[usuarioAnulado] = s.[usuarioAnulado],
       d.[fechaAnulado] = s.[fechaAnulado],
       d.[periodo] = s.[periodo],
       d.[cerrado] = s.[cerrado],
       d.[añofer] = s.[añofer],
       d.[mesIfer] = s.[mesIfer],
       d.[mesFfer] = s.[mesFfer]
    FROM AppPalma.dbo.[aTransaccion] d
    JOIN Infos.dbo.[aTransaccion] s ON d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[fecha], s.[referencia], s.[finca], s.[jornal], s.[racimos], s.[cantidad], s.[precio], s.[valorTotal], s.[fechaFinal], s.[remision], s.[observacion], s.[fechaRegistro], s.[usuarioRegistro], s.[anulado], s.[usuarioAnulado], s.[fechaAnulado], s.[periodo], s.[cerrado], s.[añofer], s.[mesIfer], s.[mesFfer] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[tipo], d.[numero], d.[fecha], d.[referencia], d.[finca], d.[jornal], d.[racimos], d.[cantidad], d.[precio], d.[valorTotal], d.[fechaFinal], d.[remision], d.[observacion], d.[fechaRegistro], d.[usuarioRegistro], d.[anulado], d.[usuarioAnulado], d.[fechaAnulado], d.[periodo], d.[cerrado], d.[añofer], d.[mesIfer], d.[mesFfer]);
    INSERT @log VALUES (N'aTransaccion', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccion] ([empresa],[año],[mes],[tipo],[numero],[fecha],[referencia],[finca],[jornal],[racimos],[cantidad],[precio],[valorTotal],[fechaFinal],[remision],[observacion],[fechaRegistro],[usuarioRegistro],[anulado],[usuarioAnulado],[fechaAnulado],[periodo],[cerrado],[añofer],[mesIfer],[mesFfer])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[fecha], s.[referencia], s.[finca], s.[jornal], s.[racimos], s.[cantidad], s.[precio], s.[valorTotal], s.[fechaFinal], s.[remision], s.[observacion], s.[fechaRegistro], s.[usuarioRegistro], s.[anulado], s.[usuarioAnulado], s.[fechaAnulado], s.[periodo], s.[cerrado], s.[añofer], s.[mesIfer], s.[mesFfer]
    FROM Infos.dbo.[aTransaccion] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccion] d WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero]);
    INSERT @log VALUES (N'aTransaccion', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[año] = s.[año],
       d.[mes] = s.[mes],
       d.[uMedida] = s.[uMedida],
       d.[finca] = s.[finca],
       d.[seccion] = s.[seccion],
       d.[lote] = s.[lote],
       d.[fecha] = s.[fecha],
       d.[cantidad] = s.[cantidad],
       d.[jornales] = s.[jornales],
       d.[racimos] = s.[racimos],
       d.[pesoRacimo] = s.[pesoRacimo],
       d.[saldo] = s.[saldo],
       d.[ejecutado] = s.[ejecutado],
       d.[signo] = s.[signo],
       d.[precioLabor] = s.[precioLabor],
       d.[concepto] = s.[concepto],
       d.[periodo] = s.[periodo],
       d.[registroNovedad] = s.[registroNovedad],
       d.[sacos] = s.[sacos]
    FROM AppPalma.dbo.[aTransaccionNovedad] d
    JOIN Infos.dbo.[aTransaccionNovedad] s ON d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[uMedida], s.[finca], s.[seccion], s.[lote], s.[fecha], s.[cantidad], s.[jornales], s.[racimos], s.[pesoRacimo], s.[saldo], s.[ejecutado], s.[signo], s.[precioLabor], s.[concepto], s.[periodo], s.[registroNovedad], s.[sacos] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[tipo], d.[numero], d.[novedad], d.[registro], d.[uMedida], d.[finca], d.[seccion], d.[lote], d.[fecha], d.[cantidad], d.[jornales], d.[racimos], d.[pesoRacimo], d.[saldo], d.[ejecutado], d.[signo], d.[precioLabor], d.[concepto], d.[periodo], d.[registroNovedad], d.[sacos]);
    INSERT @log VALUES (N'aTransaccionNovedad', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionNovedad] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[uMedida],[finca],[seccion],[lote],[fecha],[cantidad],[jornales],[racimos],[pesoRacimo],[saldo],[ejecutado],[signo],[precioLabor],[concepto],[periodo],[registroNovedad],[sacos])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[uMedida], s.[finca], s.[seccion], s.[lote], s.[fecha], s.[cantidad], s.[jornales], s.[racimos], s.[pesoRacimo], s.[saldo], s.[ejecutado], s.[signo], s.[precioLabor], s.[concepto], s.[periodo], s.[registroNovedad], s.[sacos]
    FROM Infos.dbo.[aTransaccionNovedad] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionNovedad] d WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro]);
    INSERT @log VALUES (N'aTransaccionNovedad', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[año] = s.[año],
       d.[mes] = s.[mes],
       d.[finca] = s.[finca],
       d.[seccion] = s.[seccion],
       d.[lote] = s.[lote],
       d.[tercero] = s.[tercero],
       d.[cantidad] = s.[cantidad],
       d.[jornales] = s.[jornales],
       d.[saldo] = s.[saldo],
       d.[ejecutado] = s.[ejecutado],
       d.[zCuadrilla] = s.[zCuadrilla],
       d.[precioLabor] = s.[precioLabor],
       d.[valorTotal] = s.[valorTotal],
       d.[ccosto] = s.[ccosto],
       d.[contrato] = s.[contrato],
       d.[periodo] = s.[periodo],
       d.[contratista] = s.[contratista],
       d.[proveedor] = s.[proveedor],
       d.[racimos] = s.[racimos],
       d.[fechaNovedad] = s.[fechaNovedad]
    FROM AppPalma.dbo.[aTransaccionTercero] d
    JOIN Infos.dbo.[aTransaccionTercero] s ON d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro] AND d.[registroNovedad] = s.[registroNovedad]
    WHERE EXISTS (SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[registroNovedad], s.[finca], s.[seccion], s.[lote], s.[tercero], s.[cantidad], s.[jornales], s.[saldo], s.[ejecutado], s.[zCuadrilla], s.[precioLabor], s.[valorTotal], s.[ccosto], s.[contrato], s.[periodo], s.[contratista], s.[proveedor], s.[racimos], s.[fechaNovedad] EXCEPT SELECT d.[empresa], d.[año], d.[mes], d.[tipo], d.[numero], d.[novedad], d.[registro], d.[registroNovedad], d.[finca], d.[seccion], d.[lote], d.[tercero], d.[cantidad], d.[jornales], d.[saldo], d.[ejecutado], d.[zCuadrilla], d.[precioLabor], d.[valorTotal], d.[ccosto], d.[contrato], d.[periodo], d.[contratista], d.[proveedor], d.[racimos], d.[fechaNovedad]);
    INSERT @log VALUES (N'aTransaccionTercero', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionTercero] ([empresa],[año],[mes],[tipo],[numero],[novedad],[registro],[registroNovedad],[finca],[seccion],[lote],[tercero],[cantidad],[jornales],[saldo],[ejecutado],[zCuadrilla],[precioLabor],[valorTotal],[ccosto],[contrato],[periodo],[contratista],[proveedor],[racimos],[fechaNovedad])
    SELECT s.[empresa], s.[año], s.[mes], s.[tipo], s.[numero], s.[novedad], s.[registro], s.[registroNovedad], s.[finca], s.[seccion], s.[lote], s.[tercero], s.[cantidad], s.[jornales], s.[saldo], s.[ejecutado], s.[zCuadrilla], s.[precioLabor], s.[valorTotal], s.[ccosto], s.[contrato], s.[periodo], s.[contratista], s.[proveedor], s.[racimos], s.[fechaNovedad]
    FROM Infos.dbo.[aTransaccionTercero] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionTercero] d WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[novedad] = s.[novedad] AND d.[registro] = s.[registro] AND d.[registroNovedad] = s.[registroNovedad]);
    INSERT @log VALUES (N'aTransaccionTercero', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[año] = s.[año],
       d.[mes] = s.[mes],
       d.[fecha] = s.[fecha],
       d.[fechaFinal] = s.[fechaFinal],
       d.[item] = s.[item],
       d.[uMedida] = s.[uMedida],
       d.[novedad] = s.[novedad],
       d.[cantidad] = s.[cantidad],
       d.[saldo] = s.[saldo],
       d.[mBulto] = s.[mBulto],
       d.[pBulto] = s.[pBulto],
       d.[noPalmas] = s.[noPalmas],
       d.[dosis] = s.[dosis],
       d.[registror] = s.[registror]
    FROM AppPalma.dbo.[aTransaccionItem] d
    JOIN Infos.dbo.[aTransaccionItem] s ON d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[registro] = s.[registro] AND d.[lote] = s.[lote]
    WHERE EXISTS (SELECT s.[empresa], s.[tipo], s.[numero], s.[registro], s.[lote], s.[año], s.[mes], s.[fecha], s.[fechaFinal], s.[item], s.[uMedida], s.[novedad], s.[cantidad], s.[saldo], s.[mBulto], s.[pBulto], s.[noPalmas], s.[dosis], s.[registror] EXCEPT SELECT d.[empresa], d.[tipo], d.[numero], d.[registro], d.[lote], d.[año], d.[mes], d.[fecha], d.[fechaFinal], d.[item], d.[uMedida], d.[novedad], d.[cantidad], d.[saldo], d.[mBulto], d.[pBulto], d.[noPalmas], d.[dosis], d.[registror]);
    INSERT @log VALUES (N'aTransaccionItem', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionItem] ([empresa],[tipo],[numero],[registro],[lote],[año],[mes],[fecha],[fechaFinal],[item],[uMedida],[novedad],[cantidad],[saldo],[mBulto],[pBulto],[noPalmas],[dosis],[registror])
    SELECT s.[empresa], s.[tipo], s.[numero], s.[registro], s.[lote], s.[año], s.[mes], s.[fecha], s.[fechaFinal], s.[item], s.[uMedida], s.[novedad], s.[cantidad], s.[saldo], s.[mBulto], s.[pBulto], s.[noPalmas], s.[dosis], s.[registror]
    FROM Infos.dbo.[aTransaccionItem] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionItem] d WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[registro] = s.[registro] AND d.[lote] = s.[lote]);
    INSERT @log VALUES (N'aTransaccionItem', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[planta] = s.[planta],
       d.[fecha] = s.[fecha],
       d.[pesoBruto] = s.[pesoBruto],
       d.[pesoTara] = s.[pesoTara],
       d.[pesoNeto] = s.[pesoNeto],
       d.[sacos] = s.[sacos],
       d.[racimos] = s.[racimos],
       d.[codigoConductor] = s.[codigoConductor],
       d.[nombreConductor] = s.[nombreConductor],
       d.[vehiculo] = s.[vehiculo],
       d.[remolque] = s.[remolque],
       d.[interno] = s.[interno],
       d.[empresaExtractora] = s.[empresaExtractora],
       d.[terceroExtractrora] = s.[terceroExtractrora]
    FROM AppPalma.dbo.[aTransaccionBascula] d
    JOIN Infos.dbo.[aTransaccionBascula] s ON d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[tiquete] = s.[tiquete]
    WHERE EXISTS (SELECT s.[empresa], s.[tipo], s.[numero], s.[planta], s.[tiquete], s.[fecha], s.[pesoBruto], s.[pesoTara], s.[pesoNeto], s.[sacos], s.[racimos], s.[codigoConductor], s.[nombreConductor], s.[vehiculo], s.[remolque], s.[interno], s.[empresaExtractora], s.[terceroExtractrora] EXCEPT SELECT d.[empresa], d.[tipo], d.[numero], d.[planta], d.[tiquete], d.[fecha], d.[pesoBruto], d.[pesoTara], d.[pesoNeto], d.[sacos], d.[racimos], d.[codigoConductor], d.[nombreConductor], d.[vehiculo], d.[remolque], d.[interno], d.[empresaExtractora], d.[terceroExtractrora]);
    INSERT @log VALUES (N'aTransaccionBascula', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[aTransaccionBascula] ([empresa],[tipo],[numero],[planta],[tiquete],[fecha],[pesoBruto],[pesoTara],[pesoNeto],[sacos],[racimos],[codigoConductor],[nombreConductor],[vehiculo],[remolque],[interno],[empresaExtractora],[terceroExtractrora])
    SELECT s.[empresa], s.[tipo], s.[numero], s.[planta], s.[tiquete], s.[fecha], s.[pesoBruto], s.[pesoTara], s.[pesoNeto], s.[sacos], s.[racimos], s.[codigoConductor], s.[nombreConductor], s.[vehiculo], s.[remolque], s.[interno], s.[empresaExtractora], s.[terceroExtractrora]
    FROM Infos.dbo.[aTransaccionBascula] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[aTransaccionBascula] d WHERE d.[empresa] = s.[empresa] AND d.[tipo] = s.[tipo] AND d.[numero] = s.[numero] AND d.[tiquete] = s.[tiquete]);
    INSERT @log VALUES (N'aTransaccionBascula', N'insertadas', @@ROWCOUNT);

    UPDATE d SET
       d.[empresa] = s.[empresa],
       d.[entidad] = s.[entidad],
       d.[estado] = s.[estado],
       d.[mensajeSistema] = s.[mensajeSistema],
       d.[ip] = s.[ip]
    FROM AppPalma.dbo.[sLogRegistros] d
    JOIN Infos.dbo.[sLogRegistros] s ON d.[usuario] = s.[usuario] AND d.[fecha] = s.[fecha] AND d.[operacion] = s.[operacion]
    WHERE EXISTS (SELECT s.[usuario], s.[fecha], s.[operacion], s.[empresa], s.[entidad], s.[estado], s.[mensajeSistema], s.[ip] EXCEPT SELECT d.[usuario], d.[fecha], d.[operacion], d.[empresa], d.[entidad], d.[estado], d.[mensajeSistema], d.[ip]);
    INSERT @log VALUES (N'sLogRegistros', N'actualizadas', @@ROWCOUNT);

    INSERT INTO AppPalma.dbo.[sLogRegistros] ([usuario],[fecha],[operacion],[empresa],[entidad],[estado],[mensajeSistema],[ip])
    SELECT s.[usuario], s.[fecha], s.[operacion], s.[empresa], s.[entidad], s.[estado], s.[mensajeSistema], s.[ip]
    FROM Infos.dbo.[sLogRegistros] s
    WHERE NOT EXISTS (SELECT 1 FROM AppPalma.dbo.[sLogRegistros] d WHERE d.[usuario] = s.[usuario] AND d.[fecha] = s.[fecha] AND d.[operacion] = s.[operacion]);
    INSERT @log VALUES (N'sLogRegistros', N'insertadas', @@ROWCOUNT);

    SELECT tabla, accion, filas FROM @log WHERE filas > 0 ORDER BY orden;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados.';
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
