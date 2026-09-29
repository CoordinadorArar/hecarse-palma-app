# Catalogo de Vistas (80)

Generado desde `sys.views` + `sys.sql_expression_dependencies`. El proposito se infiere del nombre y de las tablas que consulta; se recomienda validar con el equipo funcional para las marcadas con muchas dependencias.

---

### `vAusentismo`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `gDiagnostico`, `nConcepto`, `nContratos`, `nIncapacidad`, `nTipoIncapacidad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vAusentismo`

### `vContabilizacion`
- **Tablas/objetos referenciados:** `aNovedad`, `cCentrosCosto`, `cCentrosCostoSigo`, `cContabilizacion`, `cContabilizacionDetalle`, `cPuc`, `cTercero`, `nClaseContrato`, `nConcepto`, `nDepartamento`, `nPeriodoDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vContabilizacion`

### `vContabilizacionNomina`
- **Tablas/objetos referenciados:** `aNovedad`, `cCentrosCosto`, `cCentrosCostoSigo`, `cContabilizacion`, `cContabilizacionDetalle`, `cPuc`, `cTercero`, `nClaseContrato`, `nConcepto`, `nDepartamento`, `nPeriodoDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vContabilizacionNomina`

### `vContratosSS`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `gTipoCuenta`, `nCargo`, `nCentroTrabajo`, `nClaseContrato`, `nContratos`, `nDepartamento`, `nEntidadEps`, `nEntidadFondoPension`, `nParametrosGeneral`, `nParametrosTipoCotizante`, `nSubTipoCotizante`, `nTipoCotizante`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vContratosSS`

### `vEntidadAfc`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadAfc`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadAfc`

### `vEntidadArp`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadArp`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadArp`

### `vEntidadCaja`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadCaja`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadCaja`

### `vEntidadEps`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadEps`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadEps`

### `vEntidadFondo`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadFondo`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadFondo`

### `vEntidadIcbf`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadIcbf`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadIcbf`

### `vEntidadPension`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadFondoPension`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadPension`

### `vEntidadSena`
- **Tablas/objetos referenciados:** `cTercero`, `gCiudad`, `nEntidadSena`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vEntidadSena`

### `vFuncionario`
- **Tablas/objetos referenciados:** `cTercero`, `gFoto`, `nFuncionario`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vFuncionario`

### `vInformacionAgronomicoSiesa`
- **Tablas/objetos referenciados:** `aLotes`, `vTransaccionAgronomico1`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vInformacionAgronomicoSiesa`

### `vInformacionAgronomicoSiesaTotal`
- **Tablas/objetos referenciados:** `SiesaContratos`, `aLotes`, `cTercero`, `nFuncionario`, `vSiesaCentroCosto`, `vTransaccionAgronomico1`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vInformacionAgronomicoSiesaTotal`

### `vLiquidacionCesancias`
- **Tablas/objetos referenciados:** `cTercero`, `gBanco`, `gTipoCuenta`, `nCargo`, `nCentroTrabajo`, `nClaseContrato`, `nConcepto`, `nContratos`, `nLiquidacionNomina`, `nLiquidacionNominaDetalle`, `nParametrosTipoCotizante`, `nSubTipoCotizante`, `nTipoCotizante`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vLiquidacionCesancias`

### `vLiquidacionDefinitivaReal`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `gBanco`, `gTipoCuenta`, `nCargo`, `nClaseContrato`, `nConcepto`, `nContratos`, `nDepartamento`, `nEntidadEps`, `nEntidadFondoPension`, `nLiquidacionNomina`, `nLiquidacionNominaDatos`, `nLiquidacionNominaDetalle`, `nParametrosGeneral`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vLiquidacionDefinitivaReal`

### `vNovedadesNomina`
- **Tablas/objetos referenciados:** `cTercero`, `gTipoTransaccion`, `nConcepto`, `nNovedades`, `nNovedadesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vNovedadesNomina`

### `vPrecontabilizacionNomina`
- **Tablas/objetos referenciados:** `aNovedad`, `cCentrosCosto`, `cCentrosCostoSigo`, `cPrecontabilizacion`, `cPuc`, `cTercero`, `nClaseContrato`, `nConcepto`, `nDepartamento`, `nPeriodoDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vPrecontabilizacionNomina`

