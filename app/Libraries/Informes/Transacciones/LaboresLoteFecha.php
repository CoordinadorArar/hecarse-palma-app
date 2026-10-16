<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Administracion\PesoPromedioRacimosLote;
use App\Libraries\Informes\Informe;

class LaboresLoteFecha extends Informe
{
    public function opciones(string $filtro, array $dependencias): array
    {
        return $filtro === 'lote' ? (new PesoPromedioRacimosLote($this->empresa))->opciones('lote', $dependencias) : [];
    }

    public function filtros(): array
    {
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca elegida.', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
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

        if (! is_array($v)) {
            return $v;
        }

        if ($v['fechaInicial'] > $v['fechaFinal']) {
            return 'La fecha inicial no puede ser mayor que la final.';
        }

        if ($v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year'))) {
            return 'El rango no puede superar un año.';
        }

        return $v['finca'] !== '' && $v['lote'] !== '' && explode('|', $v['lote'], 2)[0] !== $v['finca'] ? 'El lote no pertenece a la finca seleccionada.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];
        $lote  = $filtros['lote'] === '' ? ['', ''] : explode('|', $filtros['lote'], 2);

        foreach (['c.finca' => $filtros['finca'] !== '' ? $filtros['finca'] : $lote[0], 'b.lote' => $lote[1], 'd.grupo' => $filtros['grupo']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT lote, loteNombre, anioSiembra, palmas, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio,
                       SUM(cantidad) AS cantidad, SUM(valor) AS valor, SUM(CASE WHEN valor < 0 THEN valor ELSE 0 END) AS negativos, COUNT(*) AS registros
                  FROM (SELECT LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, ISNULL(k.[añoSiembra], 0) AS anioSiembra,
                               ISNULL(k.palmasProduccion, 0) AS palmas, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo,
                               CAST(ISNULL(g.descripcion, '') AS varchar(100)) AS grupoNombre, LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre,
                               LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha, ISNULL(c.precioLabor, 0) AS precio,
                               CASE WHEN EXISTS (SELECT 1 FROM nFuncionario f WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero))) THEN 1 ELSE 0 END AS contratista,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                          JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                          LEFT JOIN aGrupoNovedad g ON g.codigo = d.grupo AND g.empresa = d.empresa
                          LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                          LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                         WHERE c.empresa = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}) x
                 GROUP BY lote, loteNombre, anioSiembra, palmas, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $query  = $this->db->query($sql, $binds);
        $avisos = [];
        $filas  = [];
        $lotes  = [];
        $fechas = [];
        $grupos = [];
        $fincas = [];
        $neg    = 0.0;
        $porLabor = [];
        $sinReg = 0;
        $sinVal = 0.0;

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            $clave = $c['lote'] === '' ? 'Sin lote' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre']));
            $valor = (float) $c['valor'];
            $fecha = substr((string) $c['fecha'], 0, 10);
            $finca = trim($c['finca']);
            $f     = &$filas[$c['lote'] . '|' . $c['labor'] . '|' . $c['uMedida'] . '|' . $fecha];
            $porLabor[$c['lote'] . '|' . $c['labor'] . '|' . $c['uMedida']] = ($porLabor[$c['lote'] . '|' . $c['labor'] . '|' . $c['uMedida']] ?? 0) + $valor;
            $f['lote']        = $clave;
            $f['sinLote']     = $c['lote'] === '';
            $f['anioSiembra'] = (int) $c['anioSiembra'] === 0 ? '' : (string) (int) $c['anioSiembra'];
            $f['palmas']      = $c['lote'] === '' ? 0 : (int) $c['palmas'];
            $f['grupoCodigo'] = $c['grupo'];
            $f['grupoNombre'] = trim($c['grupoNombre']);
            $f['grupo']       = $c['grupo'] === '' ? 'Sin grupo' : $c['grupo'] . ' — ' . trim($c['grupoNombre']);
            $f['labor']       = $c['labor'] . ' — ' . trim($c['laborNombre']);
            $f['codLabor']    = $c['labor'];
            $f['claveLabor']  = $c['lote'] . '|' . $c['labor'] . '|' . $c['uMedida'];
            $f['uMedida']     = $c['uMedida'];
            $f['cantidad']    = ($f['cantidad'] ?? 0) + (float) $c['cantidad'];
            $f['valor']       = ($f['valor'] ?? 0) + $valor;
            $f['valorContratista'] = ($f['valorContratista'] ?? 0) + ((int) $c['contratista'] === 1 ? $valor : 0);
            $f['hay'][(int) $c['contratista']] = true;
            $f['precios'][(string) (float) $c['precio']] = true;
            $f['fecha']       = $fecha;
            $f['fincas'][$finca] = true;
            unset($f);

            $l = &$lotes[$clave];
            $l['valor']            = ($l['valor'] ?? 0) + $valor;
            $l['valorContratista'] = ($l['valorContratista'] ?? 0) + ((int) $c['contratista'] === 1 ? $valor : 0);
            $l['fechas'][$fecha]   = true;
            $l['labores'][$c['labor']] = true;
            $l['fincas'][$finca]   = true;
            $l['palmas']           = $c['lote'] === '' ? 0 : (int) $c['palmas'];
            $l['sinLote']          = $c['lote'] === '';
            $l['codigo']           = $c['lote'];
            unset($l);

