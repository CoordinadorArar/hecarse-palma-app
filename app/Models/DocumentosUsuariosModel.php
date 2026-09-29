<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentosUsuariosModel extends Model
{
    protected $table      = 'DocumentosUsuarios';
    protected $primaryKey = 'Id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'Codigo',
        'IdUsuarioAsignado',
        'IdUsuario'
    ];

    /**
     * Obtiene los IDs de usuarios asignados a un código de documento.
     */
    public function getUsuariosByCodigo(string $codigo): array
    {
        return $this->db->table($this->table)
            ->select('IdUsuarioAsignado')
            ->where('Codigo', $codigo)
            ->get()->getResultArray();
    }

    /**
     * Sincroniza los usuarios asignados a un documento.
     * Inserta los nuevos y elimina los que se quitaron.
     */
    public function sincronizar(string $codigo, array $idsSeleccionados, int $usu_id): bool
    {
        // IDs actualmente asignados
        $actuales = array_column($this->getUsuariosByCodigo($codigo), 'IdUsuarioAsignado');
        $actuales = array_map('intval', $actuales);

        $seleccionados = array_map('intval', $idsSeleccionados);

        // Agregar los que no existían
        $agregar = array_diff($seleccionados, $actuales);
        foreach ($agregar as $idUsuario) {
            $this->insert([
                'Codigo'    => $codigo,
                'IdUsuarioAsignado' => $idUsuario,
                'IdUsuario' => $usu_id,
            ]);
        }

        // Eliminar los que se quitaron
        $eliminar = array_diff($actuales, $seleccionados);
        if (!empty($eliminar)) {
            $this->db->table($this->table)
                ->where('Codigo', $codigo)
                ->whereIn('IdUsuarioAsignado', $eliminar)
                ->delete();
        }

        return true;
    }
}
