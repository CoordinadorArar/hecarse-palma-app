<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los precios de labor por lote (tabla aNovedadPrecio).
 *
 * La llave primaria es compuesta de SEIS columnas (empresa, año, novedad, finca, lote, seccion) y
 * es inmutable: al editar solo cambian los valores. La columna "año" lleva eñe, por lo que se
 * escribe siempre entre corchetes, igual que en PreciosLaborModel.
 *
 * "lote" y "seccion" son NOT NULL: cuando el precio aplica a toda la finca, o a la finca y la
 * sección, se guarda CADENA VACÍA, nunca NULL. La función legada fRetornaPrecioLaboresTercero
 * busca literalmente lote = '' y seccion = '' para resolver la cascada
 * finca+seccion+lote → finca+seccion → finca → lista general del año (aNovedadLotePrecio), así que
 * cambiar la cadena vacía por NULL dejaría la fila inalcanzable.
 *
 * "porcentaje" aquí es float, a diferencia de aNovedadLotePrecio donde es decimal(18,3).
 *
 * "novedad" apunta a aNovedad.codigo y se compara siempre con RTRIM, como en LaboresModel; la
 * descripción se resuelve con subconsulta TOP 1 porque la llave de aNovedad incluye el concepto y
 * un JOIN directo por código duplicaría filas. aSecciones está vacía en todas las empresas (0
 * filas) y los lotes tienen manejaSeccion = 0, de modo que hoy la sección siempre viaja vacía.
 */
class PreciosLoteModel extends Model
{
    protected $table      = 'aNovedadPrecio';
    protected $returnType = 'array';

    private const COLUMNAS = 'empresa, [año], novedad, finca, lote, seccion, precioDestajo,
        precioContratistas, precioOtros, porcentaje, fechaRegistro, usuario, modificado, baseSueldo';

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
            'SELECT codigo, descripcion, activo FROM aFinca WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getLotes(int $empresa, string $finca): array
    {
        return $this->db->query(
            'SELECT codigo, descripcion, activo FROM aLotes WHERE empresa = ? AND finca = ? ORDER BY codigo ASC',
            [$empresa, trim($finca)]
        )->getResultArray();
    }

    public function getSecciones(int $empresa, string $finca): array
    {
        return $this->db->query(
            'SELECT codigo, descripcion FROM aSecciones WHERE empresa = ? AND finca = ? ORDER BY codigo ASC',
            [$empresa, trim($finca)]
        )->getResultArray();
    }

    /** Labores activas, agrupadas por código: la llave de aNovedad incluye el concepto. */
    public function getLabores(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, MIN(descripcion) AS descripcion FROM aNovedad
             WHERE empresa = ? AND activo = 1 GROUP BY RTRIM(codigo) ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    /** Años con lista general de precios, que son los que esta pantalla puede sobrescribir. */
    public function getAnios(int $empresa): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT [año] AS anio FROM aNovedadLotePrecio WHERE empresa = ? ORDER BY anio DESC',
            [$empresa]
        )->getResultArray();

