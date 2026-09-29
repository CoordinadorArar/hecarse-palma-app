<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de los parámetros generales de configuración (tabla gConfigParametrosGenerales).
 *
 * La llave primaria es compuesta (empresa, nombre), por lo que las operaciones se
 * resuelven con SQL crudo. La tabla no tiene columnas de auditoría: la trazabilidad
 * queda solo en la tabla "Auditoria".
 *
 * Los parámetros con manejaDS = 1 toman su valor de otra tabla de la base (ds), con
 * una columna de valor (cValor) y una de etiqueta (cEtiqueta). Esos identificadores
 * llegan del cliente y NO se pueden parametrizar, así que siempre se resuelven contra
 * sys.tables / sys.columns y se interpola únicamente el nombre devuelto por los
 * metadatos, entre corchetes.
 */
class ParametrosGeneralesModel extends Model
{
    protected $table      = 'gConfigParametrosGenerales';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, nombre, tipoDato, manejaDS, ds, cValor, cEtiqueta, valor';

    /** Tope de filas devueltas por valores(); hay tablas origen con más de 100.000 filas. */
    public const LIMITE_VALORES = 200;

    /** Tipos que no sirven como valor ni etiqueta de una lista desplegable. */
    private const TIPOS_NO_APTOS = [
        'varbinary', 'binary', 'image', 'text', 'ntext', 'xml',
        'sql_variant', 'geography', 'geometry', 'hierarchyid', 'timestamp', 'rowversion',
    ];

    private ?string $errorOrigen = null;

    public function errorOrigen(): ?string
    {
        return $this->errorOrigen;
    }

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

