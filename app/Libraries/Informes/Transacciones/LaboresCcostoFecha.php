<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LaboresCcostoFecha extends Informe
{
    private const CCOSTO = "COALESCE(NULLIF(LTRIM(RTRIM(c.ccosto)), ''), LTRIM(RTRIM(ISNULL(k.ccosto, ''))))";

    public function filtros(): array
    {
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );
        $centros = array_map(
            static fn ($c) => ['valor' => $c['codigo'], 'texto' => $c['codigo'] . ' — ' . trim($c['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM cCentrosCosto WHERE empresa = ? ORDER BY LTRIM(RTRIM(codigo)) ASC", [$this->empresa])->getResultArray()
        );

        if ((int) $this->db->query('SELECT CASE WHEN EXISTS (SELECT 1 FROM aTransaccionTercero c JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa WHERE c.empresa = ? AND ' . self::CCOSTO . " = '') THEN 1 ELSE 0 END AS hay", [$this->empresa])->getRow()->hay === 1) {
            $centros[] = ['valor' => '__SIN__', 'texto' => 'Sin centro de costo'];
        }

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'ccosto', 'etiqueta' => 'Centro de costo', 'tipo' => count($centros) <= 15 ? 'select' : 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los centros de costo', 'ayuda' => 'Centro de costo de la línea o, si no tiene, el del lote.', 'opciones' => $centros],
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

        return $v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year')) ? 'El rango no puede superar un año.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        $cc    = self::CCOSTO;

        foreach (['LTRIM(RTRIM(c.finca))' => $filtros['finca'], $cc => $filtros['ccosto'] === '__SIN__' ? '' : $filtros['ccosto'], 'LTRIM(RTRIM(d.grupo))' => $filtros['grupo']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND {$campo} = ?";
                $binds[] = $valor;
            }
        }

        if ($filtros['ccosto'] === '__SIN__') {
            $where .= " AND {$cc} = ''";
        }

        $sql = "SELECT ccosto, ccostoNombre, existe, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio,
                       SUM(cantidad) AS cantidad, SUM(valor) AS valor, SUM(CASE WHEN valor < 0 THEN valor ELSE 0 END) AS negativos, COUNT(*) AS registros, SUM(deLote) AS deLote
                  FROM (SELECT {$cc} AS ccosto, CAST(ISNULL(cc.descripcion, '') AS varchar(350)) AS ccostoNombre, CASE WHEN cc.codigo IS NULL THEN 0 ELSE 1 END AS existe,
                               CASE WHEN LTRIM(RTRIM(ISNULL(c.ccosto, ''))) = '' AND LTRIM(RTRIM(ISNULL(k.ccosto, ''))) <> '' THEN 1 ELSE 0 END AS deLote,
                               CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo,
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
                 GROUP BY ccosto, ccostoNombre, existe, finca, grupo, grupoNombre, labor, laborNombre, uMedida, fecha, contratista, precio";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $query   = $this->db->query($sql, $binds);
        $avisos  = [];
        $filas   = [];
        $centros = [];
        $fechas  = [];
        $grupos  = [];
        $fincas  = [];
        $labores = [];
        $neg     = 0.0;
        $sinReg  = 0;
        $sinVal  = 0.0;
        $deLote  = 0;
        $porLabor = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            $nombre = (int) $c['existe'] === 1 ? trim($c['ccostoNombre']) : 'Centro de costo no encontrado';
            $clave  = $c['ccosto'] === '' ? 'Sin centro de costo' : $c['ccosto'] . ' — ' . $nombre;
            $valor  = (float) $c['valor'];
            $fecha  = substr((string) $c['fecha'], 0, 10);
            $finca  = trim($c['finca']);
            $contr  = (int) $c['contratista'] === 1 ? $valor : 0;
            $f      = &$filas[$c['ccosto'] . '|' . $c['labor'] . '|' . $c['uMedida'] . '|' . $fecha];
            $porLabor[$c['ccosto'] . '|' . $c['labor'] . '|' . $c['uMedida']] = ($porLabor[$c['ccosto'] . '|' . $c['labor'] . '|' . $c['uMedida']] ?? 0) + $valor;
            $f['claveLabor'] = $c['ccosto'] . '|' . $c['labor'] . '|' . $c['uMedida'];
            $f['ccosto']   = $clave;
            $f['grupo']    = $c['grupo'] === '' ? 'Sin grupo' : $c['grupo'] . ' — ' . trim($c['grupoNombre']);
            $f['labor']    = $c['labor'] . ' — ' . trim($c['laborNombre']);
            $f['uMedida']  = $c['uMedida'];
            $f['cantidad'] = ($f['cantidad'] ?? 0) + (float) $c['cantidad'];
            $f['valor']    = ($f['valor'] ?? 0) + $valor;
            $f['valorContratista'] = ($f['valorContratista'] ?? 0) + $contr;
            $f['hay'][(int) $c['contratista']] = true;
            $f['precios'][(string) (float) $c['precio']] = true;
            $f['fecha']    = $fecha;
            $f['fincas'][$finca] = true;
            unset($f);

            $g = &$centros[$clave];
            $g['nombre']           = $c['ccosto'] === '' ? 'Sin centro de costo' : $nombre;
            $g['sin']              = $c['ccosto'] === '';
            $g['existe']           = (int) $c['existe'] === 1;
            $g['valor']            = ($g['valor'] ?? 0) + $valor;
            $g['valorContratista'] = ($g['valorContratista'] ?? 0) + $contr;
            $g['fechas'][$fecha]   = true;
            $g['labores'][$c['labor']] = true;
            $g['fincas'][$finca]   = true;
            unset($g);

            $fechas[$fecha]         = true;
            $grupos[$c['grupo']]    = true;
            $fincas[$finca]         = true;
            $labores[$c['labor']]   = true;
            $neg                   += (float) $c['negativos'];
            $deLote                += (int) $c['deLote'];

            if ($c['ccosto'] === '') {
                $sinReg += (int) $c['registros'];
                $sinVal += $valor;
            }
        }

        $query->freeResult();

        $filas = array_values($filas);

        usort($filas, static fn ($x, $y) => [$centros[$x['ccosto']]['sin'], $centros[$y['ccosto']]['valor'], $x['ccosto'], $porLabor[$y['claveLabor']], $x['claveLabor'], $x['fecha']] <=> [$centros[$y['ccosto']]['sin'], $centros[$x['ccosto']]['valor'], $y['ccosto'], $porLabor[$x['claveLabor']], $y['claveLabor'], $y['fecha']]);

        if (count($filas) > self::MAX_FILAS) {
            $filas    = array_slice($filas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas; el último centro de costo puede estar incompleto. Filtre por finca, centro de costo o grupo, o reduzca el rango.';
        }

        $total = array_sum(array_column($centros, 'valor'));
        $pct   = static fn (float $v) => $total == 0 ? 0 : $v / $total;

        foreach ($filas as &$f) {
            $g = $centros[$f['ccosto']];
            $f = [
                'ccosto'                 => $f['ccosto'],
                'finca'                  => implode(' · ', self::lista($f['fincas'])),
                'grupo'                  => $f['grupo'],
                'labor'                  => $f['labor'],
                'fecha'                  => $f['fecha'],
                'uMedida'                => $f['uMedida'],
                'ejecucion'              => isset($f['hay'][1]) ? (isset($f['hay'][0]) ? 'Mixta' : 'Contratista') : 'Nómina',
                'cantidad'               => round($f['cantidad'], 2),
                'precio'                 => round($f['cantidad'], 2) == 0 ? null : round($f['valor'] / $f['cantidad'], 2),
                'valor'                  => round($f['valor'], 2),
                'valorContratista'       => round($f['valorContratista'], 2),
                'negativo'               => $f['valor'] < 0,
                'variosPrecios'          => count($f['precios']) > 1,
                'pctCcosto'              => $pct($g['valor']),
                'laboresCcosto'          => count($g['labores']),
                'diasCcosto'             => count($g['fechas']),
                'valorCcosto'            => round($g['valor'], 2),
                'valorContratistaCcosto' => round($g['valorContratista'], 2),
                'fincasCcosto'           => self::lista($g['fincas']),
                'sinCcosto'              => $g['sin'],
            ];
        }

        unset($f);

        $conCentro  = array_filter($centros, static fn ($g) => ! $g['sin']);
        $noExisten  = count(array_filter($conCentro, static fn ($g) => ! $g['existe']));
        $totales    = ['valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $nFincas    = count(self::lista($fincas));
        $nGrupos    = count(self::lista($grupos));

        if ($sinReg > 0) {
            $avisos[] = self::plural($sinReg, 'registro sin centro de costo', 'registros sin centro de costo') . ' ($ ' . self::n($sinVal) . '): se agrupan al final.';
        }

        if ($noExisten > 0) {
            $avisos[] = self::plural($noExisten, 'centro de costo no existe en el maestro.', 'centros de costo no existen en el maestro.');
        }

        uasort($conCentro, static fn ($x, $y) => $y['valor'] <=> $x['valor']);

        $mayor = $conCentro === [] ? null : reset($conCentro);
        $top   = array_slice(array_keys($conCentro), 0, 10);
        $resto = array_diff_key($centros, array_flip($top));
        $cats  = $top;
        $datos = array_map(static fn ($k) => round($centros[$k]['valor'], 2), $top);
        $det   = array_map(static function ($k) use ($centros, $pct) {
            $fs = self::lista($centros[$k]['fincas']);

            return [
                'pct'         => $pct($centros[$k]['valor']),
                'labores'     => count($centros[$k]['labores']),
                'dias'        => count($centros[$k]['fechas']),
                'fincas'      => count($fs) > 3 ? count($fs) . ' fincas' : implode(' · ', $fs),
                'contratista' => round($centros[$k]['valorContratista'], 2),
            ];
        }, $top);

        if ($resto !== []) {
            $v       = array_sum(array_column($resto, 'valor'));
            $reales  = count(array_filter($resto, static fn ($g) => ! $g['sin']));
            $cats[]  = $reales === 0 ? 'Sin centro de costo' : 'Otros';
            $datos[] = round($v, 2);
            $det[]   = array_filter([
                'pct'         => $pct($v),
                'labores'     => count(array_replace(...array_map(static fn ($g) => $g['labores'], array_values($resto)))),
                'dias'        => count(array_replace(...array_map(static fn ($g) => $g['fechas'], array_values($resto)))),
                'centros'     => $reales === 0 ? null : $reales,
                'contratista' => round(array_sum(array_column($resto, 'valorContratista')), 2),
            ], static fn ($x) => $x !== null);
        }

        foreach ($det as &$d) {
            $d = array_filter($d, static fn ($v, $k) => ! in_array($k, ['fincas', 'contratista'], true) || ! in_array($v, ['', 0, 0.0], true), ARRAY_FILTER_USE_BOTH);
        }

        unset($d);

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total ejecutado', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $neg < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($neg)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Centros de costo con movimiento', 'valor' => count($conCentro), 'formato' => 'entero', 'icono' => 'bi bi-diagram-3',
                    'ayuda' => implode(' · ', array_filter([$nFincas > 0 ? self::plural($nFincas, 'finca', 'fincas') : '', $sinReg > 0 ? self::plural($sinReg, 'registro sin centro de costo', 'registros sin centro de costo') . ' ($ ' . self::n($sinVal) . ')' : '', $deLote > 0 ? self::plural($deLote, 'registro con centro de costo del lote', 'registros con centro de costo del lote') : '']))],
                ['etiqueta' => 'Centro de costo de mayor valor', 'valor' => $mayor['nombre'] ?? null, 'formato' => 'texto', 'icono' => 'bi bi-award',
                    'ayuda' => $mayor === null ? '' : '$ ' . self::n($mayor['valor']) . ' · ' . self::n($pct($mayor['valor']) * 100, 1) . '% del total · ' . self::plural(count($mayor['labores']), 'labor', 'labores')],
                ['etiqueta' => 'Labores distintas', 'valor' => count($labores), 'formato' => 'entero', 'icono' => 'bi bi-list-check',
                    'ayuda' => $centros === [] ? '' : self::plural($nGrupos, 'grupo de labor', 'grupos de labor') . ' · ' . self::plural(count($fechas), 'día con labor', 'días con labor')],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Top 10 centros de costo por valor ejecutado',
                'categorias' => $cats,
                'series'     => [['nombre' => 'Valor', 'datos' => $datos]],
                'detalle'    => $det,
                'ejeX'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'ccosto', 'titulo' => 'Centro de costo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'grupo', 'titulo' => 'Grupo labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha ejecución', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'ejecucion', 'titulo' => 'Ejecución', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false, 'ayuda' => 'Nómina, Contratista o Mixta según los trabajadores de la labor.'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Suma por labor y fecha; no se totaliza porque mezcla unidades.'],
                ['clave' => 'precio', 'titulo' => 'Precio prom.', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Valor / cantidad.'],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
                ['clave' => 'valorContratista', 'titulo' => 'Valor contratista', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'ccosto',
            'avisos'  => $avisos,
        ];
    }
}
