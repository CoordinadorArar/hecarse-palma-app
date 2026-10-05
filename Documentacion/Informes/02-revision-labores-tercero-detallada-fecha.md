# Labores tercero detallada por fecha

**Módulo:** Revisión Labores · **Clave:** `labores-tercero-detallada-fecha` · **Estado:** Implementado

## Propósito
Detalle registro a registro de las labores de uno o varios trabajadores (fecha, transacción, lote, labor, cantidad, precio, valor). Para revisar y soportar reclamos antes de liquidar nómina.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresTerceroFecha` (devuelve `SELECT *` de `vTransaccionAgronomico`, sin agregar). Variante de liquidación: `spSeleccionaLaboresTerceroFechaLiquidacion`.
- Tablas/vistas: `aTransaccionTercero` + `aTransaccionNovedad` + `aTransaccion` + `aNovedad` + `aFinca` + `aLotes` + `aTransaccionBascula` + `cTercero` (misma lógica que `vTransaccionAgronomico`).
- Lógica clave:
  - Sin agrupación; orden por nombre del trabajador, tercero, fecha de la labor, número de transacción y registro.
  - Cantidad y valor con signo (`signo = 2` → negativo).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial / final | Fecha | Sí | Default mes actual |
| Trabajador | Texto (código o nombre) | No | LIKE; recomendado en rangos largos |
| Finca | Select (aFinca) | No | `aTransaccionTercero.finca` |
| Labor | Select (aNovedad) | No | `aTransaccionTercero.novedad` |
| Tipo de transacción | Select (gTipoTransaccion) | No | Solo tipos con registros en `aTransaccionTercero` (TLA, TLC, RLF) |

## Resultado (tabla)
Agrupada por trabajador.

| Columna | Descripción |
|---|---|
| Trabajador | código – nombre |
| Fecha | fecha de la labor |
| Día | día de la semana (Lun…Dom) |
| Transacción | tipo-número (TLC-123) |
| Finca | `aFinca.descripcion` |
| Lote | código – nombre (`aTransaccionNovedad.lote` → `aLotes`) |
| Labor | código – nombre |
| U. medida | `aNovedad.uMedida` |
| Cantidad | `aTransaccionTercero.cantidad` |
| Jornales | `aTransaccionTercero.jornales` |
| Racimos línea | `aTransaccionNovedad.racimos` (solo TLC; no se totaliza) |
| Tiquete | primer tiquete de `aTransaccionBascula` de la transacción (solo TLC) |
| Precio | `aTransaccionTercero.precioLabor` |
| Valor | `aTransaccionTercero.valorTotal` |
| Periodo nómina | `aTransaccionTercero.periodo` |

Totales de pie: cantidad (si una sola unidad), jornales, valor.

## Indicadores (KPIs)
- Registros (trabajadores · transacciones).
- Jornales.
- Valor total (ajustes negativos).
- Días con labor (día de mayor valor; dd/mm, o dd/mm/aaaa si el rango abarca años distintos).

## Gráfica
- Tipo: barras verticales.
- Eje X: fecha de la labor (solo días con registros); barras: valor devengado diario. El detalle de cada barra muestra día de la semana, registros, trabajadores y jornales (si hay).

## Supuestos resueltos
- Se filtra por la fecha de la labor (`aTransaccionTercero.fechaNovedad`; si está vacía, `aTransaccionNovedad.fecha`), no por `fechaTransaccion`.
- Se consulta sobre las tablas base, no sobre `vTransaccionAgronomico`, para evitar que su `SELECT DISTINCT` colapse filas idénticas legítimas. Se excluyen anuladas y, si existe, `aTransaccionEliminada`.
- El trabajador se cruza con `cTercero` por `nit` y, si no, por `codigo`.
- Límite de 20.000 registros; si se supera se avisa y el último trabajador puede quedar incompleto.
- Los racimos son los de la línea de cosecha (`aTransaccionNovedad.racimos`), como en el legado, sin prorratear: si la línea tiene varios trabajadores, cada uno muestra los racimos de toda la línea, por eso la columna "Racimos línea" no se totaliza.
- El día de la semana se calcula en la aplicación (no depende del idioma del servidor).
