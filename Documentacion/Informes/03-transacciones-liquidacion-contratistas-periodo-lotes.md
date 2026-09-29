# Liquidación contratistas por periodo y lotes

**Módulo:** Transacciones · **Clave:** `liquidacion-contratistas-periodo-lotes` · **Estado:** Planificado

## Propósito
Consolidado por contratista, trabajador y labor, abierto por lote cuando la labor lo exige, para soportar la cuenta de cobro del contratista y el costo por lote. Para nómina, cuentas por pagar y costos.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLiquidacionContratistaTerceroNovedad` (@empresa, @fi, @ff).
- Tablas/vistas: `vTransaccionAgronomico` + `aNovedad.muestraInformeContratista`.
- Lógica clave:
  - `fechaTransaccion BETWEEN fi AND ff`, `codEmpresa = @empresa`, `anulado = 0`, `contratista = 1`.
  - Labores con `muestraInformeContratista = 0`: se agrupan sin lote; con `= 1`: se agrupan por `codLote` (UNION de ambas).
  - Agrupa por contratista (`codigoContratista`/`nombreContratista`, "PROVEEDOR NO ASOCIADO" si nulo), trabajador, labor, unidad, finca, sección: `SUM(cantidadTercero)`, `SUM(valorTotalTercero)`, `AVG(precioLabor)`, `SUM(jornalTercero)`.
  - Excluir `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Contratista | select | No | |
| Finca | select | No | |
| Lote | select dependiente | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Contratista | Código – nombre |
| Trabajador | Cédula – nombre |
| Finca / Sección | Ubicación |
| Lote | Código o vacío si la labor no se abre por lote |
| Labor | Código – nombre |
| U. medida | `uMedida` |
| Cantidad | Suma |
| Jornales | Suma |
| Precio prom. | Promedio |
| Valor | Suma |

Totales de pie: Valor, Jornales; subtotales por contratista.

## Indicadores (KPIs)
- Valor total a liquidar.
- Contratistas.
- Lotes intervenidos por contratistas.
- Valor sin proveedor asociado.

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: contratista; eje X: valor; series: finca (o top 6 lotes + "Otros" si se filtra una finca).
- Por qué: muestra cuánto cobra cada contratista y en qué frentes se ejecutó.

## Supuestos a validar
- `muestraInformeContratista` existe en `aNovedad` pero no aparece en la definición documentada de `vTransaccionAgronomico`; habrá que unir `aNovedad` directamente.
- Precio promedio simple (legado) vs. ponderado por cantidad.
