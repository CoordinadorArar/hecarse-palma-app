-- ============================================================
-- VISTA: vAusentismo
-- ============================================================
CREATE VIEW dbo.vAusentismo
AS
SELECT        c.id AS codigoTercero, c.codigo AS identificacion, c.descripcion AS nombreTercero, a.tipoIncapacidad, b.descripcion AS nombreTipoIncapacidad, a.diagnostico, d.descripcion AS nombreDiagnostico, a.fechaInicial, a.fechaFinal, 
                         a.noDias AS diasIncapacidad, a.referencia, a.fechaInicial AS fecheInicialP, DATEADD(day, a.diasPagos - 1, a.fechaInicial) AS fechaFinalP, a.diasPagos, DATEADD(day, a.diasPagos, a.fechaInicial) AS fechaInicialNP, 
                         a.fechaFinal AS fechaFiinalNP, a.noDias - a.diasPagos AS diasNP, a.diasInicio, a.valor, a.valorPagado, a.empresa, a.concepto, UPPER(e.descripcion) AS nombreConcepto, ct.ccosto, cc.descripcion AS nombreCCosto, cc.codigo, 
                         cc.descripcion, ccm.codigo AS ccostoMayor, ccm.descripcion AS nombreCcostoMayor, a.tercero, a.observacion
FROM            dbo.nIncapacidad AS a INNER JOIN
                         dbo.nTipoIncapacidad AS b ON b.codigo = a.tipoIncapacidad AND a.empresa = b.empresa INNER JOIN
                         dbo.cTercero AS c ON c.id = a.tercero AND c.empresa = a.empresa INNER JOIN
                         dbo.nConcepto AS e ON a.empresa = e.empresa AND a.concepto = e.codigo INNER JOIN
                         dbo.nContratos AS ct ON a.empresa = ct.empresa AND a.tercero = ct.tercero AND a.contrato = ct.id INNER JOIN
                         dbo.cCentrosCosto AS cc ON ct.empresa = cc.empresa AND ct.ccosto = cc.codigo INNER JOIN
                         dbo.cCentrosCosto AS ccm ON ct.empresa = ccm.empresa AND cc.mayor = ccm.codigo LEFT OUTER JOIN
                         dbo.gDiagnostico AS d ON d.codigo = a.diagnostico AND d.empresa = a.empresa
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vContabilizacion
-- ============================================================
CREATE VIEW dbo.vContabilizacion
AS
SELECT        a.empresa, aa.tipoLiquidacion, a.año, a.mes, a.periodoContable, a.registro, a.codigoEmpleado, a.identificacionEmpleado, b.descripcion AS desEmpleado, a.tipoNomina, a.docNomina, a.contrato AS noContrato, 
                         a.claseContrato, d.descripcion, d.electivaProduccion AS sena, a.periodoNomina, 'Periodo del: ' + CONVERT(varchar(50), c.fechaInicial, 112) + ' Hasta: ' + CONVERT(varchar(50), c.fechaFinal, 112) AS desPeriodo, 
                         a.manejaLabCam, a.manejaHE, a.mCcostoNomina, e.descripcion AS desmCcostoNomina, a.aCcostoNomina, f.descripcion AS desaCcostoNomina, a.departamento, g.descripcion AS desDepartamento, 
                         a.codigoConcepto, h.descripcion AS desConcepto, a.codigoLabor, i.descripcion AS desLabor, a.cuentaContable, j.nombre AS desCuentaContable, a.mCcostoContable, k.descripcion AS desmCCostoContable, 
                         a.aCcostoContable, l.descripcion AS desaCCostoContable, a.terceroContable,
                             (SELECT        TOP (1) razonSocial
                               FROM            dbo.cTercero
                               WHERE        (a.terceroContable = codigo) AND (empresa = a.empresa) AND (codigo <> '')) AS desTerceroContable, a.debito, a.credito, a.LoteDesarrollo, ISNULL(a.tipoConcepto, '') AS tipoConcepto, 
                         CASE WHEN a.debito > 0 THEN 'D' ELSE 'C' END AS naturaleza, aa.anulado
FROM            dbo.cContabilizacion AS aa INNER JOIN
                         dbo.cContabilizacionDetalle AS a ON aa.tipo = aa.tipo AND aa.numero = a.numero AND aa.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cTercero AS b ON a.codigoEmpleado = b.id AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.nPeriodoDetalle AS c ON c.noPeriodo = a.periodoNomina AND c.año = a.año AND a.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nClaseContrato AS d ON d.codigo = a.claseContrato AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS e ON a.mCcostoNomina = e.codigo AND a.empresa = e.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS f ON a.aCcostoNomina = f.codigo AND a.empresa = f.empresa LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON a.departamento = g.codigo AND g.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS h ON a.codigoConcepto = h.codigo AND h.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aNovedad AS i ON a.codigoLabor = i.codigo AND a.empresa = i.empresa LEFT OUTER JOIN
                         dbo.cPuc AS j ON j.codigo = a.cuentaContable AND j.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS k ON k.codigo = a.mCcostoContable AND k.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS l ON l.codigo = a.aCcostoContable AND l.empresa = a.empresa AND l.auxiliar = 1 AND l.mayor = k.codigo


-- ============================================================
-- VISTA: vContabilizacionNomina
-- ============================================================
CREATE VIEW dbo.vContabilizacionNomina
AS
SELECT        a.empresa, aa.tipoLiquidacion, a.año, a.mes, a.periodoContable, a.registro, a.codigoEmpleado, a.identificacionEmpleado, b.descripcion AS desEmpleado, a.tipoNomina, a.docNomina, a.contrato AS noContrato, 
                         a.claseContrato, d.descripcion, d.electivaProduccion AS sena, a.periodoNomina, 'Periodo del: ' + CONVERT(varchar(50), c.fechaInicial, 112) + ' Hasta: ' + CONVERT(varchar(50), c.fechaFinal, 112) AS desPeriodo, 
                         a.manejaLabCam, a.manejaHE, a.mCcostoNomina, e.descripcion AS desmCcostoNomina, a.aCcostoNomina, f.descripcion AS desaCcostoNomina, a.departamento, g.descripcion AS desDepartamento, 
                         a.codigoConcepto, h.descripcion AS desConcepto, a.codigoLabor, i.descripcion AS desLabor, a.cuentaContable, j.nombre AS desCuentaContable, a.mCcostoContable, k.descripcion AS desmCCostoContable, 
                         a.aCcostoContable, l.descripcion AS desaCCostoContable, a.terceroContable, UPPER
                             ((SELECT        TOP (1) razonSocial
                                 FROM            dbo.cTercero
                                 WHERE        (a.terceroContable = codigo) AND (empresa = a.empresa) AND (codigo <> ''))) AS desTerceroContable, a.debito, a.credito, a.LoteDesarrollo, ISNULL(a.tipoConcepto, '') AS tipoConcepto, 
                         CASE WHEN a.debito > 0 THEN 'D' ELSE 'C' END AS naturaleza
FROM            dbo.cContabilizacion AS aa INNER JOIN
                         dbo.cContabilizacionDetalle AS a ON a.tipo = aa.tipo AND a.numero = aa.numero AND a.empresa = aa.empresa LEFT OUTER JOIN
                         dbo.cTercero AS b ON a.codigoEmpleado = b.id AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.nPeriodoDetalle AS c ON c.noPeriodo = a.periodoNomina AND c.año = a.año AND a.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nClaseContrato AS d ON d.codigo = a.claseContrato AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS e ON a.mCcostoNomina = e.codigo AND a.empresa = e.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS f ON a.aCcostoNomina = f.codigo AND a.empresa = f.empresa LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON a.departamento = g.codigo AND g.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS h ON a.codigoConcepto = h.codigo AND h.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aNovedad AS i ON a.codigoLabor = i.codigo AND a.empresa = i.empresa LEFT OUTER JOIN
                         dbo.cPuc AS j ON j.codigo = a.cuentaContable AND j.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS k ON k.codigo = a.mCcostoContable AND k.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS l ON l.codigo = a.aCcostoContable AND l.empresa = a.empresa AND l.auxiliar = 1 AND l.mayor = k.codigo


-- ============================================================
-- VISTA: vContratosSS
-- ============================================================

CREATE VIEW [dbo].[vContratosSS]
AS
SELECT        cc.empresa, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1)) - 3) AS identificacion, cc.tercero AS codTercero, 
                         tt.razonSocial AS nombreTercero, cc.salario AS sueldo, cc.observacion, cc.banco, cc.cuentaBancaria, cc.tipoCuenta, cc.claseContrato, cl.descripcion AS nombreClaseContrato, tt.direccion, tc.descripcion AS nombreTipoCuenta, 
                         cc.formaPago, cc.entidadPension, cc.entidadEps, cc.entidadCesantias, cc.entidadCaja, cc.entidadArp, cc.entidadSena, cc.entidadIcbf, cc.fechaIngreso, cc.fechaRetiro, dbo.nCargo.codigo AS codigoCago, 
                         dbo.nCargo.descripcion AS nombreCargo, tt.codigo, cc.salario, cc.departamento, tt.codigo AS codiTercero, cc.fechaContratoHasta, cc.terminoContrato, cc.motivoRetiro, cc.tipoContizante, cc.tipoNomina, cc.salarioAnterior, 
                         cc.auxilioTransporte, cc.id AS noContrato, d.codigo AS codCCosto, d.descripcion AS nombreCcosto, g.descripcion AS nombreDepartamento, g.codigo AS codDepto, ISNULL(h.razonSocial, '') AS nombreEPS, ISNULL(i.razonSocial, 
                         '') AS nombrePension, d.mayor, cl.electivaProduccion, cc.subTipoCotizante, cl.porcentajeSS, dbo.nParametrosTipoCotizante.salud, dbo.nParametrosTipoCotizante.pension, dbo.nParametrosTipoCotizante.fondoSolidaridad, 
                         dbo.nParametrosTipoCotizante.arp, dbo.nParametrosTipoCotizante.caja, dbo.nParametrosTipoCotizante.sena, dbo.nParametrosTipoCotizante.icbf, dbo.nCentroTrabajo.codigo AS centroTrabajo, REPLACE(UPPER(tt.descripcion), 
                         'Ñ', 'N') AS nombreTerceroPago, REPLACE(UPPER(tt.direccion), 'Ñ', 'N') AS direccionPago, cc.activo AS contratoActivo, cc.id AS contrato
FROM            dbo.nContratos AS cc INNER JOIN
                         dbo.cTercero AS tt ON cc.empresa = tt.empresa AND tt.id = cc.tercero INNER JOIN
                         dbo.cCentrosCosto AS d ON d.codigo = cc.ccosto AND d.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.nClaseContrato AS cl ON cc.empresa = cl.empresa AND cc.claseContrato = cl.codigo INNER JOIN
                         dbo.gTipoCuenta AS tc ON cc.empresa = tc.empresa AND cc.tipoCuenta = tc.codigo INNER JOIN
                         dbo.nParametrosGeneral AS nn ON nn.empresa = cc.empresa INNER JOIN
                         dbo.nCargo ON cc.empresa = dbo.nCargo.empresa AND cc.cargo = dbo.nCargo.codigo LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON g.codigo = cc.departamento AND g.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.nEntidadEps AS j ON j.codigo = cc.entidadEps AND j.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.nEntidadFondoPension AS k ON k.codigo = cc.entidadPension AND k.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.cTercero AS h ON h.id = j.tercero AND h.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.cTercero AS i ON i.id = k.tercero AND i.empresa = cc.empresa LEFT OUTER JOIN
                         dbo.nCentroTrabajo ON cc.empresa = dbo.nCentroTrabajo.empresa AND cc.centroTrabajo = dbo.nCentroTrabajo.codigo LEFT OUTER JOIN
                         dbo.nTipoCotizante ON cc.empresa = dbo.nTipoCotizante.empresa AND cc.tipoContizante = dbo.nTipoCotizante.codigo LEFT OUTER JOIN
                         dbo.nSubTipoCotizante ON cc.empresa = dbo.nSubTipoCotizante.empresa AND cc.subTipoCotizante = dbo.nSubTipoCotizante.codigo LEFT OUTER JOIN
                         dbo.nParametrosTipoCotizante ON cc.empresa = dbo.nParametrosTipoCotizante.empresa AND dbo.nTipoCotizante.codigo = dbo.nParametrosTipoCotizante.tipoCotizante AND 
                         dbo.nSubTipoCotizante.codigo = dbo.nParametrosTipoCotizante.subTipoCotizante


-- ============================================================
-- VISTA: vEntidadAfc
-- ============================================================
CREATE VIEW dbo.vEntidadAfc
AS
SELECT        dbo.nEntidadAfc.codigo, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.nEntidadAfc.codigoNacional, dbo.nEntidadAfc.tercero, dbo.cTercero.empresa, dbo.gCiudad.nombre, 
                         dbo.gCiudad.codigo AS codCiudad, dbo.cTercero.contacto, dbo.cTercero.telefono, dbo.cTercero.direccion, dbo.cTercero.email, dbo.nEntidadAfc.cuenta, dbo.nEntidadAfc.activo
FROM            dbo.nEntidadAfc INNER JOIN
                         dbo.cTercero ON dbo.nEntidadAfc.empresa = dbo.cTercero.empresa AND dbo.nEntidadAfc.tercero = dbo.cTercero.id LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadArp
-- ============================================================
CREATE VIEW dbo.vEntidadArp
AS
SELECT        dbo.nEntidadArp.codigo, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.cTercero.empresa, dbo.gCiudad.nombre, dbo.gCiudad.codigo AS codCiudad, dbo.cTercero.contacto, 
                         dbo.cTercero.telefono, dbo.cTercero.direccion, dbo.cTercero.email, dbo.nEntidadArp.codigoNacional, dbo.nEntidadArp.tercero, dbo.nEntidadArp.cuenta, dbo.nEntidadArp.activo
FROM            dbo.nEntidadArp INNER JOIN
                         dbo.cTercero ON dbo.nEntidadArp.tercero = dbo.cTercero.id AND dbo.nEntidadArp.empresa = dbo.cTercero.empresa LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadCaja
-- ============================================================
CREATE VIEW dbo.vEntidadCaja
AS
SELECT        dbo.cTercero.empresa, dbo.nEntidadCaja.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.nEntidadCaja.pAporte, dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre, 
                         dbo.nEntidadCaja.codigoNacional, dbo.nEntidadCaja.tercero, dbo.nEntidadCaja.cuenta, dbo.nEntidadCaja.activo
FROM            dbo.nEntidadCaja INNER JOIN
                         dbo.cTercero ON dbo.nEntidadCaja.empresa = dbo.cTercero.empresa AND dbo.nEntidadCaja.tercero = dbo.cTercero.id LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadEps
-- ============================================================
CREATE VIEW dbo.vEntidadEps
AS
SELECT        dbo.cTercero.empresa, dbo.nEntidadEps.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.nEntidadEps.codigoNacional, dbo.nEntidadEps.pEmpleado, 
                         dbo.nEntidadEps.pEmpleador, dbo.nEntidadEps.pInactividad, dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre, dbo.nEntidadEps.tercero, dbo.nEntidadEps.cuenta, dbo.nEntidadEps.activo
FROM            dbo.nEntidadEps INNER JOIN
                         dbo.cTercero ON dbo.nEntidadEps.empresa = dbo.cTercero.empresa AND dbo.nEntidadEps.tercero = dbo.cTercero.id LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadFondo
-- ============================================================
CREATE VIEW dbo.vEntidadFondo
AS
SELECT        dbo.cTercero.empresa, dbo.nEntidadFondo.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre AS nombreCiudad, 
                         dbo.nEntidadFondo.tipofondo, dbo.nEntidadFondo.codigoNacional, dbo.nEntidadFondo.tercero, dbo.nEntidadFondo.activo
FROM            dbo.nEntidadFondo INNER JOIN
                         dbo.cTercero ON dbo.nEntidadFondo.empresa = dbo.cTercero.empresa AND dbo.nEntidadFondo.tercero = dbo.cTercero.id LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadIcbf
-- ============================================================
CREATE VIEW dbo.vEntidadIcbf
AS
SELECT        dbo.cTercero.empresa, dbo.nEntidadIcbf.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre, 
                         dbo.nEntidadIcbf.codigoNacional, dbo.nEntidadIcbf.pAporte, dbo.nEntidadIcbf.integral, dbo.nEntidadIcbf.tercero, dbo.nEntidadIcbf.cuenta, dbo.nEntidadIcbf.activo
FROM            dbo.nEntidadIcbf INNER JOIN
                         dbo.cTercero ON dbo.nEntidadIcbf.empresa = dbo.cTercero.empresa AND dbo.nEntidadIcbf.tercero = dbo.cTercero.id INNER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadPension
-- ============================================================
CREATE VIEW dbo.vEntidadPension
AS
SELECT        dbo.cTercero.empresa, dbo.nEntidadFondoPension.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre, 
                         dbo.nEntidadFondoPension.pEmpleado, dbo.nEntidadFondoPension.codigoNacional, dbo.nEntidadFondoPension.pEmpleador, dbo.nEntidadFondoPension.pInactividad, dbo.nEntidadFondoPension.pSolidaridad, 
                         dbo.nEntidadFondoPension.tercero, dbo.nEntidadFondoPension.cuenta, dbo.nEntidadFondoPension.activo
FROM            dbo.nEntidadFondoPension INNER JOIN
                         dbo.cTercero ON dbo.nEntidadFondoPension.tercero = dbo.cTercero.id AND dbo.nEntidadFondoPension.empresa = dbo.cTercero.empresa LEFT OUTER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vEntidadSena
-- ============================================================
CREATE VIEW dbo.vEntidadSena
AS
SELECT        dbo.nEntidadSena.codigo, dbo.cTercero.id, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.nEntidadSena.codigoNacional, dbo.nEntidadSena.pAporte, dbo.nEntidadSena.integral, 
                         dbo.gCiudad.codigo AS codCiudad, dbo.gCiudad.nombre, dbo.nEntidadSena.empresa, dbo.nEntidadSena.tercero, dbo.nEntidadSena.cuenta, dbo.nEntidadSena.activo
FROM            dbo.nEntidadSena INNER JOIN
                         dbo.cTercero ON dbo.nEntidadSena.empresa = dbo.cTercero.empresa AND dbo.nEntidadSena.tercero = dbo.cTercero.id INNER JOIN
                         dbo.gCiudad ON dbo.cTercero.empresa = dbo.gCiudad.empresa AND dbo.cTercero.ciudad = dbo.gCiudad.codigo


-- ============================================================
-- VISTA: vFuncionario
-- ============================================================

