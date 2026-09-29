# Diccionario de Tablas — Inventario / Items / Bodega

_11 tablas en este modulo._

---

## `iBodega`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(5) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | desCorta | varchar(50) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | validaCcosto | bit | NO |  |  |
| 7 | cCosto | varchar(50) | SI |  |  |
| 8 | validaProveedor | bit | NO |  |  |
| 9 | proveedor | int | SI |  |  |
| 10 | validaCuenta | bit | NO |  |  |
| 11 | Cuenta | varchar(16) | SI |  |  |
| 12 | produccion | bit | NO |  |  |
| 13 | mExistencia | bit | NO |  |  |

## `iBodegaTipoTransaccion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | bodega | varchar(5) | NO | 🔑 |  |

## `iDestino`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | nivel | int | NO | 🔑 |  |
| 4 | nivelPadre | int | NO |  |  |
| 5 | padre | varchar(50) | NO |  |  |
| 6 | descripcion | varchar(50) | NO |  |  |
| 7 | ctaInversion | varchar(16) | NO |  |  |
| 8 | ctaGasto | varchar(16) | NO |  |  |
| 9 | activo | bit | NO |  |  |

## `iItems`  (filas: 14)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | int | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | descripcionAbreviada | varchar(50) | NO |  |  |
| 5 | referencia | varchar(250) | NO |  |  |
| 6 | manejaIR | bit | NO |  |  |
| 7 | grupoIR | char(5) | SI |  |  |
| 8 | tipo | varchar(2) | NO |  |  |
| 9 | compras | bit | NO |  |  |
| 10 | ventas | bit | NO |  |  |
| 11 | uMedidaCompra | varchar(50) | NO |  |  |
| 12 | uMedidaConsumo | varchar(50) | NO |  |  |
| 13 | papeleta | varchar(50) | SI |  |  |
| 14 | tiempoReposicion | int | NO |  |  |
| 15 | minimo | decimal(18,3) | NO |  |  |
| 16 | maximo | decimal(18,3) | NO |  |  |
| 17 | notas | varchar(1550) | NO |  |  |
| 18 | activo | bit | NO |  |  |
| 19 | usuarioRegistro | varchar(50) | NO |  |  |
| 20 | fechaRegistro | datetime | NO |  |  |
| 21 | usuarioActualiza | varchar(50) | SI |  |  |
| 22 | fechaActualiza | datetime | SI |  |  |
| 23 | foto | int | SI |  |  |
| 24 | orden | int | SI |  |  |
| 25 | sello | bit | SI |  |  |
| 26 | descuento | bit | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_iItems_gEmpresa)_
- `empresa` → `gUnidadMedida.empresa`  _(constraint: FK_iItems_gUnidadMedida)_
- `uMedidaCompra` → `gUnidadMedida.codigo`  _(constraint: FK_iItems_gUnidadMedida)_

**Tablas que referencian a esta (hijas):**
- `iItemsCriterios.empresa` → `empresa`
- `iItemsCriterios.item` → `codigo`
- `lAnalisisItem.empresa` → `empresa`
- `lAnalisisItem.item` → `codigo`
- `pTransaccion.empresa` → `empresa`
- `pTransaccion.producto` → `codigo`
- `pTransaccionDetalle.movimiento` → `codigo`
- `pTransaccionDetalle.empresa` → `empresa`

## `iItemsBodega`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | item | varchar(50) | NO | 🔑 |  |
| 3 | bodega | varchar(5) | NO | 🔑 |  |

