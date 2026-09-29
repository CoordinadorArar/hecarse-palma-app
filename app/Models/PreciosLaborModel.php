<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de la lista de precios por labor (tabla aNovedadLotePrecio).
 *
 * La llave primaria es compuesta (empresa, año, novedad, registro) y la columna "año" lleva eñe,
 * por lo que se escribe siempre entre corchetes, igual que "[año]" en PeriodosModel. "registro"
 * no es un lote ni una referencia a otra tabla: es un contador secuencial 0..N-1 dentro de cada
 * (empresa, año) que se asigna al insertar y que nunca se reindexa, para no romper las filas ya
 * guardadas. "novedad" apunta a aNovedad.codigo y se compara siempre con RTRIM, como en
 * LaboresModel. Existen filas huérfanas productivas (años 2024 y 2025) cuyo código ya no está en
 * aNovedad pero que sí tienen movimientos: se listan y se pueden editar, nunca se filtran ni se
 * borran.
 */
class PreciosLaborModel extends Model
{
    protected $table      = 'aNovedadLotePrecio';
    protected $returnType = 'array';

    private const COLUMNAS = 'empresa, [año], novedad, registro, precioDestajo, precioContratistas,
        precioOtros, porcentaje, fechaRegistro, usuario, modificado, baseSueldo';

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

    public function getGrupos(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    /**
     * Años con lista de precios registrada, en orden descendente.
     */
    public function getAnios(int $empresa): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT [año] AS anio FROM aNovedadLotePrecio WHERE empresa = ? ORDER BY anio DESC',
            [$empresa]
        )->getResultArray();

