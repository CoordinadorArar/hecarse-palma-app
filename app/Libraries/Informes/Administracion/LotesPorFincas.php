<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class LotesPorFincas extends Informe
{
    public function opciones(string $filtro, array $dependencias): array
    {
        $tipo = (string) ($dependencias['tipo'] ?? 'T');

        return $filtro === 'finca' ? $this->fincas(in_array($tipo, ['P', 'S'], true) ? $tipo : 'T') : [];
    }

    private function fincas(string $tipo): array
    {
        $sql   = 'SELECT LTRIM(RTRIM(codigo)) AS valor, ISNULL(descripcion, \'\') AS nombre FROM aFinca WHERE empresa = ?';
        $binds = [$this->empresa];

        if ($tipo !== 'T') {
            $sql    .= ' AND ISNULL(socio, 0) = ?';
            $binds[] = $tipo === 'S' ? 1 : 0;
        }

        return array_map(
            static fn ($f) => ['valor' => $f['valor'], 'texto' => $f['valor'] . ' — ' . trim($f['nombre'])],
            $this->db->query($sql . ' ORDER BY codigo ASC', $binds)->getResultArray()
        );
    }

    public function filtros(): array
    {
        $fincas = $this->fincas('T');

        return [
            [
                'clave'       => 'tipo',
                'etiqueta'    => 'Tipo de finca',
                'tipo'        => 'select',
                'obligatorio' => true,
                'defecto'     => 'T',
                'opciones'    => [['valor' => 'T', 'texto' => 'Todas'], ['valor' => 'P', 'texto' => 'Propias'], ['valor' => 'S', 'texto' => 'Socios']],
            ],
            [
                'clave'       => 'finca',
                'etiqueta'    => 'Finca',
                'tipo'        => 'select2',
                'obligatorio' => false,
                'defecto'     => '',
                'placeholder' => 'Todas las fincas',
                'depende'     => ['tipo'],
                'opciones'    => $fincas,
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
                       ISNULL(a.hectareas, 0) AS haFinca, ISNULL(NULLIF(LTRIM(RTRIM(f.razonSocial)), ''), ISNULL(f.descripcion, '')) AS propietario,
                       ISNULL(d.nombre, '') AS ciudad, ISNULL(a.zonaGeografica, '') AS zona,
                       LTRIM(RTRIM(ISNULL(c.seccion, ''))) AS seccionCodigo, ISNULL(b.descripcion, '') AS seccionNombre,
                       LTRIM(RTRIM(ISNULL(c.codigo, ''))) AS loteCodigo, ISNULL(c.descripcion, '') AS loteNombre,
                       ISNULL(c.[añoSiembra], 0) AS anioSiembra, ISNULL(c.mesSiembra, 0) AS mesSiembra, ISNULL(e.descripcion, '') AS variedad,
                       ISNULL(c.hBrutas, 0) AS hBrutas, ISNULL(c.hNetas, 0) AS hNetas, ISNULL(c.palmasBrutas, 0) AS palmasBrutas,
                       ISNULL(c.palmasProduccion, 0) AS palmasProduccion, ISNULL(ld.palmas, ISNULL(c.palmasProduccion, 0)) AS palmasDetalle,
                       ISNULL(c.densidad, 0) AS densidad, CASE WHEN c.codigo IS NULL THEN NULL WHEN ISNULL(c.activo, 0) = 0 THEN 0 ELSE 1 END AS activo
                  FROM aFinca a
                  LEFT JOIN aLotes c ON c.empresa = a.empresa AND LTRIM(RTRIM(c.finca)) = LTRIM(RTRIM(a.codigo))
                 OUTER APPLY (SELECT TOP 1 descripcion FROM aSecciones
                               WHERE empresa = a.empresa AND LTRIM(RTRIM(finca)) = LTRIM(RTRIM(a.codigo)) AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.seccion))) b
                 OUTER APPLY (SELECT TOP 1 nombre FROM gCiudad WHERE empresa = a.empresa AND codigo = a.ciudad) d
                 OUTER APPLY (SELECT TOP 1 descripcion FROM aVariedad WHERE empresa = a.empresa AND codigo = c.variedad) e
                 OUTER APPLY (SELECT TOP 1 razonSocial, descripcion FROM cTercero WHERE empresa = a.empresa AND id = a.proveedor) f
                 OUTER APPLY (SELECT SUM(noPalma) AS palmas FROM aLotesDetalle WHERE empresa = c.empresa AND LTRIM(RTRIM(lote)) = LTRIM(RTRIM(c.codigo))) ld
                 WHERE a.empresa = ?";

        $binds = [$this->empresa];

        if ($filtros['tipo'] !== 'T') {
            $sql    .= ' AND ISNULL(a.socio, 0) = ?';
            $binds[] = $filtros['tipo'] === 'S' ? 1 : 0;
        }

        if ($filtros['finca'] !== '') {
            $sql    .= ' AND LTRIM(RTRIM(a.codigo)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['estado'] !== 'T') {
            $sql    .= ' AND c.codigo IS NOT NULL AND ISNULL(c.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $crudas   = $this->db->query($sql . ' ORDER BY a.codigo, c.seccion, c.codigo', $binds)->getResultArray();
        $avisos   = [];
        $filas    = [];
        $porFinca = [];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . number_format(self::MAX_FILAS, 0, ',', '.') . ' filas.';
        }

        foreach ($crudas as $c) {
            $finca = $c['fincaCodigo'] . ' — ' . trim($c['fincaNombre']);
            $lote  = $c['loteCodigo'] === '' ? '' : $c['loteCodigo'] . ' — ' . trim($c['loteNombre']);

            $fila = [
                'fincaCodigo'      => $c['fincaCodigo'],
                'finca'            => $finca,
                'haFinca'          => (float) $c['haFinca'],
                'propietario'      => $c['propietario'],
                'ciudad'           => $c['ciudad'],
                'zona'             => $c['zona'],
                'seccion'          => $c['seccionNombre'] === '' ? $c['seccionCodigo'] : $c['seccionCodigo'] . ' — ' . trim($c['seccionNombre']),
                'lote'             => $lote,
                'siembra'          => (int) $c['anioSiembra'] > 0 ? $c['anioSiembra'] . '-' . str_pad((string) (int) $c['mesSiembra'], 2, '0', STR_PAD_LEFT) : '',
                'variedad'         => $c['variedad'],
                'hBrutas'          => (float) $c['hBrutas'],
                'hNetas'           => (float) $c['hNetas'],
                'palmasBrutas'     => (int) $c['palmasBrutas'],
                'palmasProduccion' => (int) $c['palmasProduccion'],
                'palmasDetalle'    => (int) $c['palmasDetalle'],
                'densidad'         => (float) $c['densidad'],
                'estado'           => $c['activo'] === null ? '' : ((int) $c['activo'] === 1 ? 'Activo' : 'Inactivo'),
            ];

            $filas[] = $fila;

            $porFinca[$finca] ??= ['hNetas' => 0.0, 'lotes' => 0, 'palmas' => 0, 'haFinca' => $fila['haFinca']];
            $porFinca[$finca]['hNetas'] += $fila['hNetas'];
            $porFinca[$finca]['palmas'] += $fila['palmasProduccion'];
            $porFinca[$finca]['lotes']  += $lote === '' ? 0 : 1;
        }

        uasort($porFinca, static fn ($a, $b) => $b['hNetas'] <=> $a['hNetas']);

        $totales = [];

        foreach (['hBrutas', 'hNetas', 'palmasBrutas', 'palmasProduccion', 'palmasDetalle'] as $c) {
            $totales[$c] = array_sum(array_column($filas, $c));
        }

        $totales['hBrutas'] = round($totales['hBrutas'], 2);
        $totales['hNetas']  = round($totales['hNetas'], 2);

        return [
            'kpis' => [
                ['etiqueta' => 'Fincas', 'valor' => count($porFinca), 'formato' => 'entero', 'icono' => 'bi bi-house'],
                ['etiqueta' => 'Lotes', 'valor' => count(array_filter(array_column($filas, 'lote'))), 'formato' => 'entero', 'icono' => 'bi bi-grid-3x3-gap']
                    + ($filtros['estado'] === 'T' ? ['ayuda' => number_format(count(array_keys(array_column($filas, 'estado'), 'Activo', true)), 0, ',', '.') . ' activos'] : []),
                ['etiqueta' => 'Hectáreas netas (ha)', 'valor' => $totales['hNetas'], 'formato' => 'decimal', 'icono' => 'bi bi-bounding-box', 'ayuda' => 'Suma de lotes'],
                ['etiqueta' => 'Palmas en producción', 'valor' => $totales['palmasProduccion'], 'formato' => 'entero', 'icono' => 'bi bi-tree'],
            ],
            'grafica' => $porFinca === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'categorias' => array_keys($porFinca),
                'series'     => [['nombre' => 'Ha netas', 'datos' => array_map(static fn ($f) => round($f['hNetas'], 2), array_values($porFinca))]],
                'detalle'    => array_map(static fn ($f) => ['lotes' => $f['lotes'], 'palmasProduccion' => $f['palmas'], 'haFinca' => $f['haFinca']], array_values($porFinca)),
                'ejeX'       => 'Hectáreas netas',
                'ejeY'       => 'Finca',
                'formato'    => 'decimal',
            ],
            'columnas' => [
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'propietario', 'titulo' => 'Propietario', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'ciudad', 'titulo' => 'Ciudad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'zona', 'titulo' => 'Zona', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'seccion', 'titulo' => 'Sección', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'siembra', 'titulo' => 'Siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'hBrutas', 'titulo' => 'Ha brutas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasBrutas', 'titulo' => 'Palmas brutas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasProduccion', 'titulo' => 'Palmas producción', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmasDetalle', 'titulo' => 'Palmas (detalle)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'densidad', 'titulo' => 'Densidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'finca',
            'avisos'   => $avisos,
        ];
    }
}
