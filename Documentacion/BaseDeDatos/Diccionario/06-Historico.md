# Diccionario de Tablas — Historico / Legado

_3 tablas en este modulo._

---

## `h_TipoNoveda`  (filas: 20)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 | IDENTITY |
| 2 | descripcion | varchar(50) | SI |  |  |

## `h_Usuarios`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | usuario | varchar(50) | NO | 🔑 |  |
| 2 | descripcion | varchar(250) | NO |  |  |
| 3 | clave | varchar(250) | NO |  |  |
| 4 | activo | bit | NO |  |  |
| 5 | fechaRegistro | datetime | NO |  |  |
| 6 | email | varchar(250) | NO |  |  |

## `h_novedades`  (filas: 7)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 | IDENTITY |
| 2 | entidad_id | int | NO |  |  |
| 3 | documento | varchar(50) | NO |  |  |
| 4 | tipo_novedad_id | int | NO |  |  |
| 5 | finca | varchar(10) | SI |  |  |
| 6 | fechaInicio | date | NO |  |  |
| 7 | fechaFin | date | NO |  |  |
| 8 | fechaRegistro | datetime | NO |  |  |
