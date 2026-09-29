# Procesos Críticos — Lectura de la Lógica de Negocio Real

Casi toda la lógica de negocio de este sistema **vive en procedimientos almacenados de T-SQL**, no en el código .NET (que en gran parte es UI de Web Forms invocando estos procedimientos). Varios de ellos son enormes: `spPrecontabilizaNominaTipoPeriodo` (~141.000 caracteres), `spGuardaContabilizacion` (~104.000), `spLiquidacionNominaPeriodo` (~97.000), `spPreliquidacionDescuentoAgronomico` (~13.000). Esta es la implicación arquitectónica más importante para cualquier plan de mantenimiento o migración: **no se puede "reescribir el front" sin re-implementar estas reglas**, y no se puede entender el sistema leyendo solo `Infos/Formas/*.aspx`.

Este documento no transcribe esos procedimientos completos (están en `sql_criticos/` para consulta), sino que documenta **el flujo real**, verificado leyendo su código y sus dependencias (`sys.sql_expression_dependencies`), para cerrar los puntos que quedaron abiertos (⚠️) en el backlog de historias de usuario (`Documentacion/HistoriasUsuario/`).

---

## 0. Hallazgo transversal: arquitectura multi-empresa

`gEmpresa` tiene **2 filas** y es la tabla más referenciada del modelo (41 FKs). Absolutamente todos los procedimientos de negocio reciben `@empresa` como parámetro y filtran por él. Esto significa que **una sola base de datos sirve a 2 compañías**, y cualquier consulta, reporte o migración debe respetar ese filtro o mezclará datos entre ambas.

---

## 1. Báscula / Tiquetes (resuelve ⚠️ HU-041)

Tabla núcleo: `bRegistroBascula`. El ciclo de vida real de un tiquete:

1. **Entrada** (peso bruto) crea el registro en `bRegistroBascula` con estado inicial.
2. **`spCompletaTiquete`** cierra el ciclo: calcula `pesoNeto = pesoBruto − pesoTara`, asigna el consecutivo definitivo vía `spRetornaConsecutivoTransaccion`, marca `estado = 'SP'` e incrementa el contador `gTipoTransaccion.actual` (los consecutivos de cada tipo de transacción/empresa se llevan en esa tabla, no con IDENTITY).
3. **`spAnulaModificaTiquete`** hace dos cosas con el mismo procedimiento según el flag `@anulaTiquete`:
   - **Anular**: duplica el registro completo con `tipo = 'ANULADO'` y un nuevo consecutivo (no borra el original — es un soft-delete por duplicación, útil para auditoría pero significa que `bRegistroBascula` acumula "copias anuladas").
   - **Modificar**: actualiza el tiquete y además genera documentos de remisión (`remisionPlanta`, `remisionComercializadora`) — es decir, editar un tiquete ya despachado dispara la generación de remisiones nuevas.
4. Para la fruta de palma que llega a la extractora existe un camino paralelo: **`spInsertaTiqueteAgro`** inserta en `aTransaccionBascula` (con `empresaExtractora`, racimos, sacos, conductor) — separado de `bRegistroBascula`.

**Sobre las 3 pantallas de tiquete (`RegistroTiquete`, `RegistroTiqueteCompleto`, `RegistroTiqueteViejo`)**: con esta evidencia, lo más probable es que correspondan a etapas distintas del mismo ciclo (pesaje de entrada vs. cierre/completar vs. una versión previa a un cambio del proceso), **no** a tres implementaciones redundantes de lo mismo. Sigue siendo necesario confirmar con el usuario de báscula cuál pantalla dispara cuál procedimiento antes de tocar código.

**Riesgo detectado**: los consecutivos (`gTipoTransaccion.actual`) se actualizan con `update ... set actual = actual + 1` dentro de la misma transacción que el insert/update — es el patrón correcto para evitar duplicados, pero bajo alta concurrencia (varias básculas simultáneas) puede generar bloqueos; no se identificó un manejo explícito de reintento.

---

## 2. Liquidación de precios de labores (resuelve ⚠️ HU-039)

No existe un procedimiento genérico "liquidar precio de fruta"; lo que hay es específico a **mano de obra agrícola (labores pagadas a terceros/contratistas)**:

- **`spReplicaPrecioLaboresAños`**: copia la lista de precios de labor (`aNovedadLotePrecio`) de un año a otro (`@añoAnterior` → `@añoActual`), validando que no exista ya el año destino. Esto es lo que probablemente dispara `Padministracion/ListaPrecios.aspx` / `ListaPreciosLote.aspx` al iniciar un nuevo año.
- **`spReliquidacionPrecioLaboresFecha`**: recalcula `precioLabor` y `valorTotal` en `aTransaccionTercero` para las transacciones **no ejecutadas** (`ejecutado = 0`) dentro de un rango de fechas, usando la función escalar `fRetornaPrecioLaboresTercero` (definida en `06-Funciones.md`). **Esta es, con alta probabilidad, la lógica detrás de `LiquidarPrecios.aspx`.**
- Cada corrida queda registrada en `aLogReliquidacion` (empresa, rango de fechas, usuario, fecha) — sí hay trazabilidad de auditoría para este proceso puntual.