## `iItemsCriterios`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | item | int | NO | 🔑 |  |
| 3 | idPlan | varchar(5) | NO | 🔑 |  |
| 4 | idMayor | varchar(50) | NO | 🔑 |  |
| 5 | fechaRegistro | datetime | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `iItems.empresa`  _(constraint: FK_iItemsCriterios_iItems)_
- `item` → `iItems.codigo`  _(constraint: FK_iItemsCriterios_iItems)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_iItemsCriterios_gEmpresa)_
- `empresa` → `iPlanItem.empresa`  _(constraint: FK_iItemsCriterios_iPlanItem)_
- `idPlan` → `iPlanItem.codigo`  _(constraint: FK_iItemsCriterios_iPlanItem)_
- `empresa` → `iMayorItem.empresa`  _(constraint: FK_iItemsCriterios_iMayorItem)_
- `idMayor` → `iMayorItem.codigo`  _(constraint: FK_iItemsCriterios_iMayorItem)_

## `iMayorItem`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | planes | varchar(5) | NO |  |  |
| 4 | descripcion | varchar(950) | NO |  |  |
| 5 | observacion | varchar(1550) | NO |  |  |
| 6 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_iMayorItem_gEmpresa)_
- `empresa` → `iPlanItem.empresa`  _(constraint: FK_iMayorItem_iPlanItem)_
- `planes` → `iPlanItem.codigo`  _(constraint: FK_iMayorItem_iPlanItem)_

**Tablas que referencian a esta (hijas):**
- `iItemsCriterios.empresa` → `empresa`
- `iItemsCriterios.idMayor` → `codigo`

## `iNivelDestino`  (filas: 13)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(2) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | activo | bit | NO |  |  |

## `iPlanItem`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(5) | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | observacion | varchar(1550) | NO |  |  |
| 5 | presentaMayor | bit | NO |  |  |
| 6 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_iPlanItem_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `iItemsCriterios.empresa` → `empresa`
- `iItemsCriterios.idPlan` → `codigo`
- `iMayorItem.empresa` → `empresa`
- `iMayorItem.planes` → `codigo`

## `iTransaccion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | int | NO |  |  |
| 5 | mes | int | NO |  |  |
| 6 | naturaleza | varchar(1) | NO |  |  |
| 7 | vigencia | int | NO |  |  |
| 8 | fecha | date | NO |  |  |
| 9 | tercero | int | NO |  |  |
| 10 | referencia | varchar(50) | NO |  |  |
| 11 | tipoSalida | varchar(50) | SI |  |  |
| 12 | talonario | varchar(50) | SI |  |  |
| 13 | departamento | varchar(50) | SI |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | usuarioAnulado | varchar(50) | SI |  |  |
| 16 | fechaRegistro | datetime | NO |  |  |
| 17 | fechaAnulado | datetime | SI |  |  |
| 18 | anulado | bit | SI |  |  |

## `importar`  (filas: 1929)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | varchar(50) | SI |  |  |
| 2 | periodoInicial | varchar(50) | SI |  |  |
| 3 | periodoFinal | varchar(50) | SI |  |  |
| 4 | empleado | varchar(50) | SI |  |  |
| 5 | registro | varchar(50) | SI |  |  |
| 6 | tipo | varchar(50) | SI |  |  |
| 7 | fechaSalida | varchar(50) | SI |  |  |
| 8 | fechaRetorno | varchar(50) | SI |  |  |
| 9 | diasCausados | varchar(50) | SI |  |  |
| 10 | diasTomados | varchar(50) | SI |  |  |
| 11 | diasPendientes | varchar(50) | SI |  |  |
| 12 | diasPagados | varchar(50) | SI |  |  |
| 13 | valorPagado | varchar(50) | SI |  |  |
| 14 | valorBase | varchar(50) | SI |  |  |
| 15 | usuario | varchar(50) | SI |  |  |
| 16 | fechaRegistro | varchar(50) | SI |  |  |
| 17 | observaciones | varchar(50) | SI |  |  |
| 18 | anulado | varchar(50) | SI |  |  |
| 19 | fechaAnulado | varchar(50) | SI |  |  |
| 20 | usuarioAnulado | varchar(50) | SI |  |  |
| 21 | ejecutado | varchar(50) | SI |  |  |
