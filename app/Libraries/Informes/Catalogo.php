<?php

namespace App\Libraries\Informes;

use App\Libraries\Informes\Administracion\LotesPorAnioSiembra;
use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Administracion\LotesPorSeccionBloque;
use App\Libraries\Informes\Administracion\LotesPorVariedad;

class Catalogo
{
    public const MODULOS = [
        'administracion'   => ['nombre' => 'Administración', 'icono' => 'bi bi-building-gear'],
        'revision-labores' => ['nombre' => 'Revisión Labores', 'icono' => 'bi bi-clipboard-check'],
        'transacciones'    => ['nombre' => 'Transacciones', 'icono' => 'bi bi-arrow-left-right'],
        'indicadores'      => ['nombre' => 'Indicadores', 'icono' => 'bi bi-graph-up-arrow'],
        'sanidad'          => ['nombre' => 'Sanidad', 'icono' => 'bi bi-bug'],
        'fertilizacion'    => ['nombre' => 'Fertilización', 'icono' => 'bi bi-droplet'],
    ];

    public const INFORMES = [
        'administracion' => [
            ['lotes-por-fincas', 'Lotes por fincas', 'bi bi-grid-3x3-gap', LotesPorFincas::class, 'Maestro de fincas con sus secciones y lotes (área, palmas, siembra, variedad). Responde "¿qué lotes tiene cada finca y cuánta área/palmas suman?" para administración agronómica y dirección.'],
            ['lotes-por-seccion-bloque', 'Lotes por sec/bloq', 'bi bi-grid-3x3-gap', LotesPorSeccionBloque::class, 'Agrupa los lotes por finca y sección (bloque) mostrando área y palmas por sección. Sirve a administración de campo para ver la distribución interna de cada finca.'],
            ['lotes-por-variedad', 'Lotes por variedad', 'bi bi-flower1', LotesPorVariedad::class, 'Muestra la composición del cultivo por variedad de palma (lotes, hectáreas y palmas). Apoya decisiones agronómicas y de renovación.'],
            ['lotes-por-anio-siembra', 'Lotes por año siembra', 'bi bi-calendar3', LotesPorAnioSiembra::class, 'Distribuye lotes, hectáreas y palmas por año de siembra (edad del cultivo). Clave para proyectar producción y planear renovación.'],
            ['labores', 'Labores', 'bi bi-tools', null, 'Catálogo de labores (novedades agronómicas) con su grupo, unidad de medida, concepto de nómina y parámetros de captura. Referencia para administración y nómina.'],
            ['peso-promedio-racimos-lote', 'Peso promedio racimos por lote', 'bi bi-speedometer2', null, 'Muestra el peso promedio de racimo (kg/racimo) por lote y periodo, usado para liquidar cosecha y estimar producción. Para jefes de campo y liquidación.'],
            ['lista-precio-novedades', 'Lista de precio novedades', 'bi bi-tags', null, 'Muestra la tarifa anual de cada labor (destajo, contratistas, otros, porcentaje, base sueldo). Referencia para nómina, contratistas y control de costos.'],
            ['finca-lote-metros-canal', 'Finca - Lote metros de canal', 'bi bi-water', null, 'Metros de canal registrados por lote y tipo de canal, agrupados por finca. Sirve para planear y valorar labores de limpieza/mantenimiento de canales.'],
            ['lote-linea-palmas', 'Lote linea palmas', 'bi bi-list-ol', null, 'Detalle de palmas por línea de cada lote (censo), incluidas erradicadas. Permite cuadrar el censo contra las palmas declaradas del lote; para agronomía y sanidad.'],
        ],
        'revision-labores' => [
            ['labores-tercero-fecha', 'Labores tercero por fecha', 'bi bi-person-lines-fill', null, 'Resumen de lo ejecutado y devengado por cada trabajador en un rango de fechas (cantidad, jornales, valor por labor). Para supervisores y nómina antes de liquidar.'],
            ['labores-tercero-detallada-fecha', 'Labores tercero detallada por fecha', 'bi bi-person-vcard', null, 'Detalle registro a registro de las labores de uno o varios trabajadores (fecha, transacción, lote, labor, cantidad, precio, valor). Para revisar y soportar reclamos antes de liquidar nómina.'],
            ['labores-detallada-fecha-novedad', 'Labores detallada por fecha por novedad', 'bi bi-card-checklist', null, 'Detalle de lo ejecutado por labor (novedad) en un rango de fechas: dónde (finca/lote), cuándo, quién, cuánto y a qué costo. Para supervisores que revisan una labor específica.'],
        ],
        'transacciones' => [
            ['labores-fecha', 'Labores por fecha', 'bi bi-calendar-week', null, 'Muestra todas las labores registradas (TLA, TLC, RLF...) en un rango de fechas, con trabajador, lote, cantidad y valor. Sirve a supervisión de campo y nómina para revisar lo digitado en el periodo.'],
            ['registro-tiquetes-fecha', 'Registro tiquetes por fecha', 'bi bi-receipt', null, 'Lista los tiquetes de báscula ya registrados en transacciones de cosecha (TLC) en un rango de fechas, con pesos, racimos, vehículo y extractora. Lo usan producción y administración para conciliar la fruta despachada.'],
            ['labores-trabajador-fechas', 'Labores por trabajador en fechas', 'bi bi-person-workspace', null, 'Consulta las labores realizadas por un trabajador en un rango de fechas: qué hizo, dónde y cuánto devengó. Lo usan nómina, supervisores y la atención de reclamos.'],
            ['labores-lote-fecha', 'Labores por lote en fecha', 'bi bi-geo-alt', null, 'Resume, por lote y labor, las cantidades y el costo ejecutados en un rango de fechas. Responde cuánto se invirtió en cada lote y en qué labores, para agronomía y control de costos.'],
            ['labores-ccosto-fecha', 'Labores por centro de costo en fecha', 'bi bi-diagram-3', null, 'Totaliza cantidades y valor de labores por centro de costo en un rango de fechas. Sirve a contabilidad y dirección para distribuir el costo de mano de obra de campo.'],
            ['labores-ccosto-fecha-lote', 'Labores por centro de costo en fecha con lote', 'bi bi-diagram-3-fill', null, 'Igual que "Labores por centro de costo en fecha" pero abierto por lote: muestra qué lotes y labores componen el costo de cada centro de costo. Para contabilidad de costos y agronomía.'],
            ['labores-detalle', 'Labores detalle', 'bi bi-table', null, 'Listado línea a línea de las transacciones de labores (documento, lote, labor, trabajador, cantidades, precio, valor, estado) con filtros amplios. Es la consulta de auditoría para revisar exactamente qué se digitó.'],
            ['produccion-lote-fecha', 'Producción lote fecha', 'bi bi-basket', null, 'Muestra la fruta cosechada por lote en un rango de fechas (kilos, racimos, peso promedio y toneladas por hectárea). Responde qué lotes están produciendo más y con qué rendimiento, para agronomía y gerencia.'],
            ['produccion-lote-anual', 'Producción lote Anual', 'bi bi-calendar-range', null, 'Matriz de producción por lote y mes de un año (kilos o toneladas), con acumulado, comparación con el año anterior y proyección anual. Para agronomía y gerencia en el seguimiento de la curva productiva.'],
            ['venta-fruta-extractora', 'Venta de fruta por extractora', 'bi bi-truck', null, 'Totaliza la fruta (kilos netos de báscula) despachada a cada extractora en un rango de fechas, por mes y finca. Responde a quién se vende la fruta y cuánto, para gerencia y facturación.'],
            ['liquidacion-contratistas-periodo', 'Liquidación contratistas por periodo', 'bi bi-cash-coin', null, 'Detalla las labores ejecutadas por trabajadores de contratistas en un rango de fechas, agrupadas por contratista (proveedor), para liquidar y pagar a cada contratista. Lo usan nómina y cuentas por pagar.'],
            ['liquidacion-contratistas-periodo-lotes', 'Liquidación contratistas por periodo y lotes', 'bi bi-cash-stack', null, 'Consolidado por contratista, trabajador y labor, abierto por lote cuando la labor lo exige, para soportar la cuenta de cobro del contratista y el costo por lote. Para nómina, cuentas por pagar y costos.'],
            ['resumen-contratistas-periodo', 'Resumen contratistas por periodo', 'bi bi-pie-chart', null, 'Resumen de alto nivel del valor ejecutado por contratistas por mes y grupo de labor, comparado con el personal propio. Responde cuánto del costo de campo se hace por contratistas y en qué labores. Para gerencia.'],
            ['hoja-vida-labores', 'Hoja de vida de Labores', 'bi bi-journal-text', null, 'Historial anual de cada grupo de labor: por mes, cuántos trabajadores participaron, qué cantidad se ejecutó y a qué costo. Responde cómo se comportan las labores a lo largo del año, para agronomía y planeación.'],
            ['tiquetes-pendientes', 'Tiquetes pendientes por registrar', 'bi bi-hourglass-split', null, 'Lista los tiquetes pesados en báscula (entrada de fruta propia) que todavía no tienen una transacción de cosecha registrada. Permite a producción asegurar que toda la fruta despachada quede distribuida por lote y trabajador.'],
            ['hoja-vida-lote-novedad-fecha', 'Hoja de Vida Lote Novedad y fecha', 'bi bi-journal-bookmark', null, 'Historial de un lote: qué labores (novedades) se le hicieron, cuándo y en qué cantidad, en un rango de fechas. Responde "qué se le ha hecho a este lote", para agronomía y auditoría de campo.'],
            ['diferencia-dias-cosecha-recepcion', 'Diferencia días cosecha y recepción', 'bi bi-clock-history', null, 'Mide cuántos días pasan entre la cosecha de la fruta en el lote y su recepción/pesaje en báscula, y qué porcentaje de kilos llega en cada rango de días. La fruta que tarda en llegar pierde calidad (acidez); lo usan producción y agronomía.'],
        ],
        'indicadores' => [
            ['indicadores-anio', 'Indicadores Año', 'bi bi-bar-chart', null, 'Compara, lote por lote, la producción del mes elegido y lo acumulado en el año contra el mismo periodo del año anterior: racimos, kilos, peso promedio del racimo, toneladas por hectárea y proyección anual. Está pensado para la gerencia agronómica y los jefes de finca.'],
            ['indicadores-anio-siembra', 'Indicadores Año por año de siembra', 'bi bi-bar-chart-line', null, 'Muestra los indicadores de producción (racimos, kilos, peso promedio, ton/ha, proyección) agrupados por año de siembra (edad de la palma) y los compara con el año anterior. Sirve para evaluar la curva productiva de cada cohorte de siembra.'],
            ['ciclos-corte-racimos', 'Ciclos de corte de lotes por racimos', 'bi bi-arrow-repeat', null, 'Muestra, día a día dentro de un mes, en qué lotes hubo cosecha y cuántos racimos se cortaron. Así se ve la rotación (ciclo de corte) de cada lote y se detectan lotes con intervalos demasiado largos entre pases. Es para supervisores de cosecha y jefes de finca.'],
            ['ciclos-labores', 'Ciclos de labores', 'bi bi-arrow-clockwise', null, 'Muestra, para cada lote y labor agronómica, cuántos ciclos (pases) se han ejecutado en el año y los compara con los ciclos programados de la labor. Sirve para controlar el cumplimiento del programa de mantenimiento (plateo, poda, control de malezas, etc.).'],
            ['rendimiento-trabajador-mes', 'Rendimiento trabajador por mes', 'bi bi-person-check', null, 'Compara, trabajador por trabajador y mes a mes, lo que devengó por labores contra lo que le correspondería por salario mínimo proporcional a los jornales trabajados. Así se identifica quién rinde por encima o por debajo del mínimo. Lo usan nómina y la supervisión de campo.'],
            ['rendimiento-trabajador-cosecha', 'Rendimiento trabajador cosecha', 'bi bi-person-up', null, 'Mide los kilos cosechados por cada trabajador, por día y en total, y los compara con la tarea o rendimiento esperado de la labor de cosecha. Es para supervisores de cosecha y nómina.'],
        ],
        'sanidad' => [
            ['grupo-caracteristicas', 'Grupo de características', 'bi bi-collection', null, 'Es el listado maestro de los grupos de características sanitarias y las características que contiene cada uno. Sirve de referencia para los registros de sanidad y para revisar la configuración del catálogo.'],
            ['sanidad-detalle', 'Sanidad Detalle', 'bi bi-shield-plus', null, 'Lista en detalle las labores sanitarias ejecutadas en un rango de fechas: control de Strategus, evaluación de enfermedades, tratamiento de palmas enfermas o jóvenes, censo de plagas. Muestra el lote, la labor, la fecha, la cantidad, el trabajador y el valor. Sirve al área de sanidad para seguir la intervención por lote y su costo.'],
        ],
        'fertilizacion' => [
            ['plan-saldos', 'Plan de fertilización con saldos', 'bi bi-clipboard-data', null, 'Muestra cada plan de fertilización (PFA) por lote e insumo con lo planificado, lo ejecutado según los registros de labores de fertilización (RLF) y el saldo pendiente. Es para el agrónomo y el almacén, que controlan el avance del plan y las necesidades de insumo.'],
            ['labores-fecha-lote', 'Labores fecha lote', 'bi bi-calendar2-check', null, 'Resume, para un rango de fechas, las labores de fertilización ejecutadas por lote y fecha: cantidad aplicada, insumo usado y valor de la mano de obra. Sirve para saber cuándo y con qué se fertilizó cada lote.'],
            ['labores-fertilizacion-detalle', 'Labores fertilización detalle', 'bi bi-droplet-half', null, 'Es el detalle línea a línea de cada registro de labores de fertilización (RLF): labor, lote, trabajador, insumo, dosis y bultos, y el plan (PFA) al que se aplica. Sirve para auditar y conciliar lo aplicado contra el plan y contra la nómina.'],
        ],
    ];

    public static function informes(string $modulo): array
    {
        return array_map(static fn ($i) => [
            'clave'       => $i[0],
            'nombre'      => $i[1],
            'icono'       => $i[2],
            'clase'       => $i[3],
            'descripcion' => $i[4],
            'estado'      => $i[3] === null ? 'proximamente' : 'disponible',
        ], self::INFORMES[$modulo] ?? []);
    }

    public static function informe(string $modulo, string $clave): ?array
    {
        foreach (self::informes($modulo) as $informe) {
            if ($informe['clave'] === $clave) {
                return $informe;
            }
        }

        return null;
    }
}
