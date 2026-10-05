<?php

namespace App\Libraries\Informes\Indicadores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class CiclosCorteRacimos extends Informe
{
    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const NOMBRES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT [año] AS anio FROM aTransaccion WHERE empresa = ? AND tipo = 'TLC' AND anulado = 0 AND [año] > 0 ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $anio    = in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? '');

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => $anio, 'ayuda' => 'Año de cosecha (fecha de novedad).', 'opciones' => $anios],
            ['clave' => 'mes', 'etiqueta' => 'Mes', 'tipo' => 'mes', 'obligatorio' => true, 'defecto' => $anio === date('Y') ? date('n') : '12',
                'opciones' => array_map(static fn ($m, $n) => ['valor' => (string) $m, 'texto' => $n], array_keys(self::NOMBRES), self::NOMBRES)],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'umbral', 'etiqueta' => 'Umbral ciclo largo (días)', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => '15', 'ayuda' => 'Intervalo a partir del cual el ciclo se considera largo',
                'opciones' => array_map(static fn ($u) => ['valor' => (string) $u, 'texto' => $u . ' días'], [10, 12, 15, 18, 21, 30])],
            ['clave' => 'soloCorte', 'etiqueta' => 'Solo lotes con corte', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'N', 'opciones' => [['valor' => 'S', 'texto' => 'Sí'], ['valor' => 'N', 'texto' => 'No']]],
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

        return is_array($v) && sprintf('%04d-%02d', $v['anio'], $v['mes']) > date('Y-m') ? 'El mes elegido aún no ha comenzado.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $anio   = (int) $filtros['anio'];
        $mes    = (int) $filtros['mes'];
        $umbral = (int) $filtros['umbral'];
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin    = date('Y-m-t', strtotime($inicio));
        $dias   = (int) date('t', strtotime($inicio));
        $corte  = min($fin, date('Y-m-d'));
        $hoy    = (int) substr($corte, 8, 2);

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $fincaSql = $filtros['finca'] === '' ? '' : ' AND LTRIM(RTRIM(k.finca)) = ?';
        $binds    = $filtros['finca'] === '' ? [$this->empresa] : [$this->empresa, $filtros['finca']];

        $lotes = $this->db->query("SELECT LTRIM(RTRIM(k.codigo)) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(ISNULL(k.finca, ''))) AS finca,
                                          CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS fincaNombre, CAST(ISNULL(v.descripcion, '') AS varchar(100)) AS variedad, ISNULL(k.[añoSiembra], 0) AS anioSiembra
                                     FROM aLotes k
                                     LEFT JOIN aFinca i ON LTRIM(RTRIM(i.codigo)) = LTRIM(RTRIM(k.finca)) AND i.empresa = k.empresa
                                    OUTER APPLY (SELECT TOP 1 descripcion FROM aVariedad WHERE empresa = k.empresa AND codigo = k.variedad) v
                                    WHERE k.empresa = ? AND k.activo = 1{$fincaSql}
                                    ORDER BY LTRIM(RTRIM(ISNULL(k.finca, ''))), k.numero, k.[añoSiembra], k.letra, k.codigo", $binds)->getResultArray();

        $base = "SELECT LTRIM(RTRIM(k.codigo)) AS lote, e.fecha, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(b.racimos, 0) AS racimos
                   FROM aTransaccionNovedad b
                   JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                  CROSS APPLY (SELECT MIN(c.fechaNovedad) AS fechaNovedad
                                 FROM aTransaccionTercero c
                                 JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                                WHERE c.empresa = b.empresa AND c.tipo = b.tipo AND c.numero = b.numero AND c.registroNovedad = b.registro
                               HAVING COUNT(*) > 0) t
                  CROSS APPLY (SELECT CONVERT(date, ISNULL(t.fechaNovedad, b.fecha)) AS fecha) e
                   JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa AND k.activo = 1
                  WHERE b.empresa = ?{$fincaSql}";

        $mesDatos = $this->db->query("SELECT lote, DAY(fecha) AS dia, SUM(racimos) AS racimos FROM ({$base}) x WHERE fecha BETWEEN ? AND ? GROUP BY lote, DAY(fecha)", array_merge($binds, [$inicio, $fin]))->getResultArray();
        $previos  = array_column($this->db->query("SELECT lote, CONVERT(varchar(10), MAX(fecha), 23) AS fecha FROM (SELECT lote, fecha FROM ({$base}) x WHERE fecha < ? GROUP BY lote, fecha HAVING SUM(racimos) > 0) y GROUP BY lote", array_merge($binds, [$inicio]))->getResultArray(), 'fecha', 'lote');

        $racimos = [];

        foreach ($mesDatos as $d) {
            $racimos[$d['lote']][(int) $d['dia']] = (int) $d['racimos'];
        }

        $filas    = [];
        $mapa     = [];
        $detalle  = [];
        $diasCon  = [];
        $porFinca = [];
        $diaN     = static fn (string $f) => intdiv(strtotime($f . ' UTC'), 86400);

        foreach ($lotes as $l) {
            $r      = $racimos[$l['lote']] ?? [];
            $previo = $previos[$l['lote']] ?? null;
            $ultimo = $previo === null ? null : $diaN($previo);
            $fila   = ['finca' => $l['finca'] . ' — ' . trim($l['fincaNombre']), 'lote' => $l['lote'] . ' — ' . trim($l['loteNombre']),
                'anioSiembra' => (int) $l['anioSiembra'] === 0 ? '' : (string) (int) $l['anioSiembra'], 'variedad' => trim($l['variedad'])];
            $calor  = [];
            $notas  = [];
            $fechas = $ultimo === null ? [] : [$ultimo];
            $pases  = 0;

            for ($i = 1; $i <= $dias; $i++) {
                $valor = $r[$i] ?? 0;
                $fila['d' . $i] = $i > $hoy || $valor == 0 ? null : $valor;
                $calor[] = $i > $hoy ? -2 : ($valor > 0 ? $valor : -1);
                $nota    = null;

                if ($i <= $hoy && $valor > 0) {
                    $dia   = $diaN(sprintf('%s-%02d', substr($inicio, 0, 7), $i));
                    $sigue = $ultimo !== null && $dia - $ultimo === 1;
                    $nota  = $sigue ? 'Mismo pase del día anterior' : ($ultimo === null ? 'Sin corte anterior registrado' : self::plural($dia - $ultimo, 'día', 'días') . ' desde el corte anterior');
                    $pases += $sigue && $pases > 0 ? 0 : 1;

                    if ($sigue) {
                        array_pop($fechas);
                    }

                    $fechas[] = $dia;
                    $ultimo   = $dia;
                    $diasCon[$i] = true;
                }

                $notas[] = $nota;
            }

            $inter = array_map(static fn ($a, $b) => $b - $a, array_slice($fechas, 0, -1), array_slice($fechas, 1));

            $porFinca[$l['finca']]['lotes'] = ($porFinca[$l['finca']]['lotes'] ?? 0) + 1;

            if ($filtros['soloCorte'] === 'S' && $pases === 0) {
                continue;
            }

            $sinCorte = $ultimo === null ? null : $diaN($corte) - $ultimo;
            $intMax   = $inter === [] ? null : max($inter);
            $largo    = ($intMax !== null && $intMax > $umbral) || ($sinCorte !== null && $sinCorte > $umbral);
            $total    = array_sum(array_filter($r, static fn ($v, $k) => $k <= $hoy, ARRAY_FILTER_USE_BOTH));

            $filas[] = $fila + [
                'racimos'         => $total,
                'pases'           => $pases,
                'intProm'         => $inter === [] ? null : round(array_sum($inter) / count($inter), 2),
                'intMax'          => $intMax,
                'diasSinCorte'    => $sinCorte,
                'ultimoCorte'     => $ultimo === null ? '' : gmdate('Y-m-d', $ultimo * 86400),
                'corteAnterior'   => $previo ?? '',
                'sumaIntervalos'  => array_sum($inter),
                'nIntervalos'     => count($inter),
                'cicloLargo'      => $largo,
                'umbral'          => $umbral,
                'fincaCodigo'     => $l['finca'],
            ];

            $p = &$porFinca[$l['finca']];
            $p['cosechados'] = ($p['cosechados'] ?? 0) + ($pases > 0 ? 1 : 0);
            $p['racimos']    = ($p['racimos'] ?? 0) + $total;
            $p['suma']       = ($p['suma'] ?? 0) + array_sum($inter);
            $p['n']          = ($p['n'] ?? 0) + count($inter);
            $p['largos']     = ($p['largos'] ?? 0) + ($largo ? 1 : 0);
            unset($p);

            $mapa[]    = ['nombre' => $l['lote'], 'datos' => $calor];
            $detalle[] = ['lote' => $fila['lote'], 'finca' => $fila['finca'], 'siembra' => $fila['anioSiembra'], 'notas' => $notas];
        }

        foreach ($filas as &$f) {
            $p = $porFinca[$f['fincaCodigo']];
            unset($f['fincaCodigo']);
            $f += [
                'lotesFinca'      => $p['lotes'],
                'cosechadosFinca' => $p['cosechados'],
                'racimosFinca'    => $p['racimos'],
                'intPromFinca'    => $p['n'] > 0 ? round($p['suma'] / $p['n'], 2) : null,
                'largosFinca'     => $p['largos'],
                'fincaCicloLargo' => $p['largos'] > 0,
            ];
        }

        unset($f);

        $vacio      = $filas === [];
        $suma       = static fn (string $c) => array_sum(array_column($filas, $c));
        $activos    = count($lotes);
        $cosechados = count(array_filter($filas, static fn ($f) => $f['pases'] > 0));
        $nInt       = $suma('nIntervalos');
        $conInt     = count(array_filter($filas, static fn ($f) => $f['nIntervalos'] > 0));
        $largos     = count(array_filter($filas, static fn ($f) => $f['cicloLargo']));
        $nombreMes  = self::NOMBRES[$mes] . ' ' . $anio;
        $avisos     = [];

        if ($activos === 0) {
            $avisos[] = 'No hay lotes activos con los filtros seleccionados.';
        } elseif ($cosechados === 0) {
            $avisos[] = 'No hubo corte de racimos en ' . $nombreMes . ' con los filtros seleccionados.';
        }

        if ($corte < $fin) {
            $avisos[] = 'Mes en curso: datos hasta ' . date('d/m/Y', strtotime($corte)) . '; los días siguientes se muestran atenuados y los días sin corte se cuentan hasta hoy.';
        }

        $columnas = [
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'variedad', 'titulo' => 'Variedad', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
        ];
        $totales = [];

        for ($i = 1; $i <= $dias; $i++) {
            $columnas[] = ['clave' => 'd' . $i, 'titulo' => (string) $i, 'tipo' => 'entero', 'alineacion' => 'derecha'] + ($i > $hoy ? ['total' => false, 'futuro' => true, 'ayuda' => 'Día no transcurrido'] : ['total' => true]);

            if ($i <= $hoy) {
                $totales['d' . $i] = $suma('d' . $i);
            }
        }

        $columnas = array_merge($columnas, [
            ['clave' => 'racimos', 'titulo' => 'Total racimos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
            ['clave' => 'pases', 'titulo' => 'Pases', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Días consecutivos con corte cuentan como un pase'],
            ['clave' => 'intProm', 'titulo' => 'Int. prom.', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'sumaIntervalos', 'denominador' => 'nIntervalos',
                'ayuda' => 'Promedio de días entre pases; el primero cuenta desde el último corte del mes anterior'],
            ['clave' => 'intMax', 'titulo' => 'Int. máx.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Mayor número de días entre pases'],
            ['clave' => 'diasSinCorte', 'titulo' => 'Días sin corte', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Desde el último corte hasta el fin de mes (u hoy)'],
            ['clave' => 'ultimoCorte', 'titulo' => 'Último corte', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'corteAnterior', 'titulo' => 'Último corte antes del mes', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'sumaIntervalos', 'titulo' => 'Suma de intervalos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'nIntervalos', 'titulo' => 'N.º de intervalos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'cicloLargo', 'titulo' => 'Ciclo largo', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'umbral', 'titulo' => 'Umbral (días)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'lotesFinca', 'titulo' => 'Lotes de la finca', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'cosechadosFinca', 'titulo' => 'Lotes cosechados de la finca', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'racimosFinca', 'titulo' => 'Racimos de la finca', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'intPromFinca', 'titulo' => 'Int. prom. de la finca', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'largosFinca', 'titulo' => 'Lotes en ciclo largo de la finca', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'fincaCicloLargo', 'titulo' => 'Finca con ciclo largo', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
        ]);

        $totales += ['racimos' => $suma('racimos'), 'intProm' => $nInt > 0 ? round($suma('sumaIntervalos') / $nInt, 2) : null];

        return [
            'kpis' => [
                ['etiqueta' => 'Racimos del mes', 'valor' => $vacio ? null : $suma('racimos'), 'formato' => 'entero', 'icono' => 'bi bi-basket',
                    'ayuda' => $nombreMes . ' · ' . self::plural(count($diasCon), 'día con corte', 'días con corte')],
                ['etiqueta' => 'Lotes cosechados', 'valor' => $activos === 0 ? null : self::n($cosechados) . ' / ' . self::n($activos), 'formato' => 'texto', 'icono' => 'bi bi-check2-square',
                    'ayuda' => $activos === 0 ? '' : self::n($cosechados / $activos * 100, 1) . ' % de los lotes activos'],
                ['etiqueta' => 'Intervalo promedio', 'valor' => $nInt > 0 ? round($suma('sumaIntervalos') / $nInt, 2) : null, 'formato' => 'decimal', 'icono' => 'bi bi-arrow-repeat',
                    'ayuda' => $nInt > 0 ? 'Días entre pases · ' . self::plural($conInt, 'lote con 2 o más pases', 'lotes con 2 o más pases') : 'Sin lotes con 2 o más pases'],
                ['etiqueta' => 'Lotes en ciclo largo', 'valor' => $vacio ? null : $largos, 'formato' => 'entero', 'icono' => 'bi bi-exclamation-triangle',
                    'ayuda' => 'Int. máx. o días sin corte > ' . $umbral . ' días'],
            ],
            'grafica'  => null,
            'graficas' => $cosechados === 0 ? [] : [[
                'tipo'       => 'heatmap',
                'titulo'     => 'Rotación de corte · ' . $nombreMes,
                'icono'      => 'bi bi-grid-3x3',
                'categorias' => array_map('strval', range(1, $dias)),
                'series'     => $mapa,
                'detalle'    => $detalle,
                'medida'     => 'Racimos',
                'formato'    => 'entero',
                'ejeX'       => 'Día',
                'sufijoX'    => ' ' . self::MESES[$mes] . ' ' . $anio,
                'centinelas' => ['-1' => ['nombre' => 'Sin corte', 'color' => '#f1f3f5'], '-2' => ['nombre' => 'Día no transcurrido', 'color' => '#e3e7eb']],
            ]],
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'finca',
            'avisos'   => $avisos,
        ];
    }
}
