# Diccionario de Tablas — Logistica de Despacho

_5 tablas en este modulo._

---

## `logCarnetDespacho`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | estado | char(1) | NO |  |  |

## `logDespacho`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | año | char(6) | NO |  |  |
| 5 | mes | nchar(10) | NO |  |  |
| 6 | fecha | datetime | NO |  |  |
| 7 | tiquete | varchar(50) | NO |  |  |
| 8 | remision | varchar(50) | NO |  |  |
| 9 | remisionComercializadora | varchar(50) | NO |  |  |
| 10 | vehiculo | varchar(50) | NO |  |  |
| 11 | remolque | varchar(50) | NO |  |  |
| 12 | cantidad | float | NO |  |  |
| 13 | producto | varchar(50) | NO |  |  |
| 14 | programacion | varchar(50) | NO |  |  |
| 15 | cliente | varchar(50) | NO |  |  |
| 16 | lugarEntrega | varchar(50) | NO |  |  |
| 17 | comercializadora | varchar(50) | NO |  |  |
| 18 | planta | varchar(50) | NO |  |  |

## `logProgramacionGeneral`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | programacion | varchar(50) | NO | 🔑 |  |
| 3 | año | int | NO | 🔑 |  |
| 4 | mes | int | NO | 🔑 |  |
| 5 | producto | varchar(50) | NO | 🔑 |  |
| 6 | cantidad | float | NO |  |  |
| 7 | mercado | varchar(50) | NO |  |  |
| 8 | fechaRegistro | datetime | NO |  |  |
| 9 | usuario | varchar(50) | NO |  |  |

## `logProgramacionVehiculo`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | numero | varchar(50) | NO | 🔑 |  |
| 3 | tipo | varchar(50) | NO | 🔑 |  |
| 4 | fecha | date | NO |  |  |
| 5 | vehiculoPropio | bit | SI |  |  |
| 6 | vehiculo | varchar(50) | NO |  |  |
| 7 | despacho | varchar(50) | SI |  |  |
| 8 | codigoConductor | varchar(50) | NO |  |  |
| 9 | nombreConductor | varchar(250) | NO |  |  |
| 10 | programacionCarga | varchar(50) | NO |  |  |
| 11 | fechaDespacho | datetime | NO |  |  |
| 12 | remolque | varchar(50) | NO |  |  |
| 13 | producto | int | NO |  |  |
| 14 | comercializadora | varchar(50) | NO |  |  |
| 15 | tercero | int | NO |  |  |
| 16 | cantidad | int | NO |  |  |
| 17 | observacion | varchar(250) | NO |  |  |
| 18 | planta | varchar(50) | NO |  |  |
| 19 | estado | varchar(2) | NO |  |  |
| 20 | cliente | varchar(10) | NO |  |  |
| 21 | fechaRegistro | datetime | NO |  |  |
| 22 | usuario | varchar(50) | NO |  |  |
| 23 | certificado | varchar(50) | SI |  |  |

## `logaTransaccionTercero`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | indice | int | NO | 🔑 | IDENTITY |
| 3 | año | int | SI |  |  |
| 4 | mes | int | SI |  |  |
| 5 | tipo | varchar(50) | SI |  |  |
| 6 | numero | varchar(50) | SI |  |  |
| 7 | novedad | varchar(50) | SI |  |  |
| 8 | registro | int | SI |  |  |
| 9 | registroNovedad | int | SI |  |  |
| 10 | finca | varchar(50) | SI |  |  |
| 11 | seccion | varchar(50) | SI |  |  |
| 12 | lote | varchar(50) | SI |  |  |
| 13 | tercero | int | SI |  |  |
| 14 | cantidad | decimal(18,2) | SI |  |  |
| 15 | jornales | decimal(18,2) | SI |  |  |
| 16 | saldo | decimal(18,2) | SI |  |  |
| 17 | ejecutado | bit | SI |  |  |
| 18 | zCuadrilla | varchar(50) | SI |  |  |
| 19 | precioLabor | money | SI |  |  |
| 20 | valorTotal | int | SI |  |  |
| 21 | ccosto | varchar(50) | SI |  |  |
| 22 | contrato | int | SI |  |  |
| 23 | periodo | int | SI |  |  |
| 24 | contratista | bit | SI |  |  |
| 25 | proveedor | varchar(50) | SI |  |  |
