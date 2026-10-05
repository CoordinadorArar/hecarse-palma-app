<?php

namespace App\Libraries\Informes\Indicadores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class RendimientoTrabajadorCosecha extends Informe
{
    private const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function filtros(): array
    {
        $trabajadores = array_map(
            static fn ($t) => ['valor' => $t['tercero'], 'texto' => $t['tercero'] . ' — ' . (trim($t['nombre']) === '' ? 'Sin nombre en terceros' : trim($t['nombre']))],
            $this->db->query("SELECT x.tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre
                                FROM (SELECT DISTINCT LTRIM(RTRIM(c.tercero)) AS tercero FROM aTransaccionTercero c
                                        JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                                       WHERE c.empresa = ?) x
                               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                                             WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                                             ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) t
                               ORDER BY CASE WHEN ISNULL(LTRIM(RTRIM(t.nombre)), '') = '' THEN 1 ELSE 0 END, t.nombre, x.tercero", [$this->empresa, $this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de cosecha (fecha de novedad).'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los trabajadores', 'opciones' => $trabajadores],
            ['clave' => 'vista', 'etiqueta' => 'Vista', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'resumen',
                'opciones' => [['valor' => 'resumen', 'texto' => 'Resumen'], ['valor' => 'dia', 'texto' => 'Por día']]],
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

    private static function medidas(array $a): array
    {
        $kgDia = $a['dias'] > 0 ? $a['kilos'] / $a['dias'] : null;
        $tarea = $a['kilosTarea'] > 0 ? $a['tareaKilos'] / $a['kilosTarea'] : null;

        return [
            'kgDia'        => $kgDia === null ? null : round($kgDia, 2),
            'kgRacimo'     => $a['racimos'] > 0 ? round($a['kilos'] / $a['racimos'], 2) : null,
            'tarea'        => $tarea === null ? null : round($tarea, 2),
            'cumplimiento' => $tarea === null || $a['diasTarea'] === 0 ? null : round($a['kilosTarea'] / $a['diasTarea'] / $tarea, 4),
        ];
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        foreach (['LTRIM(RTRIM(k.finca))' => $filtros['finca'], 'x.tercero' => $filtros['trabajador']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND {$campo} = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT x.tercero, x.fecha, x.labor, x.tarea, LTRIM(RTRIM(k.codigo)) AS lote, LTRIM(RTRIM(ISNULL(k.finca, ''))) AS finca, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS fincaNombre,
                       SUM(x.signo * x.kilos) AS kilos, SUM(x.signo * x.jornales) AS jornales,
                       SUM(x.signo * ISNULL(b.racimos, 0) * CASE WHEN x.kilosLinea = 0 THEN 1.0 / x.terceros ELSE x.kilos / x.kilosLinea END) AS racimos
                  FROM (SELECT c.empresa, c.tipo, c.numero, c.registroNovedad, LTRIM(RTRIM(c.tercero)) AS tercero, LTRIM(RTRIM(c.novedad)) AS labor, ISNULL(d.tarea, 0) AS tarea,
                               ISNULL(c.cantidad, 0) AS kilos, ISNULL(c.jornales, 0) AS jornales,
                               SUM(ISNULL(c.cantidad, 0)) OVER (PARTITION BY c.empresa, c.tipo, c.numero, c.registroNovedad) AS kilosLinea,
                               COUNT(*) OVER (PARTITION BY c.empresa, c.tipo, c.numero, c.registroNovedad) AS terceros,
                               CASE WHEN b.signo = 2 THEN -1 ELSE 1 END AS signo, CONVERT(date, ISNULL(MIN(c.fechaNovedad) OVER (PARTITION BY c.empresa, c.tipo, c.numero, c.registroNovedad), b.fecha)) AS fecha
                          FROM aTransaccionTercero c
                          JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                         WHERE c.empresa = ?) x
                  JOIN aTransaccionNovedad b ON b.numero = x.numero AND b.tipo = x.tipo AND b.empresa = x.empresa AND b.registro = x.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                  JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                  LEFT JOIN aFinca i ON LTRIM(RTRIM(i.codigo)) = LTRIM(RTRIM(k.finca)) AND i.empresa = k.empresa
                 WHERE x.fecha BETWEEN ? AND ?{$where}
                 GROUP BY x.tercero, x.fecha, x.labor, x.tarea, k.codigo, k.finca, i.descripcion";

        $nombres  = [];
        $trab     = [];
        $labores  = [];
        $sinTarea = 0;

        foreach ($this->db->query($sql, $binds)->getResultArray() as $r) {
            $kilos = (float) $r['kilos'];
            $tarea = (float) $r['tarea'];
            $finca = trim($r['fincaNombre']) === '' ? $r['finca'] : trim($r['fincaNombre']);
            $t     = &$trab[$r['tercero']];
            $d     = &$t['dias'][substr((string) $r['fecha'], 0, 10)];
            $d['kilos']      = ($d['kilos'] ?? 0) + $kilos;
            $d['racimos']    = ($d['racimos'] ?? 0) + (float) $r['racimos'];
            $d['jornales']   = ($d['jornales'] ?? 0) + (float) $r['jornales'];
            $d['kilosTarea'] = ($d['kilosTarea'] ?? 0) + ($tarea > 0 ? $kilos : 0);
            $d['tareaKilos'] = ($d['tareaKilos'] ?? 0) + ($tarea > 0 ? $kilos * $tarea : 0);
            $d['lotes'][$r['lote']] = ($d['lotes'][$r['lote']] ?? 0) + $kilos;
            $t['fincas'][$finca]    = ($t['fincas'][$finca] ?? 0) + $kilos;
            unset($d, $t);

            $labores[$r['labor']] = $tarea;
            $sinTarea += $tarea > 0 ? 0 : 1;
        }

        if ($trab !== []) {
            $codigos = array_map('strval', array_keys($trab));

            foreach (array_chunk($codigos, 500) as $parte) {
                $marcas = implode(',', array_fill(0, count($parte), '?'));

                foreach ($this->db->query("SELECT x.tercero, (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) FROM cTercero
                                                              WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                                                              ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) AS nombre
                                             FROM (SELECT DISTINCT LTRIM(RTRIM(tercero)) AS tercero FROM aTransaccionTercero WHERE empresa = ? AND LTRIM(RTRIM(tercero)) IN ({$marcas})) x",
                    array_merge([$this->empresa, $this->empresa], $parte))->getResultArray() as $r) {
                    $nombres[$r['tercero']] = trim((string) $r['nombre']);
                }
            }
        }

        $resumen = [];

        foreach ($trab as $codigo => $t) {
            ksort($t['dias']);

            $a = ['dias' => 0, 'kilos' => 0, 'racimos' => 0, 'jornales' => 0, 'kilosTarea' => 0, 'tareaKilos' => 0, 'diasTarea' => 0, 'diasCumple' => 0];
            $lotes = [];

            foreach ($t['dias'] as $fecha => &$d) {
                $d['dias']      = 1;
                $d['diasTarea'] = $d['kilosTarea'] > 0 ? 1 : 0;
                $d += self::medidas($d);
                $a['diasCumple'] += $d['cumplimiento'] !== null && $d['cumplimiento'] >= 1 ? 1 : 0;

                foreach (['dias', 'kilos', 'racimos', 'jornales', 'kilosTarea', 'tareaKilos', 'diasTarea'] as $c) {
                    $a[$c] += $d[$c];
                }

                foreach ($d['lotes'] as $lote => $k) {
                    $lotes[$lote] = ($lotes[$lote] ?? 0) + $k;
                }
            }

            unset($d);
            arsort($lotes);
            arsort($t['fincas']);

            $nombre = $nombres[(string) $codigo] ?? '';
            $resumen[(string) $codigo] = $a + self::medidas($a) + [
                'trabajador' => (string) $codigo . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'fincas'     => implode(' · ', array_slice(array_map('strval', array_keys($t['fincas'])), 0, 3)),
                'lotes'      => implode(' · ', array_slice(array_map('strval', array_keys($lotes)), 0, 3)),
                'detalle'    => $t['dias'],
            ];
        }

        uasort($resumen, static fn ($x, $y) => [$y['kgDia'], $x['trabajador']] <=> [$x['kgDia'], $y['trabajador']]);

        $porDia = $filtros['vista'] === 'dia';
        $filas  = [];

        foreach ($resumen as $codigo => $r) {
            $aux = static fn ($a) => [
                'lotesPrincipales' => $r['lotes'],
                'fincasTrabajador' => $r['fincas'],
                'kilosTarea'       => round($a['kilosTarea'], 2),
                'diasTarea'        => $a['diasTarea'],
                'tareaKilos'       => round($a['tareaKilos'], 2),
                'tareaDias'        => $a['kilosTarea'] > 0 ? round($a['tareaKilos'] / $a['kilosTarea'] * $a['diasTarea'], 4) : 0,
            ];

            if (! $porDia) {
                $filas[] = [
                    'trabajador'   => $r['trabajador'],
                    'documento'    => (string) $codigo,
                    'dias'         => $r['dias'],
                    'racimos'      => round($r['racimos'], 4),
                    'kilos'        => round($r['kilos'], 2),
                    'jornales'     => round($r['jornales'], 2),
                    'kgDia'        => $r['kgDia'],
                    'kgRacimo'     => $r['kgRacimo'],
                    'tarea'        => $r['tarea'],
                    'cumplimiento' => $r['cumplimiento'],
                    'racimosExactos' => round($r['racimos'], 4),
                    'bajoTarea'    => $r['cumplimiento'] !== null && $r['cumplimiento'] < 1,
                    'diasCumple'   => $r['diasCumple'],
                ] + $aux($r);

                continue;
            }

            foreach ($r['detalle'] as $fecha => $d) {
                $lotes = $d['lotes'];
                arsort($lotes);
                $filas[] = [
                    'trabajador'   => $r['trabajador'],
                    'documento'    => (string) $codigo,
                    'fecha'        => (string) $fecha,
                    'diaSemana'    => self::DIAS[(int) date('N', strtotime((string) $fecha))],
                    'lotes'        => implode(', ', array_map('strval', array_keys($lotes))),
                    'racimos'      => round($d['racimos'], 4),
                    'kilos'        => round($d['kilos'], 2),
                    'jornales'     => round($d['jornales'], 2),
                    'kgRacimo'     => $d['kgRacimo'],
                    'tarea'        => $d['tarea'],
                    'cumplimiento' => $d['cumplimiento'],
                    'racimosExactos' => round($d['racimos'], 4),
                    'bajoTarea'    => $d['cumplimiento'] !== null && $d['cumplimiento'] < 1,
                    'diasTrabajador'  => $r['dias'],
                    'kilosTrabajador' => round($r['kilos'], 2),
                    'kgDiaTrabajador' => $r['kgDia'],
                    'diasCumple'      => $r['diasCumple'],
                ] + $aux($d);
            }
        }

        $vacio    = $resumen === [];
        $kilos    = array_sum(array_column($resumen, 'kilos'));
        $racimos  = array_sum(array_column($resumen, 'racimos'));
        $jornales = array_sum(array_column($resumen, 'jornales'));
        $tdias    = array_sum(array_column($resumen, 'dias'));
        $promedio = $tdias > 0 ? $kilos / $tdias : null;
        $conTarea = array_filter($resumen, static fn ($r) => $r['cumplimiento'] !== null);
        $cumplen  = count(array_filter($conTarea, static fn ($r) => $r['cumplimiento'] >= 1));
        $avisos   = [];
        $tareas   = array_filter($labores, static fn ($t) => $t > 0);

        $top        = array_slice(array_values(array_filter($resumen, static fn ($r) => $r['dias'] >= 3)), 0, 20);
        $tareasTrab = array_unique(array_map(static fn ($r) => $r['tarea'] === null ? 'x' : (string) $r['tarea'], $resumen));
        $unica      = count($tareasTrab) === 1 && reset($tareasTrab) !== 'x' ? (float) reset($tareasTrab) : null;

        if (! $vacio && $sinTarea > 0) {
            $avisos[] = $tareas === []
                ? 'Ninguna labor de cosecha del rango tiene tarea; el % de cumplimiento no aplica y la gráfica usa el promedio kg/día.'
                : (count($tareas) === 1 ? 'Solo la labor ' . array_key_first($tareas) . ' tiene tarea (' . self::n(reset($tareas)) . ' kg)'
                    : 'Solo las labores ' . implode(', ', array_map(static fn ($l, $t) => $l . ' (' . self::n($t) . ' kg)', array_keys($tareas), $tareas)) . ' tienen tarea')
                . '; en las demás el % de cumplimiento no aplica' . ($unica === null ? ' y la gráfica usa el promedio kg/día.' : '.');
        }

        $columnas = [
            ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'documento', 'titulo' => 'Documento', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
        ];
        $columnas = array_merge($columnas, $porDia ? [
            ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'diaSemana', 'titulo' => 'Día de la semana', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lotes', 'titulo' => 'Lotes', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
        ] : [
            ['clave' => 'dias', 'titulo' => 'Días', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Fechas de cosecha distintas.'],
        ], [
            ['clave' => 'racimos', 'titulo' => 'Racimos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Racimos de la línea repartidos según los kilos de cada trabajador.'],
            ['clave' => 'kilos', 'titulo' => 'Kilos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
        ], $porDia ? [] : [
            ['clave' => 'kgDia', 'titulo' => 'Kg/día', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilos', 'denominador' => 'dias', 'ayuda' => 'Kilos / días trabajados'],
        ], [
            ['clave' => 'kgRacimo', 'titulo' => 'Kg/racimo', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilos', 'denominador' => 'racimosExactos'],
            ['clave' => 'tarea', 'titulo' => 'Tarea (kg)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Tarea diaria de la labor, ponderada por kilos.'],
            ['clave' => 'cumplimiento', 'titulo' => '% cumpl.', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kilosTarea', 'denominador' => 'tareaDias',
                'ayuda' => 'Kg/día en labores con tarea ÷ tarea.'],
            ['clave' => 'racimosExactos', 'titulo' => 'Racimos asignados (exactos)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'bajoTarea', 'titulo' => 'Bajo la tarea', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'diasCumple', 'titulo' => 'Días que cumple', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
        ], $porDia ? [
            ['clave' => 'diasTrabajador', 'titulo' => 'Días trabajador', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'kilosTrabajador', 'titulo' => 'Kilos trabajador', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'kgDiaTrabajador', 'titulo' => 'Kg/día trabajador', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
        ] : [], [
            ['clave' => 'lotesPrincipales', 'titulo' => 'Lotes principales', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'fincasTrabajador', 'titulo' => 'Fincas', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'kilosTarea', 'titulo' => 'Kilos con tarea', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'diasTarea', 'titulo' => 'Días con tarea', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tareaKilos', 'titulo' => 'Tarea × kilos', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'tareaDias', 'titulo' => 'Tarea × días', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
        ]);

        if ($jornales == 0) {
            $columnas = array_values(array_filter($columnas, static fn ($c) => $c['clave'] !== 'jornales'));
            $filas    = array_map(static function ($f) {
                unset($f['jornales']);

                return $f;
            }, $filas);
        }

        $tareaDias  = array_sum(array_column($filas, 'tareaDias'));
        $racExactos = array_sum(array_column($filas, 'racimosExactos'));

        return [
            'kpis' => [
                ['etiqueta' => 'Kilos cosechados', 'valor' => $vacio ? null : round($kilos, 2), 'formato' => 'entero', 'icono' => 'bi bi-truck',
                    'ayuda' => $vacio ? '' : self::n($kilos / 1000, 2) . ' t · ' . self::n($racimos) . ' racimos'],
                ['etiqueta' => 'Kg por trabajador-día', 'valor' => $promedio === null ? null : round($promedio, 2), 'formato' => 'entero', 'icono' => 'bi bi-speedometer2',
                    'ayuda' => $vacio ? '' : self::plural($tdias, 'trabajador-día', 'trabajador-días')],
                ['etiqueta' => '% que cumplen la tarea', 'valor' => $conTarea === [] ? null : round($cumplen / count($conTarea), 4), 'formato' => 'porcentaje', 'icono' => 'bi bi-bullseye',
                    'ayuda' => $vacio ? '' : ($conTarea === [] ? 'Sin labores con tarea en el rango' : 'Sobre ' . self::plural(count($conTarea), 'trabajador con tarea', 'trabajadores con tarea'))],
                ['etiqueta' => 'Trabajadores activos', 'valor' => $vacio ? null : count($resumen), 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $vacio ? '' : 'Con cosecha en el rango'],
            ],
            'grafica' => $top === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Kg/día · top 20 (mín. 3 días)',
                'categorias' => array_column($top, 'trabajador'),
                'series'     => [['nombre' => 'Kg/día', 'datos' => array_column($top, 'kgDia')]],
                'colores'    => ['#41a867'],
                'detalle'    => array_map(static fn ($r) => [
                    'dias'         => $r['dias'],
                    'kilos'        => round($r['kilos'], 2),
                    'racimos'      => (int) round($r['racimos']),
                    'kgRacimo'     => $r['kgRacimo'],
                    'cumplimiento' => $r['cumplimiento'],
                ], $top),
                'referencia' => $unica !== null
                    ? ['valor' => $unica, 'etiqueta' => 'Tarea ' . self::n($unica) . ' kg', 'color' => '#f0a500']
                    : ['valor' => round((float) $promedio, 2), 'etiqueta' => 'Promedio ' . self::n((float) $promedio) . ' kg/día', 'color' => '#4a5b6c'],
                'ejeX'       => 'Kg/día',
                'formato'    => 'entero',
            ],
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => array_filter([
                'dias'         => $porDia ? null : $tdias,
                'racimos'      => round($racimos, 4),
                'kilos'        => round($kilos, 2),
                'jornales'     => $jornales == 0 ? null : round($jornales, 2),
                'kgDia'        => $porDia || $promedio === null ? null : round($promedio, 2),
                'kgRacimo'     => $racExactos > 0 ? round($kilos / $racExactos, 2) : null,
                'cumplimiento' => $tareaDias > 0 ? round(array_sum(array_column($filas, 'kilosTarea')) / $tareaDias, 4) : null,
            ], static fn ($v) => $v !== null),
            'agrupar' => $porDia ? 'trabajador' : null,
            'avisos'  => $avisos,
        ];
    }
}
