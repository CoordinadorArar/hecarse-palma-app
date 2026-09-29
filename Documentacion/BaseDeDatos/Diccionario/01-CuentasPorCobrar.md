# Diccionario de Tablas — Cuentas por Cobrar

_2 tablas en este modulo._

---

## `cxcCliente`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | idTercero | int | NO | 🔑 |  |
| 3 | codigo | varchar(10) | NO | 🔑 |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | contacto | varchar(550) | NO |  |  |
| 7 | direccion | varchar(950) | NO |  |  |
| 8 | telefono | varchar(50) | NO |  |  |
| 9 | email | varchar(90) | NO |  |  |
| 10 | ciudad | char(5) | SI |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `empresa` → `gEmpresa.id`  _(constraint: FK_cCliente_gEmpresa)_

## `cxcClienteClaseIR`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | cliente | varchar(10) | NO | 🔑 |  |
| 4 | clase | int | NO | 🔑 |  |
| 5 | concepto | varchar(5) | SI |  |  |
