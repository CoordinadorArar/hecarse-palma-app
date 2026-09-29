<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo genérico de las entidades auxiliares (16 tablas de catálogo de 3 columnas:
 * empresa más dos columnas de datos).
 *
 * La entidad llega del cliente y el nombre de la tabla NO se puede parametrizar, así
 * que se valida contra la lista blanca ENTIDADES y, además, los nombres de tabla y
 * columnas que se interpolan en el SQL se toman siempre de sys.columns (nombre
 * canónico), entre corchetes escapando "]" -> "]]". Todas las consultas filtran por
 * empresa.
 *
 * Las tres últimas entidades tienen sus dos columnas de datos dentro de la llave
 * primaria: ahí la actualización no es posible (sería reemplazar la fila).
 */
class EntidadesAuxiliaresModel extends Model
{
    protected $returnType = 'array';

    /** Lista curada del legado: la regla "3 columnas con empresa" devuelve 27 tablas, no 16. */
    public const ENTIDADES = [
        'gBanco'                   => 'Bancos',
        'gClaseCuenta'             => 'Clases de cuenta',
        'gCodigoNacionalOcupacion' => 'Códigos nacionales de ocupación',
        'gDepartamento'            => 'Departamentos',
        'gDiagnostico'             => 'Diagnósticos',
        'gEntidadNacional'         => 'Entidades nacionales',
        'gNivelEducativo'          => 'Niveles educativos',
        'gPais'                    => 'Países',
        'gRegimenTributario'       => 'Regímenes tributarios',
        'gRh'                      => 'Grupos sanguíneos (RH)',
        'gTipoCuenta'              => 'Tipos de cuenta',
        'gTipoCuentaBancaria'      => 'Tipos de cuenta bancaria',
        'gTipoLiquidacion'         => 'Tipos de liquidación',
        'gTipoTransaccionProducto' => 'Tipos de transacción por producto',
        'iBodegaTipoTransaccion'   => 'Tipos de transacción por bodega',
        'iItemsBodega'             => 'Ítems por bodega',
    ];

    /** Rótulos de columna conocidos; el resto se deriva del nombre real. */
    private const ETIQUETAS_COLUMNA = [
        'codigo'      => 'Código',
        'descripcion' => 'Descripción',
        'tipo'        => 'Tipo',
        'producto'    => 'Producto',
        'bodega'      => 'Bodega',
        'item'        => 'Ítem',
    ];

    private const TIPOS_NUMERICOS = ['int', 'smallint', 'tinyint', 'bigint'];

    public const POR_PAGINA        = 25;
    public const MAX_POR_PAGINA    = 200;

    private array $cacheMeta = [];

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
     * Lista blanca de entidades para el selector.
     */
    public function entidades(): array
    {
        $entidades = [];

        foreach (self::ENTIDADES as $nombre => $etiqueta) {
            $entidades[] = ['nombre' => $nombre, 'etiqueta' => $etiqueta];
        }

        return $entidades;
    }

    /**
     * Nombre canónico de la entidad según la lista blanca, o null si no está.
     */
    public function entidadValida(string $entidad): ?string
    {
        $entidad = trim($entidad);

        foreach (array_keys(self::ENTIDADES) as $nombre) {
            if (strcasecmp($nombre, $entidad) === 0) {
                return $nombre;
            }
        }

        return null;
    }

