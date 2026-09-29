# Diccionario de Tablas — Nomina

_66 tablas en este modulo._

---

## `nCargo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | CNO | varchar(50) | SI |  |  |
| 5 | jefeInmediato | varchar(50) | SI |  |  |
| 6 | salarioMaximo | money | SI |  |  |
| 7 | observacion | varchar(5550) | SI |  |  |
| 8 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_nCargo_gEmpresa)_

## `nCentroTrabajo`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | porcentaje | decimal(18,3) | NO |  |  |
| 5 | activo | bit | NO |  |  |

## `nClaseContrato`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | terminoFijo | bit | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | electivaProduccion | bit | SI |  |  |
| 7 | porcentaje | decimal(18,3) | SI |  |  |
| 8 | porcentajeSS | decimal(18,3) | SI |  |  |

## `nConcepto`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | abreviatura | varchar(20) | NO |  |  |
| 5 | signo | int | NO |  |  |
| 6 | tipoLiquidacion | int | NO |  |  |
| 7 | base | varchar(50) | SI |  |  |
| 8 | porcentaje | decimal(18,3) | NO |  |  |
| 9 | valor | money | NO |  |  |
| 10 | valorMinimo | money | NO |  |  |
| 11 | basePrimas | bit | NO |  |  |
| 12 | baseCajaCompensacion | bit | NO |  |  |
| 13 | baseCesantias | bit | NO |  |  |
| 14 | baseVacaciones | bit | NO |  |  |
| 15 | baseIntereses | bit | NO |  |  |
| 16 | baseSeguridadSocial | bit | NO |  |  |
| 17 | controlaSaldo | bit | NO |  |  |
| 18 | manejaRango | bit | NO |  |  |
| 19 | ingresoGravado | bit | NO |  |  |
| 20 | controlConcepto | int | NO |  |  |
| 21 | activo | bit | NO |  |  |
| 22 | fechaRegistro | datetime | NO |  |  |
| 23 | usuarioRegistro | varchar(50) | NO |  |  |
| 24 | validaPorcentaje | bit | NO |  |  |
| 25 | fijo | bit | NO |  |  |
| 26 | baseEmbargo | bit | SI |  |  |
| 27 | prioridad | int | SI |  |  |
| 28 | descuentaDomingo | bit | NO |  |  |
| 29 | descuentaTransporte | bit | NO |  |  |
| 30 | mostrarFecha | bit | NO |  |  |
| 31 | noMostrar | bit | NO |  |  |
| 32 | mostrarDetalle | bit | NO |  |  |
| 33 | ausentismo | bit | NO |  |  |
| 34 | prestacionSocial | bit | SI |  |  |
| 35 | sumaPrestacionSocial | bit | SI |  |  |
| 36 | mostrarCantidad | bit | SI |  |  |
| 37 | noMes | int | SI |  |  |
| 38 | habilitaValorTotal | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `aNovedad.empresa` → `empresa`
- `aNovedad.concepto` → `codigo`
- `nLiquidacionNominaDetalle.empresa` → `empresa`
- `nLiquidacionNominaDetalle.concepto` → `codigo`
- `nVacacionesDetalle.concepto` → `codigo`
- `nVacacionesDetalle.empresa` → `empresa`

## `nConceptoRango`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | concepto | varchar(50) | NO | 🔑 |  |
| 3 | registro | int | NO | 🔑 |  |
| 4 | minimo | money | NO |  |  |
| 5 | maximo | money | NO |  |  |
| 6 | porcentaje | decimal(18,3) | NO |  |  |
| 7 | valor | money | NO |  |  |
| 8 | por | bit | NO |  |  |

## `nConceptosFijos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | centroCosto | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | noPeriodo | int | NO | 🔑 |  |
| 6 | formaPago | int | NO |  |  |
| 7 | liquidada | bit | NO |  |  |
| 8 | acumulada | bit | NO |  |  |
| 9 | observacion | varchar(2550) | NO |  |  |
| 10 | usuario | varchar(50) | NO |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |
| 12 | lNovedades | bit | NO |  |  |
| 13 | lPresamo | bit | NO |  |  |
| 14 | lHoras | bit | NO |  |  |
| 15 | lVacaciones | bit | NO |  |  |
| 16 | lPrimas | bit | NO |  |  |
| 17 | lAusentismo | bit | NO |  |  |
| 18 | lEmbargo | bit | NO |  |  |
| 19 | lOtros | bit | NO |  |  |
| 20 | lNovedadesCredito | bit | NO |  |  |
| 21 | lFondavi | bit | NO |  |  |
| 22 | lDomingo | bit | SI |  |  |
| 23 | lFestivo | bit | SI |  |  |
| 24 | lDomingoCero | bit | SI |  |  |
| 25 | mDomingo | bit | SI |  |  |
| 26 | lSindicato | bit | SI |  |  |
| 27 | lDomingoPromedio | bit | SI |  |  |
| 28 | lFestivoPromedio | bit | SI |  |  |

**Tablas que referencian a esta (hijas):**
- `nConceptosFijosDetalle.empresa` → `empresa`
- `nConceptosFijosDetalle.centroCosto` → `centroCosto`
- `nConceptosFijosDetalle.año` → `año`
- `nConceptosFijosDetalle.mes` → `mes`
- `nConceptosFijosDetalle.noPeriodo` → `noPeriodo`

## `nConceptosFijosDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | centroCosto | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | noPeriodo | int | NO | 🔑 |  |
| 6 | concepto | varchar(50) | NO | 🔑 |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `nConceptosFijos.empresa`  _(constraint: FK_nConceptosFijosDetalle_nConceptosFijos)_
- `centroCosto` → `nConceptosFijos.centroCosto`  _(constraint: FK_nConceptosFijosDetalle_nConceptosFijos)_
- `centroCosto` → `cCentrosCosto.codigo`  _(constraint: FK_nConceptosFijosDetalle_cCentrosCosto)_
- `año` → `nConceptosFijos.año`  _(constraint: FK_nConceptosFijosDetalle_nConceptosFijos)_
- `mes` → `nConceptosFijos.mes`  _(constraint: FK_nConceptosFijosDetalle_nConceptosFijos)_
- `noPeriodo` → `nPeriodoDetalle.noPeriodo`  _(constraint: FK_nConceptosFijosDetalle_nPeriodoDetalle)_
- `empresa` → `cCentrosCosto.empresa`  _(constraint: FK_nConceptosFijosDetalle_cCentrosCosto)_
- `empresa` → `nPeriodoDetalle.empresa`  _(constraint: FK_nConceptosFijosDetalle_nPeriodoDetalle)_
- `año` → `nPeriodoDetalle.año`  _(constraint: FK_nConceptosFijosDetalle_nPeriodoDetalle)_
- `mes` → `nPeriodoDetalle.mes`  _(constraint: FK_nConceptosFijosDetalle_nPeriodoDetalle)_
- `noPeriodo` → `nConceptosFijos.noPeriodo`  _(constraint: FK_nConceptosFijosDetalle_nConceptosFijos)_

