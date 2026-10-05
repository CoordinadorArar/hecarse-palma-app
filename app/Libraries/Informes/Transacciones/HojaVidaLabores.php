<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class HojaVidaLabores extends Informe
{
    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const NOMBRES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private const COLORES = ['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500', '#5b8def'];

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT YEAR(a.fecha) AS anio FROM aTransaccion a
                               WHERE a.empresa = ? AND a.anulado = 0 AND EXISTS (SELECT 1 FROM aTransaccionTercero c WHERE c.empresa = a.empresa AND c.tipo = a.tipo AND c.numero = a.numero)
                               ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $grupos  = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? ''), 'ayuda' => 'Por fecha de la transacción.', 'opciones' => $anios],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'nivel', 'etiqueta' => 'Nivel', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'grupo',
                'opciones' => [['valor' => 'grupo', 'texto' => 'Grupo de labor'], ['valor' => 'labor', 'texto' => 'Labor']]],
            ['clave' => 'metrica', 'etiqueta' => 'Métrica', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'valor',
                'opciones' => [['valor' => 'valor', 'texto' => 'Valor'], ['valor' => 'trabajadores', 'texto' => 'Trabajadores'], ['valor' => 'cantidad', 'texto' => 'Cantidad']]],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
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

    public function validar(array $entrada): array|string
    {
        $v = parent::validar($entrada);

        return is_array($v) && $v['metrica'] === 'cantidad' && $v['nivel'] === 'grupo' ? 'La cantidad solo se puede ver por labor: los grupos de labor mezclan unidades.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $anio    = (int) $filtros['anio'];
        $nivel   = $filtros['nivel'];
        $metrica = $filtros['metrica'];
        $mesAct  = $anio < (int) date('Y') ? 12 : ($anio === (int) date('Y') ? (int) date('n') : 0);

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $anio . '-01-01', $anio . '-12-31'];

        foreach (['finca' => 'c.finca', 'grupo' => 'd.grupo'] as $clave => $campo) {
            if ($filtros[$clave] !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $filtros[$clave];
            }
        }

        $sql = "SELECT mes, grupo, grupoNombre, sinGrupo, labor, laborNombre, uMedida, tercero, SUM(valor) AS valor, SUM(cantidad) AS cantidad, MAX(fecha) AS ultima
                  FROM (SELECT MONTH(a.fecha) AS mes, CONVERT(date, a.fecha) AS fecha, LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo, CAST(ISNULL(g.descripcion, '') AS varchar(150)) AS grupoNombre,
                               CASE WHEN g.codigo IS NULL THEN 1 ELSE 0 END AS sinGrupo, LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre,
                               LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, LTRIM(RTRIM(c.tercero)) AS tercero,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                     LEFT JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                         OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM aGrupoNovedad WHERE empresa = d.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(d.grupo))) g
                         WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}) x
                 GROUP BY mes, grupo, grupoNombre, sinGrupo, labor, laborNombre, uMedida, tercero";

        $datos   = $this->db->query($sql, $binds)->getResultArray();
        $grupos  = [];
        $labores = [];
        $meses   = [];
        $trab    = [];
        $total   = 0.0;
        $ultima  = '';

        foreach ($datos as $d) {
            $m     = (int) $d['mes'];
            $valor = (float) $d['valor'];
            $cant  = (float) $d['cantidad'];
            $t     = $d['tercero'];
            $sin   = $d['grupo'] === '' || (int) $d['sinGrupo'] === 1;
            $grupo = $sin ? 'Sin grupo' : $d['grupo'] . (trim($d['grupoNombre']) === '' ? '' : ' — ' . trim($d['grupoNombre']));
            $labor = $d['labor'] . ' — ' . (trim($d['laborNombre']) === '' ? 'Sin descripción en el maestro' : trim($d['laborNombre']));

            $g = &$grupos[$grupo];
            $g['valor']          = ($g['valor'] ?? 0) + $valor;
            $g['meses'][$m]      = ($g['meses'][$m] ?? 0) + $valor;
            $g['trabM'][$m][$t]  = true;
            $g['trab'][$t]       = true;
            $g['labores'][$labor] = true;
            $g['unidades'][$d['uMedida']] = true;
            $g['sin']            = $sin;
            unset($g);

            $l = &$labores[$grupo][$labor];
            $l['valor']         = ($l['valor'] ?? 0) + $valor;
            $l['cant']          = ($l['cant'] ?? 0) + $cant;
            $l['meses'][$m]     = ($l['meses'][$m] ?? 0) + $valor;
            $l['cantM'][$m]     = ($l['cantM'][$m] ?? 0) + $cant;
            $l['trabM'][$m][$t] = true;
            $l['trab'][$t]      = true;
            $l['uMedida']       = $d['uMedida'];
            unset($l);

            $meses[$m]['valor']          = ($meses[$m]['valor'] ?? 0) + $valor;
            $meses[$m]['trab'][$t]       = true;
            $meses[$m]['labores'][$labor] = true;
            $trab[$t]                    = true;
            $total                      += $valor;
            $ultima                      = max($ultima, (string) $d['ultima']);
        }

        $total    = round($total, 2);
        $porValor = static function (array $lista) {
            uksort($lista, static fn ($x, $y) => [$x === 'Sin grupo', -$lista[$x]['valor']] <=> [$y === 'Sin grupo', -$lista[$y]['valor']]);

            return $lista;
        };
        $grupos = $porValor($grupos);
        $lista  = static fn ($set) => implode(' · ', array_filter(array_map('strval', array_keys($set)), static fn ($u) => $u !== ''));
        $mesesV = static fn (array $mv) => count(array_filter($mv, static fn ($v, $m) => $m <= $mesAct && round($v, 2) != 0, ARRAY_FILTER_USE_BOTH));

        $celda = static function (array $e, int $m) use ($metrica, $mesAct) {
            if ($m > $mesAct) {
                return null;
            }

            $v = match ($metrica) {
                'valor'        => round($e['meses'][$m] ?? 0, 2),
                'trabajadores' => count($e['trabM'][$m] ?? []),
                default        => round($e['cantM'][$m] ?? 0, 2),
            };

            return $v == 0 ? null : $v;
        };

        $fila = static function (array $e, string $etiqueta, array $extra) use ($celda, $metrica, $total) {
            $f   = ['etiqueta' => $etiqueta] + $extra;
            $neg = [];

            for ($m = 1; $m <= 12; $m++) {
                $f['m' . $m] = $celda($e, $m);
                if ($f['m' . $m] !== null && $f['m' . $m] < 0) {
                    $neg[] = $m;
                }
            }

            $f['total'] = match ($metrica) {
                'valor'        => round($e['valor'], 2),
                'trabajadores' => count($e['trab']),
                default        => round($e['cant'], 2),
            };

            if ($metrica === 'valor') {
                $f['pct'] = $total == 0 ? 0 : $e['valor'] / $total;
            }

            if ($metrica !== 'trabajadores') {
                $f['trabajadores'] = count($e['trab']);
            }

            return $f + ['negativo' => $metrica === 'valor' && $f['total'] < 0, 'negM' => implode(',', $neg)];
        };

        $filas = [];

        foreach ($grupos as $grupo => $g) {
            $aux = [
                'sinGrupo'          => $g['sin'],
                'pctGrupo'          => $total == 0 ? 0 : $g['valor'] / $total,
                'laboresGrupo'      => count($g['labores']),
                'trabajadoresGrupo' => count($g['trab']),
                'mesesGrupo'        => $mesesV($g['meses']),
                'unidadesGrupo'     => $lista($g['unidades']),
            ];

            if ($nivel === 'grupo') {
                $filas[] = $fila($g, $grupo, []) + ['labores' => count($g['labores']), 'unidades' => $lista($g['unidades'])] + $aux;

                continue;
            }

            foreach ($porValor($labores[$grupo]) as $labor => $l) {
                $filas[] = $fila($l, $labor, ['grupo' => $grupo, 'uMedida' => $l['uMedida']]) + $aux;
            }
        }

        $vacio    = $datos === [];
        $porGrupo = $filtros['grupo'] !== '';
        $series   = $porGrupo ? ($labores[array_key_first($grupos)] ?? []) : $grupos;
        uasort($series, static fn ($x, $y) => $y['valor'] <=> $x['valor']);
        $top      = array_slice($series, 0, 6, true);
        $otros    = array_slice($series, 6, null, true);
        $mesValor = static fn (array $e, int $m) => $m > $mesAct || round($e['meses'][$m] ?? 0, 2) == 0 ? null : round($e['meses'][$m], 2);
        $serie    = [];

        foreach ($top as $nombre => $e) {
            $serie[] = ['nombre' => (string) $nombre, 'datos' => array_map(static fn ($m) => $mesValor($e, $m), range(1, 12))];
        }

        if ($otros !== []) {
            $resto = ['meses' => []];
            foreach ($otros as $e) {
                foreach ($e['meses'] as $m => $v) {
                    $resto['meses'][$m] = ($resto['meses'][$m] ?? 0) + $v;
                }
            }
            $serie[] = ['nombre' => 'Otros', 'datos' => array_map(static fn ($m) => $mesValor($resto, $m), range(1, 12))];
        }

        $mayorMes = null;

        foreach ($meses as $m => $d) {
            if ($mayorMes === null || $d['valor'] > $meses[$mayorMes]['valor']) {
                $mayorMes = $m;
            }
        }

        $mayor     = array_key_first($series);
        $vMayorMes = $mayorMes === null ? 0 : $meses[$mayorMes]['valor'];
        $vMayor    = $mayor === null ? 0 : $series[$mayor]['valor'];
        $pct       = static fn (float $v) => self::n($total == 0 ? 0 : $v / $total * 100, 1) . ' % del año';
        $fincas    = array_column((new LotesPorFincas($this->empresa))->opciones('finca', []), 'texto', 'valor');
        $promedio  = $meses === [] ? 0 : array_sum(array_map(static fn ($d) => count($d['trab']), $meses)) / count($meses);
        $avisos    = [];

        if ($vacio) {
            $avisos[] = 'No hay labores registradas en ' . $anio . ' con los filtros seleccionados.';
        } elseif ($anio === (int) date('Y')) {
            $avisos[] = 'Año en curso: datos hasta ' . date('d/m/Y', strtotime($ultima)) . '; los meses siguientes se muestran atenuados.';
        }

        $sinGrupo = count($labores['Sin grupo'] ?? []);

        if ($sinGrupo > 0) {
            $avisos[] = self::plural($sinGrupo, 'labor sin grupo válido; se agrupa en "Sin grupo".', 'labores sin grupo válido; se agrupan en "Sin grupo".');
        }

        $tipo     = ['valor' => 'moneda', 'trabajadores' => 'entero', 'cantidad' => 'decimal'][$metrica];
        $esValor  = $metrica === 'valor';
        $columna  = static fn (string $clave, string $titulo, string $tipo, string $alineacion = 'derecha', bool $total = false, array $extra = []) => ['clave' => $clave, 'titulo' => $titulo, 'tipo' => $tipo, 'alineacion' => $alineacion, 'total' => $total] + $extra;
        $columnas = [$columna('etiqueta', $nivel === 'grupo' ? 'Grupo de labor' : 'Labor', 'texto', 'izquierda')];

        if ($nivel === 'labor') {
            $columnas[] = $columna('grupo', 'Grupo de labor', 'texto', 'izquierda');
            $columnas[] = $columna('uMedida', 'U. medida', 'texto', 'centro');
        }

        $totales = [];

        for ($m = 1; $m <= 12; $m++) {
            $columnas[] = $columna('m' . $m, self::MESES[$m], $tipo, 'derecha', $esValor && $m <= $mesAct, $m > $mesAct ? ['futuro' => true, 'ayuda' => 'Mes no transcurrido'] : []);
            if ($esValor && $m <= $mesAct) {
                $totales['m' . $m] = round($meses[$m]['valor'] ?? 0, 2);
            }
        }

        $columnas[] = $columna('total', $metrica === 'trabajadores' ? 'Año (distintos)' : 'Total', $tipo, 'derecha', $esValor);

        if ($esValor) {
            $columnas[] = $columna('pct', '% del total', 'porcentaje', 'derecha', true);
            $totales   += ['total' => $total, 'pct' => $total == 0 ? 0 : 1];
        }

        if ($metrica !== 'trabajadores') {
            $columnas[] = $columna('trabajadores', 'Trabajadores', 'entero', 'derecha', false, ['ayuda' => 'Distintos en el año; no se suman']);
        }

        $columnas = array_merge($columnas, [
            $columna('negativo', 'Total negativo', 'texto', 'centro'),
            $columna('negM', 'Meses con neto negativo', 'texto'),
        ], $nivel === 'grupo' ? [
            $columna('labores', 'N.º de labores', 'entero'),
            $columna('unidades', 'Unidades de medida', 'texto'),
        ] : [], [
            $columna('sinGrupo', 'Sin grupo', 'texto', 'centro'),
            $columna('pctGrupo', '% del grupo sobre el año', 'porcentaje'),
            $columna('laboresGrupo', 'Labores del grupo', 'entero'),
            $columna('trabajadoresGrupo', 'Trabajadores del grupo', 'entero'),
            $columna('mesesGrupo', 'Meses con valor del grupo', 'entero'),
            $columna('unidadesGrupo', 'Unidades del grupo', 'texto'),
        ]);

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total del año', 'valor' => $vacio ? null : $total, 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $vacio ? '' : self::plural(count($meses), 'mes con labor', 'meses con labor') . ' · ' . ($filtros['finca'] === '' ? 'Todas las fincas' : ($fincas[$filtros['finca']] ?? $filtros['finca']))],
                ['etiqueta' => 'Mes de mayor costo', 'valor' => $mayorMes === null ? '—' : self::NOMBRES[$mayorMes], 'formato' => 'texto', 'icono' => 'bi bi-calendar-event',
                    'ayuda' => $mayorMes === null ? '' : '$ ' . self::n($vMayorMes) . ' · ' . $pct($vMayorMes)],
                ['etiqueta' => 'Trabajadores distintos en el año', 'valor' => $vacio ? null : count($trab), 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $vacio ? '' : 'Promedio ' . self::n($promedio) . ' por mes'],
                ['etiqueta' => $porGrupo ? 'Labor de mayor costo' : 'Grupo de mayor costo', 'valor' => $mayor ?? '—', 'formato' => 'texto', 'icono' => 'bi bi-trophy',
                    'ayuda' => $mayor === null ? '' : '$ ' . self::n($vMayor) . ' · ' . $pct($vMayor)],
            ],
            'grafica' => $vacio ? null : [
                'tipo'       => 'bar',
                'apilada'    => count($serie) > 1,
                'horizontal' => false,
                'titulo'     => $porGrupo ? 'Valor mensual por labor · ' . array_key_first($grupos) : 'Valor mensual por grupo de labor',
                'categorias' => array_values(self::MESES),
                'series'     => $serie,
                'colores'    => array_merge(array_slice(self::COLORES, 0, count($top)), $otros === [] ? [] : ['#b8c2cc']),
                'detalle'    => array_map(static fn ($m) => [
                    'total'        => round($meses[$m]['valor'] ?? 0, 2),
                    'trabajadores' => count($meses[$m]['trab'] ?? []),
                    'labores'      => count($meses[$m]['labores'] ?? []),
                    'pct'          => $total == 0 ? 0 : ($meses[$m]['valor'] ?? 0) / $total,
                ], range(1, 12)),
                'ejeY'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => $nivel === 'labor' ? 'grupo' : null,
            'avisos'   => $avisos,
        ];
    }
}
