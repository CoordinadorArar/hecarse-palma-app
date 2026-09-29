# Tiquetes pendientes por registrar

**Módulo:** Transacciones · **Clave:** `tiquetes-pendientes` · **Estado:** Planificado

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

## Supuestos a validar
- `bRegistroBascula` no tiene columna empresa en el filtro del legado; confirmar cómo separar por empresa (vía `aFinca.empresa`).
- Que `tipo = 'EPE'` identifica la entrada de fruta propia.
- El legado no excluye transacciones eliminadas (`aTransaccionEliminada`); si una se elimina, su tiquete debe volver a pendiente.
