# Liquidación contratistas por periodo

**Módulo:** Transacciones · **Clave:** `liquidacion-contratistas-periodo` · **Estado:** Implementado

## Propósito
Detalla las labores ejecutadas por trabajadores de contratistas en un rango de fechas, agrupadas por contratista (proveedor), para liquidar y pagar a cada contratista. Lo usan nómina y cuentas por pagar.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLiquidacionContratistaPeriodoTercero` (@empresa, @fi, @ff) sobre `vSeleccionaLiquidacionContratista`. Alternativa: `spSeleccionaLiquidacionContratistaPeriodo` sobre `vTransaccionAgronomico` con `contratista = 1`.
- Tablas/vistas: `vSeleccionaLiquidacionContratista` (aTransaccion, aTransaccionNovedad, aTransaccionTercero, aNovedad, nFuncionario, cTercero proveedor, aLotes, aFinca, cCentrosCosto).
- Lógica clave:
  - Vista ya filtra `anulado = 0` y `aTransaccionTercero.contratista = 1`.
  - Contratista: `cTercero.id = ISNULL(nFuncionario.proveedor, aTransaccionTercero.proveedor)` → `nit`, `dv`, `razonSocial`.
  - `fechaT` (fecha de la transacción) `BETWEEN fi AND ff`, `empresa = @empresa`.
  - Subtotales por contratista y por trabajador; excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Contratista | select (cTercero proveedores con funcionarios contratistas) | No | |
| Finca | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Contratista | NIT-DV – razón social |
| Trabajador | Cédula – nombre |
| Fecha | `fecha` de la labor |
| Día | Día de la semana |
| Documento | `tipo` / `numero` |
| Finca / Lote | `nombreFinca` / `lote` |
| Labor | `nombreNovedad` |
| Cantidad / U. medida | `cantidad` / `uMedida` |
| Jornales | `jornales` |
| Precio | `precioLabor` |
| Valor | `valorTotal` |

Totales de pie: Valor total; subtotal por contratista y por trabajador.

## Indicadores (KPIs)
- Valor total a liquidar.
- Contratistas con labores.
- Trabajadores de contratistas.
- Jornales totales.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: contratista (de mayor a menor valor); eje X: valor a liquidar.
- Por qué: comparación de categorías con nombres largos.

## Supuestos resueltos
- No se usa `vSeleccionaLiquidacionContratista`: `aTransaccionTercero.contratista` nunca es 1 y la vista no devuelve filas. Contratista = trabajador con `nFuncionario.contratista = 1`.
- Proveedor: `nFuncionario.proveedor` se resuelve contra `cTercero` por `nit` o, si es numérico, por `id`. Si no resuelve se agrupa en "Proveedor no asociado" (al final) y se avisa.
- Se filtra por fecha de la transacción (`aTransaccion.fecha`), como el legado; la columna Fecha muestra esa misma fecha. Rango máximo de 1 año y límite de 20.000 líneas.
- Solo documentos no anulados; se excluyen los de `aTransaccionEliminada`. Las líneas con signo 2 restan en cantidad y valor.
- No se filtra por `ejecutado` (nunca es 1).
- Filtro Contratista: proveedores resueltos de funcionarios contratistas (valor `cTercero.id`).
- Alertas por línea: sin precio, precio distinto al más frecuente de la labor en el rango y valores negativos.
