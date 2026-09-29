# Catalogo de Procedimientos Almacenados (1648)

El listado completo (con parametros y fechas) esta en `procedimientos_catalogo.csv`, importable a Excel/Jira/Confluence. Este archivo resume la clasificacion usada y como se genero.

## Metodologia de clasificacion

Cada procedimiento se clasifico automaticamente a partir de su nombre, siguiendo el patron detectado en el codigo real:

- **Prefijo `Sp`/`sp`/`usp`**: convencion universal de nombre de procedimiento, se ignora para clasificar.
- **Verbo** (`Actualiza`→UPDATE, `Inserta`→INSERT, `Elimina`/`Delete`→DELETE, `Selecciona`/`Get`/`Consulta`→SELECT, `Liquida`→proceso de negocio, `Verifica`/`Valida`→validacion, `Retorna`, `Informe`, etc.).
- **Entidad/modulo**: el resto del nombre suele conservar el prefijo de la tabla que opera (`a`=Agro, `n`=Nomina, `p`=Planta/Palma, `s`=Seguridad, etc. — ver leyenda en el README de este directorio).

No es una clasificacion perfecta (algunos nombres no siguen el patron y quedan como `Sin clasificar` u `Otro / Utilitario`); sirve como punto de partida para navegar 1648 objetos, no como documentacion de reglas de negocio.

## Por tipo de operacion

| Operacion | Cantidad |
|---|---|
| Consultar (SELECT) | 502 |
| Eliminar (DELETE) | 227 |
| Crear (INSERT) | 222 |
| Consultar por llave (SELECT 1 registro) | 198 |
| Actualizar (UPDATE) | 196 |
| Sin clasificar | 146 |
| Validar | 62 |
| Generar consecutivo | 21 |
| Liquidar (proceso de negocio) | 21 |
| Anular/Reversar | 16 |
| Sistema (auto-generado SSMS/motor) | 9 |
| Generar (batch/archivo) | 8 |
| Calcular | 5 |
| Cambiar estado | 3 |
| Abrir periodo/proceso | 2 |
| Ejecutar proceso | 2 |
| Integracion con SIESA (ERP contable externo) | 2 |
| Modificar puntual | 1 |
| Cargar datos (batch/import) | 1 |
| Completar proceso | 1 |
| Guardar (INSERT/UPDATE) | 1 |
| Imprimir/Reporte | 1 |
| Contabilizar | 1 |

## Por modulo

| Modulo | Cantidad |
|---|---|
| Nomina | 321 |
| Otro / Utilitario | 270 |
| Agro (Fincas, Lotes, Transacciones de campo) | 204 |
| Contabilidad | 149 |
| Catalogos Generales | 142 |
| Planta / Proceso de extraccion (fruta, pepa, tanques) | 138 |
| Seguridad y Sistema | 109 |
| Inventario / Items / Bodega | 84 |
| Laboratorio (calidad, tanques) | 79 |
| Bascula y Transporte | 46 |
| Logistica de Despacho | 28 |
| Mercado | 23 |
| Cuentas por Pagar | 20 |
| Cuentas por Cobrar | 10 |
| Metadatos de Sistema (motor dinamico) | 10 |
| N/A | 9 |
| Temporales / Staging de procesos batch | 4 |
| Integracion | 2 |

## Hallazgos notables

- **No existe un procedimiento de login/autenticacion** (`login`, `clave`, `password`, `autentic` no aparecen en ningun nombre). La validacion de credenciales vive en el codigo de `Seguridad/App_Code/Security.cs` (compilado, no disponible como fuente en este despliegue), probablemente comparando directamente contra `sUsuarios`.
- **7 procedimientos son artefactos de sistema** generados automaticamente por los diagramas de base de datos de SSMS (`sp_alterdiagram`, `sp_creatediagram`, etc.) y **2 son integracion con SIESA** (`sp_adiciones_siesa`, `sp_consumo_siesa`), un ERP contable externo — confirma que el sistema exporta/recibe datos contables de un tercero.
- Varios procedimientos superan las **1000-2000 lineas** de codigo (`spGuardaContabilizacion`, `spLiquidacionNominaPeriodo`, `spPrecontabilizaNominaTipoPeriodo`, `spPreliquidacionDescuentoAgronomico`) — ver detalle en `05-Procesos-Criticos-Liquidacion.md`.