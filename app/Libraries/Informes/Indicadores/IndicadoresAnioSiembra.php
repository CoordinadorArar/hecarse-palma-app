<?php

namespace App\Libraries\Informes\Indicadores;

class IndicadoresAnioSiembra extends IndicadoresAnio
{
    public function consultar(array $filtros): array
    {
        $b      = $this->base($filtros);
        $grupos = [];

        foreach ($b['lotes'] as $l) {
            $grupos[$l['siembra']][] = $l;
        }

        uksort($grupos, static fn ($x, $y) => [$x === 0, $x] <=> [$y === 0, $y]);

        $filas = [];

        foreach ($grupos as $siembra => $lotes) {
            $filas[] = [
                'anioSiembra' => $siembra === 0 ? 'Sin año' : (string) $siembra,
                'edad'        => $siembra === 0 ? null : $b['anio'] - $siembra,
                'lotes'       => count($lotes),
                'hNetas'      => round(array_sum(array_column($lotes, 'hNetas')), 2),
                'palmas'      => array_sum(array_column($lotes, 'palmas')),
            ] + self::metricas(self::sumar($lotes), $b['div'], $b['acum']);
        }

        $t      = self::metricas(self::sumar($filas), $b['div'], $b['acum']);
        $n      = count($b['lotes']);
        $vacio  = $filas === [];
        $sinHa  = count(array_filter($b['lotes'], static fn ($l) => $l['hNetas'] <= 0));
        $sinRac = count(array_filter($b['lotes'], static fn ($l) => $l['kilosAct'] > 0 && $l['racimosAct'] <= 0));
        $curva  = array_values(array_filter($filas, static fn ($f) => $f['edad'] !== null));
        $mejor  = array_reduce(array_filter($curva, static fn ($f) => $f['tonHaAct'] > 0), static fn ($m, $f) => $m === null || $f['tonHaAct'] > $m['tonHaAct'] ? $f : $m);
        $kilos  = self::kpisComunes($b, $t, $n, '')[0];
        $sinAno = count($grupos[0] ?? []);
        $avisos = self::avisos($b, $t, $sinHa, $sinRac, $vacio);
        $v      = $t['variacion'];

        if (! $vacio) {
            $kilos['ayuda'] = $b['rango'] . ' ' . $b['anio'] . ' · ' . ($v === null ? 'Sin producción en ' . $b['previo']
                : 'Var. vs ' . $b['previo'] . ': ' . ($v < 0 ? '−' : '+') . self::n(abs($v) * 100, 1) . ' % · ' . $b['previo'] . ': ' . self::n($t['kilosAnt']) . ' kg');
        }

        if ($sinAno > 0) {
            $avisos[] = self::plural($sinAno, 'lote sin año de siembra: se muestra al final y no entra en la curva.', 'lotes sin año de siembra: se muestran al final y no entran en la curva.');
        }

        return [
            'kpis' => [
                $kilos,
                ['etiqueta' => 'T/ha total', 'valor' => $vacio ? null : $t['tonHaAct'], 'formato' => 'decimal', 'icono' => 'bi bi-grid',
                    'ayuda' => $vacio ? 'Sin cosecha en el periodo' : 'Sobre ' . self::n($t['hNetasAct'], 2) . ' ha netas con producción en ' . $b['anio']],
                ['etiqueta' => 'Siembra con mayor t/ha', 'valor' => $mejor['anioSiembra'] ?? null, 'formato' => 'texto', 'icono' => 'bi bi-award',
                    'ayuda' => $mejor === null ? ($vacio ? 'Sin cosecha en el periodo' : ($t['kilosAct'] == 0 ? 'Sin cosecha en ' . $b['anio'] . ' en el periodo' : 'Sin Ha netas para calcular t/ha')) : self::n($mejor['tonHaAct'], 2) . ' t/ha' . ($mejor['edad'] === null ? '' : ' · edad ' . self::plural($mejor['edad'], 'año', 'años'))],
                self::proyeccionKpi($b, $t, $vacio),
            ],
            'grafica' => $curva === [] ? null : [
                'tipo'       => 'combo',
                'titulo'     => 'Curva productiva por año de siembra · ' . $b['anio'],
                'categorias' => array_column($curva, 'anioSiembra'),
                'series'     => [
                    ['nombre' => 'Ton/ha ' . $b['anio'], 'tipo' => 'column', 'formato' => 'decimal', 'datos' => array_column($curva, 'tonHaAct')],
                    ['nombre' => 'Kg/racimo ' . $b['anio'], 'tipo' => 'line', 'formato' => 'decimal', 'eje' => 1, 'datos' => array_column($curva, 'pesoAct')],
                ],
                'colores'    => ['#41a867', '#4a5b6c'],
                'detalle'    => array_map(static fn ($f) => ['tonHaAnt' => $f['tonHaAnt'], 'variacion' => $f['variacion'], 'lotes' => $f['lotes'], 'hNetas' => $f['hNetas'], 'edad' => $f['edad']], $curva),
                'ejeY'       => 'Ton/ha',
                'ejeY2'      => 'Kg/racimo',
                'formato'    => 'decimal',
            ],
            'columnas' => self::columnas([
                ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'edad', 'titulo' => 'Edad (años)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => $b['anio'] . ' − año de siembra'],
                ['clave' => 'lotes', 'titulo' => 'N.º lotes', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ], $b),
            'filas'    => $filas,
            'totales'  => ['lotes' => $n, 'hNetas' => round(array_sum(array_column($filas, 'hNetas')), 2), 'palmas' => array_sum(array_column($filas, 'palmas'))] + array_intersect_key($t, array_flip(['racimosAnt', 'racimosAct', 'kilosAnt', 'kilosAct', 'variacion', 'pesoAnt', 'pesoAct', 'tonHaAnt', 'tonHaAct', 'proyeccion'])),
            'avisos'   => $avisos,
        ];
    }
}