    /**
     * Metadatos reales de la entidad: esquema, tabla y las dos columnas de datos
     * (nombre, tipo, longitud, si admite NULL y si hace parte de la llave primaria).
     *
     * @return array{entidad: string, etiqueta: string, esquema: string, columnas: array, llaveCompleta: bool}|null
     */
    public function metadatos(string $entidad): ?array
    {
        $tabla = $this->entidadValida($entidad);

        if ($tabla === null) {
            return null;
        }

        if (isset($this->cacheMeta[$tabla])) {
            return $this->cacheMeta[$tabla];
        }

        $filas = $this->db->query(
            "SELECT SCHEMA_NAME(t.schema_id) AS esquema, c.name AS nombre, TYPE_NAME(c.user_type_id) AS tipo,
                    c.max_length AS maxLength, c.is_nullable AS admiteNulo,
                    CASE WHEN EXISTS (SELECT 1
                                        FROM sys.indexes i
                                        INNER JOIN sys.index_columns ic ON ic.object_id = i.object_id
                                                                       AND ic.index_id  = i.index_id
                                       WHERE i.object_id = t.object_id
                                         AND i.is_primary_key = 1
                                         AND ic.column_id = c.column_id)
                         THEN 1 ELSE 0 END AS esPk
               FROM sys.tables t
               INNER JOIN sys.columns c ON c.object_id = t.object_id
              WHERE t.is_ms_shipped = 0 AND t.name = ?
              ORDER BY c.column_id ASC",
            [$tabla]
        )->getResultArray();

        if ($filas === []) {
            return null;
        }

        $esquema  = (string) $filas[0]['esquema'];
        $columnas = [];

        foreach ($filas as $fila) {
            if (strcasecmp((string) $fila['nombre'], 'empresa') === 0) {
                continue;
            }

            $tipo = strtolower(trim((string) $fila['tipo']));

            $columnas[] = [
                'nombre'      => (string) $fila['nombre'],
                'etiqueta'    => $this->etiquetaColumna((string) $fila['nombre']),
                'tipo'        => $tipo,
                'longitud'    => (int) $fila['maxLength'],
                'admiteNulo'  => (int) $fila['admiteNulo'] === 1,
                'esPk'        => (int) $fila['esPk'] === 1,
                'esNumerico'  => in_array($tipo, self::TIPOS_NUMERICOS, true),
            ];
        }

        if (count($columnas) !== 2) {
            return null;
        }

        $meta = [
            'entidad'       => $tabla,
            'etiqueta'      => self::ENTIDADES[$tabla],
            'esquema'       => $esquema,
            'columnas'      => $columnas,
            'llaveCompleta' => $columnas[0]['esPk'] && $columnas[1]['esPk'],
        ];

        return $this->cacheMeta[$tabla] = $meta;
    }

    /**
     * Página de filas de la entidad, con el total filtrado y el total sin filtro.
     *
     * @return array{filas: array, total: int, totalSinFiltro: int, pagina: int, porPagina: int, totalPaginas: int}|null
     */
    public function listar(array $meta, int $empresa, int $pagina, int $porPagina, string $busqueda = ''): ?array
    {
        $porPagina = max(1, min($porPagina, self::MAX_POR_PAGINA));
        $pagina    = max(1, $pagina);

        $expr1 = $this->expresionTexto($meta['columnas'][0]);
        $expr2 = $this->expresionTexto($meta['columnas'][1]);
        $tabla = $this->identificador($meta['esquema']) . '.' . $this->identificador($meta['entidad']);

        $filtro  = '';
        $binds   = [$empresa];
        $busqueda = trim($busqueda);

        if ($busqueda !== '') {
            $patron  = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $busqueda) . '%';
            $filtro  = '(' . $expr1 . ' LIKE ? OR ' . $expr2 . ' LIKE ?)';
            $binds[] = $patron;
            $binds[] = $patron;
        }

        try {
            $totales = $this->db->query(
                'SELECT COUNT(*) AS totalSinFiltro, '
                    . ($filtro === '' ? 'COUNT(*)' : 'SUM(CASE WHEN ' . $filtro . ' THEN 1 ELSE 0 END)') . ' AS total'
                    . ' FROM ' . $tabla . ' WHERE [empresa] = ?',
                $filtro === '' ? [$empresa] : [$binds[1], $binds[2], $empresa]
            )->getRowArray();

            $total          = (int) ($totales['total'] ?? 0);
            $totalSinFiltro = (int) ($totales['totalSinFiltro'] ?? 0);
            $offset         = ($pagina - 1) * $porPagina;

            $filas = $this->db->query(
                'SELECT ' . $expr1 . ' AS valor1, ' . $expr2 . ' AS valor2'
                    . ' FROM ' . $tabla
                    . ' WHERE [empresa] = ?' . ($filtro === '' ? '' : ' AND ' . $filtro)
                    . ' ORDER BY ' . $this->identificador($meta['columnas'][0]['nombre']) . ' ASC, '
                    . $this->identificador($meta['columnas'][1]['nombre']) . ' ASC'
                    . ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $porPagina . ' ROWS ONLY',
                $binds
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error listando la entidad auxiliar: ' . $e->getMessage());

            return null;
        }

        return [
            'filas'          => array_map(static fn (array $fila): array => [
                'valor1' => $fila['valor1'] === null ? null : (string) $fila['valor1'],
                'valor2' => $fila['valor2'] === null ? null : (string) $fila['valor2'],
            ], $filas),
            'total'          => $total,
            'totalSinFiltro' => $totalSinFiltro,
            'pagina'         => $pagina,
            'porPagina'      => $porPagina,
            'totalPaginas'   => (int) ceil($total / $porPagina),
        ];
    }

