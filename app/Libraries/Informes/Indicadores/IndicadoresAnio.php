<?php

namespace App\Libraries\Informes\Indicadores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class IndicadoresAnio extends Informe
{
    protected const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT [año] AS anio FROM aTransaccion WHERE empresa = ? AND tipo = 'TLC' AND anulado = 0 AND [año] > 0 ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $defecto = in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? '');

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => $defecto, 'ayuda' => 'Se compara con el año anterior · fecha de cosecha', 'opciones' => $anios],
            ['clave' => 'mes', 'etiqueta' => 'Mes', 'tipo' => 'mes', 'obligatorio' => true, 'defecto' => $defecto === date('Y') ? date('n') : '12', 'ayuda' => 'Mes a mostrar y corte del acumulado',
                'opciones' => array_map(static fn ($m, $n) => ['valor' => (string) $m, 'texto' => $n], array_keys(self::MESES), self::MESES)],
            ['clave' => 'periodo', 'etiqueta' => 'Periodo', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'acumulado', 'opciones' => [['valor' => 'acumulado', 'texto' => 'Acumulado'], ['valor' => 'mes', 'texto' => 'Mes']]],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
        ];
    }

    protected static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    protected static function plural(int $n, string $uno, string $varios): string
    {
        return self::n($n) . ' ' . ($n === 1 ? $uno : $varios);
    }

    protected function base(array $filtros): array
    {
        $anio  = (int) $filtros['anio'];
        $mes   = (int) $filtros['mes'];
        $acum  = $filtros['periodo'] === 'acumulado';
        $where = '';
        $binds = [$this->empresa, ($anio - 1) . '-01-01', $anio . '-12-31', $acum ? 1 : $mes, $mes];

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        if ($filtros['finca'] !== '') {
            $where   = ' AND LTRIM(RTRIM(k.finca)) = ?';
            $binds[] = $filtros['finca'];
        }

        $sql = "SELECT lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, palmas, YEAR(fecha) AS anio, MAX(MONTH(fecha)) AS ultimo, SUM(racimos) AS racimos, SUM(kilos) AS kilos
                  FROM (SELECT LTRIM(RTRIM(k.codigo)) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(ISNULL(k.finca, ''))) AS finca,
                               CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS fincaNombre, CAST(ISNULL(v.descripcion, '') AS varchar(100)) AS variedad,
                               ISNULL(k.[añoSiembra], 0) AS anioSiembra, ISNULL(k.hNetas, 0) AS hNetas, ISNULL(k.palmasProduccion, 0) AS palmas, e.fecha,
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
                         WHERE b.empresa = ? AND e.fecha BETWEEN ? AND ? AND MONTH(e.fecha) BETWEEN ? AND ?{$where}) x
                 GROUP BY lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, palmas, YEAR(fecha)";

        $lotes  = [];
        $ultimo = 0;

        foreach ($this->db->query($sql, $binds)->getResultArray() as $c) {
            $sufijo = (int) $c['anio'] === $anio ? 'Act' : 'Ant';
            $l      = &$lotes[$c['finca'] . '|' . $c['lote']];
            $l['codigo']      = $c['lote'];
            $l['fincaCodigo'] = $c['finca'];
            $l['finca']       = $c['finca'] . ' — ' . trim($c['fincaNombre']);
            $l['lote']        = $c['lote'] . ' — ' . trim($c['loteNombre']);
            $l['variedad']    = trim($c['variedad']);
            $l['siembra']     = (int) $c['anioSiembra'];
            $l['hNetas']      = (float) $c['hNetas'];
            $l['palmas']      = (int) $c['palmas'];
            $l['racimos' . $sufijo] = (int) $c['racimos'];
            $l['kilos' . $sufijo]   = (float) $c['kilos'];
            unset($l);

            if ($sufijo === 'Act') {
                $ultimo = max($ultimo, (int) $c['ultimo']);
            }
        }

        $div = ! $acum ? 0 : ($anio === (int) date('Y') ? min($mes, $ultimo) : $mes);

        foreach ($lotes as &$l) {
            $h = $l['hNetas'];
            $l += ['racimosAnt' => 0, 'racimosAct' => 0, 'kilosAnt' => 0.0, 'kilosAct' => 0.0];
            $l += [
                'kilosConRacAnt' => $l['racimosAnt'] > 0 ? $l['kilosAnt'] : 0,
                'racPesoAnt'     => max($l['racimosAnt'], 0),
                'kilosConRacAct' => $l['racimosAct'] > 0 ? $l['kilosAct'] : 0,
                'racPesoAct'     => max($l['racimosAct'], 0),
                'tonConHaAnt'    => $h > 0 ? $l['kilosAnt'] / 1000 : 0,
                'tonConHaAct'    => $h > 0 ? $l['kilosAct'] / 1000 : 0,
                'hNetasPeso'     => $h > 0 ? $h : 0,
                'hNetasAnt'      => $h > 0 && $l['kilosAnt'] > 0 ? $h : 0,
                'hNetasAct'      => $h > 0 && $l['kilosAct'] > 0 ? $h : 0,
            ];
        }

        unset($l);

        uasort($lotes, static fn ($x, $y) => [$x['finca'], $x['siembra'], $x['codigo']] <=> [$y['finca'], $y['siembra'], $y['codigo']]);

        return ['lotes' => array_values($lotes), 'anio' => $anio, 'previo' => $anio - 1, 'mes' => $mes, 'acum' => $acum, 'div' => $div,
            'rango' => ($acum && $mes > 1 ? 'Ene–' : '') . substr(self::MESES[$mes], 0, 3)];
    }

    protected static function metricas(array $a, int $div, bool $acum): array
    {
        $ponderar = static fn (float $num, float $den) => $den > 0 ? round($num / $den, 2) : null;
        $cero     = static fn (?float $v) => $v ?? ($a['hNetasPeso'] > 0 ? 0.0 : null);
        $tonHa    = $cero($ponderar($a['tonConHaAct'], $a['hNetasAct']));
        $proy     = $tonHa !== null && $div > 0 ? ($a['hNetasAct'] > 0 ? $a['tonConHaAct'] / $a['hNetasAct'] : 0) / $div * 12 : null;

        return [
            'racimosAnt' => $a['racimosAnt'],
            'racimosAct' => $a['racimosAct'],
            'kilosAnt'   => round($a['kilosAnt'], 2),
            'kilosAct'   => round($a['kilosAct'], 2),
            'variacion'  => $a['kilosAnt'] > 0 ? round(($a['kilosAct'] - $a['kilosAnt']) / $a['kilosAnt'], 4) : null,
            'pesoAnt'    => $ponderar($a['kilosConRacAnt'], $a['racPesoAnt']),
            'pesoAct'    => $ponderar($a['kilosConRacAct'], $a['racPesoAct']),
            'tonHaAnt'   => $cero($ponderar($a['tonConHaAnt'], $a['hNetasAnt'])),
            'tonHaAct'   => $tonHa,
        ] + ($acum ? ['proyeccion' => $proy === null ? null : round($proy, 2)] : []) + [
            'kilosDif'       => $a['kilosAct'] - $a['kilosAnt'],
            'kilosConRacAnt' => $a['kilosConRacAnt'],
            'racPesoAnt'     => $a['racPesoAnt'],
            'kilosConRacAct' => $a['kilosConRacAct'],
            'racPesoAct'     => $a['racPesoAct'],
            'tonConHaAnt'    => $a['tonConHaAnt'],
            'tonConHaAct'    => $a['tonConHaAct'],
            'hNetasPeso'     => $a['hNetasPeso'],
            'hNetasAnt'      => $a['hNetasAnt'],
            'hNetasAct'      => $a['hNetasAct'],
        ] + ($acum ? ['proyConHa' => $proy === null ? 0 : $proy * $a['hNetasAct']] : []);
    }

    protected static function sumar(array $filas): array
    {
        $claves = ['racimosAnt', 'racimosAct', 'kilosAnt', 'kilosAct', 'kilosConRacAnt', 'racPesoAnt', 'kilosConRacAct', 'racPesoAct', 'tonConHaAnt', 'tonConHaAct', 'hNetasPeso', 'hNetasAnt', 'hNetasAct'];

        return array_combine($claves, array_map(static fn ($c) => array_sum(array_column($filas, $c)), $claves));
    }

    protected static function columnas(array $inicio, array $b): array
    {
        [$act, $ant] = [$b['anio'], $b['previo']];
        $antes = 'Mismo periodo de ' . $ant;

        return array_merge($inicio, [
            ['clave' => 'racimosAnt', 'titulo' => 'Racimos ' . $ant, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => $antes],
            ['clave' => 'racimosAct', 'titulo' => 'Racimos ' . $act, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ['clave' => 'kilosAnt', 'titulo' => 'Kilos ' . $ant, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => $antes],
            ['clave' => 'kilosAct', 'titulo' => 'Kilos ' . $act, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ['clave' => 'variacion', 'titulo' => 'Var. kilos %', 'tipo' => 'variacion', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosDif', 'denominador' => 'kilosAnt',
                'ayuda' => '(Kilos ' . $act . ' − kilos ' . $ant . ') / kilos ' . $ant],
            ['clave' => 'pesoAnt', 'titulo' => 'Kg/racimo ' . $ant, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosConRacAnt', 'denominador' => 'racPesoAnt', 'ayuda' => $antes . ' · kilos / racimos'],
            ['clave' => 'pesoAct', 'titulo' => 'Kg/racimo ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosConRacAct', 'denominador' => 'racPesoAct', 'ayuda' => 'Kilos / racimos'],
            ['clave' => 'tonHaAnt', 'titulo' => 'T/ha ' . $ant, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'tonConHaAnt', 'denominador' => 'hNetasAnt', 'ayuda' => $antes . ' · kilos / 1.000 / Ha netas. Subtotal y total sobre Ha de lotes con producción en ' . $ant],
            ['clave' => 'tonHaAct', 'titulo' => 'T/ha ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'tonConHaAct', 'denominador' => 'hNetasAct', 'ayuda' => 'Kilos / 1.000 / Ha netas. Subtotal y total sobre Ha de lotes con producción en ' . $act],
        ], $b['acum'] ? [
            ['clave' => 'proyeccion', 'titulo' => 'Proy. t/ha ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'proyConHa', 'denominador' => 'hNetasAct',
                'ayuda' => '(T/ha acumulado / ' . self::plural($b['div'], 'mes', 'meses') . ') × 12' . ($b['div'] < $b['mes'] ? ' · ' . self::MESES[max($b['div'], 1)] . ' es el último mes con cosecha' : '')],
        ] : [], [
            ['clave' => 'kilosDif', 'titulo' => 'Diferencia de kilos', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'kilosConRacAnt', 'titulo' => 'Kilos con racimos ' . $ant, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'racPesoAnt', 'titulo' => 'Racimos para peso ' . $ant, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'kilosConRacAct', 'titulo' => 'Kilos con racimos ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'racPesoAct', 'titulo' => 'Racimos para peso ' . $act, 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tonConHaAnt', 'titulo' => 'Toneladas con Ha ' . $ant, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tonConHaAct', 'titulo' => 'Toneladas con Ha ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasPeso', 'titulo' => 'Ha netas válidas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasAnt', 'titulo' => 'Ha netas con producción ' . $ant, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetasAct', 'titulo' => 'Ha netas con producción ' . $act, 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
        ], $b['acum'] ? [['clave' => 'proyConHa', 'titulo' => 'Proyección por Ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false]] : []);
    }

    protected static function kpisComunes(array $b, array $t, int $n, string $unidad): array
    {
        $vacio = $n === 0;
        $nada  = 'Sin cosecha en el periodo';

        return [
            ['etiqueta' => 'Kilos ' . ($b['acum'] ? 'acumulados' : 'del mes') . ' ' . $b['anio'], 'valor' => $vacio ? null : $t['kilosAct'], 'formato' => 'entero', 'icono' => 'bi bi-truck',
                'ayuda' => $vacio ? $nada : $b['rango'] . ' ' . $b['anio'] . ' · ' . $unidad],
            ['etiqueta' => 'Var. vs ' . $b['previo'], 'valor' => $vacio ? null : $t['variacion'], 'formato' => 'variacion',
                'icono' => $t['variacion'] !== null && $t['variacion'] < 0 ? 'bi bi-graph-down-arrow' : 'bi bi-graph-up-arrow',
                'ayuda' => $vacio ? $nada : ($t['variacion'] === null ? 'Sin producción en ' . $b['previo'] : $b['previo'] . ': ' . self::n($t['kilosAnt']) . ' kg')],
        ];
    }

    protected static function proyeccionKpi(array $b, array $t, bool $vacio): array
    {
        return ['etiqueta' => 'Proyección t/ha ' . $b['anio'], 'valor' => $vacio || ! $b['acum'] ? null : $t['proyeccion'], 'formato' => 'decimal', 'icono' => 'bi bi-bullseye',
            'ayuda' => ! $b['acum'] ? 'Solo en acumulado' : ($vacio ? 'Sin cosecha en el periodo' : ($b['div'] === 0 ? 'Sin cosecha en ' . $b['anio'] : 'Ritmo de ' . self::plural($b['div'], 'mes', 'meses') . ' llevado a 12'
                . ($b['div'] < $b['mes'] ? ' · último mes con cosecha: ' . self::MESES[$b['div']] : '')))];
    }

    protected static function avisos(array $b, array $t, int $sinHa, int $sinRac, bool $vacio): array
    {
        $avisos = [];

        if ($sinHa > 0) {
            $avisos[] = self::plural($sinHa, 'lote sin Ha netas: no entra en T/ha' . ($b['acum'] ? ' ni en la proyección.' : '.'), 'lotes sin Ha netas: no entran en T/ha' . ($b['acum'] ? ' ni en la proyección.' : '.'));
        }

        if ($sinRac > 0) {
            $avisos[] = self::plural($sinRac, 'lote con kilos sin racimos registrados: no entra en el peso promedio.', 'lotes con kilos sin racimos registrados: no entran en el peso promedio.');
        }

        if (! $vacio && $t['kilosAnt'] <= 0) {
            $avisos[] = 'Sin producción en ' . $b['previo'] . ': no se calcula variación.';
        }

        if (! $vacio && $b['acum'] && $b['div'] > 0 && $b['div'] < $b['mes']) {
            $avisos[] = 'La proyección usa el ritmo de ' . self::plural($b['div'], 'mes', 'meses') . ' (último mes con cosecha: ' . self::MESES[$b['div']] . ').';
        }

        if (! $vacio && $t['kilosAct'] == 0) {
            $avisos[] = 'Aún no hay cosecha registrada en ' . ($b['acum'] && $b['mes'] > 1 ? 'Enero–' : '') . self::MESES[$b['mes']] . ' ' . $b['anio'] . '; los valores del año actual salen en 0.';
        }

        return $avisos;
    }

    public function consultar(array $filtros): array
    {
        $b       = $this->base($filtros);
        $fincaHa = [];

        foreach ($b['lotes'] as $l) {
            $fincaHa[$l['fincaCodigo']] = ($fincaHa[$l['fincaCodigo']] ?? false) || $l['hNetas'] <= 0;
        }

        $filas = array_map(static fn ($l) => [
            'finca'       => $l['finca'],
            'lote'        => $l['lote'],
            'variedad'    => $l['variedad'],
            'anioSiembra' => $l['siembra'] === 0 ? '' : (string) $l['siembra'],
            'hNetas'      => $l['hNetas'],
            'palmas'      => $l['palmas'],
        ] + self::metricas($l, $b['div'], $b['acum']) + [
            'sinRacimos'  => $l['kilosAct'] > 0 && $l['racimosAct'] <= 0,
            'sinHaFinca'  => $fincaHa[$l['fincaCodigo']],
        ], $b['lotes']);

        $t      = self::metricas(self::sumar($filas), $b['div'], $b['acum']);
        $vacio  = $filas === [];
        $sinHa  = count(array_filter($filas, static fn ($f) => $f['hNetas'] <= 0));
        $sinRac = count(array_filter($filas, static fn ($f) => $f['sinRacimos']));
        $caidas = count(array_filter($filas, static fn ($f) => $f['variacion'] !== null && $f['variacion'] <= -0.2));
        $conAnt = count(array_filter($filas, static fn ($f) => $f['variacion'] !== null));

        $top = array_filter($filas, static fn ($f) => $f['hNetas'] > 0);
        usort($top, static fn ($x, $y) => $y['kilosAct'] <=> $x['kilosAct']);
        $top = array_slice($top, 0, 15);

        $columnas = self::columnas([
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
            ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
        ], $b);
        $columnas[] = ['clave' => 'sinRacimos', 'titulo' => 'Kilos sin racimos', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false];
        $columnas[] = ['clave' => 'sinHaFinca', 'titulo' => 'Finca con lotes sin Ha netas', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false];

        return [
            'kpis' => array_merge(self::kpisComunes($b, $t, count($filas), self::plural(count($filas), 'lote', 'lotes')), [
                ['etiqueta' => 'T/ha', 'valor' => $vacio ? null : $t['tonHaAct'], 'formato' => 'decimal', 'icono' => 'bi bi-grid',
                    'ayuda' => $vacio ? 'Sin cosecha en el periodo' : 'Sobre ' . self::n($t['hNetasAct'], 2) . ' ha netas con producción en ' . $b['anio']],
                $b['acum'] ? self::proyeccionKpi($b, $t, $vacio)
                    : ['etiqueta' => 'Lotes con caída ≥ 20 %', 'valor' => $vacio ? null : $caidas, 'formato' => 'entero', 'icono' => 'bi bi-exclamation-triangle',
                        'ayuda' => $vacio ? 'Sin cosecha en el periodo' : 'De ' . self::plural($conAnt, 'lote', 'lotes') . ' con producción en ' . $b['previo']],
            ]),
            'grafica' => $top === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'etiquetas'  => false,
                'titulo'     => 'Ton/ha · top 15 lotes · ' . $b['anio'] . ' vs ' . $b['previo'],
                'categorias' => array_column($top, 'lote'),
                'series'     => [
                    ['nombre' => (string) $b['anio'], 'datos' => array_column($top, 'tonHaAct')],
                    ['nombre' => (string) $b['previo'], 'datos' => array_column($top, 'tonHaAnt')],
                ],
                'colores'    => ['#2f7a4b', '#8795a4'],
                'detalle'    => array_map(static fn ($f) => ['finca' => $f['finca'], 'kilosAct' => $f['kilosAct'], 'variacion' => $f['variacion'], 'pesoAct' => $f['pesoAct']], $top),
                'ejeX'       => 'Ton/ha',
                'formato'    => 'decimal',
            ],
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => ['hNetas' => round(array_sum(array_column($filas, 'hNetas')), 2), 'palmas' => array_sum(array_column($filas, 'palmas'))] + array_intersect_key($t, array_flip(['racimosAnt', 'racimosAct', 'kilosAnt', 'kilosAct', 'variacion', 'pesoAnt', 'pesoAct', 'tonHaAnt', 'tonHaAct', 'proyeccion'])),
            'agrupar'  => 'finca',
            'avisos'   => self::avisos($b, $t, $sinHa, $sinRac, $vacio),
        ];
    }
}