        return array_map(static fn ($f) => trim((string) $f['anio']), $filas);
    }

    // ── Validadores ──────────────────────────────────────────────────────────

    public function laborValida(int $empresa, string $novedad): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aNovedad WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($novedad)]
        )->getRowArray() !== null;
    }

    public function fincaValida(int $empresa, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aFinca WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($finca)]
        )->getRowArray() !== null;
    }

    public function loteValido(int $empresa, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aLotes WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($lote)]
        )->getRowArray() !== null;
    }

    public function loteDeFinca(int $empresa, string $finca, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aLotes WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$empresa, trim($finca), trim($lote)]
        )->getRowArray() !== null;
    }

    public function seccionValida(int $empresa, string $finca, string $seccion): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aSecciones WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$empresa, trim($finca), trim($seccion)]
        )->getRowArray() !== null;
    }

    public function totalSecciones(int $empresa, string $finca): int
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM aSecciones WHERE empresa = ? AND finca = ?',
            [$empresa, trim($finca)]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    private function sqlBase(): string
    {
        return "SELECT p.[año] AS anio, RTRIM(p.novedad) AS novedad,
                    ISNULL((SELECT TOP 1 n.descripcion FROM aNovedad n
                             WHERE n.empresa = p.empresa AND RTRIM(n.codigo) = RTRIM(p.novedad)), '') AS descripcion,
                    p.finca, ISNULL(f.descripcion, '') AS fincaDescripcion,
                    p.lote, ISNULL(l.descripcion, '') AS loteDescripcion,
                    p.seccion, ISNULL(s.descripcion, '') AS seccionDescripcion,
                    p.precioDestajo, p.precioContratistas, p.precioOtros, p.porcentaje,
                    ISNULL(p.baseSueldo, 0) AS baseSueldo, p.fechaRegistro, p.usuario
                FROM aNovedadPrecio p
                LEFT JOIN aFinca f ON f.empresa = p.empresa AND f.codigo = p.finca
                LEFT JOIN aLotes l ON l.empresa = p.empresa AND l.finca = p.finca AND l.codigo = p.lote
                LEFT JOIN aSecciones s ON s.empresa = p.empresa AND s.finca = p.finca AND s.codigo = p.seccion
                WHERE p.empresa = ?";
    }

    /**
     * Precios por lote de una empresa, opcionalmente filtrados por año, finca y un texto que
     * busca en el código o la descripción de la labor y en el código del lote.
     */
    public function listar(int $empresa, string $anio = '', string $finca = '', string $busqueda = ''): array
    {
        $sql   = 'SELECT * FROM (' . $this->sqlBase() . ') x WHERE 1 = 1';
        $binds = [$empresa];

        $anio = trim($anio);
        if ($anio !== '') {
            $sql .= ' AND x.anio = ?';
            $binds[] = $anio;
        }

        $finca = trim($finca);
        if ($finca !== '') {
            $sql .= ' AND x.finca = ?';
            $binds[] = $finca;
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $patron = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
            $sql .= " AND (x.novedad LIKE ? ESCAPE '\\' OR x.descripcion LIKE ? ESCAPE '\\' OR x.lote LIKE ? ESCAPE '\\')";
            $binds[] = $patron;
            $binds[] = $patron;
            $binds[] = $patron;
        }

        $sql .= ' ORDER BY x.anio DESC, x.finca ASC, x.lote ASC, x.novedad ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $anio, string $novedad, string $finca, string $lote, string $seccion): ?array
    {
        $sql = 'SELECT * FROM (' . $this->sqlBase() . ') x
                WHERE x.anio = ? AND x.novedad = ? AND x.finca = ? AND x.lote = ? AND x.seccion = ?';

        return $this->db->query($sql, [
            $empresa, trim($anio), trim($novedad), trim($finca), trim($lote), trim($seccion),
        ])->getRowArray();
    }

    public function existe(int $empresa, string $anio, string $novedad, string $finca, string $lote, string $seccion): bool
    {
        return $this->db->query(
            'SELECT TOP 1 novedad FROM aNovedadPrecio
             WHERE empresa = ? AND [año] = ? AND RTRIM(novedad) = ? AND finca = ? AND lote = ? AND seccion = ?',
            [$empresa, trim($anio), trim($novedad), trim($finca), trim($lote), trim($seccion)]
        )->getRowArray() !== null;
    }

    /** Precio de la labor en la lista general del año, que es el que esta pantalla sobrescribe. */
    public function precioBase(int $empresa, string $anio, string $novedad): ?array
    {
        return $this->db->query(
            'SELECT TOP 1 precioDestajo, precioContratistas, precioOtros, porcentaje,
                    ISNULL(baseSueldo, 0) AS baseSueldo
             FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ? AND RTRIM(novedad) = ?',
            [$empresa, trim($anio), trim($novedad)]
        )->getRowArray();
    }

    // ── Escritura ────────────────────────────────────────────────────────────

    public function crear(
        int $empresa,
        string $anio,
        string $novedad,
        string $finca,
        string $lote,
        string $seccion,
        array $data,
        ?int $usu_id,
        string $usuario
    ): bool {
        $novedad = trim($novedad);
        $finca   = trim($finca);
        $lote    = trim($lote);
        $seccion = trim($seccion);
        $hoy     = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'INSERT INTO aNovedadPrecio (' . self::COLUMNAS . ')
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
                [
                    $empresa, trim($anio), $novedad, $finca, $lote, $seccion,
                    $data['precioDestajo'], $data['precioContratistas'], $data['precioOtros'],
                    $data['porcentaje'], $hoy, $usuario, $data['baseSueldo'],
                ]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('INSERT', array_merge(
                ['empresa' => $empresa, 'año' => trim($anio), 'novedad' => $novedad,
                    'finca' => $finca, 'lote' => $lote, 'seccion' => $seccion],
                $data,
                ['fechaRegistro' => $hoy, 'usuario' => $usuario]
            ), $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por lote, crear: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /** La llave de seis columnas es inmutable: solo se actualizan los valores. */
    public function actualizar(
        int $empresa,
        string $anio,
        string $novedad,
        string $finca,
        string $lote,
        string $seccion,
        array $data,
        ?int $usu_id,
        string $usuario
    ): bool {
        $novedad = trim($novedad);
        $finca   = trim($finca);
        $lote    = trim($lote);
        $seccion = trim($seccion);
        $hoy     = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'UPDATE aNovedadPrecio SET precioDestajo = ?, precioContratistas = ?, precioOtros = ?,
                    porcentaje = ?, baseSueldo = ?, modificado = 1, fechaRegistro = ?, usuario = ?
                 WHERE empresa = ? AND [año] = ? AND RTRIM(novedad) = ? AND finca = ? AND lote = ? AND seccion = ?',
                [
                    $data['precioDestajo'], $data['precioContratistas'], $data['precioOtros'],
                    $data['porcentaje'], $data['baseSueldo'], $hoy, $usuario,
                    $empresa, trim($anio), $novedad, $finca, $lote, $seccion,
                ]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('UPDATE', array_merge(
                ['empresa' => $empresa, 'año' => trim($anio), 'novedad' => $novedad,
                    'finca' => $finca, 'lote' => $lote, 'seccion' => $seccion],
                $data,
                ['fechaRegistro' => $hoy, 'usuario' => $usuario]
            ), $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por lote, actualizar: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    public function eliminar(
        int $empresa,
        string $anio,
        string $novedad,
        string $finca,
        string $lote,
        string $seccion,
        ?int $usu_id
    ): bool {
        $novedad = trim($novedad);
        $finca   = trim($finca);
        $lote    = trim($lote);
        $seccion = trim($seccion);

        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'DELETE FROM aNovedadPrecio
                 WHERE empresa = ? AND [año] = ? AND RTRIM(novedad) = ? AND finca = ? AND lote = ? AND seccion = ?',
                [$empresa, trim($anio), $novedad, $finca, $lote, $seccion]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return false;
            }

            $this->insertarAuditoria('DELETE', [
                'empresa' => $empresa, 'año' => trim($anio), 'novedad' => $novedad,
                'finca' => $finca, 'lote' => $lote, 'seccion' => $seccion,
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por lote, eliminar: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aNovedadPrecio.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aNovedadPrecio',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
