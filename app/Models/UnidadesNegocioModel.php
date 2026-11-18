<?php

namespace App\Models;

use CodeIgniter\Model;

class UnidadesNegocioModel extends Model
{
    protected $table      = 'aUnidadNegocio';
    protected $returnType = 'array';

    private const LOTES = '(SELECT COUNT(*) FROM aLotes l WHERE l.empresa = u.empresa AND l.unidadNegocio = u.codigo)';

    private static ?bool $disponible = null;

    public function disponible(): bool
    {
        return self::$disponible ??= (int) $this->db->query(
            "SELECT CASE WHEN OBJECT_ID('dbo.aUnidadNegocio', 'U') IS NOT NULL
                          AND OBJECT_ID('dbo.aFincaUnidadNegocio', 'U') IS NOT NULL
                          AND COL_LENGTH('dbo.aLotes', 'unidadNegocio') IS NOT NULL THEN 1 ELSE 0 END AS existe"
        )->getRow()->existe === 1;
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

    public function getFincas(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion, activo FROM aFinca WHERE empresa = ? ORDER BY activo DESC, codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function listar(int $empresa, string $finca = '', string $estado = ''): array
    {
        $finca = trim($finca);
        $sql   = 'SELECT u.empresa, RTRIM(u.codigo) AS codigo, u.descripcion, u.activo, ' . self::LOTES . ' AS lotes'
            . ' FROM aUnidadNegocio u WHERE u.empresa = ?';
        $binds = [$empresa];

        if ($finca !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM aFincaUnidadNegocio fu WHERE fu.empresa = u.empresa AND fu.unidadNegocio = u.codigo AND fu.finca = ?)';
            $binds[] = $finca;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND u.activo = ?';
            $binds[] = (int) $estado;
        }

        $filas  = $this->db->query($sql . ' ORDER BY u.codigo ASC', $binds)->getResultArray();
        $fincas = [];

        foreach ($this->db->query(
            'SELECT RTRIM(fu.unidadNegocio) AS unidad, RTRIM(fu.finca) AS finca, f.descripcion
               FROM aFincaUnidadNegocio fu
               LEFT JOIN aFinca f ON f.empresa = fu.empresa AND f.codigo = fu.finca
              WHERE fu.empresa = ? ORDER BY fu.finca ASC',
            [$empresa]
        )->getResultArray() as $f) {
            $fincas[$f['unidad']][] = $f;
        }

        return array_map(static fn ($fila) => $fila + ['fincas' => $fincas[$fila['codigo']] ?? []], $filas);
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        $codigo = trim($codigo);
        $unidad = $this->db->query(
            'SELECT u.empresa, RTRIM(u.codigo) AS codigo, u.descripcion, u.activo, ' . self::LOTES . ' AS lotes'
            . ' FROM aUnidadNegocio u WHERE u.empresa = ? AND u.codigo = ?',
            [$empresa, $codigo]
        )->getRowArray();

        if ($unidad === null) {
            return null;
        }

        $unidad['fincas'] = $this->db->query(
            'SELECT RTRIM(fu.finca) AS finca, f.descripcion,
                    (SELECT COUNT(*) FROM aLotes l WHERE l.empresa = fu.empresa AND l.finca = fu.finca AND l.unidadNegocio = fu.unidadNegocio) AS lotes
               FROM aFincaUnidadNegocio fu
               LEFT JOIN aFinca f ON f.empresa = fu.empresa AND f.codigo = fu.finca
              WHERE fu.empresa = ? AND fu.unidadNegocio = ? ORDER BY fu.finca ASC',
            [$empresa, $codigo]
        )->getResultArray();

        return $unidad;
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aUnidadNegocio WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function activas(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM aUnidadNegocio WHERE empresa = ? AND activo = 1 ORDER BY descripcion ASC',
            [$empresa]
        )->getResultArray();
    }

    public function porFinca(int $empresa, bool $soloActivas): array
    {
        $mapa = [];

        foreach ($this->db->query(
            'SELECT RTRIM(fu.finca) AS finca, RTRIM(u.codigo) AS codigo, u.descripcion
               FROM aFincaUnidadNegocio fu
               JOIN aUnidadNegocio u ON u.empresa = fu.empresa AND u.codigo = fu.unidadNegocio
              WHERE fu.empresa = ?' . ($soloActivas ? ' AND u.activo = 1' : '') . ' ORDER BY u.descripcion ASC',
            [$empresa]
        )->getResultArray() as $f) {
            $mapa[$f['finca']][] = ['codigo' => $f['codigo'], 'descripcion' => $f['descripcion']];
        }

        return $mapa;
    }

    public function habilitada(int $empresa, string $finca, string $unidad, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 fu.unidadNegocio FROM aFincaUnidadNegocio fu
               JOIN aUnidadNegocio u ON u.empresa = fu.empresa AND u.codigo = fu.unidadNegocio
              WHERE fu.empresa = ? AND fu.finca = ? AND fu.unidadNegocio = ?
                AND (u.activo = 1 OR EXISTS (SELECT 1 FROM aLotes l WHERE l.empresa = fu.empresa AND l.finca = fu.finca
                                                AND l.codigo = ? AND l.unidadNegocio = fu.unidadNegocio))',
            [$empresa, trim($finca), trim($unidad), trim($lote)]
        )->getRowArray() !== null;
    }

