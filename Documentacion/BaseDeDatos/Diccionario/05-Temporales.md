# Diccionario de Tablas — Temporales / Staging de procesos batch

_15 tablas en este modulo._

---

## `tmpAcumulado`  (filas: 18)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | cc | varchar(50) | SI |  |  |
| 2 | nombre | varchar(550) | SI |  |  |
| 3 | valor | float | SI |  |  |

## `tmpDescuentaAgro`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | concepto | varchar(50) | SI |  |  |
| 3 | valortotal | float | SI |  |  |

## `tmpLiquidacionCesantia`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | año | int | SI |  |  |
| 4 | fechaInicial | date | SI |  |  |
| 5 | fechaFinal | date | SI |  |  |
| 6 | fechaIngreso | date | SI |  |  |
| 7 | basico | int | SI |  |  |
| 8 | valorTransporte | int | SI |  |  |
| 9 | valorPromedio | int | SI |  |  |
| 10 | base | int | SI |  |  |
| 11 | diasPromedio | int | SI |  |  |
| 12 | diasCesantia | int | SI |  |  |
| 13 | valorCesantia | int | SI |  |  |
| 14 | valorInteresCesantia | int | SI |  |  |
| 15 | contrato | int | SI |  |  |

## `tmpLiquidacionHoras`  (filas: 1)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | fechaP | date | SI |  |  |
| 3 | funcionario | varchar(50) | SI |  |  |
| 4 | hTurno | float | SI |  |  |
| 5 | nExtra | float | SI |  |  |
| 6 | horaEntrada | datetime | SI |  |  |
| 7 | horaSalida | datetime | SI |  |  |
| 8 | HED | float | SI |  |  |
| 9 | HEN | float | SI |  |  |
| 10 | RN | float | SI |  |  |
| 11 | HD | float | SI |  |  |
| 12 | HEDD | float | SI |  |  |
| 13 | HEND | float | SI |  |  |
| 14 | RND | float | SI |  |  |
| 15 | HF | float | SI |  |  |
| 16 | HEDF | float | SI |  |  |
| 17 | HENF | float | SI |  |  |
| 18 | RNF | float | SI |  |  |
| 19 | HTL | float | SI |  |  |
| 20 | CodTurno | varchar(50) | SI |  |  |
| 21 | cuadrilla | varchar(50) | SI |  |  |

## `tmpLiquidacionNominaDatos`  (filas: 0)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | año | int | NO | 🔑 |  |
| 3 | mes | int | NO | 🔑 |  |
| 4 | periodo | int | NO | 🔑 |  |
| 5 | tercero | int | NO | 🔑 |  |
| 6 | noContrato | int | SI |  |  |
| 7 | sueldo | float | SI |  |  |
| 8 | entidadEps | varchar(50) | SI |  |  |
| 9 | entidadPension | varchar(50) | SI |  |  |
| 10 | entidadCesantias | varchar(50) | SI |  |  |
| 11 | entidadArp | varchar(50) | SI |  |  |
| 12 | entidadCaja | varchar(50) | SI |  |  |
| 13 | entidadSena | varchar(50) | SI |  |  |
| 14 | entidadIcbf | varchar(50) | SI |  |  |
| 16 | cargo | varchar(50) | SI |  |  |

## `tmpLiquidacionPepa`  (filas: 2953)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO |  |  |
| 2 | año | int | NO |  |  |
| 3 | mes | int | NO |  |  |
| 4 | novedad | varchar(50) | NO |  |  |
| 5 | registro | int | NO |  |  |
| 6 | finca | varchar(50) | SI |  |  |
| 7 | seccion | varchar(50) | SI |  |  |
| 8 | lote | varchar(50) | SI |  |  |
| 9 | tercero | int | NO |  |  |
| 10 | cantidad | decimal(18,2) | NO |  |  |
| 11 | jornales | decimal(18,2) | NO |  |  |
| 12 | saldo | decimal(18,2) | NO |  |  |
| 13 | ejecutado | bit | NO |  |  |
| 14 | zCuadrilla | varchar(50) | SI |  |  |
| 15 | precioLabor | money | SI |  |  |
| 16 | valorTotal | int | SI |  |  |
| 17 | ccosto | varchar(50) | SI |  |  |
| 18 | contrato | int | SI |  |  |
| 19 | periodo | int | SI |  |  |
| 20 | contratista | bit | SI |  |  |
| 21 | proveedor | varchar(50) | SI |  |  |
| 22 | fechaInicial | date | SI |  |  |
| 23 | fechaFinal | date | SI |  |  |
| 24 | fecha | date | SI |  |  |
| 25 | novedadBase | varchar(50) | SI |  |  |
| 26 | cantidadBase | float | SI |  |  |
| 27 | precioBase | float | SI |  |  |
| 28 | valorTotalBase | float | SI |  |  |
| 29 | tipo | varchar(50) | SI |  |  |
| 30 | numero | varchar(50) | SI |  |  |

