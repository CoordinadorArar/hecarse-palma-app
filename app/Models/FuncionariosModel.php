<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los empleados de nómina (tabla nFuncionario).
 *
 * La llave primaria es compuesta (empresa, tercero), donde "tercero" es el id de
 * cTercero, por lo que las operaciones se resuelven con SQL crudo.
 */
class FuncionariosModel extends Model
{
    protected $table      = 'nFuncionario';
    protected $returnType = 'array';

    private const CAMPOS = 'f.empresa, f.tercero, f.codigo, f.descripcion, f.proveedor, f.salario, f.fechaIngreso,'
        . ' f.activo, f.conductor, f.contratista, f.otros';

    /**
     * La columna proveedor guarda el nit del tercero, pero hay filas legadas que
     * guardan el id; por eso el nombre se resuelve por nit y, si falla, por id.
     */
    private const JOIN_PROVEEDOR = ' LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor'
        . ' LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE'
        . " WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8"
        . ' THEN CONVERT(int, f.proveedor) END';

    /** Nombre legible del tercero, con la razón social como fuente principal. */
    private const NOMBRE_TERCERO = "ISNULL(NULLIF(LTRIM(RTRIM(%1\$s.razonSocial)), ''), %1\$s.descripcion)";

    /** Columnas bit admitidas por el filtro de vínculo. */
    private const VINCULOS = ['contratista' => 'contratista', 'conductor' => 'conductor', 'otros' => 'otros'];

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
     * Lista los empleados de una empresa con el nombre del proveedor resuelto.
     *
     * @param string $estado  '1', '0' o cadena vacía para todos.
     * @param string $vinculo 'contratista', 'conductor', 'otros' o cadena vacía.
     */
    public function listar(int $empresa, string $busqueda = '', string $estado = '', string $vinculo = ''): array
    {
        $sql = 'SELECT ' . self::CAMPOS . ', ISNULL(' . sprintf(self::NOMBRE_TERCERO, 'pn') . ', '
            . sprintf(self::NOMBRE_TERCERO, 'pv') . ') AS proveedorNombre'
            . ' FROM nFuncionario f' . self::JOIN_PROVEEDOR . ' WHERE f.empresa = ?';
        $binds = [$empresa];

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND f.activo = ?';
            $binds[] = (int) $estado;
        }

        if (isset(self::VINCULOS[$vinculo])) {
            $sql .= ' AND f.' . self::VINCULOS[$vinculo] . ' = 1';
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= ' AND (f.codigo LIKE ? OR f.descripcion LIKE ? OR f.proveedor LIKE ?';
            $binds[] = '%' . $busqueda . '%';
            $binds[] = '%' . $busqueda . '%';
            $binds[] = '%' . $busqueda . '%';

            if (ctype_digit($busqueda)) {
                $sql .= ' OR f.tercero = ?';
                $binds[] = (int) $busqueda;
            }

            $sql .= ')';
        }