CREATE VIEW [dbo].[vFuncionario]
AS
SELECT        a.empresa, a.id, a.codigo, a.tipoDocumento, a.tipo, a.nit, a.dv, a.razonSocial, a.apellido1, a.apellido2, a.nombre1, a.nombre2, a.descripcion, a.activo,
a.ciudad, a.cliente, a.proveedor, a.empleado, a.accionista, a.contratista, 
                         a.extractora, a.foto, a.contacto, a.fechaRegistro, a.telefono, a.direccion, a.barrio, a.fax, a.email, 
						 a.comercializadora, a.departamento, a.codigoEquivalencia, b.tercero, ISNULL(b.fechaIngreso, dateadd(year,-5, GETDATE())) AS fechaIngreso, 
                         b.fechaNacimiento, ISNULL(b.salario, 0) AS salario, b.conductor, b.contratista AS Expr1, b.otros, c.foto AS fotoBinaria, b.cliente AS proveedores
FROM            dbo.cTercero AS a LEFT OUTER JOIN
                         dbo.nFuncionario AS b ON b.tercero = a.id AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.gFoto AS c ON c.id = a.foto


-- ============================================================
-- VISTA: vInformacionAgronomicoSiesa
-- ============================================================
CREATE VIEW [dbo].[vInformacionAgronomicoSiesa]
AS

SELECT        TOP (100) PERCENT idTercero, codconcepto,  centroOperacion, a.ccosto, CONVERT(VARCHAR(10), fechaLabor, 112) AS fecha, 0 AS horas, 
                         valorTotalTercero, idTercero AS cedula, 1 AS idContrato, '001' AS uNegocio, nombreTercero, SUBSTRING(CONVERT(VARCHAR(10), fechaLabor, 112), 1, 6) AS periodo
,codtransaccion,numeroTransaccion,desarrollo=case when b.desarrollo=1 then 'S' else 'N' end 
,contratista,nombreConcepto,nombreGrupoLabor


FROM  dbo.vTransaccionAgronomico1 AS a
left join aLotes b on a.codLote=b.codigo

WHERE        (anulado = 0) AND (contratista = 0) and year(fechaLabor)>=2025
ORDER BY idTercero

-- ============================================================
-- VISTA: vInformacionAgronomicoSiesaTotal
-- ============================================================




CREATE VIEW [dbo].[vInformacionAgronomicoSiesaTotal]
AS

SELECT        TOP (100) PERCENT year(fechaLabor) as año, Month(fechaLabor) as mes,idTercero, codconcepto,  centroOperacion, a.ccosto, CONVERT(VARCHAR(10), fechaLabor, 112) AS fecha, 0 AS horas, 
                         valorTotalTercero, idTercero AS cedula, 1 AS idContrato, sc.unidad_negocio AS uNegocio, nombreTercero, SUBSTRING(CONVERT(VARCHAR(10), fechaLabor, 112), 1, 6) AS periodo
,codtransaccion,numeroTransaccion,desarrollo=case when b.desarrollo=1 then 'S' else 'N' end 
,a.contratista,nombreConcepto,nombreGrupoLabor, isnull(c.proveedor,'') as Temporal,isnull(razonSocial,'') as RazonTemporal
,TipoContrato=case when v.activo=1 AND v.empresa != 2 then 'Directo' else 'Temporal' end 

FROM  dbo.vTransaccionAgronomico1 AS a
left join aLotes b on a.codLote=b.codigo
left join nFuncionario c on   a.idTercero=c.codigo  and c.empresa=1
left join cTercero d on c.proveedor=d.codigo
left join [dbo].[SiesaContratos] v ON a.idTercero COLLATE DATABASE_DEFAULT = v.codigoTercero COLLATE DATABASE_DEFAULT AND v.activo = 1
left join dbo.vSiesaCentroCosto sc ON a.ccosto COLLATE DATABASE_DEFAULT = sc.codigo COLLATE DATABASE_DEFAULT
WHERE        (anulado = 0) 
-- ORDER BY idTercero


-- ============================================================
-- VISTA: vLiquidacionCesancias
-- ============================================================
CREATE VIEW dbo.vLiquidacionCesancias
AS
SELECT        a.empresa, a.tipo, a.numero, a.año, a.mes, a.fecha, a.fechaRegistro, a.usuario, a.anulado, a.cerrado, a.estado, a.observacion, a.usuarioAnulado, a.fechaAnulado, b.registro, b.noPeriodo, b.tercero, b.concepto, 
                         b.fechaInicial, b.fechaFinal, b.ccosto, b.departamento, b.cantidad, b.porcentaje, b.valorUnitario, CASE WHEN s.signo = 1 THEN b.valorTotal ELSE b.valorTotal * - 1 END AS valorTotal, b.signo, b.saldo, b.noDias, 
                         b.entidad, b.contrato, b.basePrimas, b.baseCajaCompensacion, b.baseCesantias, b.baseVacaciones, b.baseIntereses, b.baseSeguridadSocial, b.manejaRango, b.baseEmbargo, b.noPrestamo, b.cantidadR, 
                         b.valorTotalR, dbo.nContratos.banco, dbo.nContratos.cuentaBancaria, dbo.nContratos.tipoCuenta, dbo.nContratos.claseContrato, dbo.gBanco.descripcion AS nombreBanco, 
                         dbo.nClaseContrato.descripcion AS nombreCalseContrato, dbo.nContratos.codigoTercero, dbo.cTercero.id, dbo.cTercero.razonSocial AS nombreTercero, dbo.cTercero.direccion, 
                         dbo.gTipoCuenta.descripcion AS nombreTipoCuenta, dbo.nContratos.formaPago, dbo.nContratos.entidadPension, dbo.nContratos.entidadEps, dbo.nContratos.entidadCesantias, dbo.nContratos.entidadCaja, 
                         dbo.nContratos.entidadArp, dbo.nContratos.entidadSena, dbo.nContratos.entidadIcbf, dbo.nContratos.fechaIngreso, dbo.nContratos.fechaRetiro, dbo.nCargo.codigo AS codigoCago, 
                         dbo.nCargo.descripcion AS nombreCargo, dbo.cTercero.codigo, dbo.nContratos.salario, dbo.nContratos.departamento AS codDepartamento, b.fecha AS fechaLabor, s.tipoLiquidacion, 
                         dbo.nContratos.tipoContizante, dbo.nContratos.subTipoCotizante, dbo.nParametrosTipoCotizante.salud, dbo.nParametrosTipoCotizante.pension, dbo.nParametrosTipoCotizante.fondoSolidaridad, 
                         dbo.nParametrosTipoCotizante.arp, dbo.nParametrosTipoCotizante.caja, dbo.nParametrosTipoCotizante.sena, dbo.nParametrosTipoCotizante.icbf, dbo.nClaseContrato.electivaProduccion, 
                         dbo.nClaseContrato.porcentajeSS, dbo.nCentroTrabajo.codigo AS centroTrabajo
FROM            dbo.nLiquidacionNomina AS a INNER JOIN
                         dbo.nLiquidacionNominaDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.nContratos ON a.empresa = dbo.nContratos.empresa AND b.tercero = dbo.nContratos.tercero AND b.contrato = dbo.nContratos.id INNER JOIN
                         dbo.gBanco ON a.empresa = dbo.gBanco.empresa AND dbo.nContratos.banco = dbo.gBanco.codigo INNER JOIN
                         dbo.nClaseContrato ON a.empresa = dbo.nClaseContrato.empresa AND dbo.nContratos.claseContrato = dbo.nClaseContrato.codigo INNER JOIN
                         dbo.cTercero ON a.empresa = dbo.cTercero.empresa AND dbo.nContratos.tercero = dbo.cTercero.id INNER JOIN
                         dbo.gTipoCuenta ON a.empresa = dbo.gTipoCuenta.empresa AND dbo.nContratos.tipoCuenta = dbo.gTipoCuenta.codigo INNER JOIN
                         dbo.nCargo ON dbo.nContratos.empresa = dbo.nCargo.empresa AND dbo.nContratos.cargo = dbo.nCargo.codigo INNER JOIN
                         dbo.nConcepto AS s ON s.codigo = b.concepto AND s.empresa = b.empresa LEFT OUTER JOIN
                         dbo.nCentroTrabajo ON a.empresa = dbo.nCentroTrabajo.empresa AND dbo.nContratos.centroTrabajo = dbo.nCentroTrabajo.codigo LEFT OUTER JOIN
                         dbo.nTipoCotizante ON dbo.nContratos.empresa = dbo.nTipoCotizante.empresa AND dbo.nContratos.tipoContizante = dbo.nTipoCotizante.codigo LEFT OUTER JOIN
                         dbo.nSubTipoCotizante ON dbo.nContratos.empresa = dbo.nSubTipoCotizante.empresa AND dbo.nContratos.subTipoCotizante = dbo.nSubTipoCotizante.codigo LEFT OUTER JOIN
                         dbo.nParametrosTipoCotizante ON a.empresa = dbo.nParametrosTipoCotizante.empresa AND dbo.nTipoCotizante.codigo = dbo.nParametrosTipoCotizante.tipoCotizante AND 
                         dbo.nSubTipoCotizante.codigo = dbo.nParametrosTipoCotizante.subTipoCotizante


-- ============================================================
-- VISTA: vLiquidacionDefinitivaReal
-- ============================================================
CREATE VIEW dbo.vLiquidacionDefinitivaReal
AS
SELECT        a.empresa, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1)) - 3) AS identificacion, b.tercero AS codTercero, tt.descripcion, 
                         ISNULL(c.sueldo, cc.salario) AS sueldo, b.concepto AS codConcepto, s.descripcion AS Expr1, a.tipo, a.numero, a.año, a.mes, a.fecha, a.fechaRegistro, a.usuario, a.anulado, a.cerrado, a.estado, a.observacion, a.usuarioAnulado, 
                         a.fechaAnulado, b.noPeriodo, b.fechaInicial, b.fechaFinal, CASE WHEN s.noMostrar = 1 THEN b.cantidadR ELSE b.cantidad END AS cantidad, b.porcentaje, b.valorUnitario, 
                         CASE WHEN s.noMostrar = 1 THEN b.valorTotalR ELSE b.valorTotal END AS valorTotal, b.signo, b.saldo, b.noDias, b.entidad, b.contrato, b.basePrimas, b.baseCajaCompensacion, b.baseCesantias, b.baseVacaciones, 
                         b.baseIntereses, b.baseSeguridadSocial, b.manejaRango, b.baseEmbargo, b.noPrestamo, b.cantidadR, b.valorTotalR, cc.banco, cc.cuentaBancaria, cc.tipoCuenta, cc.claseContrato, bb.descripcion AS nombreBanco, 
                         cl.descripcion AS Expr2, tt.direccion, tc.descripcion AS nombreTipoCuenta, cc.formaPago, cc.entidadPension, cc.entidadEps, cc.entidadCesantias, cc.entidadCaja, cc.entidadArp, cc.entidadSena, cc.entidadIcbf, cc.fechaIngreso, 
                         cc.fechaRetiro, dbo.nCargo.codigo AS codigoCago, dbo.nCargo.descripcion AS Expr3, tt.codigo, cc.salario, cc.departamento, b.fecha AS Expr4, tt.codigo AS Expr5, s.noMostrar, s.prioridad, s.mostrarFecha, s.mostrarDetalle, 
                         b.tipoConcepto, b.desTipoConcepto, cc.fechaContratoHasta, cc.terminoContrato, cc.motivoRetiro, cc.tipoContizante, cc.tipoNomina, cc.salarioAnterior, cc.auxilioTransporte, cc.id, s.prestacionSocial, s.mostrarCantidad, 
                         d.codigo AS codCCosto, d.descripcion AS nombreCcosto, g.descripcion AS nombreDepartamento, g.codigo AS codDepto, ISNULL(c.entidadEps, cc.entidadEps) AS entidadSaludN, ISNULL(c.entidadPension, cc.entidadPension) 
                         AS entidadPensionN, ISNULL(h.razonSocial, '') AS nombreEPS, ISNULL(i.razonSocial, '') AS nombrePension, b.valorTotal AS vTotalNR, b.registro AS registroDetalleNomina, CASE WHEN b.concepto IN (np.salud, np.pension, 
                         np.fondoSolidaridad) THEN CONVERT(bit, 1) ELSE CONVERT(bit, 0) END AS validaPorcentaje, s.habilitaValorTotal
FROM            dbo.nLiquidacionNomina AS a INNER JOIN
                         dbo.nLiquidacionNominaDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.cTercero AS tt ON a.empresa = tt.empresa AND tt.id = b.tercero INNER JOIN
                         dbo.nConcepto AS s ON s.codigo = b.concepto AND s.empresa = b.empresa INNER JOIN
                         dbo.nContratos AS cc ON a.empresa = cc.empresa AND b.tercero = cc.tercero AND b.contrato = cc.id INNER JOIN
                         dbo.cCentrosCosto AS d ON d.codigo = b.ccosto AND d.empresa = a.empresa INNER JOIN
                         dbo.gBanco AS bb ON a.empresa = bb.empresa AND cc.banco = bb.codigo INNER JOIN
                         dbo.nClaseContrato AS cl ON a.empresa = cl.empresa AND cc.claseContrato = cl.codigo INNER JOIN
                         dbo.gTipoCuenta AS tc ON a.empresa = tc.empresa AND cc.tipoCuenta = tc.codigo INNER JOIN
                         dbo.nCargo ON cc.empresa = dbo.nCargo.empresa AND cc.cargo = dbo.nCargo.codigo LEFT OUTER JOIN
                         dbo.nLiquidacionNominaDatos AS c ON c.numero = a.numero AND c.empresa = a.empresa AND c.tipo = b.tipo AND c.tercero = b.tercero LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON g.codigo = b.departamento AND g.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nEntidadEps AS j ON j.codigo = c.entidadEps AND j.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nEntidadFondoPension AS k ON k.codigo = c.entidadPension AND k.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cTercero AS h ON h.id = j.tercero AND h.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cTercero AS i ON i.id = k.tercero AND i.empresa = a.empresa INNER JOIN
                         dbo.nParametrosGeneral AS np ON np.empresa = a.empresa


-- ============================================================
-- VISTA: vNovedadesNomina
-- ============================================================
CREATE VIEW dbo.vNovedadesNomina
AS
SELECT        a.empresa, a.tipo, a.numero, e.descripcion AS nombreTipoTransaccion, a.fecha, a.observacion, a.anulado, a.fechaAnulado, a.usuarioAnulado, a.usuarioRegistro, a.fechaRegistro, b.registro, b.concepto, b.empleado, b.cantidad, 
                         b.valor, b.añoInicial, b.periodoInicial, b.añoFinal, b.periodoFinal, b.detalle, b.ccosto, b.contrato, b.liquidada, b.anulado AS anuladoDetalle, c.codigo AS codigoTercero, c.descripcion AS nombreTercero, 
                         d.descripcion AS nombreConcepto
FROM            dbo.nNovedades AS a INNER JOIN
                         dbo.nNovedadesDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.cTercero AS c ON c.id = b.empleado AND c.empresa = b.empresa INNER JOIN
                         dbo.nConcepto AS d ON d.codigo = b.concepto AND d.empresa = b.empresa INNER JOIN
                         dbo.gTipoTransaccion AS e ON a.empresa = e.empresa AND a.tipo = e.codigo


-- ============================================================
-- VISTA: vPrecontabilizacionNomina
-- ============================================================
CREATE VIEW dbo.vPrecontabilizacionNomina
AS
SELECT        a.empresa, a.tipo, a.año, a.mes, a.periodoContable, a.registro, a.codigoEmpleado, a.identificacionEmpleado, b.descripcion AS desEmpleado, a.tipoNomina, a.docNomina, a.contrato AS noContrato, 
                         a.claseContrato, d.descripcion, d.electivaProduccion AS sena, a.periodoNomina, 'Periodo del: ' + CONVERT(varchar(50), c.fechaInicial, 112) + ' Hasta: ' + CONVERT(varchar(50), c.fechaFinal, 112) AS desPeriodo, 
                         a.manejaLabCam, a.manejaHE, a.mCcostoNomina, e.descripcion AS desmCcostoNomina, a.aCcostoNomina, f.descripcion AS desaCcostoNomina, a.departamento, g.descripcion AS desDepartamento, 
                         a.codigoConcepto, h.descripcion AS desConcepto, a.codigoLabor, i.descripcion AS desLabor, a.cuentaContable, j.nombre AS desCuentaContable, a.mCcostoContable, k.descripcion AS desmCCostoContable, 
                         a.aCcostoContable, l.descripcion AS desaCCostoContable, a.terceroContable, UPPER
                             ((SELECT        TOP (1) razonSocial
                                 FROM            dbo.cTercero
                                 WHERE        (a.terceroContable = codigo) AND (empresa = a.empresa) AND (codigo <> ''))) AS desTerceroContable, a.debito, a.credito, a.LoteDesarrollo, ISNULL(a.tipoConcepto, '') AS tipoConcepto, 
                         CASE WHEN a.debito > 0 THEN 'D' ELSE 'C' END AS naturaleza
FROM            dbo.cPrecontabilizacion AS a LEFT OUTER JOIN
                         dbo.cTercero AS b ON a.codigoEmpleado = b.id AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.nPeriodoDetalle AS c ON c.noPeriodo = a.periodoNomina AND c.año = a.año AND a.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nClaseContrato AS d ON d.codigo = a.claseContrato AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS e ON a.mCcostoNomina = e.codigo AND a.empresa = e.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS f ON a.aCcostoNomina = f.codigo AND a.empresa = f.empresa LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON a.departamento = g.codigo AND g.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS h ON a.codigoConcepto = h.codigo AND h.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aNovedad AS i ON a.codigoLabor = i.codigo AND a.empresa = i.empresa LEFT OUTER JOIN
                         dbo.cPuc AS j ON j.codigo = a.cuentaContable AND j.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS k ON k.codigo = a.mCcostoContable AND k.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCostoSigo AS l ON l.codigo = a.aCcostoContable AND l.empresa = a.empresa AND l.auxiliar = 1 AND l.mayor = k.codigo


-- ============================================================
-- VISTA: vProgramacionFuncionariosDias
-- ============================================================
CREATE VIEW dbo.vProgramacionFuncionariosDias
AS
SELECT        a.funcionario, CASE WHEN DATENAME(DW, a.fecha) = 'lunes' THEN ISNULL(b.cantidad, 1) ELSE 0 END AS lunes, CASE WHEN DATENAME(DW, a.fecha) = 'martes' THEN ISNULL(b.cantidad, 1) 
                         ELSE 0 END AS martes, CASE WHEN DATENAME(DW, a.fecha) = 'miércoles' THEN ISNULL(b.cantidad, 1) ELSE 0 END AS miercoles, CASE WHEN DATENAME(DW, a.fecha) = 'jueves' THEN ISNULL(b.cantidad, 1) 
                         ELSE 0 END AS jueves, CASE WHEN DATENAME(DW, a.fecha) = 'viernes' THEN ISNULL(b.cantidad, 1) ELSE 0 END AS viernes, CASE WHEN DATENAME(DW, a.fecha) = 'sábado' THEN ISNULL(b.cantidad, 1) 
                         ELSE 0 END AS sabado, CASE WHEN DATENAME(DW, a.fecha) = 'domingo' THEN ISNULL(b.cantidad, 1) ELSE 0 END AS domingo, a.turno, a.usuario, a.cuadrilla, a.estado, a.fecha, a.empresa
