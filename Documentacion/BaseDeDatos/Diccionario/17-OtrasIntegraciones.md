# Diccionario de Tablas — Otras / Integraciones

_4 tablas en este modulo._

---

## `PowerBiVentas`  (filas: 784)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | TipoIngreso | varchar(14) | NO |  |  |
| 2 | Año | int | SI |  |  |
| 3 | Mes | int | SI |  |  |
| 4 | IdCia | smallint | NO |  |  |
| 5 | NitCliente | varchar(20) | SI |  |  |
| 6 | DesCliente | varchar(100) | NO |  |  |
| 7 | Factura | char(3) | NO |  |  |
| 8 | NumeroFactura | int | NO |  |  |
| 9 | Fecha | date | SI |  |  |
| 10 | CO | char(3) | NO |  |  |
| 11 | Vendedor | varchar(25) | SI |  |  |
| 12 | DesVendedor | varchar(100) | NO |  |  |
| 13 | Referencia | char(50) | NO |  |  |
| 14 | DescReferencia | varchar(40) | NO |  |  |
| 15 | UM | char(4) | NO |  |  |
| 16 | Cantidad | numeric(38,4) | SI |  |  |
| 17 | CostoTotal | money | SI |  |  |
| 18 | ValorBruto | money | SI |  |  |
| 19 | Descuentos | money | SI |  |  |
| 20 | Subtotal | money | SI |  |  |
| 21 | Preciokilo | numeric(38,19) | SI |  |  |

## `SiesaContratos`  (filas: 302)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | empresa | smallint | NO |  |  |
| 2 | id | smallint | NO |  |  |
| 3 | codigoTercero | varchar(15) | SI |  |  |
| 4 | tercero | int | NO |  |  |
| 5 | cargo | varchar(4) | SI |  |  |
| 6 | banco | char(10) | SI |  |  |
| 7 | tipoCotizante | smallint | NO |  |  |
| 8 | motivoRetiro | varchar(20) | SI |  |  |
| 9 | turno | int | SI |  |  |
| 10 | departamento | int | SI |  |  |
| 11 | ccosto | varchar(15) | SI |  |  |
| 12 | tipoNomina | int | SI |  |  |
| 13 | tiempoBasico | int | SI |  |  |
| 14 | entidadPension | int | SI |  |  |
| 15 | entidadEps | int | SI |  |  |
| 16 | entidadCesantias | int | SI |  |  |
| 17 | entidadCaja | int | SI |  |  |
| 18 | entidadARP | int | SI |  |  |
| 19 | entidadSena | int | SI |  |  |
| 20 | entidadICBF | int | SI |  |  |
| 21 | fechaIngreso | datetime | NO |  |  |
| 22 | fechaRetiro | datetime | SI |  |  |
| 23 | fechaContratoHasta | datetime | SI |  |  |
| 24 | fechaPrimaHasta | datetime | NO |  |  |
| 25 | fechaVacacionesHasta | datetime | NO |  |  |
| 26 | fechaUltimoAumento | datetime | NO |  |  |
| 27 | fechaUltimoVacaciones | datetime | NO |  |  |
| 28 | fechaUltimaPension | datetime | NO |  |  |
| 29 | fechaUltimaCesantias | datetime | SI |  |  |
| 30 | fechaContratoLey50 | int | SI |  |  |
| 31 | salario | money | NO |  |  |
| 32 | salarioAnterior | money | NO |  |  |
| 33 | valorDeducible | money | NO |  |  |
| 34 | valorCesantiasCongeladas | money | NO |  |  |
| 35 | valorCesantiasRetiradas | money | NO |  |  |
| 36 | valorOtrosSalud | money | NO |  |  |
| 37 | valorSaludObligatoria | money | NO |  |  |
| 38 | cantidadHoras | numeric(9,6) | NO |  |  |
| 39 | pretencion | numeric(9,6) | NO |  |  |
| 40 | pTiempoLaborado | numeric(9,6) | NO |  |  |
| 41 | diasPagadosVacaciones | numeric(9,6) | NO |  |  |
| 42 | personasCargo | smallint | NO |  |  |
| 43 | cuentaBancaria | varchar(20) | SI |  |  |
| 44 | formaPago | tinyint | NO |  |  |
| 45 | regimenLaboral | tinyint | NO |  |  |
| 46 | auxilioTraansporte | tinyint | NO |  |  |
| 47 | procedimientoRete | int | NO |  |  |
| 48 | pactoColectivo | int | NO |  |  |
| 49 | deducible | int | SI |  |  |
| 50 | otrosSalud | int | NO |  |  |
| 51 | salarioIntegral | tinyint | NO |  |  |
| 52 | tipoCuenta | tinyint | NO |  |  |
| 53 | claseContrato | tinyint | NO |  |  |
| 54 | terminoContrato | tinyint | NO |  |  |
| 55 | foto | int | SI |  |  |
| 56 | observacion | int | SI |  |  |
| 57 | activo | tinyint | NO |  |  |
| 58 | valorPregagada | int | NO |  |  |
| 59 | valorDependientes | int | NO |  |  |
| 60 | fechaRegistro | datetime | NO |  |  |
| 61 | usuario | varchar(30) | NO |  |  |
| 62 | fechaActualizacion | datetime | NO |  |  |
| 63 | usuarioActualizacion | varchar(30) | NO |  |  |
| 64 | ley50 | int | SI |  |  |
| 65 | centroTrabajo | int | NO |  |  |
| 66 | diasContrato | int | NO |  |  |
| 67 | mSindicato | int | NO |  |  |
| 68 | mFondoEmpleado | int | NO |  |  |
| 69 | entidadFondoEmpleado | int | SI |  |  |
| 70 | entidadSindicato | int | SI |  |  |
| 71 | pFondoEmpleado | int | NO |  |  |
| 72 | pSindicato | int | NO |  |  |
| 73 | subTipoCotizante | int | SI |  |  |
| 74 | entidadSaludAdicional | int | SI |  |  |
| 75 | manejaDestajo | int | NO |  |  |
| 76 | grupoLaborDestajo | int | SI |  |  |
| 77 | cantidadDestajo | int | NO |  |  |

