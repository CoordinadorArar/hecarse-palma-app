<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentosCargosModel extends Model
{
    protected $table      = 'DocumentosCargos';
    protected $primaryKey = 'Id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'Codigo',
        'IdCargo',
        'IdUsuario',
        'FechaAsignacion',
    ];

    public function getCargosByCodigo(string $codigo): array
    {
        return $this->db->table($this->table)
            ->select('IdCargo')
            ->where('Codigo', $codigo)
            ->get()->getResultArray();
    }

    public function sincronizar(string $codigo, array $idsSeleccionados, int $usu_id): bool
    {
        $actuales      = array_map('intval', array_column($this->getCargosByCodigo($codigo), 'IdCargo'));
        $seleccionados = array_map('intval', $idsSeleccionados);

        $agregar = array_diff($seleccionados, $actuales);
        foreach ($agregar as $idCargo) {
            $this->insert([
                'Codigo'            => $codigo,
                'IdCargo'           => $idCargo,
                'IdUsuario' => $usu_id,
            ]);
        }

        $eliminar = array_diff($actuales, $seleccionados);
        if (!empty($eliminar)) {
            $this->db->table($this->table)
                ->where('Codigo', $codigo)
                ->whereIn('IdCargo', $eliminar)
                ->delete();
        }

        return true;
    }
}