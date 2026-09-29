<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuariosModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['password'];

    /**
     * Metodo para validar si el usuario existe en la base de datos.
     * Se consulta por "Usuario" y "Password" (Encriptada con md5).
     * 
     * @param string $username
     * @param string $password
     * @return array Datos del usuario si existe, de lo contrario [].
     */
    public function verifyUser($username, $password): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->where('Usuario', $username);
        $query = $builder->get();

        $user = $query->getRowArray();

        if ($user && password_verify($password, $user('Contrasena'))) {
            return $user;
        }

        return [];
    }

    /**
     * Método para buscar por el nombre de usuario
     */
    public function getUsuarioPorUsername($username): ?array
    {
        $builder = $this->db->table('Usuarios');
        $builder->where('Usuario', $username);
        $query = $builder->get();

        return $query->getRowArray() ?: null;
    }

    /**
     * Incrementar intentos fallidos
    */ 
    public function incrementarIntentos($idUsuario)
    {
        $builder = $this->db->table('Usuarios');
        $builder->set('IntentosFallidos', 'IntentosFallidos + 1', false);
        $builder->where('Id', $idUsuario);
        $builder->update();
    }

    /**
     * Bloquear / Inactivar usuario
     */
    public function bloquearUsuario($idUsuario)
    {
        $builder = $this->db->table('Usuarios');
        $builder->set('FechaFinalizacion', date('Y-m-d H:i:s'));
        $builder->where('Id', $idUsuario);
        $builder->update();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Método para reinicar el contador de intentos
     */
    public function reiniciarIntentos($idUsuario)
    {
        $builder = $this->db->table('Usuarios');
        $builder->set('IntentosFallidos', 0);
        $builder->where('Id', $idUsuario);
        $builder->update();
    }


    /**
     * Metodo para insertar el token en la base.
     */
    public function insertResetPassword($Id, $resetToken, $tokenExpiry)
    {
        $data = [
            'IdUsuario' => $Id,
            'ResetToken' => $resetToken,
            'TokenExpiry' => $tokenExpiry,
        ];

        $this->db->table('ResetsPasswords')->insert($data);
    }


    /**
     * Metodo para verificar que el token exista en la base.
     */
    public function verifyResetToken($resetToken)
    {
        $builder = $this->db->table('ResetsPasswords');
        $builder->where('ResetToken', $resetToken);
        // Verificar si el token ha expirado
        $builder->where('TokenExpiry >=', (new \DateTime())->format('Y-m-d\TH:i:s')); // Compara con la fecha actual
        $query = $builder->get();

        // Si el token es válido y no ha expirado, retorna los datos
        if ($query->getNumRows() > 0) {
            return $query->getRowArray(); // Devuelve el IdUsuario, etc.
        }

        // Si el token ha caducado o no es válido, retorna null
        return null;
    }

    
    /**
     * Metodo para actualizar la contraseña.
     */
    public function updatePassword($userId, $claveEncriptada)
    {
        // $nuevaClave = md5($claveEncriptada);
        $nuevaClave = password_hash($claveEncriptada, PASSWORD_ARGON2ID);

        $builder = $this->db->table('Usuarios');
        $builder->set('Contrasena', $nuevaClave);
        $builder->set('ContrasenaSegura', 1);
        $builder->where('Id', $userId);
        $builder->update();

        return true;
    }


    /**
     * Metodo para eliminar el token de la base ResetsPasswords.
     */
    public function deleteToken($resetToken)
    {
        $builder = $this->db->table('ResetsPasswords');
        $builder->where('ResetToken', $resetToken);
        $builder->delete();
        
        return $this->db->affectedRows() > 0;
    }


    /**
     * Metodo para validar si el correo existe en la base de datos.
     * 
     * @param string $email
     */
    public function verifyEmail($email)
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->where('Email', $email);
        $query = $builder->get();

        return $query->getRowArray();
    }


    /**
     * Metodo para obtener todos los usuarios.
     */
    public function getUsers()
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $query = $builder->get();

        return $query->getResultArray();
    }


    /**
     * Metodo para obtener todos los usuarios que se encuentran activos.
     */
    public function getActiveUsers(): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->where('FechaFinalizacion IS NULL');
        $builder->orderBy('u.Nombre', 'ASC');
        $query = $builder->get();

        return $query->getResultArray();
    }


    /**
     * Metodo para obtener usuarios con sus roles
     */
    public function getUsersWithRoles(): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.Id, u.Nombre, u.Apellido, u.Usuario, r.Nombre as Rol');
        $builder->join('UsuariosRoles ur', 'u.Id = ur.IdUsuario');
        $builder->join('Roles r', 'ur.IdRol = r.Id');
        $builder->where('u.FechaFinalizacion IS NULL');
        $builder->orderBy('u.Id', 'ASC');
        $query = $builder->get();

        return $query->getResultArray();
    }


    /**
     * Metodo para obtener un usuario en especifico.
     * Se consulta por su "id" en base de datos.
     * 
     * @param int $id Identificador del usuario.
     * @return array Datos del usuario.
     */
    public function getUserById($id): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->where('u.Id', $id);
        $query = $builder->get();

        return $query->getRowArray();
    }


    /**
     * Metodo para actualizar un usuario.
     * Se actualizan todos los datos del usuario que viene en array $data.
     * 
     * @param int $Id Identificador del usuario a actualizar.
     * @param array $data Datos del usuario a actualizar.
     * @return void
     */
    public function updateUser($Id, $data)
    {
        $anterior = $this->getUserById($Id);

        $builder = $this->db->table('Usuarios');
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
                'TablaAfectada' => 'Usuarios',
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
     * Metodo para buscar un usuario por su nombre.
     * 
     * @param string $term Nombre del usuario a buscar.
     * @return array Resultados de la busqueda.
     */
    public function findUserByName($term): array
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->like('Nombre', $term);
        $query = $builder->get();

        return $query->getResultArray();
    }

    
    /**
     * Metodo para crear un usuario nuevo en base de datos.
     * 
     * @param array $data Datos del usuario a crear.
     * @return bool True si se creo el usuario, de lo contrario False.
     */
    public function createUser($data): bool
    {
        $builder = $this->db->table('Usuarios');
        $result = $builder->insert($data);

        /**Insertar registro para auditoria */
        if ($result) {
            $session = session();

            $idInsertado = $this->db->insertID();
            $usuario = $this->getUserById($idInsertado);

            $jsonCambios = json_encode($usuario, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
            // Inserta en la tabla Auditoria
            $fecha = date('Y-m-d H:i:s');
            $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
            $auditoriaData = [
                'TablaAfectada' => 'Usuarios',
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
     * Metodo para validar si el nombre de usuario existe en la base de datos.
     * 
     * @param string $usuario
     */
    public function verifyNameUser($usuario)
    {
        $builder = $this->db->table('Usuarios u');
        $builder->select('u.*');
        $builder->where('Usuario', $usuario);
        $query = $builder->get();

        return $query->getRowArray();
    }


    public function actualizarFotoPerfil($idUsuario, $rutaImagen)
    {

        $builder = $this->db->table('Usuarios');
        $builder->where('Id', $idUsuario);
        $builder->update(['RutaImagen' => $rutaImagen]);

        $session = session();
        
        // Inserta en la tabla Auditoria
        $fecha = date('Y-m-d H:i:s');
        $fecha = explode(' ', $fecha)[0].'T'.explode(' ', $fecha)[1];
        $auditoriaData = [
            'TablaAfectada' => 'Usuarios',
            'Accion' => 'UPDATE',
            'CambiosRealizados' => json_encode(['RutaImagen' => $rutaImagen], JSON_UNESCAPED_UNICODE),
            'FechaRegistro' =>$fecha,
            'IdUsuario' => $session->get('usu_id'),
        ];
        $auditoriaBuilder = $this->db->table('Auditoria');
        $auditoriaBuilder->insert($auditoriaData);

    }

    /** Obtener el rol del usuario en sesion */
    public function obtenerRolUsuario($usuarioId)
    {
        $builder = $this->db->table('UsuariosRoles ur');
        $builder->select('r.Id, r.Nombre');
        $builder->join('Roles r', 'ur.IdRol = r.Id');
        $builder->where('ur.IdUsuario', $usuarioId);
        $builder->where('ur.FechaFinalizacion IS NULL');
        $query = $builder->get();

        $resultado = $query->getRowArray();
        
        return $resultado;
    }

    /** Insertar en tabla de auditoria */
    public function registrarInicioSesion($data) 
    {
        $this->db->table('AuditoriaSesiones')
                ->insert($data);
        return $this->db->insertID();
    }

    public function registrarCierreSesion($idAuditoria, $data) 
    {
        $this->db->table('AuditoriaSesiones')
                ->where('Id', $idAuditoria)
                ->update($data);
    }

}
