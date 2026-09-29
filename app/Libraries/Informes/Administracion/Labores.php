<?php

namespace App\Libraries\Informes\Administracion;

use App\Controllers\gestionpalma\LaboresController;
use App\Libraries\Informes\Informe;

class Labores extends Informe
{
    private const SIN = "(LTRIM(RTRIM(ISNULL(a.grupo, ''))) = '' OR g.codigo IS NULL)";

    private const BANDERAS = [
        'manejaLote'      => 'Lote',
        'manejaPalma'     => 'Palma',
        'manejaLinea'     => 'Línea',
        'manejaCanal'     => 'Canal',
        'manejaRacimo'    => 'Racimo',
        'manejaBascula'   => 'Báscula',
        'manejaJornal'    => 'Jornal',
        'manejaSaldo'     => 'Saldo',
        'manejaRango'     => 'Rango',
        'porHaNeta'       => 'Ha neta',
        'porHaBruta'      => 'Ha bruta',
        'porHaProduccion' => 'Ha producción',
    ];

    private static function clase(int $valor): string
    {
        return LaboresController::CLASES_LABOR[$valor] ?? "Sin etiqueta ({$valor})";
    }

    private static function pct(float $valor, float $total): string
    {
        return $total == 0 ? '0%' : number_format($valor * 100 / $total, 1, ',', '.') . '%';
    }

    private static function n(int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }

    public function filtros(): array
    {
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        $grupos[] = ['valor' => '__SIN__', 'texto' => 'Sin grupo'];

        $clases = array_map(
            static fn ($c) => ['valor' => (string) $c['claseLabor'], 'texto' => self::clase((int) $c['claseLabor'])],
            $this->db->query('SELECT DISTINCT claseLabor FROM aNovedad WHERE empresa = ? AND claseLabor IS NOT NULL ORDER BY claseLabor ASC', [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
            ['clave' => 'clase', 'etiqueta' => 'Clase de labor', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las clases', 'opciones' => $clases],
            ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '1',
                'opciones' => [['valor' => '1', 'texto' => 'Activas'], ['valor' => '0', 'texto' => 'Inactivas'], ['valor' => 'T', 'texto' => 'Todas']]],
        ];
    }

    public function consultar(array $filtros): array
    {
        $banderas = implode(', ', array_map(static fn ($b) => "ISNULL(a.{$b}, 0) AS {$b}", array_keys(self::BANDERAS)));

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " CASE WHEN " . self::SIN . " THEN 1 ELSE 0 END AS sinGrupo,
                       ISNULL(LTRIM(RTRIM(g.codigo)), '') AS grupoCodigo, ISNULL(g.descripcion, '') AS grupoNombre,
                       LTRIM(RTRIM(a.codigo)) AS codigo, ISNULL(a.descripcion, '') AS descripcion, ISNULL(a.desCorta, '') AS desCorta,
                       a.claseLabor, ISNULL(a.uMedida, '') AS uMedida, ISNULL(a.naturaleza, 0) AS naturaleza,
                       LTRIM(RTRIM(ISNULL(a.concepto, ''))) AS concepto, c.descripcion AS conceptoNombre, c.codigo AS conceptoExiste,
                       ISNULL(a.ciclos, 0) AS ciclos, ISNULL(a.tarea, 0) AS tarea, {$banderas},
                       ISNULL(a.[añoDesde], 0) AS anioDesde, ISNULL(a.[añoHasta], 0) AS anioHasta, ISNULL(a.activo, 0) AS activo
                  FROM aNovedad a
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM aGrupoNovedad WHERE empresa = a.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(a.grupo))) g
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM nConcepto WHERE empresa = a.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(a.concepto))) c
                 WHERE a.empresa = ?";

        $binds = [$this->empresa];

        if ($filtros['grupo'] === '__SIN__') {
            $sql .= ' AND ' . self::SIN;
        } elseif ($filtros['grupo'] !== '') {
            $sql    .= ' AND NOT ' . self::SIN . ' AND LTRIM(RTRIM(g.codigo)) = ?';
            $binds[] = $filtros['grupo'];
        }

        if ($filtros['clase'] !== '') {
            $sql    .= ' AND a.claseLabor = ?';
            $binds[] = (int) $filtros['clase'];
        }

        if ($filtros['estado'] !== 'T') {
            $sql    .= ' AND ISNULL(a.activo, 0) = ?';
            $binds[] = (int) $filtros['estado'];
        }

        $crudas = $this->db->query($sql . ' ORDER BY sinGrupo, grupoCodigo, codigo', $binds)->getResultArray();
        $avisos = [];
        $filas  = [];
        $grupos = [];
        $conteo = ['sinConcepto' => 0, 'porLote' => 0, 'racimo' => 0, 'bascula' => 0, 'activas' => 0];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas.';
        }

        foreach ($crudas as $c) {
            $sin         = (int) $c['sinGrupo'] === 1;
            $descripcion = trim($c['descripcion']);
            $corta       = trim($c['desCorta']);
            $parametros  = [];

            foreach (self::BANDERAS as $campo => $texto) {
                if ((int) $c[$campo] === 1) {
                    $parametros[] = $campo === 'manejaRango' && ((int) $c['anioDesde'] > 0 || (int) $c['anioHasta'] > 0) ? 'Rango ' . $c['anioDesde'] . '–' . $c['anioHasta'] . ' años' : $texto;
                }
            }

            $fila = [
                'grupo'      => $sin ? 'S/G — Sin grupo' : $c['grupoCodigo'] . ' — ' . trim($c['grupoNombre']),
                'sinGrupo'   => $sin,
                'codigo'     => $c['codigo'],
                'labor'      => $descripcion,
                'desCorta'   => $corta === $descripcion ? '' : $corta,
                'clase'      => $c['claseLabor'] === null ? '' : self::clase((int) $c['claseLabor']),
                'uMedida'    => trim($c['uMedida']),
                'signo'      => match ((int) $c['naturaleza']) { 1 => '+', 2 => '−', default => 'NA' },
                'concepto'   => $c['concepto'] === '' ? '' : ($c['conceptoExiste'] === null ? $c['concepto'] : $c['concepto'] . ' — ' . trim((string) $c['conceptoNombre'])),
                'ciclos'     => (int) $c['ciclos'],
                'tarea'      => (int) $c['tarea'],
                'parametros' => implode(' · ', $parametros),
                'estado'     => (int) $c['activo'] === 1 ? 'Activa' : 'Inactiva',
            ];

            $filas[] = $fila;

            $sinConcepto = $c['concepto'] === '';
            $porLote     = (int) $c['manejaLote'] === 1;

            $conteo['sinConcepto'] += (int) $sinConcepto;
            $conteo['porLote']     += (int) $porLote;
            $conteo['racimo']      += (int) $c['manejaRacimo'];
            $conteo['bascula']     += (int) $c['manejaBascula'];
            $conteo['activas']     += (int) $c['activo'];

            $grupos[$fila['grupo']] ??= ['total' => 0, 'activas' => 0, 'inactivas' => 0, 'sinConcepto' => 0, 'porLote' => 0, 'sin' => $sin];
            $grupos[$fila['grupo']]['total']++;
            $grupos[$fila['grupo']][(int) $c['activo'] === 1 ? 'activas' : 'inactivas']++;
            $grupos[$fila['grupo']]['sinConcepto'] += (int) $sinConcepto;
            $grupos[$fila['grupo']]['porLote']     += (int) $porLote;
        }

        uksort($grupos, static fn ($a, $b) => [$grupos[$a]['sin'], $grupos[$b]['total']] <=> [$grupos[$b]['sin'], $grupos[$a]['total']]);

        $total     = count($filas);
        $sinLabor  = $grupos['S/G — Sin grupo']['total'] ?? 0;
        $etiquetas = ['1' => 'Labores activas', '0' => 'Labores inactivas', 'T' => 'Labores'];
        $kpiFilas  = ['etiqueta' => $etiquetas[$filtros['estado']], 'valor' => $total, 'formato' => 'entero', 'icono' => 'bi bi-list-check'];

        if ($filtros['estado'] === 'T') {
            $inactivas         = $total - $conteo['activas'];
            $kpiFilas['ayuda'] = self::n($conteo['activas']) . ($conteo['activas'] === 1 ? ' activa · ' : ' activas · ') . self::n($inactivas) . ($inactivas === 1 ? ' inactiva' : ' inactivas');
        }

        $series = $filtros['estado'] === 'T'
            ? [['nombre' => 'Activas', 'datos' => array_column($grupos, 'activas')], ['nombre' => 'Inactivas', 'datos' => array_column($grupos, 'inactivas')]]
            : [['nombre' => $etiquetas[$filtros['estado']], 'datos' => array_column($grupos, 'total')]];

        $grafica = [
            'tipo'       => 'bar',
            'horizontal' => true,
            'apilada'    => $filtros['estado'] === 'T',
            'categorias' => array_map('strval', array_keys($grupos)),
            'series'     => $series,
            'detalle'    => array_map(static fn ($g) => ['sinConcepto' => $g['sinConcepto'], 'porLote' => $g['porLote'], 'pct' => self::pct($g['total'], $total)], array_values($grupos)),
            'ejeX'       => 'Número de labores',
            'ejeY'       => 'Grupo de labor',
            'formato'    => 'entero',
        ];

        if ($filtros['estado'] === 'T') {
            $grafica['colores'] = ['#41a867', '#c3ccd5'];
        }

        return [
            'kpis' => [
                $kpiFilas,
                ['etiqueta' => 'Grupos de labor', 'valor' => count($grupos) - ($sinLabor > 0 ? 1 : 0), 'formato' => 'entero', 'icono' => 'bi bi-collection',
                    'ayuda' => $sinLabor === 0 ? 'Todas agrupadas' : self::n($sinLabor) . ($sinLabor === 1 ? ' labor sin grupo' : ' labores sin grupo')],
                ['etiqueta' => 'Sin concepto de nómina', 'valor' => $conteo['sinConcepto'], 'formato' => 'entero', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $total === 0 ? 'Sin labores' : ($conteo['sinConcepto'] === 0 ? 'Todas con concepto' : self::pct($conteo['sinConcepto'], $total) . ' de las labores')],
                ['etiqueta' => 'Registran por lote', 'valor' => $conteo['porLote'], 'formato' => 'entero', 'icono' => 'bi bi-grid-3x3-gap',
                    'ayuda' => self::n($conteo['racimo']) . ' con racimo · ' . self::n($conteo['bascula']) . ' con báscula'],
            ],
            'grafica'  => $filas === [] ? null : $grafica,
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Grupo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'codigo', 'titulo' => 'Código', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'desCorta', 'titulo' => 'Descripción corta', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'clase', 'titulo' => 'Clase', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'signo', 'titulo' => 'Signo', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'concepto', 'titulo' => 'Concepto nómina', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'ciclos', 'titulo' => 'Ciclos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'tarea', 'titulo' => 'Tarea', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'parametros', 'titulo' => 'Parámetros', 'tipo' => 'etiquetas', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'    => $filas,
            'totales'  => [],
            'agrupar'  => 'grupo',
            'avisos'   => $avisos,
        ];
    }
}