## `nContratos`  (filas: 71)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | id | int | NO | 🔑 |  |
| 3 | codigoTercero | varchar(50) | NO | 🔑 |  |
| 4 | tercero | int | NO | 🔑 |  |
| 5 | cargo | varchar(50) | SI |  |  |
| 6 | banco | varchar(50) | SI |  |  |
| 7 | tipoContizante | varchar(50) | SI |  |  |
| 8 | motivoRetiro | varchar(50) | SI |  |  |
| 9 | turno | varchar(50) | SI |  |  |
| 10 | departamento | varchar(50) | SI |  |  |
| 11 | ccosto | varchar(50) | SI |  |  |
| 12 | tipoNomina | varchar(50) | SI |  |  |
| 13 | tiempoBasico | varchar(50) | SI |  |  |
| 14 | entidadPension | varchar(50) | SI |  |  |
| 15 | entidadEps | varchar(50) | SI |  |  |
| 16 | entidadCesantias | varchar(50) | SI |  |  |
| 17 | entidadCaja | varchar(50) | SI |  |  |
| 18 | entidadArp | varchar(50) | SI |  |  |
| 19 | entidadSena | varchar(50) | SI |  |  |
| 20 | entidadIcbf | varchar(50) | SI |  |  |
| 21 | fechaIngreso | datetime | SI |  |  |
| 22 | fechaRetiro | datetime | SI |  |  |
| 23 | fechaContratoHasta | datetime | SI |  |  |
| 24 | fechaPrimaHasta | datetime | SI |  |  |
| 25 | fechaVacacionesHasta | datetime | SI |  |  |
| 26 | fechaUltimoAumento | datetime | SI |  |  |
| 27 | fechaUltimoVacaciones | datetime | SI |  |  |
| 28 | fechaUltimaPension | datetime | SI |  |  |
| 29 | fechaUltimoCesantias | datetime | SI |  |  |
| 30 | fechaContratoLey50 | datetime | SI |  |  |
| 31 | salario | money | NO |  |  |
| 32 | salarioAnterior | money | NO |  |  |
| 33 | valorDeducibleRete | money | NO |  |  |
| 34 | valorCesantiasCongeladas | money | NO |  |  |
| 35 | valorCesantiasRetiradas | money | NO |  |  |
| 36 | valorOtrosSalud | money | NO |  |  |
| 37 | valorSaludObligatoria | money | NO |  |  |
| 38 | cantidadHoras | decimal(9,6) | NO |  |  |
| 39 | pRetencion | decimal(9,6) | NO |  |  |
| 40 | pTiempoLaborado | decimal(9,6) | NO |  |  |
| 41 | diasPagadosVaciones | decimal(9,6) | NO |  |  |
| 42 | personaCargo | int | SI |  |  |
| 43 | cuentaBancaria | varchar(50) | SI |  |  |
| 44 | formaPago | varchar(50) | SI |  |  |
| 45 | regimenLaboral | int | SI |  |  |
| 46 | auxilioTransporte | int | SI |  |  |
| 47 | procediimentoRete | int | SI |  |  |
| 48 | pactoColectivo | bit | SI |  |  |
| 49 | deducible | varchar(50) | SI |  |  |
| 50 | otrosSalud | varchar(50) | SI |  |  |
| 51 | salarioIntegral | bit | SI |  |  |
| 52 | tipoCuenta | varchar(50) | SI |  |  |
| 53 | claseContrato | varchar(50) | NO |  |  |
| 54 | terminoContrato | varchar(1) | NO |  |  |
| 55 | foto | int | SI |  |  |
| 56 | observacion | varchar(50) | SI |  |  |
| 57 | activo | bit | NO |  |  |
| 58 | valorPrepagada | money | NO |  |  |
| 59 | valorDependientes | money | NO |  |  |
| 60 | fechaRegistro | datetime | NO |  |  |
| 61 | usuario | varchar(50) | NO |  |  |
| 62 | fechaActualizacion | datetime | SI |  |  |
| 63 | usuarioActualizacion | varchar(50) | SI |  |  |
| 64 | ley50 | bit | SI |  |  |
| 65 | centroTrabajo | varchar(50) | NO |  |  |
| 66 | diasContrato | int | NO |  |  |
| 67 | mSindicato | bit | SI |  |  |
| 68 | mFondoEmpleado | bit | SI |  |  |
| 69 | entidadFondoEmpleado | varchar(50) | SI |  |  |
| 70 | entidadSindicato | varchar(50) | SI |  |  |
| 71 | pFondoEmpleado | float | SI |  |  |
| 72 | pSindicato | float | SI |  |  |
| 73 | subTipoCotizante | varchar(50) | SI |  |  |
| 74 | entidadSaludAdicional | varchar(50) | SI |  |  |
| 75 | manejaDestajo | bit | SI |  |  |
| 76 | grupoLaborDestajo | varchar(50) | SI |  |  |
| 77 | cantidadDestajo | int | SI |  |  |

## `nCuadrilla`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | departamento | varchar(50) | NO |  |  |
| 3 | codigo | varchar(50) | NO | 🔑 |  |
| 4 | descripcion | varchar(250) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | orden | int | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_nCuadrilla_gEmpresa)_
- `empresa` → `nDepartamento.empresa`  _(constraint: FK_nCuadrilla_nDepartamento)_
- `departamento` → `nDepartamento.codigo`  _(constraint: FK_nCuadrilla_nDepartamento)_

**Tablas que referencian a esta (hijas):**
- `nCuadrillaFuncionario.empresa` → `empresa`
- `nCuadrillaFuncionario.cuadrilla` → `codigo`

## `nCuadrillaFuncionario`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | cuadrilla | varchar(50) | NO | 🔑 |  |
| 3 | funcionario | int | NO | 🔑 |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_nCuadrillaFuncionario_gEmpresa)_
- `empresa` → `nCuadrilla.empresa`  _(constraint: FK_nCuadrillaFuncionario_nCuadrilla)_
- `cuadrilla` → `nCuadrilla.codigo`  _(constraint: FK_nCuadrillaFuncionario_nCuadrilla)_
- `empresa` → `nFuncionario.empresa`  _(constraint: FK_nCuadrillaFuncionario_nFuncionario)_
- `funcionario` → `nFuncionario.tercero`  _(constraint: FK_nCuadrillaFuncionario_nFuncionario)_

## `nDepartamento`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | ccosto | varchar(50) | NO |  |  |
| 3 | codigo | varchar(50) | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_nDepartamento_gEmpresa)_
- `empresa` → `cCentrosCosto.empresa`  _(constraint: FK_nDepartamento_cCentrosCosto)_
- `ccosto` → `cCentrosCosto.codigo`  _(constraint: FK_nDepartamento_cCentrosCosto)_

**Tablas que referencian a esta (hijas):**
- `nCuadrilla.empresa` → `empresa`
- `nCuadrilla.departamento` → `codigo`

## `nDiasHabilesCc`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | ccosto | varchar(50) | NO | 🔑 |  |
| 3 | de | int | NO |  |  |
| 4 | hasta | int | NO |  |  |
| 5 | activo | bit | NO |  |  |

## `nEmbargos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | empleado | int | NO | 🔑 |  |
| 3 | codigo | int | NO | 🔑 |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | año | int | NO |  |  |
| 6 | mes | int | NO |  |  |
| 7 | mandamiento | varchar(50) | NO |  |  |
| 8 | fecha | datetime | NO |  |  |
| 9 | periodoInicial | varchar(6) | NO |  |  |
| 10 | peridoFinal | varchar(6) | SI |  |  |
| 11 | saldoCuotas | int | NO |  |  |
| 12 | valorCuotas | money | NO |  |  |
| 13 | porcentaje | money | NO |  |  |
| 14 | empresaEmbarga | int | SI |  |  |
| 15 | embargante | int | SI |  |  |
| 16 | valorFinal | money | SI |  |  |
| 17 | pCobroPosterior | decimal(18,2) | SI |  |  |
| 18 | cuotasCobroPosterior | int | SI |  |  |
| 19 | valorCobroPosterior | money | SI |  |  |
| 20 | valorBase | money | NO |  |  |
| 21 | saldo | money | SI |  |  |
| 22 | usuario | varchar(50) | NO |  |  |
| 23 | fechaRegistro | datetime | NO |  |  |
| 24 | tipoCuenta | varchar(50) | NO |  |  |
| 25 | banco | varchar(50) | NO |  |  |
| 26 | cuentaBancaria | varchar(50) | NO |  |  |
| 27 | modoPago | varchar(50) | NO |  |  |
| 28 | activo | bit | NO |  |  |
| 29 | cobroPosterior | bit | NO |  |  |
| 30 | manejaCuota | bit | NO |  |  |
| 31 | manejaCuotaPosterior | bit | NO |  |  |
| 32 | manejaSaldo | bit | NO |  |  |
| 33 | valorFinalPosterior | money | NO |  |  |
| 34 | cuotas | int | NO |  |  |
| 35 | fiscal | varchar(200) | SI |  |  |
| 36 | añoInicial | int | SI |  |  |
| 37 | salarioMinimo | bit | NO |  |  |

## `nEntidadAfc`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | activo | bit | NO |  |  |
| 8 | observacion | varchar(5550) | NO |  |  |
| 9 | fechaRegistro | datetime | NO |  |  |
| 10 | usuario | varchar(50) | NO |  |  |
| 11 | cuenta | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nEntidadAfc_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nEntidadAfc_cTercero)_

