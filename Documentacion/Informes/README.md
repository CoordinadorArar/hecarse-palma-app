# Informes — Plan de replicación

Plan de los 40 informes del sistema anterior (menú "Informes") que se replican en la loseta **Informes** de AppPalma. Cada archivo describe propósito, fuente de datos (procedimiento legado identificado), filtros, columnas, KPIs, gráfica y supuestos a validar.

## Diseño de la interfaz
- En el menú lateral hay **6 módulos**: Administración, Revisión Labores, Transacciones, Indicadores, Sanidad y Fertilización. El script que los registra es `Documentacion/BaseDeDatos/registrar_informes_modulos.sql`.
- Cada módulo es **una página con un selector de informe**. Al elegir un informe aparecen:
  - sus filtros y el botón Consultar;
  - los indicadores (KPIs);
  - la gráfica, cuando aplica;
  - la tabla de resultados, con búsqueda, orden y exportación a Excel.
- Todos los informes son **de solo lectura**: nunca hacen INSERT, UPDATE ni DELETE.

## Criterios generales
- Se excluyen las transacciones anuladas (`anulado = 1`) y las eliminadas (`aTransaccionEliminada`). Los procedimientos legados no las excluyen.
- Los terceros se cruzan por `cTercero.nit`, que es lo que guarda la aplicación nueva. Algunos procedimientos legados usan `codigo`.
- No se replican los errores detectados en el legado:
  - procedimientos que no filtran por empresa;
  - cruces duplicados, por ejemplo al unir insumos solo por lote o al sumar el peso neto desde `vSeleccionaTiqueteFruta`;
  - parámetros de fecha que se reciben pero no se aplican.
- Todavía hay que decidir, informe por informe, si el rango de fechas filtra la fecha de la transacción o la fecha de la labor.

## Índice

### 1. Administración
| Informe | Procedimiento legado | Gráfica |
|---|---|---|
| [Lotes por fincas](01-administracion-lotes-por-fincas.md) ✅ | spSeleccionaLotesPorFincaSeccion | Barras horizontales |
| [Lotes por sec/bloq](01-administracion-lotes-por-seccion-bloque.md) ✅ | spSeleccionaLotesPorFincaSeccion | Barras horizontales |
| [Lotes por variedad](01-administracion-lotes-por-variedad.md) ✅ | spSeleccionaLotesPorFincaSeccion | Barras horizontales |
| [Lotes por año siembra](01-administracion-lotes-por-anio-siembra.md) ✅ | spSeleccionaLotesPorFincaSeccion | Barras verticales |
| [Labores](01-administracion-labores.md) | spSeleccionaLaboresEmpresa | Barra de resumen |
| [Peso promedio racimos por lote](01-administracion-peso-promedio-racimos-lote.md) | aLotePesosPeriodo (lectura directa) | Línea mensual |
| [Lista de precio novedades](01-administracion-lista-precio-novedades.md) | spSeleccionaNovedadPrecios | Barras horizontales |
| [Finca - Lote metros de canal](01-administracion-finca-lote-metros-canal.md) | spSeleccionaLoteCanalInforme | Barras apiladas horizontales |
| [Lote linea palmas](01-administracion-lote-linea-palmas.md) | spSeleccionaLoteDetalle | Barras verticales |

### 2. Revisión Labores
| Informe | Procedimiento legado | Gráfica |
|---|---|---|
| [Labores tercero por fecha](02-revision-labores-tercero-fecha.md) | spSeleccionaLaboresTerceroFecha | Barras horizontales |
| [Labores tercero detallada por fecha](02-revision-labores-tercero-detallada-fecha.md) | spSeleccionaLaboresTerceroFecha | Barras + línea |
| [Labores detallada por fecha por novedad](02-revision-labores-detallada-fecha-novedad.md) | spSeleccionaLaboresLoteFecha | Barras + línea |

