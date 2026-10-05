# Tiquetes pendientes por registrar

**Módulo:** Transacciones · **Clave:** `tiquetes-pendientes` · **Estado:** No implementado — sin fuente de datos vigente

## Propósito
Lista los tiquetes pesados en báscula (entrada de fruta propia) que todavía no tienen una transacción de cosecha registrada. Permite a producción asegurar que toda la fruta despachada quede distribuida por lote y trabajador.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaTiquetesNoRegistrados` (@empresa, @fi, @ff). Relacionado: `spSeleccionaTiquetesBasculaExtractora` (misma idea filtrando por tiquete, desde 2018).
- Tablas/vistas: `bRegistroBascula` (tiquetes de báscula, `tipo = 'EPE'`), `aFinca` (por `codigoEquivalencia`), `aTransaccion` + `aTransaccionBascula` (tiquetes ya usados).
- Lógica clave:
  - `bRegistroBascula.tipo = 'EPE'`, `tiquete <> ''`, `CONVERT(date, fechaProceso) BETWEEN fi AND ff`.
  - Pendiente = tiquete NOT EXISTS en `aTransaccionBascula` de una `aTransaccion` no anulada de la empresa (y no eliminada).
  - Finca: `aFinca.codigoEquivalencia = CONVERT(varchar, bRegistroBascula.finca)` filtrando `aFinca.empresa`.
  - Días pendiente = hoy − fecha de proceso.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | Hoy por defecto |
| Finca | select | No | |
| Tiquete | texto | No | Búsqueda parcial |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha | Fecha de proceso |
| Tiquete | `tiquete` |
| Finca | `nombreFinca` |
| Vehículo / Remolque | Placas |
| Peso neto | kg |
| Racimos | `racimos` |
| Días pendiente | Antigüedad |

Totales de pie: Peso neto, Racimos.

## Indicadores (KPIs)
- Tiquetes pendientes.
- Kilos pendientes por registrar.
- Tiquete más antiguo (días).

## Gráfica
- Tipo: barras.
- Eje X: fecha (día); eje Y: kilos pendientes.
- Por qué: muestra en qué días se acumula el rezago de registro.

## Decisión
William decidió no implementar el informe: no hay una fuente vigente de tiquetes pesados contra la cual comparar las transacciones de cosecha. El informe queda en el catálogo como "próximamente" (sin clase).

## Hallazgos (validados con SELECT)
- `bRegistroBascula` solo tiene datos del 03/01/2017 al 18/08/2018: 14.075 tiquetes `EPE` de las empresas 1 y 2. La aplicación actual no escribe en esa tabla.
- Ninguno de esos tiquetes coincide con los de `aTransaccionBascula`. Las TLC empiezan en marzo de 2019 y sus tiquetes son de las extractoras: se digitan al crear la TLC (vía `spInsertaTiqueteAgro`).
- `bRegistroBascula.finca` (5, 79, 67…) no enlaza con `aFinca.codigoEquivalencia` (309901…), así que tampoco se puede asignar finca ni empresa.
- El legado `spSeleccionaTiquetesNoRegistrados` además no filtra empresa ni excluye transacciones eliminadas (`aTransaccionEliminada`).

## Para retomarlo
- Se requiere una fuente actual de pesajes (báscula propia o archivo de la extractora) con, al menos, tiquete, finca y fecha.
- Con esa fuente, pendiente = tiquete sin registro en `aTransaccionBascula` de una `aTransaccion` no anulada ni eliminada de la empresa; el diseño de filtros, tabla, KPIs y gráfica de este documento sigue siendo válido.
