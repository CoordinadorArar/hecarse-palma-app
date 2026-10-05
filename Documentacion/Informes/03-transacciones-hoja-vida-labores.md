# Hoja de vida de Labores

**Módulo:** Transacciones · **Clave:** `hoja-vida-labores` · **Estado:** Implementado

## Propósito
Historial anual de cada grupo de labor: por mes, cuántos trabajadores participaron, qué cantidad se ejecutó y a qué costo. Responde cómo se comportan las labores a lo largo del año, para agronomía y planeación.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaHojadeVidaFincaFecha` (@año, @empresa).
- Tablas: `aTransaccionTercero`, `aTransaccionNovedad`, `aTransaccion`, `aNovedad`, `aGrupoNovedad`.
- Lógica clave (misma base que "Resumen contratistas por periodo"):
  - `anulado = 0`, `empresa = @empresa` (el legado no lo filtraba), `CONVERT(date, aTransaccion.fecha)` dentro del año elegido. Se excluye `aTransaccionEliminada` si existe.
  - Valor = Σ `valorTotal`; cantidad = Σ `cantidad`; las novedades con `signo = 2` restan en ambos.
  - Trabajadores = `COUNT(DISTINCT tercero)` por mes y por año (no se suman entre meses).
  - Grupo de labor: `aNovedad.grupo` + `aGrupoNovedad` ("Código — Nombre"; "Sin grupo" si no existe). Labor: `aTransaccionTercero.novedad` + `aNovedad` ("Código — Nombre"); la labor que no existe en el maestro se conserva en "Sin grupo".
  - Finca: `aTransaccionTercero.finca`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | anio (años con labores no anuladas, desc.) | Sí | Año actual por defecto si existe; si no, el más reciente. Por fecha de la transacción |
| Finca | select2 (aFinca) | No | Todas por defecto |
| Nivel | select (Grupo de labor, Labor) | Sí | Grupo de labor por defecto |
| Métrica | select (Valor, Trabajadores, Cantidad) | Sí | Valor por defecto |
| Grupo de labor | select2 (aGrupoNovedad) | No | Todos por defecto |

Validación: la métrica Cantidad solo se permite con nivel Labor ("La cantidad solo se puede ver por labor: los grupos de labor mezclan unidades.").

## Resultado (tabla)
Matriz de 12 meses con la métrica elegida en las celdas.
- Nivel grupo: una fila por grupo de labor, por valor anual desc. y "Sin grupo" al final; sin agrupación.
- Nivel labor: filas agrupadas por grupo de labor (grupos por valor desc., "Sin grupo" al final); dentro de cada grupo, labores por valor desc.

| Columna | Descripción |
|---|---|
| Grupo de labor / Labor | Código — nombre |
| Grupo de labor | Solo nivel labor (agrupación) |
| U. medida | Solo nivel labor |
| Ene … Dic | Valor, trabajadores distintos del mes o cantidad. Vacío si es 0; en el año en curso los meses no transcurridos se marcan y quedan vacíos |
| Total / Año (distintos) | Valor o cantidad del año; con Trabajadores, distintos en el año |
| % del total | Solo con Valor: participación sobre el valor del año |
| Trabajadores | Con Valor o Cantidad: distintos en el año (no se suman) |

Totales de pie: solo con la métrica Valor (meses transcurridos, total y %).
Datos auxiliares por fila: total negativo, meses con neto negativo (lista "1,5"), número de labores y unidades del grupo (nivel grupo), sin grupo, % del grupo sobre el año, labores, trabajadores, meses con valor y unidades del grupo.

## Indicadores (KPIs)
- Valor total del año (meses con labor y finca).
- Mes de mayor costo (valor y % del año).
- Trabajadores distintos en el año (promedio de distintos por mes con labor).
- Grupo de mayor costo; con filtro de grupo, Labor de mayor costo (valor y % del año).

## Gráfica
- Barras apiladas verticales, siempre en valor: 12 meses; series = 6 grupos de mayor valor + "Otros" (con filtro de grupo, las labores del grupo). Meses en 0 o no transcurridos sin dato.
- Detalle por mes: valor, trabajadores distintos, labores y % del año.
- Sin datos no se muestra gráfica.

## Avisos
- Sin datos: "No hay labores registradas en {año} con los filtros seleccionados."
- Año en curso: fecha máxima con datos y meses siguientes atenuados.
- Labores sin grupo válido agrupadas en "Sin grupo".

## Supuestos resueltos
- `valorTotal` ≈ `precioLabor × cantidad` (solo diferencias de redondeo); se usa `valorTotal`, como en el resto de informes de labores.
- Los grupos de labor mezclan unidades (HA, KG, JOR, PAL, UND); la cantidad solo se muestra por labor.
- Trabajadores = `COUNT(DISTINCT tercero)`; el legado sumaba filas agrupadas por fecha.
- Se filtra por empresa (el legado no lo hacía) y se añade finca opcional (`aTransaccionTercero.finca`).
- Las líneas cuya labor no existe en `aNovedad` (p. ej. `1000` en 2024–2025) se incluyen en "Sin grupo" para no perder costo.
- Verificado contra SQL independiente: 2025 $ 889.514.133 (90 trabajadores distintos; no hay líneas con `signo = 2`); 2026 $ 639.143.563 (59 trabajadores, datos hasta 21/08/2026). Cuadran valor por mes y grupo, trabajadores distintos por mes y año, y cantidad por labor, también con filtros de finca y grupo.
