# Labores tercero detallada por fecha

**Módulo:** Revisión Labores · **Clave:** `labores-tercero-detallada-fecha` · **Estado:** Planificado

## Propósito
Detalle registro a registro de las labores de uno o varios trabajadores (fecha, transacción, lote, labor, cantidad, precio, valor). Para revisar y soportar reclamos antes de liquidar nómina.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresTerceroFecha` (devuelve `SELECT *` de `vTransaccionAgronomico`, sin agregar). Variante de liquidación: `spSeleccionaLaboresTerceroFechaLiquidacion`.
- Tablas/vistas: `vTransaccionAgronomico`.
- Lógica clave:
  - Mismo filtro que el legado: fechas sobre `fechaTransaccion`, `anulado = 0`, trabajador por código o nombre (LIKE).
  - Sin agrupación; orden por trabajador, `fechaLabor`, `numeroTransaccion`.
  - Cantidades y valor con signo (`signo = 2` → negativo) ya resueltos en la vista.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial / final | Fecha | Sí | Default mes actual |
| Trabajador | Texto (código o nombre) | No | Recomendado; sin él puede traer muchos registros |
| Finca | Select (aFinca) | No | |
| Labor | Select (aNovedad) | No | |
| Tipo de transacción | Select (TLA, TLC, RLF…) | No | `codtransaccion` |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Fecha labor | `fechaLabor` |
| Día | `diaSemana` |
| Transacción | `codtransaccion`-`numeroTransaccion` |
| Trabajador | `codTercero` – `nombreTercero` |
| Finca | `nombreFinca` |
| Lote | `codLote` – `nombreLote` |
| Labor | `codLabor` – `nombreLabor` |
| U. medida | `uMedida` |
| Cantidad | `cantidadTercero` |
| Jornales | `jornalTercero` |
| Racimos | `racimoLabor` (cosecha) |
| Tiquete | `tiquete` (si aplica) |
| Precio | `precioLabor` |
| Valor | `valorTotalTercero` |
| Periodo nómina | `periodo` |

Totales de pie: cantidad (si una sola unidad), jornales, valor.

## Indicadores (KPIs)
- Registros.
- Jornales.
- Valor total.
- Días con labor.

## Gráfica
- Tipo: barras verticales (combinada barras+línea si hay espacio).
- Eje X: fecha labor; barras: valor diario; línea (eje secundario): jornales diarios.
- Por qué: evolución diaria de dos magnitudes distintas en el rango consultado.

## Supuestos a validar
- Si el filtro de fecha debe ser `fechaLabor` en vez de `fechaTransaccion`.
- Límite de filas cuando no se filtra trabajador.
