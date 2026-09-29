<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las variedades de palma (tabla aVariedad).
 *
 * La llave primaria es compuesta (empresa, codigo), por lo que las operaciones
 * se resuelven con SQL crudo. La columna "codigo" es char(5), así que se lee
 * siempre con RTRIM y se guarda recortada.
 */
class VariedadesModel extends Model
{
    protected $table      = 'aVariedad';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, RTRIM(codigo) AS codigo, descripcion, procedencia, activo';

    /** Conteo de registros que referencian la variedad, correlacionado con el alias v. */
    private const USOS = '(SELECT COUNT(*) FROM aLotes l WHERE l.empresa = v.empresa AND l.variedad = v.codigo)'
        . ' + (SELECT COUNT(*) FROM aMovimientoLotes m WHERE m.empresa = v.empresa AND m.variedad = v.codigo)';

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
     * Lista las variedades de una empresa con el número de registros que las usan.
     *
     * @param string $estado '1', '0' o cadena vacía para todas.
     */
    public function listar(int $empresa, string $estado = ''): array
    {
        $sql = 'SELECT ' . self::CAMPOS
            . ', ' . self::USOS . ' AS enLotes'
            . ' FROM aVariedad v WHERE empresa = ?';
        $binds = [$empresa];

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND activo = ?';
            $binds[] = (int) $estado;
        }

        $sql .= ' ORDER BY codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS
            . ', ' . self::USOS . ' AS enLotes'
            . ' FROM aVariedad v WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray();
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->obtener($empresa, $codigo) !== null;
    }

    public function crear(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'INSERT INTO aVariedad (empresa, codigo, descripcion, procedencia, activo) VALUES (?, ?, ?, ?, ?)',
            [$empresa, $codigo, $data['descripcion'], $data['procedencia'], $data['activo']]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function actualizar(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aVariedad SET descripcion = ?, procedencia = ?, activo = ? WHERE empresa = ? AND codigo = ?',
            [$data['descripcion'], $data['procedencia'], $data['activo'], $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM aVariedad WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aVariedad SET activo = ? WHERE empresa = ? AND codigo = ?',
            [$activo, $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de lotes y movimientos de lote que usan la variedad.
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $codigo = trim($codigo);

        $fila = $this->db->query(
            'SELECT (SELECT COUNT(*) FROM aLotes WHERE empresa = ? AND variedad = ?)
                  + (SELECT COUNT(*) FROM aMovimientoLotes WHERE empresa = ? AND variedad = ?) AS total',
            [$empresa, $codigo, $empresa, $codigo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Consecutivo sugerido: el mayor código numérico existente más uno, a 3 dígitos.
     */
    public function siguienteCodigo(int $empresa): string
    {
        $filas = $this->db->query(
            'SELECT RTRIM(codigo) AS codigo FROM aVariedad WHERE empresa = ?',
            [$empresa]
        )->getResultArray();

        $mayor = 0;

        foreach ($filas as $f) {
            $codigo = trim((string) $f['codigo']);

            if (ctype_digit($codigo) && (int) $codigo > $mayor) {
                $mayor = (int) $codigo;
            }
        }

        return str_pad((string) ($mayor + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aVariedad.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aVariedad',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