### 3. Transacciones
| Informe | Procedimiento legado | Gráfica |
|---|---|---|
| [Labores por fecha](03-transacciones-labores-fecha.md) | spSeleccionaInformeGeneralLaboresAgronomicos | Barras + línea |
| [Registro tiquetes por fecha](03-transacciones-registro-tiquetes-fecha.md) | spSeleccionaTiquetesRegistrados | Barras apiladas |
| [Labores por trabajador en fechas](03-transacciones-labores-trabajador-fechas.md) | spSeleccionaLaboresTerceroFecha | Barras horizontales |
| [Labores por lote en fecha](03-transacciones-labores-lote-fecha.md) | spSeleccionaLaboresLoteFecha | Barras horizontales apiladas |
| [Labores por centro de costo en fecha](03-transacciones-labores-ccosto-fecha.md) | spSeleccionaLaboresCcostoFecha | Barras horizontales |
| [Labores por centro de costo en fecha con lote](03-transacciones-labores-ccosto-fecha-lote.md) | spSeleccionaLaboresLoteFechaMes | Barras horizontales apiladas |
| [Labores detalle](03-transacciones-labores-detalle.md) | vTransaccionAgronomico (consulta directa) | Sin gráfica |
| [Producción lote fecha](03-transacciones-produccion-lote-fecha.md) | Lógica de spSeleccionaIndicadorAgronomico | Barras + línea |
| [Producción lote Anual](03-transacciones-produccion-lote-anual.md) | spSeleccionaIndicadorAgronomico | Mapa de calor + línea |
| [Venta de fruta por extractora](03-transacciones-venta-fruta-extractora.md) | spSeleccionaTiquetesRegistrados (agregado) | Barras apiladas |
| [Liquidación contratistas por periodo](03-transacciones-liquidacion-contratistas-periodo.md) | spSeleccionaLiquidacionContratistaPeriodoTercero | Barras horizontales |
| [Liquidación contratistas por periodo y lotes](03-transacciones-liquidacion-contratistas-periodo-lotes.md) | spSeleccionaLiquidacionContratistaTerceroNovedad | Barras horizontales apiladas |
| [Resumen contratistas por periodo](03-transacciones-resumen-contratistas-periodo.md) | spSeleccionaResumenLaboresTerceroFecha | Barras apiladas |
| [Hoja de vida de Labores](03-transacciones-hoja-vida-labores.md) | spSeleccionaHojadeVidaFincaFecha | Barras apiladas |
| [Tiquetes pendientes por registrar](03-transacciones-tiquetes-pendientes.md) | spSeleccionaTiquetesNoRegistrados | Barras |
| [Hoja de Vida Lote Novedad y fecha](03-transacciones-hoja-vida-lote-novedad-fecha.md) | spSeleccionaHojaVidaLoteLabores | Mapa de calor |
| [Diferencia días cosecha y recepción](03-transacciones-diferencia-dias-cosecha-recepcion.md) | spSeleccionaDiferenciasCosecha | Barras |

### 4. Indicadores
| Informe | Procedimiento legado | Gráfica |
|---|---|---|
| [Indicadores Año](04-indicadores-anio.md) | spSeleccionaIndicadorAgronomico | Barras agrupadas horizontales |
| [Indicadores Año por año de siembra](04-indicadores-anio-siembra.md) | spSeleccionaIndicadorAgronomico (reagrupado) | Barras + línea |
| [Ciclos de corte de lotes por racimos](04-indicadores-ciclos-corte-racimos.md) | spSeleccionaCicloCorteAñoMes | Mapa de calor |
| [Ciclos de labores](04-indicadores-ciclos-labores.md) | spSeleccionaHojaVidaLoteLabores | Mapa de calor |
| [Rendimiento trabajador por mes](04-indicadores-rendimiento-trabajador-mes.md) | spSeleccionaRendimientosTrabajadorMesAño | Barras horizontales |
| [Rendimiento trabajador cosecha](04-indicadores-rendimiento-trabajador-cosecha.md) | spSeleccionaRendimientoLabores | Barras horizontales + referencia |

### 5. Sanidad
| Informe | Fuente | Gráfica |
|---|---|---|
| [Grupo de características](05-sanidad-grupo-caracteristicas.md) | spInformeGrupoCaracteristica | Sin gráfica |
| [Sanidad Detalle](05-sanidad-detalle.md) | Labores del grupo 03 en TLA (aSanidad casi sin uso) | Barras horizontales apiladas |

### 6. Fertilización
| Informe | Procedimiento legado | Gráfica |
|---|---|---|
| [Plan de fertilización con saldos](06-fertilizacion-plan-saldos.md) | spSeleccionaPlanFertilizacionSaldo | Barras horizontales apiladas |
| [Labores fecha lote](06-fertilizacion-labores-fecha-lote.md) | spSeleccionaLaboresLoteFechaFertilizacion | Barras horizontales |
| [Labores fertilización detalle](06-fertilizacion-labores-fertilizacion-detalle.md) | spSeleccionaLaboresFertilizacionInforme | Barras apiladas |

## Datos verificados en la BD (solo lectura)
- `aSanidad` y `aSanidadDetalle` tienen 2 filas cada una. El trabajo sanitario real se registra como labores del grupo `03` en transacciones TLA; por ejemplo, 0309 Control de Strategus, 0301 Evaluación de enfermedades y 0304/656 Tratamientos.
- `aNovedad.ciclos > 0` solo en 28 de 143 labores activas.
- El saldo del plan de fertilización se calcula como plan menos lo aplicado en las RLF que referencian el plan. `aTransaccionItem.saldo` nunca se descuenta.
