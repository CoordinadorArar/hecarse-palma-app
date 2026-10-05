<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Informe;

class LaboresCcostoFechaLote extends Informe
{
    private const CCOSTO = "COALESCE(NULLIF(LTRIM(RTRIM(c.ccosto)), ''), LTRIM(RTRIM(ISNULL(k.ccosto, ''))))";

    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function opciones(string $filtro, array $dependencias): array
    {
        return (new LaboresLoteFecha($this->empresa))->opciones($filtro, $dependencias);
    }

    public function filtros(): array
    {
        $c = array_column((new LaboresCcostoFecha($this->empresa))->filtros(), null, 'clave');

        return [
            $c['fechaInicial'],
            $c['fechaFinal'],
            $c['ccosto'],
            $c['finca'],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca elegida.', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
            $c['grupo'],
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

    private static function lista(array $claves): array
    {
        return array_values(array_filter(array_map('strval', array_keys($claves)), static fn ($v) => $v !== ''));
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
        $cc    = self::CCOSTO;
        $lote  = $filtros['lote'] === '' ? ['', ''] : explode('|', $filtros['lote'], 2);

        foreach (['LTRIM(RTRIM(c.finca))' => $filtros['finca'] !== '' ? $filtros['finca'] : $lote[0], 'LTRIM(RTRIM(b.lote))' => $lote[1], $cc => $filtros['ccosto'] === '__SIN__' ? '' : $filtros['ccosto'], 'LTRIM(RTRIM(d.grupo))' => $filtros['grupo']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND {$campo} = ?";
                $binds[] = $valor;
            }
        }

        if ($filtros['ccosto'] === '__SIN__') {
            $where .= " AND {$cc} = ''";
        }

        $sql = "SELECT ccosto, ccostoNombre, existe, lote, loteNombre, anioSiembra, palmas, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio,
                       SUM(cantidad) AS cantidad, SUM(valor) AS valor, SUM(CASE WHEN valor < 0 THEN valor ELSE 0 END) AS negativos, COUNT(*) AS registros, SUM(deLote) AS deLote
                  FROM (SELECT {$cc} AS ccosto, CAST(ISNULL(cc.descripcion, '') AS varchar(350)) AS ccostoNombre, CASE WHEN cc.codigo IS NULL THEN 0 ELSE 1 END AS existe,
                               CASE WHEN LTRIM(RTRIM(ISNULL(c.ccosto, ''))) = '' AND LTRIM(RTRIM(ISNULL(k.ccosto, ''))) <> '' THEN 1 ELSE 0 END AS deLote,
                               LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, ISNULL(k.[añoSiembra], 0) AS anioSiembra,
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
                          LEFT JOIN cCentrosCosto cc ON cc.empresa = c.empresa AND LTRIM(RTRIM(cc.codigo)) = {$cc}
                         WHERE c.empresa = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}) x
                 GROUP BY ccosto, ccostoNombre, existe, lote, loteNombre, anioSiembra, palmas, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $mensual  = substr($filtros['fechaInicial'], 0, 7) !== substr($filtros['fechaFinal'], 0, 7);
        $query    = $this->db->query($sql, $binds);
        $avisos   = [];
        $filas    = [];
        $centros  = [];
        $cl       = [];
        $pl       = [];
        $lotes    = [];
        $fincas   = [];
        $neg      = 0.0;
        $sinReg   = 0;
        $sinVal   = 0.0;
        $deLote   = 0;
        $sinLReg  = 0;
        $sinLVal  = 0.0;

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            $nombre = (int) $c['existe'] === 1 ? trim($c['ccostoNombre']) : 'Centro de costo no encontrado';
            $clave  = $c['ccosto'] === '' ? 'Sin centro de costo' : $c['ccosto'] . ' — ' . $nombre;
            $lt     = $c['lote'] === '' ? 'Sin lote' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre']));
            $palmas = $c['lote'] === '' ? 0 : (int) $c['palmas'];
            $anio   = (int) $c['anioSiembra'] === 0 ? '' : (string) (int) $c['anioSiembra'];
            $valor  = (float) $c['valor'];
            $fecha  = substr((string) $c['fecha'], 0, 10);
            $mes    = $mensual ? substr($fecha, 0, 7) : '';
            $finca  = trim($c['finca']);
            $contr  = (int) $c['contratista'] === 1 ? $valor : 0;
            $f      = &$filas[$clave . '|' . $lt . '|' . $mes . '|' . $c['labor'] . '|' . $c['uMedida']];
            $f['ccosto']      = $clave;
            $f['lote']        = $lt;
            $f['sinLote']     = $c['lote'] === '';
            $f['anioSiembra'] = $anio;
            $f['palmas']      = $palmas;
            $f['mes']         = $mes;
            $f['grupo']       = $c['grupo'] === '' ? 'Sin grupo' : $c['grupo'] . ' — ' . trim($c['grupoNombre']);
            $f['labor']       = $c['labor'] . ' — ' . trim($c['laborNombre']);
            $f['uMedida']     = $c['uMedida'];
            $f['cantidad']    = ($f['cantidad'] ?? 0) + (float) $c['cantidad'];
            $f['valor']       = ($f['valor'] ?? 0) + $valor;
            $f['valorContratista'] = ($f['valorContratista'] ?? 0) + $contr;
            $f['hay'][(int) $c['contratista']] = true;
            $f['precios'][(string) (float) $c['precio']] = true;
            $f['fechas'][$fecha] = true;
            $f['fincas'][$finca] = true;
            unset($f);

            $g = &$centros[$clave];
            $g['nombre']           = $c['ccosto'] === '' ? 'Sin centro de costo' : $nombre;
            $g['sin']              = $c['ccosto'] === '';
            $g['existe']           = (int) $c['existe'] === 1;
            $g['valor']            = ($g['valor'] ?? 0) + $valor;
            $g['valorContratista'] = ($g['valorContratista'] ?? 0) + $contr;
            $g['conPalmas']        = ($g['conPalmas'] ?? 0) + ($palmas > 0 ? $valor : 0);
            $g['fechas'][$fecha]   = true;
            $g['labores'][$c['labor']] = true;
            $g['lotes']            ??= [];

            if ($c['lote'] !== '') {
                $g['lotes'][$lt] = $palmas;
                $g['anios'][$lt] = $anio;
            }

            unset($g);

            $cl[$clave][$lt] = ($cl[$clave][$lt] ?? 0) + $valor;
            $pl[$clave . '|' . $lt][$c['labor']] = true;

            $l = &$lotes[$lt];
            $l['valor']   = ($l['valor'] ?? 0) + $valor;
            $l['palmas']  = $palmas;
            $l['sinLote'] = $c['lote'] === '';
            $l['codigo']  = $c['lote'];
            unset($l);

            $fincas[$finca] = true;
            $neg           += (float) $c['negativos'];
            $deLote        += (int) $c['deLote'];

            if ($c['ccosto'] === '') {
                $sinReg += (int) $c['registros'];
                $sinVal += $valor;
            }

            if ($c['lote'] === '') {
                $sinLReg += (int) $c['registros'];
                $sinLVal += $valor;
            }
        }

        $query->freeResult();

        $filas = array_values($filas);

        usort($filas, static fn ($x, $y) => [$centros[$x['ccosto']]['sin'], $centros[$y['ccosto']]['valor'], $x['ccosto'], $x['sinLote'], $cl[$y['ccosto']][$y['lote']], $x['lote'], $x['mes'], $y['valor']]
            <=> [$centros[$y['ccosto']]['sin'], $centros[$x['ccosto']]['valor'], $y['ccosto'], $y['sinLote'], $cl[$x['ccosto']][$x['lote']], $y['lote'], $y['mes'], $x['valor']]);

        if (count($filas) > self::MAX_FILAS) {
            $filas    = array_slice($filas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas; el último centro de costo puede estar incompleto. Filtre por centro de costo, finca, lote o grupo, o reduzca el rango.';
        }

        $total   = array_sum(array_column($centros, 'valor'));
        $pct     = static fn (float $v) => $total == 0 ? 0 : $v / $total;
        $costoG  = static fn (array $g) => array_sum($g['lotes']) > 0 ? $g['conPalmas'] / array_sum($g['lotes']) : null;
        $porLote = array_count_values(array_map(static fn ($f) => $f['ccosto'] . '|' . $f['lote'], $filas));

        foreach ($filas as &$f) {
            $g  = $centros[$f['ccosto']];
            $p  = $f['palmas'];
            $vl = $cl[$f['ccosto']][$f['lote']];
            $uno = count($g['lotes']) === 1 ? (string) array_key_first($g['lotes']) : '';
            $f = array_merge([
                'ccosto'      => $f['ccosto'],
                'finca'       => implode(' · ', self::lista($f['fincas'])),
                'lote'        => $f['lote'],
                'anioSiembra' => $f['anioSiembra'],
            ], $mensual ? ['mes' => self::MESES[(int) substr($f['mes'], 5, 2) - 1] . ' ' . substr($f['mes'], 0, 4)] : [], [
                'grupo'                  => $f['grupo'],
                'labor'                  => $f['labor'],
                'uMedida'                => $f['uMedida'],
                'ejecucion'              => isset($f['hay'][1]) ? (isset($f['hay'][0]) ? 'Mixta' : 'Contratista') : 'Nómina',
                'palmas'                 => $p,
                'cantidad'               => round($f['cantidad'], 2),
                'precio'                 => round($f['cantidad'], 2) == 0 ? null : round($f['valor'] / $f['cantidad'], 2),
                'dias'                   => count($f['fechas']),
                'valor'                  => round($f['valor'], 2),
                'costoPalma'             => $p > 0 ? round($f['valor'] / $p, 2) : null,
                'palmasPeso'             => $p > 0 ? $p / $porLote[$f['ccosto'] . '|' . $f['lote']] : 0,
                'valorConPalmas'         => $p > 0 ? round($f['valor'], 2) : 0,
                'valorContratista'       => round($f['valorContratista'], 2),
                'valorLote'              => round($vl, 2),
                'pctLote'                => $g['valor'] == 0 ? 0 : $vl / $g['valor'],
                'negativo'               => $f['valor'] < 0,
                'variosPrecios'          => count($f['precios']) > 1,
                'sinCosto'               => $p === 0,
                'pctCcosto'              => $pct($g['valor']),
                'lotesCcosto'            => count($g['lotes']),
                'loteUnico'              => $uno,
                'anioUnico'              => $uno === '' ? '' : $g['anios'][$uno],
                'palmasCcosto'           => array_sum($g['lotes']),
                'costoPalmaCcosto'       => $costoG($g),
                'diasCcosto'             => count($g['fechas']),
                'valorContratistaCcosto' => round($g['valorContratista'], 2),
                'valorCcosto'            => round($g['valor'], 2),
                'sinCcosto'              => $g['sin'],
            ]);
        }

        unset($f);

        $conCentro = array_filter($centros, static fn ($g) => ! $g['sin']);
        $noExisten = count(array_filter($conCentro, static fn ($g) => ! $g['existe']));
        $conLote   = array_filter($lotes, static fn ($l) => ! $l['sinLote']);
        $sinPalmas = count(array_filter($conLote, static fn ($l) => $l['palmas'] === 0));
        $maximo    = array_reduce(array_filter($conLote, static fn ($l) => $l['palmas'] > 0), static fn ($m, $l) => $m === null || $l['valor'] / $l['palmas'] > $m['valor'] / $m['palmas'] ? $l : $m);
        $palmas    = array_sum(array_column($conLote, 'palmas'));
        $costo     = $palmas > 0 ? round(array_sum(array_column($filas, 'valorConPalmas')) / $palmas, 2) : null;
        $totales   = ['valor' => round(array_sum(array_column($filas, 'valor')), 2), 'costoPalma' => $costo];
        $nFincas   = count(self::lista($fincas));

        if ($sinReg > 0) {
            $avisos[] = self::plural($sinReg, 'registro sin centro de costo', 'registros sin centro de costo') . ' ($ ' . self::n($sinVal) . '): se agrupan al final.';
        }

        if ($noExisten > 0) {
            $avisos[] = self::plural($noExisten, 'centro de costo no existe en el maestro.', 'centros de costo no existen en el maestro.');
        }

        if ($sinPalmas > 0) {
            $avisos[] = self::plural($sinPalmas, 'lote sin palmas en producción: no entra en el costo por palma.', 'lotes sin palmas en producción: no entran en el costo por palma.');
        }

        if ($sinLReg > 0) {
            $avisos[] = self::plural($sinLReg, 'registro sin lote', 'registros sin lote') . ' ($ ' . self::n($sinLVal) . '): no entran en el costo por palma.';
        }

        uasort($conCentro, static fn ($x, $y) => $y['valor'] <=> $x['valor']);

        $varios = array_filter($conCentro, static fn ($g) => count($g['lotes']) > 1);
        $info   = static fn (array $g) => array_filter([
            'pct'        => $pct($g['valor']),
            'lotes'      => count($g['lotes']),
            'palmas'     => array_sum($g['lotes']) > 0 ? array_sum($g['lotes']) : null,
            'costoPalma' => $costoG($g),
            'labores'    => count($g['labores']),
        ], static fn ($v) => $v !== null);

        if ($varios !== []) {
            $pares = [];

            foreach (array_keys($varios) as $k) {
                foreach ($cl[$k] as $l => $v) {
                    $pares[] = [$k, (string) $l, $v];
                }
            }

            usort($pares, static fn ($x, $y) => $y[2] <=> $x[2]);

            $top   = array_slice($pares, 0, 15);
            $resto = array_slice($pares, 15);
            $cats  = array_column($top, 1);
            $datos = array_map(static fn ($p) => round($p[2], 2), $top);
            $det   = array_map(static function ($p) use ($centros, $pl) {
                $palmas = $centros[$p[0]]['lotes'][$p[1]] ?? 0;

                return array_filter([
                    'centro'     => $p[0],
                    'pctCentro'  => $centros[$p[0]]['valor'] == 0 ? 0 : $p[2] / $centros[$p[0]]['valor'],
                    'palmas'     => $palmas > 0 ? $palmas : null,
                    'costoPalma' => $palmas > 0 ? round($p[2] / $palmas, 2) : null,
                    'labores'    => count($pl[$p[0] . '|' . $p[1]]),
                ], static fn ($v) => $v !== null);
            }, $top);

            if ($resto !== []) {
                $cats[]  = 'Otros lotes';
                $datos[] = round(array_sum(array_column($resto, 2)), 2);
                $det[]   = ['lotes' => count($resto), 'labores' => count(array_replace(...array_map(static fn ($p) => $pl[$p[0] . '|' . $p[1]], $resto)))];
            }

            $grafica = [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Top 15 lotes en centros de costo con varios lotes',
                'categorias' => $cats,
                'series'     => [['nombre' => 'Valor', 'datos' => $datos]],
                'detalle'    => $det,
            ];
        } else {
            $top   = array_slice(array_keys($conCentro), 0, 10);
            $resto = array_diff_key($centros, array_flip($top));
            $cats  = $top;
            $datos = array_map(static fn ($k) => round($centros[$k]['valor'], 2), $top);
            $det   = array_map(static fn ($k) => array_diff_key($info($centros[$k]), ['lotes' => 0]), $top);

            if ($resto !== []) {
                $reales  = count(array_filter($resto, static fn ($g) => ! $g['sin']));
                $cats[]  = $reales === 0 ? 'Sin centro de costo' : 'Otros';
                $datos[] = round(array_sum(array_column($resto, 'valor')), 2);
                $det[]   = array_diff_key($info([
                    'valor'     => array_sum(array_column($resto, 'valor')),
                    'conPalmas' => array_sum(array_column($resto, 'conPalmas')),
                    'lotes'     => array_replace(...array_column($resto, 'lotes')),
                    'labores'   => array_replace(...array_column($resto, 'labores')),
                ]), ['lotes' => 0]) + ($reales === 0 ? [] : ['centros' => $reales]);
            }

            $grafica = [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Top 10 centros de costo por valor ejecutado',
                'categorias' => $cats,
                'series'     => [['nombre' => 'Valor', 'datos' => $datos]],
                'detalle'    => $det,
            ];
        }

        $columnas = [
            ['clave' => 'ccosto', 'titulo' => 'Centro de costo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'mes', 'titulo' => 'Mes', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'grupo', 'titulo' => 'Grupo labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'ejecucion', 'titulo' => 'Ejecución', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false, 'ayuda' => 'Nómina, Contratista o Mixta según los trabajadores de la labor.'],
            ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Suma por labor; no se totaliza porque mezcla unidades.'],
            ['clave' => 'precio', 'titulo' => 'Precio prom.', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Valor / cantidad.'],
            ['clave' => 'dias', 'titulo' => 'Días', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Fechas distintas con esta labor en el lote.'],
            ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
            ['clave' => 'costoPalma', 'titulo' => 'Costo/palma', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'valorConPalmas', 'denominador' => 'palmasPeso', 'pie' => false,
                'ayuda' => 'Valor / palmas en producción del lote. Subtotal por centro ponderado; el promedio global está en el indicador.'],
            ['clave' => 'palmasPeso', 'titulo' => 'Palmas peso', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'valorConPalmas', 'titulo' => 'Valor con palmas', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'valorContratista', 'titulo' => 'Valor contratista', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'valorLote', 'titulo' => 'Valor lote', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'pctLote', 'titulo' => '% del centro', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => false],
        ];

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total ejecutado', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $neg < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($neg)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Centros de costo con movimiento', 'valor' => count($conCentro), 'formato' => 'entero', 'icono' => 'bi bi-diagram-3',
                    'ayuda' => implode(' · ', array_filter([$varios !== [] ? self::n(count($varios)) . ' con varios lotes' : '', $sinReg > 0 ? self::plural($sinReg, 'registro sin centro de costo', 'registros sin centro de costo') . ' ($ ' . self::n($sinVal) . ')' : '', $deLote > 0 ? self::plural($deLote, 'registro con centro de costo del lote', 'registros con centro de costo del lote') : '']))],
                ['etiqueta' => 'Lotes intervenidos', 'valor' => count($conLote), 'formato' => 'entero', 'icono' => 'bi bi-geo-alt',
                    'ayuda' => implode(' · ', array_filter([$nFincas > 0 ? self::plural($nFincas, 'finca', 'fincas') : '', $sinLReg > 0 ? self::plural($sinLReg, 'registro sin lote', 'registros sin lote') . ' ($ ' . self::n($sinLVal) . ')' : '']))],
                ['etiqueta' => 'Costo promedio por palma', 'valor' => $costo, 'formato' => 'moneda', 'icono' => 'bi bi-tree',
                    'ayuda' => $conLote === [] ? '' : 'Ponderado sobre ' . self::n($palmas) . ' palmas en producción' . ($sinPalmas > 0 ? ' · ' . self::plural($sinPalmas, 'lote sin palmas excluido', 'lotes sin palmas excluidos') : '')
                        . ($maximo === null ? '' : ' · Máximo: $ ' . self::n($maximo['valor'] / $maximo['palmas']) . ' · ' . $maximo['codigo'] . ' (' . self::plural($maximo['palmas'], 'palma', 'palmas') . ')')],
            ],
            'grafica'  => $filas === [] ? null : $grafica + ['ejeX' => 'Valor', 'formato' => 'moneda'],
            'columnas' => $mensual ? $columnas : array_values(array_filter($columnas, static fn ($c) => $c['clave'] !== 'mes')),
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'ccosto',
            'avisos'   => $avisos,
        ];
    }
}
