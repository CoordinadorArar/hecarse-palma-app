# Labores

**Módulo:** Administración · **Clave:** `labores` · **Estado:** Planificado

## Propósito
Catálogo de labores (novedades agronómicas) con su grupo, unidad de medida, concepto de nómina y parámetros de captura. Referencia para administración y nómina.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaLaboresEmpresa` (@empresa) → `SELECT * FROM vSeleccionaLabores`.
- Tablas/vistas: `vSeleccionaLabores` (= `aNovedad` JOIN `aGrupoNovedad` LEFT JOIN `nConcepto`).
- Lógica clave:
  - Signo: `naturaleza` 1 → '+', 2 → '-', otro → 'NA'.
  - Banderas de captura: manejaLote, manejaSaldo, manejaCanal, manejaLinea, manejaPalma, manejaRacimo, manejaJornal, manejaBascula, manejaFecha, manejaRango (añoDesde–añoHasta), porHaNeta/Bruta/Producción.
  - `claseLabor` y `grupo` (01 cosecha, 02 fertilización, 03 sanidad…) vienen de `aNovedad` (join adicional porque la vista no expone `claseLabor`).

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Grupo de labor | Select (aGrupoNovedad) | No | Todos |
| Estado | Select (Activas / Inactivas / Todas) | No | Default Activas |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Código | `codigoLabor` |
| Labor | `nombreLabor` / `desCorta` |
| Grupo | `codigoGrupo` – `nombreGrupo` |
| U. medida | `uMedida` |
| Signo | +, -, NA |
| Concepto nómina | `concepto` – `nombreConcepto` |
| Ciclos | `ciclos` |
| Parámetros | Íconos/insignias de las banderas `maneja*` activas |
| Rango años | `añoDesde`–`añoHasta` (si `manejaRango`) |
| Activa | Sí/No |

Sin totales de pie.

## Indicadores (KPIs)
- Labores activas.
- Grupos de labor.
- Labores sin concepto de nómina.

## Gráfica
- Tipo: barras horizontales (una sola, de resumen).
- Eje Y: grupo de labor; eje X: nº de labores activas.
- Por qué: es un listado maestro; la única dimensión útil es cuántas labores hay por grupo.

## Supuestos a validar
- Si se deben mostrar todas las banderas o solo las relevantes (lote, racimo, jornal, báscula).
- `tarea` (rendimiento esperado) podría añadirse como columna.