### `vProgramacionFuncionariosDias`
- **Tablas/objetos referenciados:** `nHorasExtras`, `nProgramacion`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vProgramacionFuncionariosDias`

### `vSeguridadSocialEntidades`
- **Tablas/objetos referenciados:** `cTercero`, `nCentroTrabajo`, `nContratos`, `nSeguridadSocial`, `vEntidadArp`, `vEntidadCaja`, `vEntidadEps`, `vEntidadIcbf`, `vEntidadPension`, `vEntidadSena`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeguridadSocialEntidades`

### `vSeleccionLaboresProuduccion`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `aVariedad`, `cTercero`, `gTipoTransaccion`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionLaboresProuduccion`

### `vSeleccionaCorteFruta`
- **Tablas/objetos referenciados:** `aFinca`, `aNovedad`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `cTercero`, `nCuadrilla`, `nCuadrillaFuncionario`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaCorteFruta`

### `vSeleccionaDatosSeguridadSocialPlano`
- **Tablas/objetos referenciados:** `cTercero`, `gEmpresa`, `gTipoDocumento`, `nCentroTrabajo`, `nContratos`, `nSeguridadSocial`, `nSubTipoCotizante`, `nTipoCotizante`, `vEntidadArp`, `vEntidadCaja`, `vEntidadEps`, `vEntidadIcbf`, `vEntidadPension`, `vEntidadSena`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaDatosSeguridadSocialPlano`

### `vSeleccionaDatosSeguridadSocialPlano2388`
- **Tablas/objetos referenciados:** `gEmpresa`, `gTipoDocumento`, `nSeguridadSocialPila`, `vEntidadArp`, `vEntidadCaja`, `vEntidadEps`, `vEntidadIcbf`, `vEntidadPension`, `vEntidadSena`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaDatosSeguridadSocialPlano2388`

### `vSeleccionaDiferenciaLiquidacionTercero`
- **Tablas/objetos referenciados:** `vSeleccionaRealLiquidacion`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaDiferenciaLiquidacionTercero`

### `vSeleccionaEntradasMp`
- **Tablas/objetos referenciados:** `bProcedencia`, `bRegistroBascula`, `cTercero`, `fRetornaTotalFruta`, `iItems`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaEntradasMp`

### `vSeleccionaIncapacidadesAfectanARL`
- **Tablas/objetos referenciados:** `nIncapacidad`, `nTipoIncapacidad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaIncapacidadesAfectanARL`

### `vSeleccionaLabores`
- **Tablas/objetos referenciados:** `aGrupoNovedad`, `aNovedad`, `aTipoCanal`, `nConcepto`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLabores`

### `vSeleccionaLaboresTerceroLiquida`
- **Tablas/objetos referenciados:** `aNovedad`, `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `cTercero`, `gTipoTransaccion`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLaboresTerceroLiquida`

### `vSeleccionaLiquidacion`
- **Tablas/objetos referenciados:** `nLiquidacionNomina`, `nLiquidacionNominaDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLiquidacion`

### `vSeleccionaLiquidacionContratista`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `cCentrosCosto`, `cTercero`, `nFuncionario`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLiquidacionContratista`

### `vSeleccionaLiquidacionDefinitiva`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `fRetornaNombreMes`, `gBanco`, `gTipoCuenta`, `nCargo`, `nCentroTrabajo`, `nClaseContrato`, `nConcepto`, `nConceptosFijos`, `nContratos`, `nDepartamento`, `nEntidadEps`, `nEntidadFondoPension`, `nGrupoConcepto`, `nGrupoConceptoDetalle`, `nLiquidacionNomina`, `nLiquidacionNominaDatos`, `nLiquidacionNominaDetalle`, `nParametrosGeneral`, `nParametrosTipoCotizante`, `nPeriodoDetalle`, `nSubTipoCotizante`, `nTipoCotizante`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLiquidacionDefinitiva`

### `vSeleccionaLiquidacionDefinitivaCont`
- **Tablas/objetos referenciados:** `cTercero`, `gBanco`, `gTipoCuenta`, `nCargo`, `nClaseContrato`, `nConcepto`, `nContratos`, `nLiquidacionNomina`, `nLiquidacionNominaDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaLiquidacionDefinitivaCont`

