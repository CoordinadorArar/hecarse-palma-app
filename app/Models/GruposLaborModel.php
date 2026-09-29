<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los grupos de novedad/labor (tabla aGrupoNovedad).
 *
 * La llave primaria es compuesta (empresa, codigo), por lo que las operaciones se resuelven
 * con SQL crudo. La columna "ccosto" guarda el código con relleno de espacios, así que se
 * compara y se lee siempre con RTRIM. "manejaCcostoSiigo" es nullable: se lee como 0 cuando
 * es NULL y nunca se escribe NULL.
 */
class GruposLaborModel extends Model
{
    protected $table      = 'aGrupoNovedad';
    protected $returnType = 'array';

    private const CAMPOS = 'g.empresa, g.codigo, g.descripcion, g.activo, RTRIM(g.ccosto) AS ccosto,
        ISNULL(g.manejaCcostoSiigo, 0) AS manejaCcostoSiigo, g.ccostoSiigo, c.descripcion AS ccostoDescripcion';

    /** Conteo de labores que referencian el grupo, correlacionado con el alias g. */
    private const USOS = '(SELECT COUNT(*) FROM aNovedad n WHERE n.empresa = g.empresa AND n.grupo = g.codigo) AS labores';

    private const JOIN_CCOSTO = ' LEFT JOIN cCentrosCosto c ON c.empresa = g.empresa AND RTRIM(c.codigo) = RTRIM(g.ccosto)';

    /**
     * Empresas activas disponibles para el selector.
     */
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
     * Centros de costo activos y auxiliares de la empresa, para el selector.
     */
    public function getCentrosCosto(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM cCentrosCosto
             WHERE empresa = ? AND activo = 1 AND auxiliar = 1 ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function ccostoValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM cCentrosCosto WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    /**
     * Lista los grupos de labor de una empresa con el número de labores que los usan.
     *
     * @param string $estado '1', '0' o cadena vacía para todas.
     */
    public function listar(int $empresa, string $estado = ''): array
    {
        $sql = 'SELECT ' . self::CAMPOS . ', ' . self::USOS
            . ' FROM aGrupoNovedad g' . self::JOIN_CCOSTO
            . ' WHERE g.empresa = ?';
        $binds = [$empresa];

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND g.activo = ?';
            $binds[] = (int) $estado;
        }

        $sql .= ' ORDER BY g.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ', ' . self::USOS
            . ' FROM aGrupoNovedad g' . self::JOIN_CCOSTO
            . ' WHERE g.empresa = ? AND g.codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray();
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aGrupoNovedad WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    /**
     * Consecutivo sugerido: el mayor código numérico existente más uno, a 2 dígitos.
     * Devuelve cadena vacía si el máximo alcanzado (99) no permite un siguiente código.
     */
    public function siguienteCodigo(int $empresa): string
    {
        $filas = $this->db->query(
            'SELECT codigo FROM aGrupoNovedad WHERE empresa = ?',
            [$empresa]
        )->getResultArray();

        $mayor = 0;

        foreach ($filas as $f) {
            $codigo = trim((string) $f['codigo']);

            if (ctype_digit($codigo) && (int) $codigo > $mayor) {
                $mayor = (int) $codigo;
            }
        }

        $siguiente = $mayor + 1;

        return $siguiente > 99 ? '' : str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT);
    }

    public function crear(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'INSERT INTO aGrupoNovedad (empresa, codigo, descripcion, activo, ccosto, manejaCcostoSiigo, ccostoSiigo)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa, $codigo, $data['descripcion'], $data['activo'],
                $data['ccosto'], $data['manejaCcostoSiigo'], $data['ccostoSiigo'],
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function actualizar(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aGrupoNovedad SET descripcion = ?, activo = ?, ccosto = ?, manejaCcostoSiigo = ?, ccostoSiigo = ?
             WHERE empresa = ? AND codigo = ?',
            [
                $data['descripcion'], $data['activo'], $data['ccosto'], $data['manejaCcostoSiigo'], $data['ccostoSiigo'],
                $empresa, $codigo,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM aGrupoNovedad WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aGrupoNovedad SET activo = ? WHERE empresa = ? AND codigo = ?',
            [$activo, $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de labores (aNovedad) que usan el grupo.
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $codigo = trim($codigo);

        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM aNovedad WHERE empresa = ? AND grupo = ?',
            [$empresa, $codigo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aGrupoNovedad.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aGrupoNovedad',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