## `tmpLiquidacionPrima`  (filas: 30)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | añoInicial | int | SI |  |  |
| 4 | añoFinal | int | SI |  |  |
| 5 | periodoInicial | int | SI |  |  |
| 6 | periodoFinal | int | SI |  |  |
| 7 | fechaInicial | date | SI |  |  |
| 8 | fechaFinal | date | SI |  |  |
| 9 | fechaIngreso | date | SI |  |  |
| 10 | basico | int | SI |  |  |
| 11 | valorTransporte | int | SI |  |  |
| 12 | valorPromedio | int | SI |  |  |
| 13 | base | int | SI |  |  |
| 14 | diasPromedio | int | SI |  |  |
| 15 | diasPrimas | int | SI |  |  |
| 16 | valorPrima | int | SI |  |  |
| 17 | contrato | int | SI |  |  |

## `tmpLiquidacionPrimaConcepto`  (filas: 408)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tercero | int | NO | 🔑 |  |
| 3 | concepto | varchar(50) | NO | 🔑 |  |
| 4 | añoInicial | int | SI |  |  |
| 5 | añoFinal | int | SI |  |  |
| 6 | periodoInicial | int | SI |  |  |
| 7 | periodoFinal | int | SI |  |  |
| 8 | fechaInicial | date | SI |  |  |
| 9 | fechaFinal | date | SI |  |  |
| 10 | fechaIngreso | date | SI |  |  |
| 11 | basico | int | SI |  |  |
| 12 | valorTransporte | int | SI |  |  |
| 13 | valorPromedio | int | SI |  |  |
| 14 | base | int | SI |  |  |
| 15 | diasPromedio | int | SI |  |  |
| 16 | diasPrimas | int | SI |  |  |
| 17 | valorPrima | int | SI |  |  |
| 18 | contrato | int | SI |  |  |

## `tmpNovedadesDetalle`  (filas: 10127)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | tipo | varchar(50) | NO | 🔑 |  |
| 3 | numero | varchar(50) | NO | 🔑 |  |
| 4 | registro | int | NO | 🔑 |  |
| 5 | concepto | varchar(50) | SI |  |  |
| 6 | empleado | varchar(50) | SI |  |  |
| 7 | cantidad | decimal(18,3) | NO |  |  |
| 8 | valor | money | NO |  |  |
| 9 | añoInicial | int | NO |  |  |
| 10 | periodoInicial | int | NO |  |  |
| 11 | periodoFinal | int | NO |  |  |
| 12 | frecuencia | int | NO |  |  |
| 13 | detalle | varchar(250) | NO |  |  |
| 14 | ultimoPeriodoLiquidado | int | SI |  |  |
| 15 | ultimoPeriodoFrecuencia | int | SI |  |  |
| 16 | liquidada | bit | SI |  |  |
| 17 | anulado | bit | SI |  |  |
| 18 | añoFinal | int | SI |  |  |
| 19 | ccosto | varchar(50) | SI |  |  |
| 20 | contrato | int | SI |  |  |

## `tmpPrestamoDetalle`  (filas: 220)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | NO | 🔑 |  |
| 2 | codigo | varchar(50) | NO | 🔑 |  |
| 3 | registroCuota | int | NO | 🔑 |  |
| 4 | inicial | money | NO |  |  |
| 5 | interes | money | NO |  |  |
| 6 | amortizacion | money | NO |  |  |
| 7 | valorCuota | money | NO |  |  |
| 8 | final | money | NO |  |  |
| 9 | pagado | bit | NO |  |  |
| 10 | valorPagado | money | SI |  |  |
| 11 | añoPagado | int | SI |  |  |
| 12 | periodoPagado | int | SI |  |  |
| 13 | usuarioPagado | varchar(50) | SI |  |  |
| 14 | fechaPagado | datetime | SI |  |  |

