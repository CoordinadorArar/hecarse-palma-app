<?php

namespace App\Models;

class PlaneacionSugerenciaModel extends PlaneacionModel
{
    private const VENTANA    = 1095;
    private const BRECHA     = 7;
    private const ABANDONO   = 90;
    private const MIN_LOTE   = 3;
    private const MIN_LABOR  = 5;
    private const MIN_CICLO  = 7;

    private ?bool $sugerencias = null;

    public function sugerenciasDisponible(): bool
    {
        if ($this->sugerencias === null) {
            $this->sugerencias = $this->db->query("SELECT OBJECT_ID('dbo.pPlaneacionSugerencia', 'U') AS id")->getRow()->id !== null;
        }

        return $this->sugerencias;
    }

    public function sugerencias(int $empresa, string $fecha, int $dias, string $finca = ''): array
    {
        $dia    = static fn (string $f): int => intdiv(strtotime($f . ' 00:00:00 UTC'), 86400);
        $fechaD = static fn (int $d): string => gmdate('Y-m-d', $d * 86400);
        $ref    = $dia($fecha);
        $limite = $ref + $dias;
        $desde  = $fechaD($ref - self::VENTANA);

        $labores = array_column($this->db->query(
            "SELECT RTRIM(n.codigo) AS codigo, MIN(n.descripcion) AS descripcion, MIN(RTRIM(ISNULL(n.uMedida, ''))) AS uMedida,
                    MIN(LTRIM(RTRIM(n.grupo))) AS grupo, MAX(ISNULL(n.ciclos, 0)) AS ciclos, ISNULL(MIN(g.descripcion), '') AS grupoNombre
               FROM aNovedad n
               LEFT JOIN aGrupoNovedad g ON g.empresa = n.empresa AND LTRIM(RTRIM(g.codigo)) = LTRIM(RTRIM(n.grupo))
              WHERE n.empresa = ? AND n.activo = 1 AND n.manejaLote = 1 AND ISNULL(n.claseLabor, 0) <> 2
              GROUP BY RTRIM(n.codigo)",
            [$empresa]
        )->getResultArray(), null, 'codigo');

        $grupos = array_column($labores, 'grupoNombre', 'grupo');
        $lotes  = [];

        foreach ($this->db->query(
            "SELECT LTRIM(RTRIM(l.finca)) AS finca, ISNULL(MIN(f.descripcion), '') AS fincaNombre, LTRIM(RTRIM(l.codigo)) AS lote,
                    ISNULL(MIN(l.descripcion), '') AS loteNombre
               FROM aLotes l
               LEFT JOIN aFinca f ON f.empresa = l.empresa AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(l.finca))
              WHERE l.empresa = ? AND l.activo = 1 AND ISNULL(l.hNetas, 0) > 0.1
                AND l.descripcion NOT LIKE 'DISTRIBUIR%' AND l.descripcion NOT LIKE 'VIVERO%'
                AND l.codigo NOT LIKE 'DISTRIBUIR%' AND l.codigo NOT LIKE 'VIVERO%'" . ($finca === '' ? '' : ' AND LTRIM(RTRIM(l.finca)) = ?') . '
              GROUP BY LTRIM(RTRIM(l.finca)), LTRIM(RTRIM(l.codigo))',
            $finca === '' ? [$empresa] : [$empresa, $finca]
        )->getResultArray() as $l) {
            $lotes[$l['finca'] . '|' . $l['lote']] = $l;
        }

