# Diferencia días cosecha y recepción

**Módulo:** Transacciones · **Clave:** `diferencia-dias-cosecha-recepcion` · **Estado:** Planificado

## Propósito
Mide cuántos días pasan entre la cosecha de la fruta en el lote y su recepción/pesaje en báscula, y qué porcentaje de kilos llega en cada rango de días. La fruta que tarda en llegar pierde calidad (acidez); lo usan producción y agronomía.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaDiferenciasCosecha` (@empresa, @año, @mes).
- Tablas/vistas: `aTransaccion`, `aTransaccionBascula` (fecha del tiquete), `aTransaccionTercero` (lote, `fechaNovedad`, cantidad = kilos), `aLotes` (añoSiembra), `aNovedad` (`claseLabor = 2`).
- Lógica clave:
  - `anulado = 0`, `empresa = @empresa`, `año = @año`, `mes = @mes`; excluir `aTransaccionEliminada`.
  - Días = `DATEDIFF(day, aTransaccionTercero.fechaNovedad, aTransaccionBascula.fecha)`.
  - Kilos = `SUM(aTransaccionTercero.cantidad)` por lote y días; total por días; % = kilos del día / kilos totales × 100 (el legado divide enteros: calcular en decimal).
  - Promedio ponderado de días = Σ(kilos × días) / Σ kilos.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | select | Sí | |
| Mes | select | Sí | Mes actual por defecto |
| Finca | select | No | |
| Lote | select dependiente | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | Código |
| Año siembra | `añoSiembra` |
| 0 días, 1 día, 2 días, … | Kilos por diferencia de días (pivote) |
| Total kilos | Suma |
| Días promedio | Ponderado por kilos |

Fila de pie "Total por día" (kilos por columna) y fila "% del total" por columna.

## Indicadores (KPIs)
- Días promedio cosecha → recepción (ponderado).
- % de kilos recibidos el mismo día o al día siguiente (≤ 1 día).
- Kilos con 3 o más días de rezago.
- Lote con mayor rezago promedio.

## Gráfica
- Tipo: barras.
- Eje X: días de diferencia (0, 1, 2, 3+); eje Y: % de kilos.
- Por qué: distribución de pocas categorías ordenadas; se ve de inmediato cuánto llega a tiempo.

## Supuestos a validar
- Diferencias negativas (tiquete antes de la cosecha) indican error de digitación; se propone mostrarlas en una columna "Negativo".
- Solo transacciones con tiquete (TLC); confirmar si hay cosecha registrada sin báscula que deba excluirse o marcarse.
- El legado usa `DATEDIFF` sobre la fecha del tiquete de `aTransaccionBascula` (no la de `bRegistroBascula`).