## `tmpVacaciones`  (filas: 166)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | tercero | int | SI |  |  |
| 3 | fechaCorte | date | SI |  |  |
| 4 | fechaIngreso | date | SI |  |  |
| 5 | fechaUltimaVacacion | date | SI |  |  |
| 6 | dias | float | SI |  |  |
| 7 | diasVaca | float | SI |  |  |
| 8 | valor | float | SI |  |  |
| 9 | contrato | int | SI |  |  |
| 10 | sueldo | float | SI |  |  |

## `tmpVenceContrato`  (filas: 189)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | tercero | int | SI |  |  |
| 2 | codTercero | varchar(50) | SI |  |  |
| 3 | nombretercero | varchar(250) | SI |  |  |
| 4 | fechaIngreso | date | SI |  |  |
| 5 | diasContrato | int | SI |  |  |
| 6 | fechaTerminacion | date | SI |  |  |
| 7 | prorroga1 | date | SI |  |  |
| 8 | prorroga2 | date | SI |  |  |
| 9 | prorroga3 | date | SI |  |  |
| 10 | fechaPreaviso | date | SI |  |  |
| 11 | diasAviso | int | SI |  |  |
| 12 | noContrato | int | SI |  |  |
| 13 | nombreClaseContrato | varchar(550) | SI |  |  |
| 14 | empresa | int | SI |  |  |

## `tmpliquidacionNomina`  (filas: 112)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | tercero | varchar(50) | SI |  |  |
| 3 | ccosto | varchar(50) | SI |  |  |
| 4 | fecha | date | SI |  |  |
| 5 | departamento | varchar(50) | SI |  |  |
| 6 | concepto | varchar(50) | SI |  |  |
| 7 | año | int | SI |  |  |
| 8 | mes | int | SI |  |  |
| 9 | noPeriodo | int | SI |  |  |
| 10 | cantidad | float | SI |  |  |
| 11 | porcentaje | float | SI |  |  |
| 12 | valorUnitario | float | SI |  |  |
| 13 | valorTotal | float | SI |  |  |
| 14 | signo | int | SI |  |  |
| 15 | saldo | float | SI |  |  |
| 16 | noDias | float | SI |  |  |
| 17 | fechaInical | date | SI |  |  |
| 18 | fechaFinal | date | SI |  |  |
| 19 | baseSeguridadSocial | bit | SI |  |  |
| 20 | baseEmbargos | bit | SI |  |  |
| 21 | entidad | varchar(50) | SI |  |  |
| 22 | cantidadPadelma | float | SI |  |  |
| 23 | valorPadelma | float | SI |  |  |
| 24 | noContrato | int | SI |  |  |
| 25 | noPrestamo | varchar(50) | SI |  |  |
| 26 | tipoConcepto | varchar(50) | SI |  |  |
| 27 | desTipoConcepto | varchar(200) | SI |  |  |

## `tmpliquidacionNominaVacaciones`  (filas: 445)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | int | SI |  |  |
| 2 | tercero | varchar(50) | SI |  |  |
| 3 | ccosto | varchar(50) | SI |  |  |
| 4 | fecha | date | SI |  |  |
| 5 | departamento | varchar(50) | SI |  |  |
| 6 | concepto | varchar(50) | SI |  |  |
| 7 | año | int | SI |  |  |
| 8 | mes | int | SI |  |  |
| 9 | noPeriodo | int | SI |  |  |
| 10 | cantidad | decimal(18,3) | SI |  |  |
| 11 | porcentaje | decimal(18,3) | SI |  |  |
| 12 | valorUnitario | money | SI |  |  |
| 13 | valorTotal | money | SI |  |  |
| 14 | signo | int | SI |  |  |
| 15 | saldo | money | SI |  |  |
| 16 | noDias | int | SI |  |  |
| 17 | fechaInical | date | SI |  |  |
| 18 | fechaFinal | date | SI |  |  |
| 19 | baseSeguridadSocial | bit | SI |  |  |
| 20 | baseEmbargos | bit | SI |  |  |
| 21 | entidad | varchar(50) | SI |  |  |
| 22 | noPrestamo | varchar(50) | SI |  |  |
| 23 | codEmbargo | varchar(50) | SI |  |  |

## `tmpprovisional`  (filas: 80)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | codigo | varchar(50) | SI |  |  |
| 2 | codigo2 | varchar(50) | SI |  |  |
