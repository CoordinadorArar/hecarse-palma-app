<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentosModel extends Model
{
    protected $table            = 'Documentos';
    protected $primaryKey       = 'Id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'Codigo',
        'IdArea',
        'IdTipo',
        'Nombre',
        'Descripcion',
        'NumeroPaginas',
        'IdUsuarioCreacion',
        'IdUsuarioRevision',
        'IdUsuarioAprobacion',
        'FechaAprobacion',
        'Version',
        'DescripcionCambios',
        'Ruta',
        'IdUsuario',
        'FechaFinalizacion',
        'FechaModificacion',
    ];


    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    /**
     * Genera una fecha/hora en formato "Y-m-dTH:i:s" para los campos de auditoría
     * y control de versiones. Se usa este formato en lugar del estándar de SQL
     * para compatibilidad con el campo FechaFinalizacion de la BD.
     */
    private function ahora(): string
    {
        $ahora = date('Y-m-d H:i:s');
        return explode(' ', $ahora)[0] . 'T' . explode(' ', $ahora)[1];
    }

    /**
     * Inserta un registro en la tabla de auditoría con los cambios realizados.
     *
     * @param string $tabla   Nombre de la tabla afectada.
     * @param string $accion  Tipo de operación: 'INSERT' | 'UPDATE' | 'DELETE'.
     * @param array  $cambios Datos modificados que se serializan como JSON.
     * @param int    $usu_id  Id del usuario que realizó la operación.
     */
    private function insertarAuditoria(string $tabla, string $accion, array $cambios, int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => $tabla,
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => $this->ahora(),
        ]);
    }


    // =========================================================================
    // CONSULTAS — LECTURA
    // =========================================================================

    /**
     * Obtiene el siguiente consecutivo disponible para generar el código de un
     * nuevo documento (ej: GH-MAN-42). Se basa en el MAX(Id) actual + 1.
     */
    public function getUltimoId(): int
    {
        $row = $this->db->table('Documentos')
            ->selectMax('Id')
            ->get()->getRowArray();

        return (int)($row['Id'] ?? 0) + 1;
    }

    /**
     * Obtiene el listado de documentos mostrando únicamente la última versión
     * de cada código (activa si existe, o la más reciente si todas están inactivas).
     *
     * La subquery "ultima" usa COALESCE para priorizar la versión con
     * FechaFinalizacion = NULL (activa); si no hay ninguna, toma la de mayor Id.
     *
     * @param int|null    $areaId  Filtro por área.
     * @param int|null    $tipoId  Filtro por tipo de documento.
     * @param string|null $search  Búsqueda por nombre o código.
     * @param bool        $verTodos Si es false, filtra solo los documentos asignados al usuario (por usuario o por cargo).
     * @param int         $usu_id  Id del usuario en sesión.
     */
    public function getAll($areaId = null, $tipoId = null, $search = null, bool $verTodos = false, int $usu_id = 0): array
    {
        $whereFiltros = '1=1';
        $params       = [];

        // --- Filtros opcionales ---
        if (!empty($areaId)) {
            $whereFiltros .= ' AND D.IdArea = ?';
            $params[]      = $areaId;
        }
        if (!empty($tipoId)) {
            $whereFiltros .= ' AND D.IdTipo = ?';
            $params[]      = $tipoId;
        }
        if (!empty($search)) {
            $whereFiltros .= ' AND (D.Nombre LIKE ? OR D.Codigo LIKE ?)';
            $params[]      = '%' . $search . '%';
            $params[]      = '%' . $search . '%';
        }

        // --- Filtro de acceso: solo documentos donde el usuario esté asignado
        //     directamente O su cargo esté asignado al documento ---
        if (!$verTodos) {
            $whereFiltros .= "
                AND (
                    EXISTS (
                        SELECT 1 FROM DocumentosUsuarios DU
                        WHERE DU.Codigo = D.Codigo AND DU.IdUsuarioAsignado = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM DocumentosCargos DC
                        INNER JOIN Usuarios U ON U.IdCargo = DC.IdCargo
                        WHERE DC.Codigo = D.Codigo AND U.Id = ?
                    )
                )
            ";
            $params[] = $usu_id;
            $params[] = $usu_id;
        }

        // La subquery agrupa por Codigo y selecciona el Id de la versión a mostrar:
        // prioriza la activa (FechaFinalizacion IS NULL), si no hay activa toma la más reciente.
        $sql = "
            SELECT D.*
            FROM Documentos D
            INNER JOIN (
                SELECT
                    Codigo,
                    COALESCE(
                        MAX(CASE WHEN FechaFinalizacion IS NULL THEN Id END),
                        MAX(Id)
                    ) AS target_id
                FROM Documentos
                GROUP BY Codigo
            ) ultima ON D.Id = ultima.target_id
            WHERE {$whereFiltros}
            ORDER BY D.Id DESC
        ";

        return $this->db->query($sql, $params)->getResultArray();
    }

    /**
     * Obtiene un documento por su Id con todos los joins necesarios para la
     * vista de detalles: área, tipo, y datos completos (nombre + cargo) de los
     * usuarios de elaboración, revisión y aprobación.
     */
    public function getById(int $id): array|null
    {
        $row = $this->db->table('Documentos D')
            ->select('D.*')
            ->select('A.Nombre AS NombreArea',                                false)
            ->select('T.Nombre AS NombreTipo',                                false)
            ->select("UE.Nombre + ' ' + UE.Apellido AS Elaboro",              false)
            ->select('CE.Nombre AS CargoElabora',                             false)
            ->select("UR.Nombre + ' ' + UR.Apellido AS Reviso",               false)
            ->select('CR.Nombre AS CargoRevisa',                              false)
            ->select("UA.Nombre + ' ' + UA.Apellido AS Aprobo",               false)
            ->select('CA.Nombre AS CargoAproba',                              false)
            ->join('Areas A',           'D.IdArea = A.Id',               'left')
            ->join('TiposDocumentos T', 'D.IdTipo = T.Id',               'left')
            ->join('Usuarios UE',       'D.IdUsuarioCreacion = UE.Id',   'left')
            ->join('Cargos CE',         'UE.IdCargo = CE.Id',            'left')
            ->join('Usuarios UR',       'D.IdUsuarioRevision = UR.Id',   'left')
            ->join('Cargos CR',         'UR.IdCargo = CR.Id',            'left')
            ->join('Usuarios UA',       'D.IdUsuarioAprobacion = UA.Id', 'left')
            ->join('Cargos CA',         'UA.IdCargo = CA.Id',            'left')
            ->where('D.Id', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Obtiene el historial completo de versiones de un documento por su código,
     * ordenadas de la más reciente a la más antigua.
     */
    public function getVersiones(string $codigo): array
    {
        return $this->db->table('Documentos D')
            ->select('D.Id, D.Version, D.Nombre, D.Descripcion, D.DescripcionCambios, D.Ruta, D.FechaInicio, D.FechaFinalizacion', false)
            ->select("CONCAT_WS(' ', U.Nombre, U.Apellido) AS Elaboro", false)
            ->join('Usuarios U', 'D.IdUsuarioCreacion = U.Id', 'left')
            ->where('D.Codigo', $codigo)
            ->orderBy('D.Version', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Obtiene el número de la última versión registrada para un código dado.
     * Retorna 0 si aún no existe ninguna versión.
     */
    public function obtenerUltimaVersion(string $codigo): int
    {
        $row = $this->db->table('Documentos')
            ->selectMax('Version', 'max_version')
            ->where('Codigo', $codigo)
            ->get()->getRowArray();

        return (int)($row['max_version'] ?? 0);
    }


    // =========================================================================
    // OPERACIONES DE ESCRITURA — DOCUMENTOS
    // =========================================================================

    /**
     * Crea el primer registro de un nuevo documento (Versión 1).
     *
     * @param array  $data    Datos del formulario.
     * @param string $ruta    Ruta relativa del archivo subido.
     * @param string $codigo  Código generado (AbrevArea-AbrevTipo-Consecutivo).
     * @param int    $usu_id  Id del usuario en sesión.
     * @return int|false Id insertado o false en caso de error.
     */
    public function crear(array $data, string $ruta, string $codigo, int $usu_id): int|false
    {
        $insert = [
            'Codigo'              => $codigo,
            'IdArea'              => (int)$data['IdArea'],
            'IdTipo'              => (int)$data['IdTipo'],
            'Nombre'              => $data['Nombre'],
            'Descripcion'         => $data['Descripcion'] ?? null,
            'NumeroPaginas'       => (int)$data['NumeroPaginas'],
            'IdUsuarioCreacion'   => (int)$data['IdUsuarioCreacion'],
            'IdUsuarioRevision'   => !empty($data['IdUsuarioRevision'])   ? (int)$data['IdUsuarioRevision']   : null,
            'IdUsuarioAprobacion' => !empty($data['IdUsuarioAprobacion']) ? (int)$data['IdUsuarioAprobacion'] : null,
            'FechaAprobacion'     => !empty($data['FechaAprobacion'])     ? $data['FechaAprobacion']          : null,
            'Version'             => 1,
            'DescripcionCambios'  => null,
            'Ruta'                => $ruta,
            'IdUsuario'           => $usu_id,
        ];

        $ok = $this->insert($insert);
        if (!$ok) return false;

        $id = $this->getInsertID();
        $this->insertarAuditoria('Documentos', 'INSERT', $insert, $usu_id);

        return $id;
    }

    /**
     * Actualiza los metadatos editables de un documento sin reemplazar su archivo.
     * Para cambiar el archivo se debe usar agregarVersion().
     */
    public function actualizar(int $id, array $data, int $usu_id): bool
    {
        $update = [
            'Nombre'              => $data['Nombre'],
            'Descripcion'         => $data['Descripcion'] ?? null,
            'NumeroPaginas'       => (int)$data['NumeroPaginas'],
            'IdUsuarioCreacion'   => (int)$data['IdUsuarioCreacion'],
            'IdUsuarioRevision'   => !empty($data['IdUsuarioRevision'])   ? (int)$data['IdUsuarioRevision']   : null,
            'IdUsuarioAprobacion' => !empty($data['IdUsuarioAprobacion']) ? (int)$data['IdUsuarioAprobacion'] : null,
            'FechaAprobacion'     => !empty($data['FechaAprobacion'])     ? $data['FechaAprobacion']          : null,
            'DescripcionCambios'  => $data['DescripcionCambios'] ?? null,
            'FechaModificacion'   => $this->ahora(),
        ];

        $ok = $this->update($id, $update);
        if (!$ok) return false;

        $this->insertarAuditoria('Documentos', 'UPDATE', $update, $usu_id);
        return true;
    }


    // =========================================================================
    // OPERACIONES DE ESCRITURA — VERSIONES
    // =========================================================================

    /**
     * Agrega una nueva versión a un documento existente.
     * Desactiva todas las versiones anteriores del mismo Codigo antes de insertar
     * la nueva, que queda como la versión activa (FechaFinalizacion = null).
     *
     * @param array  $data    Datos del formulario de la nueva versión.
     * @param string $ruta    Ruta relativa del nuevo archivo subido.
     * @param int    $usu_id  Id del usuario en sesión.
     * @return int|false True en éxito o false en error.
     */
    public function agregarVersion(array $data, string $ruta, int $usu_id): int|false
    {
        $nuevaVersion = $this->obtenerUltimaVersion($data['Codigo']) + 1;

        // Desactivar todas las versiones activas del mismo código
        $this->db->table($this->table)
            ->where('Codigo', $data['Codigo'])
            ->update(['FechaFinalizacion' => $this->ahora()]);

        // Insertar la nueva versión como activa
        $insert = [
            'Codigo'              => $data['Codigo'],
            'IdArea'              => (int)$data['IdArea'],
            'IdTipo'              => (int)$data['IdTipo'],
            'Nombre'              => $data['Nombre'],
            'Descripcion'         => $data['Descripcion'] ?? null,
            'NumeroPaginas'       => (int)$data['NumeroPaginas'],
            'IdUsuarioCreacion'   => (int)$data['IdUsuarioCreacion'],
            'IdUsuarioRevision'   => !empty($data['IdUsuarioRevision'])   ? (int)$data['IdUsuarioRevision']   : null,
            'IdUsuarioAprobacion' => !empty($data['IdUsuarioAprobacion']) ? (int)$data['IdUsuarioAprobacion'] : null,
            'FechaAprobacion'     => !empty($data['FechaAprobacion'])     ? $data['FechaAprobacion']          : null,
            'Version'             => $nuevaVersion,
            'DescripcionCambios'  => $data['DescripcionCambios'] ?? null,
            'Ruta'                => $ruta,
            'IdUsuario'           => $usu_id,
            'FechaFinalizacion'   => null,  // La nueva versión nace activa
        ];

        $ok = $this->insert($insert);
        if (!$ok) return false;

        $this->insertarAuditoria('Documentos', 'INSERT', $insert, $usu_id);
        return true;
    }

    /**
     * Activa una versión específica de un documento.
     * Primero desactiva TODAS las versiones del mismo Codigo y luego
     * limpia FechaFinalizacion de la versión seleccionada.
     */
    public function activarVersion(int $id, int $usu_id): bool
    {
        $doc = $this->find($id);
        if (!$doc) return false;

        // Desactivar todas las versiones del mismo código
        $this->db->table($this->table)
            ->where('Codigo', $doc['Codigo'])
            ->update(['FechaFinalizacion' => $this->ahora()]);

        // Activar únicamente la versión seleccionada
        return (bool)$this->update($id, ['FechaFinalizacion' => null]);
    }

    /**
     * Desactiva una versión específica marcando su FechaFinalizacion con la hora actual.
     */
    public function desactivarVersion(int $id, int $usu_id): bool
    {
        $doc = $this->find($id);
        if (!$doc) return false;

        return (bool)$this->update($id, ['FechaFinalizacion' => $this->ahora()]);
    }
}