# Diccionario de Tablas — Seguridad y Sistema

_15 tablas en este modulo._

---

## `sEstados`  (filas: 2)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | estado | char(10) | NO | 🔑 |  |
| 2 | descripcion | varchar(150) | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `sLogRegistros.estado` → `estado`

## `sLogCorreos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 | IDENTITY |
| 2 | subject | varchar(200) | SI |  |  |
| 3 | body | varchar(MAX) | SI |  |  |
| 4 | estado | varchar(50) | SI |  |  |

## `sLogNomina`  (filas: 326)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | varchar(50) | NO |  |  |
| 2 | id | int | NO |  |  |
| 3 | usuario | varchar(50) | NO |  |  |
| 4 | nombreUsuario | varchar(350) | NO |  |  |
| 5 | fechaRegistro | datetime | NO |  |  |
| 6 | Tabla | varchar(50) | NO |  |  |
| 7 | operacion | varchar(50) | NO |  |  |

## `sLogNominaDetalle`  (filas: 25102)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO |  |  |
| 2 | id | int | NO |  |  |
| 3 | tabla | varchar(150) | NO |  |  |
| 4 | columna | varchar(250) | NO |  |  |
| 5 | valorAnt | varchar(8000) | SI |  |  |
| 6 | valorDes | varchar(8000) | SI |  |  |
| 7 | usuario | varchar(50) | NO |  |  |

## `sLogRegistros`  (filas: 90856)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | usuario | varchar(50) | NO | 🔑 |  |
| 2 | fecha | datetime | NO | 🔑 |  |
| 3 | operacion | varchar(50) | NO | 🔑 |  |
| 4 | empresa | int | SI |  |  |
| 5 | entidad | varchar(250) | NO |  |  |
| 6 | estado | char(10) | NO |  |  |
| 7 | mensajeSistema | varchar(500) | SI |  |  |
| 8 | ip | varchar(50) | SI |  |  |

**Claves foraneas (esta tabla referencia a):**
- `operacion` → `sOperaciones.codigo`  _(constraint: FK_sLogRegistros_sOperaciones)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_sLogRegistros_gEmpresa)_
- `usuario` → `sUsuarios.usuario`  _(constraint: FK_sLogRegistros_sUsuarios)_
- `estado` → `sEstados.estado`  _(constraint: FK_sLogRegistros_sEstados)_

## `sMenu`  (filas: 146)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(150) | NO | 🔑 |  |
| 2 | modulo | varchar(150) | NO | 🔑 |  |
| 3 | pagina | varchar(550) | NO |  |  |
| 4 | descripcion | varchar(550) | NO |  |  |
| 5 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `modulo` → `sModulos.codigo`  _(constraint: FK_sMenu_sModulos)_

## `sMenus`  (filas: 50)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | rowid | int | NO | 🔑 |  |
| 2 | nivel | int | NO |  |  |
| 3 | id | varchar(50) | NO |  |  |
| 4 | modulo | varchar(50) | NO |  |  |
| 5 | padre | varchar(50) | SI |  |  |
| 6 | pagina | varchar(50) | SI |  |  |
| 7 | nombre | varchar(100) | NO |  |  |
| 8 | mWeb | bit | NO |  |  |
| 9 | activo | bit | NO |  |  |
| 10 | usuarioRegistro | varchar(50) | NO |  |  |
| 11 | fechaRegistro | datetime | NO |  |  |
| 12 | usuarioModificacion | varchar(50) | SI |  |  |
| 13 | fechaModificacion | datetime | SI |  |  |

**Tablas que referencian a esta (hijas):**
- `sMenusOperaciones.rowid` → `rowid`

## `sMenusOperaciones`  (filas: 276)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | rowid | int | NO | 🔑 |  |
| 2 | operacion | varchar(50) | NO | 🔑 |  |