FROM            dbo.nProgramacion AS a LEFT OUTER JOIN
                         dbo.nHorasExtras AS b ON b.funcionario = a.funcionario AND b.fecha = a.fecha AND b.turno = a.turno AND a.empresa = b.empresa


-- ============================================================
-- VISTA: vSeguridadSocialEntidades
-- ============================================================
CREATE VIEW dbo.vSeguridadSocialEntidades
AS
/*salud*/ /*salud*/ SELECT 'Salud' concepto, aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, b.codigo nitEntidad, b.razonSocial Entidad, aa.valorSalud valor, a.empresa
FROM          nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/* pension*/ UNION
SELECT        'Pensión', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, c.codigo nitEntidad, c.razonSocial Entidad, aa.valorPension valor, a.empresa
FROM             nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/* caja*/ UNION
SELECT        'Caja de compensación', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, d.codigo nitEntidad, d.razonSocial Entidad, aa.valorCaja valor, a.empresa
FROM             nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/* ARP*/ UNION
SELECT        'Arp', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, e.codigo nitEntidad, e.razonSocial Entidad, aa.valorArp valor, a.empresa
FROM          nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/*Fondo*/ UNION
SELECT        'Fondo de solidaridad', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, c.codigo nitEntidad, c.razonSocial Entidad, isnull(aa.valorFondo, 0) + isnull(aa.valorFondoSub, 0) valor, a.empresa
FROM            nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/* ICBF*/ UNION
SELECT        'Icbf', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, g.codigo nitEntidad, g.razonSocial Entidad, valorIcbf valor, a.empresa
FROM            nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa
/* Sena*/ UNION
SELECT        'Sena', aa.año, aa.mes, aa.idTercero, a.codigoTercero, bb.descripcion, f.codigo nitEntidad, f.razonSocial Entidad, valorSena, a.empresa
FROM            nSeguridadSocial aa
		join cTercero bb on bb.id=aa.idTercero and bb.empresa=aa.empresa
		join nContratos a on a.tercero=aa.idTercero and a.empresa=aa.empresa
		left join nCentroTrabajo h on h.codigo=a.centroTrabajo and h.empresa=a.empresa
		left join vEntidadEps b on b.codigo=a.entidadEps and b.empresa=a.empresa
		left join vEntidadPension c on c.codigo=a.entidadPension and c.empresa=a.empresa
		left join vEntidadCaja d on d.codigo=a.entidadCaja and d.empresa=a.empresa
		left join vEntidadArp e on e.codigo=a.entidadArp and e.empresa=a.empresa
		left join vEntidadSena f on f.codigo=a.entidadSena and f.empresa=a.empresa
		left join vEntidadIcbf g on g.codigo=a.entidadIcbf and g.empresa=a.empresa


-- ============================================================
-- VISTA: vSeleccionLaboresProuduccion
-- ============================================================


CREATE VIEW [dbo].[vSeleccionLaboresProuduccion]
AS
SELECT   distinct     a.empresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha, a.tipo, f.descripcion AS nombreTransaccion, a.numero, a.referencia, b.finca, i.descripcion AS nombreFinca, a.remision, 
                         a.observacion, a.fechaRegistro, CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, a.fechaAnulado, b.novedad AS codLabor, b.registro AS registroLabor, b.uMedida, b.seccion, 
                         b.lote AS codLote, k.descripcion AS nombreLote, b.fecha AS fechaLabor, CASE WHEN b.signo = 2 THEN b.cantidad * - 1 ELSE b.cantidad END AS cantidadLabor, b.jornales AS jornalLabor, b.saldo AS saldoLabor, 
                         b.ejecutado, b.signo, a.anulado, k.añoSiembra, k.mesSiembra, k.palmasBrutas, k.palmasProduccion, k.hBrutas, k.hNetas, d.desCorta, d.claseLabor, k.variedad, j.descripcion AS nombreVariedad, 
                         t.descripcion AS nombrePropietario, b.racimos AS racimoLabor, d.manejaRacimo, b.sacos,
						 w.tiquete , w.fecha fechaTiquete,k.codigo numeroLote
FROM            dbo.aTransaccion AS a LEFT OUTER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
						 dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa 
						 AND c.registroNovedad = b.registro 
						 AND b.novedad =  case when b.novedad='' then '' else c.novedad end join
                         dbo.aNovedad AS d ON c.novedad = d.codigo AND d.empresa = b.empresa INNER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa INNER JOIN
                         dbo.aFinca AS i ON i.codigo = b.finca AND i.empresa = b.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.aVariedad AS j ON j.codigo = k.variedad AND j.empresa = k.empresa LEFT OUTER JOIN
                         dbo.cTercero AS t ON t.empresa = i.empresa AND t.id = i.proveedor left join
						 aTransaccionBascula w on a.tipo=w.tipo and w.numero=a.numero and a.empresa=w.empresa


-- ============================================================
-- VISTA: vSeleccionaCorteFruta
-- ============================================================
CREATE VIEW dbo.vSeleccionaCorteFruta
AS
SELECT        a.empresa, a.año, a.mes, a.tipo, a.numero, a.fecha AS fechaTransacion, b.novedad, n.descripcion AS nombreNovedad, n.uMedida, b.fecha AS fechaNovedad, c.finca, f.descripcion AS nombreFinca, c.seccion, c.lote, c.tercero, 
                         t.codigo AS identificacion, t.descripcion AS nombreTercero, d.cuadrilla, h.descripcion AS nombreCuadrilla, c.cantidad, c.jornales, c.precioLabor, c.valorTotal, c.ejecutado, c.ccosto, c.contrato, c.periodo, c.contratista, c.proveedor, 
                         e.tiquete, e.fecha AS fechaTiquete
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.empresa = a.empresa AND a.tipo = b.tipo INNER JOIN
                         dbo.aTransaccionTercero AS c ON c.empresa = b.empresa AND c.tipo = b.tipo AND c.numero = b.numero AND c.registroNovedad = b.registro INNER JOIN
                         dbo.aNovedad AS n ON n.empresa = c.empresa AND c.novedad = n.codigo INNER JOIN
                         dbo.cTercero AS t ON t.id = c.tercero AND c.empresa = t.empresa INNER JOIN
                         dbo.aFinca AS f ON f.codigo = c.finca AND f.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nCuadrillaFuncionario AS d ON d.funcionario = c.tercero AND d.empresa = c.empresa LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS e ON e.numero = a.numero AND e.tipo = a.tipo AND e.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nCuadrilla AS h ON h.codigo = d.cuadrilla AND h.empresa = d.empresa
WHERE        (n.claseLabor = 2) AND (a.anulado = 0)


-- ============================================================
-- VISTA: vSeleccionaDatosSeguridadSocialPlano
-- ============================================================
CREATE VIEW dbo.vSeleccionaDatosSeguridadSocialPlano
AS
SELECT        aa.año, aa.mes, aa.registro, aa.idTercero, aa.codigoTercero AS Identificacion, bb.descripcion AS NombreEmpleado, aa.dPension, aa.IBCpension, aa.pPension, aa.valorPension AS vPension, 
                         aa.valorFondo AS vFondo, aa.valorFondoSub AS vFondoSub, aa.dSalud, aa.IBCsalud, aa.valorSalud AS vSalud, aa.pSalud, aa.IBCarp AS IBCarl, aa.pArp, aa.valorArp, aa.dArp, aa.dCaja, aa.IBCcaja, 
                         aa.valorCaja AS vCaja, aa.pCaja, aa.valorSena AS vSena, aa.valorIcbf AS vIcbf, aa.ING, aa.RET, aa.TDE, aa.TAE, aa.TDP, aa.TAP, aa.VSP, aa.VTE, aa.VST, aa.SLN, aa.IGE, aa.LMA, aa.VAC, aa.AVP, aa.VCT, aa.IRP, 
                         aa.exoneraSalud AS ExS, aa.empresa, aa.pFondo, bb.apellido1, bb.apellido2, bb.nombre1, bb.nombre2, bb.ciudad, dbo.nTipoCotizante.codigo AS tipoCotizante, 
                         dbo.nSubTipoCotizante.codigo AS subTipoCotizante, b.nit AS Nit_Salud, b.dv AS Dv_Salud, b.codigoNacional AS CN_Salud, b.razonSocial AS Razon_Social_Salud, c.nit AS Nit_Pension, c.dv AS Dv_Pension, 
                         c.codigoNacional AS CN_Pension, c.razonSocial AS Razon_Social_Pension, d.nit AS Nit_Caja, d.dv AS Dv_Caja, d.razonSocial AS Razon_Social_Caja, d.codigoNacional AS CN_Caja, e.nit AS Nit_ARP, 
                         e.dv AS Dv_ARP, e.razonSocial AS Razon_Social_ARP, e.codigoNacional AS CN_ARP, f.nit AS Nit_Sena, f.dv AS Dv_Sena, f.razonSocial AS Razon_Social, f.codigoNacional AS CN_Sena, g.nit AS Nit_ICBF, 
                         g.dv AS Dv_ICBF, g.razonSocial AS Razon_Social_ICBF, g.codigoNacional AS CN_ICBF, dbo.gEmpresa.nit AS Nit_Aportante, dbo.gEmpresa.dv AS DV_Aportante, 
                         dbo.gEmpresa.razonSocial AS Razon_Social_Aportante, SUBSTRING(bb.ciudad, 1, 2) AS Id_Dpto_Ubi_Labora, SUBSTRING(bb.ciudad, 3, LEN(bb.ciudad)) AS Id_Cuidad_Ubi_Labora, 
                         dbo.gTipoDocumento.descripcionCorta AS Abreviatura_Tipo_Documento, aa.salario, f.pAporte AS pSena, g.pAporte AS pICBF, b.tercero AS terceroEPS, c.tercero AS terceroPension, d.tercero AS terceroCaja, 
                         e.tercero AS terceroARP, f.tercero AS terceroSena, g.tercero AS terceroICBF
FROM            dbo.nSeguridadSocial AS aa INNER JOIN
                         dbo.cTercero AS bb ON bb.id = aa.idTercero AND bb.empresa = aa.empresa INNER JOIN
                         dbo.nContratos AS a ON a.tercero = aa.idTercero AND a.empresa = aa.empresa AND a.id =
                             (SELECT        MAX(id) AS Expr1
                               FROM            dbo.nContratos AS z
                               WHERE        (a.tercero = tercero) AND (a.empresa = empresa)) INNER JOIN
                         dbo.nCentroTrabajo AS h ON h.codigo = a.centroTrabajo AND h.empresa = a.empresa INNER JOIN
                         dbo.nSubTipoCotizante ON a.empresa = dbo.nSubTipoCotizante.empresa AND a.subTipoCotizante = dbo.nSubTipoCotizante.codigo INNER JOIN
                         dbo.nTipoCotizante ON a.empresa = dbo.nTipoCotizante.empresa AND a.tipoContizante = dbo.nTipoCotizante.codigo INNER JOIN
                         dbo.gEmpresa ON bb.empresa = dbo.gEmpresa.id INNER JOIN
                         dbo.gTipoDocumento ON aa.empresa = dbo.gTipoDocumento.empresa AND bb.tipoDocumento = dbo.gTipoDocumento.codigo LEFT OUTER JOIN
                         dbo.vEntidadEps AS b ON b.codigo = a.entidadEps AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadPension AS c ON c.codigo = a.entidadPension AND c.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadCaja AS d ON d.codigo = a.entidadCaja AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadArp AS e ON e.codigo = a.entidadArp AND e.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadSena AS f ON f.codigo = a.entidadSena AND f.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadIcbf AS g ON g.codigo = a.entidadIcbf AND g.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaDatosSeguridadSocialPlano2388
-- ============================================================
CREATE VIEW dbo.vSeleccionaDatosSeguridadSocialPlano2388
AS
SELECT DISTINCT 
                         a.año, a.mes, a.registro, a.idTercero, a.codigoTercero AS Identificacion, a.dPension, a.IBCpension, a.pPension, a.valorPension AS vPension, a.valorFondo AS vFondo, a.valorFondoSub AS vFondoSub, a.dSalud, a.IBCsalud, 
                         a.valorSalud AS vSalud, a.pSalud, a.IBCarl, a.pArl, a.valorArl, a.dArl, a.dCaja, a.IBCcaja, a.valorCaja AS vCaja, a.pCaja, a.valorSena AS vSena, a.valorICBF AS vIcbf, a.ING, a.RET, a.TDE, a.TAE, a.TDP, a.TAP, a.VSP, a.VST, 
                         a.SLN, a.IGE, a.LMA, a.VAC, a.AVP, a.VCT, a.IRL, a.exoneraSalud AS ExS, a.empresa, a.pFondo, REPLACE(a.apellido1, 'Ñ', 'N') AS apellido1, REPLACE(a.apellido2, 'Ñ', 'N') AS apellido2, REPLACE(a.nombre1, 'Ñ', 'N') AS nombre1,
                          REPLACE(a.nombre2, 'Ñ', 'N') AS nombre2, a.ciudad, a.tipoCotizante, a.subTipoCotizante, b.nit AS Nit_Salud, b.dv AS Dv_Salud, b.codigoNacional AS CN_Salud, b.razonSocial AS Razon_Social_Salud, c.nit AS Nit_Pension, 
                         c.dv AS Dv_Pension, c.codigoNacional AS CN_Pension, c.razonSocial AS Razon_Social_Pension, d.nit AS Nit_Caja, d.dv AS Dv_Caja, d.razonSocial AS Razon_Social_Caja, d.codigoNacional AS CN_Caja, e.nit AS Nit_ARP, 
                         e.dv AS Dv_ARP, e.razonSocial AS Razon_Social_ARP, e.codigoNacional AS CN_ARP, f.nit AS Nit_Sena, f.dv AS Dv_Sena, f.razonSocial AS Razon_Social, f.codigoNacional AS CN_Sena, g.nit AS Nit_ICBF, g.dv AS Dv_ICBF, 
                         g.razonSocial AS Razon_Social_ICBF, g.codigoNacional AS CN_ICBF, h.nit AS Nit_Aportante, h.dv AS DV_Aportante, h.razonSocial AS Razon_Social_Aportante, a.departamento AS Id_Dpto_Ubi_Labora, SUBSTRING(a.ciudad, 3, 
                         LEN(a.ciudad)) AS Id_Cuidad_Ubi_Labora, i.descripcionCorta AS Abreviatura_Tipo_Documento, a.salario, a.pSena, a.pICBF, b.tercero AS terceroEPS, c.tercero AS terceroPension, d.tercero AS terceroCaja, e.tercero AS terceroARP,
                          f.tercero AS terceroSena, g.tercero AS terceroICBF, a.horasLaboradas, a.extranjero, a.RecidenteExterior, a.fechaRadExterior, a.fechaIngreso, a.fechaRetiro, a.fechaVSP, a.fiSLN, a.ffSLN, a.fiIGE, a.ffIGE, a.fiLMA, a.ffLMA, a.fiVAC, 
                         a.ffVAC, a.fiVCT, a.ffVCT, a.fiIRL, a.ffIRL, a.correciones, a.salarioIntegral, a.indicadorAltoRiesgo, a.cotizacionVoluntariaAfiliado, a.cotizacionVoluntariaEmpleador, a.valorRetenido, a.totalPension, a.AFPdestino, a.terceroSalud, 
                         a.valorUPC, a.noAutorizacionEG, a.noAutorizacionLMA, a.valorIncapacidad, a.valorLMA, a.saludDestino, a.terceroArl, a.claseARL, a.centroTrabajo, a.IBCCajaOtros, a.pESAP, a.valorESAP, a.pMEN, a.valorMEN, 
                         a.tipoIDcotizanteUPC, a.noIDcotizanteUPC, a.contrato, a.tipoID
FROM            dbo.nSeguridadSocialPila AS a INNER JOIN
                         dbo.gEmpresa AS h ON a.empresa = h.id INNER JOIN
                         dbo.gTipoDocumento AS i ON a.empresa = i.empresa AND a.tipoID = i.codigo LEFT OUTER JOIN
                         dbo.vEntidadEps AS b ON b.tercero = a.terceroSalud AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadPension AS c ON c.tercero = a.terceroPension AND c.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadCaja AS d ON d.tercero = a.terceroCaja AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadArp AS e ON e.tercero = a.terceroArl AND e.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadSena AS f ON f.tercero = a.terceroSena AND f.empresa = a.empresa LEFT OUTER JOIN
                         dbo.vEntidadIcbf AS g ON g.tercero = a.terceroIcbf AND g.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaDiferenciaLiquidacionTercero
-- ============================================================
CREATE VIEW dbo.vSeleccionaDiferenciaLiquidacionTercero
AS
SELECT        SUM(CASE WHEN signo = 1 THEN valorSS ELSE (- 1) * valorSS END) AS valorTotal, tercero, empresa, noContrato
FROM            dbo.vSeleccionaRealLiquidacion
GROUP BY tercero, empresa, noContrato


-- ============================================================
-- VISTA: vSeleccionaEntradasMp
-- ============================================================
CREATE VIEW dbo.vSeleccionaEntradasMp
AS
/***************************************************************************
Nombre: vSeleccionaEntradasMp
Tipo: Vista
Desarrollado: Infos Tecnologia SAS

Argumentos de entrada: 
Argumentos de salida: 

Descripción: Selecciona las entradas de materia prima por intervalo.
*****************************************************************************/ SELECT
                          1 AS orden, 'Día' AS item, YEAR(CONVERT(varchar(50), a.fechaProceso)) AS ano, CONVERT(varchar(50), CONVERT(date, a.fechaProceso)) AS intervalo, a.procedencia, a.item producto, SUM(a.pesoNeto) -SUM(a.pesoDescuento) 
                         AS pesoNeto, isnull(SUM(pesoneto) / NULLIF (dbo.fRetornaTotalFruta('D', 1, a.item, a.empresa, CONVERT(date, fechaProceso)), 0), 0) promedio, c.descripcion desProveedor, a.empresa, count(a.pesoNeto) 
                         contar
FROM            bRegistroBascula a JOIN
                         bProcedencia b ON b.codigo = a.procedencia AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.proveedor AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'EMP' AND a.pesoNeto <> 0 AND d .referencia LIKE 'FRU%'
