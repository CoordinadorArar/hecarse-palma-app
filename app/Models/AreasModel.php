<?php

namespace App\Models;

use CodeIgniter\Model;

class AreasModel extends Model
{
    protected $table      = 'Areas';
    protected $primaryKey = 'Id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'Nombre',
        'Abreviatura',
        'IdUsuario',
        'FechaInicio',
        'FechaModificacion',
        'FechaFinalizacion',
    ];

    /**
     * Obtiene todas las áreas activas (sin fecha de finalización).
     */
    public function getAllActivas(): array
    {
        return $this->where('FechaFinalizacion', null)
            ->orderBy('Nombre', 'ASC')
            ->findAll();
    }

    public function getAll(): array
    {
        return $this->db->table('Areas A')
            ->select('A.*, U.Nombre AS NombreUsuario, U.Apellido AS ApellidoUsuario', false)
            ->join('Usuarios U', 'A.IdUsuario = U.Id', 'left')
            ->orderBy('A.Nombre', 'ASC')
            ->get()->getResultArray();
    }

    public function createArea(array $data, int $usu_id): bool
    {
        $fecha = date('Y-m-d\TH:i:s');
        $id = $this->insert(array_merge($data, [
            'IdUsuario'   => $usu_id,
            'FechaInicio' => $fecha,
        ]));

        if ($id === false) return false;

        $this->insertarAuditoria('Areas', 'INSERT', array_merge($data, ['Id' => $id]), $usu_id);
        return true;
    }

    public function updateArea(int $id, array $data, int $usu_id): bool
    {
        $data['FechaModificacion'] = date('Y-m-d\TH:i:s');
        $ok = $this->update($id, $data);

        if ($ok) {
            $this->insertarAuditoria('Areas', 'UPDATE', array_merge($data, ['Id' => $id]), $usu_id);
        }

        return $ok;
    }

    public function abreviaturaExiste(string $abreviatura, int $excludeId = 0): bool
    {
        $builder = $this->where('Abreviatura', strtoupper($abreviatura))
            ->where('FechaFinalizacion IS NULL', null, false);

        if ($excludeId > 0) {
            $builder->where('Id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    private function insertarAuditoria(string $tabla, string $accion, array $cambios, int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => $tabla,
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
