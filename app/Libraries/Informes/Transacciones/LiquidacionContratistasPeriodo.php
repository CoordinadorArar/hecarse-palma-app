<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LiquidacionContratistasPeriodo extends Informe
{
    private const SIN_PROVEEDOR = 'Proveedor no asociado';

    private const PROVEEDOR = "CASE WHEN pn.id IS NULL THEN pv.id ELSE pn.id END AS proveedor, LTRIM(RTRIM(ISNULL(ISNULL(pn.nit, pv.nit), ''))) AS nit, LTRIM(RTRIM(ISNULL(ISNULL(pn.dv, pv.dv), ''))) AS dv,
                              CAST(ISNULL(ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion)), '') AS varchar(150)) AS razon
                         FROM nFuncionario f
                         LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                         LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END";

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

        return $v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year')) ? 'El rango no puede superar un año.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];
        $num   = "SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50)";

        if ($filtros['finca'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(c.finca)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['contratista'] !== '') {
            $where  .= ' AND p.proveedor = ?';
            $binds[] = (int) $filtros['contratista'];
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(c.tipo)) AS tipo, CAST(CAST({$num} AS bigint) AS varchar(20)) AS numero, CONVERT(date, a.fecha) AS fecha,
                       CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre,
                       LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre, LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre,
                       p.proveedor, p.nit, p.dv, p.razon, LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad,
                       ISNULL(c.jornales, 0) AS jornales, ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor
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
                 WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}
                 ORDER BY p.proveedor ASC, nombre ASC, tercero ASC, fecha ASC, tipo ASC, CAST({$num} AS bigint) ASC, b.registro ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '102400');
        ini_set('memory_limit', '256M');

        $query  = $this->db->query($sql, $binds);
        $avisos = [];
        $filas  = [];
        $dias   = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' líneas; el último contratista puede estar incompleto. Filtre por contratista o finca, o reduzca el rango.';

                break;
            }

            $nombre  = trim($c['nombre']);
            $fecha   = substr((string) $c['fecha'], 0, 10);
            $filas[] = [
                'contratista' => self::proveedor($c),
                'trabajador'  => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'fecha'       => $fecha,
                'dia'         => $dias[(int) date('w', strtotime($fecha))],
                'documento'   => $c['tipo'] . '-' . $c['numero'],
                'finca'       => trim($c['finca']),
                'lote'        => self::codigo($c['lote'], $c['loteNombre']),
                'labor'       => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'uMedida'     => $c['uMedida'],
                'cantidad'    => (float) $c['cantidad'],
                'jornales'    => (float) $c['jornales'],
                'precio'      => (float) $c['precio'],
                'valor'       => (float) $c['valor'],
                'codigo'      => $c['tercero'],
                'codLabor'    => $c['labor'],
                'nit'         => $c['proveedor'] === null ? '' : $c['nit'] . ($c['dv'] === '' ? '' : '-' . $c['dv']),
                'sinProveedor' => $c['proveedor'] === null,
            ];
        }

        $query->freeResult();

        $total  = round(array_sum(array_column($filas, 'valor')), 2);
        $grupos = [];
        $trab   = [];
        $precios = [];

        foreach ($filas as $f) {
            $g = &$grupos[$f['contratista']];
            $g['valor']    = ($g['valor'] ?? 0) + $f['valor'];
            $g['jornales'] = ($g['jornales'] ?? 0) + $f['jornales'];
            $g['lineas']   = ($g['lineas'] ?? 0) + 1;
            $g['nit']      = $f['nit'];
            $g['sinProveedor'] = $f['sinProveedor'];
            $g['trabajadores'][$f['codigo']] = true;
            $g['dias'][$f['fecha']] = true;
            $g['fincas'][$f['finca']] = true;
            $g['labores'][$f['codLabor']] = true;
            unset($g);

            $t = &$trab[$f['contratista']][$f['codigo']];
            $t['jornales'] = ($t['jornales'] ?? 0) + $f['jornales'];
            $t['valor']    = ($t['valor'] ?? 0) + $f['valor'];
            $t['lineas']   = ($t['lineas'] ?? 0) + 1;
            unset($t);

            if ($f['precio'] != 0) {
                $p = (string) $f['precio'];
                $precios[$f['codLabor']][$p] = ($precios[$f['codLabor']][$p] ?? 0) + 1;
            }
        }

        $habitual = [];

        foreach ($precios as $labor => $conteo) {
            arsort($conteo);
            $habitual[$labor] = (float) array_key_first($conteo);
        }

        uksort($grupos, static fn ($a, $b) => [$a === self::SIN_PROVEEDOR, -$grupos[$a]['valor']] <=> [$b === self::SIN_PROVEEDOR, -$grupos[$b]['valor']]);

        $orden = array_flip(array_keys($grupos));

        usort($filas, static fn ($a, $b) => $orden[$a['contratista']] <=> $orden[$b['contratista']]);

        foreach ($filas as &$f) {
            $sinPrecio = $f['precio'] == 0;
            $f['negativo']       = $f['valor'] < 0 || $f['cantidad'] < 0;
            $f['sinPrecio']      = $sinPrecio;
            $f['precioHabitual'] = $habitual[$f['codLabor']] ?? null;
            $f['precioDistinto'] = ! $sinPrecio && isset($habitual[$f['codLabor']]) && $f['precio'] != $habitual[$f['codLabor']];
        }

        unset($f);

        $cuentas = [];

        foreach ($filas as $f) {
            $k = &$cuentas[$f['contratista']];
            $k['sinPrecioN']      = ($k['sinPrecioN'] ?? 0) + (int) $f['sinPrecio'];
            $k['precioDistintoN'] = ($k['precioDistintoN'] ?? 0) + (int) $f['precioDistinto'];
            $k['negativoN']       = ($k['negativoN'] ?? 0) + (int) $f['negativo'];
            unset($k);
        }

        $n = count($filas);

        foreach ($filas as $i => &$f) {
            $g      = $grupos[$f['contratista']];
            $t      = $trab[$f['contratista']][$f['codigo']];
            $k      = $cuentas[$f['contratista']];
            $fincas = array_values(array_filter(array_map('strval', array_keys($g['fincas'])), static fn ($v) => $v !== ''));
            $sig    = $filas[$i + 1] ?? null;

            $f += [
                'finTrabajador'      => $sig === null || $sig['contratista'] !== $f['contratista'] || $sig['codigo'] !== $f['codigo'],
                'jornalesTrabajador' => round($t['jornales'], 2),
                'valorTrabajador'    => round($t['valor'], 2),
                'lineasTrabajador'   => $t['lineas'],
                'conNegativo'        => $k['negativoN'] > 0,
                'sinProveedor'       => $g['sinProveedor'],
                'sinPrecioGrupo'     => $k['sinPrecioN'] > 0,
                'pctGrupo'           => $total == 0 ? 0 : round($g['valor'] / $total, 4),
                'trabajadoresGrupo'  => count($g['trabajadores']),
                'diasGrupo'          => count($g['dias']),
                'fincasGrupo'        => implode(', ', $fincas),
                'fincaUnica'         => count($fincas) === 1 ? $fincas[0] : '',
                'nFincas'            => count($fincas),
                'laboresGrupo'       => count($g['labores']),
            ] + $k;
        }

        unset($f);

        $filas = array_map(static function ($f) {
            unset($f['codigo'], $f['codLabor'], $f['nit']);

            return $f;
        }, $filas);

        $trabajadores = array_sum(array_map(static fn ($t) => count($t), $trab));
        $jornales     = round(array_sum(array_column($filas, 'jornales')), 2);
        $diasTotal    = count(array_unique(array_column($filas, 'fecha')));
        $sinProv      = array_filter($filas, static fn ($f) => $f['sinProveedor']);

        if ($sinProv !== []) {
            $nTrab    = count($trab[self::SIN_PROVEEDOR] ?? []);
            $avisos[] = self::plural(count($sinProv), 'línea', 'líneas') . ' de ' . self::plural($nTrab, 'trabajador', 'trabajadores')
                . ' sin proveedor asociado; se agrupan en "' . self::SIN_PROVEEDOR . '" y no deben liquidarse sin asignarlo.';
        }

        $rango = date('d/m/Y', strtotime($filtros['fechaInicial'])) . ' – ' . date('d/m/Y', strtotime($filtros['fechaFinal']));

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total a liquidar', 'valor' => $n === 0 ? null : $total, 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $n === 0 ? '' : self::plural($n, 'línea', 'líneas') . ' · ' . $rango],
                ['etiqueta' => 'Contratistas con labores', 'valor' => $n === 0 ? null : count($grupos), 'formato' => 'entero', 'icono' => 'bi bi-briefcase',
                    'ayuda' => $n === 0 ? '' : 'Proveedores con labores en el rango'],
                ['etiqueta' => 'Trabajadores de contratistas', 'valor' => $n === 0 ? null : $trabajadores, 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $n === 0 ? '' : 'Con al menos una labor en el rango'],
                ['etiqueta' => 'Jornales totales', 'valor' => $n === 0 ? null : $jornales, 'formato' => 'decimal', 'icono' => 'bi bi-calendar-check',
                    'ayuda' => $n === 0 ? '' : self::plural($diasTotal, 'día laborado', 'días laborados')],
            ],
            'grafica' => $n === 0 ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Valor a liquidar por contratista',
                'categorias' => array_keys($grupos),
                'series'     => [['nombre' => 'Valor a liquidar', 'datos' => array_map(static fn ($g) => round($g['valor'], 2), array_values($grupos))]],
                'detalle'    => array_map(static fn ($g) => [
                    'nit'          => $g['nit'],
                    'trabajadores' => count($g['trabajadores']),
                    'jornales'     => round($g['jornales'], 2),
                    'lineas'       => $g['lineas'],
                    'pct'          => $total == 0 ? 0 : round($g['valor'] / $total, 4),
                ], array_values($grupos)),
                'ejeX'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'contratista', 'titulo' => 'Contratista', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'dia', 'titulo' => 'Día', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'documento', 'titulo' => 'Documento', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'precio', 'titulo' => 'Precio', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
                ['clave' => 'finTrabajador', 'titulo' => 'Fin trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'jornalesTrabajador', 'titulo' => 'Jornales trabajador', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valorTrabajador', 'titulo' => 'Valor trabajador', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'lineasTrabajador', 'titulo' => 'Líneas trabajador', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'negativo', 'titulo' => 'Negativo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sinPrecio', 'titulo' => 'Sin precio', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'precioDistinto', 'titulo' => 'Precio distinto', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'precioHabitual', 'titulo' => 'Precio habitual', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'conNegativo', 'titulo' => 'Contratista con negativos', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sinProveedor', 'titulo' => 'Sin proveedor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sinPrecioGrupo', 'titulo' => 'Contratista con líneas sin precio', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'pctGrupo', 'titulo' => '% del total', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'trabajadoresGrupo', 'titulo' => 'Trabajadores contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'diasGrupo', 'titulo' => 'Días contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'fincasGrupo', 'titulo' => 'Fincas contratista', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fincaUnica', 'titulo' => 'Finca única', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'nFincas', 'titulo' => 'N.º fincas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'laboresGrupo', 'titulo' => 'Labores contratista', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'sinPrecioN', 'titulo' => 'Líneas sin precio', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'precioDistintoN', 'titulo' => 'Líneas con precio distinto', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'negativoN', 'titulo' => 'Líneas negativas', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => ['jornales' => $jornales, 'valor' => $total],
            'agrupar' => 'contratista',
            'avisos'  => $avisos,
        ];
    }
}
