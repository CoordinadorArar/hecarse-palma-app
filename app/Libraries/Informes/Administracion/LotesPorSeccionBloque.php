<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class LotesPorSeccionBloque extends Informe
{
    private const SIN = "(ISNULL(c.manejaSeccion, 0) = 0 OR b.codigo IS NULL)";

    public function opciones(string $filtro, array $dependencias): array
    {
        return $filtro === 'seccion' ? $this->secciones((string) ($dependencias['finca'] ?? '')) : [];
    }

    private function secciones(string $finca): array
    {
        $sql   = "SELECT LTRIM(RTRIM(s.finca)) AS finca, LTRIM(RTRIM(s.codigo)) AS codigo, ISNULL(s.descripcion, '') AS nombre, ISNULL(f.descripcion, '') AS fincaNombre
                    FROM aSecciones s
                    LEFT JOIN aFinca f ON f.empresa = s.empresa AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(s.finca))
                   WHERE s.empresa = ?";
        $binds = [$this->empresa];

        if ($finca !== '') {
            $sql    .= ' AND LTRIM(RTRIM(s.finca)) = ?';
            $binds[] = $finca;
        }

        $opciones = array_map(
            static fn ($s) => [
                'valor' => $s['finca'] . '|' . $s['codigo'],
                'texto' => ($finca === '' ? $s['finca'] . ' · ' : '') . $s['codigo'] . (trim($s['nombre']) === '' ? '' : ' — ' . trim($s['nombre'])),
            ],
            $this->db->query($sql . ' ORDER BY s.finca ASC, s.codigo ASC', $binds)->getResultArray()
        );

        $opciones[] = ['valor' => '__SIN__', 'texto' => 'Sin sección'];

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
                'clave'       => 'seccion',
                'etiqueta'    => 'Sección',
                'tipo'        => 'select2',
                'obligatorio' => false,
                'defecto'     => '',
                'placeholder' => 'Todas las secciones',
                'depende'     => ['finca'],
                'opciones'    => $this->secciones(''),
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

    public function consultar(array $filtros): array
    {
        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(a.codigo)) AS fincaCodigo, ISNULL(a.descripcion, '') AS fincaNombre,
                       CASE WHEN " . self::SIN . " THEN 1 ELSE 0 END AS sinSeccion,
                       LTRIM(RTRIM(ISNULL(b.codigo, ''))) AS seccionCodigo, ISNULL(b.descripcion, '') AS seccionNombre, ISNULL(b.hBrutas, 0) AS haSeccion,
                       LTRIM(RTRIM(c.codigo)) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.[añoSiembra], 0) AS anioSiembra, ISNULL(c.mesSiembra, 0) AS mesSiembra, ISNULL(e.descripcion, '') AS variedad,
                       ISNULL(c.hBrutas, 0) AS hBrutas, ISNULL(c.hNetas, 0) AS hNetas, ISNULL(c.palmasProduccion, 0) AS palmasProduccion,
                       ISNULL(c.activo, 0) AS activo
                  FROM aLotes c
                  JOIN aFinca a ON a.empresa = c.empresa AND LTRIM(RTRIM(a.codigo)) = LTRIM(RTRIM(c.finca))
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion, hBrutas FROM aSecciones
                               WHERE empresa = c.empresa AND LTRIM(RTRIM(finca)) = LTRIM(RTRIM(c.finca)) AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.seccion))) b
                 OUTER APPLY (SELECT TOP 1 descripcion FROM aVariedad WHERE empresa = c.empresa AND codigo = c.variedad) e
                 WHERE c.empresa = ?";

        $binds = [$this->empresa];

        if ($filtros['finca'] !== '') {
            $sql    .= ' AND LTRIM(RTRIM(a.codigo)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['seccion'] === '__SIN__') {
            $sql .= ' AND ' . self::SIN;
        } elseif ($filtros['seccion'] !== '') {
            [$finca, $seccion] = explode('|', $filtros['seccion'], 2);
            $sql    .= ' AND NOT ' . self::SIN . ' AND LTRIM(RTRIM(a.codigo)) = ? AND LTRIM(RTRIM(b.codigo)) = ?';
            $binds[] = $finca;
            $binds[] = $seccion;
        }

        if ($filtros['estado'] !== 'T') {
            $sql    .= ' AND ISNULL(c.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $crudas     = $this->db->query($sql . ' ORDER BY a.codigo, CASE WHEN ' . self::SIN . ' THEN 1 ELSE 0 END, b.codigo, c.codigo', $binds)->getResultArray();
        $avisos     = [];
        $filas      = [];
        $porSeccion = [];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . number_format(self::MAX_FILAS, 0, ',', '.') . ' filas.';
        }

        foreach ($crudas as $c) {
            $sin     = (int) $c['sinSeccion'] === 1;
            $seccion = $sin ? 'Sin sección' : $c['seccionCodigo'] . (trim($c['seccionNombre']) === '' ? '' : ' — ' . trim($c['seccionNombre']));

            $fila = [
                'grupo'            => $c['fincaCodigo'] . ' · ' . ($sin ? 'S/S — Sin sección' : $seccion),
                'finca'            => $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']),
                'seccion'          => $seccion,
                'sinSeccion'       => $sin,
                'haSeccion'        => $sin ? 0.0 : (float) $c['haSeccion'],
                'lote'             => $c['loteCodigo'] . ' — ' . trim($c['loteNombre']),
                'siembra'          => (int) $c['anioSiembra'] > 0 ? $c['anioSiembra'] . '-' . str_pad((string) (int) $c['mesSiembra'], 2, '0', STR_PAD_LEFT) : '',
                'variedad'         => $c['variedad'],
                'hBrutas'          => (float) $c['hBrutas'],
                'hNetas'           => (float) $c['hNetas'],
                'palmasProduccion' => (int) $c['palmasProduccion'],
                'estado'           => (int) $c['activo'] === 1 ? 'Activo' : 'Inactivo',
            ];

            $filas[] = $fila;

            $porSeccion[$fila['grupo']] ??= ['hNetas' => 0.0, 'lotes' => 0, 'palmas' => 0, 'haSeccion' => $fila['haSeccion'], 'sin' => $sin];
            $porSeccion[$fila['grupo']]['hNetas'] += $fila['hNetas'];
            $porSeccion[$fila['grupo']]['palmas'] += $fila['palmasProduccion'];
            $porSeccion[$fila['grupo']]['lotes']++;
        }

        $secciones = count(array_filter($porSeccion, static fn ($s) => ! $s['sin']));

        uasort($porSeccion, static fn ($a, $b) => $b['hNetas'] <=> $a['hNetas']);

        $top     = array_slice($porSeccion, 0, 15, true);
        $totales = [];

        foreach (['hBrutas', 'hNetas', 'palmasProduccion'] as $c) {
            $totales[$c] = array_sum(array_column($filas, $c));
        }

        $totales['hBrutas'] = round($totales['hBrutas'], 2);
        $totales['hNetas']  = round($totales['hNetas'], 2);

        return [
            'kpis' => [
                ['etiqueta' => 'Secciones', 'valor' => $secciones, 'formato' => 'entero', 'icono' => 'bi bi-grid-3x3-gap'],
                ['etiqueta' => 'Lotes', 'valor' => count($filas), 'formato' => 'entero', 'icono' => 'bi bi-bounding-box'],
                ['etiqueta' => 'Hectáreas netas (ha)', 'valor' => $totales['hNetas'], 'formato' => 'decimal', 'icono' => 'bi bi-rulers', 'ayuda' => 'Suma de lotes'],
                ['etiqueta' => 'Lotes sin sección', 'valor' => count(array_filter(array_column($filas, 'sinSeccion'))), 'formato' => 'entero', 'icono' => 'bi bi-exclamation-circle'],
            ],
            'grafica' => $top === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'categorias' => array_keys($top),
                'series'     => [['nombre' => 'Ha netas', 'datos' => array_map(static fn ($s) => round($s['hNetas'], 2), array_values($top))]],
                'detalle'    => array_map(static fn ($s) => ['lotes' => $s['lotes'], 'palmasProduccion' => $s['palmas'], 'haSeccion' => $s['haSeccion']], array_values($top)),
                'ejeX'       => 'Hectáreas netas',
                'ejeY'       => 'Sección',
                'formato'    => 'decimal',
            ],
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Sección/Bloque', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'seccion', 'titulo' => 'Sección', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'haSeccion', 'titulo' => 'Ha sección (declaradas)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'siembra', 'titulo' => 'Siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'hBrutas', 'titulo' => 'Ha brutas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasProduccion', 'titulo' => 'Palmas producción', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'grupo',
            'avisos'   => $avisos,
        ];
    }
}
