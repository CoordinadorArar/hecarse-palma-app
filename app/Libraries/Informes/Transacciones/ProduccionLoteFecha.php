<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class ProduccionLoteFecha extends Informe
{
    public function filtros(): array
    {
        $variedades = array_map(
            static fn ($v) => ['valor' => $v['codigo'], 'texto' => $v['codigo'] . ' — ' . trim($v['descripcion'])],
            $this->db->query("SELECT RTRIM(codigo) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aVariedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query('SELECT DISTINCT [añoSiembra] AS anio FROM aLotes WHERE empresa = ? AND [añoSiembra] > 0 ORDER BY [añoSiembra] DESC', [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de cosecha (fecha de novedad).'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'variedad', 'etiqueta' => 'Variedad', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las variedades', 'opciones' => $variedades],
            ['clave' => 'anioSiembra', 'etiqueta' => 'Año de siembra', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los años', 'opciones' => $anios],
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
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        foreach (['LTRIM(RTRIM(k.finca))' => $filtros['finca'], 'RTRIM(k.variedad)' => $filtros['variedad'], 'k.[añoSiembra]' => $filtros['anioSiembra']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND {$campo} = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, palmas, fecha, SUM(racimos) AS racimos, SUM(kilos) AS kilos
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
                         WHERE b.empresa = ? AND e.fecha BETWEEN ? AND ?{$where}) x
                 GROUP BY lote, loteNombre, finca, fincaNombre, variedad, anioSiembra, hNetas, palmas, fecha";

        $query  = $this->db->query($sql, $binds);
        $lotes  = [];
        $fincas = [];
        $fechas = [];
        $haFinca = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            $fecha = substr((string) $c['fecha'], 0, 10);
            $l     = &$lotes[$c['finca'] . '|' . $c['lote']];
            $l['codigo']      = $c['lote'];
            $l['finca']       = $c['finca'] . ' — ' . trim($c['fincaNombre']);
            $l['lote']        = $c['lote'] . ' — ' . trim($c['loteNombre']);
            $l['variedad']    = trim($c['variedad']);
            $l['anioSiembra'] = (int) $c['anioSiembra'] === 0 ? '' : (string) (int) $c['anioSiembra'];
            $l['hNetas']      = (float) $c['hNetas'];
            $l['palmas']      = (int) $c['palmas'];
            $l['racimos']     = ($l['racimos'] ?? 0) + (int) $c['racimos'];
            $l['kilos']       = ($l['kilos'] ?? 0) + (float) $c['kilos'];
            $l['fechas'][$fecha] = true;
            unset($l);

            $fincas[$c['finca']][$fecha] = true;
            $haFinca[$c['finca']] = ($haFinca[$c['finca']] ?? false) || (float) $c['hNetas'] <= 0;
            $fechas[$fecha] = true;
        }

        $query->freeResult();

        uasort($lotes, static fn ($x, $y) => [$x['finca'], $x['codigo']] <=> [$y['finca'], $y['codigo']]);

        $filas = [];

        foreach ($lotes as $clave => $l) {
            $k = $l['kilos'];
            $r = $l['racimos'];
            $h = $l['hNetas'];
            $p = $l['palmas'];
            $filas[] = [
                'finca'           => $l['finca'],
                'lote'            => $l['lote'],
                'variedad'        => $l['variedad'],
                'anioSiembra'     => $l['anioSiembra'],
                'hNetas'          => $h,
                'palmas'          => $p,
                'racimos'         => $r,
                'kilos'           => round($k, 2),
                'pesoRacimo'      => $r > 0 ? round($k / $r, 2) : null,
                'tonHa'           => $h > 0 ? round($k / 1000 / $h, 2) : null,
                'kgPalma'         => $p > 0 ? round($k / $p, 2) : null,
                'dias'            => count($l['fechas']),
                'racimosPeso'     => $r > 0 ? $r : 0,
                'kilosConRacimos' => $r > 0 ? $k : 0,
                'tonConHa'        => $h > 0 ? $k / 1000 : 0,
                'hNetasPeso'      => $h > 0 ? $h : 0,
                'kilosConPalmas'  => $p > 0 ? $k : 0,
                'palmasPeso'      => $p > 0 ? $p : 0,
                'toneladas'       => $k / 1000,
                'sinRacimos'      => $k > 0 && $r <= 0,
                'diasFinca'       => count($fincas[explode('|', $clave, 2)[0]]),
                'sinHaFinca'      => $haFinca[explode('|', $clave, 2)[0]],
                'codigo'          => $l['codigo'],
            ];
        }

        $suma      = static fn (string $c) => array_sum(array_column($filas, $c));
        $kilos     = $suma('kilos');
        $racimos   = $suma('racimos');
        $racPeso   = $suma('racimosPeso');
        $haPeso    = $suma('hNetasPeso');
        $palPeso   = $suma('palmasPeso');
        $peso      = $racPeso > 0 ? round($suma('kilosConRacimos') / $racPeso, 2) : null;
        $tonHa     = $haPeso > 0 ? round($suma('tonConHa') / $haPeso, 2) : null;
        $kgPalma   = $palPeso > 0 ? round($suma('kilosConPalmas') / $palPeso, 2) : null;
        $sinHa     = count(array_filter($filas, static fn ($f) => $f['hNetas'] <= 0));
        $sinPalmas = count(array_filter($filas, static fn ($f) => $f['palmas'] <= 0));
        $sinRac    = count(array_filter($filas, static fn ($f) => $f['sinRacimos']));
        $pesos     = array_filter(array_column($filas, 'pesoRacimo'), static fn ($v) => $v !== null);
        $maximo    = array_reduce(array_filter($filas, static fn ($f) => $f['tonHa'] !== null), static fn ($m, $f) => $m === null || $f['tonHa'] > $m['tonHa'] ? $f : $m);
        $vacio     = $filas === [];
        $avisos    = [];

        if ($sinHa > 0) {
            $avisos[] = self::plural($sinHa, 'lote sin Ha netas: no entra en Ton/ha.', 'lotes sin Ha netas: no entran en Ton/ha.');
        }

        if ($sinPalmas > 0) {
            $avisos[] = self::plural($sinPalmas, 'lote sin palmas en producción: no entra en Kg/palma.', 'lotes sin palmas en producción: no entran en Kg/palma.');
        }

        if ($sinRac > 0) {
            $avisos[] = self::plural($sinRac, 'lote con kilos sin racimos registrados: no entra en el peso promedio.', 'lotes con kilos sin racimos registrados: no entran en el peso promedio.');
        }

        $top = $filas;
        usort($top, static fn ($x, $y) => $y['kilos'] <=> $x['kilos']);
        $top = array_slice($top, 0, 20);

        foreach ($filas as &$f) {
            unset($f['codigo']);
        }

        unset($f);

        return [
            'kpis' => [
                ['etiqueta' => 'Toneladas cosechadas', 'valor' => $vacio ? null : round($kilos / 1000, 2), 'formato' => 'decimal', 'icono' => 'bi bi-truck',
                    'ayuda' => $vacio ? '' : self::plural(count($filas), 'lote', 'lotes') . ' · ' . self::plural(count($fincas), 'finca', 'fincas') . ' · ' . self::plural(count($fechas), 'día con cosecha', 'días con cosecha')],
                ['etiqueta' => 'Racimos cosechados', 'valor' => $vacio ? null : $racimos, 'formato' => 'entero', 'icono' => 'bi bi-basket',
                    'ayuda' => $vacio ? '' : 'Kilos: ' . self::n($kilos)],
                ['etiqueta' => 'Peso promedio por racimo (kg)', 'valor' => $vacio ? null : $peso, 'formato' => 'decimal', 'icono' => 'bi bi-speedometer2',
                    'ayuda' => $vacio ? '' : 'Kilos / racimos' . ($pesos === [] ? '' : ' · Rango por lote: ' . self::n(min($pesos), 2) . ' – ' . self::n(max($pesos), 2) . ' kg')
                        . ($sinRac > 0 ? ' · ' . self::plural($sinRac, 'lote sin racimos excluido', 'lotes sin racimos excluidos') : '')],
                ['etiqueta' => 'Ton/ha promedio', 'valor' => $vacio ? null : $tonHa, 'formato' => 'decimal', 'icono' => 'bi bi-graph-up',
                    'ayuda' => $vacio ? '' : 'Ponderado sobre ' . self::n($haPeso, 2) . ' Ha netas' . ($maximo === null ? '' : ' · Máximo: ' . self::n($maximo['tonHa'], 2) . ' · ' . $maximo['codigo'])
                        . ($sinHa > 0 ? ' · ' . self::plural($sinHa, 'lote sin Ha netas excluido', 'lotes sin Ha netas excluidos') : '')],
            ],
            'grafica' => $vacio ? null : [
                'tipo'       => 'combo',
                'titulo'     => 'Top 20 lotes por toneladas',
                'categorias' => array_column($top, 'lote'),
                'series'     => [
                    ['nombre' => 'Toneladas', 'tipo' => 'column', 'formato' => 'decimal', 'datos' => array_map(static fn ($f) => round($f['toneladas'], 2), $top)],
                    ['nombre' => 'Peso prom. racimo (kg)', 'tipo' => 'line', 'formato' => 'decimal', 'eje' => 1, 'datos' => array_column($top, 'pesoRacimo')],
                ],
                'colores'    => ['#41a867', '#4a5b6c'],
                'detalle'    => array_map(static fn ($f) => ['finca' => $f['finca'], 'racimos' => $f['racimos'], 'hNetas' => $f['hNetas'], 'tonHa' => $f['tonHa'], 'kgPalma' => $f['kgPalma']], $top),
                'ejeY'       => 'Toneladas',
                'ejeY2'      => 'Kg por racimo',
                'formato'    => 'decimal',
            ],
            'columnas' => [
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'racimos', 'titulo' => 'Racimos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'kilos', 'titulo' => 'Kilos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'pesoRacimo', 'titulo' => 'Peso prom. (kg)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosConRacimos', 'denominador' => 'racimosPeso',
                    'ayuda' => 'Kilos / racimos. Subtotal y total ponderados.'],
                ['clave' => 'tonHa', 'titulo' => 'Ton/ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'tonConHa', 'denominador' => 'hNetasPeso',
                    'ayuda' => 'Kilos / 1.000 / Ha netas. Excluye lotes sin Ha netas.'],
                ['clave' => 'kgPalma', 'titulo' => 'Kg/palma', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosConPalmas', 'denominador' => 'palmasPeso',
                    'ayuda' => 'Kilos / palmas en producción. Excluye lotes sin palmas.'],
                ['clave' => 'dias', 'titulo' => 'Días cosecha', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Fechas distintas con cosecha en el lote.'],
                ['clave' => 'racimosPeso', 'titulo' => 'Racimos peso', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'kilosConRacimos', 'titulo' => 'Kilos con racimos', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'tonConHa', 'titulo' => 'Ton con Ha', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'hNetasPeso', 'titulo' => 'Ha netas peso', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'kilosConPalmas', 'titulo' => 'Kilos con palmas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'palmasPeso', 'titulo' => 'Palmas peso', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'toneladas', 'titulo' => 'Toneladas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'sinRacimos', 'titulo' => 'Sin racimos', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'diasFinca', 'titulo' => 'Días finca', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => [
                'hNetas'     => round($suma('hNetas'), 2),
                'palmas'     => $suma('palmas'),
                'racimos'    => $racimos,
                'kilos'      => round($kilos, 2),
                'pesoRacimo' => $peso,
                'tonHa'      => $tonHa,
                'kgPalma'    => $kgPalma,
            ],
            'agrupar' => 'finca',
            'avisos'  => $avisos,
        ];
    }
}
