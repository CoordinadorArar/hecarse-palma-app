# Diccionario de Tablas — Bascula y Transporte

_8 tablas en este modulo._

---

## `bProcedencia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | proveedor | int | SI |  |  |
| 4 | agrupadoPor | int | SI |  |  |
| 5 | fechaRegistro | datetime | NO |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_bProcedencia_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `bProcedenciaCorreos.empresa` → `empresa`
- `bProcedenciaCorreos.procedencia` → `codigo`

## `bProcedenciaCorreos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | procedencia | varchar(50) | NO | 🔑 |  |
| 3 | direccion | varchar(250) | NO | 🔑 |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_bProcedenciaCorreos_gEmpresa)_
- `empresa` → `bProcedencia.empresa`  _(constraint: FK_bProcedenciaCorreos_bProcedencia)_
- `procedencia` → `bProcedencia.codigo`  _(constraint: FK_bProcedenciaCorreos_bProcedencia)_

## `bRegistroBascula`  (filas: 14075)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | fecha | datetime | NO |  |  |
| 5 | tiquete | varchar(50) | NO |  |  |
| 6 | remision | varchar(50) | NO |  |  |
| 7 | pesoBruto | float | NO |  |  |
| 8 | pesoDescuento | float | SI |  |  |
| 9 | pesoTara | float | NO |  |  |
| 10 | pesoNeto | float | NO |  |  |
| 11 | fechaBruto | datetime | SI |  |  |
| 12 | fechaTara | datetime | SI |  |  |
| 13 | fechaNeto | datetime | SI |  |  |
| 14 | estado | char(2) | NO |  |  |
| 16 | vehiculo | varchar(50) | NO |  |  |
| 17 | remolque | varchar(50) | NO |  |  |
| 18 | item | int | SI |  |  |
| 19 | procedencia | varchar(50) | SI |  |  |
| 20 | finca | varchar(50) | SI |  |  |
| 21 | usuario | varchar(50) | NO |  |  |
| 22 | fechaProceso | datetime | SI |  |  |
| 23 | racimos | int | NO |  |  |
| 24 | bodega | varchar(50) | SI |  |  |
| 25 | sacos | int | NO |  |  |
| 28 | pesoSacos | float | NO |  |  |
| 30 | tipoDescargue | varchar(50) | SI |  |  |
| 31 | codigoConductor | varchar(50) | SI |  |  |
| 32 | nombreConductor | varchar(200) | SI |  |  |
| 33 | tercero | varchar(50) | SI |  |  |
| 37 | planta | varchar(50) | SI |  |  |
| 48 | observacion | varchar(2550) | SI |  |  |

## `bRegistroCertificado`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | finca | varchar(50) | SI |  |  |
| 5 | certificado | varchar(50) | NO | 🔑 |  |
| 6 | fechaRegistro | datetime | NO |  |  |
| 7 | usuario | varchar(50) | NO |  |  |

## `bRegistroPorteria`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | numero | varchar(50) | NO | 🔑 |  |
| 3 | tipo | varchar(50) | NO | 🔑 |  |
| 4 | remision | varchar(50) | NO |  |  |
| 5 | fechaEntrada | datetime | NO |  |  |
| 6 | fechaSalida | datetime | NO |  |  |
| 7 | codigoConductor | varchar(50) | NO |  |  |
| 8 | nombreConductor | varchar(250) | NO |  |  |
| 9 | estado | char(2) | NO |  |  |
| 10 | fechaProgramacion | datetime | NO |  |  |
| 11 | vehiculo | varchar(50) | NO |  |  |
| 12 | remolque | varchar(50) | NO |  |  |
| 13 | propio | bit | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
| 15 | fechaRegistro | datetime | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gTipoTransaccion.empresa`  _(constraint: FK_bRegistroPorteria_gTipoTransaccion)_
- `tipo` → `gTipoTransaccion.codigo`  _(constraint: FK_bRegistroPorteria_gTipoTransaccion)_

## `bRemision`  (filas: 17499)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | fechaCreacion | datetime | NO |  |  |
| 4 | fechaImpresion | datetime | SI |  |  |
| 5 | fechaAsignacion | datetime | SI |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | funcionarioAsignado | int | SI |  |  |
| 8 | estado | char(1) | NO |  |  |

## `bTipoVehiculo`  (filas: 5)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | aplicaRemolque | bit | NO |  |  |
| 5 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_bTipoVehiculo_gEmpresa)_

## `bVehiculo`  (filas: 1)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | nchar(10) | NO | 🔑 |  |
| 3 | codigo | varchar(50) | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | pesoTara | int | NO |  |  |
| 6 | activo | bit | NO |  |  |
| 7 | usuario | varchar(50) | NO |  |  |
| 8 | fechaRegistro | datetime | NO |  |  |
