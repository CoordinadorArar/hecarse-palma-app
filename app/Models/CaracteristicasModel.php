<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las características de sanidad (tabla aCaracteristica).
 *
 * Particularidades del legado que hay que respetar al pie de la letra:
 *
 * - La columna "manejaCaractistica" está mal escrita en la base (le falta "er"). Se escribe así en
 *   todas las sentencias; corregirla rompería la aplicación.
 * - grupoCaracteristica = 0 es un grupo válido y usado (CENSO DE ENFERMEDADES), no es "sin grupo".
 *   El "sin grupo" se representa con NULL, por lo que nunca se puede usar empty()/casting laxo:
 *   0, NULL y cadena vacía se distinguen explícitamente tanto al leer como al escribir.
 * - Las descripciones se repiten legítimamente entre grupos distintos (por ejemplo "FLECHA PODRIDA"
 *   existe en tres grupos), pero no se repiten dentro de un mismo grupo: la unicidad se valida por
 *   (empresa, descripción, grupo), nunca solo por descripción.
 * - codigo es entero y NO es identity: lo digita el usuario. siguienteCodigo() solo sugiere
 *   MAX(codigo)+1 para prellenar el formulario; no reserva nada.
 *
 * Los usos que bloquean el borrado salen de aSanidadDetalle.caracteristica (int).
 */
class CaracteristicasModel extends Model
{
    protected $table      = 'aCaracteristica';
    protected $returnType = 'array';

    private const CAMPOS = 'c.empresa, c.codigo, c.descripcion, c.manejaCaractistica, c.grupoCaracteristica,
        c.activo, g.descripcion AS grupoDescripcion, ISNULL(u.usos, 0) AS usos';

