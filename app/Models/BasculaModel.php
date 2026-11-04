<?php

namespace App\Models;

use CodeIgniter\Model;

class BasculaModel extends Model
{
    protected $table      = 'bBasculaRegistro';
    protected $returnType = 'array';

    public function insertarNuevos(string $tabla, string $llave, array $filas): ?int
    {
        if ($filas === []) {
            return 0;
        }

        $filas = array_column($filas, null, $llave);

        $this->db->transBegin();

        try {
            $existentes = $this->db->table($tabla)
                ->select($llave)
                ->whereIn($llave, array_keys($filas))
                ->get()->getResultArray();

            $nuevas = array_values(array_diff_key($filas, array_flip(array_map('intval', array_column($existentes, $llave)))));

            if ($nuevas !== [] && $this->db->table($tabla)->insertBatch($nuevas) === false) {
                $this->db->transRollback();

                return null;
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();

                return null;
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Báscula, ' . $tabla . ': ' . $e->getMessage());

            return null;
        }

        return count($nuevas);
    }
}
