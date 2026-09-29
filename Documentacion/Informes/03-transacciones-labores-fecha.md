# Labores por fecha

**Módulo:** Transacciones · **Clave:** `labores-fecha` · **Estado:** Planificado

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
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes actual |
| Fecha final | fecha | Sí | Máximo 1 año de rango |
| Finca | select (aFinca) | No | Todas por defecto |
| Tipo de transacción | select (TLA, TLC, RLF) | No | Todas por defecto |
| Grupo de labor | select (aGrupoNovedad) | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha | `fechaTransaccion` |
| Tipo / Número | `codtransaccion` / `numeroTransaccion` |
| Finca | `nombreFinca` |
| Lote | `codLote` – `nombreLote` |
| Labor | `codLabor` – `nombreLabor` |
| Trabajador | `codTercero` – `nombreTercero` |
| Contratista | `contratista` (vacío si es propio) |
| Cantidad | `cantidadTercero` |
| U. medida | `uMedida` |
| Jornales | `jornalTercero` |
| Precio | `precioLabor` |
| Valor | `valorTotalTercero` |

Totales de pie: Jornales y Valor; Cantidad solo si el resultado tiene una única unidad de medida.

## Indicadores (KPIs)
- Valor total de labores.
- Jornales totales.
- Número de transacciones (documentos distintos).
- Trabajadores distintos.

## Gráfica
- Tipo: combinada barras + línea.
- Eje X: fecha (día); barras: valor total por día (eje izquierdo); línea: jornales por día (eje secundario).
- Por qué: evolución diaria de dos magnitudes distintas (pesos y jornales).

## Supuestos a validar
- Si el rango se filtra por fecha de transacción (`aTransaccion.fecha`, como el legado) o por fecha de la labor (`aTransaccionNovedad.fecha`).
- La vista legada no filtra `aTransaccionEliminada`; se asume que debe excluirse.
- Si "Labores por fecha" debe ser detalle (como el legado) o un resumen por día; se propone detalle con gráfica diaria.