        $sql .= ' ORDER BY LTRIM(f.descripcion) ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $tercero): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ', ISNULL(' . sprintf(self::NOMBRE_TERCERO, 'pn') . ', '
            . sprintf(self::NOMBRE_TERCERO, 'pv') . ') AS proveedorNombre, t.nit AS terceroNit, '
            . sprintf(self::NOMBRE_TERCERO, 't') . ' AS terceroNombre'
            . ' FROM nFuncionario f' . self::JOIN_PROVEEDOR
            . ' LEFT JOIN cTercero t ON t.empresa = f.empresa AND t.id = f.tercero'
            . ' WHERE f.empresa = ? AND f.tercero = ?',
            [$empresa, $tercero]
        )->getRowArray();
    }

    public function existe(int $empresa, int $tercero): bool
    {
        return $this->obtener($empresa, $tercero) !== null;
    }

    public function crear(int $empresa, int $tercero, array $data, ?int $usu_id): void
    {
        $this->db->query(
            'INSERT INTO nFuncionario (empresa, tercero, codigo, descripcion, proveedor, salario, fechaIngreso,
                activo, conductor, contratista, otros)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa,
                $tercero,
                $data['codigo'],
                $data['descripcion'],
                $data['proveedor'],
                $data['salario'],
                $data['fechaIngreso'],
                $data['activo'],
                $data['conductor'],
                $data['contratista'],
                $data['otros'],
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'tercero' => $tercero], $data), $usu_id);
    }

    public function actualizar(int $empresa, int $tercero, array $data, ?int $usu_id): void
    {
        $this->db->query(
            'UPDATE nFuncionario SET codigo = ?, descripcion = ?, proveedor = ?, salario = ?, fechaIngreso = ?,
                activo = ?, conductor = ?, contratista = ?, otros = ?
             WHERE empresa = ? AND tercero = ?',
            [
                $data['codigo'],
                $data['descripcion'],
                $data['proveedor'],
                $data['salario'],
                $data['fechaIngreso'],
                $data['activo'],
                $data['conductor'],
                $data['contratista'],
                $data['otros'],
                $empresa,
                $tercero,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'tercero' => $tercero], $data), $usu_id);
    }

    public function eliminar(int $empresa, int $tercero, ?int $usu_id): void
    {
        $this->db->query('DELETE FROM nFuncionario WHERE empresa = ? AND tercero = ?', [$empresa, $tercero]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'tercero' => $tercero], $usu_id);
    }

    /**
     * Indica si el empleado tiene contratos o movimientos de nómina asociados.
     */
    public function tieneMovimientos(int $empresa, int $tercero): bool
    {
        $fila = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM nContratos WHERE empresa = ? AND tercero = ?)
              + (SELECT COUNT(*) FROM nCuadrillaFuncionario WHERE empresa = ? AND funcionario = ?)
              + (SELECT COUNT(*) FROM nHorasExtras WHERE empresa = ? AND funcionario = ?)
              + (SELECT COUNT(*) FROM nProgramacion WHERE empresa = ? AND funcionario = ?) AS total',
            [$empresa, $tercero, $empresa, $tercero, $empresa, (string) $tercero, $empresa, (string) $tercero]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0) > 0;
    }

    /**
     * Mapa empresa => terceros activos que todavía no son empleados.
     */
    public function getTercerosDisponiblesPorEmpresa(): array
    {
        $filas = $this->db->query(
            'SELECT t.empresa, t.id, t.nit, ' . sprintf(self::NOMBRE_TERCERO, 't') . ' AS nombre
             FROM cTercero t
             WHERE t.activo = 1 AND NOT EXISTS (
                SELECT 1 FROM nFuncionario f WHERE f.empresa = t.empresa AND f.tercero = t.id)
             ORDER BY t.empresa ASC, nombre ASC'
        )->getResultArray();

        return $this->agruparPorEmpresa($filas, ['id', 'nit', 'nombre']);
    }

    /**
     * Mapa empresa => terceros marcados como proveedor.
     */
    public function getProveedoresPorEmpresa(): array
    {
        $filas = $this->db->query(
            'SELECT t.empresa, t.nit, ' . sprintf(self::NOMBRE_TERCERO, 't') . ' AS nombre
             FROM cTercero t
             WHERE t.proveedor = 1 AND t.activo = 1
             ORDER BY t.empresa ASC, nombre ASC'
        )->getResultArray();

        return $this->agruparPorEmpresa($filas, ['nit', 'nombre']);
    }

    /**
     * Agrupa el catálogo por empresa, dejando vacías las empresas sin registros.
     */
    private function agruparPorEmpresa(array $filas, array $columnas): array
    {
        $mapa = [];

        foreach ($this->getEmpresas() as $e) {
            $mapa[(int) $e['id']] = [];
        }

        foreach ($filas as $f) {
            $mapa[(int) $f['empresa']][] = array_intersect_key($f, array_flip($columnas));
        }

        return $mapa;
    }

    public function terceroExiste(int $empresa, int $tercero): bool
    {
        return $this->obtenerTercero($empresa, $tercero) !== null;
    }

    public function obtenerTercero(int $empresa, int $tercero): ?array
    {
        return $this->db->query(
            'SELECT t.id, t.nit, ' . sprintf(self::NOMBRE_TERCERO, 't') . ' AS nombre
             FROM cTercero t WHERE t.empresa = ? AND t.id = ?',
            [$empresa, $tercero]
        )->getRowArray();
    }

    public function nitTerceroExiste(int $empresa, string $nit): bool
    {
        return $this->db->query(
            'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND nit = ?',
            [$empresa, $nit]
        )->getRowArray() !== null;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla nFuncionario.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'nFuncionario',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
