<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Administracion\PesoPromedioRacimosLote;
use App\Libraries\Informes\Informe;

class LiquidacionContratistasPeriodoLotes extends Informe
{
    private const SIN_PROVEEDOR = 'Proveedor no asociado';

    private const COLORES = ['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500', '#5b8def'];

    private const PROVEEDOR = "CASE WHEN pn.id IS NULL THEN pv.id ELSE pn.id END AS proveedor, LTRIM(RTRIM(ISNULL(ISNULL(pn.nit, pv.nit), ''))) AS nit, LTRIM(RTRIM(ISNULL(ISNULL(pn.dv, pv.dv), ''))) AS dv,
                              CAST(ISNULL(ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion)), '') AS varchar(150)) AS razon
                         FROM nFuncionario f
                         LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                         LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END";

    public function opciones(string $filtro, array $dependencias): array
    {
        return $filtro === 'lote' ? (new PesoPromedioRacimosLote($this->empresa))->opciones('lote', $dependencias) : [];
    }

    public function filtros(): array
    {
        $contratistas = [];

        foreach ($this->db->query('SELECT DISTINCT ' . self::PROVEEDOR . ' WHERE f.empresa = ? AND f.contratista = 1', [$this->empresa])->getResultArray() as $p) {
            if ($p['proveedor'] !== null) {
                $contratistas[(string) $p['proveedor']] = ['valor' => (string) $p['proveedor'], 'texto' => self::proveedor($p)];
            }
        }

        uasort($contratistas, static fn ($a, $b) => strcasecmp(explode(' — ', $a['texto'], 2)[1] ?? '', explode(' — ', $b['texto'], 2)[1] ?? ''));

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01')],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'contratista', 'etiqueta' => 'Contratista', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los contratistas', 'opciones' => array_values($contratistas)],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca elegida.', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
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

    private static function codigo(string $codigo, string $nombre): string
    {
        return $codigo === '' ? '' : $codigo . (trim($nombre) === '' ? '' : ' — ' . trim($nombre));
    }

    private static function proveedor(array $p): string
    {
        return $p['proveedor'] === null ? self::SIN_PROVEEDOR : $p['nit'] . ($p['dv'] === '' ? '' : '-' . $p['dv']) . ' — ' . trim($p['razon']);
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

        foreach (['c.finca' => $filtros['finca'] !== '' ? $filtros['finca'] : $lote[0], 'b.lote' => $lote[1]] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $valor;
            }
        }

        if ($filtros['contratista'] !== '') {
            $where  .= ' AND p.proveedor = ?';
            $binds[] = (int) $filtros['contratista'];
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " proveedor, nit, dv, razon, tercero, nombre, finca, seccion, lote, loteNombre, labor, laborNombre, uMedida, abreLote,
                       SUM(cantidad) AS cantidad, SUM(jornales) AS jornales, SUM(valor) AS valor, MIN(NULLIF(precio, 0)) AS precioMin, MAX(NULLIF(precio, 0)) AS precioMax,
                       SUM(CASE WHEN precio = 0 THEN 1 ELSE 0 END) AS sinPrecio, SUM(CASE WHEN negativo = 1 THEN 1 ELSE 0 END) AS negativos
                  FROM (SELECT p.proveedor, p.nit, p.dv, p.razon, LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre,
                               CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, CAST(ISNULL(NULLIF(LTRIM(RTRIM(c.seccion)), ''), LTRIM(RTRIM(ISNULL(k.seccion, '')))) AS varchar(50)) AS seccion,
                               CASE WHEN ISNULL(d.muestraInformeContratista, 0) = 1 OR ISNULL(d.manejaLote, 0) = 1 THEN 1 ELSE 0 END AS abreLote,
                               CASE WHEN ISNULL(d.muestraInformeContratista, 0) = 1 OR ISNULL(d.manejaLote, 0) = 1 THEN LTRIM(RTRIM(ISNULL(b.lote, ''))) ELSE '' END AS lote,
                               CASE WHEN ISNULL(d.muestraInformeContratista, 0) = 1 OR ISNULL(d.manejaLote, 0) = 1 THEN CAST(ISNULL(k.descripcion, '') AS varchar(100)) ELSE '' END AS loteNombre,
                               LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre, LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales, ISNULL(c.precioLabor, 0) AS precio,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, CASE WHEN b.signo = 2 THEN 1 ELSE 0 END AS negativo
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                          JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                          LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                          LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                         CROSS APPLY (SELECT TOP 1 " . self::PROVEEDOR . "
                                       WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero))
                                       ORDER BY CASE WHEN pn.id IS NULL AND pv.id IS NULL THEN 1 ELSE 0 END) p
                         OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                                       WHERE empresa = c.empresa AND (LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) OR LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.tercero)))
                                       ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) THEN 0 ELSE 1 END) t
                         WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}) x
                 GROUP BY proveedor, nit, dv, razon, tercero, nombre, finca, seccion, lote, loteNombre, labor, laborNombre, uMedida, abreLote
                 ORDER BY proveedor ASC, tercero ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '102400');
        ini_set('memory_limit', '256M');

        $query  = $this->db->query($sql, $binds);
        $avisos = [];
        $filas  = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas; el último contratista puede estar incompleto. Filtre por contratista, finca o lote, o reduzca el rango.';

                break;
            }

            $nombre   = trim($c['nombre']);
            $cantidad = (float) $c['cantidad'];
            $valor    = (float) $c['valor'];
            $min      = $c['precioMin'] === null ? null : (float) $c['precioMin'];
            $max      = $c['precioMax'] === null ? null : (float) $c['precioMax'];
            $filas[]  = [
                'contratista'   => self::proveedor($c),
                'trabajador'    => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'finca'         => trim($c['finca']),
                'seccion'       => trim($c['seccion']),
                'lote'          => self::codigo($c['lote'], $c['loteNombre']),
                'labor'         => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'uMedida'       => $c['uMedida'],
                'cantidad'      => $cantidad,
                'jornales'      => (float) $c['jornales'],
                'precio'        => $cantidad == 0 ? null : round($valor / $cantidad, 2),
                'valor'         => $valor,
                'faltaLote'     => (int) $c['abreLote'] === 1 && $c['lote'] === '',
                'sinPrecio'     => (int) $c['sinPrecio'] > 0 || ($valor == 0 && $cantidad != 0),
                'variosPrecios' => $min !== null && $min != $max,
                'precioMin'     => $min,
                'precioMax'     => $max,
                'conAjuste'     => (int) $c['negativos'] > 0,
                'netoNegativo'  => $valor < 0,
                'sinProveedor'  => $c['proveedor'] === null,
                'codigo'        => $c['tercero'],
                'nombre'        => $nombre,
                'codLote'       => $c['lote'],
                'codLabor'      => $c['labor'],
            ];
        }

        $query->freeResult();

        $total  = round(array_sum(array_column($filas, 'valor')), 2);
        $grupos = [];
        $trab   = [];

        foreach ($filas as $f) {
            $g = &$grupos[$f['contratista']];
            $g['valor']    = ($g['valor'] ?? 0) + $f['valor'];
            $g['jornales'] = ($g['jornales'] ?? 0) + $f['jornales'];
            $g['sinProveedor'] = $f['sinProveedor'];
            $g['trabajadores'][$f['codigo']] = true;
            $g['fincas'][$f['finca']] = true;
            $g['labores'][$f['codLabor']] = true;
            $g['faltaLoteN']     = ($g['faltaLoteN'] ?? 0) + (int) $f['faltaLote'];
            $g['sinPrecioN']     = ($g['sinPrecioN'] ?? 0) + (int) $f['sinPrecio'];
            $g['variosPreciosN'] = ($g['variosPreciosN'] ?? 0) + (int) $f['variosPrecios'];
            $g['ajusteN']        = ($g['ajusteN'] ?? 0) + (int) $f['conAjuste'];

            if ($f['codLote'] !== '') {
                $g['lotes'][$f['finca'] . '|' . $f['codLote']] = true;
            }

            unset($g);

            $t = &$trab[$f['contratista']][$f['codigo']];
            $t['jornales'] = ($t['jornales'] ?? 0) + $f['jornales'];
            $t['valor']    = ($t['valor'] ?? 0) + $f['valor'];
            unset($t);
        }

        uksort($grupos, static fn ($a, $b) => [$a === self::SIN_PROVEEDOR, -$grupos[$a]['valor']] <=> [$b === self::SIN_PROVEEDOR, -$grupos[$b]['valor']]);

        $orden = array_flip(array_keys($grupos));

        usort($filas, static fn ($a, $b) => [$orden[$a['contratista']], strcasecmp($a['nombre'], $b['nombre']), $a['codigo'], strcasecmp($a['finca'], $b['finca']), strcasecmp($a['seccion'], $b['seccion']), $a['codLote'] === '', $a['codLote'], $a['codLabor']]
            <=> [$orden[$b['contratista']], 0, $b['codigo'], 0, 0, $b['codLote'] === '', $b['codLote'], $b['codLabor']]);

        $n = count($filas);

        foreach ($filas as $i => &$f) {
            $g      = $grupos[$f['contratista']];
            $t      = $trab[$f['contratista']][$f['codigo']];
            $fincas = array_values(array_filter(array_map('strval', array_keys($g['fincas'])), static fn ($v) => $v !== ''));
            $sig    = $filas[$i + 1] ?? null;

            $f += [
                'finTrabajador'      => $sig === null || $sig['contratista'] !== $f['contratista'] || $sig['codigo'] !== $f['codigo'],
                'jornalesTrabajador' => round($t['jornales'], 2),
                'valorTrabajador'    => round($t['valor'], 2),
                'alertaGrupo'        => $g['sinProveedor'] || $g['faltaLoteN'] > 0 || $g['sinPrecioN'] > 0,
                'pctGrupo'           => $total == 0 ? 0 : round($g['valor'] / $total, 4),
                'trabajadoresGrupo'  => count($g['trabajadores']),
                'lotesGrupo'         => count($g['lotes'] ?? []),
                'fincaUnica'         => count($fincas) === 1 ? $fincas[0] : '',
                'nFincas'            => count($fincas),
                'laboresGrupo'       => count($g['labores']),
                'faltaLoteN'         => $g['faltaLoteN'],
                'sinPrecioN'         => $g['sinPrecioN'],
                'variosPreciosN'     => $g['variosPreciosN'],
                'ajusteN'            => $g['ajusteN'],
            ];
        }

        unset($f);

        $series = [];
        $colores = [];

        if ($n > 0) {
            $porFinca = $filtros['finca'] === '' && $lote[0] === '';
            $valores  = [];

            foreach ($filas as $f) {
                $s = $porFinca ? ($f['finca'] === '' ? 'Sin finca' : $f['finca']) : ($f['lote'] === '' ? 'Sin lote' : $f['lote']);
                $valores[$s][$f['contratista']] = ($valores[$s][$f['contratista']] ?? 0) + $f['valor'];
            }

            $sinLote = $valores['Sin lote'] ?? null;
            unset($valores['Sin lote']);
            uasort($valores, static fn ($a, $b) => array_sum($b) <=> array_sum($a));

            if (! $porFinca && count($valores) > 6) {
                $otros = [];

                foreach (array_slice($valores, 6, null, true) as $v) {
                    foreach ($v as $k => $x) {
                        $otros[$k] = ($otros[$k] ?? 0) + $x;
                    }
                }

                $valores = array_slice($valores, 0, 6, true) + ['Otros' => $otros];
            }

            if ($sinLote !== null) {
                $valores['Sin lote'] = $sinLote;
            }

            $i = 0;

            foreach ($valores as $nombre => $v) {
                $series[]  = ['nombre' => (string) $nombre, 'datos' => array_map(static fn ($k) => isset($v[$k]) ? round($v[$k], 2) : null, array_keys($grupos))];
                $colores[] = match ((string) $nombre) {
                    'Otros'    => '#b8c2cc',
                    'Sin lote' => '#8a97a5',
                    default    => self::COLORES[$i++ % count(self::COLORES)],
                };
            }
        }

        $faltaLote = array_sum(array_map(static fn ($f) => (int) $f['faltaLote'], $filas));
        $lotes     = [];
        $fincasT   = [];

        foreach ($filas as $f) {
            if ($f['codLote'] !== '') {
                $lotes[$f['finca'] . '|' . $f['codLote']] = true;
            }

            if ($f['finca'] !== '') {
                $fincasT[$f['finca']] = true;
            }
        }

        $filas = array_map(static function ($f) {
            unset($f['codigo'], $f['nombre'], $f['codLote'], $f['codLabor']);

            return $f;
        }, $filas);

        $jornales = round(array_sum(array_column($filas, 'jornales')), 2);
        $sinProv  = array_filter($filas, static fn ($f) => $f['sinProveedor']);
        $nTrab    = count($trab[self::SIN_PROVEEDOR] ?? []);

        if ($sinProv !== []) {
            $avisos[] = self::plural(count($sinProv), 'fila', 'filas') . ' de ' . self::plural($nTrab, 'trabajador', 'trabajadores')
                . ' sin proveedor asociado; se agrupan en "' . self::SIN_PROVEEDOR . '" y no deben liquidarse sin asignarlo.';
        }

        if ($faltaLote > 0) {
            $avisos[] = $faltaLote === 1 ? '1 fila de una labor que exige lote no tiene lote; su valor no se asigna a ningún lote.'
                : self::n($faltaLote) . ' filas de labores que exigen lote no tienen lote; su valor no se asigna a ningún lote.';
        }

        $rango = date('d/m/Y', strtotime($filtros['fechaInicial'])) . ' – ' . date('d/m/Y', strtotime($filtros['fechaFinal']));

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total a liquidar', 'valor' => $n === 0 ? null : $total, 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $n === 0 ? '' : self::plural($n, 'fila', 'filas') . ' · ' . $rango],
                ['etiqueta' => 'Contratistas', 'valor' => $n === 0 ? null : count($grupos), 'formato' => 'entero', 'icono' => 'bi bi-briefcase',
                    'ayuda' => $n === 0 ? '' : 'Con labores en el rango'],
                ['etiqueta' => 'Lotes intervenidos', 'valor' => $n === 0 ? null : count($lotes), 'formato' => 'entero', 'icono' => 'bi bi-geo-alt',
                    'ayuda' => $n === 0 ? '' : 'En ' . self::plural(count($fincasT), 'finca', 'fincas') . ($faltaLote > 0 ? ' · ' . self::plural($faltaLote, 'fila sin lote requerido', 'filas sin lote requerido') : '')],
                ['etiqueta' => 'Valor sin proveedor asociado', 'valor' => $n === 0 ? null : round(array_sum(array_column($sinProv, 'valor')), 2), 'formato' => 'moneda', 'icono' => 'bi bi-exclamation-diamond',
                    'ayuda' => $n === 0 ? '' : ($nTrab > 0 ? self::plural($nTrab, 'trabajador sin proveedor', 'trabajadores sin proveedor') : 'Todos los trabajadores tienen proveedor')],
            ],
            'grafica' => $n === 0 ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => count($series) > 1,
                'titulo'     => 'Valor a liquidar por contratista y ' . ($filtros['finca'] === '' && $lote[0] === '' ? 'finca' : 'lote'),
                'categorias' => array_keys($grupos),
                'series'     => $series,
                'colores'    => $colores,
                'detalle'    => array_map(static fn ($g) => [
                    'total'        => round($g['valor'], 2),
                    'pct'          => $total == 0 ? 0 : round($g['valor'] / $total, 4),
                    'trabajadores' => count($g['trabajadores']),
                    'lotes'        => count($g['lotes'] ?? []),
                    'jornales'     => round($g['jornales'], 2),
                ], array_values($grupos)),
                'ejeX'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'contratista', 'titulo' => 'Contratista', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'seccion', 'titulo' => 'Sección', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'precio', 'titulo' => 'Precio prom.', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Promedio ponderado: valor / cantidad'],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan'],
                ['clave' => 'faltaLote', 'titulo' => 'Falta lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sinPrecio', 'titulo' => 'Sin precio', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'variosPrecios', 'titulo' => 'Varios precios', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'precioMin', 'titulo' => 'Precio mínimo', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'precioMax', 'titulo' => 'Precio máximo', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'conAjuste', 'titulo' => 'Con ajustes', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'netoNegativo', 'titulo' => 'Neto negativo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sinProveedor', 'titulo' => 'Sin proveedor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finTrabajador', 'titulo' => 'Fin trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'jornalesTrabajador', 'titulo' => 'Jornales trabajador', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valorTrabajador', 'titulo' => 'Valor trabajador', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'alertaGrupo', 'titulo' => 'Contratista con alertas', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'pctGrupo', 'titulo' => '% del total', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'trabajadoresGrupo', 'titulo' => 'Trabajadores contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'lotesGrupo', 'titulo' => 'Lotes contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'fincaUnica', 'titulo' => 'Finca única', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'nFincas', 'titulo' => 'N.º fincas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'laboresGrupo', 'titulo' => 'Labores contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'faltaLoteN', 'titulo' => 'Filas sin lote requerido', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'sinPrecioN', 'titulo' => 'Filas sin precio', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'variosPreciosN', 'titulo' => 'Filas con varios precios', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'ajusteN', 'titulo' => 'Filas con ajustes', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => ['jornales' => $jornales, 'valor' => $total],
            'agrupar' => 'contratista',
            'avisos'  => $avisos,
        ];
    }
}
