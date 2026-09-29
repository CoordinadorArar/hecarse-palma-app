<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los lotes de cultivo (tabla aLotes) y sus dos subtablas:
 * aLotesCanal (canales de riego/drenaje) y aLotesDetalle (líneas de siembra).
 *
 * La llave primaria de aLotes es compuesta (empresa, codigo, finca), por lo que las operaciones
 * se resuelven con SQL crudo. A diferencia del legado (SpActualizaaLotes/SpDeleteaLotes, que solo
 * filtran por empresa+codigo), aquí siempre se filtra por la PK completa. La columna "añoSiembra"
 * lleva eñe y se escribe siempre entre corchetes. La columna "foto" nunca se toca: no se expone
 * en el formulario y queda fuera de alcance.
 */
class LotesModel extends Model
{
    protected $table      = 'aLotes';
    protected $returnType = 'array';

    private const CAMPOS = 'l.empresa, l.codigo, l.manejaSeccion, l.seccion, l.finca, l.descripcion,
        l.[añoSiembra] AS anioSiembra, l.mesSiembra, l.palmasBrutas, l.palmasProduccion, l.hBrutas, l.hNetas,
        l.dSiembra, RTRIM(l.variedad) AS variedad, l.densidad, l.NoLineas, l.fechaRegistro, l.usuario,
        l.activo, l.desarrollo, l.numero, l.letra, l.ccosto';

    /** Tablas grandes sin índice por "lote" que referencian el lote y sí bloquean el borrado. */
    private const TABLAS_USO = [
        'aTransaccionTercero',
        'aTransaccionNovedad',
        'aLotePesosPeriodo',
        'aTransaccionItem',
        'tmpLiquidacionPepa',
        'atransaccionItemSaldo',
        'aSanidad',
    ];

    /**
     * Subconsulta única con el total de usos por (empresa, lote), agregado de una sola pasada
     * por tabla en vez de una subconsulta correlacionada por cada fila de aLotes. No incluye
     * aLotesCanal ni aLotesDetalle: son hijas del propio lote y se borran en cascada con él.
     */
    private function sqlUsosAgregados(): string
    {
        $partes = [];

        foreach (self::TABLAS_USO as $tabla) {
            $partes[] = "SELECT empresa, lote, COUNT(*) AS n FROM {$tabla}"
                . ' WHERE empresa = ? AND lote IS NOT NULL AND LTRIM(RTRIM(lote)) <> \'\''
                . ' GROUP BY empresa, lote';
        }

        return '(SELECT empresa, lote, SUM(n) AS usos FROM (' . implode(' UNION ALL ', $partes) . ') u GROUP BY empresa, lote)';
    }

    /** Binds de sqlUsosAgregados(), en el mismo orden en que aparecen sus placeholders. */
    private function usosBinds(int $empresa): array
    {
        return array_fill(0, count(self::TABLAS_USO), $empresa);
    }

