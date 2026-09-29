# Labores fertilización detalle

**Módulo:** Fertilización · **Clave:** `labores-fertilizacion-detalle` · **Estado:** Planificado

## Propósito
Es el detalle línea a línea de cada registro de labores de fertilización (RLF): labor, lote, trabajador, insumo, dosis y bultos, y el plan (PFA) al que se aplica. Sirve para auditar y conciliar lo aplicado contra el plan y contra la nómina.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresFertilizacionInforme` (@empresa, @fechaInicial, @fechaFinal).
- Tablas/vistas: `aTransaccion` (RLF y su PFA de referencia), `aTransaccionNovedad`, `aTransaccionTercero`, `aTransaccionItem`, `iItems`, `aNovedad`, `cTercero`.
- Lógica clave:
  - `aTransaccion.tipo = 'RLF'`, `anulado = 0`, `fecha` entre @fi y @ff. El legado no filtra por tipo y se apoya en el join con aTransaccionItem; aquí se explicita RLF.
  - Plan de referencia: `LEFT JOIN aTransaccion p ON p.numero = rlf.referencia AND p.empresa = rlf.empresa AND p.tipo = 'PFA'` (número, fecha y fechaFinal).
  - Labor y trabajador: `aTransaccionNovedad` → `aTransaccionTercero` (por `registroNovedad`). Cantidad, jornales y valorTotal de la labor.
  - Insumo: `aTransaccionItem` tipo RLF con `registror` = registro de la línea y el mismo lote. Guarda item, cantidad, uMedida, dosis, pBulto y mBulto.
  - Trabajador: `cTercero` unido por `nit` (el legado une por `codigo`).
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | Fecha | Sí | |
| Fecha final | Fecha | Sí | |
| Plan (PFA) | Select | No | Filtra por `referencia` |
| Finca | Select (aFinca) | No | |
| Lote | Select (aLotes) | No | |
| Insumo | Select (iItems) | No | |
| Trabajador | Autocompletar | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Número RLF | |
| Fecha | |
| Plan referencia | Número PFA y periodo |
| Labor | Código y descripción |
| Lote | |
| Trabajador | |
| Cantidad labor | Con su unidad de medida |
| Jornales | |
| Valor labor | |
| Insumo | Código y descripción |
| Cantidad insumo | Con su unidad de medida |
| Dosis | Por palma |
| Peso bulto | `pBulto` |
| Bultos | `mBulto` o cantidad / pBulto |

Totales de pie: jornales, valor de labor y cantidad de insumo (agrupada por insumo).

## Indicadores (KPIs)
- Registros RLF en el rango.
- Valor total de las labores.
- Bultos aplicados.
- Registros sin plan de referencia.

## Gráfica
- Tipo: barras apiladas.
- Eje X: mes (o semana si el rango es menor a 2 meses). Eje Y: cantidad aplicada. Series: insumo (top 4, el resto en "Otros").
- Por qué: muestra cómo evoluciona en el tiempo lo aplicado y qué insumos lo componen.

## Supuestos a validar
- `mBulto` guarda el número de bultos. Si no es así, se calcula como cantidad / pBulto.
- En el legado el join con `aTransaccionNovedad` tiene condiciones redundantes y es posible que duplique filas. Aquí se usa `registroNovedad = registro`.
- Si en una misma línea de labor puede haber varios trabajadores e insumos a la vez, la tabla repetiría cada insumo por cada trabajador. En ese caso se evalúa separar la tabla de mano de obra de la de insumos.
