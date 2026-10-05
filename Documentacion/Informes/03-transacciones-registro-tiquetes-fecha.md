# Registro tiquetes por fecha

**Módulo:** Transacciones · **Clave:** `registro-tiquetes-fecha` · **Estado:** Implementado

## Propósito
Lista los tiquetes de báscula ya registrados en transacciones de cosecha (TLC) en un rango de fechas, con pesos, racimos, vehículo y extractora. Lo usan producción y administración para conciliar la fruta despachada.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaTiquetesRegistrados` (@empresa, @fi, @ff). Relacionado: `spSeleccionaInformeTiquetesAgronomico` (detalle por lote y trabajador de un tiquete).
- Tablas: `aTransaccion` (tipo TLC) + `aTransaccionBascula` (una por TLC), `aTransaccionNovedad` + `aFinca` (fincas de la TLC), `cTercero` (extractora). No se usa `vSeleccionaTiqueteFruta` porque duplica el tiquete por finca.
- Lógica clave:
  - `anulado = 0`, `empresa = @empresa`, `tipo = 'TLC'`, `CONVERT(date, aTransaccion.fecha) BETWEEN fi AND ff`. Se excluye `aTransaccionEliminada` si existe.
  - NIT de la extractora: `terceroExtractrora`, si está vacío `empresaExtractora` y, si no, `planta`.
  - Extractora: NIT → `cTercero.nit`, prefiriendo `extractora = 1`; si el NIT trae dígito de verificación (un carácter más) se cruza sin él.
  - Kilos = `pesoNeto`; racimos = `racimos`; peso promedio racimo = neto / racimos.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Fecha inicial | fecha | Sí | Por defecto, primer día del mes. Fecha de la transacción TLC |
| Fecha final | fecha | Sí | Por defecto, último día del mes. Máximo 1 año de rango |
| Finca | select (aFinca) | No | Tiquetes con al menos un lote en la finca |
| Extractora | select (cTercero extractora = 1) | No | Incluye "Sin extractora" solo si hay tiquetes sin NIT |
| Tiquete | texto | No | Búsqueda parcial |

## Resultado (tabla)
Una fila por tiquete, agrupada por extractora. Orden: extractoras por kg netos desc; dentro, fecha y número.

| Columna | Descripción |
|---|---|
| Extractora | NIT — razón social (o "Extractora no encontrada" / "Sin extractora") |
| Fecha | Fecha de la TLC |
| Transacción | `TLC-4191` |
| Tiquete | `tiquete` |
| Fecha báscula | `aTransaccionBascula.fecha` (dd/mm/aaaa) |
| Finca | Fincas distintas de la TLC unidas con " · " |
| Vehículo / Remolque | Placa en mayúsculas / tipo |
| Interno | Sí si `interno = 1` |
| Conductor / Cód. conductor | `nombreConductor` / `codigoConductor` |
| Sacos / Kg bruto / Kg tara / Kg netos | Báscula |
| Racimos | `racimos` |
| Kg/racimo | Neto / racimos (vacío si no hay racimos) |

Totales de pie: sacos, bruto, tara, neto, racimos y Kg/racimo ponderado (excluye tiquetes sin racimos).
Marcas por fila: neto descuadrado, sin racimos, tiquete repetido en otras TLC, báscula en día distinto a la TLC.

## Indicadores (KPIs)
- Kilos netos (promedio por tiquete).
- Tiquetes (días con despachos y tiquetes repetidos en varias TLC).
- Racimos (tiquetes sin racimos).
- Peso promedio por racimo (ponderado).

## Gráfica
- Tipo: barras apiladas.
- Eje X: fecha TLC por día; por semana (lunes) si el rango supera 45 días. Solo periodos con datos.
- Series: top 5 extractoras por kg netos + "Otras". Detalle: rango (semana), tiquetes, racimos y peso promedio.

## Supuestos resueltos
- El rango se filtra por la fecha de la transacción TLC, no por la de báscula.
- La extractora se cruza por NIT (no por `cTercero.codigo = planta` del legado). Los registros antiguos traen el NIT en `planta` y los nuevos en `terceroExtractrora`/`empresaExtractora` (siempre iguales); se toma el primero no vacío.
- Algunos tiquetes traen el NIT con dígito de verificación (`9002714285`); se cruzan con el NIT sin él.
- Una fila por tiquete (cada TLC tiene un único registro de báscula); la finca se muestra unida y no duplica pesos.
- Tiquete repetido: otras TLC no anuladas de toda la tabla con el mismo tiquete y NIT.
- La fecha de báscula no guarda hora (siempre 00:00); se muestra solo la fecha.
- Límite de 20.000 tiquetes; si se supera se avisa.