    /**
     * Totales de aLotesDetalle por lote, en una sola pasada, para contrastar contra los campos
     * tecleados en el maestro (palmasBrutas, palmasProduccion, NoLineas) y señalar el descuadre.
     */
    private function sqlDetalleAgregado(): string
    {
        return '(SELECT empresa, lote, COUNT(*) AS nLineas, SUM(noPalma) AS sumPalmas,
            SUM(noPalma) - SUM(ISNULL(palmaErradicada, 0)) AS sumProduccion
            FROM aLotesDetalle WHERE empresa = ? GROUP BY empresa, lote)';
    }

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

    /** Todas las secciones de la empresa (puede venir vacío); el filtro por finca lo hace la vista. */
    public function getSecciones(int $empresa): array
    {
        return $this->db->query(
            'SELECT finca, codigo, descripcion FROM aSecciones WHERE empresa = ? ORDER BY finca ASC, codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getVariedades(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM aVariedad WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getTiposCanal(int $empresa): array
    {
        return $this->db->query(
            'SELECT codigo, descripcion FROM aTipoCanal WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getCentrosCosto(int $empresa): array
    {
        return $this->db->query(
            "SELECT RTRIM(codigo) AS codigo, descripcion FROM cCentrosCosto
             WHERE empresa = ? AND activo = 1 AND auxiliar = 1 ORDER BY codigo ASC",
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

    public function seccionValida(int $empresa, string $finca, string $seccion): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aSecciones WHERE empresa = ? AND finca = ? AND codigo = ?',
            [$empresa, trim($finca), trim($seccion)]
        )->getRowArray() !== null;
    }

    public function variedadValida(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aVariedad WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function ccostoValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM cCentrosCosto WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function tipoCanalValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aTipoCanal WHERE empresa = ? AND codigo = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    // ── Maestro ──────────────────────────────────────────────────────────────

    /**
     * SELECT base del listado, con los mismos joins agregados de una sola pasada (usos y detalle)
     * más el nombre de la variedad. listar() lo envuelve en una subconsulta para poder filtrar por
     * descuadre usando las columnas ya calculadas, sin una pasada ni subconsultas extra.
     */
    private function sqlBaseListado(): string
    {
        return 'SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', RTRIM(vd.descripcion) AS variedadNombre'
            . ', ISNULL(u.usos, 0) AS usos'
            . ', ISNULL(d.nLineas, 0) AS detalleLineas'
            . ', ISNULL(d.sumPalmas, 0) AS detallePalmas'
            . ', ISNULL(d.sumProduccion, 0) AS detalleProduccion'
            . ' FROM aLotes l'
            . ' LEFT JOIN aFinca f ON f.empresa = l.empresa AND f.codigo = l.finca'
            . ' LEFT JOIN aVariedad vd ON vd.empresa = l.empresa AND vd.codigo = l.variedad'
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = l.empresa AND u.lote = l.codigo'
            . ' LEFT JOIN ' . $this->sqlDetalleAgregado() . ' d ON d.empresa = l.empresa AND d.lote = l.codigo'
            . ' WHERE l.empresa = ?';
    }

    /** Binds de sqlBaseListado(), en el mismo orden en que aparecen sus placeholders. */
    private function bindsBaseListado(int $empresa): array
    {
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $empresa;

        return $binds;
    }

    /**
     * Lista los lotes de una empresa con el número de registros que los usan y los totales
     * del detalle (líneas) para contrastar contra los campos tecleados del maestro.
     *
     * @param string $finca     Código de finca o cadena vacía para todas.
     * @param string $estado    '1', '0' o cadena vacía para todas.
     * @param string $descuadre '1' para solo los lotes con descuadre entre el maestro y el detalle, o cadena vacía para todos.
     */
    public function listar(int $empresa, string $finca = '', string $estado = '', string $descuadre = ''): array
    {
        $finca = trim($finca);

        $sql   = 'SELECT * FROM (' . $this->sqlBaseListado() . ') x WHERE 1 = 1';
        $binds = $this->bindsBaseListado($empresa);

        if ($finca !== '') {
            $sql .= ' AND x.finca = ?';
            $binds[] = $finca;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND x.activo = ?';
            $binds[] = (int) $estado;
        }

        if ($descuadre === '1') {
            $sql .= ' AND (x.palmasBrutas <> x.detallePalmas OR x.palmasProduccion <> x.detalleProduccion OR x.NoLineas <> x.detalleLineas)';
        }

        $sql .= ' ORDER BY x.finca ASC, x.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo, string $finca): ?array
    {
        $codigo = trim($codigo);
        $finca  = trim($finca);

        $sql = 'SELECT ' . self::CAMPOS
            . ', f.descripcion AS fincaDescripcion'
            . ', ISNULL(u.usos, 0) AS usos'
            . ', ISNULL(d.nLineas, 0) AS detalleLineas'
            . ', ISNULL(d.sumPalmas, 0) AS detallePalmas'
            . ', ISNULL(d.sumProduccion, 0) AS detalleProduccion'
            . ' FROM aLotes l'
            . ' LEFT JOIN aFinca f ON f.empresa = l.empresa AND f.codigo = l.finca'
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = l.empresa AND u.lote = l.codigo'
            . ' LEFT JOIN ' . $this->sqlDetalleAgregado() . ' d ON d.empresa = l.empresa AND d.lote = l.codigo'
            . ' WHERE l.empresa = ? AND l.codigo = ? AND l.finca = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $empresa;
        $binds[] = $codigo;
        $binds[] = $finca;

        return $this->db->query($sql, $binds)->getRowArray();
    }

    public function existe(int $empresa, string $codigo, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aLotes WHERE empresa = ? AND codigo = ? AND finca = ?',
            [$empresa, trim($codigo), trim($finca)]
        )->getRowArray() !== null;
    }

    /**
     * Inserta el lote y sus dos subtablas dentro de una única transacción.
     *
     * @return bool false si alguna operación falló y todo fue revertido.
     */
    public function crear(
        int $empresa,
        string $codigo,
        string $finca,
        array $data,
        array $canales,
        ?int $usu_id,
        string $usuario
    ): bool {
        $codigo = trim($codigo);
        $finca  = trim($finca);
        $hoy    = date('Y-m-d');

        $this->db->transStart();

        $ok = $this->db->query(
            'INSERT INTO aLotes (empresa, codigo, manejaSeccion, seccion, finca, descripcion, [añoSiembra],
                mesSiembra, palmasBrutas, palmasProduccion, hBrutas, hNetas, dSiembra, variedad, densidad,
                NoLineas, fechaRegistro, usuario, activo, desarrollo, numero, letra, ccosto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa, $codigo, $data['manejaSeccion'], $data['seccion'], $finca, $data['descripcion'],
                $data['anioSiembra'], 1, $data['palmasBrutas'], $data['palmasProduccion'], $data['hBrutas'],
                $data['hNetas'], $data['dSiembra'], $data['variedad'], $data['densidad'], $data['NoLineas'],
                $hoy, $usuario, $data['activo'], $data['desarrollo'], $data['numero'], $data['letra'], $data['ccosto'],
            ]
        ) !== false;

        if ($ok) {
            $this->guardarCanales($empresa, $codigo, $canales);

            $this->insertarAuditoria('INSERT', array_merge(
                ['empresa' => $empresa, 'codigo' => $codigo, 'finca' => $finca],
                $data,
                ['fechaRegistro' => $hoy, 'usuario' => $usuario, 'canales' => count($canales)]
            ), $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * Actualiza el lote y reemplaza sus canales dentro de una única transacción.
     * No toca finca, codigo, fechaRegistro, usuario ni foto: fuera de alcance o parte de la PK.
     * Las líneas (aLotesDetalle) se administran aparte, fila por fila, con crearLinea()/actualizarLinea()/eliminarLinea().
     *
     * @return bool false si alguna operación falló y todo fue revertido.
     */
    public function actualizar(
        int $empresa,
        string $codigo,
        string $finca,
        array $data,
        array $canales,
        ?int $usu_id
    ): bool {
        $codigo = trim($codigo);
        $finca  = trim($finca);

        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE aLotes SET manejaSeccion = ?, seccion = ?, descripcion = ?, [añoSiembra] = ?, mesSiembra = 1,
                palmasBrutas = ?, palmasProduccion = ?, hBrutas = ?, hNetas = ?, dSiembra = ?, variedad = ?,
                densidad = ?, NoLineas = ?, activo = ?, desarrollo = ?, numero = ?, letra = ?, ccosto = ?
             WHERE empresa = ? AND codigo = ? AND finca = ?',
            [
                $data['manejaSeccion'], $data['seccion'], $data['descripcion'], $data['anioSiembra'],
                $data['palmasBrutas'], $data['palmasProduccion'], $data['hBrutas'], $data['hNetas'],
                $data['dSiembra'], $data['variedad'], $data['densidad'], $data['NoLineas'], $data['activo'],
                $data['desarrollo'], $data['numero'], $data['letra'], $data['ccosto'],
                $empresa, $codigo, $finca,
            ]
        ) !== false;

        if ($ok) {
            $this->guardarCanales($empresa, $codigo, $canales);

            $this->insertarAuditoria('UPDATE', array_merge(
                ['empresa' => $empresa, 'codigo' => $codigo, 'finca' => $finca],
                $data,
                ['canales' => count($canales)]
            ), $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * Borra el lote y sus dos subtablas (hijas, en cascada) dentro de una única transacción.
     *
     * @return bool false si alguna operación falló y todo fue revertido.
     */
    public function eliminar(int $empresa, string $codigo, string $finca, ?int $usu_id): bool
    {
        $codigo = trim($codigo);
        $finca  = trim($finca);

        $this->db->transStart();

        $this->db->query('DELETE FROM aLotesDetalle WHERE empresa = ? AND lote = ? AND finca = ?', [$empresa, $codigo, $finca]);
        $this->db->query('DELETE FROM aLotesCanal WHERE empresa = ? AND lote = ?', [$empresa, $codigo]);

        $ok = $this->db->query(
            'DELETE FROM aLotes WHERE empresa = ? AND codigo = ? AND finca = ?',
            [$empresa, $codigo, $finca]
        ) !== false;

        if ($ok) {
            $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo, 'finca' => $finca], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function cambiarEstado(int $empresa, string $codigo, string $finca, int $activo, ?int $usu_id): void
    {
        $codigo = trim($codigo);
        $finca  = trim($finca);

        $this->db->query(
            'UPDATE aLotes SET activo = ? WHERE empresa = ? AND codigo = ? AND finca = ?',
            [$activo, $empresa, $codigo, $finca]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'finca'   => $finca,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan el lote, con el mismo criterio que listar()/eliminar().
     * No se filtra por finca: ningún código de lote se repite entre fincas (verificado).
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $codigo = trim($codigo);

        $sql = 'SELECT ISNULL(usos, 0) AS total FROM ' . $this->sqlUsosAgregados()
            . ' u WHERE u.empresa = ? AND u.lote = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $codigo;

        $fila = $this->db->query($sql, $binds)->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    // ── Subtabla: canales (aLotesCanal) ─────────────────────────────────────

    public function getCanales(int $empresa, string $lote): array
    {
        return $this->db->query(
            'SELECT c.tipoCanal, RTRIM(t.descripcion) AS descripcion, c.metros
             FROM aLotesCanal c LEFT JOIN aTipoCanal t ON t.empresa = c.empresa AND t.codigo = c.tipoCanal
             WHERE c.empresa = ? AND c.lote = ? ORDER BY c.registro ASC',
            [$empresa, trim($lote)]
        )->getResultArray();
    }

    /**
     * Reemplaza los canales del lote. El "registro" (consecutivo por lote) se recalcula al reinsertar.
     */
    public function guardarCanales(int $empresa, string $lote, array $canales): void
    {
        $lote = trim($lote);

        $this->db->query('DELETE FROM aLotesCanal WHERE empresa = ? AND lote = ?', [$empresa, $lote]);

        $registro = 1;

        foreach ($canales as $c) {
            $this->db->query(
                'INSERT INTO aLotesCanal (empresa, lote, registro, tipoCanal, metros) VALUES (?, ?, ?, ?, ?)',
                [$empresa, $lote, $registro, $c['tipoCanal'], $c['metros']]
            );
            $registro++;
        }
    }

    // ── Subtabla: líneas (aLotesDetalle) ────────────────────────────────────
    // Se administran fila por fila desde un modal aparte (un lote puede tener cientos de líneas).
    // "izquierda" no se expone en la interfaz: al crear se persiste 0 y al actualizar no se toca,
    // así conserva su valor sin necesidad de leerlo antes.

    public function getLineas(int $empresa, string $lote, string $finca): array
    {
        return $this->db->query(
            'SELECT linea, noPalma, palmaErradicada FROM aLotesDetalle WHERE empresa = ? AND lote = ? AND finca = ? ORDER BY linea ASC',
            [$empresa, trim($lote), trim($finca)]
        )->getResultArray();
    }

    /** Totales del detalle de un lote puntual, para el modal de líneas y el aviso de descuadre. */
    public function totalesLineas(int $empresa, string $lote, string $finca): array
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS nLineas, ISNULL(SUM(noPalma), 0) AS sumPalmas,
                ISNULL(SUM(noPalma) - SUM(ISNULL(palmaErradicada, 0)), 0) AS sumProduccion
             FROM aLotesDetalle WHERE empresa = ? AND lote = ? AND finca = ?',
            [$empresa, trim($lote), trim($finca)]
        )->getRowArray();

        return [
            'nLineas'       => (int) ($fila['nLineas'] ?? 0),
            'sumPalmas'     => (int) ($fila['sumPalmas'] ?? 0),
            'sumProduccion' => (int) ($fila['sumProduccion'] ?? 0),
        ];
    }

    public function lineaExiste(int $empresa, string $lote, string $finca, int $linea): bool
    {
        return $this->db->query(
            'SELECT TOP 1 linea FROM aLotesDetalle WHERE empresa = ? AND lote = ? AND finca = ? AND linea = ?',
            [$empresa, trim($lote), trim($finca), $linea]
        )->getRowArray() !== null;
    }

    public function crearLinea(
        int $empresa,
        string $lote,
        string $finca,
        int $linea,
        int $noPalma,
        ?int $palmaErradicada,
        ?int $usu_id
    ): bool {
        $lote  = trim($lote);
        $finca = trim($finca);

        $this->db->transStart();

        $ok = $this->db->query(
            'INSERT INTO aLotesDetalle (empresa, lote, linea, finca, noPalma, izquierda, palmaErradicada)
             VALUES (?, ?, ?, ?, ?, 0, ?)',
            [$empresa, $lote, $linea, $finca, $noPalma, $palmaErradicada]
        ) !== false;

        if ($ok) {
            $this->insertarAuditoriaDetalle('INSERT', [
                'empresa' => $empresa, 'lote' => $lote, 'finca' => $finca, 'linea' => $linea,
                'noPalma' => $noPalma, 'palmaErradicada' => $palmaErradicada,
            ], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /** No incluye "izquierda" en el SET: así conserva su valor sin necesidad de leerlo antes. */
    public function actualizarLinea(
        int $empresa,
        string $lote,
        string $finca,
        int $linea,
        int $noPalma,
        ?int $palmaErradicada,
        ?int $usu_id
    ): bool {
        $lote  = trim($lote);
        $finca = trim($finca);

        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE aLotesDetalle SET noPalma = ?, palmaErradicada = ?
             WHERE empresa = ? AND lote = ? AND finca = ? AND linea = ?',
            [$noPalma, $palmaErradicada, $empresa, $lote, $finca, $linea]
        ) !== false;

        if ($ok) {
            $this->insertarAuditoriaDetalle('UPDATE', [
                'empresa' => $empresa, 'lote' => $lote, 'finca' => $finca, 'linea' => $linea,
                'noPalma' => $noPalma, 'palmaErradicada' => $palmaErradicada,
            ], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function eliminarLinea(int $empresa, string $lote, string $finca, int $linea, ?int $usu_id): bool
    {
        $lote  = trim($lote);
        $finca = trim($finca);

        $this->db->transStart();

        $ok = $this->db->query(
            'DELETE FROM aLotesDetalle WHERE empresa = ? AND lote = ? AND finca = ? AND linea = ?',
            [$empresa, $lote, $finca, $linea]
        ) !== false;

        if ($ok) {
            $this->insertarAuditoriaDetalle('DELETE', [
                'empresa' => $empresa, 'lote' => $lote, 'finca' => $finca, 'linea' => $linea,
            ], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aLotes.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aLotes',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aLotesDetalle.
     */
    public function insertarAuditoriaDetalle(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aLotesDetalle',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
