<?php

namespace App\Models;

use CodeIgniter\Model;

class CargosModel extends Model
{
    protected $table      = 'Cargos';
    protected $primaryKey = 'Id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'Nombre',
        'Abreviacion',
        'IdUsuario',
        'FechaInicio',
        'FechaModificacion',
        'FechaFinalizacion',
    ];

    public function getAll(): array
    {
        return $this->where('FechaFinalizacion IS NULL', null, false)
                    ->orderBy('Nombre', 'ASC')
                    ->findAll();
    }

    /**
     * Metodo para buscar un cargo por su nombre.
     * 
     * @param string $term Nombre del cargo a buscar.
     * @return array Resultados de la busqueda.
     */
    public function findCargoByName(string $term = ''): array
    {
        $builder = $this->db->table('Cargos')
            ->where('FechaFinalizacion IS NULL')
            ->orderBy('Nombre', 'ASC');

        if (!empty($term)) {
            $builder->like('Nombre', $term);
        }
        $builder->limit(10);

        return $builder->get()->getResultArray();
    }
}