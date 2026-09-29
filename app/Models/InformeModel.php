<?php

namespace App\Models;

use CodeIgniter\Model;

class InformeModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

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

    public function getReporteGeneral($anio, $mes)
    {
        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        $builder = $this->db->table('Infos.dbo.vInformacionAgronomicoSiesaTotal');

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

        $builder->where('año', $anio);
        $builder->where('mes', $mes);

        // 1. Generamos el SQL sin ejecutarlo aún
        $sql = $builder->getCompiledSelect();

        // 2. Ejecutamos con query() pasando FALSE en el tercer parámetro (buffer off)
        // El segundo parámetro son los binds (vacío porque ya van en el SQL compilado)
        $query = $this->db->query($sql, [], false);

        return $query->getResultArray();
    }

    public function getTemporales($anio, $mes)
    {
        return $this->db
            ->table('Infos.dbo.vInformacionAgronomicoSiesaTotal')
            ->select('RazonTemporal, TipoContrato')
            ->distinct()
            ->where('año', $anio)
            ->where('mes', $mes)
            ->get()
            ->getResultArray();
    }


    public function getEmpleadosSinTemporal($anio, $mes)
    {
        return $this->db
            ->table('Infos.dbo.vInformacionAgronomicoSiesaTotal')
            ->select('cedula')
            ->select('nombreTercero')
            ->distinct()
            ->where('año', $anio)
            ->where('mes', $mes)
            ->where('TipoContrato', 'Temporal')
            ->where("(RazonTemporal IS NULL OR LTRIM(RTRIM(RazonTemporal)) = '')", null, false)
            ->get()
            ->getResultArray();
    }

    public function verificarReporteProcesado($anio, $mes, $temporal, $tipoContrato)
    {
        $count = $this->db->table('Liquidacion')
            ->where('Año', $anio)
            ->where('Mes', $mes)
            ->where('RazonTemporal', $temporal)
            ->where('TipoContrato', $tipoContrato)
            ->countAllResults();

        return $count > 0;
    }


    public function guardarReporteProcesado($datos, $anio, $mes, $temporal, $tipoContrato)
    {
        if (empty($datos)) {
            return 0;
        }

        $this->db->transBegin();

        try {

            $builder = $this->db->table('Liquidacion');

            // Eliminar registros anteriores
            $builder->where('Año', $anio)
                ->where('Mes', $mes)
                ->where('RazonTemporal', $temporal)
                ->where('TipoContrato', $tipoContrato)
                ->delete();

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
                    ([Año], Mes, Cedula, NombreEmpleado, TipoContrato, RazonTemporal, ValorEnviado, FechaProcesado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";


                $query = $this->db->query($sql, [
                    $row['Año'],
                    $row['Mes'],
                    $row['Cedula'],
                    $row['NombreEmpleado'],
                    $row['TipoContrato'],
                    $row['RazonTemporal'],
                    $row['ValorEnviado'],
                    $row['FechaProcesado']
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


    public function existeCedulaPeriodo($cedula, $anio, $mes)
    {
        return $this->db->table('Liquidacion')
            ->where('Cedula', $cedula)
            ->where('Año', $anio)
            ->where('Mes', $mes)
            ->countAllResults() > 0;
    }

    public function buscarCedula($cedula)
    {
        $sql = "
            SELECT f.codigo AS Cedula, f.descripcion AS NombreEmpleado, t.razonSocial AS RazonTemporal,
                TipoContrato = CASE WHEN sc.activo = 1 AND sc.empresa != 2 THEN 'Directo' ELSE 'Temporal' END
            FROM Infos..nFuncionario f
            LEFT JOIN Infos..cTercero t ON t.codigo = f.proveedor
            LEFT JOIN Infos..SiesaContratos sc ON f.codigo collate SQL_Latin1_General_CP1_CI_AS = sc.codigoTercero
            WHERE f.codigo = '$cedula'
        ";

        $query = $this->db->query($sql);
        return $query->getRowArray();
    }

    public function insertarNuevos($anio, $mes, $cedula, $nombre, $tipoContrato, $temporal, $valorTemporal)
    {
        $fecha = date('Ymd H:i:s');

        $sql = "
            INSERT INTO Liquidacion 
            ([Año], Mes, Cedula, NombreEmpleado, TipoContrato, RazonTemporal, valorTemporal, FechaProcesado)
            VALUES ('$anio', '$mes', '$cedula', '$nombre', '$tipoContrato', '$temporal', '$valorTemporal', '$fecha')
        ";

        return $this->db->query($sql);
    }

    public function actualizarValorTemporal($cedula, $valor, $anio, $mes)
    {
        return $this->db->table('Liquidacion')
            ->where('Cedula', $cedula)
            ->where('año', $anio)
            ->where('mes', $mes)
            ->set('ValorTemporal', $valor)
            ->update();
    }


    public function valorTotalPagos($anio, $mes)
    {
        return $this->db->table('Infos.dbo.vInformacionAgronomicoSiesaTotal')
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
            FROM Infos.dbo.vInformacionAgronomicoSiesaTotal 
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
                FROM Infos.dbo.vInformacionAgronomicoSiesaTotal 
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
            FROM Infos.dbo.vInformacionAgronomicoSiesaTotal r
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
                FROM Infos.dbo.vInformacionAgronomicoSiesaTotal 
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
            FROM Infos.dbo.vInformacionAgronomicoSiesaTotal r
            CROSS JOIN TotalGeneral tg
            CROSS JOIN TotalLiquidacion tl
            JOIN Infos.dbo.cTercero t ON r.RazonTemporal = t.razonSocial
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
        $sql = "SELECT nit FROM Infos.dbo.cTercero WHERE razonSocial = ?";

        $result = $this->db->query($sql, [$temporal])->getRow();

        return $result ? $result->nit : null;
    }
}
