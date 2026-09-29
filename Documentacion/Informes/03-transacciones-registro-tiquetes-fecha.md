# Registro tiquetes por fecha

**Módulo:** Transacciones · **Clave:** `registro-tiquetes-fecha` · **Estado:** Planificado

## Propósito
Lista los tiquetes de báscula ya registrados en transacciones de cosecha (TLC) en un rango de fechas, con pesos, racimos, vehículo y extractora. Lo usan producción y administración para conciliar la fruta despachada.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaTiquetesRegistrados` (@empresa, @fi, @ff). Relacionado: `spSeleccionaInformeTiquetesAgronomico` (detalle por lote y trabajador de un tiquete).
- Tablas/vistas: `vSeleccionaTiqueteFruta` (aTransaccion + aTransaccionBascula + aTransaccionNovedad + aFinca), `cTercero` (extractora).
- Lógica clave:
  - `anulado = 0`, `empresa = @empresa`, `aTransaccion.fecha BETWEEN fi AND ff`.
  - Extractora: `aTransaccionBascula.terceroExtractrora` (NIT) → `cTercero` con `extractora = 1`.
  - Kilos por tiquete = `aTransaccionBascula.pesoNeto`; racimos = `aTransaccionBascula.racimos`; peso promedio racimo = pesoNeto / racimos.
  - La vista une con aTransaccionNovedad y hace DISTINCT por finca: un tiquete con lotes en dos fincas sale dos veces. Agrupar por tiquete para no duplicar pesos.
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Finca | select | No | |
| Extractora | select (cTercero extractora=1) | No | |
| Tiquete | texto | No | Búsqueda parcial |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha | Fecha de la transacción |
| Número | Número de la transacción TLC |
| Tiquete | `tiquete` |
| Fecha tiquete | `aTransaccionBascula.fecha` |
| Finca | `nombreFinca` |
| Extractora | Razón social de la extractora |
| Vehículo / Remolque | Placas |
| Conductor | `nombreConductor` |
| Peso bruto / Tara / Neto | kg |
| Racimos | `racimosTiquete` |
| Peso prom. racimo | Neto / racimos |
| Externo | `interno` invertido (Sí/No) |

Totales de pie: Peso neto, Racimos y peso promedio ponderado.

## Indicadores (KPIs)
- Tiquetes registrados.
- Kilos netos totales.
- Racimos totales.
- Peso promedio por racimo (kg).

## Gráfica
- Tipo: barras apiladas.
- Eje X: fecha (día); eje Y: kilos netos; series: extractora.
- Por qué: muestra la evolución diaria y la composición por destino de la fruta.

## Supuestos a validar
- El legado une la extractora por `cTercero.codigo = planta`; la app nueva guarda el NIT en `terceroExtractrora`/`empresaExtractora`. Se asume unir por NIT.
- Si el filtro de fecha es el de la transacción o el del tiquete (`aTransaccionBascula.fecha`).
