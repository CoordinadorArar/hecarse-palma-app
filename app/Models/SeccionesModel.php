<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las secciones/bloques de finca (tabla aSecciones).
 *
 * La llave primaria es compuesta (empresa, finca, codigo), por lo que las operaciones
 * se resuelven con SQL crudo. No tiene fechaRegistro/usuarioRegistro: la trazabilidad
 * queda solo en la tabla Auditoria.
 */
class SeccionesModel extends Model
{
    protected $table      = 'aSecciones';
    protected $returnType = 'array';

    private const CAMPOS = 's.empresa, s.finca, s.codigo, s.descripcion, s.hBrutas, s.activo';

    /** Tablas que guardan un código de sección desnormalizado, con la expresión de lectura de "seccion". */
    private const TABLAS_USO = [
        ['aLotes', 'seccion'],
        ['aLotePesosPeriodo', 'seccion'],
        ['aSanidad', 'RTRIM(seccion)'],
        ['aTransaccionNovedad', 'seccion'],
        ['aTransaccionTercero', 'seccion'],
    ];

    /**
     * Subconsulta única con el total de usos por (empresa, finca, seccion), agregado de una sola pasada
     * por tabla en vez de una subconsulta correlacionada por cada fila de aSecciones.
     */
    private function sqlUsosAgregados(bool $filtrarFinca): string
    {
        $partes = [];

        foreach (self::TABLAS_USO as [$tabla, $expr]) {
            $partes[] = "SELECT empresa, finca, {$expr} AS seccion, COUNT(*) AS n FROM {$tabla}"
                . ' WHERE empresa = ?' . ($filtrarFinca ? ' AND finca = ?' : '')
                . " AND seccion IS NOT NULL AND LTRIM(RTRIM(seccion)) <> ''"
                . " GROUP BY empresa, finca, {$expr}";
        }

        return '(SELECT empresa, finca, seccion, SUM(n) AS usos FROM ('
            . implode(' UNION ALL ', $partes) . ') u GROUP BY empresa, finca, seccion)';
    }