## `Zeus®ImportarArchivo`  (filas: 225)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | IDEN | numeric(18,0) | NO |  | IDENTITY |
| 2 | SpId | int | NO |  |  |
| 3 | Col1 | varchar(8000) | SI |  |  |
| 4 | Col2 | varchar(8000) | SI |  |  |
| 5 | Col3 | varchar(8000) | SI |  |  |
| 6 | Col4 | varchar(8000) | SI |  |  |
| 7 | Col5 | varchar(8000) | SI |  |  |
| 8 | Col6 | varchar(8000) | SI |  |  |
| 9 | Col7 | varchar(8000) | SI |  |  |
| 10 | Col8 | varchar(8000) | SI |  |  |
| 11 | Col9 | varchar(8000) | SI |  |  |
| 12 | Col10 | varchar(8000) | SI |  |  |
| 13 | Col11 | varchar(8000) | SI |  |  |
| 14 | Col12 | varchar(8000) | SI |  |  |
| 15 | Col13 | float | SI |  |  |
| 16 | Col14 | varchar(8000) | SI |  |  |
| 17 | Col15 | varchar(8000) | SI |  |  |
| 18 | Col16 | varchar(8000) | SI |  |  |
| 19 | Col17 | varchar(8000) | SI |  |  |
| 20 | Col18 | varchar(8000) | SI |  |  |
| 21 | Col19 | varchar(8000) | SI |  |  |
| 22 | Col20 | varchar(8000) | SI |  |  |
| 23 | Col21 | varchar(8000) | SI |  |  |
| 24 | Col22 | varchar(8000) | SI |  |  |
| 25 | Col23 | varchar(8000) | SI |  |  |
| 26 | Col24 | varchar(8000) | SI |  |  |
| 27 | Col25 | varchar(8000) | SI |  |  |
| 28 | Col26 | varchar(8000) | SI |  |  |
| 29 | Col27 | varchar(8000) | SI |  |  |
| 30 | Col28 | varchar(8000) | SI |  |  |
| 31 | Col29 | varchar(8000) | SI |  |  |
| 32 | Col30 | varchar(8000) | SI |  |  |
| 33 | Col31 | varchar(8000) | SI |  |  |
| 34 | Col32 | varchar(8000) | SI |  |  |
| 35 | Col33 | varchar(8000) | SI |  |  |
| 36 | Col34 | varchar(8000) | SI |  |  |
| 37 | Col35 | varchar(8000) | SI |  |  |
| 38 | Col36 | varchar(8000) | SI |  |  |
| 39 | Col37 | varchar(8000) | SI |  |  |
| 40 | Col38 | varchar(8000) | SI |  |  |
| 41 | Col39 | varchar(8000) | SI |  |  |
| 42 | Col40 | varchar(8000) | SI |  |  |
| 43 | Col41 | varchar(8000) | SI |  |  |
| 44 | Col42 | varchar(8000) | SI |  |  |
| 45 | Col43 | varchar(8000) | SI |  |  |
| 46 | Col44 | varchar(8000) | SI |  |  |
| 47 | Col45 | varchar(8000) | SI |  |  |
| 48 | Col46 | varchar(8000) | SI |  |  |
| 49 | Col47 | varchar(8000) | SI |  |  |
| 50 | Col48 | varchar(8000) | SI |  |  |
| 51 | Col49 | varchar(8000) | SI |  |  |
| 52 | Col50 | varchar(8000) | SI |  |  |
| 53 | Col51 | varchar(8000) | SI |  |  |
| 54 | Col52 | varchar(8000) | SI |  |  |
| 55 | Col53 | varchar(8000) | SI |  |  |
| 56 | Col54 | varchar(8000) | SI |  |  |
| 57 | Col55 | varchar(8000) | SI |  |  |
| 58 | Col56 | varchar(8000) | SI |  |  |
| 59 | Col57 | varchar(8000) | SI |  |  |
| 60 | Col58 | varchar(8000) | SI |  |  |
| 61 | Col59 | varchar(8000) | SI |  |  |
| 62 | Col60 | varchar(8000) | SI |  |  |
| 63 | Col61 | varchar(8000) | SI |  |  |
| 64 | Col62 | varchar(8000) | SI |  |  |
| 65 | Col63 | varchar(8000) | SI |  |  |
| 66 | Col64 | varchar(8000) | SI |  |  |
| 67 | Col65 | varchar(8000) | SI |  |  |
| 68 | Col66 | varchar(8000) | SI |  |  |
| 69 | Col67 | varchar(8000) | SI |  |  |
| 70 | Col68 | varchar(8000) | SI |  |  |
| 71 | Col69 | varchar(8000) | SI |  |  |
| 72 | Col70 | varchar(8000) | SI |  |  |
| 73 | Col71 | varchar(8000) | SI |  |  |
| 74 | Col72 | varchar(8000) | SI |  |  |
| 75 | Col73 | varchar(8000) | SI |  |  |
| 76 | Col74 | varchar(8000) | SI |  |  |
| 77 | Col75 | varchar(8000) | SI |  |  |
| 78 | Col76 | varchar(8000) | SI |  |  |
| 79 | Col77 | varchar(8000) | SI |  |  |
| 80 | Col78 | varchar(8000) | SI |  |  |
| 81 | Col79 | varchar(8000) | SI |  |  |
| 82 | Col80 | varchar(8000) | SI |  |  |
| 83 | Col81 | varchar(8000) | SI |  |  |
| 84 | Col82 | varchar(8000) | SI |  |  |
| 85 | Col83 | varchar(8000) | SI |  |  |
| 86 | Col84 | varchar(8000) | SI |  |  |
| 87 | Col85 | varchar(8000) | SI |  |  |
| 88 | Col86 | varchar(8000) | SI |  |  |
| 89 | Col87 | varchar(8000) | SI |  |  |
| 90 | Col88 | varchar(8000) | SI |  |  |
| 91 | Col89 | varchar(8000) | SI |  |  |
| 92 | Col90 | varchar(8000) | SI |  |  |
| 93 | Col91 | varchar(8000) | SI |  |  |
| 94 | Col92 | varchar(8000) | SI |  |  |
| 95 | Col93 | varchar(8000) | SI |  |  |
| 96 | Col94 | varchar(8000) | SI |  |  |
| 97 | Col95 | varchar(8000) | SI |  |  |
| 98 | Col96 | varchar(8000) | SI |  |  |
| 99 | Col97 | varchar(8000) | SI |  |  |
| 100 | Col98 | varchar(8000) | SI |  |  |
| 101 | Col99 | varchar(8000) | SI |  |  |
| 102 | Col100 | varchar(8000) | SI |  |  |

## `__RefactorLog`  (filas: 4)

| # | Columna | Tipo | Nulo | PK | Identity |
|---|---|---|---|---|---|
| 1 | OperationKey | uniqueidentifier | NO | 🔑 |  |
