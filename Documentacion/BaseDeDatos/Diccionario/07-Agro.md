# Diccionario de Tablas — Agro (Fincas, Lotes, Transacciones de campo)

_31 tablas en este modulo._

---

## `aCaracteristica`  (filas: 100)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | int | NO | 🔑 |  |
| 3 | descripcion | varchar(500) | NO |  |  |
| 4 | manejaCaractistica | bit | NO |  |  |
| 5 | grupoCaracteristica | int | SI |  |  |
| 6 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `aSanidadDetalle.caracteristica` → `codigo`
- `aSanidadDetalle.empresa` → `empresa`

## `aFinca`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | proveedor | int | SI |  |  |
| 5 | ciudad | char(5) | SI |  |  |
| 6 | activo | bit | NO |  |  |
| 7 | interna | bit | NO |  |  |
| 8 | zonaGeografica | varchar(550) | SI |  |  |
| 9 | hectareas | float | NO |  |  |
| 10 | fechaRegistro | date | NO |  |  |
| 11 | usuarioRegistro | varchar(50) | NO |  |  |
| 12 | codigoEquivalencia | varchar(50) | SI |  |  |
| 13 | ubicacionGeografica | varchar(550) | SI |  |  |
| 14 | diatanciaPlanta | float | SI |  |  |
| 15 | certificada | bit | SI |  |  |
| 16 | agrupadoPor | varchar(50) | SI |  |  |
| 17 | certificadoMayor | varchar(50) | SI |  |  |
| 18 | socio | bit | SI |  |  |
| 19 | centroOperacion | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aFinca_gEmpresa)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_aFinca_gEmpresa1)_

**Tablas que referencian a esta (hijas):**
- `aLotes.empresa` → `empresa`
- `aLotes.finca` → `codigo`
- `aSanidad.empresa` → `empresa`
- `aSanidad.finca` → `codigo`
- `aSecciones.empresa` → `empresa`
- `aSecciones.finca` → `codigo`
- `aTransaccion.empresa` → `empresa`
- `aTransaccion.finca` → `codigo`

## `aGrupoCaracteristica`  (filas: 6)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | int | NO | 🔑 |  |
| 3 | descripcion | varchar(500) | NO |  |  |
| 4 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `aSanidadDetalle.empresa` → `empresa`
- `aSanidadDetalle.grupoCaracteristica` → `codigo`

## `aGrupoNovedad`  (filas: 6)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(50) | NO |  |  |
| 4 | activo | bit | NO |  |  |
| 5 | ccosto | varchar(50) | SI |  |  |
| 6 | manejaCcostoSiigo | bit | SI |  |  |
| 7 | ccostoSiigo | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aGrupoNovedad_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `aNovedad.grupo` → `codigo`
- `aNovedad.empresa` → `empresa`

## `aLogReliquidacion`  (filas: 3)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | fechaInicial | date | NO | 🔑 |  |
| 3 | fechaFinal | date | NO | 🔑 |  |
| 4 | usuario | varchar(50) | NO | 🔑 |  |
| 5 | fechaRegistro | datetime | NO | 🔑 |  |

## `aLoteCcostoSigo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | lote | varchar(50) | NO | 🔑 |  |
| 3 | mCcostoSigo | varchar(50) | NO |  |  |
| 4 | aCcostoSigo | varchar(50) | NO |  |  |

## `aLotePesosPeriodo`  (filas: 10918)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | finca | varchar(50) | NO | 🔑 |  |
| 5 | seccion | varchar(50) | SI |  |  |
| 6 | lote | varchar(50) | NO | 🔑 |  |
| 7 | pesoRacimo | decimal(18,3) | NO |  |  |
| 8 | automatico | bit | NO |  |  |
| 9 | fechaInicial | date | SI |  |  |
| 10 | fechaFinal | date | SI |  |  |