    /**
     * Binds de sqlUsosAgregados(), en el mismo orden en que aparecen sus placeholders.
     */
    private function usosBinds(int $empresa, string $finca, bool $filtrarFinca): array
    {
        $binds = [];

        foreach (self::TABLAS_USO as $ignorada) {
            $binds[] = $empresa;

            if ($filtrarFinca) {
                $binds[] = $finca;
            }
        }

        return $binds;
    }

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
     * Fincas de la empresa, para el selector y el filtro.
     */
    public function getFincas(int $empresa): array
    {
        return $this->db->query(
            'SELECT codigo, descripcion FROM aFinca WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    /**
     * Total de hectáreas asignadas en secciones y hectáreas de la finca, para el contraste en la vista.
     * Solo tiene sentido con una finca concreta; con finca vacía devuelve ambos valores en null.
     */
    public function totalesHectareas(int $empresa, string $finca, string $estado = ''): array
    {
        $finca = trim($finca);

        if ($finca === '') {
            return ['ha_asignadas' => null, 'ha_finca' => null];
        }

        $sql   = 'SELECT SUM(hBrutas) AS total FROM aSecciones WHERE empresa = ? AND finca = ?';
        $binds = [$empresa, $finca];

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND activo = ?';
            $binds[] = (int) $estado;
        }

        $fila        = $this->db->query($sql, $binds)->getRowArray();
        $haAsignadas = $fila !== null && $fila['total'] !== null ? (float) $fila['total'] : null;

        $fincaFila = $this->db->query(
            'SELECT hectareas FROM aFinca WHERE empresa = ? AND codigo = ?',
            [$empresa, $finca]
        )->getRowArray();
        $haFinca = $fincaFila !== null ? (float) $fincaFila['hectareas'] : null;

        return ['ha_asignadas' => $haAsignadas, 'ha_finca' => $haFinca];
    }

    public function fincaValida(int $empresa, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aFinca WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($finca)]
        )->getRowArray() !== null;
    }

    /**
     * Lista las secciones de una empresa con el número de registros que las usan.
     *
     * @param string $finca  Código de finca o cadena vacía para todas.
     * @param string $estado '1', '0' o cadena vacía para todas.
     */
    public function listar(int $empresa, string $finca = '', string $estado = ''): array
    {
        $finca        = trim($finca);
        $filtrarFinca = $finca !== '';

        $sql = 'SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM aSecciones s'
            . ' LEFT JOIN aFinca f ON f.empresa = s.empresa AND f.codigo = s.finca'
            . ' LEFT JOIN ' . $this->sqlUsosAgregados($filtrarFinca)
            . ' u ON u.empresa = s.empresa AND u.finca = s.finca AND u.seccion = s.codigo'
            . ' WHERE s.empresa = ?';
        $binds   = $this->usosBinds($empresa, $finca, $filtrarFinca);
        $binds[] = $empresa;

        if ($filtrarFinca) {
            $sql .= ' AND s.finca = ?';
            $binds[] = $finca;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND s.activo = ?';
            $binds[] = (int) $estado;
        }

        $sql .= ' ORDER BY s.finca ASC, s.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $finca, string $codigo): ?array
    {
        $finca = trim($finca);

        $sql = 'SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM aSecciones s'
            . ' LEFT JOIN aFinca f ON f.empresa = s.empresa AND f.codigo = s.finca'
            . ' LEFT JOIN ' . $this->sqlUsosAgregados(true)
            . ' u ON u.empresa = s.empresa AND u.finca = s.finca AND u.seccion = s.codigo'
            . ' WHERE s.empresa = ? AND s.finca = ? AND s.codigo = ?';
        $binds   = $this->usosBinds($empresa, $finca, true);
        $binds[] = $empresa;
        $binds[] = $finca;
        $binds[] = trim($codigo);

        return $this->db->query($sql, $binds)->getRowArray();
    }

    public function existe(int $empresa, string $finca, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aSecciones WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$empresa, trim($finca), trim($codigo)]
        )->getRowArray() !== null;
    }

    public function crear(int $empresa, string $finca, string $codigo, array $data, ?int $usu_id): void
    {
        $finca  = trim($finca);
        $codigo = trim($codigo);

        $this->db->query(
            'INSERT INTO aSecciones (empresa, finca, codigo, descripcion, hBrutas, activo) VALUES (?, ?, ?, ?, ?, ?)',
            [$empresa, $finca, $codigo, $data['descripcion'], $data['hBrutas'], $data['activo']]
        );

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'finca' => $finca, 'codigo' => $codigo],
            $data
        ), $usu_id);
    }

    public function actualizar(int $empresa, string $finca, string $codigo, array $data, ?int $usu_id): void
    {
        $finca  = trim($finca);
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aSecciones SET activo = ?, hBrutas = ?, descripcion = ? WHERE codigo = ? AND empresa = ? AND finca = ?',
            [$data['activo'], $data['hBrutas'], $data['descripcion'], $codigo, $empresa, $finca]
        );

        $this->insertarAuditoria('UPDATE', array_merge(
            ['empresa' => $empresa, 'finca' => $finca, 'codigo' => $codigo],
            $data
        ), $usu_id);
    }

    public function eliminar(int $empresa, string $finca, string $codigo, ?int $usu_id): void
    {
        $finca  = trim($finca);
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM aSecciones WHERE empresa = ? AND finca = ? AND codigo = ?', [$empresa, $finca, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'finca' => $finca, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $finca, string $codigo, int $activo, ?int $usu_id): void
    {
        $finca  = trim($finca);
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aSecciones SET activo = ? WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$activo, $empresa, $finca, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'finca'   => $finca,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan la sección, con el mismo criterio que listar().
     */
    public function enUso(int $empresa, string $finca, string $codigo): int
    {
        $finca = trim($finca);

        $sql = 'SELECT ISNULL(usos, 0) AS total FROM ' . $this->sqlUsosAgregados(true)
            . ' u WHERE u.empresa = ? AND u.finca = ? AND u.seccion = ?';
        $binds   = $this->usosBinds($empresa, $finca, true);
        $binds[] = $empresa;
        $binds[] = $finca;
        $binds[] = trim($codigo);

        $fila = $this->db->query($sql, $binds)->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aSecciones.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aSecciones',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
