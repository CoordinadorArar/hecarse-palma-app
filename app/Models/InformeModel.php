<?php

namespace App\Models;

use CodeIgniter\Model;

class InformeModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    private const INICIO = 'COALESCE(FechaInicio, DATEFROMPARTS(TRY_CAST([Año] AS int), TRY_CAST(Mes AS int), 1))';
    private const FIN    = 'COALESCE(FechaFin, EOMONTH(DATEFROMPARTS(TRY_CAST([Año] AS int), TRY_CAST(Mes AS int), 1)))';

    private static ?bool $columnasRango = null;

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

        $cruces = array_values(array_filter($rangos, fn ($r) => $r['desde'] !== $desde || $r['hasta'] !== $hasta));

        return ['procesado' => count($cruces) < count($rangos), 'cruces' => $cruces];
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