## `nEntidadArp`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | activo | bit | NO |  |  |
| 8 | observacion | varchar(5550) | NO |  |  |
| 9 | fechaRegistro | datetime | NO |  |  |
| 10 | usuario | varchar(50) | NO |  |  |
| 11 | cuenta | varchar(50) | SI |  |  |

## `nEntidadCaja`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | pais | varchar(50) | SI |  |  |
| 8 | ciudad | varchar(50) | SI |  |  |
| 9 | pAporte | decimal(18,6) | NO |  |  |
| 10 | integral | bit | NO |  |  |
| 11 | observacion | varchar(5550) | NO |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | fechaRegistro | datetime | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | cuenta | varchar(50) | SI |  |  |

## `nEntidadEps`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | pEmpleado | decimal(18,6) | NO |  |  |
| 8 | pEmpleador | decimal(18,6) | NO |  |  |
| 9 | pInactividad | decimal(18,6) | NO |  |  |
| 10 | integral | bit | NO |  |  |
| 11 | observacion | varchar(5550) | NO |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | fechaRegistro | datetime | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | cuenta | varchar(50) | SI |  |  |

## `nEntidadFondo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | activo | bit | NO |  |  |
| 8 | observacion | varchar(5550) | NO |  |  |
| 9 | fechaRegistro | datetime | NO |  |  |
| 10 | usuario | varchar(50) | NO |  |  |
| 11 | tipofondo | int | NO |  |  |
| 12 | cuenta | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nEntidadFondo_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nEntidadFondo_cTercero)_

## `nEntidadFondoPension`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | pEmpleado | decimal(18,6) | NO |  |  |
| 8 | pEmpleador | decimal(18,6) | NO |  |  |
| 9 | pInactividad | decimal(18,6) | NO |  |  |
| 10 | pSolidaridad | decimal(18,6) | NO |  |  |
| 11 | activo | bit | NO |  |  |
| 12 | integral | bit | NO |  |  |
| 13 | observacion | varchar(5550) | NO |  |  |
| 14 | fechaRegistro | datetime | NO |  |  |
| 15 | usuario | varchar(50) | NO |  |  |
| 16 | cuenta | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nEntidadFondoPension_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nEntidadFondoPension_cTercero)_

## `nEntidadIcbf`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | NO |  |  |
| 5 | proveedor | varchar(50) | NO |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | pais | varchar(10) | SI |  |  |
| 8 | ciudad | varchar(10) | SI |  |  |
| 9 | pAporte | decimal(18,6) | NO |  |  |
| 10 | observacion | varchar(5550) | NO |  |  |
| 11 | integral | bit | NO |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | fechaRegistro | datetime | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | cuenta | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nEntidadIcbf_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nEntidadIcbf_cTercero)_

## `nEntidadSena`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tercero | int | SI |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | codigoNacional | varchar(50) | SI |  |  |
| 7 | pais | varchar(50) | SI |  |  |
| 8 | ciudad | varchar(50) | SI |  |  |
| 9 | pAporte | decimal(18,6) | NO |  |  |
| 10 | integral | bit | NO |  |  |
| 11 | observacion | varchar(5550) | NO |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | fechaRegistro | datetime | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | cuenta | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nEntidadSena_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nEntidadSena_cTercero)_

## `nFestivo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | fecha | date | NO | 🔑 |  |

## `nFuncionario`  (filas: 331)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | sexo | varchar(1) | SI |  |  |
| 4 | codigo | varchar(50) | NO |  |  |
| 5 | proveedor | varchar(50) | SI |  |  |
| 6 | cliente | varchar(50) | SI |  |  |
| 7 | descripcion | varchar(950) | NO |  |  |
| 8 | rh | varchar(50) | SI |  |  |
| 9 | fechaNacimiento | date | SI |  |  |
| 10 | ciduadNacimiento | varchar(50) | SI |  |  |
| 11 | nivelEducativo | varchar(50) | SI |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | validaTurno | bit | NO |  |  |
| 14 | conductor | bit | NO |  |  |
| 15 | operadorLogistico | bit | NO |  |  |
| 16 | extranjero | bit | NO |  |  |
| 17 | declarante | bit | NO |  |  |
| 18 | contratista | bit | SI |  |  |
| 19 | otros | bit | SI |  |  |
| 20 | foto | int | SI |  |  |
| 22 | salario | float | SI |  |  |
| 23 | fechaIngreso | date | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_nFuncionario_gEmpresa)_
- `foto` → `gFoto.id`  _(constraint: FK_nFuncionario_gFoto)_

**Tablas que referencian a esta (hijas):**
- `nCuadrillaFuncionario.empresa` → `empresa`
- `nCuadrillaFuncionario.funcionario` → `tercero`

## `nGrupoConcepto`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | observacion | varchar(1550) | NO |  |  |
| 5 | activo | bit | NO |  |  |

## `nGrupoConceptoDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | grupo | varchar(50) | NO | 🔑 |  |
| 3 | cocepto | varchar(50) | NO | 🔑 |  |

## `nHorasExtras`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | fecha | date | NO | 🔑 |  |
| 3 | turno | varchar(50) | NO | 🔑 |  |
| 4 | funcionario | varchar(50) | NO | 🔑 |  |
| 5 | cantidad | int | NO |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | fechaRegistro | datetime | NO |  |  |

## `nIncapacidad`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | numero | int | NO | 🔑 |  |
| 4 | fechaInicial | date | SI |  |  |
| 5 | fechaFinal | date | SI |  |  |
| 6 | noDias | int | SI |  |  |
| 7 | referencia | varchar(250) | SI |  |  |
| 8 | tipoIncapacidad | varchar(50) | SI |  |  |
| 9 | prorroga | bit | SI |  |  |
| 10 | diagnostico | varchar(50) | SI |  |  |
| 11 | observacion | varchar(5550) | SI |  |  |
| 12 | liquidada | bit | SI |  |  |
| 13 | saldo | int | SI |  |  |
| 14 | usuario | varchar(50) | SI |  |  |
| 15 | fechaRegistro | datetime | SI |  |  |
| 16 | valor | money | SI |  |  |
| 17 | numeroReferencia | int | SI |  |  |
| 18 | diasPagos | int | SI |  |  |
| 19 | diasInicio | int | SI |  |  |
| 20 | valorPagado | money | SI |  |  |
| 21 | concepto | varchar(50) | NO |  |  |
| 22 | anulado | bit | SI |  |  |
| 23 | usuarioAnulado | varchar(50) | SI |  |  |
| 24 | fechaAnulado | datetime | SI |  |  |
| 25 | contrato | int | SI |  |  |

## `nIncapacidadDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | numero | int | NO | 🔑 |  |
| 4 | fecha | date | NO | 🔑 |  |
| 5 | cantidad | float | NO |  |  |
| 6 | valor | float | NO |  |  |
| 7 | cantidadR | float | NO |  |  |
| 8 | valorR | float | NO |  |  |

## `nLiquidacionCesantia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | fecha | date | SI |  |  |
| 5 | año | int | NO |  |  |
| 6 | periodo | int | NO |  |  |
| 7 | observacion | varchar(2550) | SI |  |  |
| 8 | anulado | bit | SI |  |  |
| 9 | usuario | varchar(50) | SI |  |  |
| 10 | usuarioAnulado | varchar(50) | SI |  |  |
| 11 | fechaRegistro | datetime | SI |  |  |
| 12 | fechaAnulado | datetime | SI |  |  |

## `nLiquidacionCesantiaDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | nchar(10) | NO | 🔑 |  |
| 3 | numero | nchar(10) | NO | 🔑 |  |
| 4 | tercero | int | NO | 🔑 |  |
| 5 | año | int | SI |  |  |
| 6 | fechaInicial | date | SI |  |  |
| 7 | fechaFinal | date | SI |  |  |
| 8 | fechaIngreso | date | SI |  |  |
| 9 | basico | int | SI |  |  |
| 10 | valorTransporte | int | SI |  |  |
| 11 | valorPromedio | int | SI |  |  |
| 12 | base | int | SI |  |  |
| 13 | diasPromedio | int | SI |  |  |
| 14 | diasCesantia | int | SI |  |  |
| 15 | valorCesantia | int | SI |  |  |
| 16 | valorInteresCesantia | int | SI |  |  |
| 17 | contrato | int | SI |  |  |

