# Labores detalle

**Módulo:** Transacciones · **Clave:** `labores-detalle` · **Estado:** Implementado

## Propósito
Listado línea a línea de las transacciones de labores (documento, lote, labor, trabajador, cantidades, precio, valor, estado) con filtros amplios. Es la consulta de auditoría para revisar exactamente qué se digitó.

## Fuente de datos
- Procedimiento legado probable: ninguno único; el más cercano es `spSeleccionaTransaccionCompletaLabores` (@where, @empresa) sobre `vSeleccionaTransaccionCompletaLabores` (SQL dinámico, solo encabezados). Se consulta directamente sobre las tablas base de `vTransaccionAgronomico`.
- Tablas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aGrupoNovedad`, `nConcepto`, `cTercero`, `aFinca`, `aLotes`, `cCentrosCosto`, `aTransaccionBascula`, `nFuncionario` (contratista).
- Lógica clave:
  - `empresa = @empresa`, rango sobre la fecha de la transacción (`aTransaccion.fecha`).
  - Estado: aprobadas (`anulado = 0`, por defecto), anuladas (`anulado = 1`) o todas.
  - Excluir `aTransaccionEliminada` (si existe).
  - Sin SQL dinámico: filtros por parámetros enlazados.
  - Cantidad y valor con signo según `aTransaccionNovedad.signo`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes. Fecha del documento digitado, no de ejecución de la labor |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Rango máximo de 1 año |
| Estado | select (Aprobadas, Anuladas, Todas) | Sí | Por defecto Aprobadas |
| Tipo de transacción | select (gTipoTransaccion con labores) | No | Todos por defecto |
| Número | texto | No | Coincidencia parcial del consecutivo sin prefijo ni ceros a la izquierda, o del número completo; los comodines se escapan |
| Finca | select2 (aFinca) | No | Todas por defecto |
| Lote | select2 dependiente de finca | No | Valida que pertenezca a la finca |
| Labor | select2 (aNovedad) | No | Todas por defecto |
| Trabajador | select2 (trabajadores con labores, incluidas anuladas) | No | Filtro exacto; cédula – nombre |

## Resultado (tabla)
Una fila por trabajador por línea de labor, agrupada por documento (`TLA-1520`). Orden: fecha de la transacción, tipo, número, línea y trabajador.

| Columna | Descripción |
|---|---|
| Transacción / Tipo / Número | Documento |
| Fecha transacción | `aTransaccion.fecha` |
| Finca | `aFinca.descripcion` |
| Grupo labor | código – nombre (`aGrupoNovedad`) |
| Concepto nómina | código – nombre (`nConcepto` por `aNovedad.concepto`) |
| Contratista | nombre del proveedor de `nFuncionario` cuando `contratista = 1`; vacío si es propio |
| Racimos línea | `aTransaccionNovedad.racimos` (solo TLC); se repite por trabajador y no se totaliza |
| Tiquete | tiquete de báscula de la TLC |
| Estado | Aprobado / Anulado |
| Usuario registro | `aTransaccion.usuarioRegistro` |
| Fecha labor | `fechaNovedad` o fecha de la línea |
| Lote | código – nombre |
| Labor | código – nombre |
| Trabajador | código – nombre (cruce con `cTercero` por `nit` y, si no, por `codigo`) |
| C. costo | código – nombre; centro de la línea y, si está vacío, el del lote |
| U. medida | `aNovedad.uMedida` |
| Cantidad | con signo |
| Jornales | `aTransaccionTercero.jornales` |
| Precio / Valor | `precioLabor` / `valorTotal` con signo |

Totales de pie: jornales y valor; cantidad si hay una sola unidad (si cada documento tiene una sola unidad, subtotal por documento).

## Indicadores (KPIs)
- Valor total (ajustes negativos y, si el estado es Todas, valor de anulados).
- Documentos (desglose por tipo).
- Líneas (trabajadores y promedio de líneas por documento).
- Anulados: documentos anulados del rango con los demás filtros, siempre, aunque el estado los excluya.

## Gráfica
- Tipo: sin gráfica.
- Por qué: es un listado de auditoría; los análisis agregados están en los demás informes del módulo.

## Supuestos resueltos
- "Labores detalle" es el listado línea a línea, no el detalle de un solo documento.
- Volumen: rango máximo de 1 año y límite de 20.000 líneas; si se supera se avisa y el último documento puede quedar incompleto.
- Las anuladas se controlan con el filtro Estado (por defecto se excluyen); con Todas se avisa que los totales las incluyen.
- Se omite el cargo: `nContratos` no cruza con las líneas de labor.
- Se omite la sección: no hay datos en `aTransaccionTercero`, `aTransaccionNovedad` ni `aLotes`, y `aSecciones` está vacía.
- Los racimos son de la línea y se repiten en cada trabajador, por eso no se totalizan.
- Centro de costo: el de la línea y, si está vacío, el del lote.
