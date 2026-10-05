# Labores por fecha

**Módulo:** Transacciones · **Clave:** `labores-fecha` · **Estado:** Implementado

## Propósito
Muestra todas las labores registradas (TLA, TLC, RLF...) en un rango de fechas, con trabajador, lote, cantidad y valor. Sirve a supervisión de campo y nómina para revisar lo digitado en el periodo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaInformeGeneralLaboresAgronomicos` (@fechaInicial, @fechaFinal, @empresa).
- Tablas/vistas: `vSeleccionaTransaccionesAgronomico` (aTransaccion + aTransaccionNovedad + aTransaccionTercero + aNovedad + cTercero + aFinca + aLotes + aTransaccionBascula), `nFuncionario` + `cxpProveedor` para el nombre del contratista.
- Lógica clave:
  - Filtro `fechaTransaccion BETWEEN fi AND ff`, `anulado = 0`, `codEmpresa = @empresa`.
  - Una fila por trabajador por línea de labor (`aTransaccionTercero`); `cantidadLabor` con signo según `aTransaccionNovedad.signo`.
  - `valorTotalTercero` = valor pagado al trabajador; contratista por LEFT JOIN a `nFuncionario.contratista = 1` → `cxpProveedor.descripcion`.
  - Excluir transacciones presentes en `aTransaccionEliminada`.


## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes actual. Fecha del documento digitado |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Máximo 1 año de rango |
| Tipo de transacción | select (gTipoTransaccion con labores) | No | Todos por defecto |
| Finca | select (aFinca) | No | Todas por defecto |
| Grupo de labor | select (aGrupoNovedad) | No | Todos por defecto |

## Resultado (tabla)
Detalle agrupado por documento (transacción). Orden: fecha del documento, tipo, número, línea y trabajador.

| Columna | Descripción |
|---|---|
| Transacción | tipo-número (`TLC-4191`) |
| Fecha labor | `aTransaccionTercero.fechaNovedad` o `aTransaccionNovedad.fecha` |
| Finca | `aFinca.descripcion` |
| Lote | código – nombre |
| Labor | código – nombre |
| Trabajador | código – nombre (cruce con `cTercero` por `nit` y, si no, por `codigo`) |
| Contratista | nombre del proveedor de `nFuncionario` cuando `contratista = 1`; vacío si es propio |
| U. medida | `aNovedad.uMedida` |
| Cantidad | `aTransaccionTercero.cantidad` con signo |
| Jornales | `aTransaccionTercero.jornales` |
| Precio | `aTransaccionTercero.precioLabor` |
| Valor | `aTransaccionTercero.valorTotal` con signo |

Totales de pie: jornales y valor; cantidad si hay una sola unidad (si cada documento tiene una sola unidad, subtotal por documento).

## Indicadores (KPIs)
- Valor total (ajustes negativos).
- Transacciones (documentos distintos, desglose por tipo).
- Jornales (promedio por trabajador) o Registros si no hay jornales.
- Trabajadores (días con documentos y líneas sin lote requerido).

## Gráfica
- Tipo: barras verticales.
- Eje X: fecha del documento por día; por semana (lunes) si el rango supera 45 días.
- Serie: valor digitado. Detalle: rango (semana), documentos, trabajadores, registros y jornales (si hay).

## Supuestos resueltos
- El rango se filtra por la fecha de la transacción (`aTransaccion.fecha`), como el legado; la columna Fecha labor muestra la fecha de ejecución.
- Se consulta sobre las tablas base. Se excluyen anuladas y, si existe, `aTransaccionEliminada`.
- Es un detalle agrupado por documento, no un resumen por día.
- La gráfica no usa eje secundario: se grafica el valor y los jornales van en el detalle.
- Límite de 20.000 registros; si se supera se avisa y el último documento puede quedar incompleto.