**Pendiente de validar con negocio**: la fórmula exacta dentro de `fRetornaPrecioLaboresTercero` (jerarquía finca/sección/lote/contratista) — está en `06-Funciones.md` para lectura del equipo técnico, pero su interpretación de negocio (qué prevalece si hay precio de lote y precio general) requiere confirmación funcional antes de tocarla.

---

## 3. Replicación entre empresas (corrige ⚠️ HU-021 del backlog de historias)

**Corrección respecto a lo documentado en `Documentacion/HistoriasUsuario/03-Epica-Administracion-Sistema.md`**: `Sadministracion/ReplicarTablas.aspx` **no es replicación de servidor a servidor ni entre sedes** — es una utilidad para **copiar el contenido de una tabla de la Empresa A a la Empresa B** dentro de la misma base de datos (`SpInsertaTablaReplicar`), típicamente para poblar catálogos de una compañía nueva a partir de la otra. Cada copia se registra en la tabla `sReplicacion` (empresaA, empresaB, tabla, fecha, usuario) vía `SpInsertasReplicacion`, y `spNoRegistrosTablaReplicar` cuenta cuántos registros hay pendientes/disponibles para copiar.

**Riesgo técnico a destacar**: `spInsertaTablaReplicar` construye un `INSERT` completamente dinámico concatenando el nombre de tabla recibido por parámetro (`@tabla`) y ejecuta con `EXECUTE(@ejecutar)`. El nombre de tabla no se valida contra una lista blanca dentro del procedimiento — si la pantalla que lo invoca no restringe `@tabla` a un combo cerrado de tablas válidas, es un vector de SQL dinámico que debería revisarse (no es inyección desde un usuario externo porque requiere sesión de administrador, pero sí es una práctica insegura que facilitaría un error operativo o un abuso por un usuario interno malicioso).

---

## 4. Liquidación de nómina (resuelve ⚠️ HU-048)

Pipeline real, confirmado por las llamadas internas (`EXEC`) y las dependencias:

```
spLiquidacionContratoTrabajador   (punto de entrada, por trabajador/contrato)
 ├── spLiquidacionNominaPeriodo   (motor principal — el procedimiento más grande del sistema)
 │    ├── SpSeleccionaPreLiquidacionHora
 │    ├── spCalculaTipoLiquidacion       (valor día/hora según tipo de liquidación y salario)
 │    ├── spLiquidaConceptoTercero       (liquida cada concepto de nómina, nConcepto x nParametrosAno)
 │    ├── spRecalculaJornalesCargadores
 │    └── spliquidaEmbargosNomina        (embargos judiciales, gTipoEmbargo/nEmbargos)
 └── spliquidaEmbargosNomina
```

`spLiquidacionNominaPeriodo` toca, además de las tablas propias de `Nomina` (`nContratos`, `nFuncionario`, `nPrestamo`, `nVacaciones`, `nIncapacidad`, `nEmbargos`, `nLiquidacionPrima*`…), **tablas del módulo Agro** (`tmpDescuentaAgro`, `vTransaccionAgronomico`) — confirma que **la nómina de trabajadores de campo se liquida junto con sus transacciones agrícolas** (p. ej. descuentos por insumos o adelantos asociados a labores), un acoplamiento real entre Nómina y Agro que no era evidente desde las pantallas.

Después de liquidar, la contabilización es un segundo pipeline igual de grande:

```
spPrecontabilizaNominaTipoPeriodo   (~141.000 caracteres — el procedimiento más grande de toda la base)
spGuardaContabilizacion             (~104.000 caracteres)
```

Ambos leen del resultado de la liquidación (`nLiquidacionNomina*`, `vSeleccionaLiquidacionDefinitiva*`) y de catálogos contables (`cPuc`, `cCentrosCosto`, `cParametroContaNomi`) y de **entidades de seguridad social colombianas** (`vEntidadEps`, `vEntidadArp`, `vEntidadCaja`, `vEntidadIcbf`, `vEntidadSena`, `vEntidadFondo`/Pensión) — confirma que el sistema calcula la liquidación legal completa (salud, pensión, ARL, caja de compensación, ICBF, SENA) según normativa laboral colombiana, no solo el neto a pagar.

**Integración con SIESA**: los procedimientos `sp_adiciones_siesa` y `sp_consumo_siesa` (ver `04-Procedimientos.md`) sugieren que el resultado contable se exporta hacia/importa desde **SIESA**, un ERP contable de uso común en Colombia — punto a confirmar con el equipo si se planea tocar la contabilización.

**Pendiente de validar con negocio**: la parametrización legal (`nParametrosAno`, `nParametrosGeneral`, `nTablaSmlvRedondeo`) cambia cada año por normativa — cualquier trabajo sobre este módulo debe empezar por entender cómo se actualizan esos parámetros anualmente (salario mínimo, topes, tarifas), no asumir valores fijos.

---

## Procedimientos de referencia guardados como `.sql`

Para no inflar este documento, el código fuente completo de los procedimientos mencionados arriba (y otros relacionados) está guardado en [`sql_criticos/`](sql_criticos/) tal como vive en la base de datos hoy — es la fuente de verdad para el equipo de desarrollo, este documento es solo el mapa de lectura.
