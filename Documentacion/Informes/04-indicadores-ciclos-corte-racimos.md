# Ciclos de corte de lotes por racimos

**Módulo:** Indicadores · **Clave:** `ciclos-corte-racimos` · **Estado:** Implementado

## Propósito
Muestra, día a día dentro de un mes, en qué lotes hubo cosecha y cuántos racimos se cortaron. Así se ve la rotación (ciclo de corte) de cada lote y se detectan lotes con intervalos demasiado largos entre pases. Es para supervisores de cosecha y jefes de finca.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaCicloCorteAñoMes` (@empresa, @año, @mes). Se reemplaza por consulta directa a tablas base.
- Tablas: `aTransaccionNovedad`, `aTransaccion`, `aTransaccionTercero`, `aNovedad`, `aLotes`, `aFinca`, `aVariedad`.
- Lógica clave (misma base que `produccion-lote-fecha`):
  - Líneas de `aTransaccionNovedad` con al menos un trabajador en labor `aNovedad.claseLabor = 2` y `uMedida = 'KG'`; `anulado = 0`; se excluye `aTransaccionEliminada` si existe.
  - Racimos = `aTransaccionNovedad.racimos` una vez por línea (signo 2 resta), por lote y día de la fecha de cosecha `CONVERT(date, ISNULL(MIN(fechaNovedad), fecha de la línea))`.
  - Matriz lote × día armada en PHP (sin bucles en SQL). Solo lotes activos (`aLotes.activo = 1`), ordenados por finca, `numero`, `añoSiembra`, `letra`.
  - Una segunda consulta busca, por lote, el último día con racimos > 0 antes del mes (sin límite de antigüedad).
  - Pases = grupos de días consecutivos con racimos > 0 (un pase se fecha por su último día). Intervalos = días entre pases consecutivos; el primero se cuenta desde el último corte anterior al mes. Int. prom. = promedio; Int. máx. = mayor.
  - Días sin corte = desde el último corte (del mes o anterior) hasta el fin de mes, o hasta hoy si es el mes en curso.
  - Ciclo largo: Int. máx. > umbral o Días sin corte > umbral.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | anio (años con TLC) | Sí | Año actual por defecto |
| Mes | mes | Sí | Mes actual si el año es el actual; si no, diciembre. No se admite un mes futuro |
| Finca | select2 (aFinca) | No | |
| Umbral ciclo largo (días) | select 10/12/15/18/21/30 | Sí | 15 por defecto |
| Solo lotes con corte | select Sí/No | Sí | No por defecto |

## Resultado (tabla)
Agrupada por finca; una fila por lote activo (con "Solo lotes con corte" = Sí, solo los que tuvieron pases en el mes).

| Clave | Columna | Descripción |
|---|---|---|
| `finca` | Finca | código — nombre (agrupación) |
| `lote` | Lote | código — nombre |
| `anioSiembra`, `variedad` | Año siembra, Variedad | Para el subtexto |
| `d1` … `dN` | 1 … 28–31 | Racimos del día; null sin corte; días futuros `futuro: true`, sin total |
| `racimos` | Total racimos | Suma del mes |
| `pases` | Pases | Días consecutivos con corte cuentan como un pase |
| `intProm` | Int. prom. | Decimal 2; total ponderado `sumaIntervalos / nIntervalos` |
| `intMax` | Int. máx. | Entero |
| `diasSinCorte` | Días sin corte | Entero |

Auxiliares: `ultimoCorte`, `corteAnterior`, `sumaIntervalos`, `nIntervalos`, `cicloLargo`, `umbral`, `lotesFinca`, `cosechadosFinca`, `racimosFinca`, `intPromFinca`, `largosFinca`, `fincaCicloLargo`.

Totales: racimos por día transcurrido, total racimos e intervalo promedio ponderado.

## Indicadores (KPIs)
- Racimos del mes (días con corte).
- Lotes cosechados / lotes activos ("N / M", % de activos).
- Intervalo promedio entre pases (lotes con 2 o más pases, contando el último corte del mes anterior).
- Lotes en ciclo largo (Int. máx. o días sin corte > umbral).

## Gráfica
- `graficas[0]`: mapa de calor. Filas: lotes (mismo orden de la tabla). Columnas: días '1'…'N'. Valor: racimos; centinelas -1 = sin corte, -2 = día no transcurrido. `detalle[i].notas[j]` = 'N días desde el corte anterior', 'Mismo pase del día anterior' o 'Sin corte anterior registrado' en los días con corte.
- Por qué: los datos son una matriz lote × tiempo, y el mapa de calor deja ver la rotación y los huecos entre pases.

## Supuestos resueltos
- Se usa la fecha de cosecha (fecha de novedad) y no `aTransaccion.fecha`. El total del mes cuadra con `produccion-lote-fecha` (junio 2025: 45.448 racimos; agosto 2026: 20.361).
- Racimos se toman una vez por línea (`aTransaccionNovedad.racimos`); `aTransaccionTercero.racimos` repite el valor por trabajador.
- Solo lotes activos; en junio 2025 y agosto 2026 todo el corte está en lotes activos.
- Días consecutivos con corte (diferencia de 1 día, también desde el último día del mes anterior) forman UN mismo pase. Cada pase se fecha por su último día: los intervalos van del último día de un pase al último día del siguiente y los días sin corte cuentan desde el último día de corte. Las celdas diarias siguen mostrando los racimos de cada día. Un pase que viene del mes anterior y continúa el día 1 cuenta como pase del mes, sin intervalo. Ej.: HTL8-03 jun-2025 (corte anterior 24/05; 7/06; 24 y 25/06) = 2 pases, intervalos 14 y 18, prom. 16, máx. 18, 5 días sin corte.
- El umbral de ciclo largo no existe en el sistema: es un filtro (15 días por defecto).
- Un lote sin ningún corte registrado no tiene intervalos ni días sin corte y no cuenta como ciclo largo.
