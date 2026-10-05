# Labores detallada por fecha por novedad

**Módulo:** Revisión Labores · **Clave:** `labores-detallada-fecha-novedad` · **Estado:** Implementado

## Propósito
Detalle de lo ejecutado por labor (novedad) en un rango de fechas: dónde (finca/lote), cuándo, quién, cuánto y a qué costo. Para supervisores que revisan una labor específica.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresLoteFecha` (@fi, @ff, @empresa: agrupa por grupo de labor, lote, labor, fecha) sobre `vTransaccionAgronomico`; lista de labores del rango con `spSeleccionaLaboresLoteFechaMesConcepto`.
- Tablas/vistas: `aTransaccionTercero` + `aTransaccionNovedad` + `aTransaccion` + `aNovedad` + `aGrupoNovedad` + `aFinca` + `aLotes` + `cTercero` (misma lógica que `vTransaccionAgronomico`).
- Lógica clave:
  - Detalle por registro (trabajador), agrupado por labor; orden por grupo, labor, fecha, finca, lote, trabajador, número de transacción y registro.
  - Cantidad y valor con signo (`signo = 2` → negativo).
  - Rendimiento: cantidad / jornales frente a `aNovedad.tarea`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial / final | Fecha | Sí | Default mes actual; fecha de la labor |
| Grupo de labor | Select (aGrupoNovedad) | No | `aNovedad.grupo` |
| Labor (novedad) | Select dependiente | No | Labores con movimiento en el rango y del grupo elegido; debe pertenecer al grupo |
| Finca | Select (aFinca) | No | `aTransaccionTercero.finca` |
| Lote | Select dependiente de la finca | No | `aTransaccionNovedad.lote`; debe pertenecer a la finca |

## Resultado (tabla)
Agrupada por labor.

| Columna | Descripción |
|---|---|
| Labor | código – nombre |
| Grupo | código – nombre (`aGrupoNovedad`) |
| Fecha | fecha de la labor |
| Transacción | tipo-número (TLC-123) |
| Finca | `aFinca.descripcion` |
| Lote | código – nombre |
| Año siembra | `aLotes.añoSiembra` |
| Trabajador | código – nombre |
| Contratista | Sí/No (`aTransaccionTercero.contratista`) |
| U. medida | `aNovedad.uMedida` |
| Cantidad | `aTransaccionTercero.cantidad` |
| Jornales | `aTransaccionTercero.jornales` |
| Precio | `aTransaccionTercero.precioLabor` |
| Valor | `aTransaccionTercero.valorTotal` |

Totales de pie: cantidad (si una sola unidad; si no, solo subtotal por labor), jornales, valor.

## Indicadores (KPIs)
- Cantidad ejecutada (una sola unidad) o Labores ejecutadas (unidades mixtas).
- Valor total (ajustes negativos).
- Lotes intervenidos (fincas · registros sin lote).
- Rendimiento (cantidad/jornal) o Cantidad por trabajador-día si no hay jornales, con el esperado (`tarea`) cuando hay una sola labor; con unidades mixtas, Trabajadores (días con labor).

## Gráfica
- Tipo: barras verticales.
- Eje X: fecha de la labor por día; por semana (lunes) si el rango supera 45 días.
- Una sola unidad: cantidad ejecutada (detalle: valor, trabajadores, lotes, registros). Unidades mixtas: valor (detalle: labores, trabajadores, registros). En modo semana el detalle incluye el rango de la semana.

## Supuestos resueltos
- "Novedad" es la labor agronómica (`aNovedad`), no las novedades de nómina.
- Se filtra por la fecha de la labor (`aTransaccionTercero.fechaNovedad`; si está vacía, `aTransaccionNovedad.fecha`), no por `fechaTransaccion`.
- La cantidad se totaliza solo si el resultado tiene una sola unidad de medida; si mezcla unidades se subtotaliza por labor.
- La gráfica no usa eje secundario: se grafica una sola magnitud (cantidad o valor) y la otra va en el detalle.
- Se consulta sobre las tablas base, no sobre `vTransaccionAgronomico`. Se excluyen anuladas y, si existe, `aTransaccionEliminada`.
- El trabajador se cruza con `cTercero` por `nit` y, si no, por `codigo`.
- Límite de 20.000 registros; si se supera se avisa y la última labor puede quedar incompleta.