    public function listar(int $empresa, string $manejaDS = ''): array
    {
        $sql   = 'SELECT ' . self::CAMPOS . ' FROM gConfigParametrosGenerales WHERE empresa = ?';
        $binds = [$empresa];

        if ($manejaDS === '1' || $manejaDS === '0') {
            $sql .= ' AND manejaDS = ?';
            $binds[] = (int) $manejaDS;
        }

        $sql .= ' ORDER BY nombre ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $nombre): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ' FROM gConfigParametrosGenerales WHERE empresa = ? AND nombre = ?',
            [$empresa, trim($nombre)]
        )->getRowArray();
    }

    public function existe(int $empresa, string $nombre): bool
    {
        return $this->obtener($empresa, $nombre) !== null;
    }

    public function crear(int $empresa, string $nombre, array $data, ?int $usu_id): bool
    {
        $nombre = trim($nombre);

        $this->db->transBegin();

        try {
            $this->db->query(
                'INSERT INTO gConfigParametrosGenerales (empresa, nombre, tipoDato, manejaDS, ds, cValor, cEtiqueta, valor)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $empresa,
                    $nombre,
                    $data['tipoDato'],
                    $data['manejaDS'],
                    $data['ds'],
                    $data['cValor'],
                    $data['cEtiqueta'],
                    $data['valor'],
                ]
            );

            $auditoria = $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'nombre' => $nombre], $data), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error creando el parámetro general: ' . $e->getMessage());

            return false;
        }
    }

    public function actualizar(int $empresa, string $nombre, array $data, ?int $usu_id): bool
    {
        $nombre = trim($nombre);

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE gConfigParametrosGenerales
                    SET tipoDato = ?, manejaDS = ?, ds = ?, cValor = ?, cEtiqueta = ?, valor = ?
                  WHERE empresa = ? AND nombre = ?',
                [
                    $data['tipoDato'],
                    $data['manejaDS'],
                    $data['ds'],
                    $data['cValor'],
                    $data['cEtiqueta'],
                    $data['valor'],
                    $empresa,
                    $nombre,
                ]
            );

            $auditoria = $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'nombre' => $nombre], $data), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando el parámetro general: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Borrado físico. El asiento de auditoría guarda la fila completa porque es
     * el único rastro que queda del parámetro eliminado.
     */
    public function eliminar(int $empresa, string $nombre, ?int $usu_id, array $fila = []): bool
    {
        $nombre = trim($nombre);

        $this->db->transBegin();

        try {
            $this->db->query(
                'DELETE FROM gConfigParametrosGenerales WHERE empresa = ? AND nombre = ?',
                [$empresa, $nombre]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('DELETE', array_merge($fila, ['empresa' => $empresa, 'nombre' => $nombre]), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando el parámetro general: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Tablas de usuario disponibles como origen de datos, con la marca de si
     * tienen columna "empresa" (207 de 252 la tienen).
     */
    public function tablas(): array
    {
        return $this->db->query(
            "SELECT t.name AS nombre, SCHEMA_NAME(t.schema_id) AS esquema,
                    CASE WHEN EXISTS (SELECT 1 FROM sys.columns c
                                       WHERE c.object_id = t.object_id AND c.name = 'empresa')
                         THEN 1 ELSE 0 END AS tieneEmpresa
               FROM sys.tables t
              WHERE t.is_ms_shipped = 0
              ORDER BY t.name ASC"
        )->getResultArray();
    }

    /**
     * Resuelve el nombre real de la tabla contra sys.tables.
     *
     * @return array{nombre: string, esquema: string, tieneEmpresa: bool}|null
     */
    public function tablaReal(string $tabla): ?array
    {
        $fila = $this->db->query(
            "SELECT TOP 1 t.name AS nombre, SCHEMA_NAME(t.schema_id) AS esquema,
                    CASE WHEN EXISTS (SELECT 1 FROM sys.columns c
                                       WHERE c.object_id = t.object_id AND c.name = 'empresa')
                         THEN 1 ELSE 0 END AS tieneEmpresa
               FROM sys.tables t
              WHERE t.is_ms_shipped = 0 AND t.name = ?",
            [trim($tabla)]
        )->getRowArray();

        if ($fila === null) {
            return null;
        }

        return [
            'nombre'       => (string) $fila['nombre'],
            'esquema'      => (string) $fila['esquema'],
            'tieneEmpresa' => (int) $fila['tieneEmpresa'] === 1,
        ];
    }

    /**
     * Columnas de la tabla origen, o null si la tabla no existe.
     */
    public function columnas(string $tabla): ?array
    {
        if ($this->tablaReal($tabla) === null) {
            return null;
        }

        return $this->db->query(
            'SELECT c.name AS nombre, TYPE_NAME(c.user_type_id) AS tipo, c.is_nullable AS admiteNulo
               FROM sys.columns c
               INNER JOIN sys.tables t ON t.object_id = c.object_id
              WHERE t.is_ms_shipped = 0 AND t.name = ?
              ORDER BY c.column_id ASC',
            [trim($tabla)]
        )->getResultArray();
    }

    /**
     * Resuelve el nombre real de la columna dentro de la tabla indicada.
     */
    public function columnaReal(string $tabla, string $columna): ?string
    {
        $fila = $this->db->query(
            'SELECT TOP 1 c.name AS nombre
               FROM sys.columns c
               INNER JOIN sys.tables t ON t.object_id = c.object_id
              WHERE t.is_ms_shipped = 0 AND t.name = ? AND c.name = ?',
            [trim($tabla), trim($columna)]
        )->getRowArray();

        return $fila === null ? null : (string) $fila['nombre'];
    }

    /**
     * Valida el trío (tabla, columna valor, columna etiqueta) contra los metadatos
     * y devuelve los nombres reales, los únicos que se interpolan en el SQL.
     *
     * @return array{tabla: string, esquema: string, tieneEmpresa: bool, cValor: string, cEtiqueta: string}|null
     */
    public function validarOrigen(string $tabla, string $cValor, string $cEtiqueta): ?array
    {
        $this->errorOrigen = null;

        $meta = $this->tablaReal($tabla);

        if ($meta === null) {
            return null;
        }

        $columnaValor    = $this->columnaMeta($meta['nombre'], $cValor);
        $columnaEtiqueta = $this->columnaMeta($meta['nombre'], $cEtiqueta);

        if ($columnaValor === null || $columnaEtiqueta === null) {
            return null;
        }

        if (! $this->tipoApto($columnaValor) || ! $this->tipoApto($columnaEtiqueta)) {
            $this->errorOrigen = 'La columna seleccionada no puede usarse como valor o etiqueta por su tipo de dato.';

            return null;
        }

        return [
            'tabla'        => $meta['nombre'],
            'esquema'      => $meta['esquema'],
            'tieneEmpresa' => $meta['tieneEmpresa'],
            'cValor'       => $columnaValor['nombre'],
            'cEtiqueta'    => $columnaEtiqueta['nombre'],
        ];
    }

    /**
     * Nombre canónico, tipo y longitud de la columna, o null si no existe.
     *
     * @return array{nombre: string, tipo: string, maxLength: int}|null
     */
    private function columnaMeta(string $tabla, string $columna): ?array
    {
        $fila = $this->db->query(
            'SELECT TOP 1 c.name AS nombre, TYPE_NAME(c.user_type_id) AS tipo, c.max_length AS maxLength
               FROM sys.columns c
               INNER JOIN sys.tables t ON t.object_id = c.object_id
              WHERE t.is_ms_shipped = 0 AND t.name = ? AND c.name = ?',
            [trim($tabla), trim($columna)]
        )->getRowArray();

        if ($fila === null) {
            return null;
        }

        return [
            'nombre'    => (string) $fila['nombre'],
            'tipo'      => strtolower(trim((string) $fila['tipo'])),
            'maxLength' => (int) $fila['maxLength'],
        ];
    }

    /**
     * Rechaza binarios, texto grande y tipos CLR: en un desplegable no son legibles.
     */
    private function tipoApto(array $columna): bool
    {
        return $columna['maxLength'] !== -1 && ! in_array($columna['tipo'], self::TIPOS_NO_APTOS, true);
    }

    /**
     * Pares valor/etiqueta del origen de datos, con tope de filas y búsqueda en servidor.
     *
     * @return array{valores: array, total: int, recortada: bool, limite: int, filtraEmpresa: bool}|null
     */
    public function valores(string $tabla, string $cValor, string $cEtiqueta, int $empresa, string $termino = '', int $limite = self::LIMITE_VALORES): ?array
    {
        $origen = $this->validarOrigen($tabla, $cValor, $cEtiqueta);

        if ($origen === null) {
            return null;
        }

        $limite   = max(1, min($limite, self::LIMITE_VALORES));
        $valor    = $this->expresionTexto($origen['cValor']);
        $etiqueta = $this->expresionTexto($origen['cEtiqueta']);

        $sql   = 'SELECT DISTINCT ' . $valor . ' AS valor, ' . $etiqueta . ' AS etiqueta'
            . ' FROM ' . $this->identificador($origen['esquema']) . '.' . $this->identificador($origen['tabla'])
            . ' WHERE ' . $this->identificador($origen['cValor']) . ' IS NOT NULL';
        $binds = [];

        if ($origen['tieneEmpresa']) {
            $sql .= ' AND [empresa] = ?';
            $binds[] = $empresa;
        }

        $termino = trim($termino);

        if ($termino !== '') {
            $patron  = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $termino) . '%';
            $sql    .= ' AND (' . $valor . ' LIKE ? OR ' . $etiqueta . ' LIKE ?)';
            $binds[] = $patron;
            $binds[] = $patron;
        }

        try {
            $filas = $this->db->query(
                'SELECT TOP (' . $limite . ') p.valor, p.etiqueta, COUNT(*) OVER () AS total'
                    . ' FROM (' . $sql . ') p ORDER BY p.etiqueta ASC, p.valor ASC',
                $binds
            )->getResultArray();

            $total = $filas === [] ? 0 : (int) $filas[0]['total'];
            $filas = array_map(static fn (array $fila): array => [
                'valor'    => $fila['valor'],
                'etiqueta' => $fila['etiqueta'],
            ], $filas);
        } catch (\Throwable $e) {
            log_message('error', 'Error leyendo el origen de datos del parámetro: ' . $e->getMessage());

            return null;
        }

        return [
            'valores'       => $filas,
            'total'         => $total,
            'recortada'     => $total > count($filas),
            'limite'        => $limite,
            'filtraEmpresa' => $origen['tieneEmpresa'],
        ];
    }

    /**
     * Etiqueta del valor guardado, o null si ese valor no existe en el origen
     * (caso real: nConcepto.codigo va rellenado con ceros y hay valores huérfanos).
     */
    public function resolverEtiqueta(string $tabla, string $cValor, string $cEtiqueta, int $empresa, string $valor): ?string
    {
        $origen = $this->validarOrigen($tabla, $cValor, $cEtiqueta);

        if ($origen === null) {
            return null;
        }

        $sql   = 'SELECT TOP 1 ' . $this->expresionTexto($origen['cEtiqueta']) . ' AS etiqueta'
            . ' FROM ' . $this->identificador($origen['esquema']) . '.' . $this->identificador($origen['tabla'])
            . ' WHERE ' . $this->expresionTexto($origen['cValor']) . ' = ?';
        $binds = [trim($valor)];

        if ($origen['tieneEmpresa']) {
            $sql .= ' AND [empresa] = ?';
            $binds[] = $empresa;
        }

        try {
            $fila = $this->db->query($sql, $binds)->getRowArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error resolviendo el valor del parámetro: ' . $e->getMessage());

            return null;
        }

        return $fila === null ? null : (string) $fila['etiqueta'];
    }

    private function identificador(string $nombre): string
    {
        return '[' . str_replace(']', ']]', $nombre) . ']';
    }

    private function expresionTexto(string $columna): string
    {
        return 'LTRIM(RTRIM(CONVERT(varchar(255), ' . $this->identificador($columna) . ')))';
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'gConfigParametrosGenerales',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
