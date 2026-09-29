# Diccionario de Tablas — Mercado

_2 tablas en este modulo._

---

## `fMercado`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | tipoMercado | varchar(50) | NO |  |  |
| 5 | activo | bit | NO |  |  |

## `fTipoMercado`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(550) | NO |  |  |
| 4 | activo | bit | NO |  |  |
