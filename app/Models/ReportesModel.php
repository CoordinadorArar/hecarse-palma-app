<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportesModel extends Model
{
    protected $table      = 'Modulos';
    protected $returnType = 'array';

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->where('activo', 1)->countAllResults() > 0;
    }

    public function moduloPermitido(int $usuario, string $ruta): bool
    {
        return $this->db->query(
            'SELECT TOP 1 m.Id
               FROM Modulos m
               JOIN ModulosRoles mr ON mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL
               JOIN UsuariosRoles ur ON ur.IdRol = mr.IdRol AND ur.IdUsuario = ? AND ur.FechaFinalizacion IS NULL
               JOIN Roles r ON r.Id = ur.IdRol AND r.FechaFinalizacion IS NULL
              WHERE m.Ruta = ? AND m.FechaFinalizacion IS NULL',
            [$usuario, $ruta]
        )->getRowArray() !== null;
    }
}
