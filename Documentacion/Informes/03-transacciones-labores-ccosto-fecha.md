# Labores por centro de costo en fecha

**Módulo:** Transacciones · **Clave:** `labores-ccosto-fecha` · **Estado:** Planificado

## Propósito
Totaliza cantidades y valor de labores por centro de costo en un rango de fechas. Sirve a contabilidad y dirección para distribuir el costo de mano de obra de campo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresCcostoFecha` (@fi, @ff, @empresa).
- Tablas/vistas: `vSeleccionaTransaccionesAgronomico` (centro de costo tomado de `nContratos.ccosto` → `cCentrosCosto`). Alternativa: `vTransaccionAgronomico` (centro de costo de `aTransaccionTercero.ccosto`).
- Lógica clave:
  - Agrupa por `codCCosto`, `nombreCCosto`, `codLabor`, `nombreLabor`, `uMedida`, `precioLabor`: `SUM(cantidadLabor)` y `SUM(cantidadLabor) * precioLabor` como total.
  - Añadir los filtros que el legado declara pero no aplica: fechas, empresa y `anulado = 0`.
  - Preferir `SUM(valorTotalTercero)` como valor, coherente con los demás informes.
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Centro de costo | select (cCentrosCosto) | No | |
| Finca | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Centro de costo | `codCCosto` – `nombreCCosto` |
| Labor | `codLabor` – `nombreLabor` |
| U. medida | `uMedida` |
| Cantidad | Suma |
| Precio | `precioLabor` |
| Total | Valor |

Totales de pie: Total general; subtotal por centro de costo.

## Indicadores (KPIs)
- Valor total.
- Centros de costo con movimiento.
- Centro de costo de mayor valor (nombre y %).

## Gráfica
- Tipo: barras horizontales.
- Eje Y: centro de costo (ordenado de mayor a menor, top 10 + "Otros"); eje X: valor.
- Por qué: comparación de categorías con etiquetas largas.

## Supuestos a validar
- El legado no aplica los filtros de fecha/empresa/anulado (defecto); se asume que deben aplicarse.
- Qué centro de costo manda: el del contrato del trabajador (`nContratos.ccosto`, legado) o el registrado en la línea (`aTransaccionTercero.ccosto`).
- Total con `cantidad * precio` (legado) vs. `valorTotal` guardado; pueden diferir por redondeos o signo.
