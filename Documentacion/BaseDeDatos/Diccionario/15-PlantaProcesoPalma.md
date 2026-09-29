# Diccionario de Tablas — Planta / Proceso de Palma (fruta, pepa, tanques)

_11 tablas en este modulo._

---

## `pCalibracionTanque`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | movimiento | varchar(50) | NO | 🔑 |  |
| 3 | tipo | varchar(2) | NO | 🔑 |  |
| 4 | altura | int | NO | 🔑 |  |
| 5 | volumen | decimal(18,4) | NO |  |  |
| 6 | activo | bit | NO |  |  |

## `pDensidad`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | item | varchar(50) | NO | 🔑 |  |
| 3 | temperatura | int | NO | 🔑 |  |
| 4 | densidad | float | NO |  |  |
| 5 | activo | bit | NO |  |  |

## `pFrutaEstimadaTmp`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | fecha | date | NO | 🔑 |  |
| 3 | pesoNeto | int | SI |  |  |

## `pJerarquia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | int | NO | 🔑 |  |
| 2 | empresa | int | NO | 🔑 |  |
| 3 | hijo | int | NO |  |  |
| 4 | padre | int | NO |  |  |
| 5 | descripcion | varchar(250) | NO |  |  |
| 6 | activo | bit | NO |  |  |
| 7 | nivel | int | NO |  |  |

## `pJerarquiaAnalisis`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | jerarquia | int | NO | 🔑 |  |
| 2 | analisis | varchar(10) | NO | 🔑 |  |
| 3 | empresa | bit | NO | 🔑 |  |
| 4 | resultado | bit | NO |  |  |
| 5 | formula | varchar(8000) | NO |  |  |
| 6 | prioridad | int | NO |  |  |
| 7 | discreta | bit | NO |  |  |
| 8 | campoResul | bit | NO |  |  |
| 9 | expresion | varchar(8000) | SI |  |  |
| 10 | resultadoParcial | bit | NO |  |  |
| 11 | resultadoFinal | bit | NO |  |  |

## `pNivel`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | int | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | activo | bit | NO |  |  |

## `pProductoMovimiento`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | producto | varchar(50) | NO | 🔑 |  |
| 3 | movimiento | varchar(50) | NO | 🔑 |  |
| 4 | formula | varchar(950) | NO |  |  |
| 5 | prioridad | int | NO |  |  |
| 6 | orden | int | NO |  |  |
| 7 | resultado | bit | NO |  |  |
| 8 | almacena | bit | NO |  |  |
| 9 | mCalcular | bit | NO |  |  |
| 10 | mDecimal | bit | NO |  |  |
| 11 | mInforme | bit | NO |  |  |
| 12 | activo | bit | NO |  |  |
| 13 | modulo | varchar(150) | NO | 🔑 |  |
| 14 | expresion | varchar(8000) | SI |  |  |

## `pTransaccion`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | int | NO |  |  |
| 5 | mes | int | NO |  |  |
| 6 | fecha | date | NO |  |  |
| 7 | producto | int | NO |  |  |
| 8 | usuario | varchar(50) | NO |  |  |
| 9 | usuarioAnulado | varchar(50) | SI |  |  |
| 10 | fechaRegistro | datetime | NO |  |  |
| 11 | anulado | bit | SI |  |  |
| 12 | fechaAnulado | datetime | SI |  |  |
| 13 | Observacion | varchar(5500) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `iItems.empresa`  _(constraint: FK_pTransaccion_iItems)_
- `producto` → `iItems.codigo`  _(constraint: FK_pTransaccion_iItems)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_pTransaccion_gEmpresa)_

**Tablas que referencian a esta (hijas):**
- `pTransaccionDetalle.tipo` → `tipo`
- `pTransaccionDetalle.numero` → `numero`
- `pTransaccionDetalle.empresa` → `empresa`

## `pTransaccionDetalle`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | año | int | NO |  |  |
| 6 | mes | int | NO |  |  |
| 7 | movimiento | int | NO |  |  |
| 8 | valor | float | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `tipo` → `pTransaccion.tipo`  _(constraint: FK_pTransaccionDetalle_pTransaccion)_
- `numero` → `pTransaccion.numero`  _(constraint: FK_pTransaccionDetalle_pTransaccion)_
- `empresa` → `pTransaccion.empresa`  _(constraint: FK_pTransaccionDetalle_pTransaccion)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_pTransaccionDetalle_gEmpresa)_
- `movimiento` → `iItems.codigo`  _(constraint: FK_pTransaccionDetalle_iItems)_
- `empresa` → `iItems.empresa`  _(constraint: FK_pTransaccionDetalle_iItems)_

## `pTransaccionJerarquia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | tipo | varchar(50) | NO | 🔑 |  |
| 2 | numero | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | fecha | datetime | NO | 🔑 |  |
| 6 | empresa | int | NO | 🔑 |  |
| 7 | fechaRegistro | datetime | NO |  |  |
| 8 | usuario | varchar(50) | NO |  |  |
| 9 | observacion | varchar(500) | NO |  |  |
| 10 | anulado | bit | NO |  |  |
| 11 | usuarioAnulado | varchar(50) | SI |  |  |

## `pTransaccionJerarquiaAnalisis`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | tipo | varchar(50) | NO | 🔑 |  |
| 2 | numero | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | fecha | datetime | NO | 🔑 |  |
| 6 | empresa | int | NO | 🔑 |  |
| 7 | jerarquia | int | NO | 🔑 |  |
| 8 | registro | int | NO | 🔑 |  |
| 9 | analisis | varchar(50) | NO | 🔑 |  |
| 10 | resultado | bit | NO |  |  |
| 11 | prioridad | int | NO |  |  |
| 12 | valor | decimal(18,4) | NO |  |  |
| 13 | fechaRegistro | datetime | NO |  |  |
| 14 | usuario | varchar(50) | NO |  |  |
