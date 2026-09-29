<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de los tipos de transacción del legado (dbo.gTipoTransaccion).
 *
 * Llave primaria (empresa, codigo). La columna "forma" NO se gestiona desde esta
 * pantalla: los propios SP del legado no la tocan, así que el INSERT la deja en
 * NULL y el UPDATE nunca la modifica.
 *
 * "fechaRegistro" es NOT NULL y no tiene default: el legado la escribe tanto al
 * insertar como al actualizar (funciona como fecha de última modificación).
 */
class TiposTransaccionModel extends Model
{
    protected $table      = 'gTipoTransaccion';
    protected $returnType = 'array';

    /**
     * Tablas y columnas verificadas que guardan códigos de gTipoTransaccion.
     *
     * Lista CURADA a propósito: las FK declaradas son solo 3 y dejan fuera el uso
     * real. Quedan excluidas cPrecontabilizacion.tipo (guarda códigos de
     * cClaseParametroContaNomi) y bRegistroBascula.tipo (guarda 'EPE', que no
     * existe en gTipoTransaccion).
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DEPENDENCIAS = [
        ['gTipoTransaccionCampo', 'tipoTransaccion'],
        ['gTipoTransaccionConfig', 'tipoTransaccion'],
        ['gTipoTransaccionConcurrencia', 'transaccion'],
        ['bRegistroPorteria', 'tipo'],
        ['cParametroContaNomi', 'tipoTransaccion'],
        ['aTransaccion', 'tipo'],
        ['aTransaccionTercero', 'tipo'],
        ['aTransaccionNovedad', 'tipo'],
        ['aTransaccionItem', 'tipo'],
        ['aTransaccionBascula', 'tipo'],
        ['atransaccionItemSaldo', 'tipo'],
        ['aSanidad', 'tipo'],
        ['aSanidadDetalle', 'tipo'],
        ['aTipoNovedad', 'tipo'],
    ];

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->where('activo', 1)->countAllResults() > 0;
    }

    /**
     * Catálogo de módulos del legado. NO se filtra por "activo": solo "agro" lo
     * tiene en 1 y el select quedaría con una sola opción.
     */
    public function modulos(): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(descripcion)) AS descripcion
               FROM sModulos
              ORDER BY descripcion ASC'
        )->getResultArray();
    }

    public function moduloValido(string $modulo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 1 AS existe FROM sModulos WHERE LTRIM(RTRIM(codigo)) = ?',
            [$modulo]
        )->getRowArray() !== null;
    }

    /**
     * "modulo" se devuelve crudo (recortado) aunque no esté en sModulos: hoy hay
     * filas con 'labor' y 'produccion', que no pertenecen al catálogo, y el
     * frontend debe poder mostrarlas. En ese caso moduloDescripcion viene null.
     */
    public function listar(int $empresa): array
    {
        $filas = $this->db->query(
            'SELECT t.empresa,
                    LTRIM(RTRIM(t.codigo)) AS codigo,
                    LTRIM(RTRIM(t.descripcion)) AS descripcion,
                    CASE WHEN ISNULL(t.numeracion, 0) = 0 THEN 0 ELSE 1 END AS numeracion,
                    t.actual,
                    LTRIM(RTRIM(t.prefijo)) AS prefijo,
                    t.longitud,
                    t.naturaleza,
                    LTRIM(RTRIM(t.modulo)) AS modulo,
                    LTRIM(RTRIM(m.descripcion)) AS moduloDescripcion,
                    t.modoAnulacion,
                    CASE WHEN ISNULL(t.referencia, 0) = 0 THEN 0 ELSE 1 END AS referencia,
                    LTRIM(RTRIM(t.vistaDs)) AS vistaDs,
                    CASE WHEN ISNULL(t.activo, 0) = 0 THEN 0 ELSE 1 END AS activo,
                    CONVERT(varchar(19), t.fechaRegistro, 120) AS fechaRegistro,
                    LTRIM(RTRIM(t.formato)) AS formato
               FROM gTipoTransaccion t
               LEFT JOIN sModulos m ON LTRIM(RTRIM(m.codigo)) = LTRIM(RTRIM(t.modulo))
              WHERE t.empresa = ?
              ORDER BY t.codigo ASC',
            [$empresa]
        )->getResultArray();

        $usos = $this->usos($empresa);

        foreach ($filas as &$fila) {
            $fila = $this->normalizar($fila);
            $fila['usos'] = $usos[$fila['codigo']] ?? 0;
        }

        return $filas;
    }

    /**
     * Total de registros que usan cada código, sumando las tablas de la lista
     * curada. Una sola consulta agregada: una pasada por tabla agrupando por el
     * código, no un conteo por fila del listado.
     *
     * @return array<string, int>
     */
    private function usos(int $empresa): array
    {
        $ramas      = [];
        $parametros = [];

        foreach (self::DEPENDENCIAS as $dependencia) {
            [$tabla, $columna] = $dependencia;

            $ramas[] = 'SELECT LTRIM(RTRIM(' . $columna . ')) AS codigo, COUNT(*) AS n'
                . ' FROM dbo.' . $tabla . ' WHERE empresa = ?'
                . ' GROUP BY LTRIM(RTRIM(' . $columna . '))';

            $parametros[] = $empresa;
        }

        try {
            $filas = $this->db->query(
                'SELECT codigo, SUM(n) AS usos FROM (' . implode(' UNION ALL ', $ramas) . ') u GROUP BY codigo',
                $parametros
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error contando el uso de los tipos de transacción: ' . $e->getMessage());

            return [];
        }

        $usos = [];

        foreach ($filas as $fila) {
            $usos[(string) $fila['codigo']] = (int) $fila['usos'];
        }

        return $usos;
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        $fila = $this->db->query(
            'SELECT TOP 1 t.empresa,
                    LTRIM(RTRIM(t.codigo)) AS codigo,
                    LTRIM(RTRIM(t.descripcion)) AS descripcion,
                    CASE WHEN ISNULL(t.numeracion, 0) = 0 THEN 0 ELSE 1 END AS numeracion,
                    t.actual,
                    LTRIM(RTRIM(t.prefijo)) AS prefijo,
                    t.longitud,
                    t.naturaleza,
                    LTRIM(RTRIM(t.modulo)) AS modulo,
                    LTRIM(RTRIM(m.descripcion)) AS moduloDescripcion,
                    t.modoAnulacion,
                    CASE WHEN ISNULL(t.referencia, 0) = 0 THEN 0 ELSE 1 END AS referencia,
                    LTRIM(RTRIM(t.vistaDs)) AS vistaDs,
                    CASE WHEN ISNULL(t.activo, 0) = 0 THEN 0 ELSE 1 END AS activo,
                    CONVERT(varchar(19), t.fechaRegistro, 120) AS fechaRegistro,
                    LTRIM(RTRIM(t.formato)) AS formato
               FROM gTipoTransaccion t
               LEFT JOIN sModulos m ON LTRIM(RTRIM(m.codigo)) = LTRIM(RTRIM(t.modulo))
              WHERE t.empresa = ? AND LTRIM(RTRIM(t.codigo)) = ?',
            [$empresa, $codigo]
        )->getRowArray();

        return $fila === null ? null : $this->normalizar($fila);
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->obtener($empresa, $codigo) !== null;
    }

    /**
     * @param array{descripcion: string, numeracion: int, actual: ?int, prefijo: ?string, longitud: ?int, naturaleza: int, modulo: string, modoAnulacion: string, referencia: int, vistaDs: ?string, activo: int, formato: ?string} $datos
     */
    public function crear(int $empresa, string $codigo, array $datos, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $fecha = date('Y-m-d H:i:s');

            $this->db->query(
                'INSERT INTO gTipoTransaccion (empresa, codigo, descripcion, numeracion, actual, prefijo, longitud,
                                               naturaleza, modulo, modoAnulacion, referencia, vistaDs, activo,
                                               fechaRegistro, formato)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $empresa,
                    $codigo,
                    $datos['descripcion'],
                    $datos['numeracion'],
                    $datos['actual'],
                    $datos['prefijo'],
                    $datos['longitud'],
                    $datos['naturaleza'],
                    $datos['modulo'],
                    $datos['modoAnulacion'],
                    $datos['referencia'],
                    $datos['vistaDs'],
                    $datos['activo'],
                    $fecha,
                    $datos['formato'],
                ]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('INSERT', array_merge([
                'empresa' => $empresa,
                'codigo'  => $codigo,
            ], $datos, ['fechaRegistro' => $fecha]), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error creando el tipo de transacción: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * No toca empresa ni codigo (llave primaria) ni forma (no se gestiona aquí).
     *
     * @param array{descripcion: string, numeracion: int, actual: ?int, prefijo: ?string, longitud: ?int, naturaleza: int, modulo: string, modoAnulacion: string, referencia: int, vistaDs: ?string, activo: int, formato: ?string} $datos
     */
    public function actualizar(int $empresa, string $codigo, array $datos, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $fecha = date('Y-m-d H:i:s');

            $this->db->query(
                'UPDATE gTipoTransaccion
                    SET descripcion = ?, numeracion = ?, actual = ?, prefijo = ?, longitud = ?, naturaleza = ?,
                        modulo = ?, modoAnulacion = ?, referencia = ?, vistaDs = ?, activo = ?, formato = ?,
                        fechaRegistro = ?
                  WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
                [
                    $datos['descripcion'],
                    $datos['numeracion'],
                    $datos['actual'],
                    $datos['prefijo'],
                    $datos['longitud'],
                    $datos['naturaleza'],
                    $datos['modulo'],
                    $datos['modoAnulacion'],
                    $datos['referencia'],
                    $datos['vistaDs'],
                    $datos['activo'],
                    $datos['formato'],
                    $fecha,
                    $empresa,
                    $codigo,
                ]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('UPDATE', array_merge([
                'empresa' => $empresa,
                'codigo'  => $codigo,
            ], $datos, ['fechaRegistro' => $fecha]), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando el tipo de transacción: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Borrado físico. El asiento de auditoría guarda la fila leída antes de
     * borrarla porque es el único rastro que queda.
     */
    public function eliminar(int $empresa, string $codigo, ?int $usu_id, array $fila = []): bool
    {
        $this->db->transBegin();

        try {
            $this->db->query(
                'DELETE FROM gTipoTransaccion WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
                [$empresa, $codigo]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('DELETE', $fila === [] ? [
                'empresa' => $empresa,
                'codigo'  => $codigo,
            ] : $fila, $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando el tipo de transacción: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Uso real del código en la lista curada de tablas. Cada rama cuenta solo si
     * EXISTS, para no recorrer completas las tablas de cien mil filas, y todas
     * filtran por empresa.
     *
     * Devuelve null si la verificación NO se pudo hacer: un arreglo vacío
     * significa "no hay dependencias" y habilitaría el borrado, así que el fallo
     * de la consulta no puede confundirse con él.
     *
     * @return array<int, array{tabla: string, columna: string, filas: int}>|null
     */
    public function dependencias(int $empresa, string $codigo): ?array
    {
        $consultas  = [];
        $parametros = [];

        foreach (self::DEPENDENCIAS as $dependencia) {
            [$tabla, $columna] = $dependencia;

            $donde = 'empresa = ? AND LTRIM(RTRIM(' . $columna . ')) = ?';

            $consultas[] = 'SELECT ' . $this->db->escape($tabla) . ' AS tabla, '
                . $this->db->escape($columna) . ' AS columna, '
                . '(SELECT COUNT(*) FROM ' . $tabla . ' WHERE ' . $donde . ') AS filas '
                . 'WHERE EXISTS (SELECT 1 FROM ' . $tabla . ' WHERE ' . $donde . ')';

            $parametros = array_merge($parametros, [$empresa, $codigo, $empresa, $codigo]);
        }

        try {
            $filas = $this->db->query(implode(' UNION ALL ', $consultas), $parametros)->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error verificando dependencias del tipo de transacción: ' . $e->getMessage());

            return null;
        }

        $resultado = [];

        foreach ($filas as $fila) {
            if ((int) $fila['filas'] > 0) {
                $resultado[] = [
                    'tabla'   => (string) $fila['tabla'],
                    'columna' => (string) $fila['columna'],
                    'filas'   => (int) $fila['filas'],
                ];
            }
        }

        return $resultado;
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'gTipoTransaccion',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }

    private function normalizar(array $fila): array
    {
        $fila['empresa']       = (int) $fila['empresa'];
        $fila['numeracion']    = (int) $fila['numeracion'];
        $fila['referencia']    = (int) $fila['referencia'];
        $fila['activo']        = (int) $fila['activo'];
        $fila['naturaleza']    = (int) $fila['naturaleza'];
        $fila['actual']        = $fila['actual'] === null ? null : (int) $fila['actual'];
        $fila['longitud']      = $fila['longitud'] === null ? null : (int) $fila['longitud'];
        $fila['modoAnulacion'] = trim((string) $fila['modoAnulacion']);

        return $fila;
    }
}
