<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración del peso de RFF por lote y período (tabla aLotePesosPeriodo).
 *
 * La llave primaria es compuesta de cinco columnas (empresa, año, mes, finca, lote), por lo que
 * las operaciones se resuelven con SQL crudo. La columna "año" lleva eñe y se escribe siempre
 * entre corchetes. No hay ninguna FK hacia esta tabla, así que el borrado nunca se bloquea.
 * "seccion" es nula en toda la tabla; la pantalla no la administra y persiste NULL sin tocarla.
 */
class PesosLoteModel extends Model
{
    protected $table      = 'aLotePesosPeriodo';
    protected $returnType = 'array';

    private const CAMPOS = 'p.empresa, p.[año] AS anio, p.mes, p.finca, p.lote, p.pesoRacimo,
        p.automatico, p.fechaInicial, p.fechaFinal';

    // ── Catálogos ────────────────────────────────────────────────────────────

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
            'SELECT codigo, descripcion FROM aFinca WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getLotes(int $empresa, string $finca = ''): array
    {
        $finca = trim($finca);
        $sql   = 'SELECT codigo, descripcion, finca FROM aLotes WHERE empresa = ?';
        $binds = [$empresa];

        if ($finca !== '') {
            $sql .= ' AND finca = ?';
            $binds[] = $finca;
        }

        $sql .= ' ORDER BY finca ASC, codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function getAnios(int $empresa): array
    {
        return $this->db->query(
            'SELECT DISTINCT [año] AS anio FROM aLotePesosPeriodo WHERE empresa = ? ORDER BY anio DESC',
            [$empresa]
        )->getResultArray();
    }

    // ── Validadores ──────────────────────────────────────────────────────────

    public function fincaValida(int $empresa, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aFinca WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($finca)]
        )->getRowArray() !== null;
    }

    public function loteValido(int $empresa, string $finca, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aLotes WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$empresa, trim($finca), trim($lote)]
        )->getRowArray() !== null;
    }

    // ── Maestro ──────────────────────────────────────────────────────────────

    /**
     * Lista los pesos de un período (año+mes obligatorios) para no devolver las 10.918 filas
     * de golpe. Resuelve, en la misma consulta, los tres indicadores de problemas reales: mes
     * fuera de 1..13 (el 13 es el cierre de año, igual que en cPeriodo, y no es un problema),
     * año en 0 y lote inexistente (LEFT JOIN a aLotes). pesoRacimo en 0 es un valor legítimo
     * (lote sin cosecha ese mes) y no se marca.
     *
     * Año y mes son opcionales: se omiten del WHERE cuando vienen null. Solo tiene sentido pedir
     * el listado sin periodo cuando $problemas = '1' (las filas con problemas están dispersas en
     * años fuera del periodo actual); esa restricción la exige el controlador, no este método.
     *
     * @param string $finca     Código de finca o cadena vacía para todas.
     * @param string $origen    'auto', 'manual' o cadena vacía para todos.
     * @param string $problemas '1' para solo las filas con algún problema, o cadena vacía para todas.
     */
    public function listar(int $empresa, ?int $anio = null, ?int $mes = null, string $finca = '', string $origen = '', string $problemas = ''): array
    {
        $finca = trim($finca);

        $sql = 'SELECT * FROM (SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', CASE WHEN l.codigo IS NULL THEN 1 ELSE 0 END AS loteInexistente'
            . ', CASE WHEN p.mes < 1 OR p.mes > 13 THEN 1 ELSE 0 END AS mesInvalido'
            . ', CASE WHEN p.[año] = 0 THEN 1 ELSE 0 END AS anioInvalido'
            . ' FROM aLotePesosPeriodo p'
            . ' LEFT JOIN aFinca f ON f.empresa = p.empresa AND f.codigo = p.finca'
            . ' LEFT JOIN aLotes l ON l.empresa = p.empresa AND l.finca = p.finca AND l.codigo = p.lote'
            . ' WHERE p.empresa = ?';
        $binds = [$empresa];

        if ($anio !== null) {
            $sql .= ' AND p.[año] = ?';
            $binds[] = $anio;
        }

        if ($mes !== null) {
            $sql .= ' AND p.mes = ?';
            $binds[] = $mes;
        }

        $sql .= ') x WHERE 1 = 1';

        if ($finca !== '') {
            $sql .= ' AND x.finca = ?';
            $binds[] = $finca;
        }

        if ($origen === 'auto') {
            $sql .= ' AND x.automatico = 1';
        } elseif ($origen === 'manual') {
            $sql .= ' AND x.automatico = 0';
        }

        if ($problemas === '1') {
            $sql .= ' AND (x.loteInexistente = 1 OR x.mesInvalido = 1 OR x.anioInvalido = 1)';
        }

        $sql .= ($anio === null && $mes === null)
            ? ' ORDER BY x.anio DESC, x.mes DESC, x.finca ASC, x.lote ASC'
            : ' ORDER BY x.finca ASC, x.lote ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $anio, int $mes, string $finca, string $lote): ?array
    {
        $finca = trim($finca);
        $lote  = trim($lote);

        $sql = 'SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', CASE WHEN l.codigo IS NULL THEN 1 ELSE 0 END AS loteInexistente'
            . ' FROM aLotePesosPeriodo p'
            . ' LEFT JOIN aFinca f ON f.empresa = p.empresa AND f.codigo = p.finca'
            . ' LEFT JOIN aLotes l ON l.empresa = p.empresa AND l.finca = p.finca AND l.codigo = p.lote'
            . ' WHERE p.empresa = ? AND p.[año] = ? AND p.mes = ? AND p.finca = ? AND p.lote = ?';

        return $this->db->query($sql, [$empresa, $anio, $mes, $finca, $lote])->getRowArray();
    }

    public function existe(int $empresa, int $anio, int $mes, string $finca, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 lote FROM aLotePesosPeriodo WHERE empresa = ? AND [año] = ? AND mes = ? AND finca = ? AND lote = ?',
            [$empresa, $anio, $mes, trim($finca), trim($lote)]
        )->getRowArray() !== null;
    }

    public function crear(int $empresa, int $anio, int $mes, string $finca, string $lote, array $data, ?int $usu_id): void
    {
        $finca = trim($finca);
        $lote  = trim($lote);

        $this->db->query(
            'INSERT INTO aLotePesosPeriodo (empresa, [año], mes, finca, lote, seccion, pesoRacimo, automatico, fechaInicial, fechaFinal)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)',
            [$empresa, $anio, $mes, $finca, $lote, $data['pesoRacimo'], $data['automatico'], $data['fechaInicial'], $data['fechaFinal']]
        );

        $this->insertarAuditoria('INSERT', array_merge(
            ['empresa' => $empresa, 'anio' => $anio, 'mes' => $mes, 'finca' => $finca, 'lote' => $lote],
            $data
        ), $usu_id);
    }

    public function actualizar(int $empresa, int $anio, int $mes, string $finca, string $lote, array $data, ?int $usu_id): void
    {
        $finca = trim($finca);
        $lote  = trim($lote);

        $this->db->query(
            'UPDATE aLotePesosPeriodo SET pesoRacimo = ?, automatico = ?, fechaInicial = ?, fechaFinal = ?
             WHERE empresa = ? AND [año] = ? AND mes = ? AND finca = ? AND lote = ?',
            [$data['pesoRacimo'], $data['automatico'], $data['fechaInicial'], $data['fechaFinal'], $empresa, $anio, $mes, $finca, $lote]
        );

        $this->insertarAuditoria('UPDATE', array_merge(
            ['empresa' => $empresa, 'anio' => $anio, 'mes' => $mes, 'finca' => $finca, 'lote' => $lote],
            $data
        ), $usu_id);
    }

    public function eliminar(int $empresa, int $anio, int $mes, string $finca, string $lote, ?int $usu_id): void
    {
        $finca = trim($finca);
        $lote  = trim($lote);

        $this->db->query(
            'DELETE FROM aLotePesosPeriodo WHERE empresa = ? AND [año] = ? AND mes = ? AND finca = ? AND lote = ?',
            [$empresa, $anio, $mes, $finca, $lote]
        );

        $this->insertarAuditoria('DELETE', [
            'empresa' => $empresa, 'anio' => $anio, 'mes' => $mes, 'finca' => $finca, 'lote' => $lote,
        ], $usu_id);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aLotePesosPeriodo.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aLotePesosPeriodo',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
