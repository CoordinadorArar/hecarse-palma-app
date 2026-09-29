# Diccionario de Tablas — Metadatos de Sistema (motor dinamico)

_3 tablas en este modulo._

---

## `sysEntidadCampo`  (filas: 82)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | entidad | varchar(250) | NO | 🔑 |  |
| 2 | campo | varchar(250) | NO | 🔑 |  |

## `sysEntidadMetodos`  (filas: 197)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | entidad | varchar(250) | NO | 🔑 |  |
| 2 | dBase | varchar(250) | NO | 🔑 |  |
| 3 | metodoGet | varchar(250) | SI |  |  |
| 4 | metodoInsert | varchar(250) | SI |  |  |
| 5 | metodoUpdate | varchar(250) | SI |  |  |
| 6 | metodoDelete | varchar(250) | SI |  |  |
| 7 | metodoGetKey | varchar(250) | SI |  |  |

## `sysdiagrams`  (filas: 11)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | name | sysname | NO |  |  |
| 2 | principal_id | int | NO |  |  |
| 3 | diagram_id | int | NO | 🔑 | IDENTITY |
| 4 | version | int | SI |  |  |
| 5 | definition | varbinary(MAX) | SI |  |  |
