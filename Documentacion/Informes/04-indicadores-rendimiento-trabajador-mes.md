# Rendimiento trabajador por mes

**Módulo:** Indicadores · **Clave:** `rendimiento-trabajador-mes` · **Estado:** Planificado

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

## Supuestos a validar
- El legado une `cTercero` por `codigo` (en la vista). El sistema actual usa `nit`. Hay que confirmar el cruce.
- Falta decidir si se incluyen las TLC (cosecha) además de las TLA. El legado no filtra por tipo de transacción.
- Falta decidir si se toma el mes por `aTransaccion.fecha` (legado) o por la fecha de la labor.