            $fechas[$fecha]      = true;
            $grupos[$c['grupo']] = true;
            $fincas[$finca]      = true;
            $neg                += (float) $c['negativos'];

            if ($c['lote'] === '') {
                $sinReg += (int) $c['registros'];
                $sinVal += $valor;
            }
        }

        $query->freeResult();

        $filas = array_values($filas);

        usort($filas, static fn ($x, $y) => [$x['sinLote'], $lotes[$y['lote']]['valor'], $x['lote'], $porLabor[$y['claveLabor']], $x['claveLabor'], $x['fecha']] <=> [$y['sinLote'], $lotes[$x['lote']]['valor'], $y['lote'], $porLabor[$x['claveLabor']], $y['claveLabor'], $y['fecha']]);

        if (count($filas) > self::MAX_FILAS) {
            $filas    = array_slice($filas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas; el último lote puede estar incompleto. Filtre por finca, lote o grupo, o reduzca el rango.';
        }

        $porLote = array_count_values(array_column($filas, 'lote'));

        foreach ($filas as &$f) {
            $l = $lotes[$f['lote']];
            $p = $f['palmas'];
            $f = [
                'lote'                 => $f['lote'],
                'finca'                => implode(' · ', array_filter(array_map('strval', array_keys($f['fincas'])), static fn ($v) => $v !== '')),
                'anioSiembra'          => $f['anioSiembra'],
                'palmas'               => $p,
                'grupo'                => $f['grupo'],
                'labor'                => $f['labor'],
                'fecha'                => $f['fecha'],
                'uMedida'              => $f['uMedida'],
                'ejecucion'            => isset($f['hay'][1]) ? (isset($f['hay'][0]) ? 'Mixta' : 'Contratista') : 'Nómina',
                'cantidad'             => round($f['cantidad'], 2),
                'precio'               => round($f['cantidad'], 2) == 0 ? null : round($f['valor'] / $f['cantidad'], 2),
                'valor'                => round($f['valor'], 2),
                'costoPalma'           => $p > 0 ? round($f['valor'] / $p, 2) : null,
                'palmasPeso'           => $p > 0 ? $p / $porLote[$f['lote']] : 0,
                'valorConPalmas'       => $p > 0 ? round($f['valor'], 2) : 0,
                'valorContratista'     => round($f['valorContratista'], 2),
                'diasLote'             => count($l['fechas']),
                'negativo'             => $f['valor'] < 0,
                'sinCosto'             => $p === 0,
                'variosPrecios'        => count($f['precios']) > 1,
                'valorContratistaLote' => round($l['valorContratista'], 2),
                'valorLote'            => round($l['valor'], 2),
                'grupoCodigo'          => $f['grupoCodigo'],
                'grupoNombre'          => $f['grupoNombre'],
                'codLabor'             => $f['codLabor'],
            ];
        }

        unset($f);

        $conLote    = array_filter($lotes, static fn ($l) => ! $l['sinLote']);
        $sinPalmas  = count(array_filter($conLote, static fn ($l) => $l['palmas'] === 0));
        $maximo     = array_reduce(array_filter($conLote, static fn ($l) => $l['palmas'] > 0), static fn ($m, $l) => $m === null || $l['valor'] / $l['palmas'] > $m['valor'] / $m['palmas'] ? $l : $m);
        $palmas     = array_sum(array_column($conLote, 'palmas'));
        $conPalmas  = array_sum(array_column($filas, 'valorConPalmas'));
        $costo      = $palmas > 0 ? round($conPalmas / $palmas, 2) : null;
        $totales    = ['valor' => round(array_sum(array_column($filas, 'valor')), 2), 'costoPalma' => $costo];
        $nFincas    = count(array_filter(array_map('strval', array_keys($fincas)), static fn ($v) => $v !== ''));
        $nGrupos    = count(array_filter(array_map('strval', array_keys($grupos)), static fn ($v) => $v !== ''));

        if ($sinPalmas > 0) {
            $avisos[] = self::plural($sinPalmas, 'lote sin palmas en producción: no entra en el costo por palma.', 'lotes sin palmas en producción: no entran en el costo por palma.');
        }

        if ($sinReg > 0) {
            $avisos[] = self::plural($sinReg, 'registro sin lote', 'registros sin lote') . ' ($ ' . self::n($sinVal) . '): no entran en el costo por palma.';
        }

        uasort($conLote, static fn ($x, $y) => $y['valor'] <=> $x['valor']);

        $top      = array_slice(array_keys($conLote), 0, 15);
        $porGrupo = [];
        $nombres  = [];
        $matriz   = [];

        foreach ($filas as $f) {
            if (in_array($f['lote'], $top, true)) {
                $porGrupo[$f['grupoCodigo']] = ($porGrupo[$f['grupoCodigo']] ?? 0) + $f['valor'];
                $nombres[$f['grupoCodigo']]  = $f['grupoCodigo'] === '' || $f['grupoNombre'] === '' ? 'Sin grupo' : $f['grupoNombre'];
                $matriz[$f['grupoCodigo']][$f['lote']] = ($matriz[$f['grupoCodigo']][$f['lote']] ?? 0) + $f['valor'];
            }
        }

        arsort($porGrupo);

        $claves  = array_keys($porGrupo);
        $otros   = count($claves) > 6;
        $visibles = $otros ? array_slice($claves, 0, 5) : $claves;
        $dato    = static fn (float $v) => round($v, 2) == 0 ? null : round($v, 2);
        $series  = array_map(static fn ($g) => ['nombre' => $nombres[$g], 'datos' => array_map(static fn ($l) => $dato($matriz[$g][$l] ?? 0), $top)], $visibles);

        if ($otros) {
            $series[] = ['nombre' => 'Otros grupos', 'datos' => array_map(static fn ($l) => $dato(array_sum(array_map(static fn ($g) => $matriz[$g][$l] ?? 0, array_slice($claves, 5)))), $top)];
        }

        $paleta  = ['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500', '#6f8fb3'];
        $i       = 0;
        $colores = array_map(static function ($s) use ($paleta, &$i) {
            return in_array($s['nombre'], ['Otros grupos', 'Sin grupo'], true) ? '#9aa5b1' : $paleta[$i++];
        }, $series);

        $nLabores = count(array_unique(array_column($filas, 'codLabor')));

        foreach ($filas as &$f) {
            unset($f['grupoCodigo'], $f['grupoNombre'], $f['codLabor']);
        }

        unset($f);

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total ejecutado', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $neg < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($neg)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Lotes intervenidos', 'valor' => count($conLote), 'formato' => 'entero', 'icono' => 'bi bi-geo-alt',
                    'ayuda' => implode(' · ', array_filter([$nFincas > 0 ? self::plural($nFincas, 'finca', 'fincas') : '', $sinReg > 0 ? self::plural($sinReg, 'registro sin lote', 'registros sin lote') . ' ($ ' . self::n($sinVal) . ')' : '']))],
                ['etiqueta' => 'Labores distintas', 'valor' => $nLabores, 'formato' => 'entero', 'icono' => 'bi bi-list-check',
                    'ayuda' => $lotes === [] ? '' : self::plural($nGrupos, 'grupo de labor', 'grupos de labor') . ' · ' . self::plural(count($fechas), 'día con labor', 'días con labor')],
                ['etiqueta' => 'Costo promedio por palma', 'valor' => $costo, 'formato' => 'moneda', 'icono' => 'bi bi-tree',
                    'ayuda' => $conLote === [] ? '' : 'Ponderado sobre ' . self::n($palmas) . ' palmas en producción' . ($sinPalmas > 0 ? ' · ' . self::plural($sinPalmas, 'lote sin palmas excluido', 'lotes sin palmas excluidos') : '')
                        . ($maximo === null ? '' : ' · Máximo: $ ' . self::n($maximo['valor'] / $maximo['palmas']) . ' · ' . $maximo['codigo'] . ' (' . self::plural($maximo['palmas'], 'palma', 'palmas') . ')')],
            ],
            'grafica' => $top === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => true,
                'titulo'     => 'Top 15 lotes por valor ejecutado',
                'categorias' => $top,
                'series'     => $series,
                'colores'    => $colores,
                'detalle'    => array_map(static fn ($l) => [
                    'finca'      => implode(' · ', array_filter(array_map('strval', array_keys($lotes[$l]['fincas'])), static fn ($v) => $v !== '')),
                    'palmas'     => $lotes[$l]['palmas'],
                    'costoPalma' => $lotes[$l]['palmas'] > 0 ? round($lotes[$l]['valor'] / $lotes[$l]['palmas'], 2) : null,
                    'labores'    => count($lotes[$l]['labores']),
                    'dias'       => count($lotes[$l]['fechas']),
                ], $top),
                'ejeX'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'grupo', 'titulo' => 'Grupo labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha ejecución', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'ejecucion', 'titulo' => 'Ejecución', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false, 'ayuda' => 'Nómina, Contratista o Mixta según los trabajadores de la labor.'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Suma por labor y fecha; no se totaliza porque mezcla unidades.'],
                ['clave' => 'precio', 'titulo' => 'Precio prom.', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Valor / cantidad.'],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
                ['clave' => 'costoPalma', 'titulo' => 'Costo/palma', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'valorConPalmas', 'denominador' => 'palmasPeso',
                    'ayuda' => 'Valor / palmas en producción del lote. Subtotal y total ponderados; excluyen sin lote y lotes sin palmas.'],
                ['clave' => 'palmasPeso', 'titulo' => 'Palmas peso', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valorConPalmas', 'titulo' => 'Valor con palmas', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valorContratista', 'titulo' => 'Valor contratista', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'diasLote', 'titulo' => 'Días lote', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'lote',
            'avisos'  => $avisos,
        ];
    }
}
