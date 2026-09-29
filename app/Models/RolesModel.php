<?php

namespace App\Models;

use CodeIgniter\Model;

class RolesModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    /**
     * Método para obtener todos los roles disponibles.
     * 
     * @return array Listado de roles, de lo contrario [].
     */
    public function getAllRoles(){
        $builder = $this->db->table('Roles r');
        $builder->join('Usuarios u', 'u.Id = r.IdUsuario');
        $builder->select('r.*, u.Nombre AS NombreUsuario, u.Apellido AS ApellidoUsuario');
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los roles activos, disponibles.
     * 
     * @return array Listado de roles activos, de lo contrario [].
     */
    public function getActiveRoles(): array
    {
        $builder = $this->db->table('Roles r');
        $builder->select('r.*');
        $query = $builder->get();
        $builder->where('ur.FechaFinalizacion', null);

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener un rol en especifico.
     * Se consulta por su "id" en base de datos.
     * 
     * @param int $id Identificador del rol.
     * @return array Datos del rol.
     */
    public function getRolById($id): array
    {
        $builder = $this->db->table('Roles r');
        $builder->select('r.*');
        $builder->where('r.Id', $id);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * Metodo para actualizar un rol.
     * Se actualizan todos los datos del rol que viene en array $data.
     * 
     * @param int $id_rol Identificador del rol a actualizar.
     * @param array $data Datos del rol a actualizar.
     * @return void
     */
    public function updateRol($id, $data)
    {
        $anterior = $this->getRolById($id);

        $builder = $this->db->table('Roles');
        $builder->where('Id', $id);
        $builder->update($data);

        /**Insertar registro para auditoria */
        $diferencias = [];
        foreach ($data as $columna => $valorNuevo) {
            if (isset($anterior[$columna]) && $anterior[$columna] != $valorNuevo) {
                $diferencias[] = [
                    'Columna' => $columna,
                    'ValorAnterior' => $anterior[$columna],
                    'ValorNuevo' => $valorNuevo,
                ];
            }
        }

        if (!empty($diferencias)) {
            $cambios = json_encode($diferencias);
            $session = session();
        
            // Inserta en la tabla Auditoria
            $fecha = date('Y-m-d H:i:s');
            $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
            $auditoriaData = [
                'TablaAfectada' => 'Roles',
                'Accion' => 'UPDATE',
                'CambiosRealizados' => $cambios,
                'FechaRegistro' =>$fecha,
                'IdUsuario' => $session->get('usu_id'),
            ];
        
            $auditoriaBuilder = $this->db->table('Auditoria');
            $auditoriaBuilder->insert($auditoriaData);
        }
    }

    /**
     * Metodo para buscar un rol por su nombre.
     * 
     * @param string $term Nombre del rol a buscar.
     * @return array Resultados de la busqueda.
     */
    public function findRolByName($term): array
    {
        $builder = $this->db->table('Roles r');
        $builder->select('r.*');
        $builder->like('Nombre', $term);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para crear un rol nuevo en base de datos.
     * 
     * @param array $data Datos del rol a crear.
     * @return bool True si se creo el rol, de lo contrario False.
     */
    public function createRol($data): bool
    {
        $builder = $this->db->table('Roles');
        $result = $builder->insert($data);

        /**Insertar registro para auditoria */
        if ($result) {
            $session = \Config\Services::session();

            $idInsertado = $this->db->insertID();
            $rol = $this->getRolById($idInsertado);

            $jsonCambios = json_encode($rol, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);        
            // Inserta en la tabla Auditoria
            $fecha = date('Y-m-d H:i:s');
            $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
            $auditoriaData = [
                'TablaAfectada' => 'Roles',
                'Accion' => 'INSERT',
                'CambiosRealizados' => $jsonCambios,
                'FechaRegistro' =>$fecha,
                'IdUsuario' => $session->get('usu_id'),
            ];
        
            $auditoriaBuilder = $this->db->table('Auditoria');
            $auditoriaBuilder->insert($auditoriaData);
        }

        return $result !== false;
    }

    /**
     * Metodo para obtener todos los roles disponibles que no
     * tiene asignado el usuario.
     * 
     * @param $id_usuario Identificador del usuario.
     * @return array Listado de roles, de lo contrario [].
     */
    public function getRolesNotUser($id_usuario): array
    {
        $subquery = $this->db->table('UsuariosRoles ur')
            ->select('ur.IdRol')
            ->where('ur.IdUsuario', $id_usuario)
            ->where('ur.FechaFinalizacion', null)
            ->getCompiledSelect();

        $builder = $this->db->table('Roles r');
        $builder->select('r.*');
        $builder->where('r.FechaFinalizacion', null);
        $builder->where("r.Id NOT IN ($subquery)", null, false);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los roles de un usuario en especifico.
     * 
     * @param int id_usuario Identificador del usuario.
     * @return array Listado de roles, de lo contrario [].
     */
    public function getRolesByUser($id_usuario): array
    {
        $builder = $this->db->table('UsuariosRoles ur');
        $builder->select('r.*');
        $builder->join('Roles r', 'r.Id = ur.IdRol');
        $builder->where('ur.IdUsuario', $id_usuario);
        $builder->where('ur.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los roles de un usuario en especifico
     * asi se le haya quitado o establecido la fecha de finalizacion en NULL.
     * 
     * @param int id_usuario Identificador del usuario.
     * @return array Listado de roles, de lo contrario [].
     */
    public function getAllRolesByUser($id_usuario): array
    {
        $builder = $this->db->table('UsuariosRoles ur');
        $builder->select('r.*');
        $builder->join('Roles r', 'r.Id = ur.IdRol');
        $builder->where('ur.IdUsuario', $id_usuario);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para eliminar un rol de un usuario en especifico.
     * Se establece la fecha de finalizacion del rol en la tabla correspodiente.
     * 
     * @param int $id_usuario Identificador del usuario.
     * @param int $id_rol Identificador del rol.
     * @param string $fecha Fecha de finalizacion del rol.
     * @return bool True si se elimino el rol, de lo contrario false.
     */
    public function deleteUserRole($id_usuario, $id_rol, $fecha): bool
    {
        /**Consulta para auditoria */
        $builder_anterior = $this->db->table('UsuariosRoles');
        $builder_anterior->select('*');
        $builder_anterior->where('IdUsuario', $id_usuario);
        $builder_anterior->where('IdRol', $id_rol);
        $query = $builder_anterior->get();
        $anterior = $query->getResultArray();

        /**Eliminar */
        $builder = $this->db->table('UsuariosRoles');
        $builder->set('FechaFinalizacion', $fecha);
        $builder->where('IdUsuario', $id_usuario);
        $builder->where('IdRol', $id_rol);
        $builder->update();

        /**Insertar registro para auditoria */
        $data = [];
        $data['FechaFinalizacion'] = $fecha;
        $diferencias = [];
        foreach ($data as $columna => $valorNuevo) {
            if (isset($anterior[$columna]) && $anterior[$columna] != $valorNuevo) {
                $diferencias[] = [
                    'Columna' => $columna,
                    'ValorAnterior' => $anterior[$columna],
                    'ValorNuevo' => $valorNuevo,
                ];
            }
        }

        $cambios = json_encode($diferencias);
        $session = \Config\Services::session();
    
        // Inserta en la tabla Auditoria
        $fecha = date('Y-m-d H:i:s');
        $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
        $auditoriaData = [
            'TablaAfectada' => 'UsuariosRoles',
            'Accion' => 'UPDATE',
            'CambiosRealizados' => $cambios,
            'FechaRegistro' =>$fecha,
            'IdUsuario' => $session->get('usu_id'),
        ];
    
        $auditoriaBuilder = $this->db->table('Auditoria');
        $auditoriaBuilder->insert($auditoriaData);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Metodo para actualizar un rol desactivo a un usuario en especifico.
     * Se establece la fecha de finalizacion del rol en NULL.
     * 
     * @param int $id_usuario Identificador del usuario.
     * @param int $id_rol Identificador del rol.
     * @return bool True si se elimino el rol, de lo contrario false.
     */
    public function updateUserRole($id_usuario, $id_rol): bool
    {
        /**Consulta para auditoria */
        $builder_anterior = $this->db->table('UsuariosRoles');
        $builder_anterior->select('*');
        $builder_anterior->where('IdUsuario', $id_usuario);
        $builder_anterior->where('IdRol', $id_rol);
        $query = $builder_anterior->get();
        $anterior = $query->getResultArray();

        $builder = $this->db->table('UsuariosRoles');
        $builder->set('FechaFinalizacion', null);
        $builder->where('IdUsuario', $id_usuario);
        $builder->where('IdRol', $id_rol);
        $builder->update();

        /**Insertar registro para auditoria */
        $data = [];
        $data['FechaFinalizacion'] = null;
        $diferencias = [];
        foreach ($data as $columna => $valorNuevo) {
            if (isset($anterior[$columna]) && $anterior[$columna] != $valorNuevo) {
                $diferencias[] = [
                    'Columna' => $columna,
                    'ValorAnterior' => $anterior[$columna],
                    'ValorNuevo' => $valorNuevo,
                ];
            }
        }

        $cambios = json_encode($diferencias);
        $session = \Config\Services::session();
    
        // Inserta en la tabla Auditoria
        $fecha = date('Y-m-d H:i:s');
        $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
        $auditoriaData = [
            'TablaAfectada' => 'UsuariosRoles',
            'Accion' => 'UPDATE',
            'CambiosRealizados' => $cambios,
            'FechaRegistro' =>$fecha,
            'IdUsuario' => $session->get('usu_id'),
        ];
    
        $auditoriaBuilder = $this->db->table('Auditoria');
        $auditoriaBuilder->insert($auditoriaData);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Metodo para agregar un rol a un usuario en especifico.
     * 
     * @param int $id_usuario Identificador del usuario.
     * @param int $id_rol Identificador del rol.
     * @return bool True si se agrego el rol, de lo contrario false.
     */
    public function addUserRole($id_usuario, $id_rol): bool
    {
        $builder = $this->db->table('UsuariosRoles');
        $data = [
            'IdUsuario' => $id_usuario,
            'IdRol' => $id_rol,
        ];
        $builder->insert($data);

        return $this->db->affectedRows() > 0;
    }

    /** Obtener tabs permitidos  */
    public function obtenerTabsPermitidos($idRol)
    {
        $builder = $this->db->table('ModulosPermisosTabs mpt');
        $builder->join('ModulosTabs mt', 'mpt.IdTab = mt.Id');
        $builder->select('mt.Nombre');        
        $builder->where('mpt.IdRol', $idRol);
        
        return $builder->get()->getResultArray();
    }

    /** Obtener acciones permitidas */
    public function obtenerAccionesPermitidas($idRol)
    {
        $builder = $this->db->table('ModulosPermisosAcciones mpa');
        $builder->join('ModulosAcciones ma', 'mpa.IdAccion = ma.Id');
        $builder->select('ma.Nombre');
        $builder->where('mpa.IdRol', $idRol);

        $result = $builder->get()->getResultArray();
        return array_column($result, 'Nombre');
    }
}
