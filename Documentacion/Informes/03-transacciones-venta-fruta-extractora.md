# Venta de fruta por extractora

**Módulo:** Transacciones · **Clave:** `venta-fruta-extractora` · **Estado:** Planificado

## Propósito
Totaliza la fruta (kilos netos de báscula) despachada a cada extractora en un rango de fechas, por mes y finca. Responde a quién se vende la fruta y cuánto, para gerencia y facturación.

## Fuente de datos
- Procedimiento legado probable: ninguno específico; se basa en `spSeleccionaTiquetesRegistrados` (vista `vSeleccionaTiqueteFruta` + `cTercero` como planta), agregado por extractora. Relacionado: `spSeleccionaExtractoraExterna` (catálogo `cTercero` con `extractora = 1`).
- Tablas/vistas: `aTransaccion` (TLC), `aTransaccionBascula` (tiquete, pesoNeto, racimos, `terceroExtractrora`/`empresaExtractora` = NIT), `cTercero` (`extractora = 1`), `aTransaccionNovedad` + `aFinca` para la finca.
- Lógica clave:
  - `anulado = 0`, excluir `aTransaccionEliminada`, `aTransaccionBascula.fecha BETWEEN fi AND ff`.
  - Agregar desde `aTransaccionBascula` (una fila por tiquete) para no duplicar pesos al unir con las líneas por lote.
  - Extractora: `LTRIM(RTRIM(terceroExtractrora)) = cTercero.nit` con `extractora = 1`.
  - Kilos = `SUM(pesoNeto)`; tiquetes = `COUNT(DISTINCT tiquete)`; % participación = kilos extractora / kilos totales.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Extractora | select | No | |
| Finca | select | No | |
| Agrupar por | select (extractora, extractora + mes, extractora + finca) | No | Extractora por defecto |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Extractora | NIT – razón social |
| Mes | Si se agrupa por mes |
| Finca | Si se agrupa por finca |
| Tiquetes | Cantidad |
| Racimos | Suma |
| Kilos netos | Suma de `pesoNeto` |
| Toneladas | Kilos / 1000 |
| Peso prom. racimo | Kilos / racimos |
| % participación | Sobre el total del rango |

Totales de pie: Tiquetes, Racimos, Kilos, Toneladas.

## Indicadores (KPIs)
- Toneladas vendidas.
- Tiquetes despachados.
- Extractoras atendidas.
- Extractora principal (% de participación).

## Gráfica
- Tipo: barras apiladas.
- Eje X: mes; eje Y: toneladas; series: extractora (máx. 6 + "Otras").
- Por qué: muestra la evolución mensual y la composición por comprador.

## Supuestos a validar
- No hay precio de venta en `aTransaccionBascula`; el informe es solo de kilos. Confirmar si existe un valor de venta a incluir.
- El legado une por `cTercero.codigo = planta`; la app nueva guarda el NIT en `terceroExtractrora`. Tiquetes antiguos pueden no enlazar.
- Solo tiquetes registrados (TLC); los pendientes de `bRegistroBascula` están en "Tiquetes pendientes por registrar".