## `aLotes`  (filas: 105)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | manejaSeccion | bit | NO |  |  |
| 4 | seccion | varchar(50) | SI |  |  |
| 5 | finca | varchar(50) | NO | 🔑 |  |
| 6 | descripcion | varchar(550) | NO |  |  |
| 7 | añoSiembra | int | NO |  |  |
| 8 | mesSiembra | int | NO |  |  |
| 9 | palmasBrutas | int | NO |  |  |
| 10 | palmasProduccion | int | NO |  |  |
| 11 | hBrutas | decimal(18,2) | NO |  |  |
| 12 | hNetas | decimal(18,2) | NO |  |  |
| 13 | dSiembra | decimal(18,2) | NO |  |  |
| 14 | variedad | char(5) | NO |  |  |
| 15 | densidad | decimal(18,2) | NO |  |  |
| 16 | NoLineas | int | NO |  |  |
| 17 | fechaRegistro | datetime | NO |  |  |
| 18 | usuario | varchar(50) | NO |  |  |
| 19 | foto | varchar(1550) | SI |  |  |
| 20 | activo | bit | NO |  |  |
| 21 | desarrollo | bit | NO |  |  |
| 22 | numero | int | SI |  |  |
| 23 | letra | varchar(50) | SI |  |  |
| 25 | ccosto | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `aFinca.empresa`  _(constraint: FK_aLotes_aFinca)_
- `finca` → `aFinca.codigo`  _(constraint: FK_aLotes_aFinca)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_aLotes_gEmpresa)_
- `empresa` → `aSecciones.empresa`  _(constraint: FK_aLotes_aSecciones)_
- `seccion` → `aSecciones.codigo`  _(constraint: FK_aLotes_aSecciones)_
- `finca` → `aSecciones.finca`  _(constraint: FK_aLotes_aSecciones)_
- `empresa` → `aVariedad.empresa`  _(constraint: FK_aLotes_aVariedad)_
- `variedad` → `aVariedad.codigo`  _(constraint: FK_aLotes_aVariedad)_

## `aLotesCanal`  (filas: 109)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | lote | varchar(50) | NO | 🔑 |  |
| 3 | registro | int | NO | 🔑 |  |
| 4 | tipoCanal | varchar(10) | NO | 🔑 |  |
| 5 | metros | float | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aLotesCanal_gEmpresa)_
- `empresa` → `aTipoCanal.empresa`  _(constraint: FK_aLotesCanal_aTipoCanal)_
- `tipoCanal` → `aTipoCanal.codigo`  _(constraint: FK_aLotesCanal_aTipoCanal)_

## `aLotesDetalle`  (filas: 6121)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | lote | varchar(50) | NO | 🔑 |  |
| 3 | linea | int | NO | 🔑 |  |
| 4 | finca | varchar(50) | NO | 🔑 |  |
| 5 | noPalma | int | NO |  |  |
| 6 | izquierda | bit | NO |  |  |
| 7 | palmaErradicada | int | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aLotesDetalle_gEmpresa)_

## `aMovimientoLotes`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | tipo | varchar(50) | NO | 🔑 |  |
| 2 | numero | varchar(50) | NO | 🔑 |  |
| 3 | registroT | int | NO | 🔑 |  |
| 4 | empresa | int | NO | 🔑 |  |
| 5 | codigo | varchar(50) | NO | 🔑 |  |
| 6 | registro | int | NO | 🔑 |  |
| 7 | seccion | varchar(50) | SI |  |  |
| 8 | finca | varchar(50) | SI |  |  |
| 9 | añoSiembra | int | SI |  |  |
| 10 | mesSiembra | int | SI |  |  |
| 11 | palmasBrutas | int | SI |  |  |
| 12 | palmasProduccion | int | SI |  |  |
| 13 | hBrutas | decimal(18,2) | SI |  |  |
| 14 | hNetas | decimal(18,2) | SI |  |  |
| 15 | dSiembra | decimal(18,2) | SI |  |  |
| 16 | variedad | char(5) | SI |  |  |
| 17 | densidad | decimal(18,2) | SI |  |  |
| 18 | NoLineas | int | SI |  |  |
| 19 | fechaRegistro | datetime | SI |  |  |
| 20 | usuario | varchar(50) | SI |  |  |
| 21 | desarrollo | bit | SI |  |  |

