<?php

namespace App\Models;

use CodeIgniter\Model;

class LosetasModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    /**
     * Método para obtener todos los módulos
     */
    public function getAllModules($data=null){
        $builder = $this->db->table('Modulos m');
        $builder->select('*');

        if(!empty($data)){
            foreach ($data as $key => $condition) {
                if (is_string($condition) && is_numeric($key)) {
                    $builder->where($condition, null, false);
                } elseif (is_array($condition)) {
                    // Si es un array, se espera ['campo', 'operador', 'valor']
                    [$field, $operator, $value] = $condition;
                    if (strtoupper($operator) === 'IN' || strtoupper($operator) === 'NOT IN') {
                        $builder->whereIn($field, $value); 
                    } elseif (strtoupper($operator) === 'IS NULL' || strtoupper($operator) === 'IS NOT NULL') {
                        $builder->where("$field $operator", null, false);
                    } else {
                        $builder->where("$field $operator", $value);
                    }
                }
            }
        }

        $query = $builder->get();
        
        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los modulos con el nombre de su loseta padre
     * y de su modulo padre.
     *
     * @return array Listado de modulos, de lo contrario [].
     */
    public function getAllModulesWithParents(): array
    {
        $builder = $this->db->table('Modulos m');
        $builder->select('m.*, lp.Nombre as LosetaPadre, mp.Nombre as ModuloPadre');
        $builder->join('Modulos lp', 'm.IdLoseta=lp.Id', 'left'); // Loseta Padre
        $builder->join('Modulos mp', 'm.IdModulo = mp.Id', 'left'); // Modulo Padre
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener un modulo en especifico.
     * Se consulta por su "id" en base de datos.
     * 
     * @param int $id Identificador del modulo.
     * @return array Datos del modulo.
     */
    public function getModuleById($id): array
    {
        $builder = $this->db->table('Modulos m');
        $builder->select('m.*');
        $builder->where('m.Id', $id);
        $query = $builder->get();

        return $query->getRowArray();
    }

    /**
     * Metodo para obtener los modulos (losetas) a las que tiene acceso el usuario.
     * 
     * @param string $codigo_usuario Identificador del usuario. Viene de la sesion "usu_id".
     * @return array $losetas Modulos a los que tiene acceso el usuario.
     */
    public function getLosetasByUsuario($codigo_usuario): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->distinct();
        $builder->select('m.Id, m.Nombre as Nombre, m.Icono as Icono, m.Ruta AS Ruta');
        $builder->join('UsuariosRoles us', 'us.IdUsuario = u.Id');
        $builder->join('ModulosRoles mr', 'mr.IdRol = us.IdRol');
        $builder->join('Modulos m', 'm.Id = mr.IdModulo AND m.IdLoseta IS NULL');
        $builder->where('u.Id', $codigo_usuario);
        $builder->where('us.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener los modulos por loseta, pero teniendo en cuenta los
     * permisos de un usuario en especifico.
     * 
     * @param string $codigo_usuario Identificador del usuario. Viene de la sesion "usu_id".
     * @param string $id_loseta Identificador de la loseta a la que pertenece el modulo.
     * @return array $losetas Modulos a los que tiene acceso el usuario.
     */
    public function getModuloByUsuarioRolAndLoseta($codigo_usuario, $id_loseta): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->distinct();
        $builder->select('m.Id as IdModulo, m.Nombre as Nombre, m.Icono as Icono, m.Ruta AS Ruta');
        $builder->join('UsuariosRoles us', 'us.IdUsuario = u.Id');
        $builder->join('ModulosRoles mr', 'mr.IdRol = us.IdRol');
        $builder->join('Modulos m', 'm.Id = mr.IdModulo');
        $builder->where('m.IdLoseta', $id_loseta);
        $builder->where('u.Id', $codigo_usuario);
        $builder->where('m.FechaFinalizacion', null);
        $builder->where('mr.FechaFinalizacion', null);
        $builder->where('m.IdModulo', null);
        $builder->where('us.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener los submodulos por loseta, pero teniendo en cuenta los
     * permisos de un usuario en especifico.
     * 
     * @param string $codigo_usuario Identificador del usuario. Viene de la sesion "usu_id".
     * @param string $id_loseta Identificador de la loseta a la que pertenece el modulo.
     * @return array $losetas submodulos a los que tiene acceso el usuario.
     */
    public function getSubModuloByUsuarioRolAndLoseta($codigo_usuario, $id_loseta, $id_modulo): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->distinct();
        $builder->select('m.IdModulo AS IdModuloPadre, m.Nombre as Nombre, m.Icono as Icono, m.Ruta AS Ruta');
        $builder->join('UsuariosRoles us', 'us.IdUsuario = u.Id');
        $builder->join('ModulosRoles mr', 'mr.IdRol = us.IdRol');
        $builder->join('Modulos m', 'm.Id = mr.IdModulo');
        $builder->where('m.IdLoseta', $id_loseta);
        $builder->where('u.Id', $codigo_usuario);
        $builder->where('m.IdModulo', $id_modulo);
        $builder->where('m.FechaFinalizacion', null);
        $builder->where('mr.FechaFinalizacion', null);
        $builder->where('us.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener el nombre de una loseta/modulo/submodulo.
     * 
     * @param string $id Identificador de la loseta/modulos/submodulo.
     * @return array $losetas Informacion de la loseta/modulo/submodulo.
     */
    public function getNombreLosetaById($id)
    {
        $builder = $this->db->table('Modulos m');
        $builder->select('m.*');
        $builder->where('m.Id', $id);
        $query = $builder->get();

        $result = $query->getRowArray();
        return $result['Nombre'] ?? null;
    }

    /**
     * Metodo para obtener todos los modulos disponibles que no
     * tiene asignado el rol.
     * 
     * @param $id_rol Identificador del rol.
     * @return array Listado de modulos, de lo contrario [].
     */
    public function getModulesNotRol($id_rol): array
    {
        $subquery = $this->db->table('ModulosRoles mr')
            ->select('mr.IdModulo')
            ->where('mr.IdRol', $id_rol)
            ->where('mr.FechaFinalizacion', null)
            ->getCompiledSelect();

        $builder = $this->db->table('Modulos m');
        $builder->select('m.*, lp.Nombre as LosetaPadre, mp.Nombre as ModuloPadre');
        $builder->join('Modulos lp', 'm.IdLoseta=lp.Id', 'left'); // Loseta Padre
        $builder->join('Modulos mp', 'm.IdModulo = mp.Id', 'left'); // Módulo Padre
        $builder->where('m.FechaFinalizacion', null);
        $builder->where("m.Id NOT IN ($subquery)", null, false);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los modulos de un rol en especifico.
     * 
     * @param int id_rol Identificador del rol.
     * @return array Listado de modulos, de lo contrario [].
     */
    public function getModulesByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosRoles mr');
        $builder->select('m.*, lp.Nombre as LosetaPadre, mp.Nombre as ModuloPadre');
        $builder->join('Modulos m', 'm.Id = mr.IdModulo');
        $builder->join('Modulos lp', 'm.IdLoseta=lp.Id', 'left'); // Loseta Padre
        $builder->join('Modulos mp', 'm.IdModulo = mp.Id', 'left'); // Módulo Padre
        $builder->where('mr.IdRol', $id_rol);
        $builder->where('mr.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para obtener todos los modulos de un rol en especifico
     * asi se le haya quitado o establecido la fecha de finalizacion en NULL.
     * 
     * @param int id_rol Identificador del rol.
     * @return array Listado de modulos, de lo contrario [].
     */
    public function getAllModulesByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosRoles mr');
        $builder->select('mr.*');
        $builder->join('Modulos r', 'r.Id = mr.IdModulo');
        $builder->where('mr.IdRol', $id_rol);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para eliminar un modulo de un rol en especifico.
     * Se establece la fecha de finalizacion del modulo en la tabla correspodiente.
     * 
     * @param int $id_rol Identificador del rol.
     * @param int $id_modulo Identificador del modulo.
     * @param string $fecha Fecha de finalizacion del modulo.
     * @return bool True si se elimino el modulo, de lo contrario false.
     */
    public function deleteRolModule($id_rol, $id_modulo, $fecha): bool
    {
        $builder = $this->db->table('ModulosRoles');
        $builder->set('FechaFinalizacion', $fecha);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdModulo', $id_modulo);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }


    /**
     * Metodo para actualizar un modulo desactivo a un rol en especifico.
     * Se establece la fecha de finalizacion del modulo en NULL.
     * 
     * @param int $id_ Identificador del rol.
     * @param int $id_modulo Identificador del modulo.
     * @return bool True si se elimino el modulo, de lo contrario false.
     */
    public function updateRolModule($id_rol, $id_modulo): bool
    {
        $builder = $this->db->table('ModulosRoles');
        $builder->set('FechaFinalizacion', null);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdModulo', $id_modulo);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Metodo para agregar un modulo a un rol en especifico.
     * 
     * @param int $id_rol Identificador del rol.
     * @param int $id_modulo Identificador del modulo.
     * @return bool True si se agrego el modulo, de lo contrario false.
     */
    public function addRolModule($id_rol, $id_modulo): bool
    {
        $builder = $this->db->table('ModulosRoles');
        $data = [
            'IdRol' => $id_rol,
            'IdModulo' => $id_modulo,
        ];
        $builder->insert($data);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Metodo para crear un modulo nuevo en base de datos.
     * 
     * @param array $data Datos del modulo a crear.
     * @return bool True si se creo el modulo, de lo contrario False.
     */
    public function createModule($data): bool
    {
        $builder = $this->db->table('Modulos');
        $result = $builder->insert($data);

        return $result !== false;
    }

    /**
     * Metodo para actualizar un modulo.
     * Se actualizan todos los datos del modulo que viene en array $data.
     * 
     * @param int $id_modulo Identificador del modulo a actualizar.
     * @param array $data Datos del modulo a actualizar.
     * @return void
     */
    public function updateModule($id, $data)
    {
        $builder = $this->db->table('Modulos');
        $builder->where('Id', $id);
        $builder->update($data);
    }

    /** Método para visualizar las acciones de los módulos */
    public function getAcciones(): array
    {
        $builder = $this->db->table('ModulosAcciones ma');
        $builder->join('Modulos m', 'ma.IdModulo = m.Id');
        $builder->select('ma.Id, ma.Nombre, m.Nombre as Modulo, ma.FechaInicio, ma.FechaFinalizacion');
        $query = $builder->get();
        
        return $query->getResultArray();
    }

    /** Método para visualizar las acciones de los módulos */
    public function getAccionById($id): array
    {
        $builder = $this->db->table('ModulosAcciones ma');
        $builder->select('ma.*');
        $builder->where('ma.Id', $id);
        $query = $builder->get();
        
        return $query->getRowArray();
    }

    /**
     * Metodo para crear una accion nuevo en base de datos.
     * 
     * @param array $data Datos de la accion a crear.
     * @return bool True si se creo la accion, de lo contrario False.
     */
    public function createAccion($data): bool
    {
        $builder = $this->db->table('ModulosAcciones');
        $result = $builder->insert($data);

        /**Insertar registro para auditoria */
        if ($result) {
            $session = \Config\Services::session();
        
            // Inserta en la tabla Auditoria
            $fecha = date('Y-m-d H:i:s');
            $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
            $auditoriaData = [
                'TablaAfectada' => 'ModulosAcciones',
                'Accion' => 'INSERT',
                'CambiosRealizados' => $data,
                'FechaRegistro' =>$fecha,
                'IdUsuario' => $session->get('usu_id'),
            ];
        
            $auditoriaBuilder = $this->db->table('Auditoria');
            $auditoriaBuilder->insert($auditoriaData);
        }

        return $result !== false;
    }


    /**
     * Metodo para actualizar un usuario.
     * Se actualizan todos los datos del usuario que viene en array $data.
     * 
     * @param int $Id Identificador del usuario a actualizar.
     * @param array $data Datos del usuario a actualizar.
     * @return void
     */
    public function updateAccion($Id, $data)
    {
        $anterior = $this->getAccionById($Id);

        $builder = $this->db->table('ModulosAcciones');
        $builder->where('Id', $Id);
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
                'TablaAfectada' => 'ModulosAcciones',
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
     * Metodo para obtener todas las acciones disponibles que no
     * tiene asignado el rol.
     * 
     * @param $id_rol Identificador del rol.
     * @return array Listado de acciones, de lo contrario [].
     */
    public function getAccionesNotRol($id_rol): array
    {
        // Subquery que obtiene los IdAccion asociados al rol
        $subquery = $this->db->table('ModulosPermisosAcciones mpa')
            ->select('mpa.IdAccion')
            ->where('mpa.IdRol', $id_rol)
            ->where('mpa.FechaFinalizacion', null);

        $builder = $this->db->table('ModulosAcciones ma');
        $builder->select('ma.*');
        $builder->where('ma.FechaFinalizacion', null);
        $builder->whereNotIn('ma.Id', $subquery);
        $query = $builder->get();

        return $query->getResultArray();
    }


    /**
     * Metodo para obtener todos las acciones de un rol en especifico.
     * 
     * @param int id_rol Identificador del rol.
     * @return array Listado de acciones, de lo contrario [].
     */
    public function getAccionesByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosAcciones ma');
        $builder->select('mpa.IdRol, ma.Id as IdAccion, ma.Nombre as NombreAccion');
        $builder->join('ModulosPermisosAcciones mpa', 'mpa.IdAccion = ma.Id');
        $builder->where('mpa.IdRol', $id_rol);
        $builder->where('ma.FechaFinalizacion', null);
        $builder->where('mpa.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }


    /**
     * Metodo para eliminar una acción de un rol en especifico.
     * Se establece la fecha de finalizacion de la acción en la tabla correspodiente.
     * 
     * @param int $id_rol Identificador del rol.
     * @param int $id_accion Identificador de la acción.
     * @param string $fecha Fecha de finalizacion de la acción.
     * @return bool True si se elimino la acción, de lo contrario false.
     */
    public function deleteAccionRol($id_accion, $id_rol, $fecha): bool
    {
        $builder = $this->db->table('ModulosPermisosAcciones');
        $builder->set('FechaFinalizacion', $fecha);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdAccion', $id_accion);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }


    /**
     * Metodo para obtener todos los modulos de un rol en especifico
     * asi se le haya quitado o establecido la fecha de finalizacion en NULL.
     * 
     * @param int id_rol Identificador del rol.
     * @return array Listado de modulos, de lo contrario [].
     */
    public function getAllAccionesByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosPermisosAcciones mpa');
        $builder->select('mpa.*');
        $builder->join('ModulosAcciones ma', 'ma.Id = mpa.IdAccion');
        $builder->where('mpa.IdRol', $id_rol);
        $query = $builder->get();

        return $query->getResultArray();
    }

    /**
     * Metodo para actualizar un modulo desactivo a un rol en especifico.
     * Se establece la fecha de finalizacion del modulo en NULL.
     * 
     * @param int $id_ Identificador del rol.
     * @param int $id_modulo Identificador del modulo.
     * @return bool True si se elimino el modulo, de lo contrario false.
     */
    public function updateRolAccion($id_rol, $id_accion): bool
    {
        $builder = $this->db->table('ModulosPermisosAcciones');
        $builder->set('FechaFinalizacion', null);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdAccion', $id_accion);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Metodo para agregar un modulo a un rol en especifico.
     * 
     * @param int $id_rol Identificador del rol.
     * @param int $id_modulo Identificador del modulo.
     * @return bool True si se agrego el modulo, de lo contrario false.
     */
    public function addRolAccion($id_rol, $id_accion, $usuario): bool
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $builder = $this->db->table('ModulosPermisosAcciones');
        $data = [
            'IdRol' => $id_rol,
            'IdAccion' => $id_accion,
            'IdUsuario' => $usuario,
            'FechaInicio' => $fecha
        ];
        $builder->insert($data);

        return $this->db->affectedRows() > 0;
    }


    /* CONSULTAS DE LA ADMINISTRACIÓN DE PESTAÑAS */

    public function getTabs(): array
    {
        $builder = $this->db->table('ModulosTabs ma');
        $builder->join('Modulos m', 'ma.IdModulo = m.Id');
        $builder->select('ma.Id, ma.Nombre, m.Nombre as Modulo, ma.FechaInicio, ma.FechaFinalizacion');
        $query = $builder->get();
        
        return $query->getResultArray();
    }


    public function getTabById($id): array
    {
        $builder = $this->db->table('ModulosTabs ma');
        $builder->select('ma.*');
        $builder->where('ma.Id', $id);
        $query = $builder->get();
        
        return $query->getRowArray();
    }


    public function createTab($data): bool
    {
        $builder = $this->db->table('ModulosTabs');
        $result = $builder->insert($data);

        /**Insertar registro para auditoria */
        if ($result) {
            $session = \Config\Services::session();
        
            // Inserta en la tabla Auditoria
            $fecha = date('Y-m-d H:i:s');
            $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
            $auditoriaData = [
                'TablaAfectada' => 'ModulosTabs',
                'Accion' => 'INSERT',
                'CambiosRealizados' => $data,
                'FechaRegistro' =>$fecha,
                'IdUsuario' => $session->get('usu_id'),
            ];
        
            $auditoriaBuilder = $this->db->table('Auditoria');
            $auditoriaBuilder->insert($auditoriaData);
        }

        return $result !== false;
    }


    public function updateTab($Id, $data)
    {
        $anterior = $this->getTabById($Id);

        $builder = $this->db->table('ModulosTabs');
        $builder->where('Id', $Id);
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
                'TablaAfectada' => 'ModulosTabs',
                'Accion' => 'UPDATE',
                'CambiosRealizados' => $cambios,
                'FechaRegistro' =>$fecha,
                'IdUsuario' => $session->get('usu_id'),
            ];
        
            $auditoriaBuilder = $this->db->table('Auditoria');
            $auditoriaBuilder->insert($auditoriaData);
        }
    }

    public function getTabsNotRol($id_rol): array
    {
        $subquery = $this->db->table('ModulosPermisosTabs mpt')
            ->select('mpt.IdTab')
            ->where('mpt.IdRol', $id_rol)
            ->where('mpt.FechaFinalizacion', null);

        $builder = $this->db->table('ModulosTabs mt');
        $builder->select('mt.*');
        $builder->where('mt.FechaFinalizacion', null);
        $builder->whereNotIn('mt.Id', $subquery);
        $query = $builder->get();

        return $query->getResultArray();
    }


    public function getTabsByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosTabs mt');
        $builder->select('mpt.IdRol, mt.Id as IdTab, mt.Nombre as NombreTab');
        $builder->join('ModulosPermisosTabs mpt', 'mpt.IdTab = mt.Id');
        $builder->where('mpt.IdRol', $id_rol);
        $builder->where('mt.FechaFinalizacion', null);
        $builder->where('mpt.FechaFinalizacion', null);
        $query = $builder->get();

        return $query->getResultArray();
    }


    public function deleteTabRol($id_tab, $id_rol, $fecha): bool
    {
        $builder = $this->db->table('ModulosPermisosTabs');
        $builder->set('FechaFinalizacion', $fecha);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdTab', $id_tab);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }


    public function getAllTabsByRol($id_rol): array
    {
        $builder = $this->db->table('ModulosPermisosTabs mpt');
        $builder->select('mpt.*');
        $builder->join('ModulosTabs mt', 'mt.Id = mpt.IdTab');
        $builder->where('mpt.IdRol', $id_rol);
        $query = $builder->get();

        return $query->getResultArray();
    }


    public function updateRolTab($id_rol, $id_tab): bool
    {
        $builder = $this->db->table('ModulosPermisosTabs');
        $builder->set('FechaFinalizacion', null);
        $builder->where('IdRol', $id_rol);
        $builder->where('IdTab', $id_tab);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }


    public function addRolTab($id_rol, $id_tab, $usuario): bool
    {
        $date = date('Y-m-d H:i:s');
        $fecha = explode(' ', $date)[0] . 'T' . explode(' ', $date)[1];

        $builder = $this->db->table('ModulosPermisosTabs');
        $data = [
            'IdRol' => $id_rol,
            'IdTab' => $id_tab,
            'IdUsuario' => $usuario,
            'FechaInicio' => $fecha
        ];
        $builder->insert($data);

        return $this->db->affectedRows() > 0;
    }



}