    /**
     * Fila identificada por las columnas de la llave (la 1, o las dos si ambas son PK).
     *
     * @return array{valor1: ?string, valor2: ?string}|null
     */
    public function obtener(array $meta, int $empresa, string $valor1, ?string $valor2 = null): ?array
    {
        if ($meta['llaveCompleta'] && trim((string) $valor2) === '') {
            return null;
        }

        $sql = 'SELECT TOP 1 ' . $this->expresionTexto($meta['columnas'][0]) . ' AS valor1, '
            . $this->expresionTexto($meta['columnas'][1]) . ' AS valor2'
            . ' FROM ' . $this->identificador($meta['esquema']) . '.' . $this->identificador($meta['entidad'])
            . ' WHERE [empresa] = ? AND ' . $this->identificador($meta['columnas'][0]['nombre']) . ' = ?';

        $binds = [$empresa, $valor1];

        if ($meta['llaveCompleta']) {
            $sql    .= ' AND ' . $this->identificador($meta['columnas'][1]['nombre']) . ' = ?';
            $binds[] = (string) $valor2;
        }

        try {
            $fila = $this->db->query($sql, $binds)->getRowArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error leyendo la entidad auxiliar: ' . $e->getMessage());

            return null;
        }

        if ($fila === null) {
            return null;
        }

        return [
            'valor1' => $fila['valor1'] === null ? null : (string) $fila['valor1'],
            'valor2' => $fila['valor2'] === null ? null : (string) $fila['valor2'],
        ];
    }

    public function existe(array $meta, int $empresa, string $valor1, ?string $valor2 = null): bool
    {
        return $this->obtener($meta, $empresa, $valor1, $valor2) !== null;
    }

