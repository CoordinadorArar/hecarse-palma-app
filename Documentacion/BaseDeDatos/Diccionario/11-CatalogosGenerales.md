# Diccionario de Tablas — Catalogos Generales

_29 tablas en este modulo._

---

## `gBanco`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gBanco_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `cxpProveedorBanco.empresa` → `empresa`
- `cxpProveedorBanco.banco` → `codigo`
- `nPagosNomina.Banco` → `codigo`
- `nPagosNomina.empresa` → `empresa`

## `gCiudad`  (filas: 14676)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | nombre | varchar(150) | NO |  |  |
| 4 | pais | char(5) | NO |  |  |
| 5 | departamento | varchar(50) | SI |  |  |

**Tablas que referencian a esta (hijas):**
- `cTercero.empresa` → `empresa`
- `cTercero.ciudad` → `codigo`

## `gClaseCuenta`  (filas: 18)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(350) | NO |  |  |

## `gCodigoNacionalOcupacion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

## `gConfigParametrosGenerales`  (filas: 11)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | nombre | varchar(150) | NO | 🔑 |  |
| 3 | tipoDato | varchar(50) | NO |  |  |
| 4 | manejaDS | bit | NO |  |  |
| 5 | ds | varchar(150) | SI |  |  |
| 6 | cValor | varchar(150) | SI |  |  |
| 7 | cEtiqueta | varchar(150) | SI |  |  |
| 8 | valor | varchar(8000) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gConfigParametrosGenerales_gEmpresa)_

## `gDepartamento`  (filas: 429)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | SI |  |  |

## `gDiagnostico`  (filas: 38772)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

## `gEmpresa`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 |  |
| 2 | nit | char(15) | NO |  |  |
| 3 | dv | char(1) | NO |  |  |
| 4 | razonSocial | varchar(550) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | fechaRegistro | datetime | NO |  |  |
| 7 | extractora | bit | NO |  |  |
| 8 | tercero | int | SI |  |  |

**Tablas que referencian a esta (hijas):**
- `aFinca.empresa` → `id`
- `aFinca.empresa` → `id`
- `aGrupoNovedad.empresa` → `id`
- `aLotes.empresa` → `id`
- `aLotesCanal.empresa` → `id`
- `aLotesDetalle.empresa` → `id`
- `aNovedad.empresa` → `id`
- `aNovedadLotePrecio.empresa` → `id`
- `aSecciones.empresa` → `id`
- `aTransaccion.empresa` → `id`
- `aTransaccionNovedad.empresa` → `id`
- `aTransaccionTercero.empresa` → `id`
- `aVariedad.empresa` → `id`
- `bProcedencia.empresa` → `id`
- `bProcedenciaCorreos.empresa` → `id`
- `bTipoVehiculo.empresa` → `id`
- `cTercero.empresa` → `id`
- `cxcCliente.empresa` → `id`
- `cxpClaseProveedor.empresa` → `id`
- `cxpProveedor.empresa` → `id`
- `gBanco.empresa` → `id`
- `gConfigParametrosGenerales.empresa` → `id`
- `gTipoCuenta.empresa` → `id`
- `gTipoTransaccion.empresa` → `id`
- `gTipoTransaccionCampo.empresa` → `id`
- `gTipoTransaccionConfig.empresa` → `id`
- `gUnidadMedida.empresa` → `id`
- `iItems.empresa` → `id`
- `iItemsCriterios.empresa` → `id`
- `iMayorItem.empresa` → `id`
- `iPlanItem.empresa` → `id`
- `lAnalisisItem.empresa` → `id`
- `nCargo.empresa` → `id`
- `nCuadrilla.empresa` → `id`
- `nCuadrillaFuncionario.empresa` → `id`
- `nDepartamento.empresa` → `id`
- `nFuncionario.empresa` → `id`
- `pTransaccion.empresa` → `id`
- `pTransaccionDetalle.empresa` → `id`
- `sLogRegistros.empresa` → `id`
- `sUsuarioPerfiles.empresa` → `id`

