# Liquidación contratistas por periodo y lotes

**Módulo:** Transacciones · **Clave:** `liquidacion-contratistas-periodo-lotes` · **Estado:** Implementado

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

## Supuestos resueltos
- Misma base que "Liquidación contratistas por periodo": contratista = trabajador con `nFuncionario.contratista = 1`; proveedor resuelto contra `cTercero` por `nit` o `id`, y si no resuelve se agrupa en "Proveedor no asociado" (al final, con aviso). Fecha de la transacción (`aTransaccion.fecha`), solo no anulados, se excluye `aTransaccionEliminada` si existe y el signo 2 resta en cantidad y valor. Rango máximo de 1 año y límite de 20.000 filas.
- Se une `aNovedad` directamente. `muestraInformeContratista` nunca vale 1 en los datos (0 o nulo) y todas las labores de contratistas tienen `manejaLote = 1`; por eso la fila se abre por lote cuando `muestraInformeContratista = 1` o `manejaLote = 1`; si no, el lote queda vacío. Una fila de labor que se abre por lote sin lote se marca y se avisa.
- Precio prom. = promedio ponderado (valor / cantidad), no el promedio simple del legado; vacío si la cantidad neta es 0. Se exponen precio mínimo y máximo para detectar varios precios.
- Sección = `aTransaccionTercero.seccion` y, si está vacía, `aLotes.seccion` (hoy ambas vienen vacías en los datos).
- Filtro Lote dependiente de la finca (valor `finca|lote`); se valida que el lote pertenezca a la finca elegida.
- Los totales de valor y jornales cuadran con "Liquidación contratistas por periodo" para el mismo rango.
