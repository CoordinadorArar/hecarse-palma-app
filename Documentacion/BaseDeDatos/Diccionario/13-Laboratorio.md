# Diccionario de Tablas — Laboratorio (calidad, tanques)

_7 tablas en este modulo._

---

## `lAnalisis`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(10) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | uMedida | varchar(50) | NO |  |  |
| 5 | produccion | bit | NO |  |  |
| 6 | despacho | bit | NO |  |  |
| 7 | informe | bit | NO |  |  |
| 8 | control | bit | NO |  |  |
| 9 | orden | int | NO |  |  |
| 10 | activo | bit | NO |  |  |
| 11 | descuenta | bit | NO |  |  |

## `lAnalisisItem`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | item | int | NO | 🔑 |  |
| 3 | analisis | varchar(10) | NO | 🔑 |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_lAnlisisItem_gEmpresa)_
- `empresa` → `iItems.empresa`  _(constraint: FK_lAnlisisItem_iItems)_
- `item` → `iItems.codigo`  _(constraint: FK_lAnlisisItem_iItems)_

## `lRegistroAnalisis`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | analisis | varchar(10) | NO | 🔑 |  |
| 5 | fecha | datetime | NO |  |  |
| 6 | valor | float | NO |  |  |
| 7 | usuario | varchar(50) | NO |  |  |

## `lRegistroAnalisisTanque`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | tanque | varchar(50) | NO | 🔑 |  |
| 5 | porcentaje | int | NO |  |  |

## `lRegistroBodega`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | bodega | varchar(5) | NO | 🔑 |  |
| 5 | cantidad | int | NO |  |  |

## `lRegistroSellos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | Sello | varchar(50) | NO | 🔑 |  |
| 5 | fecha | datetime | NO |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | imagen | varchar(8000) | SI |  |  |
| 8 | url | varchar(8000) | SI |  |  |
| 9 | anulado | bit | SI |  |  |
| 10 | usuarioAnulado | varchar(50) | SI |  |  |
| 11 | fechaAnulado | datetime | SI |  |  |

## `lTanque`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | char(5) | NO | 🔑 |  |
| 3 | descripcion | varchar(950) | NO |  |  |
| 4 | item | int | NO |  |  |
| 5 | capacidad | float | NO |  |  |
| 6 | activo | bit | NO |  |  |
