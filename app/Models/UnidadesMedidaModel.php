<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las unidades de medida (tabla gUnidadMedida).
 *
 * La llave primaria es compuesta (empresa, codigo), por lo que las operaciones
 * se resuelven con SQL crudo. La columna "desCorta" es char(3), así que se lee
 * siempre con RTRIM y se guarda recortada.
 */
class UnidadesMedidaModel extends Model
{
    protected $table      = 'gUnidadMedida';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, codigo, RTRIM(desCorta) AS desCorta, descripcion, activo';

    /** Conteo de registros que referencian la unidad, correlacionado con el alias u. */
    private const USOS = '(SELECT COUNT(*) FROM aNovedad a WHERE a.empresa = u.empresa AND a.uMedida = u.codigo)'
        . ' + (SELECT COUNT(*) FROM aTransaccionNovedad b WHERE b.empresa = u.empresa AND b.uMedida = u.codigo)'
        . ' + (SELECT COUNT(*) FROM aTransaccionItem c WHERE c.empresa = u.empresa AND c.uMedida = u.codigo)'
        . ' + (SELECT COUNT(*) FROM aSanidadDetalle d WHERE d.empresa = u.empresa AND d.uMedida = u.codigo)'
        . ' + (SELECT COUNT(*) FROM lAnalisis e WHERE e.empresa = u.empresa AND e.uMedida = u.codigo)'
        . ' + (SELECT COUNT(*) FROM nParametrosGeneral f WHERE f.empresa = u.empresa AND f.uMedidaJornal = u.codigo)'
        . ' + (SELECT COUNT(*) FROM iItems g WHERE g.empresa = u.empresa AND (g.uMedidaCompra = u.codigo OR g.uMedidaConsumo = u.codigo))';

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
     * Lista las unidades de medida de una empresa con el número de registros que las usan.
     *
     * @param string $estado '1', '0' o cadena vacía para todas.
     */
    public function listar(int $empresa, string $estado = ''): array
    {
        $sql = 'SELECT ' . self::CAMPOS
            . ', ' . self::USOS . ' AS usos'
            . ' FROM gUnidadMedida u WHERE empresa = ?';
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
            . ', ' . self::USOS . ' AS usos'
            . ' FROM gUnidadMedida u WHERE empresa = ? AND codigo = ?',
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
            'INSERT INTO gUnidadMedida (empresa, codigo, descripcion, desCorta, activo) VALUES (?, ?, ?, ?, ?)',
            [$empresa, $codigo, $data['descripcion'], $data['desCorta'], $data['activo']]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function actualizar(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE gUnidadMedida SET descripcion = ?, desCorta = ?, activo = ? WHERE empresa = ? AND codigo = ?',
            [$data['descripcion'], $data['desCorta'], $data['activo'], $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM gUnidadMedida WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE gUnidadMedida SET activo = ? WHERE empresa = ? AND codigo = ?',
            [$activo, $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan la unidad de medida, con el mismo criterio que listar().
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $codigo = trim($codigo);

        $fila = $this->db->query(
            'SELECT ' . self::USOS . ' AS total FROM gUnidadMedida u WHERE u.empresa = ? AND u.codigo = ?',
            [$empresa, $codigo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla gUnidadMedida.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'gUnidadMedida',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
