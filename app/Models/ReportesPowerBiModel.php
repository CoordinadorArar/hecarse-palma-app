<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportesPowerBiModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    public function getReportesByUsuario($idUsuario)
    {
        $builder = $this->db->table('ReportesPowerBi r');
        $builder->select('r.Nombre, r.Descripcion, r.Enlace, r.Icono');
        $builder->join('ReporteUsuarios ru', 'r.Id = ru.ReporteId');
        $builder->where('ru.UsuarioId', $idUsuario);
        $builder->where('r.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Método para consultar la lista de reportes.
     * 
     * @return array Lista de reportes.
     */
    // public function obtenerListaReportes($data = [])
    // {
    //     $builder = $this->db->table('ReportesPowerbi');
    //     $builder->where('FechaFinalizacion IS NULL');

    //     if (!empty($data)) {
    //         foreach ($data as $key => $value) {
    //             if ($key === 'FechaFinalizacion') {
    //                 if ($value === 'IS NULL') {
    //                     $builder->where('FechaFinalizacion IS NULL', null, false);
    //                 } elseif ($value === 'IS NOT NULL') {
    //                     $builder->where('FechaFinalizacion IS NOT NULL', null, false);
    //                 } else {
    //                     $builder->where($key, $value);
    //                 }
    //             } else {
    //                 $builder->where($key, $value);
    //             }
    //         }
    //     }

    //     $builder->orderBy('Id', 'DESC');
    //     $query = $builder->get();

    //     return $query->getResultArray();
    // }

    // /**
    //  * Metodo para obtener un reporte en especifico.
    //  * Se consulta por su "id" en base de datos.
    //  * 
    //  * @param int $id Identificador del reporte.
    //  * @return array Datos del reporte.
    //  */
    // public function getReporteById($id): array
    // {
    //     $builder = $this->db->table('ReportesPowerbi r');
    //     $builder->select('r.*');
    //     $builder->where('r.Id', $id);
    //     $query = $builder->get();

    //     return $query->getRowArray();
    // }

    // /**Metodo para crear un reporte nuevo */
    // public function createReporte($data)
    // {
    //     $builder = $this->db->table('ReportesPowerbi');
    //     $result = $builder->insert($data);

    //     if ($result) {
    //         $session = session();

    //         $idInsertado = $this->db->insertID();
    //         $reporte = $this->getReporteById($idInsertado);

    //         $jsonCambios = json_encode($reporte, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    //         // Inserta en la tabla Auditoria
    //         $fecha = date('Y-m-d H:i:s');
    //         $fecha = explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1];
    //         $auditoriaData = [
    //             'TablaAfectada' => 'ReportesPowerbi',
    //             'Accion' => 'INSERT',
    //             'CambiosRealizados' => $jsonCambios,
    //             'FechaRegistro' => $fecha,
    //             'IdUsuario' => $session->get('usu_id'),
    //         ];

    //         $auditoriaBuilder = $this->db->table('Auditoria');
    //         $auditoriaBuilder->insert($auditoriaData);
    //     }
    // }

    // /**Metodo para actualizar un reporte */
    // public function updateReporte($id, $data)
    // {
    //     $anterior = $this->getReporteById($id);

    //     $builder = $this->db->table('ReportesPowerbi');
    //     $builder->where('Id', $id);
    //     $builder->update($data);

    //     /**Insertar registro para auditoria */
    //     $diferencias = [];
    //     foreach ($data as $columna => $valorNuevo) {
    //         if (isset($anterior[$columna]) && $anterior[$columna] != $valorNuevo) {
    //             $diferencias[] = [
    //                 'Columna' => $columna,
    //                 'ValorAnterior' => $anterior[$columna],
    //                 'ValorNuevo' => $valorNuevo,
    //             ];
    //         }
    //     }

    //     if (!empty($diferencias)) {
    //         $cambios = json_encode($diferencias);
    //         $session = session();

    //         // Inserta en la tabla Auditoria
    //         $fecha = date('Y-m-d H:i:s');
    //         $fecha = explode(' ', $fecha)[0] . 'T' . explode(' ', $fecha)[1];
    //         $auditoriaData = [
    //             'TablaAfectada' => 'ReportesPowerbi',
    //             'Accion' => 'UPDATE',
    //             'CambiosRealizados' => $cambios,
    //             'FechaRegistro' => $fecha,
    //             'IdUsuario' => $session->get('usu_id'),
    //         ];

    //         $auditoriaBuilder = $this->db->table('Auditoria');
    //         $auditoriaBuilder->insert($auditoriaData);
    //     }
    // }

    // /**
    //  * Método para eliminar un reporte.
    //  * Se consulta por su "id" en base de datos y se marca como "finalizado".
    //  * 
    //  * @param int $id Identificador del reporte.
    //  */
    // public function deleteReporte($id)
    // {
    //     $anterior = $this->getReporteById($id);
    //     $fecha_delete = date('Y-m-d H:i:s');
    //     $fecha_delete = explode(' ', $fecha_delete)[0] . 'T' . explode(' ', $fecha_delete)[1];

    //     $builder = $this->db->table('ReportesPowerbi');
    //     $builder->where('Id', $id);
    //     $builder->update(['FechaFinalizacion' => $fecha_delete]);

    //     //Insertar registro para auditoria
    //     $jsonCambios = json_encode($anterior, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    //     $auditoriaData = [
    //         'TablaAfectada' => 'ReportesPowerbi',
    //         'Accion' => 'DELETE',
    //         'CambiosRealizados' => $jsonCambios,
    //         'FechaRegistro' => $fecha_delete,
    //         'IdUsuario' => session()->get('usu_id'),
    //     ];

    //     $auditoriaBuilder = $this->db->table('Auditoria');
    //     $auditoriaBuilder->insert($auditoriaData);
    // }
}