**Claves foraneas (esta tabla referencia a):**
- `rowid` → `sMenus.rowid`  _(constraint: FK_sMenusOperaciones_sMenus)_
- `operacion` → `sOperaciones.codigo`  _(constraint: FK_sMenusOperaciones_sOperaciones)_

## `sModulos`  (filas: 7)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(150) | NO | 🔑 |  |
| 2 | descripcion | varchar(250) | NO |  |  |
| 3 | dirUrl | varchar(950) | NO |  |  |
| 4 | imagen | varchar(950) | NO |  |  |
| 5 | orden | int | NO |  |  |
| 6 | activo | bit | NO |  |  |
| 7 | urlFormatos | varchar(250) | SI |  |  |
| 8 | urlReportes | varchar(250) | SI |  |  |
| 9 | formula | bit | SI |  |  |

**Tablas que referencian a esta (hijas):**
- `sMenu.modulo` → `codigo`
- `sPerfilPermisos.sitio` → `codigo`

## `sOperaciones`  (filas: 7)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(50) | NO | 🔑 |  |
| 2 | descripcion | varchar(150) | NO |  |  |
| 3 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `sLogRegistros.operacion` → `codigo`
- `sMenusOperaciones.operacion` → `codigo`
- `sPerfilPermisos.operacion` → `codigo`

## `sPerfilPermisos`  (filas: 466)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | perfil | varchar(50) | NO | 🔑 |  |
| 3 | sitio | varchar(150) | NO | 🔑 |  |
| 4 | menu | varchar(150) | NO | 🔑 |  |
| 5 | operacion | varchar(50) | NO | 🔑 |  |
| 6 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `perfil` → `sPerfiles.codigo`  _(constraint: FK_sPerfilPermisos_sPerfiles)_
- `operacion` → `sOperaciones.codigo`  _(constraint: FK_sPerfilPermisos_sOperaciones)_
- `sitio` → `sModulos.codigo`  _(constraint: FK_sPerfilPermisos_sModulos)_

## `sPerfiles`  (filas: 3)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(50) | NO | 🔑 |  |
| 2 | descripcion | varchar(550) | NO |  |  |
| 3 | activo | bit | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `sPerfilPermisos.perfil` → `codigo`
- `sUsuarioPerfiles.perfil` → `codigo`

## `sReplicacion`  (filas: 34)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | id | int | NO | 🔑 | IDENTITY |
| 2 | empresaA | int | NO |  |  |
| 3 | empresaB | int | NO |  |  |
| 4 | tabla | varchar(50) | NO |  |  |
| 5 | noRegistro | int | NO |  |  |
| 6 | usuario | varchar(50) | NO |  |  |
| 7 | fechaRegistro | datetime | NO |  |  |

## `sUsuarioPerfiles`  (filas: 10)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | usuario | varchar(50) | NO | 🔑 |  |
| 3 | perfil | varchar(50) | NO |  |  |
| 4 | activo | bit | NO |  |  |

**Claves foraneas (esta tabla referencia a):**
- `usuario` → `sUsuarios.usuario`  _(constraint: FK_sUsuarioPerfiles_sUsuarios)_
- `empresa` → `gEmpresa.id`  _(constraint: FK_sUsuarioPerfiles_gEmpresa)_
- `perfil` → `sPerfiles.codigo`  _(constraint: FK_usuarioPerfiles_perfiles)_

## `sUsuarios`  (filas: 12)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | usuario | varchar(50) | NO | 🔑 |  |
| 3 | descripcion | varchar(250) | NO |  |  |
| 4 | clave | varbinary(250) | NO |  |  |
| 5 | activo | bit | NO |  |  |
| 6 | fechaRegistro | datetime | NO |  |  |
| 7 | email | varchar(250) | NO |  |  |

**Tablas que referencian a esta (hijas):**
- `sLogRegistros.usuario` → `usuario`
- `sUsuarioPerfiles.usuario` → `usuario`
