<?php

namespace App\Models;

use CodeIgniter\Model;

class FinancieroModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    public function getTemporales($anio, $mes)
    {
        return $this->db
            ->table('Infos.dbo.vInformacionAgronomicoSiesaTotal')
            ->select('RazonTemporal')
            ->distinct()
            ->where('año', $anio)
            ->where('mes', $mes)
            ->where('TipoContrato', 'Temporal')
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

    public function getReporteGeneral($anio, $mes, $temporal)
    {
        return $this->db->table('Infos.dbo.vInformacionAgronomicoSiesaTotal')
            ->select('
            centroOperacion,
            ccosto,
            fecha,
            valorTotalTercero,
            cedula,
            uNegocio,
            nombreTercero,
            nombreConcepto,
            nombreGrupoLabor,
            RazonTemporal
        ')
            ->where('año', $anio)
            ->where('mes', $mes)
            ->where('TipoContrato', 'Temporal')
            ->where('RazonTemporal', $temporal)
            ->get()
            ->getResultArray();
    }



}
