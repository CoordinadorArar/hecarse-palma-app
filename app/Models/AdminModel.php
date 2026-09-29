<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $returnType = 'array';

    public function getDepartamentos()
    {
        $builder = $this->db->query("SELECT * FROM UNOEEARAR..t012_mm_deptos WHERE f012_id_pais = 169");

        return $builder->getResultArray();
    }

    public function getCiudades($id_departamento)
    {
        $builder = $this->db->query("SELECT * FROM UNOEEARAR..t013_mm_ciudades WHERE f013_id_depto = '$id_departamento'");

        return $builder->getResultArray();
    }

}