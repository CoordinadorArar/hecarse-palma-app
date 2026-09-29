# Diccionario de Tablas — Contabilidad

_16 tablas en este modulo._

---

## `cCentrosCosto`  (filas: 234)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | nivel | int | NO |  |  |
| 4 | nivelMayor | int | SI |  |  |
| 5 | mayor | varchar(50) | SI |  |  |
| 6 | descripcion | varchar(350) | NO |  |  |
| 7 | responsable | varchar(150) | NO |  |  |
| 8 | grupo | varchar(50) | SI |  |  |
| 9 | manejaHE | bit | NO |  |  |
| 10 | manejaLC | bit | NO |  |  |
| 11 | activo | bit | NO |  |  |
| 12 | auxiliar | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cGrupoCCosto.empresa`  _(constraint: FK_cCentrosCosto_cGrupoCCosto)_
- `grupo` → `cGrupoCCosto.codigo`  _(constraint: FK_cCentrosCosto_cGrupoCCosto)_

**Tablas que referencian a esta (hijas):**
- `nConceptosFijosDetalle.centroCosto` → `codigo`
- `nConceptosFijosDetalle.empresa` → `empresa`
- `nDepartamento.empresa` → `empresa`
- `nDepartamento.ccosto` → `codigo`
- `nLiquidacionNominaDetalle.empresa` → `empresa`
- `nLiquidacionNominaDetalle.ccosto` → `codigo`

## `cCentrosCostoSigo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO |  |  |
| 2 | codigo | varchar(50) | NO |  |  |
| 3 | nivel | int | NO |  |  |
| 4 | nivelMayor | int | SI |  |  |
| 5 | mayor | varchar(50) | SI |  |  |
| 6 | descripcion | varchar(350) | NO |  |  |
| 7 | activo | bit | NO |  |  |
| 8 | auxiliar | bit | NO |  |  |

## `cClaseIR`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | int | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | sigla | varchar(5) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | retencion | bit | NO |  |  |
| 7 | impuesto | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `cConceptoIR.empresa` → `empresa`
- `cConceptoIR.clase` → `codigo`

## `cClaseParametroContaNomi`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(500) | NO |  |  |
| 4 | tipo | varchar(50) | NO |  |  |
| 5 | tipoDocumento | varchar(50) | NO |  |  |
| 6 | comprobante | varchar(50) | NO |  |  |
| 7 | cuentaPuente | varchar(50) | NO |  |  |
| 8 | cuentaCruce | varchar(50) | SI |  |  |
| 9 | ccostoMayor | varchar(50) | SI |  |  |
| 10 | ccosto | varchar(50) | SI |  |  |
| 11 | porTercero | bit | NO |  |  |
| 12 | porCuenta | bit | NO |  |  |
| 13 | porCentroCosto | bit | NO |  |  |
| 14 | activo | bit | NO |  |  |

## `cConceptoIR`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | clase | int | NO |  |  |
| 4 | descripcion | varchar(950) | NO |  |  |
| 5 | calculo | char(1) | NO |  |  |
| 6 | baseGravable | float | NO |  |  |
| 7 | tasa | float | NO |  |  |
| 8 | baseMinima | money | NO |  |  |
| 9 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `cClaseIR.empresa`  _(constraint: FK_cConceptoIR_cClaseIR)_
- `clase` → `cClaseIR.codigo`  _(constraint: FK_cConceptoIR_cClaseIR)_

## `cContabilizacion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | tipoLiquidacion | varchar(50) | NO | 🔑 |  |
| 5 | año | int | NO | 🔑 |  |
| 6 | mes | int | NO | 🔑 |  |
| 7 | periodoContable | varchar(50) | NO | 🔑 |  |
| 8 | periodoNomina | varchar(50) | SI |  |  |
| 9 | estado | int | NO |  |  |
| 10 | fecha | datetime | NO |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |
| 12 | usuarioRegistro | varchar(50) | NO |  |  |
| 13 | observacion | varchar(500) | NO |  |  |
| 14 | anulado | bit | NO |  |  |
| 15 | fechaAnulado | datetime | SI |  |  |
| 16 | usuarioAnulado | varchar(50) | SI |  |  |

