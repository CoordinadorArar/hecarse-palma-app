<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class LoteLineaPalmas extends Informe
{
    public function opciones(string $filtro, array $dependencias): array
    {
        return $filtro === 'lote' ? (new PesoPromedioRacimosLote($this->empresa))->opciones('lote', $dependencias) : [];
    }

    public function filtros(): array
    {
        return [
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca. Con lote elegido se muestra el censo por línea.', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
            ['clave' => 'estado', 'etiqueta' => 'Estado del lote', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '1', 'ayuda' => 'Por defecto solo lotes activos. No aplica con lote elegido.',
                'opciones' => [['valor' => '1', 'texto' => 'Activos'], ['valor' => '0', 'texto' => 'Inactivos'], ['valor' => 'T', 'texto' => 'Todos']]],
            ['clave' => 'cuadre', 'etiqueta' => 'Cuadre', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos', 'ayuda' => 'Solo aplica al resumen.',
                'opciones' => [['valor' => 'D', 'texto' => 'Con diferencias'], ['valor' => 'C', 'texto' => 'Sin diferencias'], ['valor' => 'S', 'texto' => 'Sin censo']]],
        ];
    }

    private static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    private static function pct(float $valor, float $total): string
    {
        return self::n($total == 0 ? 0 : $valor * 100 / $total, 1) . '%';
    }

    private static function cuadre(int $maestro, int $censo, string $etiqueta): string
    {
        $dif = $maestro - $censo;

        return $dif === 0 ? 'Cuadra con el maestro (' . self::n($maestro) . ')' : $etiqueta . ' ' . self::n($maestro) . ' · diferencia ' . ($dif > 0 ? '+' : '-') . self::n(abs($dif));
    }

    private static function columna(string $clave, string $titulo, string $ayuda): array
    {
        return ['clave' => $clave, 'titulo' => $titulo, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => $ayuda];
    }

    public function validar(array $entrada): array|string
    {
        $valores = parent::validar($entrada);

        return is_array($valores) && $valores['finca'] !== '' && $valores['lote'] !== '' && explode('|', $valores['lote'], 2)[0] !== $valores['finca'] ? 'El lote no pertenece a la finca seleccionada.' : $valores;
    }

    public function consultar(array $filtros): array
    {
        return $filtros['lote'] === '' ? $this->resumen($filtros) : $this->detalle(explode('|', $filtros['lote'], 2));
    }

    private function resumen(array $filtros): array
    {
        $sql = "SELECT LTRIM(RTRIM(c.finca)) AS fincaCodigo, ISNULL(a.descripcion, '') AS fincaNombre, LTRIM(RTRIM(c.codigo)) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.NoLineas, 0) AS noLineas, ISNULL(c.palmasBrutas, 0) AS palmasBrutas, ISNULL(c.palmasProduccion, 0) AS palmasProduccion, ISNULL(c.activo, 0) AS activo,
                       d.lineas, d.palmas, d.erradicadas
                  FROM aLotes c
                 OUTER APPLY (SELECT TOP 1 descripcion FROM aFinca WHERE empresa = c.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.finca))) a
                  LEFT JOIN (SELECT empresa, LTRIM(RTRIM(lote)) AS lote, LTRIM(RTRIM(finca)) AS finca, COUNT(*) AS lineas, SUM(noPalma) AS palmas, SUM(ISNULL(palmaErradicada, 0)) AS erradicadas
                               FROM aLotesDetalle WHERE empresa = ? GROUP BY empresa, LTRIM(RTRIM(lote)), LTRIM(RTRIM(finca))) d
                    ON d.empresa = c.empresa AND d.lote = LTRIM(RTRIM(c.codigo)) AND d.finca = LTRIM(RTRIM(c.finca))
                 WHERE c.empresa = ?";

        $binds = [$this->empresa, $this->empresa];

        if ($filtros['finca'] !== '') {
            $sql    .= ' AND LTRIM(RTRIM(c.finca)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['estado'] !== 'T') {
            $sql    .= ' AND ISNULL(c.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $filas = [];

        foreach ($this->db->query($sql . ' ORDER BY c.finca, c.codigo', $binds)->getResultArray() as $c) {
            $sin         = $c['lineas'] === null;
            $brutas      = (int) $c['palmasBrutas'];
            $produccion  = (int) $c['palmasProduccion'];
            $lineas      = (int) $c['noLineas'];
            $censo       = $sin ? null : (int) $c['palmas'];
            $erradicadas = $sin ? null : (int) $c['erradicadas'];
            $prodCenso   = $sin ? null : $censo - $erradicadas;
            $conDif      = $sin ? $produccion > 0 || $brutas > 0 || $lineas > 0 : $brutas !== $censo || $produccion !== $prodCenso || $lineas !== (int) $c['lineas'];

            if (($filtros['cuadre'] === 'D' && ! $conDif) || ($filtros['cuadre'] === 'C' && ($sin || $conDif)) || ($filtros['cuadre'] === 'S' && ! $sin)) {
                continue;
            }

            $filas[] = [
                'finca'            => $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']),
                'lote'             => $c['loteCodigo'] . ' — ' . trim($c['loteNombre']),
                'lineasDeclaradas' => $lineas,
                'lineasCenso'      => (int) $c['lineas'],
                'palmasBrutas'     => $brutas,
                'palmasCenso'      => $censo,
                'erradicadas'      => $erradicadas,
                'palmasProduccion' => $produccion,
                'produccionCenso'  => $prodCenso,
                'diferencia'       => $produccion - ($prodCenso ?? 0),
                'estado'           => (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo',
                'sinCenso'         => $sin,
                'conDiferencia'    => $conDif,
            ];
        }

        $columnas = [
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            self::columna('lineasDeclaradas', 'Líneas lote', 'Número de líneas del maestro del lote'),
            self::columna('lineasCenso', 'Líneas censo', 'Líneas registradas en el censo'),
            self::columna('palmasBrutas', 'Palmas brutas (lote)', 'Palmas brutas del maestro'),
            self::columna('palmasCenso', 'Palmas censo', 'Suma de palmas por línea'),
            self::columna('erradicadas', 'Erradicadas', 'Palmas erradicadas del censo'),
            self::columna('palmasProduccion', 'Producción (lote)', 'Palmas en producción del maestro'),
            self::columna('produccionCenso', 'Producción censo', 'Palmas del censo menos erradicadas'),
            self::columna('diferencia', 'Diferencia', 'Producción del lote − producción del censo. Positiva: el maestro declara más palmas que el censo.'),
        ];

        if ($filtros['estado'] === 'T') {
            $columnas[] = ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false];
        }

        $totales = [];

        foreach (array_slice($columnas, 2, 8) as $col) {
            $totales[$col['clave']] = array_sum(array_column($filas, $col['clave']));
        }

        $conDif = count(array_filter(array_column($filas, 'conDiferencia')));
        $sinCen = count(array_filter(array_column($filas, 'sinCenso')));
        $lotes  = count($filas);

        return [
            'kpis' => [
                ['etiqueta' => 'Palmas en censo', 'valor' => $totales['palmasCenso'], 'formato' => 'entero', 'icono' => 'bi bi-tree', 'ayuda' => 'Maestro: ' . self::n($totales['palmasBrutas']) . ' palmas brutas'],
                ['etiqueta' => 'Erradicadas', 'valor' => $totales['erradicadas'], 'formato' => 'entero', 'icono' => 'bi bi-x-octagon', 'ayuda' => self::pct($totales['erradicadas'], $totales['palmasCenso']) . ' del censo'],
                ['etiqueta' => 'Lotes con diferencia', 'valor' => $conDif, 'formato' => 'entero', 'icono' => 'bi bi-exclamation-triangle',
                    'ayuda' => $conDif === 0 ? 'Censo cuadrado' : 'de ' . self::n($lotes) . ($lotes === 1 ? ' lote' : ' lotes') . ' · ' . self::n($sinCen) . ' sin censo'],
                ['etiqueta' => 'Promedio palmas por línea', 'valor' => $totales['lineasCenso'] > 0 ? round($totales['palmasCenso'] / $totales['lineasCenso'], 2) : null, 'formato' => 'decimal', 'icono' => 'bi bi-bar-chart-steps',
                    'ayuda' => self::n($totales['lineasCenso']) . ($totales['lineasCenso'] === 1 ? ' línea' : ' líneas') . ' en censo'],
            ],
            'grafica'  => null,
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'finca',
            'avisos'   => [],
        ];
    }

    private function detalle(array $lote): array
    {
        $m = $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS nombre, ISNULL(NoLineas, 0) AS noLineas, ISNULL(palmasBrutas, 0) AS palmasBrutas, ISNULL(palmasProduccion, 0) AS palmasProduccion
               FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = ?",
            [$this->empresa, $lote[0], $lote[1]]
        )->getRowArray();

        $crudas = $this->db->query(
            'SELECT linea, ISNULL(izquierda, 0) AS izquierda, ISNULL(noPalma, 0) AS palmas, ISNULL(palmaErradicada, 0) AS erradicadas FROM aLotesDetalle WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(lote)) = ? ORDER BY linea',
            [$this->empresa, $lote[0], $lote[1]]
        )->getResultArray();

        $nombre  = $m['codigo'] . ' — ' . trim($m['nombre']);
        $palmas  = array_map('intval', array_column($crudas, 'palmas'));
        $orden   = $palmas;
        $n       = count($orden);

        sort($orden);

        $mediana = $n === 0 ? null : ($n % 2 === 1 ? $orden[intdiv($n, 2)] : ($orden[$n / 2 - 1] + $orden[$n / 2]) / 2);
        $filas   = [];

        foreach ($crudas as $c) {
            $p       = (int) $c['palmas'];
            $e       = (int) $c['erradicadas'];
            $filas[] = [
                'linea'       => (string) $c['linea'],
                'lado'        => (int) $c['izquierda'] === 1 ? 'Izquierda' : 'Derecha',
                'palmas'      => $p,
                'erradicadas' => $e,
                'produccion'  => $p - $e,
                'atipica'     => $n >= 5 && $mediana > 0 && abs($p - $mediana) > max(0.5 * $mediana, 10),
                'negativa'    => $e > $p,
            ];
        }

        $totales = [
            'palmas'      => array_sum(array_column($filas, 'palmas')),
            'erradicadas' => array_sum(array_column($filas, 'erradicadas')),
            'produccion'  => array_sum(array_column($filas, 'produccion')),
        ];

        $atipicas = count(array_filter(array_column($filas, 'atipica')));
        $conErr   = count(array_filter(array_column($filas, 'erradicadas')));
        $med      = $mediana === null ? '—' : self::n($mediana, floor($mediana) == $mediana ? 0 : 1);
        $datos    = static fn (bool $atipica) => array_map(static fn ($f) => $f['atipica'] === $atipica ? $f['produccion'] : null, $filas);
        $series   = [['nombre' => 'En producción', 'datos' => $datos(false)]];

        if ($atipicas > 0) {
            $series[] = ['nombre' => 'En producción (línea atípica)', 'datos' => $datos(true)];
        }

        $series[] = ['nombre' => 'Erradicadas', 'datos' => array_column($filas, 'erradicadas')];

        return [
            'kpis' => [
                ['etiqueta' => 'Palmas en censo', 'valor' => $totales['palmas'], 'formato' => 'entero', 'icono' => 'bi bi-tree', 'ayuda' => self::cuadre((int) $m['palmasBrutas'], $totales['palmas'], 'Maestro')],
                ['etiqueta' => 'En producción', 'valor' => $totales['produccion'], 'formato' => 'entero', 'icono' => 'bi bi-check2-circle', 'ayuda' => self::cuadre((int) $m['palmasProduccion'], $totales['produccion'], 'Maestro')],
                ['etiqueta' => 'Erradicadas', 'valor' => $totales['erradicadas'], 'formato' => 'entero', 'icono' => 'bi bi-x-octagon',
                    'ayuda' => self::pct($totales['erradicadas'], $totales['palmas']) . ' del censo · ' . self::n($conErr) . ($conErr === 1 ? ' línea' : ' líneas') . ' con erradicadas'],
                ['etiqueta' => 'Líneas en censo', 'valor' => $n, 'formato' => 'entero', 'icono' => 'bi bi-list-ol', 'ayuda' => self::cuadre((int) $m['noLineas'], $n, 'Declaradas')],
                ['etiqueta' => 'Promedio palmas por línea', 'valor' => $n > 0 ? round($totales['palmas'] / $n, 2) : null, 'formato' => 'decimal', 'icono' => 'bi bi-bar-chart-steps',
                    'ayuda' => 'Mediana ' . $med . ($atipicas > 0 ? ' · ' . self::n($atipicas) . ($atipicas === 1 ? ' línea atípica' : ' líneas atípicas') : '')],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => true,
                'titulo'     => 'Palmas por línea · ' . $nombre,
                'categorias' => array_column($filas, 'linea'),
                'series'     => $series,
                'colores'    => $atipicas > 0 ? ['#41a867', '#f0a500', '#8795a4'] : ['#41a867', '#8795a4'],
                'detalle'    => array_map(static fn ($f) => ['palmas' => $f['palmas'], 'lado' => $f['lado']] + ($f['atipica'] || $f['negativa'] ? ['observacion' => implode(' · ', array_filter([
                    $f['atipica'] ? 'Atípica: ' . round($f['palmas'] * 100 / $mediana) . '% de la mediana (' . $med . ')' : '',
                    $f['negativa'] ? 'Erradicadas superan las palmas' : '',
                ]))] : []), $filas),
                'ejeX'       => 'Línea',
                'ejeY'       => 'Palmas',
                'formato'    => 'entero',
            ],
            'columnas' => [
                ['clave' => 'linea', 'titulo' => 'Línea', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'lado', 'titulo' => 'Lado', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false, 'ayuda' => 'Lado de la línea según el censo.'],
                ['clave' => 'palmas', 'titulo' => 'Palmas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'erradicadas', 'titulo' => 'Erradicadas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'produccion', 'titulo' => 'En producción', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => null,
            'avisos'  => $filas === [] ? ['El lote ' . $nombre . ' no tiene censo por línea registrado (maestro: ' . self::n((int) $m['noLineas']) . ((int) $m['noLineas'] === 1 ? ' línea, ' : ' líneas, ') . self::n((int) $m['palmasProduccion']) . ' palmas en producción).'] : [],
        ];
    }
}
