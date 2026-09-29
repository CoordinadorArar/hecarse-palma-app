# Labores detalle

**Módulo:** Transacciones · **Clave:** `labores-detalle` · **Estado:** Planificado

## Propósito
Listado línea a línea de las transacciones de labores (documento, lote, labor, trabajador, cantidades, precio, valor, estado) con filtros amplios. Es la consulta de auditoría para revisar exactamente qué se digitó.

## Fuente de datos
- Procedimiento legado probable: ninguno único; el más cercano es `spSeleccionaTransaccionCompletaLabores` (@where, @empresa) sobre `vSeleccionaTransaccionCompletaLabores` (SQL dinámico, solo encabezados). Se propone consulta directa sobre `vTransaccionAgronomico`.
- Tablas/vistas: `vTransaccionAgronomico` (aTransaccion, aTransaccionNovedad, aTransaccionTercero, aNovedad, aGrupoNovedad, nConcepto, cTercero, aFinca, aLotes, nContratos, nCargo, cCentrosCosto, cTercero contratista, aVariedad).
- Lógica clave:
  - `codEmpresa = @empresa`, rango sobre `fechaTransaccion`.
  - Incluye anuladas solo si el usuario lo pide (`estado` Aprobado/Anulado).
  - Excluir `aTransaccionEliminada`.
  - Sin SQL dinámico: filtros por parámetros enlazados.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | |
| Fecha final | fecha | Sí | |
| Tipo transacción | select (TLA, TLC, RLF) | No | |
| Número | texto | No | |
| Finca | select | No | |
| Lote | select dependiente | No | |
| Labor | select (aNovedad) | No | |
| Trabajador | autocompletar | No | |
| Incluir anuladas | casilla | No | Por defecto no |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Tipo / Número | Documento |
| Fecha / Fecha labor | `fechaTransaccion` / `fechaLabor` |
| Finca / Sección / Lote | Ubicación |
| Grupo / Labor | `nombreGrupoLabor` / `nombreLabor` |
| Concepto nómina | `nombreConcepto` |
| Trabajador | `codTercero` – `nombreTercero` |
| Cargo / C. costo | `nombreCargo` / `nombreCCosto` |
| Contratista | `nombreContratista` |
| Cantidad / U. medida | `cantidadTercero` / `uMedida` |
| Jornales | `jornalTercero` |
| Racimos | `racimoLabor` |
| Precio / Valor | `precioLabor` / `valorTotalTercero` |
| Tiquete | `tiquete` |
| Estado | Aprobado / Anulado |
| Usuario registro | `usuarioRegistro` |

Totales de pie: Jornales, Racimos, Valor.

## Indicadores (KPIs)
- Líneas encontradas.
- Documentos distintos.
- Valor total.
- Documentos anulados (si se incluyen).

## Gráfica
- Tipo: sin gráfica.
- Por qué: es un listado de auditoría; los análisis agregados están en los demás informes del módulo.

## Supuestos a validar
- Que "Labores detalle" es el listado línea a línea y no el detalle de un solo documento (el legado usaba `spSeleccionaTransaccionCompletaLabores` para buscar documentos).
- Volumen: limitar rango (p. ej. 3 meses) o paginar en servidor.
