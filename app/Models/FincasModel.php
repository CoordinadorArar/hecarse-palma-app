<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las fincas (tabla aFinca).
 *
 * La llave primaria es compuesta (empresa, codigo), por lo que las operaciones
 * se resuelven con SQL crudo. La columna "ciudad" es char(5), así que se lee
 * siempre con RTRIM y se guarda recortada.
 */
class FincasModel extends Model
{
    protected $table      = 'aFinca';
    protected $returnType = 'array';

    private const CAMPOS = 'f.empresa, f.codigo, f.descripcion, f.proveedor, RTRIM(f.ciudad) AS ciudad,
        f.hectareas, f.zonaGeografica, f.codigoEquivalencia, f.centroOperacion, f.interna, f.socio, f.activo';

    /** Conteo de registros que referencian la finca, correlacionado con el alias f. */
    private const USOS = '(SELECT COUNT(*) FROM aLotes l WHERE l.empresa = f.empresa AND l.finca = f.codigo)'
        . ' + (SELECT COUNT(*) FROM aSecciones s WHERE s.empresa = f.empresa AND s.finca = f.codigo)'
        . ' + (SELECT COUNT(*) FROM aSanidad n WHERE n.empresa = f.empresa AND n.finca = f.codigo)'
        . ' + (SELECT COUNT(*) FROM aTransaccion t WHERE t.empresa = f.empresa AND t.finca = f.codigo)';

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
     * Terceros marcados como proveedor activos, para el selector de propietario.
     */
    public function getPropietarios(int $empresa): array
    {
        return $this->db->query(
            "SELECT id, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
             WHERE empresa = ? AND proveedor = 1 AND activo = 1
             ORDER BY nombre ASC",
            [$empresa]
        )->getResultArray();
    }

    public function propietarioValido(int $empresa, int $id): bool
    {
        return $this->db->query(
            'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND id = ?',
            [$empresa, $id]
        )->getRowArray() !== null;
    }

    /**
     * Lista las fincas de una empresa con el número de registros que las usan.
     *
     * @param string $estado '1', '0' o cadena vacía para todas.
     */
    public function listar(int $empresa, string $estado = ''): array
    {
        $sql = 'SELECT ' . self::CAMPOS
            . ", ISNULL(NULLIF(LTRIM(RTRIM(t.razonSocial)), ''), t.descripcion) AS propietarioNombre"
            . ', ' . self::USOS . ' AS usos'
            . ' FROM aFinca f'
            . ' LEFT JOIN cTercero t ON t.empresa = f.empresa AND t.id = f.proveedor'
            . ' WHERE f.empresa = ?';
        $binds = [$empresa];

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND f.activo = ?';
            $binds[] = (int) $estado;
        }

        $sql .= ' ORDER BY f.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS
            . ", ISNULL(NULLIF(LTRIM(RTRIM(t.razonSocial)), ''), t.descripcion) AS propietarioNombre"
            . ', ' . self::USOS . ' AS usos'
            . ' FROM aFinca f'
            . ' LEFT JOIN cTercero t ON t.empresa = f.empresa AND t.id = f.proveedor'
            . ' WHERE f.empresa = ? AND f.codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray();
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->obtener($empresa, $codigo) !== null;
    }

    public function crear(int $empresa, string $codigo, array $data, ?int $usu_id, string $usuario): void
    {
        $codigo = trim($codigo);
        $hoy    = date('Y-m-d');

        $this->db->query(
            'INSERT INTO aFinca (empresa, codigo, descripcion, proveedor, ciudad, hectareas, zonaGeografica,
                codigoEquivalencia, centroOperacion, interna, socio, activo, fechaRegistro, usuarioRegistro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa,
                $codigo,
                $data['descripcion'],
                $data['proveedor'],
                $data['ciudad'],
                $data['hectareas'],
                $data['zonaGeografica'],
                $data['codigoEquivalencia'],
                $data['centroOperacion'],
                $data['interna'],
                $data['socio'],
                $data['activo'],
                $hoy,
                $usuario,
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'codigo' => $codigo],
            $data,
            ['fechaRegistro' => $hoy, 'usuarioRegistro' => $usuario]
        ), $usu_id);
    }

    public function actualizar(int $empresa, string $codigo, array $data, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aFinca SET descripcion = ?, proveedor = ?, ciudad = ?, hectareas = ?, zonaGeografica = ?,
                codigoEquivalencia = ?, centroOperacion = ?, interna = ?, socio = ?, activo = ?
             WHERE empresa = ? AND codigo = ?',
            [
                $data['descripcion'],
                $data['proveedor'],
                $data['ciudad'],
                $data['hectareas'],
                $data['zonaGeografica'],
                $data['codigoEquivalencia'],
                $data['centroOperacion'],
                $data['interna'],
                $data['socio'],
                $data['activo'],
                $empresa,
                $codigo,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query('DELETE FROM aFinca WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);

        $this->db->query(
            'UPDATE aFinca SET activo = ? WHERE empresa = ? AND codigo = ?',
            [$activo, $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan la finca, con el mismo criterio que listar().
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $fila = $this->db->query(
            'SELECT ' . self::USOS . ' AS total FROM aFinca f WHERE f.empresa = ? AND f.codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aFinca.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aFinca',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