## `gEntidadNacional`  (filas: 97)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

## `gFormaPago`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | cheque | bit | NO |  |  |

## `gFoto`  (filas: 1)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 | IDENTITY |
| 2 | foto | varbinary(MAX) | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `nFuncionario.foto` → `id`

## `gNivelEducativo`  (filas: 9)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

## `gPais`  (filas: 33)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(150) | NO |  |  |

## `gParametros`  (filas: 1)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 9 | devengoautomatico | varchar(500) | SI |  |  |
| 10 | descuentoautomatico | varchar(500) | SI |  |  |
| 11 | cuentaAlmacenPad | varchar(500) | SI |  |  |
| 12 | auxabiertoPad | varchar(500) | SI |  |  |
| 13 | cuentaAlmacenPad1 | varchar(500) | SI |  |  |
| 14 | auxabiertoPad1 | varchar(500) | SI |  |  |
| 15 | auxabiertoPad2 | varchar(500) | SI |  |  |
| 16 | auxabiertoPad3 | varchar(500) | SI |  |  |
| 18 | cuentaAlmacenPad2 | varchar(500) | SI |  |  |
| 19 | cuentaAlmacenPad3 | varchar(500) | SI |  |  |

## `gParametrosGenerales`  (filas: 1)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | entradas | varchar(50) | SI |  |  |
| 3 | entradasAlt | varchar(50) | SI |  |  |
| 4 | salidas | varchar(50) | SI |  |  |
| 5 | salidasAlt | varchar(50) | SI |  |  |
| 6 | pesajes | varchar(50) | SI |  |  |
| 7 | pesajesAlt | varchar(50) | SI |  |  |
| 8 | fruta | varchar(50) | SI |  |  |
| 9 | frutaAlt | varchar(50) | SI |  |  |
| 10 | almendra | varchar(50) | SI |  |  |
| 11 | almedraAlt | varchar(50) | SI |  |  |
| 12 | nuez | varchar(50) | SI |  |  |
| 13 | nuezAlt | varchar(50) | SI |  |  |
| 14 | crudo | varchar(50) | SI |  |  |
| 15 | crudoAlt | varchar(50) | SI |  |  |
| 16 | palmiste | varchar(50) | SI |  |  |
| 17 | palmisteAlt | varchar(50) | SI |  |  |
| 18 | blanqueado | varchar(50) | SI |  |  |
| 19 | blanqueadoAlt | varchar(50) | SI |  |  |
| 20 | cascarilla | varchar(50) | SI |  |  |
| 21 | cascarillaAlt | varchar(50) | SI |  |  |
| 22 | torta | varchar(50) | SI |  |  |
| 23 | tortaAlt | varchar(50) | SI |  |  |
| 24 | raquiz | varchar(50) | SI |  |  |
| 25 | raquizAlt | varchar(50) | SI |  |  |
| 26 | raquizPrensado | varchar(50) | SI |  |  |
| 27 | raquizPrensadoAlt | varchar(50) | SI |  |  |
| 28 | fibra | varchar(50) | SI |  |  |
| 29 | fibraAlt | varchar(50) | SI |  |  |
| 30 | tiquete | varchar(50) | SI |  |  |
| 31 | tiqueteAlt | varchar(50) | SI |  |  |
| 32 | ordenEnvio | varchar(50) | SI |  |  |
| 33 | ordenEnvioAlt | varchar(50) | SI |  |  |
| 34 | remisionComer | varchar(50) | SI |  |  |
| 35 | remisionComerAlt | varchar(50) | SI |  |  |
| 36 | remisionInt | varchar(50) | SI |  |  |
| 37 | remisionIntAlt | varchar(50) | SI |  |  |
| 38 | ordenSalida | varchar(50) | SI |  |  |
| 39 | ordenSalidaAlt | varchar(50) | SI |  |  |
| 40 | anulado | varchar(50) | SI |  |  |
| 41 | anuladoAlt | varchar(50) | SI |  |  |
| 42 | frutaDura | varchar(50) | SI |  |  |
| 43 | frutaDuraAlt | varchar(50) | SI |  |  |
| 44 | frutaTenera | varchar(50) | SI |  |  |
| 45 | frutaTeneraAlt | varchar(50) | SI |  |  |
| 46 | agl | varchar(50) | SI |  |  |
| 47 | aglAlt | varchar(50) | SI |  |  |
| 48 | humedad | varchar(50) | SI |  |  |
| 49 | humedadAlt | varchar(50) | SI |  |  |
| 50 | impurezas | varchar(50) | SI |  |  |
| 51 | impurezasAlt | varchar(50) | SI |  |  |
| 52 | tablaPesoPromedio | varchar(50) | SI |  |  |
| 53 | ReportService | varchar(250) | SI |  |  |
| 54 | usuarioReporte | varchar(250) | SI |  |  |
| 55 | claveReporte | varchar(50) | SI |  |  |
| 56 | DominioReporte | varchar(50) | SI |  |  |