### `vSeleccionaNovedades`
- **Tablas/objetos referenciados:** `nNovedades`, `nNovedadesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaNovedades`

### `vSeleccionaNovedadesNomina`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `nConcepto`, `nNovedades`, `nNovedadesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaNovedadesNomina`

### `vSeleccionaPago`
- **Tablas/objetos referenciados:** `gFormaPago`, `vSeleccionaLiquidacionDefinitiva`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaPago`

### `vSeleccionaPagosNomina`
- **Tablas/objetos referenciados:** `cTercero`, `gBanco`, `gTipoCuenta`, `nClaseContrato`, `nContratos`, `nPagosNomina`, `nPagosNominaDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaPagosNomina`

### `vSeleccionaPreliquidacion`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `nCargo`, `nConcepto`, `nContratos`, `nDepartamento`, `nEntidadEps`, `nEntidadFondoPension`, `nGrupoConcepto`, `nGrupoConceptoDetalle`, `tmpliquidacionNomina`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaPreliquidacion`

### `vSeleccionaRealLiquidacion`
- **Tablas/objetos referenciados:** `nConcepto`, `tmpliquidacionNomina`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaRealLiquidacion`

### `vSeleccionaRealLiquidacionVaca`
- **Tablas/objetos referenciados:** `nConcepto`, `tmpliquidacionNominaVacaciones`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaRealLiquidacionVaca`

### `vSeleccionaRegistroLabores`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aNovedadLotePrecio`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaRegistroLabores`

### `vSeleccionaRegistroNovadesNomina`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `nConcepto`, `nFuncionario`, `nNovedades`, `nNovedadesDetalle`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaRegistroNovadesNomina`

### `vSeleccionaSalidasDPT`
- **Tablas/objetos referenciados:** `bRegistroBascula`, `cTercero`, `fRetornaTotalDepachos`, `iItems`, `logDespacho`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaSalidasDPT`

### `vSeleccionaTiqueteFruta`
- **Tablas/objetos referenciados:** `aFinca`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTiqueteFruta`

### `vSeleccionaTransaccionCompletaLabores`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aNovedadLotePrecio`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTransaccionCompletaLabores`

### `vSeleccionaTransaccionCompletaSanidad`
- **Tablas/objetos referenciados:** `aSanidad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTransaccionCompletaSanidad`

### `vSeleccionaTransaccionFertilizante`
- **Tablas/objetos referenciados:** `aTransaccion`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTransaccionFertilizante`

### `vSeleccionaTransaccionTiquete`
- **Tablas/objetos referenciados:** `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTransaccionTiquete`

### `vSeleccionaTransaccionesAgronomico`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaTransaccionesAgronomico`

### `vSeleccionaVacaciones`
- **Tablas/objetos referenciados:** `nVacaciones`, `nVacacionesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaVacaciones`

### `vSeleccionaVacacionesSS`
- **Tablas/objetos referenciados:** `nParametrosGeneral`, `nVacaciones`, `nVacacionesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionaVacacionesSS`

### `vSeleccionapTransaccionLaboratorio`
- **Tablas/objetos referenciados:** `pTransaccionJerarquia`, `pTransaccionJerarquiaAnalisis`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSeleccionapTransaccionLaboratorio`

### `vSelecionaLiquidacionContratista`
- **Tablas/objetos referenciados:** `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `cTercero`, `nFuncionario`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSelecionaLiquidacionContratista`

### `vSiesaCentroCosto`
- **Tablas/objetos referenciados:** `t284_co_ccosto`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaCentroCosto`

### `vSiesaConceptosNomina`
- **Tablas/objetos referenciados:** `w0501_conceptos`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaConceptosNomina`

### `vSiesaContratos`
- **Tablas/objetos referenciados:** `t200_mm_terceros`, `t284_co_ccosto`, `w0550_contratos`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaContratos`

### `vSiesaEmpresas`
- **Tablas/objetos referenciados:** `t010_mm_companias`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaEmpresas`

### `vSiesaFuncionarios`
- **Tablas/objetos referenciados:** `t200_mm_terceros`, `w0540_empleados`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaFuncionarios`

