<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class LotesPorAnioSiembra extends Informe
{
    private const SIN = "(LTRIM(RTRIM(ISNULL(c.variedad, ''))) = '' OR e.codigo IS NULL)";

    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function filtros(): array
    {
        [$finca, $variedad, $estado] = (new LotesPorVariedad($this->empresa))->filtros();

        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query('SELECT DISTINCT [añoSiembra] AS anio FROM aLotes WHERE empresa = ? AND [añoSiembra] > 0 ORDER BY anio ASC', [$this->empresa])->getResultArray()
        );

        return [
            $finca,
            ['clave' => 'anioDesde', 'etiqueta' => 'Año siembra desde', 'tipo' => 'anio', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Sin límite', 'opciones' => $anios],
            ['clave' => 'anioHasta', 'etiqueta' => 'Año siembra hasta', 'tipo' => 'anio', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Sin límite', 'opciones' => $anios],
            $variedad,
            $estado,
        ];
    }

    private static function pct(float $valor, float $total): string
    {
        return $total == 0 ? '0%' : number_format($valor * 100 / $total, 1, ',', '.') . '%';
    }

    private static function edad(int $edad): string
    {
        return $edad === 0 ? 'menos de 1 año' : ($edad === 1 ? '1 año' : $edad . ' años');
    }

    private static function ha(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    public function consultar(array $filtros): array
    {
        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(a.codigo)) AS fincaCodigo, ISNULL(a.descripcion, '') AS fincaNombre,
                       LTRIM(RTRIM(c.codigo)) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.[añoSiembra], 0) AS anioSiembra, ISNULL(c.mesSiembra, 0) AS mesSiembra,
                       CASE WHEN " . self::SIN . " THEN '' ELSE ISNULL(e.descripcion, '') END AS variedad,
                       ISNULL(c.hNetas, 0) AS hNetas, ISNULL(c.palmasProduccion, 0) AS palmasProduccion,
                       ISNULL(c.desarrollo, 0) AS desarrollo, ISNULL(c.activo, 0) AS activo
                  FROM aLotes c
                  JOIN aFinca a ON a.empresa = c.empresa AND LTRIM(RTRIM(a.codigo)) = LTRIM(RTRIM(c.finca))
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM aVariedad WHERE empresa = c.empresa AND codigo = c.variedad) e
                 WHERE c.empresa = ?";

        $binds  = [$this->empresa];
        $avisos = [];
        $desde  = $filtros['anioDesde'];
        $hasta  = $filtros['anioHasta'];

        if ($filtros['finca'] !== '') {
            $sql    .= ' AND LTRIM(RTRIM(a.codigo)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($desde !== '' && $hasta !== '' && (int) $desde > (int) $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
            $avisos[]        = 'Se invirtió el rango de años de siembra.';
        }

        if ($desde !== '' || $hasta !== '') {
            $sql .= ' AND ISNULL(c.[añoSiembra], 0) > 0';
        }

        if ($desde !== '') {
            $sql    .= ' AND c.[añoSiembra] >= ?';
            $binds[] = (int) $desde;
        }

        if ($hasta !== '') {
            $sql    .= ' AND c.[añoSiembra] <= ?';
            $binds[] = (int) $hasta;
        }

        if ($filtros['variedad'] === '__SIN__') {
            $sql .= ' AND ' . self::SIN;
        } elseif ($filtros['variedad'] !== '') {
            $sql    .= ' AND NOT ' . self::SIN . ' AND RTRIM(c.variedad) = ?';
            $binds[] = $filtros['variedad'];
        }

        if ($filtros['estado'] !== 'T') {
            $sql    .= ' AND ISNULL(c.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $crudas = $this->db->query($sql . ' ORDER BY CASE WHEN ISNULL(c.[añoSiembra], 0) <= 0 THEN 1 ELSE 0 END, c.[añoSiembra], a.codigo, c.codigo', $binds)->getResultArray();
        $filas  = [];
        $anios  = [];
        $actual = (int) date('Y');

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . number_format(self::MAX_FILAS, 0, ',', '.') . ' filas.';
        }

        foreach ($crudas as $c) {
            $anio = (int) $c['anioSiembra'];
            $sin  = $anio <= 0;
            $edad = $sin ? '' : max(0, $actual - $anio);
            $mes  = (int) $c['mesSiembra'];

            $fila = [
                'grupo'            => $sin ? 'S/A — Sin año de siembra' : $anio . ' — ' . self::edad($edad),
                'edad'             => $edad,
                'pctHa'            => '',
                'sinAnio'          => $sin,
                'finca'            => $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']),
                'lote'             => $c['loteCodigo'] . ' — ' . trim($c['loteNombre']),
                'mesSiembra'       => self::MESES[$mes] ?? '',
                'variedad'         => trim($c['variedad']),
                'hNetas'           => (float) $c['hNetas'],
                'palmasProduccion' => (int) $c['palmasProduccion'],
                'estadoCultivo'    => (int) $c['desarrollo'] === 1 ? 'Desarrollo' : 'Producción',
                'estado'           => (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo',
            ];

            $filas[] = $fila;

            if (! $sin) {
                $anios[$anio] ??= ['hNetas' => 0.0, 'produccion' => 0.0, 'desarrollo' => 0.0, 'lotes' => 0, 'palmas' => 0, 'edad' => $edad];
                $anios[$anio]['hNetas'] += $fila['hNetas'];
                $anios[$anio][$fila['estadoCultivo'] === 'Desarrollo' ? 'desarrollo' : 'produccion'] += $fila['hNetas'];
                $anios[$anio]['palmas'] += $fila['palmasProduccion'];
                $anios[$anio]['lotes']++;
            }
        }

        $totales = [
            'hNetas'           => round(array_sum(array_column($filas, 'hNetas')), 2),
            'palmasProduccion' => array_sum(array_column($filas, 'palmasProduccion')),
        ];

        $porGrupo = [];

        foreach ($filas as $fila) {
            $porGrupo[$fila['grupo']] = ($porGrupo[$fila['grupo']] ?? 0.0) + $fila['hNetas'];
        }

        foreach ($filas as $i => $fila) {
            $filas[$i]['pctHa'] = self::pct($porGrupo[$fila['grupo']], $totales['hNetas']);
        }

        $sinLotes = count($filas) - array_sum(array_column($anios, 'lotes'));
        $haAnios  = array_sum(array_column($anios, 'hNetas'));

        if ($anios === []) {
            $promedio = ['—', 'Sin años de siembra registrados'];
        } else {
            $valor    = $haAnios > 0
                ? array_sum(array_map(static fn ($a) => $a['edad'] * $a['hNetas'], $anios)) / $haAnios
                : array_sum(array_map(static fn ($a) => $a['edad'] * $a['lotes'], $anios)) / array_sum(array_column($anios, 'lotes'));
            $promedio = [
                number_format($valor, 1, ',', '.') . ' años',
                ($haAnios > 0 ? 'Ponderada por Ha' : 'Promedio simple (sin Ha netas)') . ($sinLotes === 0 ? ($haAnios > 0 ? ' netas' : '') : ' · excluye ' . number_format($sinLotes, 0, ',', '.') . ($sinLotes === 1 ? ' lote sin año' : ' lotes sin año')),
            ];
        }

        $min = $anios === [] ? 0 : min(array_keys($anios));
        $max = $anios === [] ? 0 : max(array_keys($anios));

        if ($anios === []) {
            $rango = ['—', 'Sin años de siembra registrados'];
        } elseif ($min === $max) {
            $rango = [(string) $min, 'Edad de ' . self::edad($anios[$min]['edad'])];
        } else {
            $rango = [$min . ' – ' . $max, 'Edades de ' . $anios[$max]['edad'] . ' a ' . $anios[$min]['edad'] . ' años'];
        }

        $haProduccion = array_sum(array_map(static fn ($f) => $f['estadoCultivo'] === 'Producción' ? $f['hNetas'] : 0.0, $filas));
        $haDesarrollo = array_sum(array_map(static fn ($f) => $f['estadoCultivo'] === 'Desarrollo' ? $f['hNetas'] : 0.0, $filas));

        $lotes = number_format(count($filas), 0, ',', '.') . (count($filas) === 1 ? ' lote' : ' lotes');

        if ($filtros['estado'] === 'T') {
            $lotes .= ' · ' . number_format(count(array_keys(array_column($filas, 'estado'), 'Activo', true)), 0, ',', '.') . ' activos';
        }

        $categorias = [];

        for ($a = $min; $anios !== [] && $a <= $max; $a++) {
            $categorias[$a] = $anios[$a] ?? ['hNetas' => 0.0, 'produccion' => 0.0, 'desarrollo' => 0.0, 'lotes' => 0, 'palmas' => 0, 'edad' => max(0, $actual - $a)];
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Edad promedio', 'valor' => $promedio[0], 'formato' => 'texto', 'icono' => 'bi bi-hourglass-split', 'ayuda' => $promedio[1]],
                ['etiqueta' => 'Siembra más antigua / reciente', 'valor' => $rango[0], 'formato' => 'texto', 'icono' => 'bi bi-calendar-range', 'ayuda' => $rango[1]],
                ['etiqueta' => 'Ha en producción', 'valor' => $totales['hNetas'] == 0 ? '—' : self::pct($haProduccion, $totales['hNetas']), 'formato' => 'texto', 'icono' => 'bi bi-pie-chart',
                    'ayuda' => 'Producción ' . self::ha($haProduccion) . ' · Desarrollo ' . self::ha($haDesarrollo) . ' ha'],
                ['etiqueta' => 'Hectáreas netas (ha)', 'valor' => $totales['hNetas'], 'formato' => 'decimal', 'icono' => 'bi bi-rulers', 'ayuda' => $lotes],
            ],
            'grafica' => $anios === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => true,
                'categorias' => array_map('strval', array_keys($categorias)),
                'series'     => [
                    ['nombre' => 'Producción', 'datos' => array_map(static fn ($v) => round($v['produccion'], 2), array_values($categorias))],
                    ['nombre' => 'Desarrollo', 'datos' => array_map(static fn ($v) => round($v['desarrollo'], 2), array_values($categorias))],
                ],
                'colores'    => ['#2f7a4b', '#7cc49a'],
                'detalle'    => array_map(static fn ($v) => [
                    'hNetas'           => round($v['hNetas'], 2),
                    'edad'             => self::edad($v['edad']),
                    'lotes'            => $v['lotes'],
                    'palmasProduccion' => $v['palmas'],
                    'pctHa'            => $v['lotes'] === 0 ? '0,0%' : self::pct($v['hNetas'], $totales['hNetas']),
                ], array_values($categorias)),
                'ejeX'       => 'Año de siembra',
                'ejeY'       => 'Hectáreas netas',
                'formato'    => 'decimal',
            ],
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'edad', 'titulo' => 'Edad (años)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'mesSiembra', 'titulo' => 'Mes siembra', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasProduccion', 'titulo' => 'Palmas producción', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'estadoCultivo', 'titulo' => 'Estado cultivo', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'grupo',
            'avisos'   => $avisos,
        ];
    }
}
