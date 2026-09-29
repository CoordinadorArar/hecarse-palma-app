# Sanidad Detalle

**Módulo:** Sanidad · **Clave:** `sanidad-detalle` · **Estado:** Planificado

## Propósito
Lista en detalle las labores sanitarias ejecutadas en un rango de fechas: control de Strategus, evaluación de enfermedades, tratamiento de palmas enfermas o jóvenes, censo de plagas. Muestra el lote, la labor, la fecha, la cantidad, el trabajador y el valor. Sirve al área de sanidad para seguir la intervención por lote y su costo.

## Fuente de datos
- Procedimiento legado probable: `spInformeSanidadDetallado` (vista `vTransaccionesSanidad`, sobre `aSanidad`/`aSanidadDetalle`). Hoy no sirve como fuente principal porque esas tablas tienen solo 2 filas (verificado en la BD).
- **Fuente principal:** las labores del grupo `'03'` (SANIDAD) registradas en transacciones TLA.
  - Tablas: `aTransaccion`, `aTransaccionNovedad`, `aTransaccionTercero`, `aNovedad`, `aLotes`, `aFinca`, `aSecciones`, `cTercero`.
  - Labores con más volumen desde 2025: 0309 Control de Strategus (462 líneas), 0301 Evaluación de enfermedades (432), 0304 Tratamiento palmas enfermas (412), 656 Tratamiento palma joven (270), 0303 Censo de plagas (265).
- **Fuente secundaria:** `aSanidad` + `aSanidadDetalle` + `aGrupoCaracteristica` + `aCaracteristica`. Solo se muestra si llega a tener registros, en una pestaña o sección aparte "Registros sanitarios (censo por palma)".
- Lógica clave (fuente principal):
  - `aTransaccion.tipo = 'TLA'`, `anulado = 0`. Se excluyen las transacciones que estén en `aTransaccionEliminada`.
  - `aNovedad.grupo = '03'` (con `LTRIM/RTRIM`, igual que en `FertilizacionTransaccionModel`).
  - Join `aTransaccionNovedad` → `aTransaccionTercero` por empresa, tipo, numero y `registroNovedad = registro`.
  - Fecha = `aTransaccionTercero.fechaNovedad` (fecha de la labor), filtrada entre @fi y @ff.
  - Cantidad = `aTransaccionTercero.cantidad`, jornales = `jornales`, valor = `valorTotal`, precio = `precioLabor`.
  - Trabajador: `cTercero` por `nit = aTransaccionTercero.tercero`. Lote: `aLotes` por `aTransaccionNovedad.lote`.
  - Derivadas: costo por hectárea = valor / `aLotes.hNetas`; costo por palma = valor / `palmasProduccion`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | Fecha | Sí | Fecha de la labor |
| Fecha final | Fecha | Sí | |
| Finca | Select (aFinca) | No | |
| Lote | Select (aLotes) | No | Depende de la finca |
| Labor sanitaria | Select (aNovedad grupo 03) | No | |
| Trabajador | Autocompletar (cTercero) | No | |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha labor | `aTransaccionTercero.fechaNovedad` |
| Número TLA | Documento |
| Finca | |
| Sección | |
| Lote | Código y nombre |
| Año siembra | |
| Labor | Código y descripción (grupo 03) |
| U. medida | `aNovedad.uMedida` |
| Trabajador | NIT y nombre |
| Cantidad | |
| Jornales | |
| Precio | |
| Valor | |

Totales de pie: jornales y valor. La cantidad se totaliza solo si se filtra una labor, para no sumar unidades distintas.

## Indicadores (KPIs)
- Valor total de las labores sanitarias.
- Lotes intervenidos (distintos).
- Jornales totales.
- Labor con más registros.

## Gráfica
- Tipo: barras horizontales apiladas.
- Eje Y: lote (top 15 por valor, de mayor a menor). Eje X: valor ($). Series: labor sanitaria (top 4, el resto en "Otras").
- Por qué: compara el costo sanitario entre muchos lotes y muestra qué labores lo componen.

## Supuestos a validar
- El grupo `'03'` agrupa todas las labores sanitarias. La labor 656 (Tratamiento palma joven) tiene un código que no sigue el patrón 03xx, así que hay que confirmar que su `grupo` sea '03'.
- Falta decidir si también hay labores sanitarias registradas en TLC u otros tipos. Por ahora solo se toma TLA.
- Grupo y característica solo existen en la fuente secundaria (`aSanidadDetalle`). En la principal no hay detalle por palma ni por característica.
- Se usa la fecha de la labor y no `aTransaccion.fecha`.
