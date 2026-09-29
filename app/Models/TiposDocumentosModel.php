<?php

namespace App\Models;

use CodeIgniter\Model;

class TiposDocumentosModel extends Model
{
    protected $table      = 'TiposDocumentos';
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
     * Obtiene todos los tipos de documento activos (sin fecha de finalización).
     */
    public function getAllActivos(): array
    {
        return $this->where('FechaFinalizacion', null)
            ->orderBy('Nombre', 'ASC')
            ->findAll();
    }

    public function getAll(): array
    {
        return $this->db->table('TiposDocumentos T')
            ->select('T.*, U.Nombre AS NombreUsuario, U.Apellido AS ApellidoUsuario', false)
            ->join('Usuarios U', 'T.IdUsuario = U.Id', 'left')
            ->orderBy('T.Nombre', 'ASC')
            ->get()->getResultArray();
    }

    public function createTipo(array $data, int $usu_id): int|false
    {
        $id = $this->insert(array_merge($data, [
            'IdUsuario'   => $usu_id,
            'FechaInicio' => date('Y-m-d\TH:i:s'),
        ]));

        if ($id === false) return false;

        $this->insertarAuditoria('TiposDocumentos', 'INSERT', array_merge($data, ['Id' => $id]), $usu_id);
        return $id;
    }

    public function updateTipo(int $id, array $data, int $usu_id): bool
    {
        $data['FechaModificacion'] = date('Y-m-d\TH:i:s');
        $ok = $this->update($id, $data);

        if ($ok) {
            $this->insertarAuditoria('TiposDocumentos', 'UPDATE', array_merge($data, ['Id' => $id]), $usu_id);
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
