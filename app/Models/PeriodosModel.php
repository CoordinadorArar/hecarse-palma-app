<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los periodos contables (tabla cPeriodo).
 *
 * La llave primaria es compuesta (empresa, año, mes), por lo que las
 * operaciones se resuelven con SQL crudo. La columna "año" se escribe
 * entre corchetes por contener un carácter no ASCII.
 */
class PeriodosModel extends Model
{
    protected $table      = 'cPeriodo';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, [año] AS anio, mes, descripcion, periodo, cerrado, fechaInicial, fechaFinal';

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

    /**
     * Años con periodos registrados, en orden descendente.
     */
    public function getAnios(): array
    {
        $filas = $this->db->query('SELECT DISTINCT [año] AS anio FROM cPeriodo ORDER BY anio DESC')->getResultArray();

        return array_map(static fn ($f) => (int) $f['anio'], $filas);
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->countAllResults() > 0;
    }

    /**
     * Lista los periodos de una empresa, filtrando por año y término libre.
     *
     * @param int|null $anio Año a filtrar o null para todos.
     */
    public function listar(int $empresa, ?int $anio = null, string $busqueda = ''): array
    {
        $sql   = 'SELECT ' . self::CAMPOS . ' FROM cPeriodo WHERE empresa = ?';
        $binds = [$empresa];

        if ($anio !== null) {
            $sql .= ' AND [año] = ?';
            $binds[] = $anio;
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= ' AND (descripcion LIKE ? OR periodo LIKE ?';
            $binds[] = '%' . $busqueda . '%';
            $binds[] = '%' . $busqueda . '%';

            if (ctype_digit($busqueda)) {
                $sql .= ' OR mes = ?';
                $binds[] = (int) $busqueda;
            }

            $sql .= ')';
        }

        $sql .= ' ORDER BY [año] DESC, mes ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $anio, int $mes): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ' FROM cPeriodo WHERE empresa = ? AND [año] = ? AND mes = ?',
            [$empresa, $anio, $mes]
        )->getRowArray();
    }

    public function existe(int $empresa, int $anio, int $mes): bool
    {
        return $this->obtener($empresa, $anio, $mes) !== null;
    }

    public function crear(int $empresa, int $anio, int $mes, array $data, ?int $usu_id): void
    {
        $this->db->query(
            'INSERT INTO cPeriodo (empresa, [año], mes, descripcion, periodo, cerrado, fechaInicial, fechaFinal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa,
                $anio,
                $mes,
                $data['descripcion'],
                $data['periodo'],
                $data['cerrado'],
                $data['fechaInicial'],
                $data['fechaFinal'],
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'año' => $anio, 'mes' => $mes],
            $data
        ), $usu_id);
    }

    public function actualizar(int $empresa, int $anio, int $mes, array $data, ?int $usu_id): void
    {
        $this->db->query(
            'UPDATE cPeriodo SET descripcion = ?, periodo = ?, cerrado = ?, fechaInicial = ?, fechaFinal = ?
             WHERE empresa = ? AND [año] = ? AND mes = ?',
            [
                $data['descripcion'],
                $data['periodo'],
                $data['cerrado'],
                $data['fechaInicial'],
                $data['fechaFinal'],
                $empresa,
                $anio,
                $mes,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(
            ['empresa' => $empresa, 'año' => $anio, 'mes' => $mes],
            $data
        ), $usu_id);
    }

    public function eliminar(int $empresa, int $anio, int $mes, ?int $usu_id): void
    {
        $this->db->query(
            'DELETE FROM cPeriodo WHERE empresa = ? AND [año] = ? AND mes = ?',
            [$empresa, $anio, $mes]
        );

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'año' => $anio, 'mes' => $mes], $usu_id);
    }

    public function alternarCerrado(int $empresa, int $anio, int $mes, int $cerrado, ?int $usu_id): void
    {
        $this->db->query(
            'UPDATE cPeriodo SET cerrado = ? WHERE empresa = ? AND [año] = ? AND mes = ?',
            [$cerrado, $empresa, $anio, $mes]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'año'     => $anio,
            'mes'     => $mes,
            'cerrado' => $cerrado,
        ], $usu_id);
    }

    /**
     * Meses (1..13) ya registrados para la empresa y el año indicados.
     */
    public function mesesExistentes(int $empresa, int $anio): array
    {
        $filas = $this->db->query(
            'SELECT mes FROM cPeriodo WHERE empresa = ? AND [año] = ?',
            [$empresa, $anio]
        )->getResultArray();

        return array_map(static fn ($f) => (int) $f['mes'], $filas);
    }

    public function cerrarAnio(int $empresa, int $anio, int $cerrado, ?int $usu_id): int
    {
        $this->db->query(
            'UPDATE cPeriodo SET cerrado = ? WHERE empresa = ? AND [año] = ?',
            [$cerrado, $empresa, $anio]
        );

        $afectados = $this->db->affectedRows();

        $this->insertarAuditoria('UPDATE', [
            'empresa'   => $empresa,
            'año'       => $anio,
            'cerrado'   => $cerrado,
            'afectados' => $afectados,
            'resumen'   => 'Cierre/apertura masiva del año',
        ], $usu_id);

        return $afectados;
    }

    public function eliminarAnio(int $empresa, int $anio, ?int $usu_id): int
    {
        $this->db->query(
            'DELETE FROM cPeriodo WHERE empresa = ? AND [año] = ?',
            [$empresa, $anio]
        );

        $afectados = $this->db->affectedRows();

        $this->insertarAuditoria('DELETE', [
            'empresa'   => $empresa,
            'año'       => $anio,
            'afectados' => $afectados,
            'resumen'   => 'Eliminación masiva del año',
        ], $usu_id);

        return $afectados;
    }

    /**
     * Indica si un periodo (YYYYMM) tiene movimientos contabilizados.
     */
    public function tieneMovimientos(int $empresa, ?string $periodo): bool
    {
        if ($periodo === null || $periodo === '') {
            return false;
        }

        $fila = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM cContabilizacion WHERE empresa = ? AND periodoContable = ?)
              + (SELECT COUNT(*) FROM cPrecontabilizacion WHERE empresa = ? AND periodoContable = ?) AS total',
            [$empresa, $periodo, $empresa, $periodo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0) > 0;
    }

    /**
     * Periodos de un año que tienen movimientos contabilizados.
     */
    public function periodosConMovimientos(int $empresa, int $anio): array
    {
        $filas = $this->db->query(
            'SELECT p.periodo FROM cPeriodo p
             WHERE p.empresa = ? AND p.[año] = ? AND p.periodo IS NOT NULL
               AND (EXISTS (SELECT 1 FROM cContabilizacion c WHERE c.empresa = p.empresa AND c.periodoContable = p.periodo)
                 OR EXISTS (SELECT 1 FROM cPrecontabilizacion pc WHERE pc.empresa = p.empresa AND pc.periodoContable = p.periodo))
             ORDER BY p.periodo ASC',
            [$empresa, $anio]
        )->getResultArray();

        return array_map(static fn ($f) => (string) $f['periodo'], $filas);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla cPeriodo.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'cPeriodo',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
