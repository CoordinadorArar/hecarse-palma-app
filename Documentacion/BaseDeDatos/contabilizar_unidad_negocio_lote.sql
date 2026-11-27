/* ============================================================================
   Contabilización: unidad de negocio tomada del lote
   ----------------------------------------------------------------------------
   Ejecutar DESPUÉS del COMMIT de reorganizar_fincas_unidades_negocio.sql.

   QUÉ HACE
   --------
     Altera dbo.vInformacionAgronomicoSiesaTotal para que la columna uNegocio
     (unidad de negocio que va a Siesa) salga de aLotes.unidadNegocio y, si el
     lote no tiene unidad, del centro de costo (vSiesaCentroCosto) como hasta
     ahora. Además filtra el join con vSiesaCentroCosto por empresa
     (sc.empresa = a.codEmpresa): esa vista trae los centros de costo de
     varias empresas y duplicaba filas. El resto de la vista queda igual.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Reversión al final (comentada): restaura sc.unidad_negocio.
   ============================================================================ */

ALTER VIEW [dbo].[vInformacionAgronomicoSiesaTotal]
AS

SELECT        TOP (100) PERCENT year(fechaLabor) as año, Month(fechaLabor) as mes,idTercero, codconcepto,  centroOperacion, a.ccosto, CONVERT(VARCHAR(10), fechaLabor, 112) AS fecha, 0 AS horas,
                         valorTotalTercero, idTercero AS cedula, 1 AS idContrato, ISNULL(b.unidadNegocio, sc.unidad_negocio COLLATE DATABASE_DEFAULT) AS uNegocio, nombreTercero, SUBSTRING(CONVERT(VARCHAR(10), fechaLabor, 112), 1, 6) AS periodo
,codtransaccion,numeroTransaccion,desarrollo=case when b.desarrollo=1 then 'S' else 'N' end
,a.contratista,nombreConcepto,nombreGrupoLabor, isnull(c.proveedor,'') as Temporal,isnull(razonSocial,'') as RazonTemporal
,TipoContrato=case when v.activo=1 AND v.empresa != 2 then 'Directo' else 'Temporal' end

FROM  dbo.vTransaccionAgronomico1 AS a
left join aLotes b on a.codLote=b.codigo
left join nFuncionario c on   a.idTercero=c.codigo  and c.empresa=1
left join cTercero d on c.proveedor=d.codigo
left join [dbo].[SiesaContratos] v ON a.idTercero COLLATE DATABASE_DEFAULT = v.codigoTercero COLLATE DATABASE_DEFAULT AND v.activo = 1
left join dbo.vSiesaCentroCosto sc ON a.ccosto COLLATE DATABASE_DEFAULT = sc.codigo COLLATE DATABASE_DEFAULT AND sc.empresa = a.codEmpresa
WHERE        (anulado = 0)
-- ORDER BY idTercero
GO

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   Restaura la unidad de negocio tomada del centro de costo.
   ----------------------------------------------------------------------------
ALTER VIEW [dbo].[vInformacionAgronomicoSiesaTotal]
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
GO
---------------------------------------------------------------------------- */
