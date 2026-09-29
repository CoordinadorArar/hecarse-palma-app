<?php

namespace App\Models;

use CodeIgniter\Model;

class SiteModel extends Model
{
    protected $returnType = 'array';

    /**
     * Obtener toda la configuración del sitio como un array asociativo
     * @return array Configuración con claves como 'nombre_app', 'logo', etc.
     */
    public function getConfiguracionCompleta(): array
    {
        $builder = $this->db->table('ConfiguracionSitio');
        $builder->where('FechaFinalizacion', null);
        $query = $builder->get();

        $config = [];
        foreach ($query->getResultArray() as $row) {
            $config[$row['Nombre']] = $row['Valor'];
        }

        return $config;
    }

    /**
     * Obtener UN valor específico de configuración
     * @param string $nombre Clave de la configuración (nombre_app, logo, etc.)
     * @return string|null Valor o null si no existe
     */
    public function getConfiguracionPorNombre(string $nombre): ?string
    {
        $builder = $this->db->table('ConfiguracionSitio');
        $builder->select('Valor');
        $builder->where('Nombre', $nombre);
        $builder->where('FechaFinalizacion', null);
        $query = $builder->get();

        $row = $query->getRowArray();
        return $row ? $row['Valor'] : null;
    }


    /**
     * Insertar o actualizar configuraciones del sitio
     * @param array $data Array asociativo con claves como 'nombre_app', 'logo', etc. y sus respectivos valores
     * @return bool True si la operación fue exitosa, false en caso contrario
     */
    public function upsertConfiguracion(array $data): bool
    {
        $session = \Config\Services::session();
        $fecha = date('Y-m-d H:i:s');
        $fecha = explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1];


        $this->db->transStart();

        foreach ($data as $nombre => $valor) {
            $builder = $this->db->table('ConfiguracionSitio');
            $builder->where('Nombre', $nombre);
            $builder->where('FechaFinalizacion', null);
            $existente = $builder->get()->getRowArray();

            if ($existente) {
                // Actualizar
                $updateData = [
                    'Valor' => $valor,
                    'IdUsuario' => $session->get('usu_id'),
                    'FechaModificacion' => $fecha
                ];
                $builder->where('Id', $existente['Id']);
                $result = $builder->update($updateData);

                if ($result) {
                    $this->insertarAuditoria('ConfiguracionSitio', 'UPDATE', $updateData, $session->get('usu_id'));
                }
            } else {
                // Insertar nuevo
                $insertData = [
                    'Nombre' => $nombre,
                    'Valor' => $valor,
                    'IdUsuario' => $session->get('usu_id'),

                ];
                $result = $this->db->table('ConfiguracionSitio')->insert($insertData);

                if ($result) {
                    $this->insertarAuditoria('ConfiguracionSitio', 'INSERT', $insertData, $session->get('usu_id'));
                }
            }
        }

        $this->db->transComplete();
        return $this->db->transStatus() === false ? false : true;
    }

    /** Metodo privado para insertar registros de auditoría
     * @param string $tabla Nombre de la tabla afectada
     * @param string $accion Tipo de acción (INSERT, UPDATE, DELETE)
     * @param array $cambios Array asociativo con los cambios realizados (columna => valor)
     * @param int $usu_id ID del usuario que realizó el cambio
     */
    private function insertarAuditoria(string $tabla, string $accion, array $cambios, int $usu_id): void
    {
        $fecha = date('Y-m-d H:i:s');
        $fecha = explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1];

        $auditoriaData = [
            'TablaAfectada' => $tabla,
            'Accion' => $accion,
            'CambiosRealizados' => json_encode($cambios),
            'IdUsuario' => $usu_id,
            'FechaRegistro' => $fecha,
        ];

        $this->db->table('Auditoria')->insert($auditoriaData);
    }
}
