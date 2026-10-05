# Resumen contratistas por periodo

**Módulo:** Transacciones · **Clave:** `resumen-contratistas-periodo` · **Estado:** Implementado

## Propósito
Resumen de alto nivel del valor ejecutado por contratistas por mes y grupo de labor, comparado con el personal propio. Responde cuánto del costo de campo se hace por contratistas y en qué labores. Para gerencia.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaResumenLaboresTerceroFecha` (@fechaI, @fechaF, @empresa).
- Tablas: `aTransaccionTercero`, `aTransaccionNovedad`, `aTransaccion`, `aNovedad`, `aGrupoNovedad`, `nFuncionario` (contratista, proveedor), `cTercero` (proveedor).
- Lógica clave (misma base que "Liquidación contratistas por periodo"):
  - `anulado = 0`, `empresa = @empresa`, `CONVERT(date, aTransaccion.fecha) BETWEEN fi AND ff`. Se excluye `aTransaccionEliminada` si existe.
  - Valor = Σ `valorTotal`; las novedades con `signo = 2` restan.
  - Contratista: el tercero existe en `nFuncionario` con `contratista = 1` (`codigo = tercero`). Proveedor por `nFuncionario.proveedor` (NIT o id de `cTercero`); si no resuelve, "Proveedor no asociado". Todo lo demás es personal propio.
  - Grupo de labor: `aNovedad.grupo` + `aGrupoNovedad` ("Código — Nombre"; "Sin grupo" si no existe).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, 1 de enero del año actual. Dentro de un mismo año |
| Fecha final | fecha | Sí | Por defecto, último día del mes actual. Dentro de un mismo año |
| Agrupar por | select (Grupo de labor, Contratista) | Sí | Grupo de labor por defecto |
| Incluir personal propio | select (Sí, No) | Sí | Sí por defecto |

## Resultado (tabla)
Con personal propio, filas agrupadas por tipo: primero "Contratistas" y después "Personal propio". Sin personal propio, solo filas de contratistas, sin agrupación.
- Agrupar por grupo de labor: en cada tipo, los grupos de labor con valor, por total desc.
- Agrupar por contratista: proveedores ("NIT-DV — Razón social") por total desc y "Proveedor no asociado" al final; el personal propio en una sola fila.

| Columna | Descripción |
|---|---|
| Grupo de labor / Contratista | Según agrupación |
| Tipo | Contratistas / Personal propio |
| Ene … Dic | Valor por mes (solo los meses del rango; los meses no transcurridos se marcan y no totalizan) |
| Total | Suma |
| % del total | Participación sobre el total mostrado |

Totales de pie: por mes, total general y % del total.
Datos auxiliares por fila: % contratistas dentro del grupo de labor, trabajadores, sin grupo, % del tipo sobre el total de labores, contratistas, trabajadores y meses con valor del tipo, y si el bloque de contratistas tiene trabajadores sin proveedor.

## Indicadores (KPIs)
- Valor contratistas (meses y rango).
- % contratistas sobre el total de labores (propios + contratistas, aunque no se incluya el personal propio en la tabla).
- Contratistas activos (proveedores) y trabajadores contratistas.
- Grupo de labor con mayor valor por contratistas (valor y % del valor contratistas).

## Gráfica
- Combinada: columnas con el valor de contratistas por mes (eje izquierdo) y línea con el % de contratistas sobre el total de labores del mes (eje derecho). Detalle por mes: valor propio, total de labores y contratistas activos.
- Sin valor de contratistas en el rango no se muestra gráfica.

## Supuestos resueltos
- `aTransaccionTercero.ejecutado` nunca vale 1 (ni en propios ni en contratistas); el legado exigía `ejecutado = 1` a los propios y daría 0, por lo que no se filtra por `ejecutado`.
- Se usa una sola fecha, la de la transacción, para ambos tipos (el legado usaba la fecha de labor para propios); la fecha de labor cae en otro mes solo en 153 de ~97.000 líneas.
- Se compara el valor de personal propio completo, sin separar por desarrollo del lote.
- Verificado: 2020 contratistas $ 19.033.230 y propios $ 319.850.500; 2023 contratistas $ 16.156.265 y propios $ 436.550.019.
