<?php

namespace App\Models;

use CodeIgniter\Model;

class InformeModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    private const INICIO = 'COALESCE(FechaInicio, DATEFROMPARTS(TRY_CAST([Año] AS int), TRY_CAST(Mes AS int), 1))';
    private const FIN    = 'COALESCE(FechaFin, EOMONTH(DATEFROMPARTS(TRY_CAST([Año] AS int), TRY_CAST(Mes AS int), 1)))';

    private const RAZON = "CASE WHEN v.TipoContrato = 'Directo' AND (v.RazonTemporal IS NULL OR LTRIM(RTRIM(v.RazonTemporal)) = '') THEN 'Nomina Hecarse' ELSE v.RazonTemporal END";

    private const EMPRESA = 1;

    private static ?bool $columnasRango = null;

    private static ?bool $columnasPreliminar = null;

    private array $gruposDominicales = [];

    private ?float $ultimoDominical = null;

    public function tienePreliminar(): bool
    {
        return self::$columnasPreliminar ??= (int) $this->db->query(
            "SELECT CASE WHEN COL_LENGTH('dbo.Liquidacion', 'FechaCorte') IS NULL OR COL_LENGTH('dbo.Liquidacion', 'ValorReal') IS NULL
                          OR COL_LENGTH('dbo.Liquidacion', 'ValorEstimado') IS NULL OR COL_LENGTH('dbo.Liquidacion', 'ValorAjuste') IS NULL
                          OR COL_LENGTH('dbo.Liquidacion', 'AjustadoEnInicio') IS NULL OR COL_LENGTH('dbo.Liquidacion', 'AjustadoEnFin') IS NULL
                         THEN 0 ELSE 1 END AS existe"
        )->getRow()->existe === 1;
    }

    public function calculoLiquidacion(string $desde, string $hasta, ?string $corte, ?string $temporal = null, ?string $tipoContrato = null): array
    {
        $empleados = [];

        foreach ($this->movimientos($desde, $corte ?? $hasta, $temporal, $tipoContrato) as $m) {
            $cedula = trim((string) $m['cedula']);
            $clave  = $m['RazonTemporal'] . '|' . $m['TipoContrato'] . '|' . $cedula;
            $valor  = (float) $m['valor'];

            $empleados[$clave] ??= $this->empleadoLiquidacion($cedula, (string) $m['nombreTercero'], (string) $m['RazonTemporal'], (string) $m['TipoContrato']);
            $empleados[$clave]['real'] += $valor;

            if ((int) $m['dominical'] === 0) {
                $empleados[$clave]['base'] += $valor;
                $empleados[$clave]['grupos'][(string) $m['nombreGrupoLabor']] = ($empleados[$clave]['grupos'][(string) $m['nombreGrupoLabor']] ?? 0) + $valor;

                if ((int) date('N', strtotime($m['fecha'])) <= 6) {
                    $empleados[$clave]['dias'][$m['fecha']] = true;
                }
            }
        }

        $restantes = $corte === null ? ['habiles' => 0, 'domingos' => 0, 'primerDomingo' => null] : $this->diasRestantes($corte, $hasta);

        foreach ($empleados as &$e) {
            if ($corte !== null && $e['dias'] !== []) {
                $e['diasConLabor']   = count($e['dias']);
                $e['promedioDiario'] = round($e['base'] / $e['diasConLabor'], 2);
                $e['jornalDominical'] = $restantes['domingos'] > 0 ? $this->precioDominical($e['cedula'], $e['grupos'], $restantes['primerDomingo']) : 0.0;
                $e['estimado']       = round($e['promedioDiario'] * $restantes['habiles'] + $restantes['domingos'] * $e['jornalDominical']);
            }
        }

        unset($e);

        $ajustes = [];

        foreach ($this->tienePreliminar() ? $this->preliminares($desde, $hasta, $temporal, $tipoContrato) : [] as $grupo) {
            $reales = [];

            foreach ($this->movimientos(date('Y-m-d', strtotime($grupo['corte'] . ' +1 day')), $grupo['hasta'], $grupo['RazonTemporal'], $grupo['TipoContrato']) as $m) {
                $reales[trim((string) $m['cedula'])] = ($reales[trim((string) $m['cedula'])] ?? 0) + (float) $m['valor'];
            }

            $valorGrupo = 0.0;

            foreach ($grupo['filas'] as $p) {
                $clave = $grupo['RazonTemporal'] . '|' . $grupo['TipoContrato'] . '|' . $p['cedula'];
                $valor = round(($reales[$p['cedula']] ?? 0) - (float) $p['estimado'], 2);

                $empleados[$clave] ??= $this->empleadoLiquidacion($p['cedula'], (string) $p['NombreEmpleado'], $grupo['RazonTemporal'], $grupo['TipoContrato']);
                $empleados[$clave]['ajuste']    += $valor;
                $empleados[$clave]['ajusteDe'][] = ['desde' => $grupo['desde'], 'hasta' => $grupo['hasta'], 'corte' => $grupo['corte'], 'estimado' => (float) $p['estimado'], 'real' => round($reales[$p['cedula']] ?? 0, 2), 'ajuste' => $valor];
                $valorGrupo                    += $valor;
            }

            unset($grupo['filas']);

            $ajustes[] = $grupo + ['empleados' => count($grupo['ids']), 'valor' => round($valorGrupo, 2)];
        }

        foreach ($empleados as &$e) {
            $e['real']   = round($e['real'], 2);
            $e['ajuste'] = round($e['ajuste'], 2);
            $e['total']  = round($e['real'] + $e['estimado'] + $e['ajuste']);
            unset($e['base'], $e['grupos'], $e['dias']);
        }

        unset($e);

        usort($empleados, static fn ($a, $b) => [$a['RazonTemporal'], $a['nombreTercero']] <=> [$b['RazonTemporal'], $b['nombreTercero']]);

        return [
            'preliminar'           => $corte !== null,
            'corte'                => $corte,
            'estimadoDesde'        => $corte === null ? null : date('Y-m-d', strtotime($corte . ' +1 day')),
            'estimadoHasta'        => $corte === null ? null : $hasta,
            'diasHabilesRestantes' => $restantes['habiles'],
            'domingosRestantes'    => $restantes['domingos'],
            'ajustes'              => $ajustes,
            'empleados'            => $empleados,
        ];
    }

    public function ajustadoPor(string $desde, string $hasta, string $temporal, string $tipoContrato): ?array
    {
        if (! $this->tienePreliminar()) {
            return null;
        }

        return $this->db->query(
            "SELECT TOP 1 CONVERT(varchar(10), AjustadoEnInicio, 23) AS desde, CONVERT(varchar(10), AjustadoEnFin, 23) AS hasta
               FROM Liquidacion
              WHERE RazonTemporal = ? AND TipoContrato = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ? AND AjustadoEnInicio IS NOT NULL",
            [$temporal, $tipoContrato, $this->ymd($desde), $this->ymd($hasta)]
        )->getRowArray();
    }

    public function guardarLiquidacion(array $cedulas, string $desde, string $hasta, string $temporal, string $tipoContrato, ?string $corte): array
    {
        $calculo = $this->calculoLiquidacion($desde, $hasta, $corte, $temporal, $tipoContrato);
        $filas   = array_values(array_filter($calculo['empleados'], static fn ($e) => isset($cedulas[$e['cedula']]) || $e['ajusteDe'] !== []));

        if ($filas === []) {
            return ['registros' => 0, 'ajustes' => 0];
        }

        $rango = [$temporal, $tipoContrato, $this->ymd($desde), $this->ymd($hasta)];

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE Liquidacion SET AjustadoEnInicio = NULL, AjustadoEnFin = NULL
                  WHERE RazonTemporal = ? AND TipoContrato = ? AND AjustadoEnInicio = ? AND AjustadoEnFin = ?',
                $rango
            );

            $this->db->query(
                "DELETE FROM Liquidacion WHERE RazonTemporal = ? AND TipoContrato = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ?",
                $rango
            );

            foreach ($filas as $f) {
                $this->db->query(
                    'INSERT INTO Liquidacion ([Año], Mes, Cedula, NombreEmpleado, TipoContrato, RazonTemporal, ValorEnviado, FechaProcesado,
                                              FechaInicio, FechaFin, FechaCorte, ValorReal, ValorEstimado, ValorAjuste)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        substr($desde, 0, 4), substr($desde, 5, 2), $f['cedula'], $f['nombreTercero'], $tipoContrato, $temporal,
                        $f['total'], date('Ymd H:i:s'), $this->ymd($desde), $this->ymd($hasta),
                        $corte === null ? null : $this->ymd($corte), $f['real'], $f['estimado'], $f['ajuste'],
                    ]
                );
            }

            foreach ($calculo['ajustes'] as $a) {
                $this->db->query(
                    'UPDATE Liquidacion SET AjustadoEnInicio = ?, AjustadoEnFin = ?
                      WHERE RazonTemporal = ? AND TipoContrato = ? AND FechaCorte IS NOT NULL AND FechaInicio = ? AND FechaFin = ?',
                    [$this->ymd($desde), $this->ymd($hasta), $a['RazonTemporal'], $a['TipoContrato'], $this->ymd($a['desde']), $this->ymd($a['hasta'])]
                );
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();

                throw new \RuntimeException('La transacción de la liquidación fue revertida.');
            }

            $this->db->transCommit();

            return ['registros' => count($filas), 'ajustes' => count($calculo['ajustes'])];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error guardando la liquidación: ' . $e->getMessage());

            throw $e;
        }
    }

    private function empleadoLiquidacion(string $cedula, string $nombre, string $temporal, string $tipoContrato): array
    {
        return [
            'cedula'          => $cedula,
            'nombreTercero'   => $nombre,
            'RazonTemporal'   => $temporal,
            'TipoContrato'    => $tipoContrato,
            'real'            => 0.0,
            'estimado'        => 0.0,
            'ajuste'          => 0.0,
            'total'           => 0.0,
            'diasConLabor'    => 0,
            'promedioDiario'  => 0.0,
            'jornalDominical' => 0.0,
            'ajusteDe'        => [],
            'base'            => 0.0,
            'grupos'          => [],
            'dias'            => [],
        ];
    }

    private function movimientos(string $desde, string $hasta, ?string $temporal, ?string $tipoContrato): array
    {
        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');

        $sql = "SELECT x.cedula, MAX(x.nombreTercero) AS nombreTercero, x.RazonTemporal, x.TipoContrato, ISNULL(x.nombreGrupoLabor, '') AS nombreGrupoLabor,
                       x.fecha, x.dominical, SUM(x.valorTotalTercero) AS valor
                  FROM (SELECT v.cedula, v.nombreTercero, " . self::RAZON . " AS RazonTemporal, v.TipoContrato, v.nombreGrupoLabor, v.fecha, v.valorTotalTercero,
                               CASE WHEN EXISTS (SELECT 1 FROM aTransaccionTercero r
                                                   JOIN aNovedad n ON n.empresa = r.empresa AND RTRIM(n.codigo) = RTRIM(r.novedad)
                                                                  AND n.activo = 1 AND LTRIM(n.descripcion) LIKE 'DOMINICAL%'
                                                  WHERE r.empresa IN (SELECT id FROM gEmpresa) AND r.tipo = v.codtransaccion
                                                    AND r.numero = v.numeroTransaccion AND r.tercero = v.cedula AND r.fechaNovedad = v.fecha
                                                    AND ABS(ISNULL(r.valorTotal, 0)) = ABS(v.valorTotalTercero)) THEN 1 ELSE 0 END AS dominical
                          FROM dbo.vInformacionAgronomicoSiesaTotal v
                         WHERE v.fecha >= ? AND v.fecha <= ?) x";

        $binds = [$this->ymd($desde), $this->ymd($hasta)];

        if ($temporal !== null && $tipoContrato !== null) {
            $sql    .= ' WHERE x.RazonTemporal = ? AND x.TipoContrato = ?';
            $binds[] = $temporal;
            $binds[] = $tipoContrato;
        }

        return $this->db->query(
            $sql . ' GROUP BY x.cedula, x.RazonTemporal, x.TipoContrato, ISNULL(x.nombreGrupoLabor, \'\'), x.fecha, x.dominical',
            $binds
        )->getResultArray();
    }

    private function diasRestantes(string $corte, string $hasta): array
    {
        $festivos = array_column($this->db->query(
            'SELECT CONVERT(varchar(10), fecha, 23) AS fecha FROM nFestivo WHERE empresa = ? AND fecha > ? AND fecha <= ?',
            [self::EMPRESA, $this->ymd($corte), $this->ymd($hasta)]
        )->getResultArray(), 'fecha', 'fecha');

        $restantes = ['habiles' => 0, 'domingos' => 0, 'primerDomingo' => null];

        for ($dia = strtotime($corte . ' +1 day'); $dia <= strtotime($hasta); $dia = strtotime('+1 day', $dia)) {
            if (date('N', $dia) === '7') {
                $restantes['domingos']++;
                $restantes['primerDomingo'] ??= date('Y-m-d', $dia);
            } elseif (! isset($festivos[date('Y-m-d', $dia)])) {
                $restantes['habiles']++;
            }
        }

        return $restantes;
    }

    private function precioDominical(string $cedula, array $grupos, string $domingo): float
    {
        if ($this->gruposDominicales === []) {
            foreach ($this->db->query(
                "SELECT g.descripcion AS grupo, MIN(RTRIM(n.codigo)) AS novedad
                   FROM aGrupoNovedad g
                   JOIN aNovedad n ON n.empresa = g.empresa AND n.grupo = g.codigo AND n.activo = 1 AND LTRIM(n.descripcion) LIKE 'DOMINICAL%'
                  WHERE g.empresa = ?
                  GROUP BY g.descripcion",
                [self::EMPRESA]
            )->getResultArray() as $fila) {
                $this->gruposDominicales[(string) $fila['grupo']] = $fila['novedad'];
            }
        }

        arsort($grupos);

        foreach (array_keys($grupos) as $grupo) {
            if (isset($this->gruposDominicales[(string) $grupo]) && ctype_digit($cedula)) {
                try {
                    $precio = (float) $this->db->query(
                        'SELECT dbo.fRetornaPrecioLaboresTercero(?, ?, ?, ?, ?, ?, ?, ?, ?) AS precio',
                        [self::EMPRESA, $this->gruposDominicales[(string) $grupo], (int) substr($domingo, 0, 4), (int) $cedula, $this->ymd($domingo), '', '', '', 0]
                    )->getRow()->precio;
                } catch (\Throwable $e) {
                    log_message('error', 'Error resolviendo el precio dominical: ' . $e->getMessage());
                    $precio = 0.0;
                }

                if ($precio > 0) {
                    return round($precio, 2);
                }

                break;
            }
        }

        $dominical = "EXISTS (SELECT 1 FROM aNovedad n WHERE n.empresa = r.empresa AND RTRIM(n.codigo) = RTRIM(r.novedad)
                                 AND n.activo = 1 AND LTRIM(n.descripcion) LIKE 'DOMINICAL%')";

        $fila = $this->db->query(
            "SELECT TOP 1 r.precioLabor FROM aTransaccionTercero r
              WHERE r.empresa = ? AND LTRIM(RTRIM(r.tercero)) = ? AND ISNULL(r.precioLabor, 0) > 0 AND {$dominical}
              ORDER BY r.fechaNovedad DESC",
            [self::EMPRESA, $cedula]
        )->getRowArray();

        if ($fila !== null) {
            return round((float) $fila['precioLabor'], 2);
        }

        $this->ultimoDominical ??= (float) ($this->db->query(
            "SELECT TOP 1 r.precioLabor FROM aTransaccionTercero r
              WHERE r.empresa = ? AND ISNULL(r.precioLabor, 0) > 0 AND {$dominical}
              ORDER BY r.fechaNovedad DESC",
            [self::EMPRESA]
        )->getRowArray()['precioLabor'] ?? 0);

        return round($this->ultimoDominical, 2);
    }

    private function preliminares(string $desde, string $hasta, ?string $temporal, ?string $tipoContrato): array
    {
        $sql = "SELECT Id, LTRIM(RTRIM(CAST(Cedula AS varchar(50)))) AS cedula, NombreEmpleado, RazonTemporal, TipoContrato,
                       CONVERT(varchar(10), FechaInicio, 23) AS desde, CONVERT(varchar(10), FechaFin, 23) AS hasta,
                       CONVERT(varchar(10), FechaCorte, 23) AS corte, ISNULL(ValorEstimado, 0) AS estimado
                  FROM Liquidacion
                 WHERE FechaCorte IS NOT NULL AND FechaInicio IS NOT NULL AND FechaFin < ?
                   AND (AjustadoEnInicio IS NULL OR (AjustadoEnInicio = ? AND AjustadoEnFin = ?))";

        $binds = [$this->ymd($desde), $this->ymd($desde), $this->ymd($hasta)];

        if ($temporal !== null && $tipoContrato !== null) {
            $sql    .= ' AND RazonTemporal = ? AND TipoContrato = ?';
            $binds[] = $temporal;
            $binds[] = $tipoContrato;
        }

        $grupos = [];

        foreach ($this->db->query($sql . ' ORDER BY FechaFin, Id', $binds)->getResultArray() as $fila) {
            $clave = implode('|', [$fila['RazonTemporal'], $fila['TipoContrato'], $fila['desde'], $fila['hasta']]);

            $grupos[$clave] ??= [
                'RazonTemporal' => (string) $fila['RazonTemporal'],
                'TipoContrato'  => (string) $fila['TipoContrato'],
                'desde'         => $fila['desde'],
                'hasta'         => $fila['hasta'],
                'corte'         => $fila['corte'],
                'ids'           => [],
                'filas'         => [],
            ];

            $grupos[$clave]['ids'][]   = (int) $fila['Id'];
            $grupos[$clave]['filas'][] = $fila;
        }

        return array_values($grupos);
    }

    public function tieneRangoFechas(): bool
    {
        return self::$columnasRango ??= (int) $this->db->query(
            "SELECT CASE WHEN COL_LENGTH('dbo.Liquidacion', 'FechaInicio') IS NULL OR COL_LENGTH('dbo.Liquidacion', 'FechaFin') IS NULL THEN 0 ELSE 1 END AS existe"
        )->getRow()->existe === 1;
    }

    private function ymd(string $fecha): string
    {
        return str_replace('-', '', $fecha);
    }

    private function filtrarRango($builder, array $rango)
    {
        return $builder->where('fecha >=', $this->ymd($rango[0]))->where('fecha <=', $this->ymd($rango[1]));
    }

    public function updateContratos()
    {
        $db = $this->db;

        $sqlDrop = "IF OBJECT_ID('SiesaContratos', 'U') IS NOT NULL
                    DROP TABLE SiesaContratos";

        $sqlCreate = "SELECT * INTO SiesaContratos FROM vSiesaContratos";

        $db->query($sqlDrop);
        $db->query($sqlCreate);

        return true;
    }

    public function getReporteGeneral(array $rango)
    {
        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        $builder = $this->db->table('dbo.vInformacionAgronomicoSiesaTotal');

        $builder->select("
            centroOperacion,
            ccosto,
            fecha,
            valorTotalTercero,
            cedula,
            uNegocio,
            nombreTercero,
            nombreConcepto,
            nombreGrupoLabor,
            CASE 
                WHEN TipoContrato = 'Directo'
                    AND (RazonTemporal IS NULL 
                        OR LTRIM(RTRIM(RazonTemporal)) = '')
                THEN 'Nomina Hecarse'
                ELSE RazonTemporal
            END AS RazonTemporal,
            TipoContrato
        ");

        $this->filtrarRango($builder, $rango);

        // 1. Generamos el SQL sin ejecutarlo aún
        $sql = $builder->getCompiledSelect();

        // 2. Ejecutamos con query() pasando FALSE en el tercer parámetro (buffer off)
        // El segundo parámetro son los binds (vacío porque ya van en el SQL compilado)
        $query = $this->db->query($sql, [], false);

        return $query->getResultArray();
    }

    public function getTemporales($anio, $mes, ?array $rango = null)
    {
        $builder = $this->db
            ->table('dbo.vInformacionAgronomicoSiesaTotal')
            ->select('RazonTemporal, TipoContrato')
            ->distinct();

        if ($rango === null) {
            $builder->where('año', $anio)->where('mes', $mes);
        } else {
            $this->filtrarRango($builder, $rango);
        }

        return $builder->get()->getResultArray();
    }


    public function getEmpleadosSinTemporal(array $rango)
    {
        return $this->filtrarRango($this->db
            ->table('dbo.vInformacionAgronomicoSiesaTotal')
            ->select('cedula')
            ->select('nombreTercero')
            ->distinct(), $rango)
            ->where('TipoContrato', 'Temporal')
            ->where("(RazonTemporal IS NULL OR LTRIM(RTRIM(RazonTemporal)) = '')", null, false)
            ->get()
            ->getResultArray();
    }

    public function verificarReporteProcesado($desde, $hasta, $temporal, $tipoContrato)
    {
        $rangos = $this->db->query(
            "SELECT DISTINCT CONVERT(varchar(10), x.inicio, 23) AS desde, CONVERT(varchar(10), x.fin, 23) AS hasta
               FROM (SELECT " . self::INICIO . " AS inicio, " . self::FIN . " AS fin
                       FROM Liquidacion WHERE RazonTemporal = ? AND TipoContrato = ?) x
              WHERE x.inicio <= ? AND x.fin >= ?
              ORDER BY desde, hasta",
            [$temporal, $tipoContrato, $this->ymd($hasta), $this->ymd($desde)]
        )->getResultArray();

        $cruces    = array_values(array_filter($rangos, fn ($r) => $r['desde'] !== $desde || $r['hasta'] !== $hasta));
        $procesado = count($cruces) < count($rangos);
        $marca     = $procesado && $this->tienePreliminar() ? $this->db->query(
            "SELECT TOP 1 CONVERT(varchar(10), FechaCorte, 23) AS corte, CONVERT(varchar(10), AjustadoEnInicio, 23) AS ajustadoDesde,
                    CONVERT(varchar(10), AjustadoEnFin, 23) AS ajustadoHasta
               FROM Liquidacion
              WHERE RazonTemporal = ? AND TipoContrato = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ?
              ORDER BY FechaCorte DESC, AjustadoEnInicio DESC",
            [$temporal, $tipoContrato, $this->ymd($desde), $this->ymd($hasta)]
        )->getRowArray() : null;

        return [
            'procesado'  => $procesado,
            'cruces'     => $cruces,
            'preliminar' => ($marca['corte'] ?? null) !== null,
            'corte'      => $marca['corte'] ?? null,
            'ajustadoEn' => ($marca['ajustadoDesde'] ?? null) === null ? null : ['desde' => $marca['ajustadoDesde'], 'hasta' => $marca['ajustadoHasta']],
        ];
    }


    public function guardarReporteProcesado($datos, $desde, $hasta, $temporal, $tipoContrato)
    {
        if (empty($datos)) {
            return 0;
        }

        [$anio, $mes] = [substr($desde, 0, 4), substr($desde, 5, 2)];

        $this->db->transBegin();

        try {

            $this->db->query(
                "DELETE FROM Liquidacion WHERE RazonTemporal = ? AND TipoContrato = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ?",
                [$temporal, $tipoContrato, $this->ymd($desde), $this->ymd($hasta)]
            );

            $datosParaInsertar = [];

            foreach ($datos as $registro) {

                if (!is_array($registro)) {
                    continue;
                }

                $datosParaInsertar[] = [
                    'Año' => $anio,
                    'Mes' => $mes,
                    'Cedula' => $registro['cedula'] ?? null,
                    'NombreEmpleado' => $registro['nombreTercero'] ?? null,
                    'TipoContrato' => $tipoContrato,
                    'RazonTemporal' => $temporal,
                    'ValorEnviado' => $registro['valorTotalTercero'] ?? 0,
                    'FechaProcesado' => date('Ymd H:i:s')
                ];
            }

            foreach ($datosParaInsertar as $row) {

                $sql = "INSERT INTO Liquidacion
                    ([Año], Mes, Cedula, NombreEmpleado, TipoContrato, RazonTemporal, ValorEnviado, FechaProcesado, FechaInicio, FechaFin)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


                $query = $this->db->query($sql, [
                    $row['Año'],
                    $row['Mes'],
                    $row['Cedula'],
                    $row['NombreEmpleado'],
                    $row['TipoContrato'],
                    $row['RazonTemporal'],
                    $row['ValorEnviado'],
                    $row['FechaProcesado'],
                    $this->ymd($desde),
                    $this->ymd($hasta)
                ]);

                if ($query === false) {
                    print_r($this->db->error());
                    exit();
                }
            }

            $this->db->transCommit();

            return count($datosParaInsertar);
        } catch (\Exception $e) {

            $this->db->transRollback();
            log_message('error', 'Error guardando reporte: ' . $e->getMessage());
            throw $e;
        }
    }


    public function crucesCedulas(array $cedulas, $desde, $hasta)
    {
        return $this->db->query(
            "SELECT DISTINCT TOP 50 x.cedula, CONVERT(varchar(10), x.inicio, 23) AS desde, CONVERT(varchar(10), x.fin, 23) AS hasta
               FROM (SELECT LTRIM(RTRIM(CAST(Cedula AS varchar(30)))) AS cedula, " . self::INICIO . " AS inicio, " . self::FIN . " AS fin
                       FROM Liquidacion
                      WHERE CHARINDEX(',' + LTRIM(RTRIM(CAST(Cedula AS varchar(30)))) + ',', ?) > 0) x
              WHERE x.inicio <= ? AND x.fin >= ? AND NOT (x.inicio = ? AND x.fin = ?)
              ORDER BY cedula, desde, hasta",
            [',' . implode(',', $cedulas) . ',', $this->ymd($hasta), $this->ymd($desde), $this->ymd($desde), $this->ymd($hasta)]
        )->getResultArray();
    }

    public function existeCedulaPeriodo($cedula, $desde, $hasta)
    {
        return $this->db->query(
            "SELECT COUNT(*) AS total FROM Liquidacion WHERE Cedula = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ?",
            [$cedula, $this->ymd($desde), $this->ymd($hasta)]
        )->getRow()->total > 0;
    }

    public function buscarCedula($cedula)
    {
        $sql = "
            SELECT f.codigo AS Cedula, f.descripcion AS NombreEmpleado, t.razonSocial AS RazonTemporal,
                TipoContrato = CASE WHEN sc.activo = 1 AND sc.empresa != 2 THEN 'Directo' ELSE 'Temporal' END
            FROM dbo.nFuncionario f
            LEFT JOIN dbo.cTercero t ON t.codigo = f.proveedor
            LEFT JOIN dbo.SiesaContratos sc ON f.codigo collate SQL_Latin1_General_CP1_CI_AS = sc.codigoTercero
            WHERE f.codigo = ?
        ";

        $query = $this->db->query($sql, [$cedula]);
        return $query->getRowArray();
    }

    public function insertarNuevos($desde, $hasta, $cedula, $nombre, $tipoContrato, $temporal, $valorTemporal)
    {
        $sql = "
            INSERT INTO Liquidacion
            ([Año], Mes, Cedula, NombreEmpleado, TipoContrato, RazonTemporal, valorTemporal, FechaProcesado, FechaInicio, FechaFin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        return $this->db->query($sql, [
            substr($desde, 0, 4),
            substr($desde, 5, 2),
            $cedula,
            $nombre,
            $tipoContrato,
            $temporal,
            $valorTemporal,
            date('Ymd H:i:s'),
            $this->ymd($desde),
            $this->ymd($hasta)
        ]);
    }

    public function actualizarValorTemporal($cedula, $valor, $desde, $hasta)
    {
        return $this->db->query(
            "UPDATE Liquidacion SET ValorTemporal = ? WHERE Cedula = ? AND " . self::INICIO . " = ? AND " . self::FIN . " = ?",
            [$valor, $cedula, $this->ymd($desde), $this->ymd($hasta)]
        );
    }


    public function valorTotalPagos($anio, $mes)
    {
        return $this->db->table('dbo.vInformacionAgronomicoSiesaTotal')
            ->select("SUM(valorTotalTercero) AS Total")
            ->where('año', $anio)
            ->where('mes', $mes)
            ->where('TipoContrato', 'Temporal')
            ->get()
            ->getResultArray();
    }


    public function pagosLaborResumen($anio, $mes)
    {
        $sql = "
        WITH Registros AS (
            SELECT 
                centroOperacion,
                ccosto,
                fecha,
                valorTotalTercero,
                cedula,
                uNegocio,
                nombreTercero,
                nombreGrupoLabor,
                RazonTemporal
            FROM dbo.vInformacionAgronomicoSiesaTotal 
            WHERE [año] = ?
                AND mes = ?
                AND TipoContrato = 'Temporal'
        ),
        TotalGeneral AS (
            SELECT RazonTemporal, SUM(valorTotalTercero) AS Total
            FROM Registros
            GROUP BY RazonTemporal
        ),
        TotalLiquidacion AS (
            SELECT RazonTemporal, ISNULL(SUM(valortemporal), 0) AS TotalTemp
            FROM Liquidacion 
            WHERE año = ? AND mes = ?
            GROUP BY RazonTemporal
        )
        SELECT 
            r.ccosto,
            r.nombreGrupoLabor,
            SUM(r.valorTotalTercero) AS ValorHecarse,
            CASE 
                WHEN tg.Total > 0 
                THEN SUM(r.valorTotalTercero) / tg.Total 
                ELSE 0 
            END AS Participacion,
            CASE 
                WHEN tg.Total > 0 
                THEN ISNULL(tl.TotalTemp, 0) * SUM(r.valorTotalTercero) / tg.Total 
                ELSE 0 
            END AS ValorTemporal
        FROM Registros r
        LEFT JOIN TotalGeneral tg ON r.RazonTemporal = tg.RazonTemporal
        LEFT JOIN TotalLiquidacion tl ON r.RazonTemporal = tl.RazonTemporal
        GROUP BY r.ccosto, r.nombreGrupoLabor, tg.Total, tl.TotalTemp
        ORDER BY r.ccosto, r.nombreGrupoLabor
        ";

        return $this->db->query($sql, [$anio, $mes, $anio, $mes])->getResultArray();
    }


    public function pagosLaborDetallado($anio, $mes)
    {
        $sql = "
            WITH TotalGeneral AS (
                SELECT RazonTemporal, SUM(valorTotalTercero) AS Total
                FROM dbo.vInformacionAgronomicoSiesaTotal 
                WHERE [año] = ?
                    AND mes = ?
                    AND TipoContrato = 'Temporal'
                GROUP BY RazonTemporal
                
            ),
            TotalLiquidacion AS (
                SELECT RazonTemporal, ISNULL(SUM(valortemporal), 0) AS TotalTemp
                FROM Liquidacion 
                WHERE año = ? AND mes = ?
                GROUP BY RazonTemporal
            )
            SELECT 
                r.ccosto,
                r.nombreGrupoLabor,
                r.RazonTemporal,
                r.valorTotalTercero AS ValorHecarse,
                r.nombreTercero,
                r.cedula,
                CASE 
                    WHEN tg.Total > 0 
                    THEN r.valorTotalTercero / tg.Total 
                    ELSE 0 
                END AS Participacion,
                CASE 
                    WHEN tg.Total > 0 
                    THEN ISNULL(tl.TotalTemp, 0) * r.valorTotalTercero / tg.Total 
                    ELSE 0 
                END AS ValorTemporal
            FROM dbo.vInformacionAgronomicoSiesaTotal r
            LEFT JOIN TotalGeneral tg ON r.RazonTemporal = tg.RazonTemporal
            LEFT JOIN TotalLiquidacion tl ON r.RazonTemporal = tl.RazonTemporal
            WHERE r.[año] = ?
                AND r.mes = ?
                AND r.TipoContrato = 'Temporal'
            ORDER BY r.ccosto, r.nombreGrupoLabor
            ";

        return $this->db->query($sql, [$anio, $mes, $anio, $mes, $anio, $mes])->getResultArray();
    }


    // En InformeModel.php
    public function getDatosLiquidacion($anio, $mes, $temporal)
    {
        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        $sql = "
            WITH TotalGeneral AS (
                SELECT SUM(valorTotalTercero) AS Total
                FROM dbo.vInformacionAgronomicoSiesaTotal 
                WHERE [año] = ?
                    AND mes = ?
                    AND TipoContrato = 'Temporal'
                    AND RazonTemporal = ?
            ),
            TotalLiquidacion AS (
                SELECT ISNULL(SUM(valortemporal), 0) AS TotalTemp
                FROM Liquidacion 
                WHERE año = ? AND mes = ?
                AND RazonTemporal = ?
            )
            SELECT 
                t.nit,
                r.ccosto,
                r.centroOperacion,
                r.uNegocio,
                r.nombreGrupoLabor,
                CASE 
                    WHEN tg.Total > 0 
                    THEN CAST(ROUND(tl.TotalTemp * SUM(r.valorTotalTercero) / tg.Total, 0) AS INT)
                    ELSE 0 
                END AS ValorTemporal
            FROM dbo.vInformacionAgronomicoSiesaTotal r
            CROSS JOIN TotalGeneral tg
            CROSS JOIN TotalLiquidacion tl
            JOIN dbo.cTercero t ON r.RazonTemporal = t.razonSocial
            WHERE r.[año] = ?
                AND r.mes = ?
                AND r.RazonTemporal = ?
            GROUP BY 
                t.nit,
                r.ccosto,
                r.centroOperacion,
                r.uNegocio,
                r.nombreGrupoLabor,
                tg.Total,
                tl.TotalTemp
            ORDER BY 
                r.ccosto,
                r.nombreGrupoLabor;
        ";

        return $this->db->query($sql, [
            $anio, $mes, $temporal,  // TotalGeneral
            $anio, $mes, $temporal,  // TotalLiquidacion
            $anio, $mes, $temporal   // WHERE principal
        ])->getResultArray();
    }


    public function getNitTemporal($temporal)
    {
        $sql = "SELECT nit FROM dbo.cTercero WHERE razonSocial = ?";

        $result = $this->db->query($sql, [$temporal])->getRow();

        return $result ? $result->nit : null;
    }
}