## `cContabilizacionDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | tipoLiquidacion | varchar(50) | NO | 🔑 |  |
| 5 | año | int | NO | 🔑 |  |
| 6 | mes | int | NO | 🔑 |  |
| 7 | periodoContable | varchar(50) | NO | 🔑 |  |
| 8 | registro | int | NO | 🔑 |  |
| 9 | codigoEmpleado | int | NO |  |  |
| 10 | identificacionEmpleado | varchar(50) | NO |  |  |
| 11 | tipoNomina | varchar(50) | SI |  |  |
| 12 | docNomina | varchar(50) | SI |  |  |
| 13 | contrato | int | NO |  |  |
| 14 | periodoNomina | varchar(50) | SI |  |  |
| 15 | claseContrato | varchar(50) | SI |  |  |
| 16 | manejaLabCam | bit | SI |  |  |
| 17 | manejaHE | bit | SI |  |  |
| 18 | mCcostoNomina | varchar(50) | SI |  |  |
| 19 | aCcostoNomina | varchar(50) | SI |  |  |
| 20 | departamento | varchar(50) | SI |  |  |
| 21 | codigoConcepto | varchar(50) | NO |  |  |
| 22 | codigoLabor | varchar(50) | SI |  |  |
| 23 | cuentaContable | varchar(50) | SI |  |  |
| 24 | mCcostoContable | varchar(50) | SI |  |  |
| 25 | aCcostoContable | varchar(50) | SI |  |  |
| 26 | terceroContable | varchar(50) | SI |  |  |
| 27 | debito | float | SI |  |  |
| 28 | credito | float | SI |  |  |
| 29 | entidadSalud | varchar(50) | SI |  |  |
| 30 | entidadPension | varchar(50) | SI |  |  |
| 31 | entidadArl | varchar(50) | SI |  |  |
| 32 | entidadCaja | varchar(50) | SI |  |  |
| 33 | entidadSena | varchar(50) | SI |  |  |
| 34 | entidadCesantias | varchar(50) | SI |  |  |
| 35 | EntidadFsolidaridad | varchar(50) | SI |  |  |
| 36 | EntidadICBF | varchar(50) | SI |  |  |
| 37 | EntidadAdicional | varchar(50) | SI |  |  |
| 38 | mCcostoLote | varchar(50) | SI |  |  |
| 39 | aCcostoLote | varchar(50) | SI |  |  |
| 40 | LoteDesarrollo | bit | SI |  |  |
| 41 | baseCesantias | bit | SI |  |  |
| 42 | basePrimas | bit | SI |  |  |
| 43 | baseIntereses | bit | SI |  |  |
| 44 | baseVacaciones | bit | SI |  |  |
| 45 | baseEmbargos | bit | SI |  |  |
| 46 | baseCajaCompensacion | bit | SI |  |  |
| 47 | baseSeguridadSocial | bit | SI |  |  |
| 48 | tipoConcepto | varchar(50) | SI |  |  |
| 49 | conceptoReferencia | varchar(50) | SI |  |  |
| 50 | estado | int | NO |  |  |
| 51 | fecha | datetime | NO |  |  |
| 52 | fechaRegistro | datetime | NO |  |  |
| 53 | usuarioRegistro | varchar(50) | NO |  |  |

## `cEstructuraCCosto`  (filas: 38)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | nivel | int | NO | 🔑 |  |
| 3 | inicio | int | NO |  |  |
| 4 | tamaño | int | NO |  |  |
| 5 | total | int | NO |  |  |
| 6 | descripcion | varchar(550) | NO |  |  |
| 7 | activo | bit | NO |  |  |

## `cGrupoCCosto`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `cCentrosCosto.empresa` → `empresa`
- `cCentrosCosto.grupo` → `codigo`

## `cGrupoConceptoIR`  (filas: 3)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | grupo | char(5) | NO | 🔑 |  |
| 3 | cocepto | char(5) | NO | 🔑 |  |

## `cGrupoIR`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(5) | NO | 🔑 |  |
| 4 | descripcion | varchar(950) | NO |  |  |
| 5 | observacion | varchar(1550) | NO |  |  |
| 6 | activo | bit | NO |  |  |

