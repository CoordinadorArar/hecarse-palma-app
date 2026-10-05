# Venta de fruta por extractora

**Módulo:** Transacciones · **Clave:** `venta-fruta-extractora` · **Estado:** Implementado

## Propósito
Totaliza la fruta (kilos netos de báscula) despachada a cada extractora en un rango de fechas, por mes y finca. Responde a quién se vende la fruta y cuánto, para gerencia y facturación.

## Fuente de datos
- Procedimiento legado probable: ninguno específico; se basa en `spSeleccionaTiquetesRegistrados` agregado por extractora. Relacionado: `spSeleccionaExtractoraExterna` (catálogo `cTercero` con `extractora = 1`).
- Tablas: `aTransaccion` (tipo TLC) + `aTransaccionBascula` (una por TLC), `aTransaccionNovedad` + `aFinca` (finca de la TLC), `cTercero` (extractora).
- Lógica clave:
  - `anulado = 0`, `empresa = @empresa`, `tipo = 'TLC'`, `CONVERT(date, aTransaccion.fecha) BETWEEN fi AND ff`. Se excluye `aTransaccionEliminada` si existe.
  - NIT de la extractora: `terceroExtractrora`, si está vacío `empresaExtractora` y, si no, `planta`. Se cruza con `cTercero.nit` prefiriendo `extractora = 1`, también sin dígito de verificación.
  - Kilos = Σ `pesoNeto`; racimos = Σ `racimos`; tiquetes = tiquetes distintos por (tiquete, NIT); % participación = kilos / kilos del rango filtrado.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes. Fecha de la transacción TLC |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Máximo 1 año de rango |
| Extractora | select (cTercero extractora = 1) | No | Incluye "Sin extractora" solo si hay tiquetes sin NIT |
| Finca | select (aFinca) | No | Tiquetes cuya TLC corresponde a la finca |
| Agrupar por | select (Extractora, Extractora y mes, Extractora y finca) | Sí | Extractora por defecto |

## Resultado (tabla)
Una fila por extractora, o por extractora y mes / extractora y finca (agrupadas por extractora). Orden: extractoras por kg netos desc (sin extractora al final); dentro, meses cronológicos o fincas por kg desc.

| Columna | Descripción |
|---|---|
| Extractora | NIT — razón social (o "Extractora no encontrada" / "Sin extractora") |
| Mes | `Ene 2025` (solo agrupando por mes) |
| Finca | Código — nombre (solo agrupando por finca) |
| Tiquetes | Tiquetes distintos |
| Racimos | Suma |
| Kg netos | Suma de `pesoNeto` |
| Toneladas | Kilos / 1000 |
| Kg/racimo | Kg netos / racimos (excluye registros de báscula sin racimos) |
| % part. | Kilos de la fila / kilos del rango filtrado |

Totales de pie: tiquetes, racimos, kilos, toneladas, Kg/racimo ponderado y % part.
Marcas por fila: tiquetes partidos en varias TLC, Kg/racimo que difiere más de 20 % del promedio del rango, registros de báscula sin racimos, extractora no encontrada.

## Indicadores (KPIs)
- Toneladas vendidas (kg netos y racimos).
- Tiquetes despachados (promedio t por tiquete y repetidos en varias TLC).
- Extractoras atendidas (de N extractoras activas).
- Extractora principal (% de participación, razón social y toneladas).

## Gráfica
- Rango con 2 o más meses con datos: barras apiladas; eje X todos los meses del rango (con año si cruza años), eje Y toneladas; series: 6 extractoras con más kilos + "Otras" (incluye sin extractora). Detalle por mes: total, tiquetes, racimos y peso promedio.
- Un solo mes: barras horizontales de toneladas por extractora. Detalle: participación, tiquetes, racimos y peso promedio.

## Supuestos resueltos
- No hay precio de venta en `aTransaccionBascula`; el informe es solo de kilos.
- El rango se filtra por la fecha de la transacción TLC, no por la de báscula.
- La extractora se cruza por NIT (no por `cTercero.codigo = planta` del legado); se toma el primer NIT no vacío y se admite el NIT con dígito de verificación. Hoy todos los NIT resuelven; se conservan "Extractora no encontrada" y "Sin extractora".
- Cada TLC tiene un único registro de báscula y una única finca; no se duplican pesos.
- Hay tiquetes partidos en 2 TLC (mismo camión, distinta tara): los kilos se suman y el tiquete se cuenta una vez por (tiquete, NIT), en la fila de la TLC con más kilos. Los tiquetes sin número cuentan cada uno.
- Solo tiquetes registrados (TLC); los pendientes de `bRegistroBascula` están en "Tiquetes pendientes por registrar".