        $terceros = array_column($this->db->query(
            "SELECT LTRIM(RTRIM(nit)) AS tercero, MIN(ISNULL(LTRIM(RTRIM(codigoEquivalencia)), '')) AS codigo,
                    MIN(ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), ISNULL(descripcion, ''))) AS nombre
               FROM cTercero WHERE empresa = ? AND activo = 1 GROUP BY LTRIM(RTRIM(nit))",
            [$empresa]
        )->getResultArray(), null, 'tercero');

        $planeadas = [];

        foreach ($this->db->query(
            "SELECT l.finca, l.lote, l.novedad, CONVERT(varchar(10), MAX(p.fecha), 23) AS fecha
               FROM pPlaneacionLinea l JOIN pPlaneacion p ON p.id = l.planeacion
              WHERE p.empresa = ? AND l.estado <> 'NO_EJECUTADA' GROUP BY l.finca, l.lote, l.novedad",
            [$empresa]
        )->getResultArray() as $p) {
            $planeadas[$p['finca'] . '|' . $p['lote'] . '|' . $p['novedad']] = $dia($p['fecha']);

            if (($labores[$p['novedad']]['grupo'] ?? '') === FertilizacionTransaccionModel::GRUPO) {
                $llave             = $p['finca'] . '|' . $p['lote'] . '|#';
                $planeadas[$llave] = max($planeadas[$llave] ?? 0, $dia($p['fecha']));
            }
        }

        $decididas = [];

        if ($this->sugerenciasDisponible()) {
            foreach ($this->db->query(
                "SELECT s.finca, s.lote, s.novedad, s.base FROM pPlaneacionSugerencia s
                  WHERE s.empresa = ? AND (s.estado = 'RECHAZADA' OR EXISTS (SELECT 1 FROM pPlaneacionLinea l WHERE l.id = s.linea AND l.estado <> 'NO_EJECUTADA'))",
                [$empresa]
            )->getResultArray() as $d) {
                $decididas[$d['finca'] . '|' . $d['lote'] . '|' . $d['novedad'] . '|' . $d['base']] = true;
            }
        }

        $sugerencias = [];
        $agregar     = function (array $lote, string $novedad, string $base, string $origen, ?int $ciclo, int $proxima, string $ultima, string $referencia, array $trabajadores)
            use (&$sugerencias, $labores, $grupos, $decididas, $terceros, $ref, $fechaD): void {
            $llave = $lote['finca'] . '|' . $lote['lote'] . '|' . $novedad . '|' . $base;

            if (isset($decididas[$llave])) {
                return;
            }

            $labor = $labores[$novedad] ?? ['descripcion' => '', 'grupo' => FertilizacionTransaccionModel::GRUPO, 'uMedida' => ''];
            $lista = [];

            foreach (array_keys($trabajadores) as $t) {
                if (isset($terceros[$t])) {
                    $lista[] = $terceros[$t];
                }
            }

            usort($lista, static fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

            $sugerencias[] = [
                'clave'        => base64_encode($llave),
                'finca'        => $lote['finca'],
                'fincaNombre'  => $lote['fincaNombre'],
                'lote'         => $lote['lote'],
                'loteNombre'   => $lote['loteNombre'],
                'novedad'      => $novedad,
                'labor'        => $labor['descripcion'],
                'grupo'        => $labor['grupo'],
                'grupoNombre'  => $grupos[$labor['grupo']] ?? '',
                'uMedida'      => $labor['uMedida'],
                'fechaUltima'  => $ultima,
                'ciclo'        => $ciclo,
                'origen'       => $origen,
                'base'         => $base,
                'fechaProxima' => $fechaD($proxima),
                'dias'         => $proxima - $ref,
                'referencia'   => $referencia,
                'trabajadores' => $lista,
            ];
        };

        $historial = fn (string $tipo): array => $this->historialLabores($empresa, $tipo, $desde, $fecha);
        $pases     = static function (array $fechas) use ($dia): array {
            ksort($fechas);
            $pases = [];

            foreach ($fechas as $f => $terceros) {
                $d    = $dia($f);
                $last = count($pases) - 1;

                if ($last >= 0 && $d - $pases[$last]['fin'] <= self::BRECHA) {
                    $pases[$last]['fin']          = $d;
                    $pases[$last]['trabajadores'] += $terceros;
                } else {
                    $pases[] = ['inicio' => $d, 'fin' => $d, 'trabajadores' => $terceros];
                }
            }

            return $pases;
        };
        $mediana   = static function (array $valores): int {
            sort($valores);
            $n = count($valores);

            return (int) round($n % 2 === 1 ? $valores[intdiv($n, 2)] : ($valores[$n / 2 - 1] + $valores[$n / 2]) / 2);
        };

        $porLlave  = [];
        $porLabor  = [];

        foreach ($historial(self::TIPO) as $llave => $fechas) {
            [$f, $l, $n] = explode('|', $llave);

            if (! isset($labores[$n]) || $labores[$n]['grupo'] === FertilizacionTransaccionModel::GRUPO) {
                continue;
            }

            $p         = $pases($fechas);
            $intervalo = [];

            for ($i = 1; $i < count($p); $i++) {
                $intervalo[] = $p[$i]['fin'] - $p[$i - 1]['fin'];
            }

            $porLabor[$n]     = array_merge($porLabor[$n] ?? [], $intervalo);
            $porLlave[$llave] = ['finca' => $f, 'lote' => $l, 'novedad' => $n, 'pase' => end($p), 'intervalos' => $intervalo];
        }

        foreach ($porLlave as $llave => $h) {
            $lote = $lotes[$h['finca'] . '|' . $h['lote']] ?? null;

            if ($lote === null) {
                continue;
            }

            $ciclo  = (int) $labores[$h['novedad']]['ciclos'];
            $origen = 'CATALOGO';

            if ($ciclo <= 0) {
                $origen = 'HISTORIAL';
                $ciclo  = count($h['intervalos']) >= self::MIN_LOTE ? $mediana($h['intervalos'])
                    : (count($porLabor[$h['novedad']]) >= self::MIN_LABOR ? $mediana($porLabor[$h['novedad']]) : 0);

                if ($ciclo < self::MIN_CICLO) {
                    continue;
                }
            }

            $ultimo  = $h['pase']['fin'];
            $proxima = $ultimo + $ciclo;

            if ($proxima > $limite || ($ref - $ultimo > 2 * $ciclo && $ref - $ultimo > self::ABANDONO) || ($planeadas[$llave] ?? 0) > $ultimo) {
                continue;
            }

            $agregar($lote, $h['novedad'], $fechaD($ultimo), $origen, $ciclo, $proxima, $fechaD($ultimo), '', $h['pase']['trabajadores']);
        }

        $fertilizacion = [];

        foreach ($historial(FertilizacionTransaccionModel::TIPO) as $llave => $fechas) {
            [$f, $l, $n] = explode('|', $llave);

            if (isset($labores[$n]) && $labores[$n]['grupo'] === FertilizacionTransaccionModel::GRUPO) {
                $pase = $pases($fechas);
                $pase = end($pase);

                if (! isset($fertilizacion[$f . '|' . $l]) || $pase['fin'] > $fertilizacion[$f . '|' . $l]['pase']['fin']) {
                    $fertilizacion[$f . '|' . $l] = ['novedad' => $n, 'pase' => $pase];
                }
            }
        }

        $planes       = new PlanFertilizacionModel();
        $elegidos     = [];
        $fertilizados = [];

        foreach ($this->db->query(
            "SELECT LTRIM(RTRIM(t.numero)) AS numero, LTRIM(RTRIM(t.finca)) AS finca, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                    CONVERT(varchar(10), t.fechaFinal, 23) AS fechaFinal
               FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND ISNULL(t.anulado, 0) = 0 AND CAST(t.fecha AS date) <= CAST(? AS date) AND CAST(t.fechaFinal AS date) >= CAST(? AS date)"
                . ($finca === '' ? '' : ' AND LTRIM(RTRIM(t.finca)) = ?') . $this->sinEliminadas('t') . ' ORDER BY t.fecha, t.numero',
            $finca === '' ? [$empresa, FertilizacionTransaccionModel::PLAN, $fechaD($limite), $fecha]
                : [$empresa, FertilizacionTransaccionModel::PLAN, $fechaD($limite), $fecha, $finca]
        )->getResultArray() as $plan) {
            $orden  = $dia($plan['fecha']) <= $ref && $dia($plan['fechaFinal']) >= $ref ? [0, -$dia($plan['fecha'])] : [1, $dia($plan['fecha'])];
            $usados = $planes->lotesUsados($empresa, $plan['numero']);

            foreach ($usados as $u) {
                $fertilizados[$plan['finca'] . '|' . $u] = true;
            }

            foreach ($planes->lotesPlan($empresa, $plan['finca'], $plan['numero']) as $l) {
                $llave = $plan['finca'] . '|' . $l['codigo'];

                if (! in_array($l['codigo'], $usados, true) && (! isset($elegidos[$llave]) || $orden < $elegidos[$llave]['orden'])) {
                    $elegidos[$llave] = ['orden' => $orden, 'plan' => $plan];
                }
            }
        }

        foreach ($elegidos as $llave => $e) {
            $plan = $e['plan'];
            $lote = $lotes[$llave] ?? null;
            $fert = $fertilizacion[$llave] ?? null;

            if ($lote === null || isset($fertilizados[$llave]) || ($planeadas[$llave . '|#'] ?? 0) >= $dia($plan['fecha'])) {
                continue;
            }

            $agregar($lote, $fert['novedad'] ?? '', 'PFA:' . $plan['numero'], 'PLAN', null, max($dia($plan['fecha']), $ref),
                $fert === null ? '' : $fechaD($fert['pase']['fin']), $plan['numero'], $fert['pase']['trabajadores'] ?? []);
        }

        usort($sugerencias, static fn ($a, $b) => [$a['fechaProxima'], $a['finca'], $a['lote'], $a['novedad']] <=> [$b['fechaProxima'], $b['finca'], $b['lote'], $b['novedad']]);

        return $sugerencias;
    }

    private function historialLabores(int $empresa, string $tipo, string $desde, string $hasta): array
    {
        $filas = [];

        foreach ($this->db->query(
            "SELECT LTRIM(RTRIM(b.finca)) AS finca, LTRIM(RTRIM(b.lote)) AS lote, RTRIM(c.novedad) AS novedad,
                    CONVERT(varchar(10), e.fecha, 23) AS fecha, LTRIM(RTRIM(c.tercero)) AS tercero
               FROM aTransaccionTercero c
               JOIN aTransaccionNovedad b ON b.empresa = c.empresa AND b.tipo = c.tipo AND b.numero = c.numero AND b.registro = c.registroNovedad
               JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero AND ISNULL(a.anulado, 0) = 0
              CROSS APPLY (SELECT CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha) e
              WHERE c.empresa = ? AND c.tipo = ? AND e.fecha BETWEEN CAST(? AS date) AND CAST(? AS date) AND LTRIM(RTRIM(ISNULL(b.lote, ''))) <> ''" . $this->sinEliminadas('a') . "
              GROUP BY LTRIM(RTRIM(b.finca)), LTRIM(RTRIM(b.lote)), RTRIM(c.novedad), e.fecha, LTRIM(RTRIM(c.tercero))
             HAVING SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0)) > 0",
            [$empresa, $tipo, $desde, $hasta]
        )->getResultArray() as $r) {
            $filas[$r['finca'] . '|' . $r['lote'] . '|' . $r['novedad']][$r['fecha']][$r['tercero']] = true;
        }

        return $filas;
    }

    public function historial(int $empresa, string $desde, string $hasta): array
    {
        return $this->db->query(
            "SELECT s.id, s.finca, ISNULL(f.descripcion, '') AS fincaNombre, s.lote, ISNULL(lo.descripcion, '') AS loteNombre, s.novedad,
                    ISNULL(n.descripcion, '') AS labor, s.base, s.origen, s.ciclo, ISNULL(CONVERT(varchar(10), s.fechaProxima, 23), '') AS fechaProxima,
                    s.estado, ISNULL(s.motivo, '') AS motivo, s.usuario, CONVERT(varchar(16), s.fecha, 120) AS fecha, s.linea,
                    ISNULL(CONVERT(varchar(10), p.fecha, 23), '') AS fechaPlaneacion, p.id AS planeacion, ISNULL(l.estado, '') AS estadoLinea
               FROM pPlaneacionSugerencia s
               LEFT JOIN pPlaneacionLinea l ON l.id = s.linea
               LEFT JOIN pPlaneacion p ON p.id = l.planeacion
               OUTER APPLY (SELECT TOP 1 descripcion FROM aFinca WHERE empresa = s.empresa AND LTRIM(RTRIM(codigo)) = s.finca) f
               OUTER APPLY (SELECT TOP 1 descripcion FROM aLotes WHERE empresa = s.empresa AND LTRIM(RTRIM(finca)) = s.finca AND LTRIM(RTRIM(codigo)) = s.lote) lo
               OUTER APPLY (SELECT MIN(descripcion) AS descripcion FROM aNovedad WHERE empresa = s.empresa AND RTRIM(codigo) = s.novedad) n
              WHERE s.empresa = ? AND s.fecha >= CAST(? AS date) AND s.fecha < DATEADD(day, 1, CAST(? AS date))
              ORDER BY s.fecha DESC, s.id DESC",
            [$empresa, $desde, $hasta]
        )->getResultArray();
    }

    public function aprobar(int $empresa, array $sugerencia, string $fecha, array $actividad, ?array $plan, string $usuario, ?int $usu_id): array
    {
        $linea = 0;

        $resultado = $this->transaccion(function () use ($empresa, $sugerencia, $fecha, $actividad, $plan, $usuario, &$linea) {
            $existente = $this->decisionVigente($empresa, $sugerencia);

            if (is_string($existente)) {
                return $existente;
            }

            $id = $this->lineaNueva($empresa, $fecha, $actividad, $plan, $usuario);

            if (is_string($id)) {
                return $id;
            }

            $linea = $this->ultimaLinea;

            $this->decidir($empresa, $sugerencia, $existente, 'APROBADA', null, $linea, $usuario);

            return $id;
        }, $plan === null ? 'INSERT' : 'UPDATE', ['accion' => 'aprobarSugerencia', 'sugerencia' => $sugerencia, 'actividad' => $actividad], $usu_id, $usuario,
            'Sugerencia aprobada: actividad agregada al plan del ' . $fecha . '.');

        return $resultado['success'] ? $resultado + ['linea' => $linea] : $resultado;
    }

    public function rechazar(int $empresa, array $sugerencia, string $motivo, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($empresa, $sugerencia, $motivo, $usuario) {
            $existente = $this->decisionVigente($empresa, $sugerencia);

            if (is_string($existente)) {
                return $existente;
            }

            $this->decidir($empresa, $sugerencia, $existente, 'RECHAZADA', $motivo, null, $usuario);

            return 0;
        }, 'INSERT', ['accion' => 'rechazarSugerencia', 'sugerencia' => $sugerencia, 'motivo' => $motivo], $usu_id, $usuario, 'Sugerencia rechazada.');
    }

    private function decisionVigente(int $empresa, array $s): int|string|null
    {
        $fila = $this->db->query(
            "SELECT s.id, CASE WHEN s.estado = 'RECHAZADA' OR l.estado <> 'NO_EJECUTADA' THEN 1 ELSE 0 END AS vigente
               FROM pPlaneacionSugerencia s WITH (UPDLOCK, HOLDLOCK)
               LEFT JOIN pPlaneacionLinea l ON l.id = s.linea
              WHERE s.empresa = ? AND s.finca = ? AND s.lote = ? AND s.novedad = ? AND s.base = ?",
            [$empresa, $s['finca'], $s['lote'], $s['novedad'], $s['base']]
        )->getRowArray();

        if ($fila === null) {
            return null;
        }

        return (int) $fila['vigente'] === 1 ? 'La sugerencia ya fue aprobada o rechazada por otro usuario; recargue las sugerencias.' : (int) $fila['id'];
    }

    private function decidir(int $empresa, array $s, ?int $existente, string $estado, ?string $motivo, ?int $linea, string $usuario): void
    {
        $fila = [
            'origen'       => $s['origen'],
            'ciclo'        => $s['ciclo'],
            'fechaProxima' => $s['fechaProxima'],
            'estado'       => $estado,
            'motivo'       => $motivo,
            'linea'        => $linea,
            'usuario'      => $usuario,
            'fecha'        => date('Y-m-d\TH:i:s'),
        ];

        if ($existente !== null) {
            $this->db->table('pPlaneacionSugerencia')->where('id', $existente)->update($fila);

            return;
        }

        $this->db->table('pPlaneacionSugerencia')->insert($fila + [
            'empresa' => $empresa,
            'finca'   => $s['finca'],
            'lote'    => $s['lote'],
            'novedad' => $s['novedad'],
            'base'    => $s['base'],
        ]);
    }
}
