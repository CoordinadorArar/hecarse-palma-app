<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class RegistroTiquetesFecha extends Informe
{
    private const NIT = "COALESCE(NULLIF(LTRIM(RTRIM(%1\$s.terceroExtractrora)), ''), NULLIF(LTRIM(RTRIM(%1\$s.empresaExtractora)), ''), NULLIF(LTRIM(RTRIM(%1\$s.planta)), ''))";

    public function filtros(): array
    {
        $extractoras = array_map(
            static fn ($t) => ['valor' => $t['nit'], 'texto' => $t['nit'] . ' — ' . trim($t['nombre'])],
            $this->db->query("SELECT DISTINCT LTRIM(RTRIM(nit)) AS nit, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), ISNULL(descripcion, '')) AS nombre FROM cTercero
                               WHERE empresa = ? AND extractora = 1 AND LTRIM(RTRIM(ISNULL(nit, ''))) <> '' ORDER BY nombre ASC", [$this->empresa])->getResultArray()
        );
        $sinNit = $this->db->query('SELECT TOP 1 1 AS hay FROM aTransaccionBascula b WHERE b.empresa = ? AND b.tipo = ? AND ' . sprintf(self::NIT, 'b') . ' IS NULL', [$this->empresa, 'TLC'])->getRow() !== null;

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de la transacción TLC, no la de báscula.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', []), 'ayuda' => 'Tiquetes con al menos un lote en la finca.'],
            ['clave' => 'extractora', 'etiqueta' => 'Extractora', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las extractoras', 'opciones' => $sinNit ? [...$extractoras, ['valor' => '__SIN__', 'texto' => 'Sin extractora']] : $extractoras],
            ['clave' => 'tiquete', 'etiqueta' => 'Tiquete', 'tipo' => 'texto', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Número o parte del tiquete', 'ayuda' => 'Búsqueda parcial dentro del rango de fechas.'],
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
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = %1$s.empresa AND el.tipo = %1$s.tipo AND el.numero = LTRIM(RTRIM(%1$s.numero)))';

        $where = '';
        $binds = [$this->empresa, $this->empresa, 'TLC', $filtros['fechaInicial'], $filtros['fechaFinal']];

        if ($filtros['finca'] !== '') {
            $where  .= ' AND EXISTS (SELECT 1 FROM aTransaccionNovedad n WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero AND LTRIM(RTRIM(n.finca)) = ?)';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['extractora'] !== '') {
            $where .= $filtros['extractora'] === '__SIN__' ? ' AND x.nit IS NULL' : ' AND t.nit = ?';
            $binds  = $filtros['extractora'] === '__SIN__' ? $binds : [...$binds, $filtros['extractora']];
        }

        if ($filtros['tiquete'] !== '') {
            $where  .= " AND LTRIM(RTRIM(b.tiquete)) LIKE ? ESCAPE '!'";
            $binds[] = '%' . strtr($filtros['tiquete'], ['!' => '!!', '%' => '!%', '_' => '!_', '[' => '![']) . '%';
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(a.tipo)) AS tipo, CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS numero, CONVERT(char(10), a.fecha, 120) AS fecha,
                       LTRIM(RTRIM(ISNULL(b.tiquete, ''))) AS tiquete, ISNULL(CONVERT(varchar(16), b.fecha, 120), '') AS fechaBascula, UPPER(LTRIM(RTRIM(ISNULL(b.vehiculo, '')))) AS vehiculo,
                       LTRIM(RTRIM(ISNULL(b.remolque, ''))) AS remolque, ISNULL(b.interno, 0) AS interno, LTRIM(RTRIM(ISNULL(b.nombreConductor, ''))) AS conductor,
                       LTRIM(RTRIM(ISNULL(b.codigoConductor, ''))) AS codigoConductor, ISNULL(b.sacos, 0) AS sacos, ISNULL(b.pesoBruto, 0) AS bruto, ISNULL(b.pesoTara, 0) AS tara,
                       ISNULL(b.pesoNeto, 0) AS neto, ISNULL(b.racimos, 0) AS racimos, ISNULL(x.nit, '') AS nitTiquete, ISNULL(t.nit, '') AS nit, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre,
                       ISNULL(STUFF((SELECT DISTINCT '|' + LTRIM(RTRIM(ISNULL(f.descripcion, n.finca))) FROM aTransaccionNovedad n LEFT JOIN aFinca f ON f.empresa = n.empresa AND f.codigo = n.finca
                                     WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero FOR XML PATH(''), TYPE).value('.', 'varchar(max)'), 1, 1, ''), '') AS fincas,
                       CASE WHEN LTRIM(RTRIM(ISNULL(b.tiquete, ''))) = '' THEN '' ELSE ISNULL(STUFF((SELECT ', ' + LTRIM(RTRIM(ra.tipo)) + '-' + CAST(CAST(SUBSTRING(LTRIM(RTRIM(ra.numero)), LEN(ra.tipo) + 1, 50) AS bigint) AS varchar(20))
                                     FROM aTransaccionBascula r JOIN aTransaccion ra ON ra.empresa = r.empresa AND ra.tipo = r.tipo AND ra.numero = r.numero AND ra.anulado = 0" . sprintf($eliminadas, 'ra') . "
                                     WHERE r.empresa = b.empresa AND r.tipo = b.tipo AND r.numero <> b.numero AND LTRIM(RTRIM(r.tiquete)) = LTRIM(RTRIM(b.tiquete)) AND ISNULL(" . sprintf(self::NIT, 'r') . ", '') = ISNULL(x.nit, '')
                                     ORDER BY ra.numero FOR XML PATH(''), TYPE).value('.', 'varchar(max)'), 1, 2, ''), '') END AS repetido
                  FROM aTransaccion a
                  JOIN aTransaccionBascula b ON b.empresa = a.empresa AND b.tipo = a.tipo AND b.numero = a.numero
                 CROSS APPLY (SELECT " . sprintf(self::NIT, 'b') . " AS nit) x
                 OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(nit)) AS nit, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.nit OR (LEN(x.nit) > 6 AND LTRIM(RTRIM(nit)) = LEFT(x.nit, LEN(x.nit) - 1)))
                               ORDER BY ISNULL(extractora, 0) DESC, CASE WHEN LTRIM(RTRIM(nit)) = x.nit THEN 0 ELSE 1 END) t
                 WHERE a.empresa = ? AND a.tipo = ? AND a.anulado = 0" . sprintf($eliminadas, 'a') . " AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}
                 ORDER BY a.fecha ASC, numero ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '102400');
        ini_set('memory_limit', '256M');

        $query  = $this->db->query($sql, $binds);
        $avisos    = [];
        $filas     = [];
        $repetidos = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' tiquetes. Filtre por finca o extractora, o reduzca el rango.';

                break;
            }

            $neto    = (int) $c['neto'];
            $repetidos += $c['repetido'] === '' ? [] : [$c['tiquete'] . '|' . $c['nitTiquete'] => true];
            $racimos = (int) $c['racimos'];
            $bascula = $c['fechaBascula'] === '' ? null : strtotime($c['fechaBascula']);
            $nombre  = trim($c['nombre']);
            $filas[] = [
                'extractora'      => $c['nit'] !== '' ? $c['nit'] . ' — ' . $nombre : ($c['nitTiquete'] !== '' ? $c['nitTiquete'] . ' — Extractora no encontrada' : 'Sin extractora'),
                'fecha'           => $c['fecha'],
                'transaccion'     => $c['tipo'] . '-' . $c['numero'],
                'tiquete'         => $c['tiquete'],
                'fechaTiquete'    => $bascula === null ? '' : date('d/m/Y', $bascula),
                'finca'           => implode(' · ', array_filter(explode('|', $c['fincas']), static fn ($v) => $v !== '')),
                'vehiculo'        => $c['vehiculo'],
                'remolque'        => $c['remolque'],
                'interno'         => (int) $c['interno'] === 1 ? 'Sí' : 'No',
                'conductor'       => $c['conductor'],
                'codigoConductor' => $c['codigoConductor'],
                'sacos'           => (int) $c['sacos'],
                'bruto'           => (int) $c['bruto'],
                'tara'            => (int) $c['tara'],
                'neto'            => $neto,
                'racimos'         => $racimos,
                'kgRacimo'        => $racimos > 0 ? round($neto / $racimos, 2) : null,
                'netoConRacimos'  => $racimos > 0 ? $neto : 0,
                'sinExtractora'   => $c['nit'] === '',
                'descuadre'       => $neto !== (int) $c['bruto'] - (int) $c['tara'],
                'sinRacimos'      => $racimos === 0,
                'repetido'        => $c['repetido'],
                'bascula'         => $bascula !== null && date('Y-m-d', $bascula) !== $c['fecha'] ? 'Báscula ' . date(date('Y', $bascula) === substr($c['fecha'], 0, 4) ? 'd/m' : 'd/m/Y', $bascula) : '',
                'serie'           => $c['nit'] !== '' ? $nombre : ($c['nitTiquete'] !== '' ? 'Extractora no encontrada' : 'Sin extractora'),
            ];
        }

        $query->freeResult();

        $totalRacimos = array_sum(array_column($filas, 'racimos'));
        $promedio     = $totalRacimos > 0 ? array_sum(array_column($filas, 'netoConRacimos')) / $totalRacimos : null;
        $conRacimos   = count($filas) - count(array_filter(array_column($filas, 'sinRacimos')));
        $semanal      = (strtotime($filtros['fechaFinal']) - strtotime($filtros['fechaInicial'])) / 86400 > 45;
        $lunes        = static fn (string $f) => date('Y-m-d', strtotime($f . ' -' . (date('N', strtotime($f)) - 1) . ' days'));
        $anio         = substr($semanal ? $lunes($filtros['fechaInicial']) : $filtros['fechaInicial'], 0, 4) === substr($filtros['fechaFinal'], 0, 4) ? 'd/m' : 'd/m/Y';
        $porGrupo     = [];
        $nombres      = [];
        $periodos     = [];

        foreach ($filas as &$f) {
            $porGrupo[$f['extractora']] = ($porGrupo[$f['extractora']] ?? 0) + $f['neto'];
            $nombres[$f['extractora']]  = $f['serie'];
            $p                          = &$periodos[$semanal ? $lunes($f['fecha']) : $f['fecha']];
            $p['kg'][$f['extractora']]  = ($p['kg'][$f['extractora']] ?? 0) + $f['neto'];
            $p['tiquetes']              = ($p['tiquetes'] ?? 0) + 1;
            $p['racimos']               = ($p['racimos'] ?? 0) + $f['racimos'];
            $p['netoRacimos']           = ($p['netoRacimos'] ?? 0) + $f['netoConRacimos'];
            unset($p, $f['serie']);
        }

        unset($f);

        usort($filas, static fn ($x, $y) => [$porGrupo[$y['extractora']], $x['extractora']] <=> [$porGrupo[$x['extractora']], $y['extractora']]);

        foreach ([
            'descuadre'     => ['tiquete con neto descuadrado (neto ≠ bruto − tara).', 'tiquetes con neto descuadrado (neto ≠ bruto − tara).'],
            'sinRacimos'    => ['tiquete sin racimos.', 'tiquetes sin racimos.'],
            'repetido'      => ['tiquete registrado en más de una TLC.', 'tiquetes registrados en más de una TLC.'],
            'sinExtractora' => ['tiquete con extractora no encontrada en terceros.', 'tiquetes con extractora no encontrada en terceros.'],
        ] as $clave => [$uno, $varios]) {
            $n = $clave === 'repetido' ? count($repetidos) : count(array_filter(array_column($filas, $clave)));

            if ($n > 0) {
                $avisos[] = self::plural($n, $uno, $varios);
            }
        }

        arsort($porGrupo);
        ksort($periodos);

        $top     = array_slice(array_keys($porGrupo), 0, 5);
        $otras   = count($porGrupo) > 5;
        $series  = array_map(static fn ($g) => ['nombre' => $nombres[$g], 'datos' => array_map(static fn ($p) => $p['kg'][$g] ?? null, array_values($periodos))], $top);
        $detalle = [];

        if ($otras) {
            $series[] = ['nombre' => 'Otras', 'datos' => array_map(static fn ($p) => array_sum(array_diff_key($p['kg'], array_flip($top))) ?: null, array_values($periodos))];
        }

        foreach ($periodos as $clave => $p) {
            $detalle[] = ($semanal ? ['rango' => date('d/m', strtotime(max($clave, $filtros['fechaInicial']))) . ' – ' . date('d/m', strtotime(min(date('Y-m-d', strtotime($clave . ' +6 days')), $filtros['fechaFinal'])))] : [])
                + ['tiquetes' => $p['tiquetes'], 'racimos' => $p['racimos'], 'pesoPromedio' => $p['racimos'] > 0 ? round($p['netoRacimos'] / $p['racimos'], 2) : null];
        }

        $tiquetes = count($filas);
        $sinRac   = $tiquetes - $conRacimos;
        $totales  = [
            'sacos'    => array_sum(array_column($filas, 'sacos')),
            'bruto'    => array_sum(array_column($filas, 'bruto')),
            'tara'     => array_sum(array_column($filas, 'tara')),
            'neto'     => array_sum(array_column($filas, 'neto')),
            'racimos'  => $totalRacimos,
            'kgRacimo' => $promedio === null ? null : round($promedio, 2),
        ];

        return [
            'kpis' => [
                ['etiqueta' => 'Kilos netos', 'valor' => $totales['neto'], 'formato' => 'entero', 'icono' => 'bi bi-truck',
                    'ayuda' => $tiquetes > 0 ? 'Promedio ' . self::n($totales['neto'] / $tiquetes) . ' kg por tiquete' : ''],
                ['etiqueta' => 'Tiquetes', 'valor' => $tiquetes, 'formato' => 'entero', 'icono' => 'bi bi-ticket-perforated',
                    'ayuda' => $tiquetes > 0 ? self::plural(count(array_unique(array_column($filas, 'fecha'))), 'día con despachos', 'días con despachos') . ($repetidos !== [] ? ' · ' . self::plural(count($repetidos), 'repetido en varias TLC', 'repetidos en varias TLC') : '') : ''],
                ['etiqueta' => 'Racimos', 'valor' => $totalRacimos, 'formato' => 'entero', 'icono' => 'bi bi-basket',
                    'ayuda' => $tiquetes === 0 ? '' : ($sinRac > 0 ? self::plural($sinRac, 'tiquete sin racimos', 'tiquetes sin racimos') : 'Todos los tiquetes con racimos')],
                ['etiqueta' => 'Peso promedio por racimo', 'valor' => $totales['kgRacimo'], 'formato' => 'decimal', 'icono' => 'bi bi-speedometer2', 'ayuda' => 'Ponderado; excluye tiquetes sin racimos'],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => true,
                'titulo'     => 'Kilos netos' . ($semanal ? ' por semana' : ' por día'),
                'categorias' => array_map(static fn ($k) => ($semanal ? 'Sem. ' : '') . date($anio, strtotime((string) $k)), array_keys($periodos)),
                'series'     => $series,
                'colores'    => [...array_slice(['#41a867', '#2f7a4b', '#7cc49a', '#4a5b6c', '#f0a500'], 0, count($top)), ...($otras ? ['#9aa5b1'] : [])],
                'detalle'    => $detalle,
                'ejeY'       => 'Kg netos',
                'formato'    => 'entero',
            ],
            'columnas' => [
                ['clave' => 'extractora', 'titulo' => 'Extractora', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'transaccion', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'tiquete', 'titulo' => 'Tiquete', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fechaTiquete', 'titulo' => 'Fecha báscula', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'vehiculo', 'titulo' => 'Vehículo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'remolque', 'titulo' => 'Remolque', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'interno', 'titulo' => 'Interno', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'conductor', 'titulo' => 'Conductor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'codigoConductor', 'titulo' => 'Cód. conductor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'sacos', 'titulo' => 'Sacos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'bruto', 'titulo' => 'Kg bruto', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'tara', 'titulo' => 'Kg tara', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'neto', 'titulo' => 'Kg netos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Peso neto de báscula.'],
                ['clave' => 'racimos', 'titulo' => 'Racimos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'kgRacimo', 'titulo' => 'Kg/racimo', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'netoConRacimos', 'denominador' => 'racimos',
                    'ayuda' => 'Kg netos / racimos. El total es ponderado y excluye tiquetes sin racimos.'],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'extractora',
            'avisos'  => $avisos,
        ];
    }
}
