<?php

namespace App\Models;

use CodeIgniter\Model;

class MotivosAusenciaModel extends Model
{
    protected $table      = 'pMotivoAusencia';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, LTRIM(RTRIM(codigo)) AS codigo, descripcion, activo';

    private const USOS = '(SELECT COUNT(*) FROM pPlaneacionAusencia a JOIN pPlaneacion p ON p.id = a.planeacion'
        . ' WHERE p.empresa = m.empresa AND a.codigoMotivo = m.codigo)';

    private ?bool $disponible = null;

    public function disponible(): bool
    {
        if ($this->disponible === null) {
            $this->disponible = (int) $this->db->query(
                "SELECT CASE WHEN OBJECT_ID('dbo.pMotivoAusencia', 'U') IS NOT NULL
                              AND COL_LENGTH('pPlaneacionAusencia', 'codigoMotivo') IS NOT NULL THEN 1 ELSE 0 END AS existe"
            )->getRow()->existe === 1;
        }

        return $this->disponible;
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
        return $this->db->table('gEmpresa')->where('id', $empresa)->countAllResults() > 0;
    }

    public function listar(int $empresa, string $estado = ''): array
    {
        $sql   = 'SELECT ' . self::CAMPOS . ', ' . self::USOS . ' AS usos FROM pMotivoAusencia m WHERE empresa = ?';
        $binds = [$empresa];

        if ($estado === '1' || $estado === '0') {
            $sql    .= ' AND activo = ?';
            $binds[] = (int) $estado;
        }

        return $this->db->query($sql . ' ORDER BY codigo ASC', $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ', ' . self::USOS . ' AS usos FROM pMotivoAusencia m WHERE empresa = ? AND codigo = ?',
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
            'INSERT INTO pMotivoAusencia (empresa, codigo, descripcion, activo) VALUES (?, ?, ?, ?)',
            [$empresa, $codigo, $data['descripcion'], $data['activo']]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function actualizar(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE pMotivoAusencia SET descripcion = ?, activo = ? WHERE empresa = ? AND codigo = ?',
            [$data['descripcion'], $data['activo'], $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM pMotivoAusencia WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('UPDATE pMotivoAusencia SET activo = ? WHERE empresa = ? AND codigo = ?', [$activo, $empresa, $codigo]);

        $this->insertarAuditoria('UPDATE', ['empresa' => $empresa, 'codigo' => $codigo, 'activo' => $activo], $usu_id);
    }

    public function enUso(int $empresa, string $codigo): int
    {
        $fila = $this->db->query(
            'SELECT ' . self::USOS . ' AS total FROM pMotivoAusencia m WHERE m.empresa = ? AND m.codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'pMotivoAusencia',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
