# Rendimiento trabajador por mes

**Módulo:** Indicadores · **Clave:** `rendimiento-trabajador-mes` · **Estado:** Implementado

## Propósito
Compara, trabajador por trabajador y mes a mes, lo que devengó por labores contra lo que le correspondería por salario mínimo proporcional a los jornales trabajados. Así se identifica quién rinde por encima o por debajo del mínimo. Lo usan nómina y la supervisión de campo.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaRendimientosTrabajadorMesAño` (@empresa, @año, @mi, @mf).
- Tablas/vistas: `vTransaccionAgronomico` (aTransaccion + aTransaccionNovedad + aTransaccionTercero + aNovedad + cTercero + nContratos + cCentrosCosto) y `nParametrosAno` (`vSalarioMinimo`).
- Lógica clave:
  - `anulado = 0`, `aNovedad.noPrestacional = 0`, `aTransaccionTercero.contratista = 0` (solo personal propio). Año y mes según `aTransaccion.fecha`.
  - Por trabajador × mes: jornales = `SUM(aTransaccionTercero.jornales)` y valor = `SUM(aTransaccionTercero.valorTotal)`.
  - Valor salario = `(vSalarioMinimo / 30) × jornales`, tomando `nParametrosAno.ano = año`.
  - Diferencia = valor − valor salario. Rinde = 1 si la diferencia ≥ 0.
  - Trabajador: `cTercero` unido por `nit = aTransaccionTercero.tercero` (convención del modelo actual). Centro de costo del contrato.
  - Se excluyen las transacciones que estén en `aTransaccionEliminada`.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select | Sí | |
| Mes inicial | Select 1–12 | Sí | |
| Mes final | Select 1–12 | Sí | ≥ mes inicial |
| Centro de costo | Select | No | |
| Trabajador | Autocompletar (cTercero) | No | |
| Solo bajo el mínimo | Check | No | Diferencia < 0 |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Documento | NIT del trabajador |
| Trabajador | Nombre |
| Centro de costo | |
| Mes | Nombre del mes |
| Jornales | Suma |
| Valor labores | Devengado por labores |
| Valor salario mínimo | (SMLV/30) × jornales |
| Diferencia | Valor labores − valor salario |
| Rinde | Sí/No (badge) |

Totales de pie: jornales, valor labores, valor salario y diferencia.

## Indicadores (KPIs)
- Trabajadores evaluados.
- % de trabajadores que rinden (diferencia ≥ 0).
- Diferencia total ($).
- Valor promedio por jornal frente a SMLV/30.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: trabajador (top 20 por diferencia, de mayor a menor, incluyendo los negativos). Eje X: diferencia ($). Color distinto para positivo y negativo.
- Por qué: compara muchos trabajadores con nombres largos y deja ver enseguida quién está por debajo del mínimo.

## Supuestos resueltos
- Implementado en `app/Libraries/Informes/Indicadores/RendimientoTrabajadorMes.php` sobre las tablas base (no la vista): anuladas fuera y, si existe, `aTransaccionEliminada`.
- Personal propio = trabajadores que **no** están en `nFuncionario` con `contratista = 1` (por `codigo` = `aTransaccionTercero.tercero`); `aTransaccionTercero.contratista` nunca vale 1. Solo labores con `aNovedad.noPrestacional = 0`.
- Se incluyen todos los tipos de transacción con labores por trabajador (TLA, TLC y RLF), igual que el legado. Mes según `aTransaccion.fecha`.
- Valor = `SUM(valorTotal)` con signo (las líneas con `signo = 2` restan); jornales con el mismo signo.
- **Jornales:** `aTransaccionTercero.jornales` está en 0 en todas las labores desde 2022. Si un trabajador-mes no tiene jornales registrados se usan los **días laborados** como jornales: fechas de labor distintas (`ISNULL(fechaNovedad, fecha de la línea)`) que caen dentro del mismo mes de `aTransaccion.fecha`. Así un día no se cuenta en dos meses ni se superan los días del mes; las pocas líneas con fecha de labor en otro mes (24 en 2025) suman valor pero no días. Si un trabajador-mes tiene valor pero ningún día de labor dentro del mes, se toman los días de labor distintos de esas líneas (aunque caigan en otro mes), con mínimo 1 y tope de los días del mes, para no dejar salario 0. Se avisa y la fila lleva `jornalesEstimados`.
- SMLV = `nParametrosAno.vSalarioMinimo` de la empresa y el año (solo existen 2022, 2023 y 2025). Sin parámetro: valor SMLV, diferencia, rinde, KPIs 2–4 y gráfica quedan vacíos y se avisa. No se escribe en la BD.
- Valor SMLV = SMLV/30 × jornales; diferencia = valor − valor SMLV; rinde = diferencia ≥ 0.
- **Centro de costo:** `aTransaccionTercero.contrato` está vacío y `nContratos` solo cubre ~20 de los ~90 trabajadores con labores, así que el contrato no es fiable. Se usa el centro de costo **predominante** (más líneas) de `aTransaccionTercero.ccosto` del trabajador en el rango; si todas sus líneas lo tienen vacío queda "Sin centro de costo" (se avisa). El filtro de centro de costo compara contra ese predominante.
- Nombre: `cTercero` por `nit` o `codigo` (prioriza `nit`); "cédula — nombre".
- "Solo bajo el mínimo" oculta los meses con diferencia ≥ 0; los KPIs, la gráfica y los auxiliares del trabajador se calculan sobre todos sus meses del rango.
- Gráfica: diferencia total del rango por trabajador; con más de 20, 10 mayores + 10 menores.
