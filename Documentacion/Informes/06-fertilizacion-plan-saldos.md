# Plan de fertilización con saldos

**Módulo:** Fertilización · **Clave:** `plan-saldos` · **Estado:** Planificado

## Propósito
Muestra cada plan de fertilización (PFA) por lote e insumo con lo planificado, lo ejecutado según los registros de labores de fertilización (RLF) y el saldo pendiente. Es para el agrónomo y el almacén, que controlan el avance del plan y las necesidades de insumo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaPlanFertilizacionSaldo` (@empresa). Complementarios: `spSeleccionaPeriodosPlanFertilizacion` (combo de periodos) y `spSeleccionaTraFerSaldo` (combo de planes).
- Tablas/vistas: `aTransaccion` (PFA y RLF), `aTransaccionItem`, `iItems`, `aFinca`, `aLotes`, `aVariedad`.
- Lógica clave:
  - Plan: `aTransaccion.tipo = 'PFA'`, `anulado = 0`. Las líneas están en `aTransaccionItem` (lote, item, uMedida, noPalmas, dosis, cantidad = palmas × dosis, mBulto, pBulto).
  - Ejecutado = `SUM(aTransaccionItem.cantidad)` de las RLF no anuladas con `aTransaccion.referencia = plan.numero`, mismo `item` y mismo `lote`.
  - **Saldo = plan.cantidad − ejecutado** (calculado). No se usa `aTransaccionItem.saldo`, que en los datos reales nunca se descuenta.
  - % ejecución = ejecutado / plan. Bultos pendientes = saldo / pBulto (si pBulto > 0).
  - Edad = año actual − `aLotes.añoSiembra`. Periodo del plan: `fecha`–`fechaFinal`, `año`, `mesIfer`–`mesFfer`.
  - El procedimiento legado une `aTransaccionItem c` con una condición que no restringe nada (produce un producto cartesiano que se oculta con DISTINCT). No se replica.
  - Se excluyen los PFA y RLF que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Plan (PFA) | Select (número + rango de fechas) | No | Por defecto, planes vigentes |
| Año | Select | No | `aTransaccion.año` del plan |
| Finca | Select (aFinca) | No | |
| Insumo | Select (iItems) | No | |
| Solo con saldo | Check | No | Saldo > 0 |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Plan | Número y periodo |
| Finca | |
| Lote | |
| Año siembra / Edad | |
| Variedad | |
| Ha netas | |
| Insumo | Código y descripción |
| U. medida | |
| Palmas | `noPalmas` |
| Dosis | Por palma |
| Planificado | `cantidad` |
| Ejecutado | Suma de RLF |
| Saldo | Planificado − ejecutado |
| % ejecución | Barra de progreso |
| Bultos pendientes | Saldo / pBulto |

Totales de pie: planificado, ejecutado, saldo y bultos pendientes. Los totales por insumo solo tienen sentido con una misma unidad, así que se agrupan por insumo.

## Indicadores (KPIs)
- % de ejecución global del plan (ejecutado / planificado).
- Saldo total pendiente, con unidad cuando se filtra un solo insumo.
- Lotes con saldo > 0.
- Bultos pendientes.

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: insumo, o lote si se filtra un insumo, ordenado por planificado de mayor a menor. Eje X: cantidad. Series: ejecutado y saldo; la suma de las dos es el planificado.
- Por qué: muestra la composición plan = ejecutado + saldo por categoría y cuánto falta.

## Supuestos a validar
- El vínculo RLF → PFA es `aTransaccion.referencia` del RLF igual al `numero` del PFA, con la misma empresa (el legado no compara empresa en la subconsulta).
- Si un RLF puede aplicar a varios planes, o si también debe compararse `aTransaccionItem.novedad`.
- La unidad de medida del RLF debe coincidir con la del plan para restar (kg frente a bultos).