## `nLiquidacionNomina`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | int | NO | 🔑 |  |
| 5 | mes | int | NO | 🔑 |  |
| 6 | fecha | datetime | NO |  |  |
| 7 | fechaRegistro | datetime | NO |  |  |
| 8 | usuario | varchar(50) | NO |  |  |
| 9 | anulado | bit | NO |  |  |
| 10 | cerrado | bit | NO |  |  |
| 11 | estado | varchar(5) | NO |  |  |
| 12 | observacion | varchar(500) | NO |  |  |
| 13 | usuarioAnulado | varchar(50) | SI |  |  |
| 14 | fechaAnulado | datetime | SI |  |  |

## `nLiquidacionNominaDatos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | int | NO | 🔑 |  |
| 5 | mes | int | NO | 🔑 |  |
| 6 | periodo | int | NO | 🔑 |  |
| 7 | tercero | int | NO | 🔑 |  |
| 8 | noContrato | int | NO | 🔑 |  |
| 9 | sueldo | float | SI |  |  |
| 10 | entidadEps | varchar(50) | SI |  |  |
| 11 | entidadPension | varchar(50) | SI |  |  |
| 12 | entidadCesantias | varchar(50) | SI |  |  |
| 13 | entidadArp | varchar(50) | SI |  |  |
| 14 | entidadCaja | varchar(50) | SI |  |  |
| 15 | entidadSena | varchar(50) | SI |  |  |
| 16 | entidadIcbf | varchar(50) | SI |  |  |
| 17 | centroTrabajo | varchar(50) | SI |  |  |

## `nLiquidacionNominaDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | int | NO | 🔑 |  |
| 5 | mes | int | NO | 🔑 |  |
| 6 | registro | int | NO | 🔑 |  |
| 7 | noPeriodo | int | NO | 🔑 |  |
| 8 | tercero | int | NO | 🔑 |  |
| 9 | concepto | varchar(50) | NO |  |  |
| 10 | fechaInicial | date | SI |  |  |
| 11 | fechaFinal | date | SI |  |  |
| 12 | ccosto | varchar(50) | SI |  |  |
| 13 | departamento | varchar(50) | SI |  |  |
| 14 | cantidad | decimal(18,3) | SI |  |  |
| 15 | porcentaje | decimal(18,3) | SI |  |  |
| 16 | valorUnitario | money | SI |  |  |
| 17 | valorTotal | money | SI |  |  |
| 18 | signo | int | SI |  |  |
| 19 | saldo | money | SI |  |  |
| 20 | noDias | int | SI |  |  |
| 21 | entidad | int | SI |  |  |
| 22 | contrato | int | NO |  |  |
| 23 | basePrimas | bit | NO |  |  |
| 24 | baseCajaCompensacion | bit | NO |  |  |
| 25 | baseCesantias | bit | NO |  |  |
| 26 | baseVacaciones | bit | NO |  |  |
| 27 | baseIntereses | bit | NO |  |  |
| 28 | baseSeguridadSocial | bit | NO |  |  |
| 29 | manejaRango | bit | NO |  |  |
| 30 | baseEmbargo | bit | NO |  |  |
| 31 | noPrestamo | varchar(50) | SI |  |  |
| 32 | cantidadR | decimal(18,3) | SI |  |  |
| 33 | valorTotalR | money | SI |  |  |
| 34 | fecha | date | SI |  |  |
| 35 | tipoConcepto | varchar(50) | SI |  |  |
| 36 | desTipoConcepto | varchar(200) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nLiquidacionNominaDetalle_cTercero)_
- `tercero` → `cTercero.id`  _(constraint: FK_nLiquidacionNominaDetalle_cTercero)_
- `empresa` → `nConcepto.empresa`  _(constraint: FK_nLiquidacionNominaDetalle_nConcepto)_
- `año` → `nLiquidacionNominaDetalle.año`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `mes` → `nLiquidacionNominaDetalle.mes`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `registro` → `nLiquidacionNominaDetalle.registro`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `noPeriodo` → `nLiquidacionNominaDetalle.noPeriodo`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `tercero` → `nLiquidacionNominaDetalle.tercero`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `concepto` → `nConcepto.codigo`  _(constraint: FK_nLiquidacionNominaDetalle_nConcepto)_
- `empresa` → `cCentrosCosto.empresa`  _(constraint: FK_nLiquidacionNominaDetalle_cCentrosCosto)_
- `ccosto` → `cCentrosCosto.codigo`  _(constraint: FK_nLiquidacionNominaDetalle_cCentrosCosto)_
- `empresa` → `nLiquidacionNominaDetalle.empresa`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `tipo` → `nLiquidacionNominaDetalle.tipo`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_
- `numero` → `nLiquidacionNominaDetalle.numero`  _(constraint: FK_nLiquidacionNominaDetalle_nLiquidacionNominaDetalle)_

**Tablas que referencian a esta (hijas):**
- `nLiquidacionNominaDetalle.año` → `año`
- `nLiquidacionNominaDetalle.mes` → `mes`
- `nLiquidacionNominaDetalle.registro` → `registro`
- `nLiquidacionNominaDetalle.noPeriodo` → `noPeriodo`
- `nLiquidacionNominaDetalle.tercero` → `tercero`
- `nLiquidacionNominaDetalle.empresa` → `empresa`
- `nLiquidacionNominaDetalle.tipo` → `tipo`
- `nLiquidacionNominaDetalle.numero` → `numero`

## `nLiquidacionNominaDetalleAcumulado`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | float | SI |  |  |
| 2 | tipo | varchar(50) | SI |  |  |
| 3 | numero | varchar(50) | SI |  |  |
| 4 | año | float | SI |  |  |
| 5 | mes | float | SI |  |  |
| 6 | registro | float | SI |  |  |
| 7 | noPeriodo | float | SI |  |  |
| 8 | tercero | float | SI |  |  |
| 9 | concepto | varchar(50) | SI |  |  |
| 10 | fechaInicial | date | SI |  |  |
| 11 | fechaFinal | date | SI |  |  |
| 12 | ccosto | varchar(50) | SI |  |  |
| 13 | departamento | varchar(50) | SI |  |  |
| 14 | cantidad | decimal(18,3) | SI |  |  |
| 15 | porcentaje | decimal(18,3) | SI |  |  |
| 16 | valorUnitario | float | SI |  |  |
| 17 | valorTotal | float | SI |  |  |
| 18 | signo | int | SI |  |  |
| 19 | saldo | float | SI |  |  |
| 20 | noDias | float | SI |  |  |
| 21 | entidad | varchar(50) | SI |  |  |
| 22 | contrato | float | SI |  |  |
| 23 | basePrimas | bit | SI |  |  |
| 24 | baseCajaCompensacion | bit | SI |  |  |
| 25 | baseCesantias | bit | SI |  |  |
| 26 | baseVacaciones | bit | SI |  |  |
| 27 | baseIntereses | bit | SI |  |  |
| 28 | baseSeguridadSocial | bit | SI |  |  |
| 29 | manejaRango | bit | SI |  |  |
| 30 | baseEmbargo | bit | SI |  |  |
| 31 | noPrestamo | nvarchar(255) | SI |  |  |
| 32 | cantidadR | float | SI |  |  |
| 33 | valorTotalR | float | SI |  |  |
| 34 | fecha | varchar(50) | SI |  |  |
| 35 | tipoConcepto | varchar(50) | SI |  |  |
| 36 | desTipoConcepto | varchar(50) | SI |  |  |

## `nLiquidacionPrima`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | fecha | date | SI |  |  |
| 5 | año | int | NO |  |  |
| 6 | periodo | int | NO |  |  |
| 7 | observacion | varchar(2550) | SI |  |  |
| 8 | anulado | bit | SI |  |  |
| 9 | usuario | varchar(50) | SI |  |  |
| 10 | usuarioAnulado | varchar(50) | SI |  |  |
| 11 | fechaRegistro | datetime | SI |  |  |
| 12 | fechaAnulado | datetime | SI |  |  |