GROUP BY YEAR(CONVERT(varchar(50), fechaProceso)), CONVERT(varchar(50), CONVERT(date, fechaProceso)), a.procedencia, item, b.proveedor, c.descripcion, a.empresa, CONVERT(date, fechaProceso)
UNION
SELECT        3 AS orden, 'Mes', YEAR(fechaProceso), CONVERT(varchar(50), MONTH(fechaProceso)), procedencia, item, SUM(a.pesoNeto) -SUM(a.pesoDescuento), isnull(SUM(pesoneto) / NULLIF (dbo.fRetornaTotalFruta('M', CONVERT(varchar(50), 
                         MONTH(fechaProceso)), a.item, a.empresa, getdate()), 0), 0), c.descripcion, a.empresa, count(a.pesoNeto) contar
FROM            bRegistroBascula a JOIN
                         bProcedencia b ON b.codigo = a.procedencia AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.proveedor AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'EMP' AND a.pesoNeto <> 0 AND d .referencia LIKE 'FRU%'
GROUP BY YEAR(fechaProceso), MONTH(fechaProceso), procedencia, item, c.descripcion, a.empresa
UNION
SELECT        4 AS orden, 'Año', YEAR(fechaProceso), CONVERT(varchar(50), YEAR(fechaProceso)), procedencia, item, SUM(pesoNeto) - sum (pesoDescuento), isnull(SUM(pesoneto) / NULLIF (dbo.fRetornaTotalFruta('A', CONVERT(varchar(50), 
                         year(fechaProceso)), a.item, a.empresa, getdate()), 0), 0) promedio, c.descripcion, a.empresa, count(a.pesoNeto) contar
FROM            bRegistroBascula a JOIN
                         bProcedencia b ON b.codigo = a.procedencia AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.proveedor AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'EMP' AND a.pesoNeto <> 0 AND d .referencia LIKE 'FRU%'
GROUP BY YEAR(fechaProceso), YEAR(fechaProceso), procedencia, item, c.descripcion, a.empresa


-- ============================================================
-- VISTA: vSeleccionaIncapacidadesAfectanARL
-- ============================================================
CREATE VIEW dbo.vSeleccionaIncapacidadesAfectanARL
AS
SELECT        a.empresa, a.tercero, a.fechaInicial, a.fechaFinal, a.noDias, a.valor, CASE WHEN b.afectaNovedadSS = 'IGE' THEN 'X' ELSE ' ' END AS IGE, CASE WHEN b.afectaNovedadSS = 'SLN' THEN 'X' ELSE ' ' END AS SLN,
                          CASE WHEN b.afectaNovedadSS = 'LMA' THEN 'X' ELSE ' ' END AS LMA, CASE WHEN b.afectaNovedadSS = 'IRP' THEN a.noDias ELSE 0 END AS IRP
FROM            dbo.nIncapacidad AS a INNER JOIN
                         dbo.nTipoIncapacidad AS b ON b.codigo = a.tipoIncapacidad AND b.empresa = a.empresa
WHERE        (a.anulado = 0) AND (b.afectaARL = 1)


-- ============================================================
-- VISTA: vSeleccionaLabores
-- ============================================================
CREATE VIEW dbo.vSeleccionaLabores
AS
SELECT        a.empresa, a.codigo AS codigoLabor, a.descripcion AS nombreLabor, a.desCorta, a.grupo AS codigoGrupo, b.descripcion AS nombreGrupo, a.uMedida, a.ciclos, 
                         CASE WHEN a.naturaleza = 1 THEN '+' WHEN a.naturaleza = 2 THEN '-' ELSE 'NA' END AS Signo, a.equivalencia, a.concepto, c.descripcion AS nombreConcepto, a.manejaLote, a.manejaSaldo, a.manejaCanal, 
                         a.manejaLinea, a.manejaPalma, a.tipoCanal, a.manejaRacimo, a.manejaJornal, a.porHaNeta, a.porHaBruta, a.porHaProduccion, a.manejaBascula, a.manejaFecha, a.manejaRango, a.añoDesde, a.añoHasta, 
                         a.activo
FROM            dbo.aNovedad AS a INNER JOIN
                         dbo.aGrupoNovedad AS b ON b.codigo = a.grupo AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS c ON c.codigo = a.concepto AND c.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aTipoCanal AS d ON d.codigo = a.tipoCanal


-- ============================================================
-- VISTA: vSeleccionaLaboresTerceroLiquida
-- ============================================================
CREATE VIEW dbo.vSeleccionaLaboresTerceroLiquida
AS
SELECT        a.fecha, a.tipo, a.numero, b.novedad AS labor, b.fecha AS fechaLabor, c.tercero, c.cantidad, c.precioLabor, d.concepto, d.empresa, d.descripcion AS desLabor, b.lote, c.jornales, b.uMedida, DATENAME(WEEKDAY, 
                         b.fecha) AS diaSemana, f.prefijo, b.signo, b.racimos, c.contrato
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.empresa = a.empresa AND b.tipo = a.tipo INNER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.empresa = b.empresa AND c.tipo = b.tipo AND c.registroNovedad = b.registro INNER JOIN
                         dbo.aNovedad AS d ON d.codigo = b.novedad AND d.empresa = b.empresa INNER JOIN
                         dbo.cTercero AS e ON e.id = c.tercero AND c.empresa = e.empresa INNER JOIN
                         dbo.gTipoTransaccion AS f ON f.empresa = a.empresa AND f.codigo = a.tipo
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vSeleccionaLiquidacion
-- ============================================================
CREATE VIEW dbo.vSeleccionaLiquidacion
AS
SELECT        a.numero, a.empresa, a.tipo, a.año, a.mes, a.fecha, a.fechaRegistro, a.usuario, a.anulado, a.cerrado, a.estado, a.observacion, b.registro, b.noPeriodo, b.tercero, b.concepto, b.fechaInicial, b.fechaFinal, b.ccosto, 
                         b.departamento, b.cantidad, b.porcentaje, b.valorUnitario, b.valorTotal, b.signo, b.saldo, b.noDias, b.entidad, b.contrato, b.tipoConcepto, b.desTipoConcepto
FROM            dbo.nLiquidacionNomina AS a INNER JOIN
                         dbo.nLiquidacionNominaDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaLiquidacionContratista
-- ============================================================
CREATE VIEW dbo.vSeleccionaLiquidacionContratista
AS
SELECT        a.empresa, a.año, a.mes, a.tipo, a.numero, b.fecha, d.codigo AS novedad, d.descripcion AS nombreNovedad, c.lote, c.cantidad, b.uMedida, c.precioLabor, c.jornales, c.valorTotal, e.tercero, SUBSTRING(CONVERT(varchar, 
                         CONVERT(money, RTRIM(f.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(f.codigo)), 1)) - 3) AS codTercero, e.descripcion AS nombreTercero, a.anulado, e.contratista, f.nit, f.dv, f.razonSocial, a.fecha AS fechaT, 
                         g.descripcion AS nombreLote, c.ccosto AS codCCosto, c.finca AS codfinca, cc.descripcion AS nombreCCosto, c.tercero AS idTercero, fi.descripcion AS nombreFinca
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero INNER JOIN
                         dbo.aTransaccionTercero AS c ON a.empresa = c.empresa AND a.tipo = c.tipo AND a.numero = c.numero AND b.registro = c.registroNovedad INNER JOIN
                         dbo.aNovedad AS d ON a.empresa = d.empresa AND c.novedad = d.codigo INNER JOIN
                         dbo.nFuncionario AS e ON a.empresa = e.empresa AND c.tercero = e.tercero INNER JOIN
                         dbo.cTercero AS f ON a.empresa = f.empresa AND CASE WHEN len(e.proveedor) > 0 THEN (e.proveedor) ELSE c.proveedor END = f.id LEFT OUTER JOIN
                         dbo.aLotes AS g ON c.empresa = g.empresa AND c.lote = g.codigo INNER JOIN
                         dbo.aFinca AS fi ON fi.empresa = c.empresa AND fi.codigo = c.finca LEFT OUTER JOIN
                         dbo.cCentrosCosto AS cc ON c.empresa = cc.empresa AND cc.codigo = c.ccosto
WHERE        (a.anulado = 0) AND (c.contratista = 1)


-- ============================================================
-- VISTA: vSeleccionaLiquidacionDefinitiva
-- ============================================================
CREATE VIEW dbo.vSeleccionaLiquidacionDefinitiva
AS
SELECT   a.empresa, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(tt.codigo)), 1)) - 3) AS identificacion, b.tercero AS codTercero, tt.razonSocial AS nombreTercero, ISNULL(c.sueldo, cc.salario) AS sueldo, b.concepto AS codConcepto, 
             s.descripcion AS nombreConcepto, a.tipo, a.numero, a.año, a.mes, a.fecha, a.fechaRegistro, a.usuario, a.anulado, a.cerrado, a.estado, a.observacion, a.usuarioAnulado, a.fechaAnulado, b.noPeriodo, b.fechaInicial, b.fechaFinal, b.cantidad, b.porcentaje, b.valorUnitario, 
             CASE WHEN s.noMostrar = 1 THEN b.valorTotalR ELSE b.valorTotal END AS valorTotal, b.signo, b.saldo, b.noDias, b.entidad, b.contrato, b.basePrimas, b.baseCajaCompensacion, b.baseCesantias, b.baseVacaciones, b.baseIntereses, b.baseSeguridadSocial, b.manejaRango, b.baseEmbargo, b.noPrestamo, b.cantidadR, 
             b.valorTotalR, cc.banco, cc.cuentaBancaria, cc.tipoCuenta, cc.claseContrato, bb.descripcion AS nombreBanco, cl.descripcion AS nombreClaseContrato, tt.direccion, tc.descripcion AS nombreTipoCuenta, cc.formaPago, cc.entidadPension, cc.entidadEps, cc.entidadCesantias, cc.entidadCaja, cc.entidadArp, cc.entidadSena, 
             cc.entidadIcbf, cc.fechaIngreso, cc.fechaRetiro, dbo.nCargo.codigo AS codigoCago, dbo.nCargo.descripcion AS nombreCargo, tt.codigo, cc.salario, cc.departamento, b.fecha AS fechaConcepto, tt.codigo AS codiTercero, s.noMostrar, s.prioridad, s.mostrarFecha, s.mostrarDetalle, b.tipoConcepto, b.desTipoConcepto, 
             cc.fechaContratoHasta, cc.terminoContrato, cc.motivoRetiro, cc.tipoContizante, cc.tipoNomina, cc.salarioAnterior, cc.auxilioTransporte, cc.id AS noContrato, s.prestacionSocial, s.mostrarCantidad, d.codigo AS codCCosto, d.descripcion AS nombreCcosto, g.descripcion AS nombreDepartamento, g.codigo AS codDepto, 
             ISNULL(h.razonSocial, '') AS nombreEPS, ISNULL(i.razonSocial, '') AS nombrePension, CASE WHEN b.concepto IN (nn.ganaDomingo, nn.pagoFestivo, nn.PrimasExtralegales) AND n.mDomingo = 1 THEN 1 ELSE 0 END AS mDomingo, p.fechaInicial AS fip, p.fechaFinal AS ffp, d.mayor, CONVERT(varchar(4), a.año) 
             + RTRIM(RIGHT('00' + RTRIM(a.mes), 2)) AS periodoUnido, dbo.fRetornaNombreMes(p.mes) AS nombreMes, s.sumaPrestacionSocial, cl.electivaProduccion, cc.subTipoCotizante, cl.porcentajeSS, s.tipoLiquidacion, dbo.nParametrosTipoCotizante.salud, dbo.nParametrosTipoCotizante.pension, 
             dbo.nParametrosTipoCotizante.fondoSolidaridad, dbo.nParametrosTipoCotizante.arp, dbo.nParametrosTipoCotizante.caja, dbo.nParametrosTipoCotizante.sena, dbo.nParametrosTipoCotizante.icbf, dbo.nCentroTrabajo.codigo AS centroTrabajo, s.descripcion, h.id AS terceroSalud, i.id AS terceroPension, b.valorTotal AS valorNP, 
             ISNULL(gc.codigo, '99') AS grupoConcepto, ISNULL(gc.descripcion, 'Otros no asociados') AS nombreGrupoConcepto, REPLACE(UPPER(tt.descripcion), 'Ñ', 'N') AS nombreTerceroPago, REPLACE(UPPER(tt.direccion), 'Ñ', 'N') AS direccionPago
FROM     dbo.nLiquidacionNomina AS a INNER JOIN
             dbo.nLiquidacionNominaDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
             dbo.cTercero AS tt ON a.empresa = tt.empresa AND tt.id = b.tercero INNER JOIN
             dbo.nConcepto AS s ON s.codigo = b.concepto AND s.empresa = b.empresa INNER JOIN
             dbo.nContratos AS cc ON a.empresa = cc.empresa AND b.tercero = cc.tercero AND b.contrato = cc.id INNER JOIN
             dbo.cCentrosCosto AS d ON d.codigo = b.ccosto AND d.empresa = a.empresa LEFT OUTER JOIN
             dbo.gBanco AS bb ON a.empresa = bb.empresa AND cc.banco = bb.codigo INNER JOIN
             dbo.nClaseContrato AS cl ON a.empresa = cl.empresa AND cc.claseContrato = cl.codigo INNER JOIN
             dbo.gTipoCuenta AS tc ON a.empresa = tc.empresa AND cc.tipoCuenta = tc.codigo INNER JOIN
             dbo.nPeriodoDetalle AS p ON p.noPeriodo = b.noPeriodo AND p.año = a.año AND p.empresa = a.empresa INNER JOIN
             dbo.nParametrosGeneral AS nn ON nn.empresa = a.empresa INNER JOIN
             dbo.nCargo ON cc.empresa = dbo.nCargo.empresa AND cc.cargo = dbo.nCargo.codigo LEFT OUTER JOIN
             dbo.nLiquidacionNominaDatos AS c ON c.numero = a.numero AND c.empresa = a.empresa AND c.tipo = b.tipo AND c.tercero = b.tercero AND c.noContrato = b.contrato LEFT OUTER JOIN
             dbo.nDepartamento AS g ON g.codigo = b.departamento AND g.empresa = a.empresa LEFT OUTER JOIN
             dbo.nEntidadEps AS j ON j.codigo = c.entidadEps AND j.empresa = a.empresa LEFT OUTER JOIN
             dbo.nEntidadFondoPension AS k ON k.codigo = c.entidadPension AND k.empresa = a.empresa LEFT OUTER JOIN
             dbo.cTercero AS h ON h.id = j.tercero AND h.empresa = a.empresa LEFT OUTER JOIN
             dbo.cTercero AS i ON i.id = k.tercero AND i.empresa = a.empresa LEFT OUTER JOIN
             dbo.nConceptosFijos AS n ON n.empresa = a.empresa AND n.año = a.año AND n.noPeriodo = b.noPeriodo AND n.centroCosto = b.ccosto LEFT OUTER JOIN
             dbo.nCentroTrabajo ON a.empresa = dbo.nCentroTrabajo.empresa AND cc.centroTrabajo = dbo.nCentroTrabajo.codigo LEFT OUTER JOIN
             dbo.nTipoCotizante ON cc.empresa = dbo.nTipoCotizante.empresa AND cc.tipoContizante = dbo.nTipoCotizante.codigo LEFT OUTER JOIN
             dbo.nSubTipoCotizante ON cc.empresa = dbo.nSubTipoCotizante.empresa AND cc.subTipoCotizante = dbo.nSubTipoCotizante.codigo LEFT OUTER JOIN
             dbo.nParametrosTipoCotizante ON a.empresa = dbo.nParametrosTipoCotizante.empresa AND dbo.nTipoCotizante.codigo = dbo.nParametrosTipoCotizante.tipoCotizante AND dbo.nSubTipoCotizante.codigo = dbo.nParametrosTipoCotizante.subTipoCotizante LEFT OUTER JOIN
             dbo.nGrupoConceptoDetalle AS ll ON ll.empresa = b.empresa AND ll.cocepto = b.concepto LEFT OUTER JOIN
             dbo.nGrupoConcepto AS gc ON gc.empresa = ll.empresa AND ll.grupo = gc.codigo


-- ============================================================
-- VISTA: vSeleccionaLiquidacionDefinitivaCont
-- ============================================================

CREATE VIEW [dbo].[vSeleccionaLiquidacionDefinitivaCont]
AS
SELECT        a.empresa, a.tipo, a.numero, a.año, a.mes, a.fecha, a.fechaRegistro, a.usuario, a.anulado, a.cerrado, a.estado, a.observacion, a.usuarioAnulado, a.fechaAnulado, b.registro, b.noPeriodo, b.tercero, b.concepto, 
                         b.fechaInicial, b.fechaFinal, b.ccosto, b.departamento, CASE WHEN s.noMostrar = 1 THEN b.cantidadR ELSE b.cantidad END AS cantidad, b.porcentaje, b.valorUnitario, 
                         CASE WHEN s.noMostrar = 1 THEN b.valorTotalR ELSE b.valorTotal END AS valorTotal, b.signo, b.saldo, b.noDias, b.entidad, b.contrato, b.basePrimas, b.baseCajaCompensacion, b.baseCesantias, 
                         b.baseVacaciones, b.baseIntereses, b.baseSeguridadSocial, b.manejaRango, b.baseEmbargo, b.noPrestamo, b.cantidadR, b.valorTotalR, dbo.nContratos.banco, dbo.nContratos.cuentaBancaria, 
                         dbo.nContratos.tipoCuenta, dbo.nContratos.claseContrato, dbo.gBanco.descripcion AS nombreBanco, dbo.nClaseContrato.descripcion AS nombreCalseContrato, dbo.nContratos.codigoTercero, dbo.cTercero.id, 
                         dbo.cTercero.razonSocial AS nombreTercero, dbo.cTercero.direccion, dbo.gTipoCuenta.descripcion AS nombreTipoCuenta, dbo.nContratos.formaPago, dbo.nContratos.entidadPension, dbo.nContratos.entidadEps, 
                         dbo.nContratos.entidadCesantias, dbo.nContratos.entidadCaja, dbo.nContratos.entidadArp, dbo.nContratos.entidadSena, dbo.nContratos.entidadIcbf, dbo.nContratos.fechaIngreso, dbo.nContratos.fechaRetiro, 
                         dbo.nCargo.codigo AS codigoCago, dbo.nCargo.descripcion AS nombreCargo, dbo.cTercero.codigo, dbo.nContratos.salario, dbo.nContratos.departamento AS codDepartamento, b.fecha AS fechaLabor, 
                         dbo.cTercero.codigo AS IdTercero, s.descripcion AS nombreConcepto, s.noMostrar, s.prioridad, s.mostrarFecha, s.mostrarDetalle, b.tipoConcepto, b.desTipoConcepto, dbo.nContratos.fechaContratoHasta, 
                         dbo.nContratos.terminoContrato, dbo.nContratos.motivoRetiro, dbo.nContratos.tipoContizante, dbo.nContratos.tipoNomina, dbo.nContratos.salarioAnterior, dbo.nContratos.auxilioTransporte, 
                         dbo.nContratos.id AS noContrato, s.prestacionSocial
