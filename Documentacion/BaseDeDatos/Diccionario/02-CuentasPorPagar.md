# Diccionario de Tablas — Cuentas por Pagar

_4 tablas en este modulo._

---

## `cxpClaseProveedor`  (filas: 3)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(4) | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_cxpClaseProveedor_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `cxpProveedor.empresa` → `empresa`
- `cxpProveedor.clase` → `codigo`

## `cxpProveedor`  (filas: 856)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | idTercero | int | NO | 🔑 |  |
| 3 | codigo | varchar(10) | NO | 🔑 |  |
| 4 | descripcion | varchar(950) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | clase | char(4) | SI |  |  |
| 7 | contacto | varchar(950) | NO |  |  |
| 8 | ciudad | char(5) | SI |  |  |
| 9 | direccion | varchar(950) | NO |  |  |
| 10 | telefono | varchar(50) | NO |  |  |
| 11 | email | varchar(250) | NO |  |  |
| 12 | fechaRegistro | datetime | NO |  |  |
| 13 | entradaDirecta | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_cxpProveedor_gEmpresa)_
- `empresa` → `cxpClaseProveedor.empresa`  _(constraint: FK_cxpProveedor_cxpClaseProveedor)_
- `clase` → `cxpClaseProveedor.codigo`  _(constraint: FK_cxpProveedor_cxpClaseProveedor)_

## `cxpProveedorBanco`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | proveedor | int | NO | 🔑 |  |
| 3 | banco | varchar(50) | NO | 🔑 |  |
| 4 | tipoCuenta | char(1) | NO |  |  |
| 5 | nroCuenta | varchar(150) | NO |  |  |
| 6 | ciudad | char(5) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gBanco.empresa`  _(constraint: FK_cxpProveedorBanco_gBanco)_
- `banco` → `gBanco.codigo`  _(constraint: FK_cxpProveedorBanco_gBanco)_
- `empresa` → `gTipoCuenta.empresa`  _(constraint: FK_cxpProveedorBanco_gTipoCuenta)_
- `tipoCuenta` → `gTipoCuenta.codigo`  _(constraint: FK_cxpProveedorBanco_gTipoCuenta)_

## `cxpProveedorCalseIR`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | proveedor | varchar(10) | NO | 🔑 |  |
| 4 | clase | int | NO | 🔑 |  |
| 5 | concepto | varchar(5) | SI |  |  |