## `nLiquidacionPrimaDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | tercero | int | NO | 🔑 |  |
| 5 | añoInicial | int | SI |  |  |
| 6 | añoFinal | int | SI |  |  |
| 7 | periodoInicial | int | SI |  |  |
| 8 | periodoFinal | int | SI |  |  |
| 9 | fechaInicial | date | SI |  |  |
| 10 | fechaFinal | date | SI |  |  |
| 11 | fechaIngreso | date | SI |  |  |
| 12 | basico | int | SI |  |  |
| 13 | valorTransporte | int | SI |  |  |
| 14 | valorPromedio | int | SI |  |  |
| 15 | base | int | SI |  |  |
| 16 | diasPromedio | int | SI |  |  |
| 17 | diasPrimas | int | SI |  |  |
| 18 | valorPrima | int | SI |  |  |
| 19 | contrato | int | SI |  |  |

## `nModoCampo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | modo | varchar(50) | NO | 🔑 |  |
| 3 | entidad | varchar(250) | NO | 🔑 |  |
| 4 | campo | varchar(250) | NO | 🔑 |  |

## `nMotivoRetiro`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | activo | bit | NO |  |  |
| 5 | observacion | varchar(5550) | NO |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | fechaRegistro | datetime | NO |  |  |

## `nNovedades`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | fecha | date | NO |  |  |
| 5 | remision | varchar(50) | SI |  |  |
| 6 | ccosto | varchar(50) | SI |  |  |
| 7 | empleado | varchar(50) | SI |  |  |
| 8 | concepto | varchar(50) | SI |  |  |
| 9 | observacion | varchar(2000) | NO |  |  |
| 10 | anulado | bit | NO |  |  |
| 11 | fechaAnulado | datetime | SI |  |  |
| 12 | usuarioAnulado | varchar(50) | SI |  |  |
| 13 | usuarioRegistro | varchar(50) | NO |  |  |
| 14 | fechaRegistro | datetime | NO |  |  |

## `nNovedadesDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | concepto | varchar(50) | SI |  |  |
| 6 | empleado | varchar(50) | SI |  |  |
| 7 | cantidad | decimal(18,3) | NO |  |  |
| 8 | valor | money | NO |  |  |
| 9 | añoInicial | int | NO |  |  |
| 10 | periodoInicial | int | NO |  |  |
| 11 | periodoFinal | int | NO |  |  |
| 12 | frecuencia | int | NO |  |  |
| 13 | detalle | varchar(250) | NO |  |  |
| 14 | ultimoPeriodoLiquidado | int | SI |  |  |
| 15 | ultimoPeriodoFrecuencia | int | SI |  |  |
| 16 | liquidada | bit | SI |  |  |
| 17 | anulado | bit | SI |  |  |
| 18 | añoFinal | int | SI |  |  |
| 19 | ccosto | varchar(50) | SI |  |  |
| 20 | contrato | int | SI |  |  |

## `nPagosNomina`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | periodoNomina | int | NO | 🔑 |  |
| 5 | registro | int | NO | 🔑 |  |
| 6 | numero | varchar(50) | NO | 🔑 |  |
| 7 | fecha | date | NO |  |  |
| 8 | Banco | varchar(50) | NO |  |  |
| 9 | TipoCuenta | varchar(50) | NO |  |  |
| 10 | noCuenta | varchar(50) | NO |  |  |
| 11 | NoChequeInicial | varchar(50) | NO |  |  |
| 12 | usuario | varchar(50) | NO |  |  |
| 13 | anulado | bit | SI |  |  |
| 14 | usuarioAnulado | varchar(50) | SI |  |  |
| 15 | fechaAnualado | datetime | SI |  |  |
| 16 | fechaRegistro | datetime | NO |  |  |
| 17 | valorTotal | money | NO |  |  |
| 18 | noChequeFinal | varchar(50) | SI |  |  |
| 19 | mPagoCheque | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `Banco` → `gBanco.codigo`  _(constraint: FK_nPagosNomina_gBanco)_
- `empresa` → `gBanco.empresa`  _(constraint: FK_nPagosNomina_gBanco)_

## `nPagosNominaDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | periodoNomina | int | NO | 🔑 |  |
| 5 | registro | int | NO | 🔑 |  |
| 6 | item | int | NO | 🔑 |  |
| 7 | codigoBanco | varchar(50) | NO |  |  |
| 8 | tercero | int | NO |  |  |
| 9 | claseContrato | int | NO |  |  |
| 10 | valorPago | decimal(18,3) | NO |  |  |
| 11 | tipoCuenta | varchar(10) | NO |  |  |
| 12 | documentoNomina | varchar(50) | NO |  |  |
| 13 | noCheque | varchar(50) | NO |  |  |
| 14 | formaPago | varchar(50) | NO |  |  |
| 15 | noContrato | int | SI |  |  |
| 16 | centroCosto | varchar(50) | SI |  |  |
| 17 | otros | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `tercero` → `cTercero.id`  _(constraint: FK_nPagosNominaDetalle_cTercero)_
- `empresa` → `cTercero.empresa`  _(constraint: FK_nPagosNominaDetalle_cTercero)_

## `nParametrosAno`  (filas: 3)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | ano | int | NO | 🔑 |  |
| 3 | fechaRegistro | datetime | NO |  |  |
| 4 | usuario | varchar(30) | NO |  |  |
| 5 | vSalarioMinimo | money | NO |  |  |
| 6 | vAuxilioTransporte | money | NO |  |  |
| 7 | vUVT | money | NO |  |  |
| 8 | vPatrimonioBruto | money | NO |  |  |
| 9 | vIngresoBruto | money | NO |  |  |
| 10 | pExentoRetencion | decimal(18,3) | NO |  |  |
| 11 | pMaximoaportePension | decimal(18,3) | NO |  |  |
| 12 | pExentoSalario1393 | decimal(18,3) | NO |  |  |
| 13 | pDependientes | decimal(18,3) | NO |  |  |
| 14 | vMaximoExento | money | NO |  |  |
| 15 | vMaxAporteAFC | money | NO |  |  |
| 16 | vMaxDeducibleVivienda | money | NO |  |  |
| 17 | vDependientes | money | NO |  |  |
| 18 | vMinimoingresosDeclarante | money | NO |  |  |
| 19 | vUVT1 | money | NO |  |  |
| 20 | vUVT2 | money | NO |  |  |
| 21 | vUVT3 | money | NO |  |  |
| 22 | vUVT4 | money | NO |  |  |
| 23 | vUVT5 | money | NO |  |  |
| 24 | vUVT6 | money | NO |  |  |
| 25 | cAplicarArt385 | bit | NO |  |  |
| 26 | cSalarioIntegral | bit | NO |  |  |
| 27 | cRestaIncapacidad | bit | NO |  |  |
| 28 | cDiasTNL | bit | NO |  |  |
| 29 | observacion | varchar(5000) | NO |  |  |
| 30 | vMinimoPeriodo | money | NO |  |  |
| 31 | noSueldoST | int | NO |  |  |
| 32 | vMaxPagoSalud | money | NO |  |  |

