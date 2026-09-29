# Lista de precio novedades

**Módulo:** Administración · **Clave:** `lista-precio-novedades` · **Estado:** Implementado

## Propósito
Muestra la tarifa anual de cada labor (destajo, contratistas, otros, porcentaje, base sueldo). Referencia para nómina, contratistas y control de costos.

## Fuente de datos
- Procedimiento legado probable: `spSeleccionaNovedadPrecios` (@año, @empresa); años disponibles con `spSeleccionaListaPrecios`. Precio efectivo: `spSeleccionaPrecioNovedadAño`.
- Tablas/vistas: `aNovedadLotePrecio`, `aNovedad`, `aGrupoNovedad`, `nParametrosAno`.
- Lógica clave:
  - `aNovedad` (activo = 1) LEFT JOIN `aNovedadLotePrecio` ON novedad, empresa, año → precios en 0 si no hay registro.
  - Precio destajo efectivo: si `baseSueldo = 1` → `nParametrosAno.vSalarioMinimo / 30`; si no, `precioDestajo`.
  - Variación vs año anterior = (precio año − precio año−1) / precio año−1.

## Filtros
| Filtro | Tipo | Obligatorio | Nota |
|---|---|---|---|
| Año | Select (años de `aNovedadLotePrecio`) | Sí | Default el más reciente |
| Grupo de labor | Select (aGrupoNovedad) | No | |
| Solo con precio | Check | No | Oculta labores con precios en 0 |

## Resultado (tabla)
| Columna | Descripción |
|---|---|
| Código | Labor |
| Labor | `aNovedad.descripcion` |
| Grupo | Grupo de labor |
| U. medida | `uMedida` |
| Precio destajo | `precioDestajo` (o efectivo si base sueldo) |
| Precio contratistas | `precioContratistas` |
| Precio otros | `precioOtros` |
| % | `porcentaje` |
| Base sueldo | Sí/No |
| Var. vs año anterior | % sobre precio destajo |

## Indicadores (KPIs)
- Labores con precio.
- Labores sin precio (en 0).
- Variación promedio vs año anterior.
- Labores a base sueldo.

## Gráfica
- Tipo: barras horizontales.
- Eje Y: labor (top 15 por variación absoluta); eje X: % de variación vs año anterior.
- Por qué: un listado de precios no tiene dimensión analítica propia; la variación interanual es lo que aporta decisión.

## Supuestos a validar
- `aNovedadLotePrecio.año` es varchar: normalizar a entero.
- Clave incluye `registro`: confirmar si puede haber más de un precio por labor/año (tomar el último).
- Significado de `precioOtros` y `porcentaje`.