    public function crear(int $empresa, string $codigo, array $data, array $fincas, ?int $usu_id, string $usuario): bool
    {
        $fecha = date('Y-m-d\TH:i:s');

        $this->db->transStart();

        $this->db->query(
            'INSERT INTO aUnidadNegocio (empresa, codigo, descripcion, activo, fechaRegistro, usuarioRegistro) VALUES (?, ?, ?, ?, ?, ?)',
            [$empresa, $codigo, $data['descripcion'], $data['activo'], $fecha, $usuario]
        );

        $this->guardarFincas($empresa, $codigo, $fincas);

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'codigo' => $codigo],
            $data,
            ['fincas' => $fincas, 'fechaRegistro' => $fecha, 'usuarioRegistro' => $usuario]
        ), $usu_id);

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    public function actualizar(int $empresa, string $codigo, array $data, array $fincas, ?int $usu_id): bool
    {
        $codigo = trim($codigo);

        $this->db->transStart();

        $this->db->query(
            'UPDATE aUnidadNegocio SET descripcion = ?, activo = ? WHERE empresa = ? AND codigo = ?',
            [$data['descripcion'], $data['activo'], $empresa, $codigo]
        );

        $this->guardarFincas($empresa, $codigo, $fincas);

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data, ['fincas' => $fincas]), $usu_id);

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    private function guardarFincas(int $empresa, string $codigo, array $fincas): void
    {
        $this->db->query(
            'DELETE FROM aFincaUnidadNegocio WHERE empresa = ? AND unidadNegocio = ?' . ($fincas !== [] ? ' AND finca NOT IN ?' : ''),
            $fincas !== [] ? [$empresa, $codigo, $fincas] : [$empresa, $codigo]
        );

        foreach ($fincas as $finca) {
            $this->db->query(
                'INSERT INTO aFincaUnidadNegocio (empresa, finca, unidadNegocio) SELECT ?, ?, ?
                  WHERE NOT EXISTS (SELECT 1 FROM aFincaUnidadNegocio WHERE empresa = ? AND finca = ? AND unidadNegocio = ?)',
                [$empresa, $finca, $codigo, $empresa, $finca, $codigo]
            );
        }
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM aUnidadNegocio WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('UPDATE aUnidadNegocio SET activo = ? WHERE empresa = ? AND codigo = ?', [$activo, $empresa, $codigo]);

        $this->insertarAuditoria('UPDATE', ['empresa' => $empresa, 'codigo' => $codigo, 'activo' => $activo], $usu_id);
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aUnidadNegocio',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