FROM            dbo.nLiquidacionNomina AS a INNER JOIN
                         dbo.nLiquidacionNominaDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.nContratos ON a.empresa = dbo.nContratos.empresa AND b.tercero = dbo.nContratos.tercero AND b.contrato = dbo.nContratos.id INNER JOIN
                         dbo.gBanco ON a.empresa = dbo.gBanco.empresa AND dbo.nContratos.banco = dbo.gBanco.codigo INNER JOIN
                         dbo.nClaseContrato ON a.empresa = dbo.nClaseContrato.empresa AND dbo.nContratos.claseContrato = dbo.nClaseContrato.codigo INNER JOIN
                         dbo.cTercero ON a.empresa = dbo.cTercero.empresa AND dbo.nContratos.tercero = dbo.cTercero.id INNER JOIN
                         dbo.gTipoCuenta ON a.empresa = dbo.gTipoCuenta.empresa AND dbo.nContratos.tipoCuenta = dbo.gTipoCuenta.codigo INNER JOIN
                         dbo.nCargo ON dbo.nContratos.empresa = dbo.nCargo.empresa AND dbo.nContratos.cargo = dbo.nCargo.codigo INNER JOIN
                         dbo.nConcepto AS s ON s.codigo = b.concepto AND s.empresa = b.empresa



-- ============================================================
-- VISTA: vSeleccionaNovedades
-- ============================================================
CREATE VIEW dbo.vSeleccionaNovedades
AS
SELECT        dbo.nNovedades.empresa, dbo.nNovedades.tipo, dbo.nNovedades.numero, dbo.nNovedades.fecha, dbo.nNovedades.remision, dbo.nNovedades.observacion AS nota, dbo.nNovedades.fechaRegistro, dbo.nNovedades.anulado, 
                         dbo.nNovedades.usuarioAnulado, dbo.nNovedades.fechaAnulado, dbo.nNovedades.usuarioRegistro AS usuario, dbo.nNovedades.ccosto, dbo.nNovedadesDetalle.concepto, dbo.nNovedadesDetalle.empleado
FROM            dbo.nNovedades INNER JOIN
                         dbo.nNovedadesDetalle ON dbo.nNovedades.empresa = dbo.nNovedadesDetalle.empresa AND dbo.nNovedades.tipo = dbo.nNovedadesDetalle.tipo AND dbo.nNovedades.numero = dbo.nNovedadesDetalle.numero


-- ============================================================
-- VISTA: vSeleccionaNovedadesNomina
-- ============================================================
CREATE VIEW dbo.vSeleccionaNovedadesNomina
AS
SELECT        a.empresa, a.tipo, a.numero, a.fecha AS fechatransaccion, a.ccosto AS codccostocab, c.descripcion AS ccostocab, a.observacion, a.anulado, a.concepto AS codconceptocab, d.descripcion AS conceptocap, 
                         b.registro, b.concepto AS codconceptodet, e.descripcion AS conceptodet, b.empleado AS codempleado, f.razonSocial AS empleado, b.cantidad, b.valor, b.añoInicial, b.añoFinal, b.periodoInicial, b.periodoFinal, 
                         b.frecuencia, b.detalle, b.liquidada, b.anulado AS Expr1
FROM            dbo.nNovedades AS a INNER JOIN
                         dbo.nNovedadesDetalle AS b ON a.tipo = b.tipo AND a.numero = b.numero AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS c ON a.ccosto = c.codigo AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS d ON a.concepto = d.codigo AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nConcepto AS e ON b.concepto = e.codigo AND e.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cTercero AS f ON b.empleado = f.id AND f.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaPago
-- ============================================================
CREATE VIEW dbo.vSeleccionaPago
AS
SELECT     ROW_NUMBER() OVER (ORDER BY codTercero DESC) AS Item, banco codigoBanco, nombreBanco, codTercero AS tercero, codiTercero identificacion, upper(replace(nombreTercero, 'ñ', 'n')) AS nombreTercero, claseContrato, nombreClaseContrato, cuentaBancaria, 
CONVERT(int, sum(CASE WHEN signo = 1 THEN valortotal ELSE valorTotal * - 1 END)) valorPago, upper(direccion) AS direccion, tipocuenta tipoCuenta, nombreTipoCuenta, a.empresa, noPeriodo, año, mes, numero, formaPago, anulado, noContrato, codCCosto, b.cheque cheque, 
b.codigo codforpago, b.descripcion desformapago, 1 tipoRegistroBanco
FROM        vSeleccionaLiquidacionDefinitiva a JOIN
                  gFormaPago b ON a.formaPago = b.codigo AND a.empresa = b.empresa
GROUP BY banco, nombreBanco, codTercero, codiTercero, nombreTercero, claseContrato, nombreClaseContrato, cuentaBancaria, direccion, tipocuenta, nombreTipoCuenta, a.empresa, noPeriodo, año, mes, numero, formaPago, anulado, noContrato, codCCosto, b.cheque, 
                  b.codigo, b.descripcion


-- ============================================================
-- VISTA: vSeleccionaPagosNomina
-- ============================================================
CREATE VIEW dbo.vSeleccionaPagosNomina
AS
SELECT   b.item, b.codigoBanco, c.descripcion AS nombreBanco, b.tercero, d.codigo AS identificacion, d.descripcion AS nombreTercero, b.claseContrato, e.descripcion AS nombreCalseContrato, f.cuentaBancaria, b.valorPago, d.direccion, f.tipoCuenta, g.descripcion AS nombreTipoCuenta, a.empresa, a.periodoNomina AS noPeriodo, a.año, 
             a.mes, b.documentoNomina AS numero, b.noCheque, b.formaPago, a.anulado, a.fecha, b.otros, b.noContrato
FROM     dbo.nPagosNomina AS a INNER JOIN
             dbo.nPagosNominaDetalle AS b ON a.año = b.año AND a.mes = b.mes AND a.periodoNomina = b.periodoNomina AND a.registro = b.registro AND a.empresa = b.empresa LEFT OUTER JOIN
             dbo.gBanco AS c ON c.codigo = a.Banco AND c.empresa = b.empresa INNER JOIN
             dbo.cTercero AS d ON d.id = b.tercero AND d.empresa = b.empresa INNER JOIN
             dbo.nClaseContrato AS e ON e.codigo = b.claseContrato AND e.empresa = b.empresa INNER JOIN
             dbo.nContratos AS f ON f.tercero = b.tercero AND f.empresa = b.empresa AND f.id = b.noContrato LEFT OUTER JOIN
             dbo.gTipoCuenta AS g ON g.codigo = f.tipoCuenta AND g.empresa = f.empresa


-- ============================================================
-- VISTA: vSeleccionaPreliquidacion
-- ============================================================
CREATE VIEW dbo.vSeleccionaPreliquidacion
AS
SELECT        SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(c.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(c.codigo)), 1)) - 3) AS identificacion, c.id AS codTercero, c.razonSocial AS nombreTercero, 
                         d.codigo AS codCCosto, d.descripcion AS nombreCcosto, e.salario AS sueldo, f.descripcion AS nombreCargo, a.concepto AS codConcepto, b.descripcion AS nombreConcepto, 
                         CASE WHEN b.noMostrar = 1 THEN a.cantidadPadelma ELSE a.cantidad END AS cantidad, CASE WHEN b.noMostrar = 1 THEN a.valorPadelma ELSE a.valorTotal END AS valorConcepto, a.saldo, a.noPeriodo, a.fecha, 
                         a.fechaInical, a.fechaFinal, a.año, a.mes, CONVERT(varchar(4), a.año) + RTRIM(RIGHT('00' + RTRIM(a.mes), 2)) AS periodoUnido, DATENAME(MONTH, a.fecha) AS nombreMes, g.descripcion AS nombreDepartamento, 
                         g.codigo AS codDepto, a.signo, a.empresa, e.entidadEps, e.entidadPension, h.razonSocial AS nombreEPS, CASE WHEN e.entidadPension = '' THEN '' ELSE i.razonSocial END AS nombrePension, b.prioridad, b.mostrarFecha, 
                         b.noMostrar, b.mostrarDetalle, a.tipoConcepto, a.desTipoConcepto, b.mostrarCantidad, a.noContrato, ISNULL(n.codigo, '99') AS grupoConcepto, ISNULL(n.descripcion, 'Otros no asociados') AS nombreGrupoConcepto
FROM            dbo.tmpliquidacionNomina AS a LEFT OUTER JOIN
                         dbo.nConcepto AS b ON a.concepto = b.codigo AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.cTercero AS c ON c.id = a.tercero AND c.empresa = a.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS d ON d.codigo = a.ccosto AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS e ON e.tercero = a.tercero AND e.empresa = a.empresa AND e.id = a.noContrato LEFT OUTER JOIN
                         dbo.nCargo AS f ON f.codigo = e.cargo AND f.empresa = e.empresa LEFT OUTER JOIN
                         dbo.nDepartamento AS g ON g.codigo = e.departamento AND g.empresa = e.empresa LEFT OUTER JOIN
                         dbo.nEntidadEps AS j ON j.codigo = e.entidadEps AND j.empresa = e.empresa LEFT OUTER JOIN
                         dbo.nEntidadFondoPension AS k ON k.codigo = e.entidadPension AND k.empresa = e.empresa LEFT OUTER JOIN
                         dbo.cTercero AS h ON h.id = j.tercero AND h.empresa = e.empresa LEFT OUTER JOIN
                         dbo.cTercero AS i ON i.id = k.tercero AND i.empresa = e.empresa LEFT OUTER JOIN
                         dbo.nGrupoConceptoDetalle AS l ON l.empresa = a.empresa AND l.cocepto = a.concepto LEFT OUTER JOIN
                         dbo.nGrupoConcepto AS n ON n.empresa = l.empresa AND l.grupo = n.codigo


-- ============================================================
-- VISTA: vSeleccionaRealLiquidacion
-- ============================================================
CREATE VIEW dbo.vSeleccionaRealLiquidacion
AS
SELECT        a.empresa, a.tercero, a.ccosto, a.fecha, a.departamento, a.concepto, a.año, a.mes, a.noPeriodo, a.cantidad, a.porcentaje, a.valorUnitario, a.valorTotal, a.signo, a.saldo, a.noDias, a.fechaInical, a.fechaFinal, 
                         a.baseSeguridadSocial, a.baseEmbargos, a.entidad, a.cantidadPadelma, a.valorPadelma, CASE WHEN b.noMostrar = 1 THEN a.valorPadelma ELSE valorTotal END AS valorSS, a.tipoConcepto, 
                         a.desTipoConcepto, a.noContrato
FROM            dbo.tmpliquidacionNomina AS a INNER JOIN
                         dbo.nConcepto AS b ON b.codigo = a.concepto AND b.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaRealLiquidacionVaca
-- ============================================================
CREATE VIEW dbo.vSeleccionaRealLiquidacionVaca
AS
SELECT        a.empresa, a.tercero, a.ccosto, a.fecha, a.departamento, a.concepto, a.año, a.mes, a.noPeriodo, a.cantidad, a.porcentaje, a.valorUnitario, a.valorTotal, a.signo, a.saldo, a.noDias, a.fechaInical, a.fechaFinal, 
                         a.baseSeguridadSocial, a.baseEmbargos, a.entidad
FROM            dbo.tmpliquidacionNominaVacaciones AS a INNER JOIN
                         dbo.nConcepto AS b ON b.codigo = a.concepto AND b.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaRegistroLabores
-- ============================================================
CREATE VIEW dbo.vSeleccionaRegistroLabores
AS
SELECT        a.empresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha, a.tipo, f.descripcion AS nombreTransaccion, a.numero, a.referencia, a.finca, 
                         i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, g.descripcion AS uduarioRegistro, CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion, 
                         a.fechaAnulado, b.novedad AS codLabor, b.registro AS registroLabor, b.uMedida, b.seccion, b.lote AS codLote, k.descripcion AS nombreLote, b.fecha AS fechaLabor, 
                         CASE WHEN b.signo = 1 THEN b.cantidad ELSE b.cantidad * - 1 END AS cantidadLabor, b.jornales AS jornalLabor, b.racimos AS racimoLabor, b.saldo AS saldoLabor, b.ejecutado, b.signo, 
                         c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) AS codTercero, 
                         e.razonSocial AS nombreTercero, c.cantidad AS cantidadTercero, c.jornales AS jornalTercero, l.precioDestajo AS precioLabor, n.codigo AS codCargo, n.descripcion AS nombreCargo, o.codigo AS codCCosto, 
                         o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado, k.añoSiembra, k.mesSiembra, k.palmasBrutas, k.palmasProduccion, k.hBrutas, k.hNetas, d.desCorta, d.claseLabor
FROM            dbo.aTransaccion AS a LEFT OUTER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro LEFT OUTER JOIN
                         dbo.aNovedad AS d ON b.novedad = d.codigo AND d.empresa = b.empresa LEFT OUTER JOIN
                         dbo.cTercero AS e ON e.id = c.tercero AND e.empresa = c.empresa LEFT OUTER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro LEFT OUTER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa LEFT OUTER JOIN
                         dbo.aFinca AS i ON i.codigo = a.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = m.ccosto AND o.empresa = m.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado LEFT OUTER JOIN
                         dbo.aNovedadLotePrecio AS l ON l.novedad = b.novedad AND l.año = a.año LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS p ON p.numero = a.numero AND p.tipo = a.tipo AND p.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaRegistroNovadesNomina
-- ============================================================
CREATE VIEW dbo.vSeleccionaRegistroNovadesNomina
AS
SELECT        a.tipo, a.numero, a.fecha, a.remision, a.ccosto AS codCcosto, e.descripcion AS nombreCcosto, a.observacion, f.descripcion AS usuarioRegistro, a.fechaRegistro, g.descripcion AS usuarioAnulado, 
                         a.anulado AS anuladaTransaccion, a.fechaAnulado, b.registro, b.concepto AS cosConcepto, d.descripcion AS nombreConcepto, b.empleado AS codTrabajador, RTRIM(c.codigo) AS idTrabajador, 
                         c.descripcion AS nombreTrabajador, b.cantidad, b.valor, b.añoInicial, b.periodoInicial, b.añoFinal, b.periodoFinal, b.frecuencia, b.ultimoPeriodoLiquidado, b.ultimoPeriodoFrecuencia, b.liquidada, 
                         b.anulado AS registroAnulado, a.empresa
FROM            dbo.nNovedades AS a INNER JOIN
                         dbo.nNovedadesDetalle AS b ON b.numero = a.numero AND b.tipo = a.tipo AND a.empresa = b.empresa INNER JOIN
                         dbo.nFuncionario AS c ON c.tercero = b.empleado AND c.empresa = b.empresa INNER JOIN
                         dbo.nConcepto AS d ON d.codigo = b.concepto AND d.empresa = b.empresa INNER JOIN
                         dbo.cCentrosCosto AS e ON e.codigo = a.ccosto AND e.empresa = a.empresa INNER JOIN
                         dbo.sUsuarios AS f ON f.usuario = a.usuarioRegistro LEFT OUTER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioAnulado


-- ============================================================
-- VISTA: vSeleccionaSalidasDPT
-- ============================================================
CREATE VIEW dbo.vSeleccionaSalidasDPT
AS
/***************************************************************************
Nombre: vSeleccionaEntradasDES
Tipo: Vista
Desarrollado: Infos Tecnologia SAS

Argumentos de entrada: 
Argumentos de salida: 

Descripción: Selecciona las entradas de materia prima por intervalo.
*****************************************************************************/ SELECT
                          1 AS orden, 'Día' AS item, YEAR(CONVERT(varchar(50), a.fechaProceso)) AS ano, CONVERT(varchar(50), CONVERT(date, a.fechaProceso)) AS intervalo, c.descripcion desCliente, a.item producto, 
                         d .descripcion desProducto, SUM(a.pesoNeto) - SUM(a.pesoDescuento) AS pesoNeto, isnull(SUM(pesoneto) / NULLIF (dbo.fRetornaTotalDepachos('D', 1, a.item, a.empresa, CONVERT(date, fechaProceso)), 0), 0) 
                         promedio, a.empresa, count(a.pesoNeto) contar
FROM            bRegistroBascula a JOIN
                         logDespacho b ON b.numero = a.numero AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.cliente AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'DPT' AND a.pesoNeto <> 0
GROUP BY YEAR(CONVERT(varchar(50), fechaProceso)), CONVERT(varchar(50), CONVERT(date, fechaProceso)), c.descripcion, item, d .descripcion, c.codigo, c.descripcion, a.empresa, CONVERT(date, fechaProceso)
UNION
SELECT        3 AS orden, 'Mes', YEAR(fechaProceso), CONVERT(varchar(50), MONTH(fechaProceso)), c.descripcion, item, d .descripcion, SUM(a.pesoNeto) - SUM(a.pesoDescuento), isnull(SUM(pesoneto) 
                         / NULLIF (dbo.fRetornaTotalDepachos('M', CONVERT(varchar(50), MONTH(fechaProceso)), a.item, a.empresa, getdate()), 0), 0), a.empresa, count(a.pesoNeto) contar
FROM            bRegistroBascula a JOIN
                         logDespacho b ON b.numero = a.numero AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.cliente AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'DPT' AND a.pesoNeto <> 0
GROUP BY YEAR(fechaProceso), MONTH(fechaProceso), c.descripcion, item, d .descripcion, a.empresa
UNION
SELECT        4 AS orden, 'Año', YEAR(fechaProceso), CONVERT(varchar(50), YEAR(fechaProceso)), c.descripcion, item, d .descripcion, SUM(pesoNeto) - sum(pesoDescuento), isnull(SUM(pesoneto) 
                         / NULLIF (dbo.fRetornaTotalDepachos('A', CONVERT(varchar(50), year(fechaProceso)), a.item, a.empresa, getdate()), 0), 0) promedio, a.empresa, count(a.pesoNeto) contar
FROM            bRegistroBascula a JOIN
                         logDespacho b ON b.numero = a.numero AND b.empresa = a.empresa JOIN
                         cTercero c ON c.id = b.cliente AND c.empresa = b.empresa JOIN
                         iItems d ON d .codigo = a.item AND d .empresa = a.empresa
WHERE        a.tipo = 'DPT' AND a.pesoNeto <> 0
GROUP BY YEAR(fechaProceso), YEAR(fechaProceso), c.descripcion, item, d .descripcion, a.empresa


-- ============================================================
-- VISTA: vSeleccionaTiqueteFruta
-- ============================================================