    public function crear(array $meta, int $empresa, string $valor1, ?string $valor2, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $this->db->query(
                'INSERT INTO ' . $this->identificador($meta['esquema']) . '.' . $this->identificador($meta['entidad'])
                    . ' ([empresa], ' . $this->identificador($meta['columnas'][0]['nombre']) . ', '
                    . $this->identificador($meta['columnas'][1]['nombre']) . ') VALUES (?, ?, ?)',
                [$empresa, $valor1, $valor2]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria($meta, 'INSERT', $this->asiento($meta, $empresa, $valor1, $valor2), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error creando la fila de la entidad auxiliar: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Solo cambia la columna 2; la 1 hace parte de la llave primaria.
     */
    public function actualizar(array $meta, int $empresa, string $valor1, ?string $valor2, ?int $usu_id): bool
    {
        if ($meta['llaveCompleta']) {
            return false;
        }

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE ' . $this->identificador($meta['esquema']) . '.' . $this->identificador($meta['entidad'])
                    . ' SET ' . $this->identificador($meta['columnas'][1]['nombre']) . ' = ?'
                    . ' WHERE [empresa] = ? AND ' . $this->identificador($meta['columnas'][0]['nombre']) . ' = ?',
                [$valor2, $empresa, $valor1]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria($meta, 'UPDATE', $this->asiento($meta, $empresa, $valor1, $valor2), $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando la fila de la entidad auxiliar: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Borrado físico. El asiento de auditoría guarda la fila completa leída antes
     * de borrarla porque es el único rastro que queda.
     */
    public function eliminar(array $meta, int $empresa, string $valor1, ?string $valor2, ?int $usu_id, array $fila = []): bool
    {
        if ($meta['llaveCompleta'] && trim((string) $valor2) === '') {
            return false;
        }

        $sql = 'DELETE FROM ' . $this->identificador($meta['esquema']) . '.' . $this->identificador($meta['entidad'])
            . ' WHERE [empresa] = ? AND ' . $this->identificador($meta['columnas'][0]['nombre']) . ' = ?';

        $binds = [$empresa, $valor1];

        if ($meta['llaveCompleta']) {
            $sql    .= ' AND ' . $this->identificador($meta['columnas'][1]['nombre']) . ' = ?';
            $binds[] = (string) $valor2;
        }

        $this->db->transBegin();

        try {
            $this->db->query($sql, $binds);

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria(
                $meta,
                'DELETE',
                $this->asiento($meta, $empresa, $fila['valor1'] ?? $valor1, $fila['valor2'] ?? $valor2),
                $usu_id
            );

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando la fila de la entidad auxiliar: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Tablas que referencian la fila por llave foránea, con el conteo de filas
     * dependientes. Solo gBanco (2 FK) y gTipoCuenta (1 FK) están referenciadas.
     *
     * @return array<int, array{tabla: string, columna: string, filas: int}>
     */
    public function dependencias(array $meta, int $empresa, string $valor1, ?string $valor2 = null): array
    {
        $referencias = $this->db->query(
            "SELECT fk.name AS restriccion, SCHEMA_NAME(o.schema_id) AS esquema, o.name AS tabla,
                    c.name AS columna, rc.name AS columnaRef
               FROM sys.foreign_keys fk
               INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
               INNER JOIN sys.objects o ON o.object_id = fk.parent_object_id
               INNER JOIN sys.columns c ON c.object_id = fkc.parent_object_id
                                       AND c.column_id = fkc.parent_column_id
               INNER JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id
                                        AND rc.column_id = fkc.referenced_column_id
              WHERE fk.referenced_object_id = OBJECT_ID(?)
              ORDER BY fk.name, o.name, c.name",
            [$meta['esquema'] . '.' . $meta['entidad']]
        )->getResultArray();

        if ($referencias === []) {
            return [];
        }

        $valores = ['empresa' => $empresa, strtolower($meta['columnas'][0]['nombre']) => $valor1];

        if ($meta['llaveCompleta']) {
            $valores[strtolower($meta['columnas'][1]['nombre'])] = (string) $valor2;
        }

        $restricciones = [];

        foreach ($referencias as $referencia) {
            $restricciones[(string) $referencia['restriccion']][] = $referencia;
        }

        $consultas  = [];
        $parametros = [];

        foreach ($restricciones as $pares) {
            $tabla       = $this->identificador((string) $pares[0]['esquema']) . '.' . $this->identificador((string) $pares[0]['tabla']);
            $condiciones = [];
            $binds       = [];
            $columnas    = [];

            foreach ($pares as $par) {
                $referida = strtolower((string) $par['columnaRef']);

                if (! array_key_exists($referida, $valores)) {
                    $condiciones = [];
                    break;
                }

                $condiciones[] = $this->identificador((string) $par['columna']) . ' = ?';
                $binds[]       = $valores[$referida];

                if ($referida !== 'empresa') {
                    $columnas[] = (string) $par['columna'];
                }
            }

            if ($condiciones === []) {
                continue;
            }

            $donde = implode(' AND ', $condiciones);

            $consultas[] = 'SELECT ' . $this->db->escape((string) $pares[0]['tabla']) . ' AS tabla, '
                . $this->db->escape(implode(', ', $columnas)) . ' AS columna, '
                . '(SELECT COUNT(*) FROM ' . $tabla . ' WHERE ' . $donde . ') AS filas '
                . 'WHERE EXISTS (SELECT 1 FROM ' . $tabla . ' WHERE ' . $donde . ')';

            $parametros = array_merge($parametros, $binds, $binds);
        }

        if ($consultas === []) {
            return [];
        }

        try {
            $filas = $this->db->query(implode(' UNION ALL ', $consultas), $parametros)->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error verificando dependencias de la entidad auxiliar: ' . $e->getMessage());

            return [];
        }

        return array_map(static fn (array $fila): array => [
            'tabla'   => (string) $fila['tabla'],
            'columna' => (string) $fila['columna'],
            'filas'   => (int) $fila['filas'],
        ], $filas);
    }

    public function insertarAuditoria(array $meta, string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => $meta['entidad'],
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }

    private function asiento(array $meta, int $empresa, ?string $valor1, ?string $valor2): array
    {
        return [
            'empresa'                          => $empresa,
            $meta['columnas'][0]['nombre']     => $valor1,
            $meta['columnas'][1]['nombre']     => $valor2,
        ];
    }

    private function etiquetaColumna(string $columna): string
    {
        $clave = strtolower(trim($columna));

        return self::ETIQUETAS_COLUMNA[$clave] ?? ucfirst($clave);
    }

    private function identificador(string $nombre): string
    {
        return '[' . str_replace(']', ']]', $nombre) . ']';
    }

    private function expresionTexto(array $columna): string
    {
        $longitud = $columna['esNumerico'] || $columna['longitud'] <= 0 ? 30 : $columna['longitud'];

        return 'LTRIM(RTRIM(CONVERT(varchar(' . $longitud . '), ' . $this->identificador($columna['nombre']) . ')))';
    }
}