    private const SQL_USOS = '(SELECT empresa, caracteristica, COUNT(*) AS usos FROM aSanidadDetalle
        WHERE empresa = ? AND caracteristica IS NOT NULL GROUP BY empresa, caracteristica)';

    private const JOINS = ' FROM aCaracteristica c
        LEFT JOIN aGrupoCaracteristica g ON g.empresa = c.empresa AND g.codigo = c.grupoCaracteristica
        LEFT JOIN ' . self::SQL_USOS . ' u ON u.empresa = c.empresa AND u.caracteristica = c.codigo';

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
        return $this->db->table('gEmpresa')->where('id', $empresa)->countAllResults() > 0;
    }

    /**
     * Catálogo de grupos con el número de características asignadas a cada uno.
     */
    public function getGrupos(int $empresa): array
    {
        $res = $this->db->query(
            'SELECT g.codigo, g.descripcion, g.activo,
                (SELECT COUNT(*) FROM aCaracteristica c
                  WHERE c.empresa = g.empresa AND c.grupoCaracteristica = g.codigo) AS total
             FROM aGrupoCaracteristica g WHERE g.empresa = ? ORDER BY g.codigo ASC',
            [$empresa]
        );

        return $res === false ? [] : $res->getResultArray();
    }

    /** El código 0 es un grupo real: la comparación es estrictamente numérica. */
    public function grupoExiste(int $empresa, int $codigo): bool
    {
        $res = $this->db->query(
            'SELECT TOP 1 codigo FROM aGrupoCaracteristica WHERE empresa = ? AND codigo = ?',
            [$empresa, $codigo]
        );

        return $res !== false && $res->getRowArray() !== null;
    }

    public function nombreGrupo(int $empresa, ?int $codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        $res  = $this->db->query(
            'SELECT TOP 1 descripcion FROM aGrupoCaracteristica WHERE empresa = ? AND codigo = ?',
            [$empresa, $codigo]
        );
        $fila = $res === false ? null : $res->getRowArray();

        return trim((string) ($fila['descripcion'] ?? ''));
    }

    /**
     * @param string $grupo    Código de grupo; '0' es un filtro legítimo y '' significa "todos".
     * @param string $estado   '1', '0' o cadena vacía para todos.
     * @param string $busqueda Término libre contra código y descripción.
     */
    public function listar(int $empresa, string $grupo = '', string $estado = '', string $busqueda = ''): array
    {
        $grupo    = trim($grupo);
        $busqueda = trim($busqueda);

        $sql   = 'SELECT ' . self::CAMPOS . self::JOINS . ' WHERE c.empresa = ?';
        $binds = [$empresa, $empresa];

        if ($grupo !== '' && ctype_digit($grupo)) {
            $sql .= ' AND c.grupoCaracteristica = ?';
            $binds[] = (int) $grupo;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND c.activo = ?';
            $binds[] = (int) $estado;
        }

        if ($busqueda !== '') {
            $comodin = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
            $sql .= " AND (CAST(c.codigo AS varchar(50)) LIKE ? ESCAPE '\\' OR c.descripcion LIKE ? ESCAPE '\\')";
            $binds[] = $comodin;
            $binds[] = $comodin;
        }

        $sql .= ' ORDER BY c.codigo ASC';

        $res = $this->db->query($sql, $binds);

        return $res === false ? [] : $res->getResultArray();
    }

    public function obtener(int $empresa, int $codigo): ?array
    {
        $res = $this->db->query(
            'SELECT ' . self::CAMPOS . self::JOINS . ' WHERE c.empresa = ? AND c.codigo = ?',
            [$empresa, $empresa, $codigo]
        );

        return $res === false ? null : $res->getRowArray();
    }

    public function existe(int $empresa, int $codigo): bool
    {
        $res = $this->db->query(
            'SELECT TOP 1 codigo FROM aCaracteristica WHERE empresa = ? AND codigo = ?',
            [$empresa, $codigo]
        );

        return $res !== false && $res->getRowArray() !== null;
    }

    /**
     * Consecutivo SUGERIDO: MAX(codigo)+1, ya que la columna no es identity y el usuario digita el
     * código. Es solo una sugerencia para prellenar el formulario, no una reserva: dos usuarios
     * simultáneos pueden recibir el mismo número y el segundo INSERT fallará por llave duplicada,
     * que es el comportamiento correcto.
     */
    public function siguienteCodigo(int $empresa): int
    {
        $res = $this->db->query(
            'SELECT ISNULL(MAX(codigo), 0) + 1 AS siguiente FROM aCaracteristica WHERE empresa = ?',
            [$empresa]
        );

        $fila = $res === false ? null : $res->getRowArray();

        return (int) ($fila['siguiente'] ?? 1);
    }

    /**
     * Unicidad por (empresa, descripción, grupo): la misma descripción puede existir en otro grupo.
     *
     * Se resuelve sobre variantes() en vez de con una comparación SQL para que el servidor use
     * exactamente la misma normalización que el mapa que recibe el JS (mayúsculas y espacios
     * colapsados); con UPPER/LTRIM/RTRIM en SQL los espacios interiores no se colapsan y el aviso
     * del navegador y el rechazo del servidor podrían discrepar. La comparación del grupo es
     * estricta: 0 y null son valores distintos.
     */
    public function descripcionDuplicada(int $empresa, string $descripcion, ?int $grupo, ?int $excluirCodigo = null): bool
    {
        foreach ($this->variantes($empresa)[self::normalizar($descripcion)] ?? [] as $e) {
            if ($excluirCodigo !== null && $e['codigo'] === $excluirCodigo) {
                continue;
            }

            if ($e['grupo'] === $grupo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Universo completo de descripciones de la empresa, agrupadas por descripción normalizada
     * (mayúsculas y espacios colapsados), con los grupos en que aparece cada una.
     *
     * Se calcula siempre sobre TODAS las características, nunca sobre el listado ya filtrado: la
     * marca de variante debe seguir apareciendo justo cuando se filtra por un grupo.
     *
     * @return array<string, list<array{codigo:int, grupo:int|null, grupoDescripcion:string}>>
     */
    public function variantes(int $empresa): array
    {
        $res = $this->db->query(
            'SELECT c.codigo, c.descripcion, c.grupoCaracteristica, g.descripcion AS grupoDescripcion
             FROM aCaracteristica c
             LEFT JOIN aGrupoCaracteristica g ON g.empresa = c.empresa AND g.codigo = c.grupoCaracteristica
             WHERE c.empresa = ? ORDER BY c.codigo ASC',
            [$empresa]
        );

        $mapa = [];

        foreach ($res === false ? [] : $res->getResultArray() as $f) {
            $clave = self::normalizar((string) $f['descripcion']);

            $mapa[$clave][] = [
                'codigo'           => (int) $f['codigo'],
                'grupo'            => $f['grupoCaracteristica'] === null ? null : (int) $f['grupoCaracteristica'],
                'grupoDescripcion' => trim((string) ($f['grupoDescripcion'] ?? '')),
            ];
        }

        return $mapa;
    }

    /** Clave de comparación de descripciones: mayúsculas y espacios colapsados. */
    public static function normalizar(string $descripcion): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $descripcion)));
    }

    public function enUso(int $empresa, int $codigo): int
    {
        $res  = $this->db->query(
            'SELECT COUNT(*) AS total FROM aSanidadDetalle WHERE empresa = ? AND caracteristica = ?',
            [$empresa, $codigo]
        );
        $fila = $res === false ? null : $res->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /** El código lo digita el usuario; la llave duplicada se valida antes con existe(). */
    public function crear(int $empresa, int $codigo, array $data, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'INSERT INTO aCaracteristica (empresa, codigo, descripcion, manejaCaractistica, grupoCaracteristica, activo)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $empresa, $codigo, $data['descripcion'], $data['manejaCaractistica'],
                    $data['grupoCaracteristica'], $data['activo'],
                ]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Características, crear: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /** La llave (empresa, codigo) es inmutable: solo se actualizan los valores. */
    public function actualizar(int $empresa, int $codigo, array $data, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'UPDATE aCaracteristica SET descripcion = ?, manejaCaractistica = ?, grupoCaracteristica = ?, activo = ?
                 WHERE empresa = ? AND codigo = ?',
                [
                    $data['descripcion'], $data['manejaCaractistica'], $data['grupoCaracteristica'],
                    $data['activo'], $empresa, $codigo,
                ]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Características, actualizar: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    public function eliminar(int $empresa, int $codigo, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'DELETE FROM aCaracteristica WHERE empresa = ? AND codigo = ?',
                [$empresa, $codigo]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Características, eliminar: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    public function cambiarEstado(int $empresa, int $codigo, int $activo, ?int $usu_id): bool
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'UPDATE aCaracteristica SET activo = ? WHERE empresa = ? AND codigo = ?',
                [$activo, $empresa, $codigo]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('UPDATE', [
                'empresa' => $empresa,
                'codigo'  => $codigo,
                'activo'  => $activo,
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Características, cambiar estado: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aCaracteristica.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aCaracteristica',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