CREATE VIEW [dbo].[vSeleccionaTiqueteFruta]
AS
SELECT distinct      a.empresa, a.año, a.mes, a.tipo, a.numero, a.fecha, a.referencia, c.finca, a.jornal, a.racimos, a.cantidad, a.precio, a.valorTotal, a.fechaFinal, a.remision, a.observacion, a.fechaRegistro, a.usuarioRegistro, a.anulado, 
                         a.usuarioAnulado, a.fechaAnulado, b.tiquete, b.pesoBruto, b.pesoTara, b.pesoNeto, b.sacos, b.racimos AS racimosTiquete, b.codigoConductor, b.nombreConductor, b.vehiculo, b.remolque, b.interno, 
                         dbo.aFinca.descripcion AS nombreFinca, b.fecha AS fechaTiquete, b.terceroExtractrora  planta
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionBascula AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa 
						 join aTransaccionNovedad AS c ON c.numero = a.numero AND c.tipo = a.tipo AND c.empresa = a.empresa 
						 INNER JOIN
                         dbo.aFinca ON a.empresa = dbo.aFinca.empresa AND c.finca = dbo.aFinca.codigo


-- ============================================================
-- VISTA: vSeleccionaTransaccionCompletaLabores
-- ============================================================
CREATE VIEW dbo.vSeleccionaTransaccionCompletaLabores
AS
SELECT        a.empresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha, a.tipo, f.descripcion AS nombreTransaccion, a.numero, a.referencia, a.finca, 
                         i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, g.descripcion AS uduarioRegistro, CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion, 
                         a.fechaAnulado, b.novedad AS codLabor, b.registro AS registroLabor, b.uMedida, b.seccion, b.lote AS codLote, k.descripcion AS nombreLote, b.fecha AS fechaLabor, 
                         CASE WHEN b.signo = 1 THEN b.cantidad ELSE b.cantidad * - 1 END AS cantidadLabor, b.jornales AS jornalLabor, b.racimos AS racimoLabor, b.saldo AS saldoLabor, b.ejecutado, b.signo, 
                         c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) AS codTercero, 
                         e.razonSocial AS nombreTercero, c.cantidad AS cantidadTercero, c.jornales AS jornalTercero, l.precioDestajo AS precioLabor, n.codigo AS codCargo, n.descripcion AS nombreCargo, o.codigo AS codCCosto, 
                         o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado
FROM            dbo.aTransaccion AS a LEFT OUTER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro LEFT OUTER JOIN
                         dbo.aNovedad AS d ON b.novedad = d.codigo AND d.empresa = b.empresa LEFT OUTER JOIN
                         dbo.cTercero AS e ON e.id = c.tercero AND e.empresa = c.empresa LEFT OUTER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro LEFT OUTER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa LEFT OUTER JOIN
                         dbo.aFinca AS i ON i.codigo = a.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa AND m.id = c.contrato LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = m.ccosto AND o.empresa = m.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado LEFT OUTER JOIN
                         dbo.aNovedadLotePrecio AS l ON l.novedad = b.novedad AND l.año = a.año LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS p ON p.numero = a.numero AND p.tipo = a.tipo AND p.empresa = a.empresa
WHERE        (p.numero IS NULL)


-- ============================================================
-- VISTA: vSeleccionaTransaccionCompletaSanidad
-- ============================================================
CREATE VIEW [dbo].[vSeleccionaTransaccionCompletaSanidad]
AS
SELECT        empresa, tipo, numero, fecha, finca, seccion, remision, nota, referencia, usuario, fechaRegistro, anulado, usuarioAnulado, fechaAnulado, ejecutado, aprobado
FROM            dbo.aSanidad



-- ============================================================
-- VISTA: vSeleccionaTransaccionFertilizante
-- ============================================================
CREATE view vSeleccionaTransaccionFertilizante
as
select a.fecha, a.tipo, a.año, a.periodo, a.observacion, a.anulado, a.observacion notas, a.empresa, a.numero, a.finca
from aTransaccion a 
where a.tipo in ('PFA','RLF')

-- ============================================================
-- VISTA: vSeleccionaTransaccionTiquete
-- ============================================================
CREATE VIEW dbo.vSeleccionaTransaccionTiquete
AS
SELECT        a.empresa, a.año, a.mes, a.tipo, a.numero, a.fecha, a.referencia, a.finca, a.jornal, a.racimos, a.cantidad, a.precio, a.valorTotal, a.fechaFinal, a.remision, a.observacion, a.fechaRegistro, a.usuarioRegistro, a.anulado, 
                         a.usuarioAnulado, a.fechaAnulado, b.novedad, b.registro, b.uMedida, b.seccion, b.lote, b.fecha AS fechaNovedad, b.cantidad AS cantidadNovedad, b.jornales, b.racimos AS Expr2, b.saldo, b.ejecutado, c.tiquete, c.pesoBruto, 
                         c.pesoTara, c.pesoNeto, c.sacos, c.racimos AS racimoBascula, c.codigoConductor, c.nombreConductor, c.vehiculo, c.remolque, c.fecha AS fechaBascula, c.interno
FROM            dbo.aTransaccion AS a LEFT OUTER JOIN
                         dbo.aTransaccionNovedad AS b ON a.numero = b.numero AND a.empresa = b.empresa AND a.tipo = b.tipo LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS c ON a.numero = c.numero AND a.tipo = a.tipo AND a.empresa = c.empresa


-- ============================================================
-- VISTA: vSeleccionaTransaccionesAgronomico
-- ============================================================
CREATE VIEW dbo.vSeleccionaTransaccionesAgronomico
AS
SELECT        a.empresa AS codEmpresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha AS fechaTransaccion, a.tipo AS codtransaccion, 
                         f.descripcion AS nombreTransaccion, a.numero AS numeroTransaccion, a.referencia, RTRIM(LTRIM(a.finca)) AS codFinca, i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, 
                         g.descripcion AS uduarioRegistro, CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion AS usuarioAnulado, a.fechaAnulado, b.novedad AS codLabor, 
                         b.registro AS registroLabor, b.uMedida, b.seccion, b.lote AS codLote, k.descripcion AS nombreLote, b.fecha AS fechaLabor, CASE WHEN b.signo = 1 THEN b.cantidad ELSE b.cantidad * - 1 END AS cantidadLabor, 
                         b.jornales AS jornalLabor, b.racimos AS racimoLabor, b.saldo AS saldoLabor, b.ejecutado, b.signo, c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, 
                         RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) AS codTercero, e.descripcion AS nombreTercero, c.cantidad AS cantidadTercero, c.jornales AS jornalTercero, 
                         c.precioLabor, n.codigo AS codCargo, n.descripcion AS nombreCargo, o.codigo AS codCCosto, o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado, d.claseLabor, l.tiquete, l.pesoNeto, 
                         l.racimos AS racimoTiquete, l.vehiculo, l.remolque, l.fecha AS fechaTiquete, c.valorTotal AS valorTotalTercero
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro INNER JOIN
                         dbo.aNovedad AS d ON b.novedad = d.codigo AND d.empresa = b.empresa INNER JOIN
                         dbo.cTercero AS e ON e.codigo = c.tercero AND e.empresa = c.empresa INNER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa INNER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro INNER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa INNER JOIN
                         dbo.aFinca AS i ON i.codigo = a.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa AND m.id = c.contrato LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = m.ccosto AND o.empresa = m.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS l ON l.numero = a.numero AND l.tipo = a.tipo AND l.empresa = a.empresa


-- ============================================================
-- VISTA: vSeleccionaVacaciones
-- ============================================================
CREATE VIEW dbo.vSeleccionaVacaciones
AS
SELECT        dbo.nVacaciones.empresa, dbo.nVacaciones.periodoInicial, dbo.nVacaciones.periodoFinal, dbo.nVacaciones.empleado, dbo.nVacaciones.registro, dbo.nVacaciones.tipo, dbo.nVacaciones.fechaSalida, 
                         dbo.nVacaciones.fechaRetorno, dbo.nVacaciones.diasCausados, dbo.nVacaciones.diasTomados, dbo.nVacaciones.diasPendientes, dbo.nVacaciones.diasPagados, dbo.nVacaciones.valorPagado, 
                         dbo.nVacaciones.valorBase, dbo.nVacaciones.anulado, dbo.nVacaciones.fechaAnulado, dbo.nVacaciones.ejecutado, dbo.nVacaciones.pagaNomina, dbo.nVacaciones.acumulada, dbo.nVacaciones.liquidada, 
                         dbo.nVacacionesDetalle.concepto, dbo.nVacacionesDetalle.cantidad, dbo.nVacacionesDetalle.porcentaje, dbo.nVacacionesDetalle.valorUnitario, dbo.nVacacionesDetalle.valorTotal, dbo.nVacacionesDetalle.signo, 
                         dbo.nVacacionesDetalle.saldo, dbo.nVacacionesDetalle.noDias, dbo.nVacaciones.año, dbo.nVacaciones.mes, dbo.nVacaciones.periodo, dbo.nVacaciones.añoPago, dbo.nVacacionesDetalle.noPrestamo
FROM            dbo.nVacaciones LEFT OUTER JOIN
                         dbo.nVacacionesDetalle ON dbo.nVacaciones.empresa = dbo.nVacacionesDetalle.empresa AND dbo.nVacaciones.periodoInicial = dbo.nVacacionesDetalle.periodoInicial AND 
                         dbo.nVacaciones.periodoFinal = dbo.nVacacionesDetalle.periodoFinal AND dbo.nVacaciones.empleado = dbo.nVacacionesDetalle.empleado AND dbo.nVacaciones.registro = dbo.nVacacionesDetalle.registro


-- ============================================================
-- VISTA: vSeleccionaVacacionesSS
-- ============================================================
CREATE VIEW dbo.vSeleccionaVacacionesSS
AS
SELECT        a.empresa, a.periodoInicial, a.periodoFinal, a.empleado, a.registro, a.tipo, a.fechaSalida AS fechaInicial, DATEADD(day, - 1, a.fechaRetorno) AS fechaFinal, a.diasCausados, a.diasTomados, a.diasPendientes, 
                         a.diasPagados, a.valorPagado, a.valorBase, a.usuario, a.fechaRegistro, a.observaciones, a.anulado, a.fechaAnulado, a.usuarioAnulado, a.ejecutado, a.pagaNomina, a.acumulada, a.liquidada, a.año, a.mes, 
                         a.periodo, a.añoPago, ISNULL(b.valorTotal, a.valorPagado) AS valorVacaciones
FROM            dbo.nVacaciones AS a LEFT OUTER JOIN
                         dbo.nVacacionesDetalle AS b ON b.empresa = a.empresa AND b.empleado = a.empleado AND b.periodoInicial = a.periodoInicial AND b.periodoFinal = a.periodoFinal AND b.registro = a.registro LEFT OUTER JOIN
                         dbo.nParametrosGeneral AS c ON c.empresa = a.empresa AND b.concepto = c.vacaciones
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vSeleccionapTransaccionLaboratorio
-- ============================================================


CREATE VIEW [dbo].[vSeleccionapTransaccionLaboratorio]
AS
SELECT        dbo.pTransaccionJerarquia.numero, dbo.pTransaccionJerarquia.tipo, dbo.pTransaccionJerarquia.fecha, dbo.pTransaccionJerarquia.fechaRegistro, 
                         dbo.pTransaccionJerarquia.usuario, dbo.pTransaccionJerarquia.observacion, dbo.pTransaccionJerarquia.anulado, dbo.pTransaccionJerarquia.usuarioAnulado, 
                         dbo.pTransaccionJerarquia.empresa, dbo.pTransaccionJerarquiaAnalisis.jerarquia, dbo.pTransaccionJerarquiaAnalisis.registro, 
                         dbo.pTransaccionJerarquiaAnalisis.analisis, dbo.pTransaccionJerarquiaAnalisis.resultado, dbo.pTransaccionJerarquiaAnalisis.prioridad, 
                         dbo.pTransaccionJerarquiaAnalisis.valor, dbo.pTransaccionJerarquia.año, dbo.pTransaccionJerarquia.mes
FROM            dbo.pTransaccionJerarquia INNER JOIN
                         dbo.pTransaccionJerarquiaAnalisis ON dbo.pTransaccionJerarquia.tipo = dbo.pTransaccionJerarquiaAnalisis.tipo



-- ============================================================
-- VISTA: vSelecionaLiquidacionContratista
-- ============================================================
CREATE VIEW dbo.vSelecionaLiquidacionContratista
AS
SELECT        dbo.aTransaccion.empresa, dbo.aTransaccion.año, dbo.aTransaccion.mes, dbo.aTransaccion.tipo, dbo.aTransaccion.numero, dbo.aTransaccionNovedad.fecha, dbo.aNovedad.codigo, dbo.aNovedad.descripcion, 
                         dbo.aTransaccionTercero.lote, dbo.aTransaccionTercero.cantidad, dbo.aTransaccionNovedad.uMedida, dbo.aTransaccionTercero.precioLabor, dbo.aTransaccionTercero.jornales, 
                         dbo.aTransaccionTercero.valorTotal, dbo.nFuncionario.tercero, dbo.nFuncionario.codigo AS identificacion, dbo.nFuncionario.descripcion AS nombreTercero, dbo.aTransaccion.anulado, 
                         dbo.nFuncionario.contratista, dbo.cTercero.nit, dbo.cTercero.dv, dbo.cTercero.razonSocial, dbo.aTransaccion.fecha AS fechaT, dbo.aLotes.descripcion AS nombreLote
FROM            dbo.aTransaccion INNER JOIN
                         dbo.aTransaccionNovedad ON dbo.aTransaccion.empresa = dbo.aTransaccionNovedad.empresa AND dbo.aTransaccion.tipo = dbo.aTransaccionNovedad.tipo AND 
                         dbo.aTransaccion.numero = dbo.aTransaccionNovedad.numero INNER JOIN
                         dbo.aTransaccionTercero ON dbo.aTransaccion.empresa = dbo.aTransaccionTercero.empresa AND dbo.aTransaccion.tipo = dbo.aTransaccionTercero.tipo AND 
                         dbo.aTransaccion.numero = dbo.aTransaccionTercero.numero AND dbo.aTransaccionNovedad.registro = dbo.aTransaccionTercero.registroNovedad INNER JOIN
                         dbo.aNovedad ON dbo.aTransaccion.empresa = dbo.aNovedad.empresa AND dbo.aTransaccionTercero.novedad = dbo.aNovedad.codigo INNER JOIN
                         dbo.nFuncionario ON dbo.aTransaccion.empresa = dbo.nFuncionario.empresa AND dbo.aTransaccionTercero.tercero = dbo.nFuncionario.tercero INNER JOIN
                         dbo.cTercero ON dbo.aTransaccion.empresa = dbo.cTercero.empresa AND dbo.nFuncionario.proveedor = dbo.cTercero.id LEFT OUTER JOIN
                         dbo.aLotes ON dbo.aTransaccionTercero.empresa = dbo.aLotes.empresa AND dbo.aTransaccionTercero.lote = dbo.aLotes.codigo
WHERE        (dbo.aTransaccion.anulado = 0) AND (dbo.nFuncionario.contratista = 1)


-- ============================================================
-- VISTA: vSiesaCentroCosto
-- ============================================================


CREATE view [dbo].[vSiesaCentroCosto] as
SELECT   f284_id_cia empresa, f284_id codigo, case when f284_id_ccosto_mayor is null then 1 else 2 end nivel,
case when f284_id_ccosto_mayor is null then null else 1 end nivelMayor,  f284_id_ccosto_mayor mayor,
f284_descripcion descripcion,f284_responsable responsable ,f284_id_grupo_ccosto grupo, 0 manejaHE, 0 manejaLC,1 activo, 
case when f284_id_ccosto_mayor is null then 0 else 1 end auxiliar, f284_id_un unidad_negocio
FROM  [LinkedServerRDSSQL].SUnoEE_AgroInvHecarse_Real.dbo.t284_co_ccosto 


-- ============================================================
-- VISTA: vSiesaConceptosNomina
-- ============================================================


CREATE view [dbo].[vSiesaConceptosNomina]
as
select 1 empresa, c0501_id codigo,c0501_descripcion descripcion,
c0501_abreviatura abreviatura,c0501_ind_naturaleza signo,
0 tipoLiquidacion,0 base,0 porcentaje,0 valor,0 valorMinimo,0 basePrimas,0 baseCajaCompensacion,0 baseCesantias,
0 baseVacaciones,0 baseIntereses,0 baseSeguridadSocial,0 controlaSaldo,
0 manejaRango,0 ingresoGravado,0 controlConcepto,1 activo,c0501_fecha_creacion fechaRegistro,
'admin' usuarioRegistro,0 validaPorcentaje,0 fijo,0 baseEmbargo,0 prioridad,
0 descuentaDomingo,0 descuentaTransporte,0 mostrarFecha,0 noMostrar,
0 mostrarDetalle,0 ausentismo,0 prestacionSocial,0 sumaPrestacionSocial,
0 mostrarCantidad,0 noMes,0 habilitaValorTotal
  from [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].dbo.w0501_conceptos

			


-- ============================================================
-- VISTA: vSiesaContratos
-- ============================================================

