<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class FincaLoteMetrosCanal extends Informe
{
    private const COLORES = ['#41a867', '#f0a500', '#4a5b6c', '#2f7a4b', '#7cc49a', '#8795a4', '#c3ccd5'];

    public function filtros(): array
    {
        $tipos = array_map(
            static fn ($t) => ['valor' => $t['codigo'], 'texto' => $t['codigo'] . ' — ' . trim($t['descripcion']) . ((int) $t['activo'] === 0 ? ' (inactivo)' : '')],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion, ISNULL(activo, 0) AS activo FROM aTipoCanal WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            [
                'clave'       => 'finca',
                'etiqueta'    => 'Finca',
                'tipo'        => 'select2',
                'obligatorio' => false,
                'defecto'     => '',
                'placeholder' => 'Todas las fincas',
                'opciones'    => (new LotesPorFincas($this->empresa))->opciones('finca', []),
            ],
            [
                'clave'       => 'tipoCanal',
                'etiqueta'    => 'Tipo de canal',
                'tipo'        => 'select2',
                'obligatorio' => false,
                'defecto'     => '',
                'placeholder' => 'Todos los tipos',
                'ayuda'       => 'Solo lotes con canal de este tipo.',
                'opciones'    => $tipos,
            ],
            [
                'clave'       => 'estado',
                'etiqueta'    => 'Estado del lote',
                'tipo'        => 'select',
                'obligatorio' => false,
                'defecto'     => '1',
                'ayuda'       => 'Por defecto solo lotes activos.',
                'opciones'    => [['valor' => '1', 'texto' => 'Activos'], ['valor' => '0', 'texto' => 'Inactivos'], ['valor' => 'T', 'texto' => 'Todos']],
            ],
        ];
    }

    private static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    private static function plural(int $n, string $singular, string $plural): string
    {
        return self::n($n) . ' ' . ($n === 1 ? $singular : $plural);
    }

    private static function densidad(array $filas): ?float
    {
        $ha = array_sum(array_map(static fn ($f) => $f['haUnica'] ?? 0.0, $filas));

        return $ha > 0 ? round(array_sum(array_column($filas, 'metrosConHa')) / $ha, 2) : null;
    }

    public function consultar(array $filtros): array
    {
        $from  = ' FROM aLotes c JOIN aFinca a ON a.empresa = c.empresa AND LTRIM(RTRIM(a.codigo)) = LTRIM(RTRIM(c.finca))';
        $where = ' WHERE c.empresa = ?';
        $binds = [$this->empresa];

        if ($filtros['finca'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(a.codigo)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['estado'] !== 'T') {
            $where  .= ' AND ISNULL(c.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $alcance = array_column($this->db->query('SELECT LTRIM(RTRIM(a.codigo)) AS finca, COUNT(*) AS lotes' . $from . $where . ' GROUP BY LTRIM(RTRIM(a.codigo))', $binds)->getResultArray(), 'lotes', 'finca');

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(a.codigo)) AS fincaCodigo, ISNULL(a.descripcion, '') AS fincaNombre,
                       LTRIM(RTRIM(ISNULL(c.seccion, ''))) AS seccion, LTRIM(RTRIM(c.codigo)) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.[añoSiembra], 0) AS anioSiembra, ISNULL(c.hNetas, 0) AS hNetas, b.tipoCanal, t.descripcion AS tipoNombre, b.metros"
            . $from . "
                  JOIN (SELECT LTRIM(RTRIM(lote)) AS lote, LTRIM(RTRIM(tipoCanal)) AS tipoCanal, SUM(ISNULL(metros, 0)) AS metros
                          FROM aLotesCanal WHERE empresa = ? GROUP BY LTRIM(RTRIM(lote)), LTRIM(RTRIM(tipoCanal))) b ON b.lote = LTRIM(RTRIM(c.codigo))
                 OUTER APPLY (SELECT TOP 1 ISNULL(descripcion, '') AS descripcion FROM aTipoCanal WHERE empresa = c.empresa AND LTRIM(RTRIM(codigo)) = b.tipoCanal) t"
            . $where;

        $binds = [$this->empresa, ...$binds];

        if ($filtros['tipoCanal'] !== '') {
            $sql    .= ' AND b.tipoCanal = ?';
            $binds[] = $filtros['tipoCanal'];
        }

        $crudas = $this->db->query($sql . ' ORDER BY a.codigo, c.seccion, c.codigo, b.tipoCanal', $binds)->getResultArray();
        $avisos = [];
        $filas  = [];
        $lotes  = [];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas.';
        }

        foreach ($crudas as $c) {
            $clave  = $c['fincaCodigo'] . '|' . $c['loteCodigo'];
            $hNetas = (float) $c['hNetas'];
            $metros = round((float) $c['metros'], 2);

            $filas[] = [
                'fincaCodigo' => $c['fincaCodigo'],
                'finca'       => $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']),
                'seccion'     => $c['seccion'],
                'loteClave'   => $clave,
                'lote'        => $c['loteCodigo'] . ' — ' . trim($c['loteNombre']),
                'anioSiembra' => (int) $c['anioSiembra'] === 0 ? '' : (string) $c['anioSiembra'],
                'tipoCodigo'  => $c['tipoCanal'],
                'tipoNombre'  => $c['tipoNombre'] === null ? $c['tipoCanal'] : trim($c['tipoNombre']),
                'tipoCanal'   => $c['tipoNombre'] === null ? $c['tipoCanal'] : $c['tipoCanal'] . ' — ' . trim($c['tipoNombre']),
                'hNetas'      => $hNetas,
                'metros'      => $metros,
                'mHa'         => $hNetas > 0 ? round($metros / $hNetas, 2) : null,
                'haUnica'     => isset($lotes[$clave]) ? null : $hNetas,
                'metrosConHa' => $hNetas > 0 ? $metros : 0.0,
                'lotesFinca'  => (int) ($alcance[$c['fincaCodigo']] ?? 0),
            ];

            $lotes[$clave] = $hNetas;
        }

        $totalLotes = array_sum(array_map('intval', $alcance));
        $metros     = round(array_sum(array_column($filas, 'metros')), 2);
        $densidad   = self::densidad($filas);
        $haNetas    = array_sum(array_filter($lotes, static fn ($h) => $h > 0));
        $sinHa      = count(array_filter($lotes, static fn ($h) => $h <= 0));
        $sinCanal   = $totalLotes - count($lotes);
        $deTipo     = $filtros['tipoCanal'] === '' ? '' : ' de este tipo';

        if ($filas === [] && $totalLotes > 0) {
            $avisos[] = 'Hay ' . self::plural($totalLotes, 'lote', 'lotes') . ' sin canal registrado con estos filtros.';
        }

        $porTipo  = [];
        $porFinca = [];

        foreach ($filas as $f) {
            $porTipo[$f['tipoCodigo']] ??= ['nombre' => $f['tipoNombre'], 'metros' => 0.0];
            $porTipo[$f['tipoCodigo']]['metros'] += $f['metros'];
            $porFinca[$f['finca']][] = $f;
        }

        uasort($porTipo, static fn ($a, $b) => $b['metros'] <=> $a['metros']);

        $principal = reset($porTipo);

        return [
            'kpis' => [
                ['etiqueta' => 'Metros de canal', 'valor' => $metros, 'formato' => 'entero', 'icono' => 'bi bi-rulers',
                    'ayuda' => self::plural(count($porTipo), 'tipo de canal', 'tipos de canal') . ' · ' . self::plural(count($porFinca), 'finca', 'fincas')],
                ['etiqueta' => 'Lotes con canal', 'valor' => count($lotes), 'formato' => 'entero', 'icono' => 'bi bi-grid-3x3-gap',
                    'ayuda' => $totalLotes === 0 ? 'Sin lotes en el alcance' : 'de ' . self::plural($totalLotes, 'lote', 'lotes') . ' · ' . ($sinCanal === 0 ? 'Todos con canal' . $deTipo : self::n($sinCanal) . ' sin canal' . $deTipo)],
                ['etiqueta' => 'Densidad promedio (m/Ha)', 'valor' => $densidad, 'formato' => 'decimal', 'icono' => 'bi bi-bounding-box',
                    'ayuda' => 'Sobre ' . self::n($haNetas, 2) . ' ha netas' . ($sinHa === 0 ? '' : ' · ' . self::plural($sinHa, 'lote sin Ha excluido', 'lotes sin Ha excluidos'))],
                ['etiqueta' => 'Tipo de canal predominante', 'valor' => $principal === false ? '—' : $principal['nombre'], 'formato' => 'texto', 'icono' => 'bi bi-water',
                    'ayuda' => $principal === false ? 'Sin canales registrados' : self::n($metros > 0 ? $principal['metros'] * 100 / $metros : 0, 1) . '% de los metros · ' . self::n($principal['metros']) . ' m'],
            ],
            'grafica'  => $filas === [] ? null : $this->grafica($porFinca, $porTipo),
            'columnas' => [
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'seccion', 'titulo' => 'Sección', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'tipoCanal', 'titulo' => 'Tipo de canal', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Ha netas del lote; se repite por cada tipo de canal'],
                ['clave' => 'metros', 'titulo' => 'Metros', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Suma de los registros del lote para el tipo de canal'],
                ['clave' => 'mHa', 'titulo' => 'm/Ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'metrosConHa', 'denominador' => 'haUnica',
                    'ayuda' => 'Metros / Ha netas del lote. En totales: Σ metros / Σ Ha netas, con cada lote contado una vez'],
            ],
            'filas'   => $filas,
            'totales' => ['metros' => $metros, 'mHa' => $densidad],
            'agrupar' => 'finca',
            'avisos'  => $avisos,
        ];
    }

    private function grafica(array $porFinca, array $porTipo): array
    {
        $totales = array_map(static fn ($f) => array_sum(array_column($f, 'metros')), $porFinca);

        arsort($totales);

        $grupos = count($porTipo) > 7 ? array_slice(array_keys($porTipo), 0, 6) : array_keys($porTipo);
        $series = [];

        foreach ($grupos as $codigo) {
            $series[$codigo] = ['nombre' => $porTipo[$codigo]['nombre'], 'tipos' => [(string) $codigo]];
        }

        if (count($porTipo) > 7) {
            $series['__OTROS__'] = ['nombre' => 'Otros', 'tipos' => array_map('strval', array_slice(array_keys($porTipo), 6))];
        }

        $datos = [];

        foreach ($series as $s) {
            $valores = [];

            foreach (array_keys($totales) as $finca) {
                $suyas     = array_filter($porFinca[$finca], static fn ($f) => in_array($f['tipoCodigo'], $s['tipos'], true));
                $valores[] = $suyas === [] ? null : round(array_sum(array_column($suyas, 'metros')), 2);
            }

            $datos[] = ['nombre' => $s['nombre'], 'datos' => $valores];
        }

        return [
            'tipo'       => 'bar',
            'horizontal' => true,
            'apilada'    => count($datos) > 1,
            'categorias' => array_keys($totales),
            'series'     => $datos,
            'detalle'    => array_map(static fn ($finca) => [
                'total' => round($totales[$finca], 2),
                'lotes' => count(array_unique(array_column($porFinca[$finca], 'loteClave'))),
                'mHa'   => self::densidad($porFinca[$finca]),
            ], array_keys($totales)),
            'ejeX'    => 'Metros',
            'ejeY'    => 'Finca',
            'formato' => 'entero',
            'colores' => array_slice(self::COLORES, 0, count($datos)),
        ];
    }
}
