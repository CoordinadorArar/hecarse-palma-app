<?php

namespace App\Models;

use CodeIgniter\Model;

class TransaccionesResumenModel extends Model
{
    protected $table      = 'aTransaccion';
    protected $returnType = 'array';

    private const MODULOS = [
        'produccion'    => ['Producción', [ProduccionModel::TIPO]],
        'labores'       => ['Labores', [LaboresTransaccionModel::TIPO]],
        'fertilizacion' => ['Fertilización', [FertilizacionTransaccionModel::TIPO, FertilizacionTransaccionModel::PLAN]],
    ];

    private const FINCA = "ISNULL(NULLIF(LTRIM(RTRIM(t.finca)), ''), (SELECT TOP 1 LTRIM(RTRIM(n.finca)) FROM aTransaccionNovedad n
        WHERE n.empresa = t.empresa AND n.tipo = t.tipo AND n.numero = t.numero ORDER BY n.registro))";

    private ?string $eliminadas = null;

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function resumen(int $empresa): array
    {
        $hoy   = date('Y-m-d');
        $desde = date('Y-m-01');
        $prev  = strtotime('first day of last month');
        $dias  = min((int) date('j'), (int) date('t', $prev));
        $rango = [$empresa, $desde, date('Y-m-d', strtotime($hoy . ' +1 day'))];
        $rangoAnterior = [$empresa, date('Y-m-01', $prev), date('Y-m-d', strtotime(date('Y-m-01', $prev) . ' +' . $dias . ' day'))];

        $resumen = ['anio' => (int) date('Y'), 'mes' => (int) date('n'), 'desde' => $desde, 'hasta' => $hoy,
            'kpis' => ['total' => null, 'anuladas' => null, 'totalMesAnterior' => null,
                'produccion' => ['transacciones' => null, 'racimos' => null, 'kg' => null],
                'labores' => ['transacciones' => null, 'jornales' => null, 'valor' => null],
                'fertilizacion' => ['registros' => null, 'planes' => null],
                'planeacion' => ['actividades' => null, 'completadas' => null, 'pendientes' => null]],
            'porDia' => [], 'porModulo' => [], 'porFinca' => [], 'ultimas' => [], 'fallos' => [], 'avisos' => []];

        if ($empresa <= 0) {
            $resumen['avisos'][] = 'No hay una empresa activa seleccionada.';

            return $resumen;
        }

        $etiquetas = ['transacciones' => 'el conteo de transacciones del mes', 'mesAnterior' => 'el comparativo con el mes anterior', 'produccion' => 'los indicadores de producción', 'labores' => 'los indicadores de labores', 'planeacion' => 'los indicadores de planeación', 'porFinca' => 'las transacciones por finca', 'ultimas' => 'las últimas transacciones'];

        $consultas = [
            'transacciones' => function () use ($rango, $desde, &$resumen) {
                $filas = $this->db->query("SELECT CONVERT(varchar(10), t.fecha, 23) AS fecha, LTRIM(RTRIM(t.tipo)) AS tipo,
                    CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado, COUNT(*) AS n
                    FROM aTransaccion t WHERE " . $this->base() . "
                    GROUP BY CONVERT(varchar(10), t.fecha, 23), LTRIM(RTRIM(t.tipo)), CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END", $rango)->getResultArray();

                $porDia = [];

                for ($i = 0; $i < (int) date('j'); $i++) {
                    $fecha          = date('Y-m-d', strtotime($desde . ' +' . $i . ' day'));
                    $porDia[$fecha] = ['fecha' => $fecha, 'produccion' => 0, 'labores' => 0, 'fertilizacion' => 0];
                }

                $modulos = [];

                foreach (self::MODULOS + ['otros' => ['Otras', []]] as $clave => [$nombre]) {
                    $modulos[$clave] = ['clave' => $clave, 'nombre' => $nombre, 'transacciones' => 0, 'anuladas' => 0];
                }

                $tipos = [];

                foreach ($filas as $f) {
                    $clave = $this->modulo($f['tipo']);
                    $n     = (int) $f['n'];

                    if ((int) $f['anulado'] === 1) {
                        $modulos[$clave]['anuladas'] += $n;

                        continue;
                    }

                    $modulos[$clave]['transacciones'] += $n;
                    $tipos[$f['tipo']] = ($tipos[$f['tipo']] ?? 0) + $n;

                    if (isset($porDia[$f['fecha']][$clave])) {
                        $porDia[$f['fecha']][$clave] += $n;
                    }
                }

                if ($modulos['otros']['transacciones'] + $modulos['otros']['anuladas'] === 0) {
                    unset($modulos['otros']);
                }

                $resumen['porDia']    = array_values($porDia);
                $resumen['porModulo'] = array_values($modulos);
                $resumen['kpis']['total'] = array_sum(array_column($modulos, 'transacciones'));
                $resumen['kpis']['anuladas'] = array_sum(array_column($modulos, 'anuladas'));
                $resumen['kpis']['produccion']['transacciones'] = $modulos['produccion']['transacciones'];
                $resumen['kpis']['labores']['transacciones'] = $modulos['labores']['transacciones'];
                $resumen['kpis']['fertilizacion'] = ['registros' => $tipos[FertilizacionTransaccionModel::TIPO] ?? 0, 'planes' => $tipos[FertilizacionTransaccionModel::PLAN] ?? 0];
            },
            'mesAnterior' => function () use ($rangoAnterior, &$resumen) {
                $resumen['kpis']['totalMesAnterior'] = (int) $this->db->query('SELECT COUNT(*) AS n FROM aTransaccion t WHERE ' . $this->vigentes(), $rangoAnterior)->getRow()->n;
            },
            'produccion' => function () use ($rango, &$resumen) {
                $row = $this->db->query("SELECT
                    (SELECT ISNULL(SUM(b.pesoNeto), 0) FROM aTransaccionBascula b
                       JOIN aTransaccion t ON t.empresa = b.empresa AND t.tipo = b.tipo AND t.numero = b.numero
                      WHERE " . $this->vigentes() . " AND t.tipo = ?) AS kg,
                    (SELECT ISNULL(SUM(CASE WHEN n.signo = 2 THEN -1 ELSE 1 END * ISNULL(n.racimos, 0)), 0) FROM aTransaccionNovedad n
                       JOIN aTransaccion t ON t.empresa = n.empresa AND t.tipo = n.tipo AND t.numero = n.numero
                      WHERE " . $this->vigentes() . " AND t.tipo = ?
                        AND EXISTS (SELECT 1 FROM aTransaccionTercero c
                                      JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                                     WHERE c.empresa = n.empresa AND c.tipo = n.tipo AND c.numero = n.numero AND c.registroNovedad = n.registro)) AS racimos",
                    array_merge($rango, [ProduccionModel::TIPO], $rango, [ProduccionModel::TIPO]))->getRowArray();

                $resumen['kpis']['produccion']['racimos'] = (int) $row['racimos'];
                $resumen['kpis']['produccion']['kg'] = round((float) $row['kg'], 2);
            },
            'labores' => function () use ($rango, &$resumen) {
                $row = $this->db->query('SELECT ISNULL(SUM(r.jornales), 0) AS jornales, ISNULL(SUM(r.valorTotal), 0) AS valor
                    FROM aTransaccionTercero r
                    JOIN aTransaccion t ON t.empresa = r.empresa AND t.tipo = r.tipo AND t.numero = r.numero
                    WHERE ' . $this->vigentes() . ' AND t.tipo = ?', array_merge($rango, [LaboresTransaccionModel::TIPO]))->getRowArray();

                $resumen['kpis']['labores']['jornales'] = round((float) $row['jornales'], 2);
                $resumen['kpis']['labores']['valor'] = round((float) $row['valor'], 2);
            },
            'planeacion' => function () use ($rango, &$resumen) {
                $row = $this->db->query("SELECT COUNT(*) AS actividades,
                    ISNULL(SUM(CASE WHEN l.estado = 'COMPLETADA' THEN 1 ELSE 0 END), 0) AS completadas,
                    ISNULL(SUM(CASE WHEN l.estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pendientes
                    FROM pPlaneacionLinea l JOIN pPlaneacion p ON p.id = l.planeacion
                    WHERE p.empresa = ? AND p.fecha >= ? AND p.fecha < ?", $rango)->getRowArray();

                $resumen['kpis']['planeacion'] = array_map('intval', $row);
            },
            'porFinca' => function () use ($empresa, $rango, &$resumen) {
                $filas = $this->db->query("SELECT TOP 8 ISNULL(x.finca, '') AS finca, ISNULL(LTRIM(RTRIM(MAX(f.descripcion))), '') AS nombre,
                    COUNT(*) AS transacciones, ISNULL(SUM(x.kg), 0) AS kg
                    FROM (SELECT " . self::FINCA . " AS finca,
                                 CASE WHEN t.tipo = ? THEN (SELECT SUM(b.pesoNeto) FROM aTransaccionBascula b
                                       WHERE b.empresa = t.empresa AND b.tipo = t.tipo AND b.numero = t.numero) END AS kg
                            FROM aTransaccion t WHERE " . $this->vigentes() . ") x
                    LEFT JOIN aFinca f ON f.empresa = ? AND LTRIM(RTRIM(f.codigo)) = x.finca
                    GROUP BY ISNULL(x.finca, '')
                    ORDER BY COUNT(*) DESC, ISNULL(x.finca, '')", array_merge([ProduccionModel::TIPO], $rango, [$empresa]))->getResultArray();

                $resumen['porFinca'] = array_map(static fn ($f) => ['finca' => $f['finca'], 'nombre' => $f['nombre'] !== '' ? $f['nombre'] : ($f['finca'] !== '' ? $f['finca'] : 'Sin finca'),
                    'transacciones' => (int) $f['transacciones'], 'kg' => round((float) $f['kg'], 2)], $filas);
            },
            'ultimas' => function () use ($rango, &$resumen) {
                $filas = $this->db->query("SELECT TOP 8 LTRIM(RTRIM(t.tipo)) AS tipo, LTRIM(RTRIM(t.numero)) AS numero, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                    ISNULL(x.finca, '') AS finca, ISNULL(LTRIM(RTRIM(f.descripcion)), '') AS fincaNombre,
                    LTRIM(RTRIM(ISNULL(t.usuarioRegistro, ''))) AS usuario, CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado
                    FROM aTransaccion t
                    CROSS APPLY (SELECT " . self::FINCA . " AS finca) x
                    LEFT JOIN aFinca f ON f.empresa = t.empresa AND LTRIM(RTRIM(f.codigo)) = x.finca
                    WHERE " . $this->base() . "
                    ORDER BY t.fechaRegistro DESC, t.numero DESC", $rango)->getResultArray();

                $resumen['ultimas'] = array_map(fn ($f) => ['modulo' => $this->modulo($f['tipo']), 'tipo' => $f['tipo'], 'numero' => $f['numero'], 'fecha' => $f['fecha'],
                    'finca' => $f['fincaNombre'] !== '' ? $f['fincaNombre'] : $f['finca'], 'usuario' => $f['usuario'], 'anulado' => (int) $f['anulado'] === 1], $filas);
            },
        ];

        foreach ($consultas as $clave => $consulta) {
            try {
                $consulta();
            } catch (\Throwable $e) {
                log_message('error', 'TransaccionesResumenModel::resumen [' . $clave . '] ' . $e->getMessage());
                $resumen['fallos'][] = $clave;
                $resumen['avisos'][] = 'No fue posible cargar ' . $etiquetas[$clave] . '.';
            }
        }

        return $resumen;
    }

    private function base(): string
    {
        $this->eliminadas ??= $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = t.empresa AND el.tipo = t.tipo AND el.numero = LTRIM(RTRIM(t.numero)))';

        return 't.empresa = ? AND t.fecha >= ? AND t.fecha < ?' . $this->eliminadas;
    }

    private function vigentes(): string
    {
        return $this->base() . ' AND ISNULL(t.anulado, 0) = 0';
    }

    private function modulo(string $tipo): string
    {
        foreach (self::MODULOS as $clave => [, $tipos]) {
            if (in_array($tipo, $tipos, true)) {
                return $clave;
            }
        }

        return 'otros';
    }
}