CREATE view [dbo].[vSiesaContratos]
as
select
c0550_id_cia empresa,
c0550_id id,
ltrim(rtrim(b.f200_id)) codigoTercero,
a.c0550_rowid_tercero  tercero,
c0550_id_cargo cargo,
c0550_id_banco banco,
c0550_id_tipos_cotizante tipoCotizante,
c0550_id_motivo_retiro motivoRetiro,
c0550_rowid_turno turno,
null departamento,
(select ltrim(rtrim(f284_id)) from [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[t284_co_ccosto] z where 
z.f284_rowid=c0550_rowid_ccosto and f284_id_cia=c0550_id_cia ) ccosto,
null tipoNomina,
c0550_rowid_tiempo_basico tiempoBasico,
null entidadPension,
null entidadEps,
null entidadCesantias,
null entidadCaja,
null entidadARP,
null entidadSena,
null entidadICBF,
c0550_fecha_ingreso fechaIngreso,
c0550_fecha_retiro fechaRetiro,
c0550_fecha_contrato_hasta fechaContratoHasta,
c0550_fecha_prima_hasta fechaPrimaHasta,
c0550_fecha_vacaciones_hasta fechaVacacionesHasta,
c0550_fecha_ult_aumento fechaUltimoAumento,
c0550_fecha_ult_vacaciones fechaUltimoVacaciones,
c0550_fecha_ult_pension fechaUltimaPension,
c0550_fecha_ult_cesantias fechaUltimaCesantias,
null fechaContratoLey50,
c0550_salario salario,
c0550_salario_anterior  salarioAnterior,
c0550_vlr_deducible_rtefte valorDeducible,
c0550_vlr_cesantias_congeladas valorCesantiasCongeladas,
c0550_vlr_cesantias_retiradas valorCesantiasRetiradas,
c0550_vlr_otros_salud valorOtrosSalud,
c0550_vlr_salud_obligatoria valorSaludObligatoria,
c0550_cantidad cantidadHoras,
c0550_porc_rtefte pretencion,
c0550_porc_tiempo_laborado pTiempoLaborado,
c0550_dias_pagados_vacaciones diasPagadosVacaciones,
c0550_nro_personas_cargo personasCargo,
c0550_cuenta_bancaria cuentaBancaria,
c0550_ind_forma_pago formaPago,
c0550_ind_regimen_laboral regimenLaboral,
c0550_ind_auxilio_transporte auxilioTraansporte,
0 procedimientoRete,
0 pactoColectivo,
null deducible,
0 otrosSalud,
c0550_ind_salario_integral salarioIntegral,
c0550_ind_tipo_cuenta tipoCuenta,
c0550_ind_clase_contrato claseContrato, 
c0550_ind_termino_contrato terminoContrato,
null foto,
null observacion,
c0550_ind_estado activo,
0 valorPregagada,
0 valorDependientes,
c0550_fecha_creacion fechaRegistro,
c0550_usuario_creacion usuario,
c0550_fecha_actualizacion fechaActualizacion,
c0550_usuario_actualizacion usuarioActualizacion,
null ley50,
c0550_rowid_centros_trabajo centroTrabajo,
0 diasContrato,
0 mSindicato,
0 mFondoEmpleado,
null entidadFondoEmpleado,
null entidadSindicato,
0 pFondoEmpleado,
0 pSindicato,
null subTipoCotizante,
null entidadSaludAdicional,
0 manejaDestajo,
null grupoLaborDestajo,
0 cantidadDestajo
from 
[LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[w0550_contratos] a 
join 
[LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[t200_mm_terceros] b on a.c0550_id_cia=b.f200_id_cia 
and a.c0550_rowid_tercero=b.f200_rowid


-- ============================================================
-- VISTA: vSiesaEmpresas
-- ============================================================

CREATE view [dbo].[vSiesaEmpresas]
as
select f010_id id, f010_nit nit, f010_dv_nit dv,
f010_razon_Social razonSocial, f010_ind_estado activo, getdate() fechaRegotro, 0 extractora,
null tercero from [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[t010_mm_companias]


-- ============================================================
-- VISTA: vSiesaFuncionarios
-- ============================================================


CREATE view [dbo].[vSiesaFuncionarios]
as
select 
a.c0540_id_cia empresa,
a.c0540_rowid_tercero tercero,
c0540_ind_sexo sexo,
RTRIM(LTRIM(b.f200_id)) codigo,
 c0540_id_sucursal_prov proveedor,
c0540_id_sucursal_cli cliente,
b.f200_razon_social descripcion,
null rh,
c0540_fecha_nacimiento fechaNacimiento,
c0540_id_ciudad_nacimiento ciudadNacimiento,
c0540_rowid_nivel_educativo nivelEducativo,
b.f200_ind_estado activo,
0 validaTurno,
0 conductor,
0 operadorlogistico,
0 extranjero,
0 declarante,
0 contratista,
0 otros,
null foto,
null pepa
from [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[w0540_empleados] a join
[LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[t200_mm_terceros] b on a.c0540_id_cia=b.f200_id_cia
and a.c0540_rowid_tercero=b.f200_rowid


-- ============================================================
-- VISTA: vSiesaGrupoCentroCosto
-- ============================================================

CREATE view [dbo].[vSiesaGrupoCentroCosto] as 
select f279_id_cia empresa,  f279_id codigo,f279_descripcion descripcion, 1 activo
FROM  [LinkedServerRDSSQL].SUnoEE_AgroInvHecarse_Real.dbo.t279_co_grupos_ccostos 


-- ============================================================
-- VISTA: vSiesaTerceros
-- ============================================================



-- vista de terceros
CREATE view [dbo].[vSiesaTerceros]
as
SELECT        f200_id_cia AS empresa, f200_rowid AS id, LTRIM(RTRIM(f200_id)) AS codigo, f200_id_tipo_ident AS tipoDocumento, f200_ind_tipo_tercero AS tipo, LTRIM(RTRIM(f200_id)) AS nit, f200_dv_nit AS dv, 
                         f200_razon_social AS razonSocial, f200_apellido1 AS apellido1, f200_apellido2 AS apellido2, CASE WHEN CHARINDEX(' ', f200_nombres) > 0 THEN SUBSTRING(f200_nombres, 1, CHARINDEX(' ', f200_nombres)) 
                         ELSE f200_nombres END AS nombre1, CASE WHEN CHARINDEX(' ', f200_nombres) > 0 THEN SUBSTRING(f200_nombres, CHARINDEX(' ', f200_nombres), len(f200_nombres)) 
                         ELSE f200_nombres END AS nombre2, f200_razon_social AS descripcion, f200_ind_estado AS activo, NULL AS ciudad, f200_ind_cliente AS cliente, f200_ind_proveedor AS proveedor, 
                         f200_ind_empleado AS empleado, 0 AS accionista, 0 AS contratistas, 0 AS extractora, NULL AS foto, NULL AS contacto, f200_ts AS fehcaRegistro, NULL AS telefono, NULL AS direccion, NULL AS barrio, NULL 
                         AS fax, NULL AS email, 0 AS comercializadora, NULL AS departamento,
						   ISNULL((SELECT      h.f753_dato_texto 
                                 FROM  [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t750_mm_movto_entidad AS g INNER JOIN
                                                         [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t753_mm_movto_entidad_columna AS h ON h.f753_rowid_movto_entidad = g.f750_rowid INNER JOIN
                                                         [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t742_mm_entidad AS k ON k.f742_rowid = h.f753_rowid_entidad AND k.f742_id_cia = h.f753_id_cia AND g.f750_rowid = c.f200_rowid_movto_entidad AND k.f742_id = '001'), '') AS codigoEquivalencia
from [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].[t200_mm_terceros]  as c


--


-- ============================================================
-- VISTA: vTransaccionAgronomico
-- ============================================================






CREATE VIEW [dbo].[vTransaccionAgronomico]
AS
SELECT DISTINCT 
                         a.empresa AS codEmpresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha AS fechaTransaccion, a.tipo AS codtransaccion, f.descripcion AS nombreTransaccion, 
                         a.numero AS numeroTransaccion, a.referencia, c.finca AS codFinca, i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, g.descripcion AS usuarioRegistro, 
                         CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion AS usuarioAnulado, a.fechaAnulado, c.novedad AS codLabor, b.registro AS registroLabor,d.uMedida, c.seccion, ISNULL(LTRIM(RTRIM(c.lote)), 'NA') AS codLote, 
                         isnull(k.descripcion,'NO APLICA') AS nombreLote, c.fechaNovedad AS fechaLabor, CASE WHEN b.signo = 2 THEN b.cantidad * - 1 ELSE b.cantidad END AS cantidadLabor, b.jornales AS jornalLabor, 
						  (select   sum(isnull(z.racimos,0)) from   dbo.aTransaccionNovedad z where z.numero = c.numero 
						  AND  z.tipo = c.tipo and z.empresa=c.empresa and 
						  (z.novedad=c.novedad or z.novedad='')
						  and c.registroNovedad=z.registro
						   ) AS racimoLabor, b.saldo AS saldoLabor, 
						    (select   sum(isnull(z.sacos,0)) from   dbo.aTransaccionNovedad z where z.numero = c.numero 
						  AND  z.tipo = c.tipo and z.empresa=c.empresa and 
						  (z.novedad=c.novedad or z.novedad='')
						  and c.registroNovedad=z.registro
						   ) AS sacos,
                         b.ejecutado, b.signo, c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) 
                         AS codTercero, e.descripcion AS nombreTercero, CASE WHEN b.signo = 2 THEN isnull(c.cantidad,0) * - 1 ELSE isnull( c.cantidad,0) END AS cantidadTercero, c.jornales AS jornalTercero, c.precioLabor, n.codigo AS codCargo, 
                         n.descripcion AS nombreCargo, o.codigo AS codCCosto, o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado, l.codigo AS codConcepto, l.descripcion AS nombreConcepto, 
                         CASE WHEN b.signo = 2 THEN c.valorTotal * - 1 ELSE c.valorTotal END AS valorTotalTercero, k.añoSiembra, k.palmasBrutas, k.palmasProduccion, x.codigo AS codGrupoLabor, x.descripcion AS nombreGrupoLabor, c.contrato, 
                         k.hBrutas, k.hNetas, c.contratista, c.periodo, c.ejecutado AS ejecutadoNomina, a.añofer, a.mesIfer, a.mesFfer, d.noPrestacional, tt.id AS idContratista, tt.codigo AS codigoContratista, tt.razonSocial AS nombreContratista, 
                         e.codigo AS codigoTercero, d.claseLabor, d.tarea AS rendimiento, d.grupo AS grupoLabor,
						  DATENAME(WEEKDAY, b.fecha) AS diaSemana, a.tipo,
						  tt.dv dvContratista,
						 ISNULL(( select top 1 z.tiquete from aTransaccionBascula  z where z.tipo=a.tipo and z.empresa=a.empresa and z.numero=a.numero), '') tiquete
, y.codigo variedad, y.descripcion nombreVariedad,
 w.pesoRacimo AS pesoRacimo, k.desarrollo, m.claseContrato, z.descripcion nombreClaseContrato
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa 
						 AND c.registroNovedad = b.registro 
						 AND b.novedad =  case when b.novedad='' then '' else c.novedad end
						 INNER JOIN
                         dbo.aNovedad AS d ON c.novedad = d.codigo AND d.empresa = b.empresa INNER JOIN
                         dbo.aGrupoNovedad AS x ON x.codigo = d.grupo AND x.empresa = d.empresa INNER JOIN
                         dbo.nConcepto AS l ON l.codigo = d.concepto AND l.empresa = d.empresa INNER JOIN
                         dbo.cTercero AS e ON e.codigo = c.tercero AND e.empresa = c.empresa INNER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa INNER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro INNER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa LEFT OUTER JOIN
                         dbo.aFinca AS i ON i.codigo = c.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa AND m.id = c.contrato LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = c.ccosto AND o.empresa = m.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado LEFT OUTER JOIN
                         dbo.cTercero AS tt ON tt.empresa = a.empresa AND ( ltrim(rtrim(tt.codigo))= ltrim(rtrim(c.proveedor)) or tt.id =c.proveedor)
						 left join aVariedad y on k.variedad=y.codigo and y.empresa=a.empresa
						 		 left join aLotePesosPeriodo w on w.lote=c.lote and w.año = year(a.fecha) and w.mes=MONTH(a.fecha) and w.empresa=a.empresa
								 left join nClaseContrato z on z.codigo=m.claseContrato and z.empresa=m.empresa











-- ============================================================
-- VISTA: vTransaccionAgronomico1
-- ============================================================








CREATE VIEW [dbo].[vTransaccionAgronomico1]
AS
SELECT DISTINCT 
                         a.empresa AS codEmpresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha AS fechaTransaccion, a.tipo AS codtransaccion, f.descripcion AS nombreTransaccion, 
                         a.numero AS numeroTransaccion, a.referencia, c.finca AS codFinca, i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, g.descripcion AS usuarioRegistro, 
                         CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion AS usuarioAnulado, a.fechaAnulado, c.novedad AS codLabor, b.registro AS registroLabor,d.uMedida, c.seccion, ISNULL(LTRIM(RTRIM(c.lote)), 'NA') AS codLote, 
                         isnull(k.descripcion,'NO APLICA') AS nombreLote, c.fechaNovedad AS fechaLabor, CASE WHEN b.signo = 2 THEN b.cantidad * - 1 ELSE b.cantidad END AS cantidadLabor, b.jornales AS jornalLabor, 
						  (select   sum(isnull(z.racimos,0)) from   dbo.aTransaccionNovedad z where z.numero = c.numero 
						  AND  z.tipo = c.tipo and z.empresa=c.empresa and 
						  (z.novedad=c.novedad or z.novedad='')
						  and c.registroNovedad=z.registro
						   ) AS racimoLabor, b.saldo AS saldoLabor, 
						    (select   sum(isnull(z.sacos,0)) from   dbo.aTransaccionNovedad z where z.numero = c.numero 
						  AND  z.tipo = c.tipo and z.empresa=c.empresa and 
						  (z.novedad=c.novedad or z.novedad='')
						  and c.registroNovedad=z.registro
						   ) AS sacos,
                         b.ejecutado, b.signo, c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) 
                         AS codTercero, e.descripcion AS nombreTercero, CASE WHEN b.signo = 2 THEN isnull(c.cantidad,0) * - 1 ELSE isnull( c.cantidad,0) END AS cantidadTercero, c.jornales AS jornalTercero, c.precioLabor, n.codigo AS codCargo, 
                         n.descripcion AS nombreCargo, o.codigo AS codCCosto, o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado, l.codigo AS codConcepto, l.descripcion AS nombreConcepto, 
                         CASE WHEN b.signo = 2 THEN c.valorTotal * - 1 ELSE c.valorTotal END AS valorTotalTercero, k.añoSiembra, k.palmasBrutas, k.palmasProduccion, x.codigo AS codGrupoLabor, x.descripcion AS nombreGrupoLabor, c.contrato, 
                         k.hBrutas, k.hNetas, c.contratista, c.periodo, c.ejecutado AS ejecutadoNomina, a.añofer, a.mesIfer, a.mesFfer, d.noPrestacional, tt.id AS idContratista, tt.codigo AS codigoContratista, tt.razonSocial AS nombreContratista, 
                         e.codigo AS codigoTercero, d.claseLabor, d.tarea AS rendimiento, d.grupo AS grupoLabor,
						  DATENAME(WEEKDAY, b.fecha) AS diaSemana, a.tipo,
						  tt.dv dvContratista,
						 ISNULL(( select top 1 z.tiquete from aTransaccionBascula  z where z.tipo=a.tipo and z.empresa=a.empresa and z.numero=a.numero), '') tiquete
, y.codigo variedad, y.descripcion nombreVariedad,
 w.pesoRacimo AS pesoRacimo, k.desarrollo, m.claseContrato, z.descripcion nombreClaseContrato
 ,i.centroOperacion,k.ccosto
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa 
						 AND c.registroNovedad = b.registro 
						 AND b.novedad =  case when b.novedad='' then '' else c.novedad end
						 INNER JOIN
                         dbo.aNovedad AS d ON c.novedad = d.codigo AND d.empresa = b.empresa INNER JOIN
                         dbo.aGrupoNovedad AS x ON x.codigo = d.grupo AND x.empresa = d.empresa INNER JOIN
                         dbo.nConcepto AS l ON l.codigo = d.concepto AND l.empresa = d.empresa INNER JOIN
                         dbo.cTercero AS e ON e.codigo = c.tercero AND e.empresa = c.empresa INNER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa INNER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro INNER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa LEFT OUTER JOIN
                         dbo.aFinca AS i ON i.codigo = c.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa AND m.id = c.contrato LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = c.ccosto AND o.empresa = m.empresa 
						 left JOIN        dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa 
						 LEFT OUTER JOIN   dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado 
						 LEFT OUTER JOIN  dbo.cTercero AS tt ON tt.empresa = a.empresa AND ( ltrim(rtrim(tt.codigo))= ltrim(rtrim(c.proveedor)) or tt.id =c.proveedor)
						 left join aVariedad y on k.variedad=y.codigo and y.empresa=a.empresa
						 left join aLotePesosPeriodo w on w.lote=c.lote and w.año = year(a.fecha) and w.mes=MONTH(a.fecha) and w.empresa=a.empresa
						left join nClaseContrato z on z.codigo=m.claseContrato and z.empresa=m.empresa











-- ============================================================
-- VISTA: vTransaccionPepa
-- ============================================================
CREATE VIEW dbo.vTransaccionPepa
AS
SELECT        b.cantidad, b.tercero, b.novedad, b.numeroReferencia AS numero, b.registroReferencia AS registro, b.tipoReferencia AS tipo, b.valorTotal, a.periodo, b.finca, b.seccion, b.lote
FROM            dbo.aTransaccionPepa AS a INNER JOIN
                         dbo.aTransaccionTerceroPepa AS b ON b.numero = a.numero AND b.empresa = a.empresa AND b.tipo = a.tipo
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vTransaccionProduccion
-- ============================================================
CREATE VIEW dbo.vTransaccionProduccion
AS
SELECT        e.razonSocial, a.tipo, a.numero, a.fecha, a.producto, a.usuario, b.registro, b.movimiento, c.descripcion AS desMovimiento, d.descripcion AS desProducto, a.empresa, b.valor, c.referencia AS refMovimiento, 
                         d.referencia AS refProducto, a.anulado
FROM            dbo.pTransaccion AS a INNER JOIN
                         dbo.pTransaccionDetalle AS b ON b.tipo = a.tipo AND b.numero = a.numero AND b.empresa = a.empresa INNER JOIN
                         dbo.iItems AS c ON c.codigo = b.movimiento AND c.empresa = b.empresa AND c.tipo = 'M' INNER JOIN
                         dbo.iItems AS d ON d.codigo = a.producto AND d.empresa = a.empresa AND d.tipo = 'P' INNER JOIN
                         dbo.gEmpresa AS e ON e.id = a.empresa
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vTransaccioneLaboresDomingo
-- ============================================================
CREATE VIEW dbo.vTransaccioneLaboresDomingo
AS
SELECT        a.fecha, c.tercero, c.novedad, a.tipo, a.numero, b.fecha AS fechaNovedad, a.empresa, c.jornales, c.cantidad, c.precioLabor, c.ejecutado, e.claseLabor, c.valorTotal, b.signo, e.manejaJornal, e.noPrestacional, c.contratista, 
                         c.periodo, a.año
FROM            dbo.aTransaccion AS a INNER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
                         dbo.aTransaccionTercero AS c ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND c.novedad = b.novedad AND c.registroNovedad = b.registro INNER JOIN
                         dbo.aNovedad AS e ON a.empresa = e.empresa AND b.novedad = e.codigo
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vTransaccionesCampoLiquidacion
-- ============================================================
CREATE VIEW dbo.vTransaccionesCampoLiquidacion
AS
SELECT   a.año, a.mes, a.fecha, c.novedad, b.uMedida, b.lote, c.registro AS registroTercero, c.registroNovedad, CASE WHEN b.signo = 2 THEN c.cantidad * - 1 ELSE c.cantidad END - SUM(ISNULL(pp.cantidad, 0)) AS cantidadTercero, c.jornales AS jornalesTercero, d.concepto, c.precioLabor, f.codigo AS idConcepto, 
             f.descripcion AS desConcepto, f.abreviatura, f.signo, f.tipoLiquidacion, f.base, f.porcentaje AS porcConcepto, f.valor, f.valorMinimo, f.basePrimas, f.baseCajaCompensacion, f.baseCesantias, f.baseVacaciones, f.baseIntereses, f.baseSeguridadSocial, f.controlaSaldo, 
             f.manejaRango AS manjaRangoConcepto, f.ingresoGravado, f.controlConcepto, f.validaPorcentaje, f.fijo, f.baseEmbargo, f.prioridad, f.descuentaDomingo, f.descuentaTransporte, a.tipo, a.numero, b.seccion, MAX(h.salario) AS salario, c.tercero, b.fecha AS fechaNovedad, a.empresa, 
             CASE WHEN d .naturaleza = 2 OR
             b.signo = 2 THEN (c.valorTotal * - 1) ELSE c.valorTotal END - SUM(ISNULL(pp.valorTotal, 0)) AS valorTotal, c.ejecutado, a.anulado, d.claseLabor, MAX(h.id) AS contrato, c.periodo
FROM     dbo.aTransaccion AS a INNER JOIN
             dbo.aTransaccionNovedad AS b ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa INNER JOIN
             dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro INNER JOIN
             dbo.aNovedad AS d ON c.novedad = d.codigo AND b.empresa = d.empresa INNER JOIN
             dbo.nConcepto AS f ON d.concepto = f.codigo AND f.empresa = d.empresa INNER JOIN
             dbo.nContratos AS h ON h.tercero = c.tercero AND h.empresa = c.empresa AND h.id = c.contrato LEFT OUTER JOIN
             dbo.vTransaccionPepa AS pp ON pp.numero = c.numero AND pp.tipo = c.tipo AND pp.registro = c.registro AND pp.novedad = c.novedad AND pp.tercero = c.tercero
WHERE   (a.anulado = 0) AND (c.contratista = 0)
GROUP BY a.año, a.mes, a.fecha, c.novedad, b.uMedida, b.lote, c.registro, c.registroNovedad, b.signo, c.cantidad, c.jornales, d.concepto, c.precioLabor, f.codigo, f.descripcion, f.abreviatura, f.signo, f.tipoLiquidacion, f.base, f.porcentaje, f.valor, f.valorMinimo, f.basePrimas, f.baseCajaCompensacion, 
             f.baseCesantias, f.baseVacaciones, f.baseIntereses, f.baseSeguridadSocial, f.controlaSaldo, f.manejaRango, f.ingresoGravado, f.controlConcepto, f.validaPorcentaje, f.fijo, f.baseEmbargo, f.prioridad, f.descuentaDomingo, f.descuentaTransporte, a.tipo, a.numero, b.seccion, c.tercero, b.fecha, a.empresa, 
             d.naturaleza, c.valorTotal, c.ejecutado, a.anulado, d.claseLabor, c.periodo


-- ============================================================
-- VISTA: vTransaccionesSanidad
-- ============================================================
/*where a.empresa=1 */
CREATE VIEW dbo.vTransaccionesSanidad
AS
SELECT        a.empresa, a.tipo, a.numero, a.fecha AS fechaT, a.finca AS codFinca, g.descripcion AS finca, a.seccion, a.remision, a.nota AS notaEncabezado, a.referencia, a.usuario, a.fechaRegistro, a.anulado, a.fechaAnulado, 
                         a.usuarioAprobado, a.fechaAprobado, aa.registro, aa.fecha AS fechaL, a.lote, aa.linea, aa.palma, aa.item AS idNovedad, h.descripcion AS Novedad, aa.uMedida, aa.cantidad, aa.detalle AS notaDetalle, 
                         aa.ejecutado, aa.usuarioEjecturado, aa.naturaleza, aa.caracteristica AS codCaracteristica, f.descripcion AS caracteristica, aa.grupoCaracteristica AS codGruCara, e.descripcion AS grupoCaracteristica
FROM            dbo.aSanidad AS a INNER JOIN
                         dbo.aSanidadDetalle AS aa ON a.numero = aa.numero AND a.tipo = aa.tipo AND a.empresa = aa.empresa INNER JOIN
                         dbo.aNovedad AS b ON aa.item = b.codigo AND a.empresa = b.empresa LEFT OUTER JOIN
                         dbo.aLotes AS c ON a.lote = c.codigo AND a.empresa = c.empresa LEFT OUTER JOIN
                         dbo.aSecciones AS d ON a.seccion = d.codigo AND d.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aGrupoCaracteristica AS e ON e.codigo = aa.grupoCaracteristica AND aa.empresa = e.empresa LEFT OUTER JOIN
                         dbo.aCaracteristica AS f ON f.codigo = aa.caracteristica AND aa.empresa = e.empresa INNER JOIN
                         dbo.aFinca AS g ON a.finca = g.codigo AND a.empresa = g.empresa INNER JOIN
                         dbo.aNovedad AS h ON aa.item = h.codigo AND aa.empresa = h.empresa


-- ============================================================
-- VISTA: vVacaciones
-- ============================================================
CREATE VIEW dbo.vVacaciones
AS
SELECT        b.concepto AS idconcepto, a.empresa, a.periodoInicial, a.periodoFinal, a.empleado, a.registro, 
                         CASE WHEN a.tipo = 1 THEN 'Disfrutada' ELSE CASE WHEN a.tipo = 2 THEN 'Compensada' ELSE CASE WHEN a.tipo = 3 THEN '7/8' ELSE '' END END END AS tipo, a.fechaSalida, a.fechaRetorno, a.diasCausados, 
                         a.diasTomados, a.diasPendientes, a.diasPagados, a.valorPagado, a.valorBase, a.usuario, a.fechaRegistro, a.observaciones, a.anulado, a.fechaAnulado, a.usuarioAnulado, a.ejecutado, a.pagaNomina, a.acumulada, a.liquidada, 
                         a.año, a.mes, a.periodo, a.añoPago, b.cantidad, b.valorUnitario, b.valorTotal, b.signo, b.saldo, b.noDias, b.entidad, b.noPrestamo, c.baseSeguridadSocial, d.descripcion, d.codigo, c.descripcion AS desconcepto, b.porcentaje, 
                         a.contrato, f.codigo AS idccosto, f.descripcion AS desccosto, g.codigo AS idccostomayor, g.descripcion AS desccostomayor
FROM            dbo.nVacaciones AS a INNER JOIN
                         dbo.nVacacionesDetalle AS b ON a.periodoInicial = b.periodoInicial AND a.periodoFinal = b.periodoFinal AND a.empleado = b.empleado AND a.empresa = b.empresa INNER JOIN
                         dbo.nConcepto AS c ON b.concepto = c.codigo AND c.empresa = a.empresa INNER JOIN
                         dbo.cTercero AS d ON d.id = a.empleado AND d.empresa = a.empresa INNER JOIN
                         dbo.nContratos AS e ON a.contrato = e.id AND a.empresa = e.empresa AND a.empleado = e.tercero INNER JOIN
                         dbo.cCentrosCosto AS f ON e.ccosto = f.codigo AND e.empresa = f.empresa INNER JOIN
                         dbo.cCentrosCosto AS g ON f.mayor = g.codigo AND f.empresa = g.empresa
WHERE        (a.anulado = 0)


-- ============================================================
-- VISTA: vValorAcumuladoPrimas
-- ============================================================
CREATE VIEW dbo.vValorAcumuladoPrimas
AS
SELECT        CASE WHEN a.concepto = f.vacaciones AND a.tipoConcepto <> 1 THEN 0 ELSE CASE WHEN b.signo = 2 THEN a.valorTotal * - 1 ELSE a.valorTotal END END AS valorTotal, a.empresa, a.tercero, a.contrato, a.año, a.noPeriodo, 
                         a.numero, a.concepto, a.tipo, b.basePrimas, CASE WHEN a.concepto = f.vacaciones AND a.tipoConcepto <> 1 THEN 0 ELSE CASE WHEN b.signo = 2 AND 
                         b.codigo <> f.suspenciones THEN a.cantidad * - 1 ELSE a.cantidad END END AS cantidad, b.sumaPrestacionSocial, b.ausentismo, b.descripcion AS nombreConcepto, b.baseCesantias
FROM            dbo.nLiquidacionNominaDetalle AS a INNER JOIN
                         dbo.nConcepto AS b ON b.codigo = a.concepto AND b.empresa = a.empresa INNER JOIN
                         dbo.nLiquidacionNomina AS e ON e.numero = a.numero AND e.tipo = a.tipo AND e.anulado = 0 AND a.empresa = e.empresa INNER JOIN
                         dbo.nParametrosGeneral AS f ON f.empresa = a.empresa


-- ============================================================
-- VISTA: v_PowerBITransporte
-- ============================================================
CREATE VIEW v_PowerBITransporte as

select año, mes, lote, SUM(cantidadnovedad) as Kilos , SUM(expr2) as Racimos from vSeleccionaTransaccionTiquete
where vehiculo is not null
group by año, mes, lote



-- ============================================================
-- VISTA: v_PowerBiActualizacion
-- ============================================================
Create view v_PowerBiActualizacion as
select GETDATE() as Hora


-- ============================================================
-- VISTA: v_PowerBiVentas
-- ============================================================


CREATE view [dbo].[v_PowerBiVentas] as

 select
            TipoIngreso= case when substring(f120_id_tipo_inv_serv,3,2) ='42' then  'No Operacional'  else   'Operacional' end ,
            Año=year(f350_fecha), Mes=month(f350_fecha),
            a.f200_id_cia as IdCia,
            NitCliente= convert(varchar(20),a.f200_id) ,
            DesCliente= a.f200_razon_social ,
            Factura= f350_id_tipo_docto  ,
            NumeroFactura = f350_consec_docto ,
            Fecha= convert(date,f350_fecha,108),
            CO= f350_id_co,
            Vendedor = f.f200_nit,
            DesVendedor= f.f200_razon_social,
            Referencia= d.f120_referencia,
            DescReferencia= d.f120_descripcion,
            UM= f470_id_unidad_medida,
            Cantidad=  f470_cant_base * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end) ,
            CostoTotal=f470_costo_prom_tot  *(case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end),
            ValorBruto=f470_vlr_bruto * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end) ,
            Descuentos=f470_vlr_dscto_linea * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end) ,
            Subtotal= (f470_vlr_bruto-f470_vlr_dscto_linea) * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end),
            Preciokilo = ((f470_vlr_bruto-f470_vlr_dscto_linea) * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end))/(f470_cant_base * (case when f350_id_clase_docto in (521,525,25,526) then -1 else 1 end))
            from
            [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t350_co_docto_contable b
            inner join  [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t200_mm_terceros a on a.f200_rowid = b.f350_rowid_tercero
            inner join [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t470_cm_movto_invent  c on  b.f350_rowid = c.f470_rowid_docto_fact
            inner join [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t121_mc_items_extensiones ie on c.f470_id_cia=ie.f121_id_cia and c.f470_rowid_item_ext=ie.f121_rowid -- traigo esta como intermediaria entre t470_cm_movto_invent y t120_mc_items
            inner join  [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t120_mc_items d on ie.f121_rowid_item=d.f120_rowid and ie.f121_id_cia=d.f120_id_cia -- Cambio c por ie para que haga el cruce y cruco por idcia también
            inner join [LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].[dbo].t200_mm_terceros f on c.f470_rowid_tercero_vend=f.f200_rowid and f.f200_id_cia=c.f470_id_cia
            --where f350_fecha>=concat(year (dateadd(mm,-1,getdate())),case when len(month(dateadd(mm,-1,getdate())))=1 then convert(varchar(2),CONCAT('0',month (dateadd(mm,-1,getdate()))) ) else convert(varchar(2),month (dateadd(mm,-1,getdate())) )   end,'01')
            where f350_fecha >= '2023-01-01';


-- ============================================================
-- VISTA: v_powerbiFertilizacion
-- ============================================================

CREATE view [dbo].[v_powerbiFertilizacion] as

select * from v_powerbiSeleccionaRegistroLabores
where novedad like '02%' and año>=2022 and tipo='RLF' and estado='Aprobado' -- and saldoLabor>0 

/*select distinct y.*, x.idTercero, x.hNetas from 
v_powerbiSeleccionaRegistroLabores x
left join (
select DISTINCT AÑO, MES, FECHA, tipo, NUMERO, codLote, racimolabor, saldolabor, racimoLabor/COUNT( distinct idtercero) as RacimosLabor,  SaldoLabor/COUNT(distinct idtercero) as SaldosLabor
from v_powerbiSeleccionaRegistroLabores
WHERE año>=2022 and novedad=''
group by AÑO, MES, FECHA, tipo, NUMERO, codLote, racimoLabor, saldoLabor
) as y on x.numero=y.numero and x.codLote=y.codLote and x.fecha=y.fecha and x.racimoLabor=y.racimoLabor and x.saldoLabor=y.saldoLabor
where x.año>=2022 and x.novedad='' -- Pued que no esté al 100% bien*/


-- ============================================================
-- VISTA: v_powerbiFincas
-- ============================================================
Create view v_powerbiFincas as
select  f.codigo as Codigo, f.descripcion as Finca, zonaGeografica as Zona, d.descripcion as Departamento,
c.nombre as Ciudad, hectareas as Hectareas
from aFinca f
left join gCiudad c on f.ciudad=c.codigo and f.empresa=c.empresa
left join gDepartamento d on c.departamento=d.codigo and f.empresa=d.empresa


-- ============================================================
-- VISTA: v_powerbiLotes
-- ============================================================
CREATE view [dbo].[v_powerbiLotes] as
select codigo as CodigoLote, finca as Finca, palmasBrutas AS Palmas, palmasProduccion as PalmasProducción, descripcion as Lote  , hBrutas as HectBrutas, hNetas as HectNetas
from aLotes


-- ============================================================
-- VISTA: v_powerbiLotesDetalle
-- ============================================================

Create view v_powerbiLotesDetalle as
select l.codigo as CodigoLote, ld.linea as Linea, ld.noPalma as Palmas, ld. palmaErradicada as PalmasErradicadas,  l.añoSiembra as AñoSiembre, l.mesSiembra as MesSiembra, v.descripcion as Variedad
from aLotes l
left join aLotesDetalle ld on l.codigo=ld.lote
left join aVariedad v on l.variedad = v.codigo


-- ============================================================
-- VISTA: v_powerbiProduccion
-- ============================================================

CREATE view [dbo].[v_powerbiProduccion] as

select * from v_powerbiSeleccionaRegistroLabores
where novedad='' and saldoLabor>0 and año>=2022

/*select distinct y.*, x.idTercero, x.hNetas from 
v_powerbiSeleccionaRegistroLabores x
left join (
select DISTINCT AÑO, MES, FECHA, tipo, NUMERO, codLote, racimolabor, saldolabor, racimoLabor/COUNT( distinct idtercero) as RacimosLabor,  SaldoLabor/COUNT(distinct idtercero) as SaldosLabor
from v_powerbiSeleccionaRegistroLabores
WHERE año>=2022 and novedad=''
group by AÑO, MES, FECHA, tipo, NUMERO, codLote, racimoLabor, saldoLabor
) as y on x.numero=y.numero and x.codLote=y.codLote and x.fecha=y.fecha and x.racimoLabor=y.racimoLabor and x.saldoLabor=y.saldoLabor
where x.año>=2022 and x.novedad='' -- Pued que no esté al 100% bien*/


-- ============================================================
-- VISTA: v_powerbiSeleccionaRegistroLabores
-- ============================================================

CREATE view [dbo].[v_powerbiSeleccionaRegistroLabores] 
AS
SELECT        a.empresa, h.razonSocial AS nombreEmpresa, a.año, a.mes, DATENAME(MONTH, a.fecha) AS nombreMes, a.fecha, a.tipo, f.descripcion AS nombreTransaccion, a.numero, a.referencia, a.finca, b.novedad,
                         i.descripcion AS nombreFinca, a.remision, a.observacion, a.fechaRegistro, g.descripcion AS uduarioRegistro, CASE WHEN a.anulado = 0 THEN 'Aprobado' ELSE 'Anulado' END AS estado, j.descripcion, 
                         a.fechaAnulado, b.novedad AS codLabor, b.registro AS registroLabor, b.uMedida, b.seccion, b.lote AS codLote, k.descripcion AS nombreLote, b.fecha AS fechaLabor, 
                         CASE WHEN b.signo = 1 THEN b.cantidad ELSE b.cantidad * - 1 END AS cantidadLabor, b.jornales AS jornalLabor, b.racimos AS racimoLabor, b.saldo AS saldoLabor, b.ejecutado, b.signo, 
                         c.registro AS registroTercero, c.tercero AS idTercero, SUBSTRING(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1), 1, LEN(CONVERT(varchar, CONVERT(money, RTRIM(e.codigo)), 1)) - 3) AS codTercero, 
                         e.razonSocial AS nombreTercero, c.cantidad AS cantidadTercero, c.jornales AS jornalTercero, l.precioDestajo AS precioLabor, n.codigo AS codCargo, n.descripcion AS nombreCargo, o.codigo AS codCCosto, 
                         o.descripcion AS nombreCCosto, d.descripcion AS nombreLabor, a.anulado, k.añoSiembra, k.mesSiembra, k.palmasBrutas, k.palmasProduccion, k.hBrutas, k.hNetas, d.desCorta, d.claseLabor, c.precioLabor as PrecioLabor1
FROM            dbo.aTransaccion AS a LEFT OUTER JOIN
                         dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa LEFT OUTER JOIN
                         dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro LEFT OUTER JOIN
                         dbo.aNovedad AS d ON b.novedad = d.codigo AND d.empresa = b.empresa LEFT OUTER JOIN
                         dbo.cTercero AS e ON e.id = c.tercero AND e.empresa = c.empresa LEFT OUTER JOIN
                         dbo.gTipoTransaccion AS f ON f.codigo = a.tipo AND f.empresa = a.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS g ON g.usuario = a.usuarioRegistro LEFT OUTER JOIN
                         dbo.gEmpresa AS h ON h.id = a.empresa LEFT OUTER JOIN
                         dbo.aFinca AS i ON i.codigo = a.finca AND i.empresa = a.empresa LEFT OUTER JOIN
                         dbo.nContratos AS m ON m.tercero = c.tercero AND m.empresa = c.empresa LEFT OUTER JOIN
                         dbo.nCargo AS n ON n.codigo = m.cargo AND n.empresa = m.empresa LEFT OUTER JOIN
                         dbo.cCentrosCosto AS o ON o.codigo = m.ccosto AND o.empresa = m.empresa LEFT OUTER JOIN
                         dbo.aLotes AS k ON k.codigo = b.lote AND k.empresa = b.empresa LEFT OUTER JOIN
                         dbo.sUsuarios AS j ON j.usuario = a.usuarioAnulado LEFT OUTER JOIN
                         dbo.aNovedadLotePrecio AS l ON l.novedad = b.novedad AND l.año = a.año LEFT OUTER JOIN
                         dbo.aTransaccionBascula AS p ON p.numero = a.numero AND p.tipo = a.tipo AND p.empresa = a.empresa


-- ============================================================
-- VISTA: v_powerbiTerceros
-- ============================================================
Create view v_powerbiTerceros as
select nit as Documento, razonSocial from cTercero
where empleado=1