## `nParametrosGeneral`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | noSalarioIntegral | int | NO |  |  |
| 3 | jornadaDiaria | int | NO |  |  |
| 4 | tipoJornadaDiaria | varchar(2) | NO |  |  |
| 5 | horaInicioDiurna | int | NO |  |  |
| 6 | horaInicioNocturna | int | NO |  |  |
| 7 | fechaUlrimaCesantias | date | NO |  |  |
| 8 | HO | varchar(50) | SI |  |  |
| 9 | HRN | varchar(50) | SI |  |  |
| 10 | HEN | varchar(50) | SI |  |  |
| 11 | HED | varchar(50) | SI |  |  |
| 12 | HD | varchar(50) | SI |  |  |
| 13 | HF | varchar(50) | SI |  |  |
| 14 | HRF | varchar(50) | SI |  |  |
| 15 | HENF | varchar(50) | SI |  |  |
| 16 | HEDF | varchar(50) | SI |  |  |
| 17 | sueldo | varchar(50) | SI |  |  |
| 18 | jornales | varchar(50) | SI |  |  |
| 19 | cesantias | varchar(50) | SI |  |  |
| 20 | intereses | varchar(50) | SI |  |  |
| 21 | vacaciones | varchar(50) | SI |  |  |
| 22 | primas | varchar(50) | SI |  |  |
| 23 | salarioIntegral | varchar(50) | SI |  |  |
| 24 | permisos | varchar(50) | SI |  |  |
| 25 | subsidioTransporte | varchar(50) | SI |  |  |
| 26 | retroactivo | varchar(50) | SI |  |  |
| 27 | retencion | varchar(50) | SI |  |  |
| 28 | suspenciones | varchar(50) | SI |  |  |
| 29 | incapacidades | varchar(50) | SI |  |  |
| 30 | cajaCompensacion | varchar(50) | SI |  |  |
| 31 | sena | varchar(50) | SI |  |  |
| 32 | ICBF | varchar(50) | SI |  |  |
| 33 | ARP | varchar(50) | SI |  |  |
| 34 | indemnizacion | varchar(50) | SI |  |  |
| 35 | EM | varchar(50) | SI |  |  |
| 36 | IVM | varchar(50) | SI |  |  |
| 37 | ATEP | varchar(50) | SI |  |  |
| 38 | fondoSolidaridad | varchar(50) | SI |  |  |
| 39 | licRemunerado | varchar(50) | SI |  |  |
| 40 | licNoRemunerado | varchar(50) | SI |  |  |
| 41 | PrimasExtralegales | varchar(50) | SI |  |  |
| 42 | anticipoCesantias | varchar(50) | SI |  |  |
| 43 | fechaRegistro | datetime | NO |  |  |
| 44 | fechaEdicion | datetime | SI |  |  |
| 45 | usuarioRegistro | varchar(50) | NO |  |  |
| 46 | usuarioEdicion | varchar(50) | SI |  |  |
| 47 | salud | varchar(50) | SI |  |  |
| 48 | pension | varchar(50) | SI |  |  |
| 49 | embargos | varchar(50) | SI |  |  |
| 50 | ganaDomingo | varchar(50) | SI |  |  |
| 51 | pGanaDomingo | bit | NO |  |  |
| 52 | HEDD | varchar(50) | SI |  |  |
| 53 | HEND | varchar(50) | SI |  |  |
| 54 | HRD | varchar(50) | SI |  |  |
| 55 | fondoEmpleado | varchar(50) | SI |  |  |
| 56 | sindicato | varchar(50) | SI |  |  |
| 57 | diasVacaciones | int | SI |  |  |
| 58 | pagoFestivo | varchar(50) | SI |  |  |
| 59 | aprendizSena | varchar(50) | SI |  |  |
| 62 | horaFinalDiurna | int | SI |  |  |
| 63 | horaFinalNocturna | int | SI |  |  |
| 64 | noSMLVSenaICBF | int | SI |  |  |
| 65 | promedioFestivo | bit | NO |  |  |
| 66 | paga31 | bit | SI |  |  |
| 67 | LQN | varchar(50) | SI |  |  |
| 68 | LQC | varchar(50) | SI |  |  |
| 69 | ACU | varchar(50) | SI |  |  |
| 70 | aporteParafiscalesING | bit | SI |  |  |
| 71 | entidadARL | varchar(50) | SI |  |  |
| 72 | uMedidaJornal | varchar(50) | SI |  |  |

## `nParametrosTipoCotizante`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipoCotizante | varchar(50) | NO | 🔑 |  |
| 3 | subTipoCotizante | varchar(50) | NO | 🔑 |  |
| 4 | salud | bit | NO |  |  |
| 5 | pension | bit | NO |  |  |
| 6 | fondoSolidaridad | bit | NO |  |  |
| 7 | arp | bit | NO |  |  |
| 8 | caja | bit | NO |  |  |
| 9 | sena | bit | NO |  |  |
| 10 | icbf | bit | NO |  |  |

## `nPeriodoDetalle`  (filas: 79)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | noPeriodo | int | NO | 🔑 |  |
| 5 | fechaInicial | date | SI |  |  |
| 6 | fechaFinal | date | SI |  |  |
| 7 | fechaCorte | date | SI |  |  |
| 8 | fechaPago | datetime | SI |  |  |
| 9 | cerrado | bit | NO |  |  |
| 10 | fechaRegistro | datetime | NO |  |  |
| 11 | usuario | varchar(50) | NO |  |  |
| 12 | tipoNomina | varchar(50) | NO |  |  |
| 13 | diasNomina | int | SI |  |  |
| 14 | agronomico | bit | NO |  |  |
| 15 | ejecutaLabores | bit | NO |  |  |
| 16 | nombrePeriodo | varchar(250) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `nPeriodoDetalle.empresa`  _(constraint: FK_nPeriodoDetalle_nPeriodoDetalle1)_
- `año` → `nPeriodoDetalle.año`  _(constraint: FK_nPeriodoDetalle_nPeriodoDetalle1)_
- `mes` → `nPeriodoDetalle.mes`  _(constraint: FK_nPeriodoDetalle_nPeriodoDetalle1)_
- `noPeriodo` → `nPeriodoDetalle.noPeriodo`  _(constraint: FK_nPeriodoDetalle_nPeriodoDetalle1)_

**Tablas que referencian a esta (hijas):**
- `nConceptosFijosDetalle.noPeriodo` → `noPeriodo`
- `nConceptosFijosDetalle.empresa` → `empresa`
- `nConceptosFijosDetalle.año` → `año`
- `nConceptosFijosDetalle.mes` → `mes`
- `nPeriodoDetalle.empresa` → `empresa`
- `nPeriodoDetalle.año` → `año`
- `nPeriodoDetalle.mes` → `mes`
- `nPeriodoDetalle.noPeriodo` → `noPeriodo`

## `nPlanoBanco`  (filas: 6)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | banco | varchar(50) | NO | 🔑 |  |
| 3 | tipoRegistro | int | NO | 🔑 |  |
| 4 | usuario | varchar(50) | NO |  |  |
| 5 | fechaRegistro | datetime | NO |  |  |

## `nPlanoBancoDetalle`  (filas: 106)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | banco | varchar(50) | NO | 🔑 |  |
| 3 | registro | int | NO | 🔑 |  |
| 4 | tipoRegistro | int | NO | 🔑 |  |
| 5 | nombreCampo | varchar(500) | SI |  |  |
| 6 | inicio | int | NO |  |  |
| 7 | longitud | int | NO |  |  |
| 8 | mValorFijo | bit | NO |  |  |
| 9 | valorFijo | varchar(50) | SI |  |  |
| 10 | tipoCampo | int | NO |  |  |
| 11 | mCampoFormulario | bit | NO |  |  |
| 12 | campoFormulario | varchar(50) | SI |  |  |

## `nPrestamo`  (filas: 25)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | fecha | date | SI |  |  |
| 4 | ccosto | varchar(50) | NO |  |  |
| 5 | empleado | int | NO |  |  |
| 6 | concepto | varchar(50) | NO |  |  |
| 7 | año | int | NO |  |  |
| 8 | mes | int | NO |  |  |
| 9 | periodoInicial | int | NO |  |  |
| 10 | valor | money | NO |  |  |
| 11 | cuotas | int | NO |  |  |
| 12 | valorCuotas | money | NO |  |  |
| 13 | cuotasPendiente | int | NO |  |  |
| 14 | valorSaldo | money | NO |  |  |
| 15 | frecuencia | int | NO |  |  |
| 16 | observacion | varchar(5500) | NO |  |  |
| 17 | usuarioRegistro | varchar(50) | NO |  |  |
| 18 | fechaRegistro | datetime | NO |  |  |
| 19 | liquidado | bit | NO |  |  |
| 20 | formaPago | varchar(50) | NO |  |  |
| 21 | docRef | varchar(200) | NO |  |  |
| 22 | contrato | int | SI |  |  |
| 23 | pInteres | float | NO |  |  |
| 24 | tipoPrestamo | varchar(50) | NO |  |  |
| 25 | detallado | bit | NO |  |  |
| 26 | conceptoInteres | varchar(50) | SI |  |  |

## `nPrestamoDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | registroCuota | int | NO | 🔑 |  |
| 4 | inicial | money | NO |  |  |
| 5 | interes | money | NO |  |  |
| 6 | amortizacion | money | NO |  |  |
| 7 | valorCuota | money | NO |  |  |
| 8 | final | money | NO |  |  |
| 9 | pagado | bit | NO |  |  |
| 10 | valorPagado | money | SI |  |  |
| 11 | añoPagado | int | SI |  |  |
| 12 | periodoPagado | int | SI |  |  |
| 13 | usuarioPagado | varchar(50) | SI |  |  |
| 14 | fechaPagado | datetime | SI |  |  |

