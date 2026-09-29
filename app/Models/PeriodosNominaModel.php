<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los periodos de nómina (tabla nPeriodoDetalle).
 *
 * La llave primaria es compuesta (empresa, año, mes, noPeriodo), por lo que las
 * operaciones se resuelven con SQL crudo. La columna "año" se escribe entre
 * corchetes por contener un carácter no ASCII.
 */
class PeriodosNominaModel extends Model
{
    protected $table      = 'nPeriodoDetalle';
    protected $returnType = 'array';

    private const CAMPOS = 'empresa, [año] AS anio, mes, noPeriodo, fechaInicial, fechaFinal, fechaCorte, fechaPago,'
        . ' cerrado, fechaRegistro, usuario, tipoNomina, diasNomina, agronomico, ejecutaLabores, nombrePeriodo';

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
     * Años con periodos de nómina registrados, en orden descendente.
     */
    public function getAnios(): array
    {
        $filas = $this->db->query('SELECT DISTINCT [año] AS anio FROM nPeriodoDetalle ORDER BY anio DESC')->getResultArray();

        return array_map(static fn ($f) => (int) $f['anio'], $filas);
    }

    /**
     * Tipos de nómina activos de una empresa.
     */
    public function getTiposNomina(int $empresa): array
    {
        return $this->db->query(
            'SELECT codigo, descripcion FROM nTipoNomina WHERE empresa = ? AND activo = 1 ORDER BY descripcion ASC',
            [$empresa]
        )->getResultArray();
    }

    /**
     * Mapa empresa => tipos de nómina activos, resuelto en una sola consulta.
     */
    public function getTiposNominaPorEmpresa(): array
    {
        $filas = $this->db->query(
            'SELECT empresa, codigo, descripcion FROM nTipoNomina WHERE activo = 1 ORDER BY empresa, descripcion'
        )->getResultArray();

        $mapa = [];

        foreach ($filas as $f) {
            $mapa[(int) $f['empresa']][] = ['codigo' => $f['codigo'], 'descripcion' => $f['descripcion']];
        }

        return $mapa;
    }

    public function tipoNominaValido(int $empresa, string $codigo): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM nTipoNomina WHERE empresa = ? AND codigo = ? AND activo = 1',
            [$empresa, $codigo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0) > 0;
    }

    /**
     * Lista los periodos de una empresa, filtrando por año y término libre.
     *
     * @param int|null $anio Año a filtrar o null para todos.
     */
    public function listar(int $empresa, ?int $anio = null, string $busqueda = ''): array
    {
        $sql   = 'SELECT ' . self::CAMPOS . ' FROM nPeriodoDetalle WHERE empresa = ?';
        $binds = [$empresa];

        if ($anio !== null) {
            $sql .= ' AND [año] = ?';
            $binds[] = $anio;
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= ' AND (nombrePeriodo LIKE ? OR tipoNomina LIKE ?';
            $binds[] = '%' . $busqueda . '%';
            $binds[] = '%' . $busqueda . '%';

            if (ctype_digit($busqueda)) {
                $sql .= ' OR mes = ? OR noPeriodo = ? OR diasNomina = ?';
                $binds[] = (int) $busqueda;
                $binds[] = (int) $busqueda;
                $binds[] = (int) $busqueda;
            }

            $sql .= ')';
        }

        $sql .= ' ORDER BY [año] DESC, mes ASC, noPeriodo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $anio, int $mes, int $noPeriodo): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ' FROM nPeriodoDetalle WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?',
            [$empresa, $anio, $mes, $noPeriodo]
        )->getRowArray();
    }

    public function existe(int $empresa, int $anio, int $mes, int $noPeriodo): bool
    {
        return $this->obtener($empresa, $anio, $mes, $noPeriodo) !== null;
    }

    public function crear(int $empresa, int $anio, int $mes, int $noPeriodo, array $data, ?int $usu_id, string $usuario): void
    {
        $fechaRegistro = date('Y-m-d H:i:s');
        $usuario       = mb_substr($usuario, 0, 50);

        $this->db->query(
            'INSERT INTO nPeriodoDetalle (empresa, [año], mes, noPeriodo, fechaInicial, fechaFinal, fechaCorte, fechaPago,
                cerrado, fechaRegistro, usuario, tipoNomina, diasNomina, agronomico, ejecutaLabores, nombrePeriodo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa,
                $anio,
                $mes,
                $noPeriodo,
                $data['fechaInicial'],
                $data['fechaFinal'],
                $data['fechaCorte'],
                $data['fechaPago'],
                $data['cerrado'],
                $fechaRegistro,
                $usuario,
                $data['tipoNomina'],
                $data['diasNomina'],
                $data['agronomico'],
                $data['ejecutaLabores'],
                $data['nombrePeriodo'],
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'año' => $anio, 'mes' => $mes, 'noPeriodo' => $noPeriodo],
            $data,
            ['fechaRegistro' => $fechaRegistro, 'usuario' => $usuario]
        ), $usu_id);
    }

    public function actualizar(int $empresa, int $anio, int $mes, int $noPeriodo, array $data, ?int $usu_id): void
    {
        $this->db->query(
            'UPDATE nPeriodoDetalle SET fechaInicial = ?, fechaFinal = ?, fechaCorte = ?, fechaPago = ?, cerrado = ?,
                tipoNomina = ?, diasNomina = ?, agronomico = ?, ejecutaLabores = ?, nombrePeriodo = ?
             WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?',
            [
                $data['fechaInicial'],
                $data['fechaFinal'],
                $data['fechaCorte'],
                $data['fechaPago'],
                $data['cerrado'],
                $data['tipoNomina'],
                $data['diasNomina'],
                $data['agronomico'],
                $data['ejecutaLabores'],
                $data['nombrePeriodo'],
                $empresa,
                $anio,
                $mes,
                $noPeriodo,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(
            ['empresa' => $empresa, 'año' => $anio, 'mes' => $mes, 'noPeriodo' => $noPeriodo],
            $data
        ), $usu_id);
    }

    public function eliminar(int $empresa, int $anio, int $mes, int $noPeriodo, ?int $usu_id): void
    {
        $this->db->query(
            'DELETE FROM nPeriodoDetalle WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?',
            [$empresa, $anio, $mes, $noPeriodo]
        );

        $this->insertarAuditoria('DELETE', [
            'empresa'   => $empresa,
            'año'       => $anio,
            'mes'       => $mes,
            'noPeriodo' => $noPeriodo,
        ], $usu_id);
    }

    public function alternarCerrado(int $empresa, int $anio, int $mes, int $noPeriodo, int $cerrado, ?int $usu_id): void
    {
        $this->db->query(
            'UPDATE nPeriodoDetalle SET cerrado = ? WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?',
            [$cerrado, $empresa, $anio, $mes, $noPeriodo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa'   => $empresa,
            'año'       => $anio,
            'mes'       => $mes,
            'noPeriodo' => $noPeriodo,
            'cerrado'   => $cerrado,
        ], $usu_id);
    }

    /**
     * Indica si el periodo tiene nómina liquidada o conceptos fijos asociados.
     */
    public function tieneMovimientos(int $empresa, int $anio, int $mes, int $noPeriodo): bool
    {
        $fila = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM nLiquidacionNominaDetalle WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?)
              + (SELECT COUNT(*) FROM nConceptosFijosDetalle WHERE empresa = ? AND [año] = ? AND mes = ? AND noPeriodo = ?) AS total',
            [$empresa, $anio, $mes, $noPeriodo, $empresa, $anio, $mes, $noPeriodo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0) > 0;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla nPeriodoDetalle.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'nPeriodoDetalle',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
