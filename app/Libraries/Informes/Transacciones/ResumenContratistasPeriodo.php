<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Informe;

class ResumenContratistasPeriodo extends Informe
{
    private const SIN_PROVEEDOR = 'Proveedor no asociado';

    private const CONTRATISTAS = 'Contratistas';

    private const PROPIO = 'Personal propio';

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const PROVEEDOR = "CASE WHEN pn.id IS NULL THEN pv.id ELSE pn.id END AS proveedor, LTRIM(RTRIM(ISNULL(ISNULL(pn.nit, pv.nit), ''))) AS nit, LTRIM(RTRIM(ISNULL(ISNULL(pn.dv, pv.dv), ''))) AS dv,
                              CAST(ISNULL(ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion)), '') AS varchar(150)) AS razon
                         FROM nFuncionario f
                         LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                         LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END";

    public function filtros(): array
    {
        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-01-01'), 'ayuda' => 'Dentro de un mismo año'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Dentro de un mismo año'],
            ['clave' => 'agrupar', 'etiqueta' => 'Agrupar por', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'grupo',
                'opciones' => [['valor' => 'grupo', 'texto' => 'Grupo de labor'], ['valor' => 'contratista', 'texto' => 'Contratista']]],
            ['clave' => 'propio', 'etiqueta' => 'Incluir personal propio', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'S',
                'opciones' => [['valor' => 'S', 'texto' => 'Sí'], ['valor' => 'N', 'texto' => 'No']]],
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

        return substr($v['fechaInicial'], 0, 4) !== substr($v['fechaFinal'], 0, 4) ? 'El rango debe estar dentro de un mismo año.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $sql = "SELECT mes, grupo, grupoNombre, sinGrupo, contratista, proveedor, nit, dv, razon, tercero, SUM(valor) AS valor
                  FROM (SELECT MONTH(a.fecha) AS mes, LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo, CAST(ISNULL(g.descripcion, '') AS varchar(150)) AS grupoNombre,
                               CASE WHEN g.codigo IS NULL THEN 1 ELSE 0 END AS sinGrupo, ISNULL(p.es, 0) AS contratista, p.proveedor, p.nit, p.dv, p.razon,
                               LTRIM(RTRIM(c.tercero)) AS tercero, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                          JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                         OUTER APPLY (SELECT TOP 1 1 AS es, " . self::PROVEEDOR . "
                                       WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero))
                                       ORDER BY CASE WHEN pn.id IS NULL AND pv.id IS NULL THEN 1 ELSE 0 END) p
                         OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM aGrupoNovedad WHERE empresa = d.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(d.grupo))) g
                         WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?) x
                 GROUP BY mes, grupo, grupoNombre, sinGrupo, contratista, proveedor, nit, dv, razon, tercero";

        $datos   = $this->db->query($sql, [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']])->getResultArray();
        $propio  = $filtros['propio'] === 'S';
        $modo    = $filtros['agrupar'];
        $desde   = (int) substr($filtros['fechaInicial'], 5, 2);
        $hasta   = (int) substr($filtros['fechaFinal'], 5, 2);
        $rangoM  = range($desde, $hasta);
        $tipos   = [self::CONTRATISTAS => [], self::PROPIO => []];
        $filas   = [self::CONTRATISTAS => [], self::PROPIO => []];
        $labores = [];
        $meses   = [];

        foreach ($datos as $d) {
            $tipo  = (int) $d['contratista'] === 1 ? self::CONTRATISTAS : self::PROPIO;
            $m     = (int) $d['mes'];
            $valor = (float) $d['valor'];
            $grupo = $d['grupo'] === '' || (int) $d['sinGrupo'] === 1 ? 'Sin grupo' : $d['grupo'] . (trim($d['grupoNombre']) === '' ? '' : ' — ' . trim($d['grupoNombre']));
            $prov  = $tipo === self::PROPIO ? self::PROPIO : ($d['proveedor'] === null ? self::SIN_PROVEEDOR : $d['nit'] . ($d['dv'] === '' ? '' : '-' . $d['dv']) . ' — ' . trim($d['razon']));
            $clave = $modo === 'grupo' ? $grupo : $prov;

            $t = &$tipos[$tipo];
            $t['valor']                 = ($t['valor'] ?? 0) + $valor;
            $t['meses'][$m]             = ($t['meses'][$m] ?? 0) + $valor;
            $t['trab'][$d['tercero']]   = true;
            $t['prov'][$prov]           = true;
            if ($tipo === self::CONTRATISTAS && $d['proveedor'] === null) {
                $t['sinProv'][$d['tercero']] = true;
            }
            unset($t);

            $f = &$filas[$tipo][$clave];
            $f['valor']               = ($f['valor'] ?? 0) + $valor;
            $f['meses'][$m]           = ($f['meses'][$m] ?? 0) + $valor;
            $f['trab'][$d['tercero']] = true;
            $f['grupo']               = $grupo;
            unset($f);

            $labores[$grupo][$tipo] = ($labores[$grupo][$tipo] ?? 0) + $valor;
            $meses[$m][$tipo]       = ($meses[$m][$tipo] ?? 0) + $valor;
            if ($tipo === self::CONTRATISTAS) {
                $meses[$m]['prov'][$prov] = true;
            }
        }

        $valorC  = round($tipos[self::CONTRATISTAS]['valor'] ?? 0, 2);
        $valorP  = round($tipos[self::PROPIO]['valor'] ?? 0, 2);
        $totalL  = $valorC + $valorP;
        $bloques = $propio ? $filas : [self::CONTRATISTAS => $filas[self::CONTRATISTAS]];
        $mostrado = round(array_sum(array_map(static fn ($b) => array_sum(array_column($b, 'valor')), $bloques)), 2);
        $salida  = [];
        $totales = ['total' => $mostrado, 'pct' => $mostrado == 0 ? 0 : 1];

        foreach ($bloques as $tipo => $sub) {
            uksort($sub, static fn ($x, $y) => [$x === self::SIN_PROVEEDOR, -$sub[$x]['valor']] <=> [$y === self::SIN_PROVEEDOR, -$sub[$y]['valor']]);

            $t = $tipos[$tipo];

            foreach ($sub as $clave => $f) {
                $fila = ['etiqueta' => (string) $clave, 'tipo' => $tipo];

                foreach ($rangoM as $m) {
                    $v                  = round($f['meses'][$m] ?? 0, 2);
                    $fila['m' . $m]     = $v == 0 ? null : $v;
                    $totales['m' . $m]  = round(($totales['m' . $m] ?? 0) + $v, 2);
                }

                $l        = $labores[$f['grupo']];
                $lTotal   = ($l[self::CONTRATISTAS] ?? 0) + ($l[self::PROPIO] ?? 0);
                $salida[] = $fila + [
                    'total'            => round($f['valor'], 2),
                    'pct'              => $mostrado == 0 ? 0 : $f['valor'] / $mostrado,
                    'pctGrupoLabor'    => $tipo === self::CONTRATISTAS && $modo === 'grupo' && $propio && $lTotal != 0 ? ($l[self::CONTRATISTAS] ?? 0) / $lTotal : null,
                    'trabajadores'     => count($f['trab']),
                    'sinGrupo'         => $modo === 'grupo' && $clave === 'Sin grupo',
                    'pctTipo'          => $totalL == 0 ? 0 : ($t['valor'] ?? 0) / $totalL,
                    'contratistasTipo' => $tipo === self::CONTRATISTAS ? count($t['prov'] ?? []) : null,
                    'trabajadoresTipo' => count($t['trab'] ?? []),
                    'mesesTipo'        => count(array_filter($t['meses'] ?? [], static fn ($v) => round($v, 2) != 0)),
                    'sinProveedorTipo' => $tipo === self::CONTRATISTAS && ! empty($t['sinProv']),
                ];
            }
        }

        $c       = $tipos[self::CONTRATISTAS];
        $activos = count($c['prov'] ?? []);
        $trabC   = count($c['trab'] ?? []);
        $sinProv = count($c['sinProv'] ?? []);
        $mayor   = null;

        foreach ($labores as $grupo => $l) {
            if (isset($l[self::CONTRATISTAS]) && ($mayor === null || $l[self::CONTRATISTAS] > $labores[$mayor][self::CONTRATISTAS])) {
                $mayor = $grupo;
            }
        }

        $avisos = [];

        if ($datos === []) {
            $avisos[] = 'No hay labores en el rango seleccionado.';
        } elseif ($c === []) {
            $avisos[] = 'No hubo labores de contratistas en el rango' . ($propio ? '; solo se muestra el personal propio.' : '.');
        }

        if ($sinProv > 0) {
            $avisos[] = self::plural($sinProv, 'trabajador contratista', 'trabajadores contratistas') . ' sin proveedor asociado; se agrupan en "' . self::SIN_PROVEEDOR . '".';
        }

        $rango   = date('d/m/Y', strtotime($filtros['fechaInicial'])) . ' – ' . date('d/m/Y', strtotime($filtros['fechaFinal']));
        $anio    = (int) substr($filtros['fechaInicial'], 0, 4);
        $mesAct  = $anio < (int) date('Y') ? 12 : ($anio === (int) date('Y') ? (int) date('n') : 0);
        $columna = static fn (string $clave, string $titulo, string $tipo, string $alineacion = 'derecha', bool $total = false, array $extra = []) => ['clave' => $clave, 'titulo' => $titulo, 'tipo' => $tipo, 'alineacion' => $alineacion, 'total' => $total] + $extra;
        $vMayor  = $mayor === null ? 0 : $labores[$mayor][self::CONTRATISTAS];

        $respuesta = [
            'kpis' => [
                ['etiqueta' => 'Valor contratistas', 'valor' => $valorC, 'formato' => 'moneda', 'icono' => 'bi bi-briefcase',
                    'ayuda' => self::plural(count($rangoM), 'mes', 'meses') . ' · ' . $rango],
                ['etiqueta' => '% contratistas', 'valor' => $totalL == 0 ? 0 : $valorC / $totalL, 'formato' => 'porcentaje', 'icono' => 'bi bi-pie-chart',
                    'ayuda' => 'Sobre $ ' . self::n($totalL) . ' de labores totales · por fecha de transacción'],
                ['etiqueta' => 'Contratistas activos', 'valor' => $activos, 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => self::plural($trabC, 'trabajador contratista', 'trabajadores contratistas')],
                ['etiqueta' => 'Grupo con mayor valor por contratistas', 'valor' => $mayor ?? '—', 'formato' => 'texto', 'icono' => 'bi bi-trophy',
                    'ayuda' => $mayor === null ? 'Sin labores de contratistas en el rango' : '$ ' . self::n($vMayor) . ' · ' . self::n($valorC == 0 ? 0 : $vMayor / $valorC * 100, 1) . ' % del valor contratistas'],
            ],
            'grafica' => $valorC == 0 ? null : [
                'tipo'       => 'combo',
                'titulo'     => 'Valor por contratistas y participación mensual',
                'categorias' => array_map(static fn ($m) => self::MESES[$m], $rangoM),
                'series'     => [
                    ['nombre' => 'Valor contratistas', 'tipo' => 'column', 'formato' => 'moneda',
                        'datos' => array_map(static fn ($m) => ($v = round($meses[$m][self::CONTRATISTAS] ?? 0, 2)) == 0 ? null : $v, $rangoM)],
                    ['nombre' => '% contratistas', 'tipo' => 'line', 'formato' => 'decimal', 'eje' => 1,
                        'datos' => array_map(static fn ($m) => ($t = ($meses[$m][self::CONTRATISTAS] ?? 0) + ($meses[$m][self::PROPIO] ?? 0)) == 0 ? null : round(($meses[$m][self::CONTRATISTAS] ?? 0) / $t * 100, 1), $rangoM)],
                ],
                'colores'    => ['#41a867', '#4a5b6c'],
                'detalle'    => array_map(static fn ($m) => [
                    'propio'       => round($meses[$m][self::PROPIO] ?? 0, 2),
                    'total'        => round(($meses[$m][self::CONTRATISTAS] ?? 0) + ($meses[$m][self::PROPIO] ?? 0), 2),
                    'contratistas' => count($meses[$m]['prov'] ?? []),
                ], $rangoM),
                'ejeY'       => 'Valor contratistas',
                'ejeY2'      => '% sobre total de labores',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                $columna('etiqueta', $modo === 'grupo' ? 'Grupo de labor' : 'Contratista', 'texto', 'izquierda'),
                $columna('tipo', 'Tipo', 'texto', 'izquierda'),
                ...array_map(static fn ($m) => $columna('m' . $m, self::MESES[$m], 'moneda', 'derecha', $m <= $mesAct, $m > $mesAct ? ['futuro' => true, 'ayuda' => 'Mes no transcurrido'] : []), $rangoM),
                $columna('total', 'Total', 'moneda', 'derecha', true),
                $columna('pct', '% del total', 'porcentaje', 'derecha', true, ['ayuda' => 'Participación sobre el total mostrado']),
                $columna('pctGrupoLabor', '% contratistas en el grupo', 'porcentaje'),
                $columna('trabajadores', 'Trabajadores', 'entero'),
                $columna('sinGrupo', 'Sin grupo', 'texto', 'centro'),
                $columna('pctTipo', '% del tipo sobre total labores', 'porcentaje'),
                $columna('contratistasTipo', 'Contratistas del tipo', 'entero'),
                $columna('trabajadoresTipo', 'Trabajadores del tipo', 'entero'),
                $columna('mesesTipo', 'Meses con valor del tipo', 'entero'),
                $columna('sinProveedorTipo', 'Contratistas sin proveedor', 'texto', 'centro'),
            ],
            'filas'   => $salida,
            'totales' => $totales,
            'avisos'  => $avisos,
        ];

        return $propio ? $respuesta + ['agrupar' => 'tipo'] : $respuesta;
    }
}