## `nProgramacion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | fecha | date | NO | 🔑 |  |
| 3 | turno | varchar(50) | NO | 🔑 |  |
| 4 | funcionario | varchar(50) | NO | 🔑 |  |
| 5 | cuadrilla | varchar(50) | SI |  |  |
| 6 | horaInicio | int | NO |  |  |
| 7 | horaEntrada | datetime | SI |  |  |
| 8 | horaSalida | datetime | SI |  |  |
| 9 | horasTurno | int | NO |  |  |
| 10 | horasExtras | float | NO |  |  |
| 11 | estado | char(10) | NO |  |  |
| 12 | fechaRegistro | datetime | NO |  |  |
| 13 | usuario | varchar(50) | NO |  |  |
| 14 | finca | varchar(50) | SI |  |  |

## `nProrroga`  (filas: 158)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | id | int | NO | 🔑 |  |
| 3 | contrato | int | NO | 🔑 |  |
| 4 | tipo | varchar(1) | NO | 🔑 |  |
| 5 | tercero | int | NO | 🔑 |  |
| 6 | fechaInicial | date | NO |  |  |
| 7 | fechaFinal | date | NO |  |  |
| 8 | dias | int | NO |  |  |
| 9 | fechaFinalAnterior | date | NO |  |  |
| 10 | observacion | varchar(5550) | NO |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |
| 12 | usuario | varchar(50) | NO |  |  |
| 13 | motivoRetiro | varchar(50) | SI |  |  |
| 14 | retirado | bit | SI |  |  |
| 15 | fechaRetiro | date | SI |  |  |

## `nSeguridadSocial`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | idTercero | int | NO |  |  |
| 6 | codigoTercero | varchar(50) | NO |  |  |
| 7 | salario | int | SI |  |  |
| 8 | IBCsalud | int | SI |  |  |
| 9 | IBCpension | int | SI |  |  |
| 10 | IBCarp | int | SI |  |  |
| 11 | IBCcaja | int | SI |  |  |
| 12 | dSalud | int | SI |  |  |
| 13 | dPension | int | SI |  |  |
| 14 | dArp | int | SI |  |  |
| 15 | dCaja | int | SI |  |  |
| 16 | pSalud | float | SI |  |  |
| 17 | pPension | float | SI |  |  |
| 18 | pArp | float | SI |  |  |
| 19 | pCaja | float | SI |  |  |
| 20 | pFondo | float | SI |  |  |
| 21 | valorSalud | int | SI |  |  |
| 22 | valorPension | int | SI |  |  |
| 23 | valorFondo | int | SI |  |  |
| 24 | valorFondoSub | int | SI |  |  |
| 25 | valorArp | int | SI |  |  |
| 26 | valorCaja | int | SI |  |  |
| 27 | valorSena | int | SI |  |  |
| 28 | valorIcbf | int | SI |  |  |
| 29 | ING | varchar(1) | SI |  |  |
| 30 | RET | varchar(1) | SI |  |  |
| 31 | TDE | varchar(1) | SI |  |  |
| 32 | TAE | varchar(1) | SI |  |  |
| 33 | TDP | varchar(1) | SI |  |  |
| 34 | TAP | varchar(1) | SI |  |  |
| 35 | VSP | varchar(1) | SI |  |  |
| 36 | VTE | varchar(1) | SI |  |  |
| 37 | VST | varchar(1) | SI |  |  |
| 38 | SLN | varchar(1) | SI |  |  |
| 39 | IGE | varchar(1) | SI |  |  |
| 40 | LMA | varchar(1) | SI |  |  |
| 41 | VAC | varchar(1) | SI |  |  |
| 42 | AVP | varchar(1) | SI |  |  |
| 43 | VCT | varchar(1) | SI |  |  |
| 44 | IRP | int | SI |  |  |
| 45 | exoneraSalud | varchar(1) | SI |  |  |
| 46 | terceroSalud | int | SI |  |  |
| 47 | terceroPension | int | SI |  |  |
| 48 | terceroCaja | int | SI |  |  |
| 49 | terceroArp | int | SI |  |  |
| 50 | terceroSena | int | SI |  |  |
| 51 | terceroIcbf | int | SI |  |  |

## `nSeguridadSocialPila`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | idTercero | int | NO |  |  |
| 6 | codigoTercero | varchar(50) | NO |  |  |
| 7 | apellido1 | varchar(250) | SI |  |  |
| 8 | apellido2 | varchar(250) | SI |  |  |
| 9 | nombre1 | varchar(250) | SI |  |  |
| 10 | nombre2 | varchar(250) | SI |  |  |
| 11 | departamento | varchar(250) | SI |  |  |
| 12 | ciudad | varchar(250) | SI |  |  |
| 13 | tipoCotizante | varchar(50) | SI |  |  |
| 14 | subTipoCotizante | varchar(50) | SI |  |  |
| 15 | horasLaboradas | int | SI |  |  |
| 16 | extranjero | varchar(1) | SI |  |  |
| 17 | RecidenteExterior | varchar(1) | SI |  |  |
| 18 | fechaRadExterior | date | SI |  |  |
| 19 | ING | varchar(1) | SI |  |  |
| 20 | fechaIngreso | date | SI |  |  |
| 21 | RET | varchar(1) | SI |  |  |
| 22 | fechaRetiro | date | SI |  |  |
| 23 | TDE | varchar(1) | SI |  |  |
| 24 | TAE | varchar(1) | SI |  |  |
| 25 | TDP | varchar(1) | SI |  |  |
| 26 | TAP | varchar(1) | SI |  |  |
| 27 | VSP | varchar(1) | SI |  |  |
| 28 | fechaVSP | date | SI |  |  |
| 29 | VST | varchar(1) | SI |  |  |
| 30 | SLN | varchar(1) | SI |  |  |
| 31 | fiSLN | date | SI |  |  |
| 32 | ffSLN | date | SI |  |  |
| 33 | IGE | varchar(1) | SI |  |  |
| 34 | fiIGE | date | SI |  |  |
| 35 | ffIGE | date | SI |  |  |
| 36 | LMA | varchar(1) | SI |  |  |
| 37 | fiLMA | date | SI |  |  |
| 38 | ffLMA | date | SI |  |  |
| 39 | VAC | varchar(1) | SI |  |  |
| 40 | fiVAC | date | SI |  |  |
| 41 | ffVAC | date | SI |  |  |
| 42 | AVP | varchar(1) | SI |  |  |
| 43 | VCT | varchar(1) | SI |  |  |
| 44 | fiVCT | date | SI |  |  |
| 45 | ffVCT | date | SI |  |  |
| 46 | IRL | int | SI |  |  |
| 47 | fiIRL | date | SI |  |  |
| 48 | ffIRL | date | SI |  |  |
| 49 | correciones | varchar(1) | SI |  |  |
| 50 | salario | int | SI |  |  |
| 51 | salarioIntegral | varchar(1) | SI |  |  |
| 52 | terceroPension | int | SI |  |  |
| 53 | dPension | int | SI |  |  |
| 54 | IBCpension | int | SI |  |  |
| 55 | pPension | float | SI |  |  |
| 56 | valorPension | int | SI |  |  |
| 57 | indicadorAltoRiesgo | int | SI |  |  |
| 58 | cotizacionVoluntariaAfiliado | float | SI |  |  |
| 59 | cotizacionVoluntariaEmpleador | float | SI |  |  |
| 60 | valorFondo | float | SI |  |  |
| 61 | valorFondoSub | float | SI |  |  |
| 62 | pFondo | float | SI |  |  |
| 63 | valorRetenido | float | SI |  |  |
| 64 | totalPension | float | SI |  |  |
| 65 | AFPdestino | varchar(50) | SI |  |  |
| 66 | terceroSalud | int | SI |  |  |
| 67 | dSalud | int | SI |  |  |
| 68 | IBCsalud | int | SI |  |  |
| 69 | pSalud | float | SI |  |  |
| 70 | valorSalud | float | SI |  |  |
| 71 | valorUPC | float | SI |  |  |
| 72 | noAutorizacionEG | varchar(100) | SI |  |  |
| 73 | valorIncapacidad | float | SI |  |  |
| 74 | noAutorizacionLMA | varchar(100) | SI |  |  |
| 75 | valorLMA | float | SI |  |  |
| 76 | saludDestino | nchar(10) | SI |  |  |
| 77 | terceroArl | int | SI |  |  |
| 78 | dArl | int | SI |  |  |
| 79 | IBCarl | int | SI |  |  |
| 80 | pArl | float | SI |  |  |
| 81 | claseARL | int | SI |  |  |
| 82 | centroTrabajo | varchar(50) | SI |  |  |
| 83 | valorArl | float | SI |  |  |
| 84 | dCaja | int | SI |  |  |
| 85 | terceroCaja | int | SI |  |  |
| 86 | IBCcaja | int | SI |  |  |
| 87 | pCaja | float | SI |  |  |
| 88 | valorCaja | int | SI |  |  |
| 89 | IBCCajaOtros | float | SI |  |  |
| 90 | pSena | float | SI |  |  |
| 91 | valorSena | int | SI |  |  |
| 92 | terceroSena | int | SI |  |  |
| 93 | pICBF | float | SI |  |  |
| 94 | valorICBF | int | SI |  |  |
| 95 | terceroIcbf | int | SI |  |  |
| 96 | pESAP | float | SI |  |  |
| 97 | valorESAP | float | SI |  |  |
| 98 | pMEN | float | SI |  |  |
| 99 | valorMEN | float | SI |  |  |
| 100 | exoneraSalud | varchar(1) | SI |  |  |
| 101 | tipoIDcotizanteUPC | varchar(50) | SI |  |  |
| 102 | noIDcotizanteUPC | varchar(50) | SI |  |  |
| 103 | contrato | int | SI |  |  |
| 104 | tipoID | varchar(50) | SI |  |  |

