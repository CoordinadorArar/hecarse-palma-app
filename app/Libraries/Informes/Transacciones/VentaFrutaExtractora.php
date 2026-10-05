<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class VentaFrutaExtractora extends Informe
{
    private const NIT = "COALESCE(NULLIF(LTRIM(RTRIM(%1\$s.terceroExtractrora)), ''), NULLIF(LTRIM(RTRIM(%1\$s.empresaExtractora)), ''), NULLIF(LTRIM(RTRIM(%1\$s.planta)), ''))";

    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function filtros(): array
    {
        $extractoras = array_map(
            static fn ($t) => ['valor' => $t['nit'], 'texto' => $t['nit'] . ' — ' . trim($t['nombre'])],
            $this->db->query("SELECT DISTINCT LTRIM(RTRIM(nit)) AS nit, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), ISNULL(descripcion, '')) AS nombre FROM cTercero
                               WHERE empresa = ? AND extractora = 1 AND LTRIM(RTRIM(ISNULL(nit, ''))) <> '' ORDER BY nombre ASC", [$this->empresa])->getResultArray()
        );
        $sinNit = $this->db->query('SELECT TOP 1 1 AS hay FROM aTransaccionBascula b WHERE b.empresa = ? AND b.tipo = ? AND ' . sprintf(self::NIT, 'b') . ' IS NULL', [$this->empresa, 'TLC'])->getRow() !== null;

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de la transacción TLC, no la de báscula.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'extractora', 'etiqueta' => 'Extractora', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las extractoras', 'opciones' => $sinNit ? [...$extractoras, ['valor' => '__SIN__', 'texto' => 'Sin extractora']] : $extractoras],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', []), 'ayuda' => 'Tiquetes cuya TLC corresponde a la finca.'],
            ['clave' => 'agrupar', 'etiqueta' => 'Agrupar por', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'extractora',
                'opciones' => [['valor' => 'extractora', 'texto' => 'Extractora'], ['valor' => 'mes', 'texto' => 'Extractora y mes'], ['valor' => 'finca', 'texto' => 'Extractora y finca']]],
        ];
    }

    private static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    private static function plural(int $n, string $uno, string $varios): string
    {
        return self::n($n) . ' ' . ($n === 1 ? $uno : $varios);
    }

    private static function mes(string $clave, bool $anio = true, bool $completo = false): string
    {
        $nombre = self::MESES[(int) substr($clave, 5, 2)];

        return ($completo ? $nombre : substr($nombre, 0, 3)) . ($anio ? ' ' . substr($clave, 0, 4) : '');
    }

    public function validar(array $entrada): array|string
    {
        $v = parent::validar($entrada);

        if (! is_array($v)) {
            return $v;
        }

        if ($v['fechaInicial'] > $v['fechaFinal']) {
            return 'La fecha inicial no puede ser mayor que la final.';
        }

        return $v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year')) ? 'El rango no puede superar un año.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $this->empresa, 'TLC', $filtros['fechaInicial'], $filtros['fechaFinal']];

        if ($filtros['finca'] !== '') {
            $where  .= ' AND EXISTS (SELECT 1 FROM aTransaccionNovedad n WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero AND LTRIM(RTRIM(n.finca)) = ?)';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['extractora'] !== '') {
            $where .= $filtros['extractora'] === '__SIN__' ? ' AND x.nit IS NULL' : ' AND t.nit = ?';
            $binds  = $filtros['extractora'] === '__SIN__' ? $binds : [...$binds, $filtros['extractora']];
        }

        $sql = "SELECT CONVERT(char(7), a.fecha, 120) AS mes, LTRIM(RTRIM(ISNULL(b.tiquete, ''))) AS tiquete, ISNULL(b.pesoNeto, 0) AS neto, ISNULL(b.racimos, 0) AS racimos,
                       ISNULL(x.nit, '') AS nitTiquete, ISNULL(t.nit, '') AS nit, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre, ISNULL(fn.codigo, '') AS finca, ISNULL(fn.nombre, '') AS nombreFinca
                  FROM aTransaccion a
                  JOIN aTransaccionBascula b ON b.empresa = a.empresa AND b.tipo = a.tipo AND b.numero = a.numero
                 CROSS APPLY (SELECT " . sprintf(self::NIT, 'b') . " AS nit) x
                 OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(nit)) AS nit, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.nit OR (LEN(x.nit) > 6 AND LTRIM(RTRIM(nit)) = LEFT(x.nit, LEN(x.nit) - 1)))
                               ORDER BY ISNULL(extractora, 0) DESC, CASE WHEN LTRIM(RTRIM(nit)) = x.nit THEN 0 ELSE 1 END) t
                 OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(n.finca)) AS codigo, LTRIM(RTRIM(ISNULL(f.descripcion, ''))) AS nombre FROM aTransaccionNovedad n
                                LEFT JOIN aFinca f ON f.empresa = n.empresa AND f.codigo = n.finca
                               WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero ORDER BY n.finca) fn
                 WHERE a.empresa = ? AND a.tipo = ? AND a.anulado = 0{$eliminadas} AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}";

        $tlcs  = $this->db->query($sql, $binds)->getResultArray();
        $modo  = $filtros['agrupar'];
        $unid  = [];

        foreach ($tlcs as $i => &$c) {
            $c['neto']    = (int) $c['neto'];
            $c['racimos'] = (int) $c['racimos'];
            $c['nombre']  = trim($c['nombre']);
            $c['unidad']  = $c['tiquete'] === '' ? '#' . $i : $c['tiquete'] . '|' . $c['nitTiquete'];
            $u            = &$unid[$c['unidad']];
            $u['kg']      = ($u['kg'] ?? 0) + $c['neto'];
            $u['racimos'] = ($u['racimos'] ?? 0) + $c['racimos'];
            $u['n']       = ($u['n'] ?? 0) + 1;
            $u['tlc']     = isset($u['tlc']) && $tlcs[$u['tlc']]['neto'] >= $c['neto'] ? $u['tlc'] : $i;
            unset($u);
        }

        unset($c);

        $grupos = [];
        $filas  = [];
        $meses  = [];

        foreach ($tlcs as $i => $c) {
            $u      = $unid[$c['unidad']];
            $cuenta = $u['tlc'] === $i;
            $e      = $c['nit'] !== '' ? $c['nit'] . ' — ' . $c['nombre'] : ($c['nitTiquete'] !== '' ? $c['nitTiquete'] . ' — Extractora no encontrada' : 'Sin extractora');
            $fin    = $c['finca'] === '' ? 'Sin finca' : $c['finca'] . ($c['nombreFinca'] !== '' ? ' — ' . $c['nombreFinca'] : '');
            $conRac = $c['racimos'] > 0 ? $c['neto'] : 0;

            $sumar = static function (?array &$a) use ($c, $u, $cuenta, $conRac) {
                $a['kilos']           = ($a['kilos'] ?? 0) + $c['neto'];
                $a['racimos']         = ($a['racimos'] ?? 0) + $c['racimos'];
                $a['kilosConRacimos'] = ($a['kilosConRacimos'] ?? 0) + $conRac;
                $a['tiquetes']        = ($a['tiquetes'] ?? 0) + ($cuenta ? 1 : 0);
                $a['partidos']        = ($a['partidos'] ?? 0) + ($cuenta && $u['n'] > 1 ? 1 : 0);
                $a['sinRacimos']      = ($a['sinRacimos'] ?? 0) + ($c['neto'] > 0 && $c['racimos'] === 0 ? 1 : 0);
            };

            $sumar($grupos[$e]);
            $sumar($filas[$e][$modo === 'mes' ? $c['mes'] : ($modo === 'finca' ? $fin : '')]);
            $sumar($meses[$c['mes']]);

            $grupos[$e]['serie']              = $c['nit'] !== '' ? $c['nombre'] : ($c['nitTiquete'] !== '' ? 'Extractora no encontrada' : 'Sin extractora');
            $grupos[$e]['sin']                = $c['nit'] === '';
            $grupos[$e]['fincas'][$fin]       = true;
            $grupos[$e]['meses'][$c['mes']]   = true;
            $meses[$c['mes']]['kg'][$e]       = ($meses[$c['mes']]['kg'][$e] ?? 0) + $c['neto'];
        }

        uksort($grupos, static fn ($x, $y) => [$grupos[$x]['sin'], $grupos[$y]['kilos'], $x] <=> [$grupos[$y]['sin'], $grupos[$x]['kilos'], $y]);
        ksort($meses);

        $kilos    = array_sum(array_column($grupos, 'kilos'));
        $racimos  = array_sum(array_column($grupos, 'racimos'));
        $tiquetes = array_sum(array_column($grupos, 'tiquetes'));
        $partidos = array_sum(array_column($grupos, 'partidos'));
        $sinRac   = array_sum(array_column($grupos, 'sinRacimos'));
        $promedio = $racimos > 0 ? array_sum(array_column($grupos, 'kilosConRacimos')) / $racimos : null;
        $kgRac    = static fn ($a) => $a['racimos'] > 0 ? round($a['kilosConRacimos'] / $a['racimos'], 2) : null;
        $salida   = [];

        foreach ($grupos as $e => $g) {
            $sub = $filas[$e];

            if ($modo === 'mes') {
                ksort($sub);
            } else {
                uksort($sub, static fn ($x, $y) => [$sub[$y]['kilos'], $x] <=> [$sub[$x]['kilos'], $y]);
            }

            foreach ($sub as $clave => $f) {
                $kr       = $kgRac($f);
                $salida[] = ($modo === 'extractora' ? ['extractora' => $e] : ['grupo' => $e, $modo => $modo === 'mes' ? self::mes((string) $clave) : (string) $clave]) + [
                    'tiquetes'        => $f['tiquetes'],
                    'racimos'         => $f['racimos'],
                    'kilos'           => $f['kilos'],
                    'toneladas'       => $f['kilos'] / 1000,
                    'kgRacimo'        => $kr,
                    'participacion'   => $kilos > 0 ? $f['kilos'] / $kilos : 0,
                    'kilosConRacimos' => $f['kilosConRacimos'],
                    'sinRacimos'      => $f['sinRacimos'],
                    'atipico'         => $kr !== null && $promedio !== null && abs($kr - $promedio) > 0.2 * $promedio,
                    'partidos'        => $f['partidos'],
                    'sinExtractora'   => $g['sin'],
                    'kgRacimoGrupo'   => $kgRac($g),
                    'tiquetesGrupo'   => $g['tiquetes'],
                    'fincasGrupo'     => count($g['fincas']),
                    'mesesGrupo'      => count($g['meses']),
                    'promedioRango'   => $promedio === null ? null : round($promedio, 2),
                ];
            }
        }

        $avisos = [];

        foreach ([
            [$partidos, 'tiquete registrado en más de una TLC; se cuenta una sola vez.', 'tiquetes registrados en más de una TLC; se cuentan una sola vez.'],
            [$sinRac, 'registro de báscula sin racimos; no entra en el peso promedio.', 'registros de báscula sin racimos; no entran en el peso promedio.'],
            [array_sum(array_map(static fn ($g) => $g['sin'] ? $g['tiquetes'] : 0, $grupos)), 'tiquete con extractora no encontrada en terceros.', 'tiquetes con extractora no encontrada en terceros.'],
        ] as [$n, $uno, $varios]) {
            if ($n > 0) {
                $avisos[] = self::plural($n, $uno, $varios);
            }
        }

        $grafica = null;

        if ($grupos !== [] && count($meses) > 1) {
            $todos = [];

            for ($m = substr($filtros['fechaInicial'], 0, 7); $m <= substr($filtros['fechaFinal'], 0, 7); $m = date('Y-m', strtotime($m . '-01 +1 month'))) {
                $todos[$m] = $meses[$m] ?? ['kg' => [], 'kilos' => 0, 'tiquetes' => 0, 'racimos' => 0, 'kilosConRacimos' => 0];
            }

            $cruza = substr($filtros['fechaInicial'], 0, 4) !== substr($filtros['fechaFinal'], 0, 4);
            $top   = array_slice(array_keys(array_filter($grupos, static fn ($g) => ! $g['sin'])), 0, 6);
            $resto = array_diff(array_keys($grupos), $top);
            $datos = static fn (array $claves) => array_map(static fn ($p) => ($t = round(array_sum(array_intersect_key($p['kg'], array_flip($claves))) / 1000, 2)) > 0 ? $t : null, array_values($todos));
            $grafica = [
                'tipo'       => 'bar',
                'apilada'    => true,
                'horizontal' => false,
                'titulo'     => 'Toneladas vendidas por mes y extractora',
                'categorias' => array_map(static fn ($m) => $cruza ? self::mes($m, false) . ' ' . substr($m, 2, 2) : self::mes($m, false), array_keys($todos)),
                'series'     => [...array_map(static fn ($e) => ['nombre' => $grupos[$e]['serie'], 'datos' => $datos([$e])], $top), ...($resto !== [] ? [['nombre' => 'Otras', 'datos' => $datos($resto)]] : [])],
                'colores'    => [...array_slice(['#2f7a4b', '#41a867', '#7cc49a', '#4a5b6c', '#f0a500', '#5b8def'], 0, count($top)), ...($resto !== [] ? ['#9aa5b1'] : [])],
                'detalle'    => array_map(static fn ($p) => ['total' => round($p['kilos'] / 1000, 2), 'tiquetes' => $p['tiquetes'], 'racimos' => $p['racimos'], 'pesoPromedio' => $kgRac($p)], array_values($todos)),
                'ejeY'       => 'Toneladas',
                'formato'    => 'decimal',
            ];
        } elseif ($grupos !== []) {
            $grafica = [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Toneladas por extractora — ' . self::mes((string) array_key_first($meses), true, true),
                'categorias' => array_values(array_column($grupos, 'serie')),
                'series'     => [['nombre' => 'Toneladas', 'datos' => array_values(array_map(static fn ($g) => round($g['kilos'] / 1000, 2), $grupos))]],
                'colores'    => ['#41a867'],
                'detalle'    => array_values(array_map(static fn ($g) => ['participacion' => $kilos > 0 ? $g['kilos'] / $kilos : 0, 'tiquetes' => $g['tiquetes'], 'racimos' => $g['racimos'], 'pesoPromedio' => $kgRac($g)], $grupos)),
                'ejeX'       => 'Toneladas',
                'formato'    => 'decimal',
            ];
        }

        $activas   = (int) $this->db->query("SELECT COUNT(DISTINCT LTRIM(RTRIM(nit))) AS n FROM cTercero WHERE empresa = ? AND extractora = 1 AND LTRIM(RTRIM(ISNULL(nit, ''))) <> ''", [$this->empresa])->getRow()->n;
        $principal = $grupos === [] ? null : $grupos[array_key_first($grupos)];
        $columna   = static fn (string $clave, string $titulo, string $tipo, string $alineacion = 'derecha', bool|string $total = false, array $extra = []) => ['clave' => $clave, 'titulo' => $titulo, 'tipo' => $tipo, 'alineacion' => $alineacion, 'total' => $total] + $extra;

        $respuesta = [
            'kpis' => [
                ['etiqueta' => 'Toneladas vendidas', 'valor' => $grupos === [] ? null : $kilos / 1000, 'formato' => 'decimal', 'icono' => 'bi bi-truck',
                    'ayuda' => $grupos === [] ? '' : self::n($kilos) . ' kg netos · ' . self::n($racimos) . ' racimos'],
                ['etiqueta' => 'Tiquetes despachados', 'valor' => $grupos === [] ? null : $tiquetes, 'formato' => 'entero', 'icono' => 'bi bi-ticket-perforated',
                    'ayuda' => $tiquetes > 0 ? 'Promedio ' . self::n($kilos / 1000 / $tiquetes, 2) . ' t por tiquete' . ($partidos > 0 ? ' · ' . self::plural($partidos, 'repetido en varias TLC', 'repetidos en varias TLC') : '') : ''],
                ['etiqueta' => 'Extractoras atendidas', 'valor' => $grupos === [] ? null : count($grupos), 'formato' => 'entero', 'icono' => 'bi bi-building',
                    'ayuda' => $grupos === [] ? '' : 'De ' . self::plural($activas, 'extractora activa', 'extractoras activas')],
                ['etiqueta' => 'Extractora principal', 'valor' => $principal === null ? null : ($kilos > 0 ? $principal['kilos'] / $kilos : 0), 'formato' => 'porcentaje', 'icono' => 'bi bi-trophy',
                    'ayuda' => $principal === null ? '' : ($filtros['extractora'] !== '' ? 'Filtro aplicado' : $principal['serie'] . ' · ' . self::n($principal['kilos'] / 1000, 2) . ' t')],
            ],
            'grafica'  => $grafica,
            'columnas' => [
                ...($modo === 'extractora' ? [$columna('extractora', 'Extractora', 'texto', 'izquierda')] : [$columna('grupo', 'Extractora', 'texto', 'izquierda')]),
                ...($modo === 'mes' ? [$columna('mes', 'Mes', 'texto', 'centro')] : ($modo === 'finca' ? [$columna('finca', 'Finca', 'texto', 'izquierda')] : [])),
                $columna('tiquetes', 'Tiquetes', 'entero', 'derecha', true),
                $columna('racimos', 'Racimos', 'entero', 'derecha', true),
                $columna('kilos', 'Kg netos', 'entero', 'derecha', true, ['ayuda' => 'Peso neto de báscula.']),
                $columna('toneladas', 'Toneladas', 'decimal', 'derecha', true),
                $columna('kgRacimo', 'Kg/racimo', 'decimal', 'derecha', 'ponderado', ['numerador' => 'kilosConRacimos', 'denominador' => 'racimos', 'ayuda' => 'Kg netos / racimos; excluye registros de báscula sin racimos.']),
                $columna('participacion', '% part.', 'porcentaje', 'derecha', true, ['ayuda' => 'Sobre el total de kilos del rango filtrado.']),
                $columna('kilosConRacimos', 'Kilos con racimos', 'entero'),
                $columna('sinRacimos', 'Sin racimos', 'entero'),
                $columna('atipico', 'Atípico', 'texto', 'centro'),
                $columna('partidos', 'Partidos', 'entero'),
                $columna('sinExtractora', 'Sin extractora', 'texto', 'centro'),
                $columna('kgRacimoGrupo', 'Kg/racimo extractora', 'decimal'),
                $columna('tiquetesGrupo', 'Tiquetes extractora', 'entero'),
                $columna('fincasGrupo', 'Fincas extractora', 'entero'),
                $columna('mesesGrupo', 'Meses extractora', 'entero'),
                $columna('promedioRango', 'Kg/racimo rango', 'decimal'),
            ],
            'filas'   => $salida,
            'totales' => [
                'tiquetes'        => $tiquetes,
                'racimos'         => $racimos,
                'kilos'           => $kilos,
                'toneladas'       => $kilos / 1000,
                'kgRacimo'        => $promedio === null ? null : round($promedio, 2),
                'participacion'   => $kilos > 0 ? 1 : 0,
                'kilosConRacimos' => array_sum(array_column($grupos, 'kilosConRacimos')),
            ],
            'avisos' => $avisos,
        ];

        return $modo === 'extractora' ? $respuesta : $respuesta + ['agrupar' => 'grupo'];
    }
}