        return array_map(static fn ($f) => trim((string) $f['anio']), $filas);
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /**
     * Resumen por año: labores registradas, cuántas tienen precio de destajo y la última edición.
     */
    public function listarAnios(int $empresa, string $busqueda = ''): array
    {
        $sql = 'SELECT p.[año] AS anio, COUNT(*) AS labores,
                    SUM(CASE WHEN p.precioDestajo <> 0 THEN 1 ELSE 0 END) AS conPrecio,
                    MAX(p.fechaRegistro) AS fechaRegistro,
                    (SELECT TOP 1 u.usuario FROM aNovedadLotePrecio u
                      WHERE u.empresa = p.empresa AND u.[año] = p.[año]
                      ORDER BY u.fechaRegistro DESC, u.registro DESC) AS usuario
                FROM aNovedadLotePrecio p
                WHERE p.empresa = ?';
        $binds = [$empresa];

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= " AND p.[año] LIKE ? ESCAPE '\\'";
            $binds[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
        }

        $sql .= ' GROUP BY p.empresa, p.[año] ORDER BY p.[año] DESC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    /**
     * Detalle de un año: las filas de precio existentes (incluidas las huérfanas, sin descripción)
     * más las labores activas de aNovedad que todavía no tienen precio ese año, con registro nulo.
     */
    public function detalle(int $empresa, string $anio): array
    {
        return $this->db->query(
            "SELECT RTRIM(p.novedad) AS novedad, p.registro, ISNULL(n.descripcion, '') AS descripcion,
                    ISNULL(RTRIM(n.grupo), '') AS grupo, ISNULL(g.descripcion, '') AS grupoDescripcion,
                    ISNULL(RTRIM(n.uMedida), '') AS uMedida,
                    p.precioDestajo, p.precioContratistas, p.precioOtros, p.porcentaje,
                    ISNULL(p.baseSueldo, 0) AS baseSueldo, 1 AS existe,
                    CASE WHEN n.codigo IS NULL THEN 1 ELSE 0 END AS huerfana
             FROM aNovedadLotePrecio p
             LEFT JOIN aNovedad n ON n.empresa = p.empresa AND RTRIM(n.codigo) = RTRIM(p.novedad)
             LEFT JOIN aGrupoNovedad g ON g.empresa = n.empresa AND RTRIM(g.codigo) = RTRIM(n.grupo)
             WHERE p.empresa = ? AND p.[año] = ?
             UNION ALL
             SELECT RTRIM(n.codigo), NULL, n.descripcion,
                    ISNULL(RTRIM(n.grupo), ''), ISNULL(g.descripcion, ''), ISNULL(RTRIM(n.uMedida), ''),
                    0, 0, 0, 0, 0, 0, 0
             FROM aNovedad n
             LEFT JOIN aGrupoNovedad g ON g.empresa = n.empresa AND RTRIM(g.codigo) = RTRIM(n.grupo)
             WHERE n.empresa = ? AND n.activo = 1
               AND NOT EXISTS (SELECT 1 FROM aNovedadLotePrecio x
                                WHERE x.empresa = n.empresa AND x.[año] = ? AND RTRIM(x.novedad) = RTRIM(n.codigo))
             ORDER BY novedad ASC",
            [$empresa, $anio, $empresa, $anio]
        )->getResultArray();
    }

    public function existeAnio(int $empresa, string $anio): bool
    {
        return $this->db->query(
            'SELECT TOP 1 registro FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ?',
            [$empresa, $anio]
        )->getRowArray() !== null;
    }

    /**
     * Mapa de una sola consulta con las filas ya guardadas del año: código recortado =>
     * ['novedad' => valor tal como está almacenado, 'registro' => parte restante de la llave].
     * Evita una consulta por fila al guardar y permite filtrar el UPDATE por la llave completa.
     */
    public function registrosPorNovedad(int $empresa, string $anio): array
    {
        $filas = $this->db->query(
            'SELECT novedad, RTRIM(novedad) AS clave, registro FROM aNovedadLotePrecio
             WHERE empresa = ? AND [año] = ? ORDER BY registro ASC',
            [$empresa, $anio]
        )->getResultArray();

        $mapa = [];

        foreach ($filas as $f) {
            $clave = (string) $f['clave'];

            if (! isset($mapa[$clave])) {
                $mapa[$clave] = ['novedad' => (string) $f['novedad'], 'registro' => (int) $f['registro']];
            }
        }

        return $mapa;
    }

    /**
     * Códigos aceptados al guardar: los de aNovedad más los que ya tienen fila ese año, para no
     * bloquear la edición de las filas huérfanas.
     */
    public function novedadesValidas(int $empresa, string $anio): array
    {
        $filas = $this->db->query(
            'SELECT RTRIM(codigo) AS novedad FROM aNovedad WHERE empresa = ?
             UNION
             SELECT RTRIM(novedad) FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ?',
            [$empresa, $empresa, $anio]
        )->getResultArray();

        return array_map(static fn ($f) => (string) $f['novedad'], $filas);
    }

    public function totalLaboresActivas(int $empresa): int
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM aNovedad WHERE empresa = ? AND activo = 1',
            [$empresa]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Transacciones agrícolas del año, que bloquean el borrado de la lista de precios.
     */
    public function transaccionesAnio(int $empresa, string $anio): int
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM aTransaccion WHERE empresa = ? AND YEAR(fecha) = ?',
            [$empresa, (int) $anio]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    // ── Escritura ────────────────────────────────────────────────────────────

    /**
     * Guarda las filas modificadas dentro de una única transacción: actualiza por la llave
     * completa (empresa, año, novedad, registro) si la novedad ya tiene precio ese año y, si no,
     * inserta tomando el siguiente "registro" libre del año sin reindexar las filas existentes.
     * Las filas ya guardadas se resuelven con una sola consulta previa, no una por fila.
     *
     * @return array|null ['actualizados' => int, 'creados' => int] o null si todo fue revertido.
     */
    public function guardar(int $empresa, string $anio, array $filas, ?int $usu_id, string $usuario): ?array
    {
        $hoy        = date('Y-m-d H:i:s');
        $existentes = $this->registrosPorNovedad($empresa, $anio);
        $creados    = 0;
        $editados   = 0;

        $this->db->transBegin();

        try {
            foreach ($filas as $fila) {
                $actual = $existentes[trim((string) $fila['novedad'])] ?? null;

                if ($actual !== null) {
                    $ok = $this->db->query(
                        'UPDATE aNovedadLotePrecio SET precioDestajo = ?, precioContratistas = ?, precioOtros = ?,
                            porcentaje = ?, baseSueldo = ?, modificado = 1, fechaRegistro = ?, usuario = ?
                         WHERE empresa = ? AND [año] = ? AND novedad = ? AND registro = ?',
                        [
                            $fila['precioDestajo'], $fila['precioContratistas'], $fila['precioOtros'],
                            $fila['porcentaje'], $fila['baseSueldo'], $hoy, $usuario,
                            $empresa, $anio, $actual['novedad'], $actual['registro'],
                        ]
                    ) !== false;

                    $editados++;
                } else {
                    $ok = $this->db->query(
                        'INSERT INTO aNovedadLotePrecio (' . self::COLUMNAS . ')
                         SELECT ?, ?, ?, ISNULL(MAX(registro), -1) + 1, ?, ?, ?, ?, ?, ?, 1, ?
                         FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ?',
                        [
                            $empresa, $anio, $fila['novedad'],
                            $fila['precioDestajo'], $fila['precioContratistas'], $fila['precioOtros'],
                            $fila['porcentaje'], $hoy, $usuario, $fila['baseSueldo'],
                            $empresa, $anio,
                        ]
                    ) !== false;

                    $creados++;
                }

                if (! $ok) {
                    $this->db->transRollback();

                    return null;
                }
            }

            $this->insertarAuditoria('UPDATE', [
                'empresa'      => $empresa,
                'año'          => $anio,
                'usuario'      => $usuario,
                'actualizados' => $editados,
                'creados'      => $creados,
                'novedades'    => array_column($filas, 'novedad'),
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por labor, guardar: ' . $e->getMessage());

            return null;
        }

        return ['actualizados' => $editados, 'creados' => $creados];
    }

    /**
     * Replica la lista de precios de un año en otro, igual que el procedimiento legado
     * spReplicaPrecioLaboresAños: copia registro, precios, porcentaje, la fechaRegistro original,
     * modificado y baseSueldo, y solo cambia el usuario por el que ejecuta la acción.
     *
     * @return int|null Filas insertadas o null si todo fue revertido.
     */
    public function replicarAnio(int $empresa, string $destino, string $origen, ?int $usu_id, string $usuario): ?int
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'INSERT INTO aNovedadLotePrecio (' . self::COLUMNAS . ')
                 SELECT empresa, ?, novedad, registro, precioDestajo, precioContratistas, precioOtros,
                        porcentaje, fechaRegistro, ?, modificado, baseSueldo
                 FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ?',
                [$destino, $usuario, $empresa, $origen]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return null;
            }

            $insertados = $this->db->affectedRows();

            $this->insertarAuditoria('INSERT', [
                'empresa'    => $empresa,
                'año'        => $destino,
                'origen'     => $origen,
                'usuario'    => $usuario,
                'insertados' => $insertados,
                'resumen'    => 'Replicación de la lista de precios desde otro año',
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por labor, replicar año: ' . $e->getMessage());

            return null;
        }

        return $insertados;
    }

    /**
     * Crea la lista del año en ceros, una fila por labor activa, con el "registro" secuencial
     * 0..N-1 asignado por orden de código.
     *
     * @return int|null Filas insertadas o null si todo fue revertido.
     */
    public function crearAnioVacio(int $empresa, string $anio, ?int $usu_id, string $usuario): ?int
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'INSERT INTO aNovedadLotePrecio (' . self::COLUMNAS . ')
                 SELECT n.empresa, ?, RTRIM(n.codigo), ROW_NUMBER() OVER (ORDER BY n.codigo ASC) - 1,
                        0, 0, 0, 0, ?, ?, 1, 0
                 FROM aNovedad n WHERE n.empresa = ? AND n.activo = 1',
                [$anio, date('Y-m-d H:i:s'), $usuario, $empresa]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return null;
            }

            $insertados = $this->db->affectedRows();

            $this->insertarAuditoria('INSERT', [
                'empresa'    => $empresa,
                'año'        => $anio,
                'usuario'    => $usuario,
                'insertados' => $insertados,
                'resumen'    => 'Creación de la lista de precios en ceros',
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por labor, crear año en ceros: ' . $e->getMessage());

            return null;
        }

        return $insertados;
    }

    /**
     * @return int|null Filas eliminadas o null si todo fue revertido.
     */
    public function eliminarAnio(int $empresa, string $anio, ?int $usu_id): ?int
    {
        $this->db->transBegin();

        try {
            $ok = $this->db->query(
                'DELETE FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ?',
                [$empresa, $anio]
            ) !== false;

            if (! $ok) {
                $this->db->transRollback();

                return null;
            }

            $eliminados = $this->db->affectedRows();

            $this->insertarAuditoria('DELETE', [
                'empresa'    => $empresa,
                'año'        => $anio,
                'eliminados' => $eliminados,
                'resumen'    => 'Eliminación masiva de la lista de precios del año',
            ], $usu_id);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Precios por labor, eliminar año: ' . $e->getMessage());

            return null;
        }

        return $eliminados;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aNovedadLotePrecio.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aNovedadLotePrecio',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