### `vSiesaGrupoCentroCosto`
- **Tablas/objetos referenciados:** `t279_co_grupos_ccostos`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaGrupoCentroCosto`

### `vSiesaTerceros`
- **Tablas/objetos referenciados:** `t200_mm_terceros`, `t742_mm_entidad`, `t750_mm_movto_entidad`, `t753_mm_movto_entidad_columna`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vSiesaTerceros`

### `vTransaccionAgronomico`
- **Tablas/objetos referenciados:** `aFinca`, `aGrupoNovedad`, `aLotePesosPeriodo`, `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `aVariedad`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nClaseContrato`, `nConcepto`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionAgronomico`

### `vTransaccionAgronomico1`
- **Tablas/objetos referenciados:** `aFinca`, `aGrupoNovedad`, `aLotePesosPeriodo`, `aLotes`, `aNovedad`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `aVariedad`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nClaseContrato`, `nConcepto`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionAgronomico1`

### `vTransaccionPepa`
- **Tablas/objetos referenciados:** `aTransaccionPepa`, `aTransaccionTerceroPepa`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionPepa`

### `vTransaccionProduccion`
- **Tablas/objetos referenciados:** `gEmpresa`, `iItems`, `pTransaccion`, `pTransaccionDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionProduccion`

### `vTransaccioneLaboresDomingo`
- **Tablas/objetos referenciados:** `aNovedad`, `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccioneLaboresDomingo`

### `vTransaccionesCampoLiquidacion`
- **Tablas/objetos referenciados:** `aNovedad`, `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `nConcepto`, `nContratos`, `vTransaccionPepa`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionesCampoLiquidacion`

### `vTransaccionesSanidad`
- **Tablas/objetos referenciados:** `aCaracteristica`, `aFinca`, `aGrupoCaracteristica`, `aLotes`, `aNovedad`, `aSanidad`, `aSanidadDetalle`, `aSecciones`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vTransaccionesSanidad`

### `vVacaciones`
- **Tablas/objetos referenciados:** `cCentrosCosto`, `cTercero`, `nConcepto`, `nContratos`, `nVacaciones`, `nVacacionesDetalle`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vVacaciones`

### `vValorAcumuladoPrimas`
- **Tablas/objetos referenciados:** `nConcepto`, `nLiquidacionNomina`, `nLiquidacionNominaDetalle`, `nParametrosGeneral`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `vValorAcumuladoPrimas`

### `v_PowerBITransporte`
- **Tablas/objetos referenciados:** `vSeleccionaTransaccionTiquete`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_PowerBITransporte`

### `v_PowerBiActualizacion`
- _(no se detectaron dependencias directas — posible vista sobre otras vistas o funciones)_
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_PowerBiActualizacion`

### `v_PowerBiVentas`
- **Tablas/objetos referenciados:** `t120_mc_items`, `t121_mc_items_extensiones`, `t200_mm_terceros`, `t350_co_docto_contable`, `t470_cm_movto_invent`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_PowerBiVentas`

### `v_powerbiFertilizacion`
- **Tablas/objetos referenciados:** `v_powerbiSeleccionaRegistroLabores`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiFertilizacion`

### `v_powerbiFincas`
- **Tablas/objetos referenciados:** `aFinca`, `gCiudad`, `gDepartamento`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiFincas`

### `v_powerbiLotes`
- **Tablas/objetos referenciados:** `aLotes`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiLotes`

### `v_powerbiLotesDetalle`
- **Tablas/objetos referenciados:** `aLotes`, `aLotesDetalle`, `aVariedad`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiLotesDetalle`

### `v_powerbiProduccion`
- **Tablas/objetos referenciados:** `v_powerbiSeleccionaRegistroLabores`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiProduccion`

### `v_powerbiSeleccionaRegistroLabores`
- **Tablas/objetos referenciados:** `aFinca`, `aLotes`, `aNovedad`, `aNovedadLotePrecio`, `aTransaccion`, `aTransaccionBascula`, `aTransaccionNovedad`, `aTransaccionTercero`, `cCentrosCosto`, `cTercero`, `gEmpresa`, `gTipoTransaccion`, `nCargo`, `nContratos`, `sUsuarios`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiSeleccionaRegistroLabores`

### `v_powerbiTerceros`
- **Tablas/objetos referenciados:** `cTercero`
- Definicion completa: ver `vistas_definiciones.sql`, seccion `v_powerbiTerceros`