## `cParametroContaNomi`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | id | int | NO | 🔑 |  |
| 3 | tipo | varchar(50) | NO |  |  |
| 4 | clase | varchar(50) | NO |  |  |
| 5 | tipoTransaccion | varchar(50) | NO |  |  |
| 6 | cCostoMayor | varchar(50) | NO |  |  |
| 7 | cCosto | varchar(50) | NO |  |  |
| 8 | departamento | varchar(50) | SI |  |  |
| 9 | concepto | varchar(50) | SI |  |  |
| 10 | manejaEntidad | bit | SI |  |  |
| 11 | cuentaActivo | varchar(50) | SI |  |  |
| 12 | cuentaGasto | varchar(50) | SI |  |  |
| 13 | cuentaContratista | varchar(50) | SI |  |  |
| 14 | cCostoMayorSigo | varchar(50) | SI |  |  |
| 15 | cCostoSigo | varchar(50) | SI |  |  |
| 16 | cuentaCredito | varchar(50) | SI |  |  |
| 17 | cCostoMayorCredito | varchar(50) | SI |  |  |
| 18 | cCostoCredito | varchar(50) | SI |  |  |
| 19 | tipoDato | int | SI |  |  |
| 20 | valorTipoDato | decimal(18,3) | SI |  |  |
| 21 | entidad | varchar(50) | SI |  |  |
| 22 | labor | bit | SI |  |  |
| 23 | tercero | varchar(50) | SI |  |  |
| 24 | terceroCredito | varchar(50) | SI |  |  |

## `cPeriodo`  (filas: 99)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | SI |  |  |
| 5 | periodo | varchar(6) | SI |  |  |
| 6 | cerrado | bit | NO |  |  |
| 7 | fechaInicial | date | SI |  |  |
| 8 | fechaFinal | date | SI |  |  |

## `cPrecontabilizacion`  (filas: 101212)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | periodoContable | varchar(50) | NO | 🔑 |  |
| 6 | registro | int | NO | 🔑 |  |
| 7 | codigoEmpleado | int | NO |  |  |
| 8 | identificacionEmpleado | varchar(50) | NO |  |  |
| 9 | tipoNomina | varchar(50) | SI |  |  |
| 10 | docNomina | varchar(50) | SI |  |  |
| 11 | contrato | int | NO |  |  |
| 12 | periodoNomina | varchar(50) | SI |  |  |
| 13 | claseContrato | varchar(50) | SI |  |  |
| 14 | manejaLabCam | bit | SI |  |  |
| 15 | manejaHE | bit | SI |  |  |
| 16 | mCcostoNomina | varchar(50) | SI |  |  |
| 17 | aCcostoNomina | varchar(50) | SI |  |  |
| 18 | departamento | varchar(50) | SI |  |  |
| 19 | codigoConcepto | varchar(50) | NO |  |  |
| 20 | codigoLabor | varchar(50) | SI |  |  |
| 21 | cuentaContable | varchar(50) | SI |  |  |
| 22 | mCcostoContable | varchar(50) | SI |  |  |
| 23 | aCcostoContable | varchar(50) | SI |  |  |
| 24 | terceroContable | varchar(50) | SI |  |  |
| 25 | debito | float | SI |  |  |
| 26 | credito | float | SI |  |  |
| 27 | entidadSalud | varchar(50) | SI |  |  |
| 28 | entidadPension | varchar(50) | SI |  |  |
| 29 | entidadArl | varchar(50) | SI |  |  |
| 30 | entidadCaja | varchar(50) | SI |  |  |
| 31 | entidadSena | varchar(50) | SI |  |  |
| 32 | entidadCesantias | varchar(50) | SI |  |  |
| 33 | EntidadFsolidaridad | varchar(50) | SI |  |  |
| 34 | EntidadICBF | varchar(50) | SI |  |  |
| 35 | EntidadAdicional | varchar(50) | SI |  |  |
| 36 | mCcostoLote | varchar(50) | SI |  |  |
| 37 | aCcostoLote | varchar(50) | SI |  |  |
| 38 | LoteDesarrollo | bit | SI |  |  |
| 39 | baseCesantias | bit | SI |  |  |
| 40 | basePrimas | bit | SI |  |  |
| 41 | baseIntereses | bit | SI |  |  |
| 42 | baseVacaciones | bit | SI |  |  |
| 43 | baseEmbargos | bit | SI |  |  |
| 44 | baseCajaCompensacion | bit | SI |  |  |
| 45 | baseSeguridadSocial | bit | SI |  |  |
| 46 | tipoConcepto | varchar(50) | SI |  |  |
| 47 | conceptoReferencia | varchar(50) | SI |  |  |
| 48 | estado | int | NO |  |  |
| 49 | fecha | datetime | NO |  |  |
| 50 | fechaRegistro | datetime | NO |  |  |
| 51 | usuarioRegistro | varchar(50) | NO |  |  |