## `aNovedad`  (filas: 144)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(200) | NO |  |  |
| 4 | desCorta | varchar(50) | NO |  |  |
| 5 | grupo | varchar(50) | NO |  |  |
| 6 | uMedida | varchar(50) | NO |  |  |
| 7 | ciclos | int | NO |  |  |
| 8 | tarea | int | NO |  |  |
| 9 | naturaleza | int | NO |  |  |
| 10 | impuesto | bit | NO |  |  |
| 11 | equivalencia | varchar(50) | SI |  |  |
| 12 | concepto | varchar(50) | NO | 🔑 |  |
| 13 | grupoIR | char(5) | SI |  |  |
| 14 | manejaLote | bit | NO |  |  |
| 15 | manejaSaldo | bit | NO |  |  |
| 16 | manejaCanal | bit | NO |  |  |
| 17 | manejaLinea | bit | NO |  |  |
| 18 | manejaPalma | bit | NO |  |  |
| 19 | tipoCanal | varchar(10) | SI |  |  |
| 20 | manejaRacimo | bit | NO |  |  |
| 21 | manejaJornal | bit | NO |  |  |
| 22 | porHaNeta | bit | NO |  |  |
| 23 | porHaBruta | bit | NO |  |  |
| 24 | porHaProduccion | bit | NO |  |  |
| 25 | manejaBascula | bit | NO |  |  |
| 26 | manejaFecha | bit | NO |  |  |
| 27 | manejaRango | bit | NO |  |  |
| 28 | añoDesde | int | SI |  |  |
| 29 | añoHasta | int | SI |  |  |
| 30 | activo | bit | NO |  |  |
| 31 | fechaRegistro | datetime | NO |  |  |
| 32 | usuario | varchar(50) | NO |  |  |
| 33 | claseLabor | int | SI |  |  |
| 34 | manejaDecimal | bit | NO |  |  |
| 35 | noPrestacional | bit | NO |  |  |
| 36 | manejaCaracteristica | bit | NO |  |  |
| 37 | muestraInforme | bit | SI |  |  |
| 38 | muestraInformeContratista | bit | SI |  |  |
| 39 | calculaJornal | bit | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gUnidadMedida.empresa`  _(constraint: FK_aNovedad_gUnidadMedida)_
- `uMedida` → `gUnidadMedida.codigo`  _(constraint: FK_aNovedad_gUnidadMedida)_
- `empresa` → `nConcepto.empresa`  _(constraint: FK_aNovedad_nConcepto)_
- `concepto` → `nConcepto.codigo`  _(constraint: FK_aNovedad_nConcepto)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_aNovedad_gEmpresa)_
- `tipoCanal` → `aTipoCanal.codigo`  _(constraint: FK_aNovedad_aTipoCanal)_
- `empresa` → `aTipoCanal.empresa`  _(constraint: FK_aNovedad_aTipoCanal)_
- `grupo` → `aGrupoNovedad.codigo`  _(constraint: FK_aNovedad_aGrupoNovedad)_
- `empresa` → `aGrupoNovedad.empresa`  _(constraint: FK_aNovedad_aGrupoNovedad)_

## `aNovedadFincaCcosto`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | novedad | varchar(50) | NO | 🔑 |  |
| 3 | finca | varchar(50) | NO | 🔑 |  |
| 4 | mayorCcostoC | varchar(50) | NO |  |  |
| 5 | ccostoContable | varchar(50) | NO |  |  |

## `aNovedadLotePrecio`  (filas: 774)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | varchar(50) | NO | 🔑 |  |
| 3 | novedad | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | precioDestajo | money | NO |  |  |
| 6 | precioContratistas | money | NO |  |  |
| 7 | precioOtros | money | NO |  |  |
| 8 | porcentaje | decimal(18,3) | NO |  |  |
| 9 | fechaRegistro | datetime | NO |  |  |
| 10 | usuario | varchar(50) | NO |  |  |
| 11 | modificado | bit | NO |  |  |
| 12 | baseSueldo | bit | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aNovedadLotePrecio_gEmpresa)_

## `aNovedadPrecio`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | varchar(50) | NO | 🔑 |  |
| 3 | novedad | varchar(50) | NO | 🔑 |  |
| 4 | finca | varchar(50) | NO | 🔑 |  |
| 5 | lote | varchar(50) | NO | 🔑 |  |
| 6 | seccion | varchar(50) | NO | 🔑 |  |
| 7 | precioDestajo | money | NO |  |  |
| 8 | precioContratistas | money | NO |  |  |
| 9 | precioOtros | money | NO |  |  |
| 10 | porcentaje | float | NO |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |
| 12 | usuario | varchar(50) | NO |  |  |
| 13 | modificado | bit | NO |  |  |
| 14 | baseSueldo | bit | SI |  |  |

## `aParametrosDescuento`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | finca | varchar(50) | NO | 🔑 |  |
| 3 | novedadBase | varchar(50) | NO | 🔑 |  |
| 4 | novedadAplicar | varchar(50) | SI |  |  |
| 5 | mPorcentaje | bit | SI |  |  |
| 6 | porcentaje | float | SI |  |  |
| 7 | mCantidad | bit | SI |  |  |
| 8 | cantidad | float | SI |  |  |
| 9 | mValor | bit | SI |  |  |
| 10 | valor | float | SI |  |  |
| 11 | fechaInicio | date | SI |  |  |
| 12 | retencion | float | SI |  |  |
| 13 | activo | bit | SI |  |  |
| 14 | mSeccion | bit | SI |  |  |
| 15 | seccion | varchar(50) | NO | 🔑 |  |
| 16 | mLote | bit | SI |  |  |
| 17 | lote | varchar(50) | NO | 🔑 |  |
| 18 | mNovedadFestiva | bit | NO |  |  |
| 19 | novedadFestiva | varchar(50) | SI |  |  |

## `aPeriodo`  (filas: 221)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | periodo | varchar(6) | NO |  |  |
| 6 | cerrado | bit | NO |  |  |

## `aSanidad`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | fecha | date | NO |  |  |
| 5 | lote | varchar(50) | NO |  |  |
| 6 | finca | varchar(50) | NO |  |  |
| 7 | seccion | char(3) | SI |  |  |
| 8 | remision | varchar(50) | SI |  |  |
| 9 | nota | varchar(950) | NO |  |  |
| 10 | referencia | varchar(50) | SI |  |  |
| 11 | usuario | varchar(50) | NO |  |  |
| 12 | fechaRegistro | datetime | NO |  |  |
| 13 | anulado | bit | SI |  |  |
| 14 | usuarioAnulado | varchar(50) | SI |  |  |
| 15 | fechaAnulado | datetime | SI |  |  |
| 16 | ejecutado | bit | NO |  |  |
| 17 | aprobado | bit | NO |  |  |
| 18 | usuarioAprobado | varchar(50) | SI |  |  |
| 19 | fechaAprobado | datetime | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `aFinca.empresa`  _(constraint: FK_aSanidad_aFinca)_
- `finca` → `aFinca.codigo`  _(constraint: FK_aSanidad_aFinca)_

**Tablas que referencian a esta (hijas):**
- `aSanidadDetalle.empresa` → `empresa`
- `aSanidadDetalle.tipo` → `tipo`
- `aSanidadDetalle.numero` → `numero`

## `aSanidadDetalle`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | fecha | date | NO |  |  |
| 6 | linea | int | SI |  |  |
| 7 | palma | int | SI |  |  |
| 8 | item | varchar(50) | NO |  |  |
| 9 | cantidad | decimal(18,3) | SI |  |  |
| 10 | uMedida | varchar(50) | SI |  |  |
| 11 | detalle | varchar(550) | NO |  |  |
| 12 | ejecutado | bit | SI |  |  |
| 13 | fechaEjecutado | datetime | SI |  |  |
| 14 | usuarioEjecturado | datetime | SI |  |  |
| 15 | referenciaDetalle | varchar(50) | SI |  |  |
| 16 | grupoCaracteristica | int | SI |  |  |
| 17 | caracteristica | int | SI |  |  |
| 18 | naturaleza | int | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `aSanidad.empresa`  _(constraint: FK_aSanidadDetalle_aSanidad)_
- `tipo` → `aSanidad.tipo`  _(constraint: FK_aSanidadDetalle_aSanidad)_
- `numero` → `aSanidad.numero`  _(constraint: FK_aSanidadDetalle_aSanidad)_
- `empresa` → `aGrupoCaracteristica.empresa`  _(constraint: FK_aSanidadDetalle_aGrupoCaracteristica)_
- `grupoCaracteristica` → `aGrupoCaracteristica.codigo`  _(constraint: FK_aSanidadDetalle_aGrupoCaracteristica)_
- `caracteristica` → `aCaracteristica.codigo`  _(constraint: FK_aSanidadDetalle_aCaracteristica1)_
- `empresa` → `aCaracteristica.empresa`  _(constraint: FK_aSanidadDetalle_aCaracteristica1)_

## `aSecciones`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | finca | varchar(50) | NO | 🔑 |  |
| 3 | codigo | varchar(50) | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | hBrutas | float | SI |  |  |
| 6 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `aFinca.empresa`  _(constraint: FK_aSecciones_aFinca)_
- `finca` → `aFinca.codigo`  _(constraint: FK_aSecciones_aFinca)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_aSecciones_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `aLotes.empresa` → `empresa`
- `aLotes.seccion` → `codigo`
- `aLotes.finca` → `finca`

## `aTipoCanal`  (filas: 5)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(10) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `aLotesCanal.empresa` → `empresa`
- `aLotesCanal.tipoCanal` → `codigo`
- `aNovedad.tipoCanal` → `codigo`
- `aNovedad.empresa` → `empresa`

## `aTipoNovedad`  (filas: 14)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | novedad | int | NO | 🔑 |  |

## `aTransaccion`  (filas: 6435)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | numero | varchar(50) | NO | 🔑 |  |
| 6 | fecha | date | NO |  |  |
| 7 | referencia | varchar(50) | SI |  |  |
| 8 | finca | varchar(50) | SI |  |  |
| 9 | jornal | int | SI |  |  |
| 10 | racimos | int | SI |  |  |
| 11 | cantidad | int | SI |  |  |
| 12 | precio | money | SI |  |  |
| 13 | valorTotal | money | SI |  |  |
| 14 | fechaFinal | date | SI |  |  |
| 15 | remision | varchar(50) | SI |  |  |
| 16 | observacion | varchar(2550) | NO |  |  |
| 17 | fechaRegistro | datetime | NO |  |  |
| 18 | usuarioRegistro | varchar(50) | NO |  |  |
| 19 | anulado | bit | SI |  |  |
| 20 | usuarioAnulado | varchar(50) | SI |  |  |
| 21 | fechaAnulado | datetime | SI |  |  |
| 22 | periodo | int | SI |  |  |
| 23 | cerrado | bit | NO |  |  |
| 24 | añofer | int | SI |  |  |
| 25 | mesIfer | int | SI |  |  |
| 26 | mesFfer | int | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aTransaccion_gEmpresa)_
- `empresa` → `aFinca.empresa`  _(constraint: FK_aTransaccion_aFinca)_
- `finca` → `aFinca.codigo`  _(constraint: FK_aTransaccion_aFinca)_

**Tablas que referencian a esta (hijas):**
- `aTransaccionNovedad.empresa` → `empresa`
- `aTransaccionNovedad.tipo` → `tipo`
- `aTransaccionNovedad.numero` → `numero`
- `aTransaccionTercero.empresa` → `empresa`
- `aTransaccionTercero.tipo` → `tipo`
- `aTransaccionTercero.numero` → `numero`

## `aTransaccionBascula`  (filas: 2982)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | planta | varchar(50) | SI |  |  |
| 5 | tiquete | varchar(50) | NO | 🔑 |  |
| 6 | fecha | datetime | NO |  |  |
| 7 | pesoBruto | int | NO |  |  |
| 8 | pesoTara | int | NO |  |  |
| 9 | pesoNeto | int | NO |  |  |
| 10 | sacos | int | NO |  |  |
| 11 | racimos | int | NO |  |  |
| 12 | codigoConductor | varchar(50) | SI |  |  |
| 13 | nombreConductor | varchar(550) | SI |  |  |
| 14 | vehiculo | varchar(50) | NO |  |  |
| 15 | remolque | varchar(50) | NO |  |  |
| 16 | interno | bit | NO |  |  |
| 17 | empresaExtractora | varchar(50) | SI |  |  |
| 18 | terceroExtractrora | varchar(50) | SI |  |  |

## `aTransaccionItem`  (filas: 5159)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | lote | varchar(50) | NO | 🔑 |  |
| 6 | año | int | NO |  |  |
| 7 | mes | int | NO |  |  |
| 8 | fecha | date | NO |  |  |
| 9 | fechaFinal | date | NO |  |  |
| 10 | item | varchar(50) | NO |  |  |
| 11 | uMedida | varchar(50) | NO |  |  |
| 12 | novedad | varchar(50) | SI |  |  |
| 13 | cantidad | decimal(18,2) | NO |  |  |
| 14 | saldo | decimal(18,2) | NO |  |  |
| 15 | mBulto | decimal(18,2) | NO |  |  |
| 16 | pBulto | decimal(18,2) | NO |  |  |
| 17 | noPalmas | decimal(18,2) | NO |  |  |
| 18 | dosis | decimal(18,2) | NO |  |  |
| 19 | registror | int | NO |  |  |

## `aTransaccionNovedad`  (filas: 52376)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | numero | varchar(50) | NO | 🔑 |  |
| 6 | novedad | varchar(50) | NO | 🔑 |  |
| 7 | registro | int | NO | 🔑 |  |
| 8 | uMedida | varchar(50) | SI |  |  |
| 9 | finca | varchar(50) | SI |  |  |
| 10 | seccion | varchar(50) | SI |  |  |
| 11 | lote | varchar(50) | SI |  |  |
| 12 | fecha | date | SI |  |  |
| 13 | cantidad | decimal(18,2) | NO |  |  |
| 14 | jornales | decimal(18,2) | NO |  |  |
| 15 | racimos | int | NO |  |  |
| 16 | pesoRacimo | decimal(18,2) | NO |  |  |
| 17 | saldo | decimal(18,2) | NO |  |  |
| 18 | ejecutado | bit | NO |  |  |
| 19 | signo | int | SI |  |  |
| 20 | precioLabor | decimal(18,2) | SI |  |  |
| 21 | concepto | varchar(50) | SI |  |  |
| 22 | periodo | int | SI |  |  |
| 23 | registroNovedad | int | SI |  |  |
| 24 | sacos | int | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `aTransaccion.empresa`  _(constraint: FK_aTransaccionNovedad_aTransaccion)_
- `tipo` → `aTransaccion.tipo`  _(constraint: FK_aTransaccionNovedad_aTransaccion)_
- `numero` → `aTransaccion.numero`  _(constraint: FK_aTransaccionNovedad_aTransaccion)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_aTransaccionNovedad_gEmpresa)_
- `empresa` → `gUnidadMedida.empresa`  _(constraint: FK_aTransaccionNovedad_gUnidadMedida)_
- `uMedida` → `gUnidadMedida.codigo`  _(constraint: FK_aTransaccionNovedad_gUnidadMedida)_

## `aTransaccionPepa`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | numero | varchar(50) | NO | 🔑 |  |
| 6 | fecha | date | NO |  |  |
| 7 | referencia | varchar(50) | SI |  |  |
| 8 | finca | varchar(50) | SI |  |  |
| 9 | jornal | int | NO |  |  |
| 10 | racimos | int | NO |  |  |
| 11 | cantidad | int | NO |  |  |
| 12 | precio | money | NO |  |  |
| 13 | valorTotal | money | NO |  |  |
| 14 | fechaFinal | date | SI |  |  |
| 15 | remision | varchar(50) | SI |  |  |
| 16 | observacion | varchar(2550) | NO |  |  |
| 17 | fechaRegistro | datetime | NO |  |  |
| 18 | usuarioRegistro | varchar(50) | NO |  |  |
| 19 | anulado | bit | SI |  |  |
| 20 | usuarioAnulado | varchar(50) | SI |  |  |
| 21 | fechaAnulado | datetime | SI |  |  |
| 22 | periodo | int | SI |  |  |
| 23 | cerrado | bit | NO |  |  |

## `aTransaccionTercero`  (filas: 105776)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | numero | varchar(50) | NO | 🔑 |  |
| 6 | novedad | varchar(50) | NO | 🔑 |  |
| 7 | registro | int | NO | 🔑 |  |
| 8 | registroNovedad | int | NO | 🔑 |  |
| 9 | finca | varchar(50) | SI |  |  |
| 10 | seccion | varchar(50) | SI |  |  |
| 11 | lote | varchar(50) | SI |  |  |
| 12 | tercero | varchar(50) | NO |  |  |
| 13 | cantidad | decimal(18,2) | NO |  |  |
| 14 | jornales | decimal(18,2) | NO |  |  |
| 15 | saldo | decimal(18,2) | NO |  |  |
| 16 | ejecutado | bit | NO |  |  |
| 17 | zCuadrilla | varchar(50) | SI |  |  |
| 18 | precioLabor | decimal(18,2) | SI |  |  |
| 19 | valorTotal | decimal(18,2) | SI |  |  |
| 20 | ccosto | varchar(50) | SI |  |  |
| 21 | contrato | int | SI |  |  |
| 22 | periodo | int | SI |  |  |
| 23 | contratista | bit | SI |  |  |
| 24 | proveedor | varchar(50) | SI |  |  |
| 25 | racimos | int | SI |  |  |
| 26 | fechaNovedad | date | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aTransaccionTercero_gEmpresa)_
- `empresa` → `aTransaccion.empresa`  _(constraint: FK_aTransaccionTercero_aTransaccion)_
- `tipo` → `aTransaccion.tipo`  _(constraint: FK_aTransaccionTercero_aTransaccion)_
- `numero` → `aTransaccion.numero`  _(constraint: FK_aTransaccionTercero_aTransaccion)_

## `aTransaccionTerceroPepa`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | tipo | varchar(50) | NO | 🔑 |  |
| 5 | numero | varchar(50) | NO | 🔑 |  |
| 6 | novedad | varchar(50) | NO | 🔑 |  |
| 7 | registro | int | NO | 🔑 |  |
| 8 | registroNovedad | int | NO | 🔑 |  |
| 9 | finca | varchar(50) | SI |  |  |
| 10 | seccion | varchar(50) | SI |  |  |
| 11 | lote | varchar(50) | SI |  |  |
| 12 | tercero | int | NO |  |  |
| 13 | cantidad | decimal(18,2) | NO |  |  |
| 14 | jornales | decimal(18,2) | NO |  |  |
| 15 | saldo | decimal(18,2) | NO |  |  |
| 16 | ejecutado | bit | NO |  |  |
| 17 | zCuadrilla | varchar(50) | SI |  |  |
| 18 | precioLabor | money | SI |  |  |
| 19 | valorTotal | int | SI |  |  |
| 20 | ccosto | varchar(50) | SI |  |  |
| 21 | contrato | int | SI |  |  |
| 22 | periodo | int | SI |  |  |
| 23 | contratista | bit | SI |  |  |
| 24 | proveedor | varchar(50) | SI |  |  |
| 25 | tipoReferencia | varchar(50) | SI |  |  |
| 26 | numeroReferencia | varchar(50) | SI |  |  |
| 27 | registroReferencia | int | SI |  |  |

## `aVariedad`  (filas: 9)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(5) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | procedencia | varchar(550) | NO |  |  |
| 5 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_aVariedad_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `aLotes.empresa` → `empresa`
- `aLotes.variedad` → `codigo`

## `atransaccionItemSaldo`  (filas: 98)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO |  |  |
| 2 | tipo | varchar(50) | NO |  |  |
| 3 | numero | varchar(50) | NO |  |  |
| 4 | referencia | varchar(50) | NO |  |  |
| 5 | lote | varchar(50) | NO |  |  |
| 6 | item | int | NO |  |  |
| 7 | fecha | date | NO |  |  |
| 8 | saldoInicial | float | NO |  |  |
| 9 | suma | float | NO |  |  |
| 10 | resta | float | NO |  |  |
| 11 | saldoFinal | float | NO |  |  |
| 12 | anulado | bit | NO |  |  |
