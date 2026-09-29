# Ciclos de corte de lotes por racimos

**Módulo:** Indicadores · **Clave:** `ciclos-corte-racimos` · **Estado:** Planificado

## Propósito
Muestra, día a día dentro de un mes, en qué lotes hubo cosecha y cuántos racimos se cortaron. Así se ve la rotación (ciclo de corte) de cada lote y se detectan lotes con intervalos demasiado largos entre pases. Es para supervisores de cosecha y jefes de finca.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaCicloCorteAñoMes` (@empresa, @año, @mes).
- Tablas/vistas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aLotes`.
- Lógica clave:
  - `aTransaccion.tipo = 'TLC'`, `anulado = 0`, `aNovedad.claseLabor = 2`.
  - Racimos = `SUM(aTransaccionNovedad.racimos)` por lote y por día de `aTransaccionTercero.fechaNovedad` (join por `registroNovedad`).
  - El legado recorre los días del mes con un WHILE y rellena con 0 los días sin corte. La versión nueva genera una matriz lote × día: los días sin dato quedan en 0 en PHP o con una tabla calendario, sin bucles en SQL.
  - Orden por `aLotes.numero`, `añoSiembra`, `aLotes.letra`.
  - Métricas derivadas por lote: N.º de pases (días con racimos > 0), intervalo promedio y máximo entre pases (días) y días desde el último corte hasta el fin de mes.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select | Sí | |
| Mes | Select 1–12 | Sí | |
| Finca | Select (aFinca) | No | |
| Solo lotes con corte | Check | No | El legado incluye todos los lotes |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Lote | Código |
| Año siembra | |
| Día 1 … Día 28–31 | Racimos cortados ese día (vacío o 0 si no hubo) |
| Total racimos | Suma del mes |
| N.º pases | Días con corte |
| Intervalo prom. (días) | Promedio de días entre pases |
| Intervalo máx. (días) | |

Totales de pie: racimos por día y total del mes.

## Indicadores (KPIs)
- Total de racimos del mes.
- Lotes cosechados / lotes activos.
- Intervalo promedio entre pases (días).
- Lotes con intervalo máximo mayor a un umbral (p. ej. 15 días).

## Gráfica
- Tipo: mapa de calor.
- Filas: lotes. Columnas: días del mes. Intensidad: racimos.
- Por qué: los datos son una matriz lote × tiempo, y el mapa de calor deja ver la rotación y los huecos entre pases.

## Supuestos a validar
- Se usa la fecha de la labor (`aTransaccionTercero.fechaNovedad`) y no `aTransaccion.fecha`.
- El umbral de "ciclo largo" (días) no está definido en el sistema. Hay que confirmarlo con operaciones o dejarlo parametrizable.
- El legado incluye también los lotes inactivos (no filtra `aLotes.activo`). Se propone filtrar solo los activos.