## `cPuc`  (filas: 1392)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(16) | NO | 🔑 |  |
| 3 | raiz | varchar(16) | NO |  |  |
| 4 | nombre | varchar(150) | NO |  |  |
| 5 | naturaleza | char(1) | NO |  |  |
| 6 | nivel | int | NO |  |  |
| 7 | tipo | char(1) | NO |  |  |
| 8 | tercero | bit | NO |  |  |
| 9 | cCosto | bit | NO |  |  |
| 10 | base | bit | NO |  |  |
| 11 | activo | bit | NO |  |  |
| 12 | clase | varchar(50) | NO |  |  |
| 13 | banco | varchar(50) | SI |  |  |

## `cTercero`  (filas: 406)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | id | int | NO | 🔑 |  |
| 3 | codigo | varchar(50) | NO |  |  |
| 4 | tipoDocumento | char(3) | SI |  |  |
| 5 | tipo | int | NO |  |  |
| 6 | nit | varchar(25) | NO |  |  |
| 7 | dv | char(3) | SI |  |  |
| 8 | razonSocial | varchar(550) | SI |  |  |
| 9 | apellido1 | varchar(250) | NO |  |  |
| 10 | apellido2 | varchar(250) | NO |  |  |
| 11 | nombre1 | varchar(550) | NO |  |  |
| 12 | nombre2 | varchar(250) | SI |  |  |
| 13 | descripcion | varchar(950) | SI |  |  |
| 14 | activo | bit | NO |  |  |
| 15 | ciudad | varchar(50) | SI |  |  |
| 16 | cliente | bit | NO |  |  |
| 17 | proveedor | bit | NO |  |  |
| 18 | empleado | bit | NO |  |  |
| 19 | accionista | bit | NO |  |  |
| 20 | contratista | bit | NO |  |  |
| 21 | extractora | bit | NO |  |  |
| 22 | foto | int | SI |  |  |
| 23 | contacto | varchar(550) | SI |  |  |
| 24 | fechaRegistro | datetime | NO |  |  |
| 25 | telefono | varchar(50) | SI |  |  |
| 26 | direccion | varchar(550) | SI |  |  |
| 27 | barrio | varchar(550) | SI |  |  |
| 28 | fax | varchar(50) | SI |  |  |
| 29 | email | varchar(250) | SI |  |  |
| 30 | comercializadora | bit | SI |  |  |
| 31 | departamento | varchar(50) | SI |  |  |
| 32 | codigoEquivalencia | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gCiudad.empresa`  _(constraint: FK_cTercero_gCiudad)_
- `ciudad` → `gCiudad.codigo`  _(constraint: FK_cTercero_gCiudad)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_cTercero_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `nEntidadAfc.empresa` → `empresa`
- `nEntidadAfc.tercero` → `id`
- `nEntidadFondo.empresa` → `empresa`
- `nEntidadFondo.tercero` → `id`
- `nEntidadFondoPension.empresa` → `empresa`
- `nEntidadFondoPension.tercero` → `id`
- `nEntidadIcbf.empresa` → `empresa`
- `nEntidadIcbf.tercero` → `id`
- `nEntidadSena.empresa` → `empresa`
- `nEntidadSena.tercero` → `id`
- `nLiquidacionNominaDetalle.empresa` → `empresa`
- `nLiquidacionNominaDetalle.tercero` → `id`
- `nPagosNominaDetalle.tercero` → `id`
- `nPagosNominaDetalle.empresa` → `empresa`
- `nVacaciones.empresa` → `empresa`
- `nVacaciones.empleado` → `id`
- `nVacacionesDetalle.empleado` → `id`
- `nVacacionesDetalle.empresa` → `empresa`