## `gRegimenTributario`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(1) | NO | 🔑 |  |
| 3 | descripcion | varchar(50) | NO |  |  |

## `gRh`  (filas: 24)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |

## `gTipoCuenta`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(1) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gTipoCuenta_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `cxpProveedorBanco.empresa` → `empresa`
- `cxpProveedorBanco.tipoCuenta` → `codigo`

## `gTipoCuentaBancaria`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |

## `gTipoDocumento`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | descripcionCorta | varchar(50) | NO |  |  |
| 5 | codigoTD | int | NO |  |  |
| 6 | mNit | bit | SI |  |  |
| 7 | equivalencia | varchar(50) | SI |  |  |

## `gTipoEmbargo`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(50) | NO | 🔑 |  |
| 2 | empresa | int | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | activo | bit | NO |  |  |

## `gTipoLiquidacion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | int | NO | 🔑 |  |
| 2 | empresa | int | NO | 🔑 |  |
| 3 | descripcion | varchar(200) | NO |  |  |

## `gTipoTransaccion`  (filas: 21)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(50) | NO |  |  |
| 4 | numeracion | bit | SI |  |  |
| 5 | actual | int | SI |  |  |
| 6 | prefijo | varchar(50) | SI |  |  |
| 7 | longitud | int | SI |  |  |
| 8 | naturaleza | int | NO |  |  |
| 9 | modulo | varchar(50) | NO |  |  |
| 10 | modoAnulacion | char(1) | NO |  |  |
| 11 | referencia | bit | NO |  |  |
| 12 | vistaDs | varchar(250) | SI |  |  |
| 13 | activo | bit | NO |  |  |
| 14 | fechaRegistro | datetime | NO |  |  |
| 15 | forma | varchar(50) | SI |  |  |
| 16 | formato | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gTipoTransaccion_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `bRegistroPorteria.empresa` → `empresa`
- `bRegistroPorteria.tipo` → `codigo`
- `gTipoTransaccionCampo.empresa` → `empresa`
- `gTipoTransaccionCampo.tipoTransaccion` → `codigo`
- `gTipoTransaccionConfig.empresa` → `empresa`
- `gTipoTransaccionConfig.tipoTransaccion` → `codigo`

