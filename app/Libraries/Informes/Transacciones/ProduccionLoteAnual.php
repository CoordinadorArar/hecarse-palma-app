<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class ProduccionLoteAnual extends Informe
{
    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const MEDIDAS = ['kilos' => 'Kilos', 'toneladas' => 'Toneladas', 'racimos' => 'Racimos', 'tonha' => 'Ton/ha'];

    public function filtros(): array
    {
        $variedades = array_map(
            static fn ($v) => ['valor' => $v['codigo'], 'texto' => $v['codigo'] . ' — ' . trim($v['descripcion'])],
            $this->db->query("SELECT RTRIM(codigo) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aVariedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT [año] AS anio FROM aTransaccion WHERE empresa = ? AND tipo = 'TLC' AND anulado = 0 AND [año] > 0 ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? ''), 'ayuda' => 'Año de cosecha (fecha de novedad).', 'opciones' => $anios],
            ['clave' => 'medida', 'etiqueta' => 'Medida', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'kilos', 'opciones' => array_map(static fn ($v, $t) => ['valor' => $v, 'texto' => $t], array_keys(self::MEDIDAS), self::MEDIDAS)],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'variedad', 'etiqueta' => 'Variedad', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las variedades', 'opciones' => $variedades],
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

    public function consultar(array $filtros): array
    {
        $anio    = (int) $filtros['anio'];
        $previo  = $anio - 1;
        $medida  = $filtros['medida'];
        $nombre  = self::MEDIDAS[$medida];
        $entero  = in_array($medida, ['kilos', 'racimos'], true);
        $tonha   = $medida === 'tonha';
        $base    = $medida === 'racimos' ? 'racimos' : 'kilos';

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $previo . '-01-01', $anio . '-12-31'];

        foreach (['LTRIM(RTRIM(k.finca))' => $filtros['finca'], 'RTRIM(k.variedad)' => $filtros['variedad']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND {$campo} = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, YEAR(fecha) AS anio, MONTH(fecha) AS mes, SUM(racimos) AS racimos, SUM(kilos) AS kilos
                  FROM (SELECT LTRIM(RTRIM(k.codigo)) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(ISNULL(k.finca, ''))) AS finca,
                               CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS fincaNombre, CAST(ISNULL(v.descripcion, '') AS varchar(100)) AS variedad,
                               ISNULL(k.[añoSiembra], 0) AS anioSiembra, ISNULL(k.hNetas, 0) AS hNetas, e.fecha,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(b.racimos, 0) AS racimos, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * t.kilos AS kilos
                          FROM aTransaccionNovedad b
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                         CROSS APPLY (SELECT SUM(ISNULL(c.cantidad, 0)) AS kilos, MIN(c.fechaNovedad) AS fechaNovedad
                                        FROM aTransaccionTercero c
                                        JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                                       WHERE c.empresa = b.empresa AND c.tipo = b.tipo AND c.numero = b.numero AND c.registroNovedad = b.registro
                                      HAVING COUNT(*) > 0) t
                         CROSS APPLY (SELECT CONVERT(date, ISNULL(t.fechaNovedad, b.fecha)) AS fecha) e
                          JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                          LEFT JOIN aFinca i ON LTRIM(RTRIM(i.codigo)) = LTRIM(RTRIM(k.finca)) AND i.empresa = k.empresa
                         OUTER APPLY (SELECT TOP 1 descripcion FROM aVariedad WHERE empresa = k.empresa AND codigo = k.variedad) v
                         WHERE b.empresa = ? AND e.fecha BETWEEN ? AND ?{$where}) x
                 GROUP BY lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, YEAR(fecha), MONTH(fecha)";

        $lotes  = [];
        $mesesA = [];
        $lineaP = array_fill(1, 12, 0.0);
        $hayP   = false;

        foreach ($this->db->query($sql, $binds)->getResultArray() as $c) {
            $mes = (int) $c['mes'];
            $l   = &$lotes[$c['finca'] . '|' . $c['lote']];
            $l['codigo']      = $c['lote'];
            $l['fincaCodigo'] = $c['finca'];
            $l['finca']       = $c['finca'] . ' — ' . trim($c['fincaNombre']);
            $l['lote']        = $c['lote'] . ' — ' . trim($c['loteNombre']);
            $l['variedad']    = trim($c['variedad']);
            $l['siembra']     = (int) $c['anioSiembra'];
            $l['hNetas']      = (float) $c['hNetas'];

            if ((int) $c['anio'] === $anio) {
                $l['act'][$mes]['kilos']   = ($l['act'][$mes]['kilos'] ?? 0) + (float) $c['kilos'];
                $l['act'][$mes]['racimos'] = ($l['act'][$mes]['racimos'] ?? 0) + (int) $c['racimos'];
                $mesesA[$mes] = true;
            } else {
                $l['ant'][$mes]['kilos']   = ($l['ant'][$mes]['kilos'] ?? 0) + (float) $c['kilos'];
                $l['ant'][$mes]['racimos'] = ($l['ant'][$mes]['racimos'] ?? 0) + (int) $c['racimos'];
                $lineaP[$mes] += (float) $c['kilos'] / 1000;
                $hayP = true;
            }

            unset($l);
        }

        $m = $anio === (int) date('Y') ? ($mesesA === [] ? 0 : max(array_keys($mesesA))) : 12;

        $valor = static function (float $kg, int $rac, float $h) use ($medida) {
            return match ($medida) {
                'kilos'     => round($kg, 2),
                'toneladas' => round($kg / 1000, 2),
                'racimos'   => $rac,
                default     => $h > 0 ? round($kg / 1000 / $h, 2) : null,
            };
        };

        $lotes = array_filter($lotes, static function ($l) use ($m) {
            foreach (array_merge(array_keys($l['act'] ?? []), array_keys($l['ant'] ?? [])) as $mes) {
                if ($mes <= $m) {
                    return true;
                }
            }

            return false;
        });

        uasort($lotes, static fn ($x, $y) => [$x['finca'], $x['siembra'], $x['codigo']] <=> [$y['finca'], $y['siembra'], $y['codigo']]);

        $haFinca = [];

        foreach ($lotes as $l) {
            $haFinca[$l['fincaCodigo']] = ($haFinca[$l['fincaCodigo']] ?? false) || $l['hNetas'] <= 0;
        }

        $filas   = [];
        $mapa    = [];
        $detalle = [];
        $lineaA  = array_fill(1, 12, 0.0);

        foreach ($lotes as $l) {
            $h      = $l['hNetas'];
            $kg     = 0.0;
            $rac    = 0;
            $kgAnt  = 0.0;
            $racAnt = 0;
            $hayAnt = false;
            $sinC   = false;
            $fila   = ['finca' => $l['finca'], 'lote' => $l['lote'], 'variedad' => $l['variedad'], 'anioSiembra' => $l['siembra'] === 0 ? '' : (string) $l['siembra'], 'hNetas' => $h];
            $aux    = [];
            $calor  = [];
            $tonHaM = [];

            for ($i = 1; $i <= 12; $i++) {
                $k = (float) ($l['act'][$i]['kilos'] ?? 0);
                $r = (int) ($l['act'][$i]['racimos'] ?? 0);

                if ($i > $m) {
                    $fila['m' . $i]      = null;
                    $aux['tonConHa' . $i] = 0;
                    $calor[]  = -2;
                    $tonHaM[] = null;

                    continue;
                }

                $kg  += $k;
                $rac += $r;
                $sinC = $sinC || $k == 0;
                $lineaA[$i] += $k / 1000;
                $fila['m' . $i]       = $valor($k, $r, $h);
                $aux['tonConHa' . $i] = $h > 0 ? $k / 1000 : 0;
                $calor[]  = $k == 0 ? -1 : $fila['m' . $i];
                $tonHaM[] = $h > 0 ? round($k / 1000 / $h, 2) : null;

                if (isset($l['ant'][$i])) {
                    $hayAnt  = true;
                    $kgAnt  += (float) $l['ant'][$i]['kilos'];
                    $racAnt += (int) $l['ant'][$i]['racimos'];
                }
            }

            $bAct = $base === 'kilos' ? $kg : $rac;
            $bAnt = $base === 'kilos' ? $kgAnt : $racAnt;
            $proy = $h > 0 && $m > 0 ? $kg / 1000 / $h / $m * 12 : null;

            $filas[] = $fila + [
                'total'       => $valor($kg, $rac, $h),
                'anterior'    => $hayAnt ? $valor($kgAnt, $racAnt, $h) : null,
                'variacion'   => $bAnt > 0 ? round(($bAct - $bAnt) / $bAnt, 4) : null,
                'proyeccion'  => $proy === null ? null : round($proy, 2),
                'baseDif'     => $bAct - $bAnt,
                'baseAnt'     => $bAnt,
                'toneladas'   => $kg / 1000,
                'hNetasPeso'  => $h > 0 ? $h : 0,
                'hNetasAct'   => $h > 0 && isset($l['act']) ? $h : 0,
                'hNetasAnt'   => $h > 0 && $hayAnt ? $h : 0,
                'tonConHa'    => $h > 0 ? $kg / 1000 : 0,
                'tonConHaAnt' => $h > 0 ? $kgAnt / 1000 : 0,
            ] + $aux + [
                'proyConHa'   => $proy === null ? 0 : $proy * $h,
                'sinCosecha'  => $sinC,
                'sinHaFinca'  => $haFinca[$l['fincaCodigo']],
                'kgAnt'       => $kgAnt,
            ];

            if (isset($l['act'])) {
                $mapa[] = [
                    'orden'   => [$l['siembra'], $l['codigo'], $l['finca']],
                    'serie'   => ['nombre' => $l['codigo'], 'datos' => $calor],
                    'detalle' => ['lote' => $l['lote'], 'finca' => $l['finca'], 'siembra' => $l['siembra'] === 0 ? '' : (string) $l['siembra'], 'tonHa' => $tonHaM],
                ];
            }
        }

        usort($mapa, static fn ($x, $y) => $x['orden'] <=> $y['orden']);
        $detalle = array_column($mapa, 'detalle');
        $mapa    = array_column($mapa, 'serie');

        $suma   = static fn (string $c) => array_sum(array_column($filas, $c));
        $haAct  = $suma('hNetasAct');
        $div    = static fn (float $a, float $b, int $d = 2) => $b > 0 ? round($a / $b, $d) : null;
        $kgAct  = $suma('toneladas') * 1000;
        $kgAnt  = $suma('kgAnt');
        $tonHa  = $div($suma('tonConHa'), $haAct);
        $proyT  = $tonHa !== null && $m > 0 ? round($suma('tonConHa') / $haAct / $m * 12, 2) : null;
        $vacio  = $filas === [];
        $hayAnt = array_filter(array_column($filas, 'anterior'), static fn ($v) => $v !== null) !== [];
        $sinHa  = count(array_filter($filas, static fn ($f) => $f['hNetas'] <= 0));
        $sinC   = count(array_filter($filas, static fn ($f) => $f['sinCosecha']));
        $varKg  = $kgAnt > 0 ? round(($kgAct - $kgAnt) / $kgAnt, 4) : null;
        $rango  = $m <= 1 ? self::MESES[max($m, 1)] : 'Ene–' . self::MESES[$m];
        $tipo   = $entero ? 'entero' : 'decimal';
        $avisos = [];

        if ($sinHa > 0) {
            $avisos[] = self::plural($sinHa, 'lote sin Ha netas: no entra en Ton/ha ni en la proyección.', 'lotes sin Ha netas: no entran en Ton/ha ni en la proyección.');
        }

        if ($sinC > 0) {
            $avisos[] = self::plural($sinC, 'lote con meses sin cosecha dentro del periodo (marcados en la tabla y en ámbar en el mapa).', 'lotes con meses sin cosecha dentro del periodo (marcados en la tabla y en ámbar en el mapa).');
        }

        if (! $vacio && $kgAnt <= 0) {
            $avisos[] = 'Sin producción en ' . $previo . ': no se calcula variación.';
        }

        $totales = ['hNetas' => round($suma('hNetas'), 2)];
        $columnas = [
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
        ];

        for ($i = 1; $i <= 12; $i++) {
            $columnas[] = ['clave' => 'm' . $i, 'titulo' => self::MESES[$i], 'tipo' => $tipo, 'alineacion' => 'derecha']
                + ($i > $m ? ['total' => false, 'futuro' => true, 'ayuda' => 'Mes no transcurrido'] : ($tonha ? ['total' => 'ponderado', 'numerador' => 'tonConHa' . $i, 'denominador' => 'hNetasAct'] : ['total' => true]));
            $totales['m' . $i] = $i > $m ? null : ($tonha ? $div($suma('tonConHa' . $i), $haAct) : round($medida === 'toneladas' ? $lineaA[$i] : $suma('m' . $i), 2));
        }

        $columnas = array_merge($columnas, [
            ['clave' => 'total', 'titulo' => 'Total año', 'tipo' => $tipo, 'alineacion' => 'derecha'] + ($tonha ? ['total' => 'ponderado', 'numerador' => 'tonConHa', 'denominador' => 'hNetasAct'] : ['total' => true]),
            ['clave' => 'anterior', 'titulo' => 'Año anterior', 'tipo' => $tipo, 'alineacion' => 'derecha', 'ayuda' => 'Mismo rango de meses de ' . $previo] + ($tonha ? ['total' => 'ponderado', 'numerador' => 'tonConHaAnt', 'denominador' => 'hNetasAnt'] : ['total' => true]),
            ['clave' => 'variacion', 'titulo' => 'Var. %', 'tipo' => 'variacion', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'baseDif', 'denominador' => 'baseAnt'],
            ['clave' => 'proyeccion', 'titulo' => 'Proyección t/ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'proyConHa', 'denominador' => 'hNetasAct'],
            ['clave' => 'baseDif', 'titulo' => 'Base diferencia', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'baseAnt', 'titulo' => 'Base anterior', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'toneladas', 'titulo' => 'Toneladas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasAct', 'titulo' => 'Ha netas con producción', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasAnt', 'titulo' => 'Ha netas con producción año anterior', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasPeso', 'titulo' => 'Ha netas peso', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tonConHa', 'titulo' => 'Ton con Ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tonConHaAnt', 'titulo' => 'Ton con Ha anterior', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $columnas[] = ['clave' => 'tonConHa' . $i, 'titulo' => 'Ton con Ha ' . self::MESES[$i], 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false];
        }

        $columnas = array_merge($columnas, [
            ['clave' => 'proyConHa', 'titulo' => 'Proyección por Ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'sinCosecha', 'titulo' => 'Sin cosecha', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'sinHaFinca', 'titulo' => 'Sin Ha finca', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
        ]);

        $totales += [
            'total'      => $tonha ? $tonHa : round($medida === 'toneladas' ? $kgAct / 1000 : $suma('total'), 2),
            'anterior'   => ! $hayAnt ? null : ($tonha ? $div($suma('tonConHaAnt'), $suma('hNetasAnt')) : round($medida === 'toneladas' ? $kgAnt / 1000 : $suma('anterior'), 2)),
            'variacion'  => $div($suma('baseDif'), $suma('baseAnt'), 4),
            'proyeccion' => $div($suma('proyConHa'), $haAct),
        ];

        foreach ($filas as &$f) {
            unset($f['kgAnt']);
        }

        unset($f);

        $series = [['nombre' => (string) $anio, 'datos' => array_map(static fn ($i) => $i > $m ? null : round($lineaA[$i], 2), range(1, 12))]];

        if ($hayP) {
            $series[] = ['nombre' => $previo . ' (año anterior)', 'datos' => array_map(static fn ($v) => round($v, 2), array_values($lineaP))];
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Toneladas acumuladas', 'valor' => $vacio ? null : round($kgAct / 1000, 2), 'formato' => 'decimal', 'icono' => 'bi bi-truck',
                    'ayuda' => $vacio ? '' : $rango . ' ' . $anio . ' · ' . self::plural(count($mapa), 'lote', 'lotes')],
                ['etiqueta' => 'Variación vs ' . $previo, 'valor' => $vacio ? null : $varKg, 'formato' => 'variacion', 'icono' => $varKg !== null && $varKg < 0 ? 'bi bi-graph-down-arrow' : 'bi bi-graph-up-arrow',
                    'ayuda' => $vacio ? '' : ($varKg === null ? 'Sin producción en ' . $previo : 'Mismo periodo ' . $previo . ': ' . self::n($kgAnt / 1000, 2) . ' t')],
                ['etiqueta' => 'Ton/ha acumulado', 'valor' => $vacio ? null : $tonHa, 'formato' => 'decimal', 'icono' => 'bi bi-grid',
                    'ayuda' => $vacio ? '' : 'Sobre ' . self::n($haAct, 2) . ' ha netas con producción en ' . $anio],
                ['etiqueta' => 'Proyección t/ha ' . $anio, 'valor' => $vacio ? null : $proyT, 'formato' => 'decimal', 'icono' => 'bi bi-bullseye',
                    'ayuda' => $vacio ? '' : ($m === 12 ? 'Año completo: igual al acumulado' : 'Ritmo de ' . $m . ' ' . ($m === 1 ? 'mes' : 'meses') . ' llevado a 12')],
            ],
            'grafica'  => null,
            'graficas' => $vacio ? [] : [
                [
                    'tipo'       => 'line',
                    'titulo'     => 'Toneladas por mes · ' . $anio . ' vs ' . $previo,
                    'icono'      => 'bi bi-graph-up',
                    'categorias' => array_values(self::MESES),
                    'series'     => $series,
                    'colores'    => ['#2f7a4b', '#8795a4'],
                    'guiones'    => [0, 5],
                    'anchos'     => [3, 2],
                    'ejeX'       => 'Mes',
                    'ejeY'       => 'Toneladas',
                    'formato'    => 'decimal',
                ],
                [
                    'tipo'       => 'heatmap',
                    'titulo'     => 'Mapa de producción por lote · ' . $nombre . ' ' . $anio,
                    'icono'      => 'bi bi-grid-3x3',
                    'categorias' => array_values(self::MESES),
                    'series'     => $mapa,
                    'detalle'    => $detalle,
                    'medida'     => $nombre,
                    'formato'    => $tipo,
                ],
            ],
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'finca',
            'avisos'   => $avisos,
        ];
    }
}
