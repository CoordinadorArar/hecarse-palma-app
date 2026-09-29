<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class LotesPorVariedad extends Informe
{
    private const SIN = "(LTRIM(RTRIM(ISNULL(c.variedad, ''))) = '' OR e.codigo IS NULL)";

    private function variedades(): array
    {
        $opciones = array_map(
            static fn ($v) => ['valor' => $v['codigo'], 'texto' => $v['codigo'] . ' — ' . trim($v['descripcion'])],
            $this->db->query("SELECT RTRIM(codigo) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aVariedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        $opciones[] = ['valor' => '__SIN__', 'texto' => 'Sin variedad'];

        return $opciones;
    }

    public function filtros(): array
    {
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
                'clave'       => 'variedad',
                'etiqueta'    => 'Variedad',
                'tipo'        => 'select2',
                'obligatorio' => false,
                'defecto'     => '',
                'placeholder' => 'Todas las variedades',
                'opciones'    => $this->variedades(),
            ],
            [
                'clave'       => 'estado',
                'etiqueta'    => 'Estado del lote',
                'tipo'        => 'select',
                'obligatorio' => false,
                'defecto'     => '1',
                'opciones'    => [['valor' => '1', 'texto' => 'Activos'], ['valor' => '0', 'texto' => 'Inactivos'], ['valor' => 'T', 'texto' => 'Todos']],
            ],
        ];
    }

    private static function pct(float $valor, float $total): string
    {
        return $total == 0 ? '0%' : number_format($valor * 100 / $total, 1, ',', '.') . '%';
    }

    public function consultar(array $filtros): array
    {
        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(a.codigo)) AS fincaCodigo, ISNULL(a.descripcion, '') AS fincaNombre,
                       CASE WHEN " . self::SIN . " THEN 1 ELSE 0 END AS sinVariedad,
                       RTRIM(ISNULL(e.codigo, '')) AS variedadCodigo, ISNULL(e.descripcion, '') AS variedadNombre, ISNULL(e.procedencia, '') AS procedencia,
                       LTRIM(RTRIM(c.codigo)) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.[añoSiembra], 0) AS anioSiembra, ISNULL(c.mesSiembra, 0) AS mesSiembra,
                       ISNULL(c.hNetas, 0) AS hNetas, ISNULL(c.palmasProduccion, 0) AS palmasProduccion, ISNULL(c.densidad, 0) AS densidad,
                       ISNULL(c.activo, 0) AS activo
                  FROM aLotes c
                  JOIN aFinca a ON a.empresa = c.empresa AND LTRIM(RTRIM(a.codigo)) = LTRIM(RTRIM(c.finca))
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion, procedencia FROM aVariedad WHERE empresa = c.empresa AND codigo = c.variedad) e
                 WHERE c.empresa = ?";

        $binds = [$this->empresa];

        if ($filtros['finca'] !== '') {
            $sql    .= ' AND LTRIM(RTRIM(a.codigo)) = ?';
            $binds[] = $filtros['finca'];
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

        $crudas      = $this->db->query($sql . ' ORDER BY a.codigo, c.codigo', $binds)->getResultArray();
        $avisos      = [];
        $filas       = [];
        $porVariedad = [];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . number_format(self::MAX_FILAS, 0, ',', '.') . ' filas.';
        }

        foreach ($crudas as $c) {
            $sin = (int) $c['sinVariedad'] === 1;

            $fila = [
                'grupo'            => $sin ? 'S/V — Sin variedad' : $c['variedadCodigo'] . ' — ' . trim($c['variedadNombre']),
                'procedencia'      => $sin ? '' : trim($c['procedencia']),
                'sinVariedad'      => $sin,
                'pctHa'            => '',
                'finca'            => $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']),
                'lote'             => $c['loteCodigo'] . ' — ' . trim($c['loteNombre']),
                'siembra'          => (int) $c['anioSiembra'] > 0 ? $c['anioSiembra'] . '-' . str_pad((string) (int) $c['mesSiembra'], 2, '0', STR_PAD_LEFT) : '',
                'hNetas'           => (float) $c['hNetas'],
                'palmasProduccion' => (int) $c['palmasProduccion'],
                'densidad'         => (float) $c['densidad'],
                'estado'           => (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo',
            ];

            $filas[] = $fila;

            $porVariedad[$fila['grupo']] ??= ['hNetas' => 0.0, 'lotes' => 0, 'palmas' => 0, 'procedencia' => $fila['procedencia'], 'nombre' => trim($c['variedadNombre']), 'sin' => $sin];
            $porVariedad[$fila['grupo']]['hNetas'] += $fila['hNetas'];
            $porVariedad[$fila['grupo']]['palmas'] += $fila['palmasProduccion'];
            $porVariedad[$fila['grupo']]['lotes']++;
        }

        uasort($porVariedad, static fn ($a, $b) => [$a['sin'], $b['hNetas']] <=> [$b['sin'], $a['hNetas']]);

        $totales = [
            'hNetas'           => round(array_sum(array_column($filas, 'hNetas')), 2),
            'palmasProduccion' => array_sum(array_column($filas, 'palmasProduccion')),
        ];

        foreach ($porVariedad as $grupo => $v) {
            $porVariedad[$grupo]['pctHa'] = self::pct($v['hNetas'], $totales['hNetas']);
        }

        $orden = array_flip(array_keys($porVariedad));

        foreach ($filas as $i => $fila) {
            $filas[$i]['pctHa'] = $porVariedad[$fila['grupo']]['pctHa'];
        }

        usort($filas, static fn ($a, $b) => $orden[$a['grupo']] <=> $orden[$b['grupo']]);

        $conVariedad = array_filter($porVariedad, static fn ($v) => ! $v['sin']);
        $sinLotes    = $porVariedad['S/V — Sin variedad']['lotes'] ?? 0;
        $principal   = reset($conVariedad);
        $lotes       = number_format(count($filas), 0, ',', '.') . (count($filas) === 1 ? ' lote' : ' lotes');

        if ($filtros['estado'] === 'T') {
            $lotes .= ' · ' . number_format(count(array_keys(array_column($filas, 'estado'), 'Activo', true)), 0, ',', '.') . ' activos';
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Variedades en uso', 'valor' => count($conVariedad), 'formato' => 'entero', 'icono' => 'bi bi-tags',
                    'ayuda' => $sinLotes === 0 ? 'Todos asignados' : ($sinLotes === 1 ? '1 lote sin variedad' : number_format($sinLotes, 0, ',', '.') . ' lotes sin variedad')],
                ['etiqueta' => 'Variedad predominante', 'valor' => $principal === false ? '—' : $principal['nombre'], 'formato' => 'texto', 'icono' => 'bi bi-award',
                    'ayuda' => $principal === false ? 'Sin variedades asignadas' : $principal['pctHa'] . ' de las Ha netas'],
                ['etiqueta' => 'Hectáreas netas (ha)', 'valor' => $totales['hNetas'], 'formato' => 'decimal', 'icono' => 'bi bi-rulers', 'ayuda' => $lotes],
                ['etiqueta' => 'Palmas en producción', 'valor' => $totales['palmasProduccion'], 'formato' => 'entero', 'icono' => 'bi bi-tree'],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'categorias' => array_keys($porVariedad),
                'series'     => [['nombre' => 'Ha netas', 'datos' => array_map(static fn ($v) => round($v['hNetas'], 2), array_values($porVariedad))]],
                'detalle'    => array_map(static fn ($v) => ['pctHa' => $v['pctHa'], 'lotes' => $v['lotes'], 'palmasProduccion' => $v['palmas'], 'procedencia' => $v['procedencia'] === '' ? '—' : $v['procedencia']], array_values($porVariedad)),
                'ejeX'       => 'Hectáreas netas',
                'ejeY'       => 'Variedad',
                'formato'    => 'decimal',
            ],
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'procedencia', 'titulo' => 'Procedencia', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'siembra', 'titulo' => 'Siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasProduccion', 'titulo' => 'Palmas producción', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'densidad', 'titulo' => 'Densidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'grupo',
            'avisos'   => $avisos,
        ];
    }
}
