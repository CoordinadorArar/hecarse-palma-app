<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de empresas (tabla gEmpresa).
 *
 * Tabla productiva de la que dependen módulos de Contabilidad, Agro,
 * Cuentas por Pagar y Cuentas por Cobrar. No maneja columnas propias
 * de auditoría (IdUsuario/FechaModificacion/FechaFinalizacion); los
 * cambios quedan registrados en la tabla "Auditoria".
 */
class EmpresasModel extends Model
{
    protected $table      = 'gEmpresa';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'nit',
        'dv',
        'razonSocial',
        'activo',
        'fechaRegistro',
        'extractora',
        'tercero',
    ];

    /**
     * Pesos oficiales DIAN para el cálculo del dígito de verificación (DV)
     * de un NIT colombiano, de la posición 15 (izquierda) a la 1 (derecha).
     */
    private const PESOS_DV = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];

    /** Empresa bajo la que se registran los terceros (todas las filas de cTercero son de la 1). */
    private const EMPRESA_TERCEROS = 1;

    /**
     * Columnas opcionales de cTercero que se aceptan para el representante.
     * "actividadEconomica" y "notas" aun no existen en la tabla; el script
     * Documentacion/BaseDeDatos/agregar_columnas_representante.sql las crea y,
     * una vez ejecutado, basta con agregarlas a esta lista.
     */
    private const COLUMNAS_REPRESENTANTE = [
        'codigo', 'nit', 'razonSocial', 'descripcion', 'nombre1', 'telefono', 'direccion', 'email',
    ];

    /**
     * Columnas del listado: la empresa con el NIT sin el relleno del char(15)
     * y los datos del representante tomados de cTercero.
     */
    private const SELECT_LISTADO = "e.id, LTRIM(RTRIM(e.nit)) AS nit, e.dv, e.razonSocial, e.activo,
        e.fechaRegistro, e.extractora, e.tercero,
        NULLIF(LTRIM(RTRIM(COALESCE(t.razonSocial, CONCAT(t.nombre1, ' ', t.apellido1)))), '') AS NombreTercero,
        NULLIF(LTRIM(RTRIM(COALESCE(t.nit, t.codigo))), '') AS RepresentanteCc,
        NULLIF(LTRIM(RTRIM(COALESCE(t.razonSocial, CONCAT(t.nombre1, ' ', t.apellido1)))), '') AS RepresentanteNombre,
        NULLIF(LTRIM(RTRIM(t.telefono)), '') AS RepresentanteTelefono,
        NULLIF(LTRIM(RTRIM(t.direccion)), '') AS RepresentanteDireccion,
        NULLIF(LTRIM(RTRIM(t.email)), '') AS RepresentanteEmail";

    /**
     * Obtiene todas las empresas, con los datos del representante asociado.
     */
    public function getAll(): array
    {
        return $this->db->table('gEmpresa e')
            ->select(self::SELECT_LISTADO, false)
            ->join('cTercero t', 't.id = e.tercero AND t.empresa = ' . self::EMPRESA_TERCEROS, 'left')
            ->orderBy('e.razonSocial', 'ASC')
            ->get()->getResultArray();
    }

    public function getEmpresaById(int $id): ?array
    {
        return $this->db->table('gEmpresa e')
            ->select(self::SELECT_LISTADO, false)
            ->join('cTercero t', 't.id = e.tercero AND t.empresa = ' . self::EMPRESA_TERCEROS, 'left')
            ->where('e.id', $id)
            ->get()->getRowArray();
    }

    /**
     * Verifica si un NIT ya está registrado (excluyendo opcionalmente un id).
     */
    public function nitExiste(string $nit, int $excludeId = 0): bool
    {
        $builder = $this->where('nit', $nit);

        if ($excludeId > 0) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Consecutivo disponible para gEmpresa (la columna id no es identity).
     *
     * @param bool $bloquear Toma UPDLOCK/HOLDLOCK; solo dentro de la transaccion de alta.
     */
    public function siguienteId(bool $bloquear = false): int
    {
        $fila = $this->db->query(
            'SELECT ISNULL(MAX(id), 0) + 1 AS siguiente FROM gEmpresa'
            . ($bloquear ? ' WITH (UPDLOCK, HOLDLOCK)' : '')
        )->getRowArray();

        return (int) ($fila['siguiente'] ?? 1);
    }

    /**
     * Consecutivo disponible para cTercero dentro de la empresa indicada.
     */
    private function siguienteIdTercero(int $empresa, bool $bloquear = false): int
    {
        $fila = $this->db->query(
            'SELECT ISNULL(MAX(id), 0) + 1 AS siguiente FROM cTercero'
            . ($bloquear ? ' WITH (UPDLOCK, HOLDLOCK)' : '') . ' WHERE empresa = ?',
            [$empresa]
        )->getRowArray();

        return (int) ($fila['siguiente'] ?? 1);
    }

    /**
     * Crea la empresa junto con el tercero de su representante en una sola
     * transaccion. Si el tercero no se puede insertar, la empresa tampoco.
     *
     * @return int|null Id de la empresa creada, o null si la transaccion fallo.
     */
    public function crearEmpresaConRepresentante(array $empresa, array $representante, ?int $usu_id): ?int
    {
        $this->db->transBegin();

        try {
            $ahora     = date('Y-m-d\TH:i:s');
            $terceroId = $this->siguienteIdTercero(self::EMPRESA_TERCEROS, true);

            $tercero = array_merge([
                'empresa'       => self::EMPRESA_TERCEROS,
                'id'            => $terceroId,
                'tipo'          => 1,
                'apellido1'     => '',
                'apellido2'     => '',
                'activo'        => 1,
                'cliente'       => 0,
                'proveedor'     => 0,
                'empleado'      => 0,
                'accionista'    => 0,
                'contratista'   => 0,
                'extractora'    => 0,
                'fechaRegistro' => $ahora,
            ], array_intersect_key($representante, array_flip(self::COLUMNAS_REPRESENTANTE)));

            $columnas = array_keys($tercero);
            $marcas   = implode(', ', array_fill(0, count($columnas), '?'));

            $ok = $this->db->query(
                'INSERT INTO cTercero (' . implode(', ', $columnas) . ') VALUES (' . $marcas . ')',
                array_values($tercero)
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return null;
            }

            $empresaId = $this->siguienteId(true);

            $ok = $this->db->query(
                'INSERT INTO gEmpresa (id, nit, dv, razonSocial, activo, fechaRegistro, extractora, tercero)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $empresaId,
                    $empresa['nit'],
                    $empresa['dv'],
                    $empresa['razonSocial'],
                    $empresa['activo'],
                    $ahora,
                    $empresa['extractora'],
                    $terceroId,
                ]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return null;
            }

            $auditoriaTercero = $this->insertarAuditoria('INSERT', $tercero, $usu_id, 'cTercero');
            $auditoriaEmpresa = $this->insertarAuditoria('INSERT', array_merge($empresa, [
                'id'            => $empresaId,
                'fechaRegistro' => $ahora,
                'tercero'       => $terceroId,
            ]), $usu_id);

            if (! $auditoriaTercero || ! $auditoriaEmpresa || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return null;
            }

            $this->db->transCommit();

            return $empresaId;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error creando la empresa y su representante: ' . $e->getMessage());

            return null;
        }
    }

    public function createEmpresa(array $data, int $usu_id): int|false
    {
        $data['fechaRegistro'] = date('Y-m-d\TH:i:s');

        $id = $this->insert($data);
        if ($id === false) {
            return false;
        }

        $this->insertarAuditoria('INSERT', array_merge($data, ['id' => $id]), $usu_id);

        return $id;
    }

    public function updateEmpresa(int $id, array $data, int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            if ($this->update($id, $data) === false || $this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('UPDATE', array_merge($data, ['id' => $id]), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando la empresa: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Tablas que referencian a la empresa indicada, con la cantidad de filas.
     * Los pares (tabla, columna) se derivan de los metadatos porque gEmpresa
     * tiene 40 llaves foráneas y no todas apuntan a una columna "empresa".
     *
     * @return array<int, array{tabla: string, columna: string, filas: int}>
     */
    public function dependencias(int $id): array
    {
        $referencias = $this->db->query(
            "SELECT DISTINCT SCHEMA_NAME(o.schema_id) AS esquema, o.name AS tabla, c.name AS columna
               FROM sys.foreign_keys fk
               INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
               INNER JOIN sys.objects o ON o.object_id = fk.parent_object_id
               INNER JOIN sys.columns c ON c.object_id = fkc.parent_object_id
                                       AND c.column_id = fkc.parent_column_id
              WHERE fk.referenced_object_id = OBJECT_ID('gEmpresa')
              ORDER BY o.name, c.name"
        )->getResultArray();

        if ($referencias === []) {
            return [];
        }

        $consultas   = [];
        $parametros  = [];

        foreach ($referencias as $referencia) {
            $tabla   = '[' . $referencia['esquema'] . '].[' . $referencia['tabla'] . ']';
            $columna = '[' . $referencia['columna'] . ']';

            $consultas[] = 'SELECT ' . $this->db->escape($referencia['tabla']) . ' AS tabla, '
                . $this->db->escape($referencia['columna']) . ' AS columna, '
                . '(SELECT COUNT(*) FROM ' . $tabla . ' WHERE ' . $columna . ' = ?) AS filas '
                . 'WHERE EXISTS (SELECT 1 FROM ' . $tabla . ' WHERE ' . $columna . ' = ?)';

            $parametros[] = $id;
            $parametros[] = $id;
        }

        $filas = $this->db->query(implode(' UNION ALL ', $consultas), $parametros)->getResultArray();

        return array_map(static fn (array $fila): array => [
            'tabla'   => $fila['tabla'],
            'columna' => $fila['columna'],
            'filas'   => (int) $fila['filas'],
        ], $filas);
    }

    /**
     * Elimina únicamente la fila de gEmpresa (el cTercero del representante
     * se conserva porque otras tablas lo referencian).
     */
    public function eliminarEmpresa(int $id, ?int $usu_id, array $fila = []): bool
    {
        $this->db->transBegin();

        try {
            if ($this->db->query('DELETE FROM gEmpresa WHERE id = ?', [$id]) === false || $this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('DELETE', $fila + ['id' => $id], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando la empresa: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Búsqueda de terceros (para el buscador de la empresa-tercero del formulario).
     */
    public function buscarTercero(string $termino): array
    {
        return $this->db->table('cTercero')
            ->select("id, nit, COALESCE(razonSocial, CONCAT(nombre1, ' ', apellido1)) AS Nombre", false)
            ->groupStart()
                ->like('razonSocial', $termino)
                ->orLike('nombre1', $termino)
                ->orLike('apellido1', $termino)
                ->orLike('nit', $termino)
            ->groupEnd()
            ->where('activo', 1)
            ->orderBy('Nombre', 'ASC')
            ->limit(15)
            ->get()->getResultArray();
    }

    /**
     * Calcula el dígito de verificación (DV) de un NIT colombiano
     * según el algoritmo oficial de la DIAN (módulo 11).
     */
    public function calcularDV(string $nit): int
    {
        $nit    = preg_replace('/\D/', '', $nit);
        $nit    = str_pad($nit, 15, '0', STR_PAD_LEFT);
        $digitos = str_split($nit);

        $suma = 0;
        foreach ($digitos as $i => $digito) {
            $suma += ((int) $digito) * self::PESOS_DV[$i];
        }

        $residuo = $suma % 11;

        return ($residuo === 0 || $residuo === 1) ? $residuo : 11 - $residuo;
    }

    private function insertarAuditoria(string $accion, array $cambios, ?int $usu_id, string $tabla = 'gEmpresa'): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => $tabla,
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