## `nSubTipoCotizante`  (filas: 14)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | tipoCotizante | varchar(50) | NO |  |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | observacion | varchar(5550) | NO |  |  |
| 6 | activo | bit | NO |  |  |
| 7 | fechaRegistro | datetime | NO |  |  |
| 8 | usuario | varchar(50) | NO |  |  |

## `nTablaSmlvRedondeo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | año | int | NO | 🔑 |  |
| 2 | dia | float | NO | 🔑 |  |
| 3 | IBC | float | SI |  |  |
| 4 | pension | float | SI |  |  |
| 5 | saludSena | float | SI |  |  |
| 6 | salud | float | SI |  |  |
| 7 | CCF | float | SI |  |  |
| 8 | Riesgos_6,96% | float | SI |  |  |
| 9 | Riesgos_4,35% | float | SI |  |  |
| 10 | Riesgos_2,436% | float | SI |  |  |
| 11 | Riesgos_1,044% | float | SI |  |  |
| 12 | Riesgos_0,522% | float | SI |  |  |
| 13 | senaEspecial | float | SI |  |  |
| 14 | sena | float | SI |  |  |
| 15 | ICBF | float | SI |  |  |
| 16 | ESAP | float | SI |  |  |
| 17 | MEN | float | SI |  |  |

## `nTablaSmlvRedondeoARP`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | dia | float | NO | 🔑 |  |
| 4 | riesgo | varchar(50) | NO | 🔑 |  |
| 5 | IBC | float | NO |  |  |
| 6 | valor | float | NO |  |  |

## `nTipoConcepto`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | activo | bit | NO |  |  |

## `nTipoConceptoDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipoConcepto | varchar(50) | NO | 🔑 |  |
| 3 | concepto | varchar(50) | NO | 🔑 |  |

## `nTipoCotizante`  (filas: 36)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | observacion | varchar(5550) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | fechaRegistro | datetime | NO |  |  |
| 7 | usuario | varchar(50) | NO |  |  |

## `nTipoIncapacidad`  (filas: 24)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | porcentaje | float | NO |  |  |
| 5 | adicionarPorcentaje | bit | NO |  |  |
| 6 | despues | int | NO |  |  |
| 7 | porcentajeNuevo | float | NO |  |  |
| 8 | activo | bit | NO |  |  |
| 9 | afectaSeguridadSocial | bit | NO |  |  |
| 10 | afectaARL | bit | NO |  |  |
| 11 | afectaNovedadSS | varchar(50) | SI |  |  |
| 12 | promediaIBC | bit | SI |  |  |

## `nTipoNomina`  (filas: 6)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | periocidad | varchar(50) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | observacion | varchar(5550) | NO |  |  |
| 7 | usuario | nchar(10) | NO |  |  |
| 8 | fechaRegistro | datetime | NO |  |  |

## `nTurno`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | horaInicio | int | NO |  |  |
| 5 | horas | int | NO |  |  |
| 6 | activo | bit | NO |  |  |

## `nTurnoDepartamento`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | turno | varchar(50) | NO | 🔑 |  |
| 3 | departamento | varchar(50) | NO | 🔑 |  |
| 4 | activo | bit | NO |  |  |

## `nVacaciones`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | periodoInicial | date | NO | 🔑 |  |
| 3 | periodoFinal | date | NO | 🔑 |  |
| 4 | empleado | int | NO | 🔑 |  |
| 5 | registro | int | NO | 🔑 |  |
| 6 | tipo | varchar(10) | NO |  |  |
| 7 | fechaSalida | date | SI |  |  |
| 8 | fechaRetorno | date | SI |  |  |
| 9 | diasCausados | int | NO |  |  |
| 10 | diasTomados | int | NO |  |  |
| 11 | diasPendientes | int | NO |  |  |
| 12 | diasPagados | int | NO |  |  |
| 13 | valorPagado | decimal(18,2) | NO |  |  |
| 14 | valorBase | decimal(18,2) | NO |  |  |
| 15 | usuario | varchar(50) | NO |  |  |
| 16 | fechaRegistro | datetime | NO |  |  |
| 17 | observaciones | varchar(500) | SI |  |  |
| 18 | anulado | bit | NO |  |  |
| 19 | fechaAnulado | datetime | SI |  |  |
| 20 | usuarioAnulado | varchar(50) | SI |  |  |
| 21 | ejecutado | bit | NO |  |  |
| 22 | pagaNomina | bit | NO |  |  |
| 23 | acumulada | bit | SI |  |  |
| 24 | liquidada | bit | SI |  |  |
| 25 | año | int | SI |  |  |
| 26 | mes | int | SI |  |  |
| 27 | periodo | int | SI |  |  |
| 28 | añoPago | int | SI |  |  |
| 29 | contrato | int | SI |  |  |
| 30 | promedio | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cTercero.empresa`  _(constraint: FK_nVacaciones_cTercero)_
- `empleado` → `cTercero.id`  _(constraint: FK_nVacaciones_cTercero)_

## `nVacacionesDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | periodoInicial | date | NO | 🔑 |  |
| 3 | periodoFinal | date | NO | 🔑 |  |
| 4 | empleado | int | NO | 🔑 |  |
| 5 | registro | int | NO | 🔑 |  |
| 6 | concepto | varchar(50) | NO | 🔑 |  |
| 7 | cantidad | decimal(18,3) | SI |  |  |
| 8 | porcentaje | decimal(18,3) | SI |  |  |
| 9 | valorUnitario | money | SI |  |  |
| 10 | valorTotal | money | SI |  |  |
| 11 | signo | int | SI |  |  |
| 12 | saldo | money | SI |  |  |
| 13 | noDias | int | SI |  |  |
| 14 | baseSeguridadSocial | bit | SI |  |  |
| 15 | baseEmbargos | bit | SI |  |  |
| 16 | entidad | varchar(50) | SI |  |  |
| 17 | noPrestamo | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `concepto` → `nConcepto.codigo`  _(constraint: FK_nVacacionesDetalle_nConcepto)_
- `empresa` → `nConcepto.empresa`  _(constraint: FK_nVacacionesDetalle_nConcepto)_
- `empleado` → `cTercero.id`  _(constraint: FK_nVacacionesDetalle_cTercero)_
- `empresa` → `cTercero.empresa`  _(constraint: FK_nVacacionesDetalle_cTercero)_