## `gTipoTransaccionCampo`  (filas: 170)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipoTransaccion | varchar(50) | NO | 🔑 |  |
| 3 | entidad | varchar(250) | NO | 🔑 |  |
| 4 | campo | varchar(250) | NO | 🔑 |  |
| 5 | tipoCampo | varchar(50) | NO |  |  |
| 6 | tercero | bit | NO |  |  |
| 7 | aplicaCliente | bit | NO |  |  |
| 8 | aplicaProveedor | bit | NO |  |  |
| 9 | aplicaTercero | bit | NO |  |  |
| 10 | terceroDefecto | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gTipoTransaccionCampo_gEmpresa)_
- `empresa` → `gTipoTransaccion.empresa`  _(constraint: FK_gTipoTransaccionCampo_gTipoTransaccion)_
- `tipoTransaccion` → `gTipoTransaccion.codigo`  _(constraint: FK_gTipoTransaccionCampo_gTipoTransaccion)_

## `gTipoTransaccionConcurrencia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | transaccion | varchar(50) | NO | 🔑 |  |
| 2 | empresa | int | NO | 🔑 |  |
| 3 | concurrencia | char(1) | NO |  |  |
| 4 | control | bit | NO |  |  |

## `gTipoTransaccionConfig`  (filas: 10)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipoTransaccion | varchar(50) | NO | 🔑 |  |
| 3 | ajuste | bit | NO |  |  |
| 4 | nivelDestino | int | NO |  |  |
| 5 | salida | bit | NO |  |  |
| 6 | validaSaldo | bit | NO |  |  |
| 7 | manejaTalonario | bit | NO |  |  |
| 8 | referenciaTercero | bit | NO |  |  |
| 9 | formatoImpresion | varchar(250) | NO |  |  |
| 10 | dsReferenciaDetalle | varchar(250) | NO |  |  |
| 11 | cantidadEditable | bit | NO |  |  |
| 12 | vUnitarioEditable | bit | NO |  |  |
| 13 | pIvaEditable | bit | NO |  |  |
| 14 | manejaBodega | bit | NO |  |  |
| 15 | liberaReferencia | bit | NO |  |  |
| 16 | entradaDirecta | bit | NO |  |  |
| 17 | registroDirecto | bit | NO |  |  |
| 18 | consignacion | bit | NO |  |  |
| 19 | diaSemana | bit | NO |  |  |
| 20 | manejaDocumento | bit | NO |  |  |
| 21 | vigencia | bit | NO |  |  |
| 22 | pDesEditable | bit | NO |  |  |
| 23 | UmedidaEditable | bit | NO |  |  |
| 24 | estudioCompra | bit | NO |  |  |
| 25 | registroProveedor | bit | NO |  |  |
| 26 | fechaActual | bit | NO |  |  |
| 28 | manejaBascula | bit | NO |  |  |
| 29 | mTipoLiquidacionNomina | bit | SI |  |  |
| 30 | tipoLiquidacionNomina | varchar(50) | SI |  |  |
| 31 | mtercero | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gTipoTransaccionConfig_gEmpresa)_
- `empresa` → `gTipoTransaccion.empresa`  _(constraint: FK_gTipoTransaccionConfig_gTipoTransaccion)_
- `tipoTransaccion` → `gTipoTransaccion.codigo`  _(constraint: FK_gTipoTransaccionConfig_gTipoTransaccion)_

## `gTipoTransaccionDias`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | lunes | bit | NO |  |  |
| 4 | martes | bit | NO |  |  |
| 5 | miercoles | bit | NO |  |  |
| 6 | jueves | bit | NO |  |  |
| 7 | viernes | bit | NO |  |  |
| 8 | sabado | bit | NO |  |  |
| 9 | domingo | bit | NO |  |  |

## `gTipoTransaccionProducto`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | producto | varchar(50) | NO | 🔑 |  |

## `gUnidadMedida`  (filas: 8)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(50) | NO |  |  |
| 4 | desCorta | char(3) | NO |  |  |
| 5 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_gUnidadMedida_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `aNovedad.empresa` → `empresa`
- `aNovedad.uMedida` → `codigo`
- `aTransaccionNovedad.empresa` → `empresa`
- `aTransaccionNovedad.uMedida` → `codigo`
- `iItems.empresa` → `empresa`
- `iItems.uMedidaCompra` → `codigo`
