<?php

namespace App\Libraries\Informes\Indicadores;

use App\Libraries\Informes\Informe;

class RendimientoTrabajadorMes extends Informe
{
    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private const SIN_CCOSTO = 'Sin centro de costo';

    private const PROPIO = "NOT EXISTS (SELECT 1 FROM nFuncionario f WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero)))";

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT YEAR(a.fecha) AS anio FROM aTransaccion a
                               WHERE a.empresa = ? AND a.anulado = 0 AND EXISTS (SELECT 1 FROM aTransaccionTercero c WHERE c.empresa = a.empresa AND c.tipo = a.tipo AND c.numero = a.numero)
                               ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $meses   = array_map(static fn ($m) => ['valor' => (string) $m, 'texto' => self::MESES[$m]], array_keys(self::MESES));
        $ccostos = array_map(
            static fn ($c) => ['valor' => $c['codigo'], 'texto' => $c['codigo'] . (trim($c['nombre']) === '' ? '' : ' — ' . trim($c['nombre']))],
            $this->db->query("SELECT x.codigo, CAST(ISNULL((SELECT TOP 1 descripcion FROM cCentrosCosto WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = x.codigo), '') AS varchar(150)) AS nombre
                                FROM (SELECT DISTINCT LTRIM(RTRIM(c.ccosto)) AS codigo FROM aTransaccionTercero c WHERE c.empresa = ? AND LTRIM(RTRIM(ISNULL(c.ccosto, ''))) <> '') x
                               ORDER BY x.codigo", [$this->empresa, $this->empresa])->getResultArray()
        );
        $trabajadores = array_map(
            static fn ($t) => ['valor' => $t['tercero'], 'texto' => $t['tercero'] . ' — ' . (trim($t['nombre']) === '' ? 'Sin nombre en terceros' : trim($t['nombre']))],
            $this->db->query("SELECT x.tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre
                                FROM (SELECT DISTINCT LTRIM(RTRIM(c.tercero)) AS tercero FROM aTransaccionTercero c
                                        JOIN aTransaccion a ON a.numero = c.numero AND a.tipo = c.tipo AND a.empresa = c.empresa AND a.anulado = 0
                                       WHERE c.empresa = ? AND " . self::PROPIO . ") x
                               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                                             WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                                             ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) t
                               ORDER BY CASE WHEN ISNULL(LTRIM(RTRIM(t.nombre)), '') = '' THEN 1 ELSE 0 END, t.nombre, x.tercero", [$this->empresa, $this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? ''), 'ayuda' => 'Por fecha de la transacción.', 'opciones' => $anios],
            ['clave' => 'mesDesde', 'etiqueta' => 'Mes inicial', 'tipo' => 'mes', 'obligatorio' => true, 'defecto' => '1', 'opciones' => $meses],
            ['clave' => 'mesHasta', 'etiqueta' => 'Mes final', 'tipo' => 'mes', 'obligatorio' => true, 'defecto' => date('n'), 'ayuda' => '≥ mes inicial', 'opciones' => $meses],
            ['clave' => 'ccosto', 'etiqueta' => 'Centro de costo', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los centros de costo', 'opciones' => $ccostos],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los trabajadores', 'opciones' => $trabajadores],
            ['clave' => 'soloBajo', 'etiqueta' => 'Solo bajo el mínimo', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => '0',
                'opciones' => [['valor' => '0', 'texto' => 'No'], ['valor' => '1', 'texto' => 'Sí']]],
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

        return is_array($v) && (int) $v['mesDesde'] > (int) $v['mesHasta'] ? 'El mes inicial no puede ser mayor que el final.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $anio  = (int) $filtros['anio'];
        $smlv  = $this->db->query('SELECT TOP 1 vSalarioMinimo FROM nParametrosAno WHERE empresa = ? AND ano = ?', [$this->empresa, $anio])->getRow()?->vSalarioMinimo;
        $smlv  = $smlv === null || (float) $smlv <= 0 ? null : (float) $smlv;
        $diario = $smlv === null ? null : $smlv / 30;

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $anio . '-' . sprintf('%02d', $filtros['mesDesde']) . '-01', date('Y-m-t', strtotime($anio . '-' . sprintf('%02d', $filtros['mesHasta']) . '-01'))];

        if ($filtros['trabajador'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(c.tercero)) = ?';
            $binds[] = $filtros['trabajador'];
        }

        $sql = "SELECT x.tercero, x.mes, x.dia, x.ccosto, x.lineas, x.jornales, x.valor, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre,
                       CAST(ISNULL((SELECT TOP 1 descripcion FROM cCentrosCosto WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = x.ccosto), '') AS varchar(150)) AS ccostoNombre
                  FROM (SELECT LTRIM(RTRIM(c.tercero)) AS tercero, MONTH(a.fecha) AS mes, CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS dia, LTRIM(RTRIM(ISNULL(c.ccosto, ''))) AS ccosto, COUNT(*) AS lineas,
                               SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.jornales, 0)) AS jornales,
                               SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0)) AS valor
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                          JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.noPrestacional = 0
                         WHERE c.empresa = ? AND a.fecha >= ? AND a.fecha < DATEADD(day, 1, CAST(? AS date)) AND " . self::PROPIO . "{$where}
                         GROUP BY LTRIM(RTRIM(c.tercero)), MONTH(a.fecha), CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)), LTRIM(RTRIM(ISNULL(c.ccosto, '')))) x
                 OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) t";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');

        $trab  = [];
        $query = $this->db->query($sql, array_merge([$this->empresa], $binds, [$this->empresa]));

        while (($r = $query->getUnbufferedRow('array')) !== null) {
            $t = &$trab[$r['tercero']];
            $t['nombre'] = trim($r['nombre']);
            $m = &$t['meses'][(int) $r['mes']];
            $m['registrados'] = ($m['registrados'] ?? 0) + (float) $r['jornales'];
            $m['valor']       = ($m['valor'] ?? 0) + (float) $r['valor'];
            $m[substr((string) $r['dia'], 0, 7) === sprintf('%04d-%02d', $anio, $r['mes']) ? 'dias' : 'otros'][substr((string) $r['dia'], 0, 10)] = true;
            unset($m);

            if ($r['ccosto'] !== '') {
                $t['cc'][$r['ccosto']] = ($t['cc'][$r['ccosto']] ?? 0) + (int) $r['lineas'];
                $t['ccNombre'][$r['ccosto']] = trim($r['ccostoNombre']);
            }

            unset($t);
        }

        $query->freeResult();

        $grupos = [];

        foreach ($trab as $codigo => $t) {
            $cc = '';

            if (! empty($t['cc'])) {
                arsort($t['cc']);
                $cc = (string) array_key_first($t['cc']);
            }

            if ($filtros['ccosto'] !== '' && $cc !== $filtros['ccosto']) {
                continue;
            }

            ksort($t['meses']);

            foreach ($t['meses'] as $mes => &$m) {
                $m['dias']      = count($m['dias'] ?? []) ?: ($m['valor'] != 0 ? min(max(count($m['otros'] ?? []), 1), (int) date('t', strtotime(sprintf('%04d-%02d-01', $anio, $mes)))) : 0);
                $m['estimados'] = $m['registrados'] == 0;
                $m['jornales']  = $m['estimados'] ? $m['dias'] : $m['registrados'];
            }

            unset($m);

            $jornales = array_sum(array_column($t['meses'], 'jornales'));
            $valor    = array_sum(array_column($t['meses'], 'valor'));
            $salario  = $diario === null ? null : array_sum(array_map(static fn ($m) => round($diario * $m['jornales'], 2), $t['meses']));
            $rinde    = $diario === null ? 0 : count(array_filter($t['meses'], static fn ($m) => $m['valor'] - $diario * $m['jornales'] >= 0));

            $grupos[$codigo] = [
                'trabajador' => (string) $codigo . ' — ' . ($t['nombre'] === '' ? 'Sin nombre en terceros' : $t['nombre']),
                'nombre'     => $t['nombre'],
                'ccosto'     => $cc === '' ? self::SIN_CCOSTO : $cc . ($t['ccNombre'][$cc] === '' ? '' : ' — ' . $t['ccNombre'][$cc]),
                'meses'      => $t['meses'],
                'jornales'   => $jornales,
                'valor'      => $valor,
                'salario'    => $salario,
                'diferencia' => $salario === null ? null : $valor - $salario,
                'rinde'      => $rinde,
            ];
        }

        uasort($grupos, static fn ($a, $b) => [$a['nombre'] === '', $a['nombre'], $a['trabajador']] <=> [$b['nombre'] === '', $b['nombre'], $b['trabajador']]);

        $filas = [];

        foreach ($grupos as $codigo => $g) {
            foreach ($g['meses'] as $mes => $m) {
                $salario    = $diario === null ? null : round($diario * $m['jornales'], 2);
                $diferencia = $salario === null ? null : round($m['valor'] - $salario, 2);

                if ($filtros['soloBajo'] === '1' && ! ($diferencia !== null && $diferencia < 0)) {
                    continue;
                }

                $filas[] = [
                    'trabajador'           => $g['trabajador'],
                    'documento'            => (string) $codigo,
                    'ccosto'               => $g['ccosto'],
                    'mes'                  => self::MESES[$mes],
                    'jornales'             => round($m['jornales'], 2),
                    'valor'                => round($m['valor'], 2),
                    'valorSalario'         => $salario,
                    'diferencia'           => $diferencia,
                    'valorJornal'          => $m['jornales'] == 0 ? null : round($m['valor'] / $m['jornales'], 2),
                    'rinde'                => $diferencia === null ? '' : ($diferencia >= 0 ? 'Sí' : 'No'),
                    'numMes'               => $mes,
                    'diasLaborados'        => $m['dias'],
                    'jornalesRegistrados'  => round($m['registrados'], 2),
                    'jornalesEstimados'    => $m['estimados'],
                    'jornalesTrabajador'   => round($g['jornales'], 2),
                    'valorTrabajador'      => round($g['valor'], 2),
                    'salarioTrabajador'    => $g['salario'] === null ? null : round($g['salario'], 2),
                    'diferenciaTrabajador' => $g['diferencia'] === null ? null : round($g['diferencia'], 2),
                    'mesesTrabajador'      => count($g['meses']),
                    'mesesRinde'           => $diario === null ? null : $g['rinde'],
                    'mesesBajo'            => $diario === null ? null : count($g['meses']) - $g['rinde'],
                    'conMesesBajo'         => $diario !== null && count($g['meses']) > $g['rinde'],
                ];
            }
        }

        $vacio    = $grupos === [];
        $jornales = array_sum(array_column($grupos, 'jornales'));
        $valor    = array_sum(array_column($grupos, 'valor'));
        $salario  = $diario === null ? null : array_sum(array_column($grupos, 'salario'));
        $difTotal = $salario === null ? null : $valor - $salario;
        $nMeses   = array_sum(array_map(static fn ($g) => count($g['meses']), $grupos));
        $rinden   = count(array_filter($grupos, static fn ($g) => $g['diferencia'] !== null && $g['diferencia'] >= 0));
        $porJornal = $jornales == 0 ? null : $valor / $jornales;
        $sinSmlv  = 'Sin SMLV ' . $anio;
        $avisos   = [];

        if ($diario === null) {
            $avisos[] = 'No hay salario mínimo registrado para ' . $anio . '; valor salario, diferencia y rinde no se calculan.';
        }

        $estimados = array_sum(array_map(static fn ($g) => count(array_filter($g['meses'], static fn ($m) => $m['estimados'])), $grupos));

        if ($estimados > 0) {
            $avisos[] = 'En ' . self::plural($estimados, 'trabajador-mes', 'trabajador-mes') . ' las labores no registran jornales; se usan los días laborados (fechas distintas con labor) como jornales.';
        }

        $sinCc = count(array_filter($grupos, static fn ($g) => $g['ccosto'] === self::SIN_CCOSTO));

        if ($sinCc > 0 && $filtros['ccosto'] === '') {
            $avisos[] = self::plural($sinCc, 'trabajador sin centro de costo en sus labores del rango.', 'trabajadores sin centro de costo en sus labores del rango.');
        }

        $grafica = null;

        if (! $vacio && $diario !== null) {
            $orden = array_values($grupos);
            usort($orden, static fn ($a, $b) => $b['diferencia'] <=> $a['diferencia']);
            $mas   = count($orden) > 20;
            $orden = $mas ? array_merge(array_slice($orden, 0, 10), array_slice($orden, -10)) : $orden;

            $grafica = [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => $mas ? 'Diferencia vs salario mínimo · 10 mayores y 10 menores' : 'Diferencia vs salario mínimo',
                'categorias' => array_column($orden, 'trabajador'),
                'series'     => [['nombre' => 'Diferencia', 'datos' => array_map(static fn ($g) => round($g['diferencia'], 2), $orden)]],
                'colores'    => ['#41a867'],
                'detalle'    => array_map(static fn ($g) => [
                    'ccosto'      => $g['ccosto'],
                    'jornales'    => round($g['jornales'], 2),
                    'valorJornal' => $g['jornales'] == 0 ? null : round($g['valor'] / $g['jornales'], 2),
                    'mesesRinde'  => $g['rinde'] . ' de ' . count($g['meses']),
                ], $orden),
                'etiquetas'  => false,
                'ejeX'       => 'Diferencia',
                'formato'    => 'moneda',
            ];
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Trabajadores evaluados', 'valor' => $vacio ? null : count($grupos), 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $vacio ? '' : self::plural($nMeses, 'trabajador-mes', 'trabajador-mes') . ' · personal propio'],
                ['etiqueta' => '% que rinden', 'valor' => $vacio || $diario === null ? null : round($rinden / count($grupos), 4), 'formato' => 'porcentaje', 'icono' => 'bi bi-check2-circle',
                    'ayuda' => $diario === null ? $sinSmlv : ($vacio ? '' : 'Diferencia ≥ 0 en el total del rango')],
                ['etiqueta' => 'Diferencia total', 'valor' => $vacio || $difTotal === null ? null : round($difTotal, 2), 'formato' => 'moneda',
                    'icono' => $difTotal === null ? 'bi bi-dash-circle' : ($difTotal < 0 ? 'bi bi-arrow-down-circle' : 'bi bi-arrow-up-circle'),
                    'ayuda' => $diario === null ? $sinSmlv : ($vacio ? '' : 'Labores $' . self::n($valor) . ' vs salario $' . self::n($salario))],
                ['etiqueta' => 'Valor por jornal', 'valor' => $vacio || $diario === null ? null : ($porJornal === null ? null : round($porJornal, 2)), 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $diario === null ? $sinSmlv : ($vacio ? '' : 'SMLV/30 $' . self::n($diario)) . ($porJornal === null ? '' : ' · ' . ($porJornal >= $diario ? '+' : '−') . self::n(abs($porJornal / $diario - 1) * 100, 1) . ' %')],
            ],
            'grafica'  => $grafica,
            'columnas' => [
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'documento', 'titulo' => 'Documento', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'ccosto', 'titulo' => 'Centro de costo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false, 'ayuda' => 'Centro de costo predominante en las labores del trabajador en el rango.'],
                ['clave' => 'mes', 'titulo' => 'Mes', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Jornales registrados; si el mes no los tiene, días laborados.'],
                ['clave' => 'valor', 'titulo' => 'Valor labores', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'valorSalario', 'titulo' => 'Valor SMLV', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => $diario !== null, 'ayuda' => 'SMLV ' . $anio . '/30 × jornales'],
                ['clave' => 'diferencia', 'titulo' => 'Diferencia', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => $diario !== null, 'ayuda' => 'Valor labores − valor SMLV'],
                ['clave' => 'valorJornal', 'titulo' => '$/jornal', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'valor', 'denominador' => 'jornales',
                    'ayuda' => $diario === null ? $sinSmlv : 'SMLV/30 = $' . self::n($diario)],
                ['clave' => 'rinde', 'titulo' => 'Rinde', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'numMes', 'titulo' => 'N.º mes', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'diasLaborados', 'titulo' => 'Días laborados', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'jornalesRegistrados', 'titulo' => 'Jornales registrados', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'jornalesEstimados', 'titulo' => 'Jornales por días laborados', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'jornalesTrabajador', 'titulo' => 'Jornales trabajador', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valorTrabajador', 'titulo' => 'Valor trabajador', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'salarioTrabajador', 'titulo' => 'Valor SMLV trabajador', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'diferenciaTrabajador', 'titulo' => 'Diferencia trabajador', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'mesesTrabajador', 'titulo' => 'Meses trabajador', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'mesesRinde', 'titulo' => 'Meses que rinde', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'mesesBajo', 'titulo' => 'Meses bajo el mínimo', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'conMesesBajo', 'titulo' => 'Trabajador con meses bajo el mínimo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => [
                'jornales'     => round(array_sum(array_column($filas, 'jornales')), 2),
                'valor'        => round(array_sum(array_column($filas, 'valor')), 2),
                'valorSalario' => $diario === null ? null : round(array_sum(array_column($filas, 'valorSalario')), 2),
                'diferencia'   => $diario === null ? null : round(array_sum(array_column($filas, 'diferencia')), 2),
                'valorJornal'  => array_sum(array_column($filas, 'jornales')) == 0 ? null : round(array_sum(array_column($filas, 'valor')) / array_sum(array_column($filas, 'jornales')), 2),
            ],
            'agrupar' => 'trabajador',
            'avisos'  => $avisos,
        ];
    }
}
