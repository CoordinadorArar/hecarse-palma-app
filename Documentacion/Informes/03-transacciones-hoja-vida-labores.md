# Hoja de vida de Labores

**Módulo:** Transacciones · **Clave:** `hoja-vida-labores` · **Estado:** Planificado

## Propósito
Historial anual de cada grupo de labor: por mes, cuántos trabajadores participaron, qué cantidad se ejecutó y a qué costo. Responde cómo se comportan las labores a lo largo del año, para agronomía y planeación.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaHojadeVidaFincaFecha` (@año, @empresa).
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aGrupoNovedad`, `cTercero`.
- Lógica clave:
  - `YEAR(aTransaccion.fecha) = @año`, `anulado = 0`; agregar `empresa = @empresa` (el legado no lo filtra) y excluir `aTransaccionEliminada`.
  - Agrupa por mes y grupo de labor (`aGrupoNovedad`): `COUNT(DISTINCT tercero)` trabajadores, `SUM(cantidad)`, `SUM(precioLabor * cantidad)` valor.
  - Opción de bajar a labor (`aNovedad`).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | select | Sí | Año actual por defecto |
| Finca | select | No | |
| Nivel | select (grupo de labor, labor) | No | Grupo por defecto |
| Grupo de labor | select | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Mes | Nombre del mes |
| Grupo / Labor | Código – nombre |
| U. medida | `uMedida` |
| Trabajadores | Distintos |
| Cantidad | Suma |
| Valor | Suma |

Totales de pie: Valor; subtotal por mes.

## Indicadores (KPIs)
- Valor total del año.
- Mes de mayor costo.
- Trabajadores distintos en el año.
- Grupo de labor de mayor costo.

## Gráfica
- Tipo: barras apiladas.
- Eje X: mes (12); eje Y: valor; series: grupo de labor (máx. 6 + "Otros").
- Por qué: evolución mensual con la composición del costo por tipo de labor.

## Supuestos a validar
- El legado cuenta trabajadores sumando filas agrupadas por fecha (no distintos); se propone `COUNT(DISTINCT)`.
- El legado no filtra empresa; se asume que debe filtrarse.
- Nombre del legado ("HojadeVidaFinca") sugiere filtro por finca; se añade como opcional.
