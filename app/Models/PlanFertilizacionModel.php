<?php

namespace App\Models;

class PlanFertilizacionModel extends FertilizacionTransaccionModel
{
    public const TIPO = 'PFA';

    public function lotesFinca(int $empresa, string $finca, string $seccion = ''): array
    {
        $sql = "SELECT LTRIM(RTRIM(codigo)) AS codigo, descripcion, LTRIM(RTRIM(ISNULL(seccion, ''))) AS seccion,
                       ISNULL(palmasProduccion, 0) AS palmas
                  FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND activo = 1";

        $binds = [$empresa, trim($finca)];

        if (trim($seccion) !== '') {
            $sql    .= ' AND LTRIM(RTRIM(seccion)) = ?';
            $binds[] = trim($seccion);
        }

        $filas = $this->db->query($sql . ' ORDER BY codigo ASC', $binds)->getResultArray();

        foreach ($filas as &$fila) {
            $fila['palmas'] = (float) $fila['palmas'];
        }

        unset($fila);

        return $filas;
    }

    public function palmasLote(int $empresa, string $finca, string $lote): float
    {
        $fila = $this->db->query(
            'SELECT TOP 1 ISNULL(palmasProduccion, 0) AS palmas FROM aLotes
              WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, trim($finca), trim($lote)]
        )->getRowArray();

        return $fila === null ? 0.0 : (float) $fila['palmas'];
    }

    public function usos(int $empresa, string $numero): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) AS n FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = 'RLF' AND LTRIM(RTRIM(t.referencia)) = ? AND ISNULL(t.anulado, 0) = 0" . $this->sinEliminadas('t'),
            [$empresa, trim($numero)]
        )->getRow()->n;
    }

    public function lotesUsados(int $empresa, string $numero): array
    {
        $filas = $this->db->query(
            "SELECT DISTINCT LTRIM(RTRIM(n.lote)) AS lote FROM aTransaccion t
               JOIN aTransaccionNovedad n ON n.empresa = t.empresa AND n.tipo = t.tipo AND n.numero = t.numero
              WHERE t.empresa = ? AND t.tipo = 'RLF' AND LTRIM(RTRIM(t.referencia)) = ? AND ISNULL(t.anulado, 0) = 0" . $this->sinEliminadas('t'),
            [$empresa, trim($numero)]
        )->getResultArray();

        return array_column($filas, 'lote');
    }

    public function detalle(int $empresa, string $numero): ?array
    {
        $binds = [$empresa, static::TIPO, trim($numero)];

        $encabezado = $this->db->query(
            "SELECT LTRIM(RTRIM(t.numero)) AS numero, LTRIM(RTRIM(t.tipo)) AS tipo, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                    CONVERT(varchar(10), t.fechaFinal, 23) AS fechaFinal, t.[año] AS anio, t.mes,
                    LTRIM(RTRIM(ISNULL(t.finca, ''))) AS finca, ISNULL(t.observacion, '') AS observacion,
                    CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                    CASE WHEN EXISTS (SELECT 1 FROM cPeriodo p WHERE p.empresa = t.empresa AND p.[año] = t.[año]
                                         AND p.mes = t.mes AND p.cerrado = 1) THEN 1 ELSE 0 END AS cerrado
               FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND LTRIM(RTRIM(t.numero)) = ?" . $this->sinEliminadas('t'),
            $binds
        )->getRowArray();

        if ($encabezado === null) {
            return null;
        }

        foreach (['anio', 'mes', 'anulado', 'cerrado'] as $c) {
            $encabezado[$c] = (int) $encabezado[$c];
        }

        $encabezado['bloqueada'] = 0;
        $encabezado['usos']      = $this->usos($empresa, $encabezado['numero']);
        $encabezado['usado']     = $encabezado['usos'] > 0 ? 1 : 0;

        $items = $this->db->query(
            "SELECT r.registro, LTRIM(RTRIM(r.lote)) AS lote, ISNULL(l.descripcion, '') AS loteNombre, ISNULL(r.noPalmas, 0) AS palmas,
                    LTRIM(RTRIM(r.item)) AS item, ISNULL(i.descripcion, '') AS itemNombre, LTRIM(RTRIM(ISNULL(r.uMedida, ''))) AS uMedida,
                    ISNULL(r.dosis, 0) AS dosis, ISNULL(r.pBulto, 0) AS pBulto, ISNULL(r.mBulto, 0) AS mBulto, ISNULL(r.cantidad, 0) AS cantidad
               FROM aTransaccionItem r
               OUTER APPLY (SELECT TOP 1 descripcion FROM aLotes
                             WHERE empresa = r.empresa AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(r.lote))) l
               LEFT JOIN iItems i ON i.empresa = r.empresa AND CAST(i.codigo AS varchar(20)) = LTRIM(RTRIM(r.item))
              WHERE r.empresa = ? AND r.tipo = ? AND LTRIM(RTRIM(r.numero)) = ?
              ORDER BY r.registro ASC",
            array_merge([$encabezado['finca']], $binds)
        )->getResultArray();

        foreach ($items as &$item) {
            $item['registro'] = (int) $item['registro'];

            foreach (['palmas', 'dosis', 'pBulto', 'mBulto', 'cantidad'] as $c) {
                $item[$c] = (float) $item[$c];
            }
        }

        unset($item);

        return ['encabezado' => $encabezado, 'items' => $items];
    }

    public function guardar(array $datos, ?int $usu_id): array
    {
        $empresa = (int) $datos['empresa'];

        $this->db->transBegin();

        try {
            $numero = $this->consecutivo($empresa);

            if ($numero === null) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo obtener el consecutivo del tipo de transacción ' . static::TIPO . '.'];
            }

            if ($this->numeroUsado($empresa, $numero)) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'El consecutivo ' . $numero . ' ya está usado por otra transacción. Intente de nuevo.'];
            }

            $registro = date('Y-m-d\TH:i:s');

            $this->db->query(
                'INSERT INTO aTransaccion (empresa, [año], mes, tipo, numero, fecha, fechaFinal, finca, referencia, remision, observacion,
                                           fechaRegistro, usuarioRegistro, anulado, cerrado, jornal, racimos, cantidad, precio, valorTotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0)',
                [$empresa, $datos['anio'], $datos['mes'], static::TIPO, $numero, $datos['fecha'], $datos['fechaFinal'], $datos['finca'],
                 $datos['observacion'], $registro, $datos['usuario']]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar el encabezado del plan.'];
            }

            return $this->cerrar($numero, $datos, null, $usu_id, 'INSERT', 'registrado');
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error registrando el plan de fertilización: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo registrar el plan. La operación fue revertida.'];
        }
    }

    public function actualizar(array $datos, array $antes, ?int $usu_id): array
    {
        $numero = $antes['encabezado']['numero'];

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE aTransaccion SET fecha = ?, fechaFinal = ?, [año] = ?, mes = ?, finca = ?, observacion = ?
                  WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? AND ISNULL(anulado, 0) = 0',
                [$datos['fecha'], $datos['fechaFinal'], $datos['anio'], $datos['mes'], $datos['finca'], $datos['observacion'],
                 $datos['empresa'], static::TIPO, $numero]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar el encabezado del plan.'];
            }

            $this->db->query(
                'DELETE FROM aTransaccionItem WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?',
                [$datos['empresa'], static::TIPO, $numero]
            );

            return $this->cerrar($numero, $datos, $antes, $usu_id, 'UPDATE', 'actualizado');
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando el plan de fertilización: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo actualizar el plan. La operación fue revertida.'];
        }
    }

    private function cerrar(string $numero, array $datos, ?array $antes, ?int $usu_id, string $accion, string $verbo): array
    {
        foreach ($datos['items'] as $i => $item) {
            $this->db->query(
                'INSERT INTO aTransaccionItem (empresa, tipo, numero, registro, lote, [año], mes, fecha, fechaFinal, item, uMedida,
                                               novedad, cantidad, saldo, mBulto, pBulto, noPalmas, dosis, registror)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, 0)',
                [
                    $datos['empresa'], static::TIPO, $numero, $i, $item['lote'], $datos['anio'], $datos['mes'],
                    $datos['fecha'] . 'T00:00:00', $datos['fechaFinal'] . 'T00:00:00', $item['item'], $item['uMedida'],
                    $item['cantidad'], $item['cantidad'], $item['mBulto'], $item['pBulto'], $item['noPalmas'], $item['dosis'],
                ]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar el insumo ' . $item['item'] . ' del lote ' . $item['lote'] . '.'];
            }
        }

        $auditoria = $this->insertarAuditoria($accion, array_filter([
            'empresa' => $datos['empresa'],
            'tipo'    => static::TIPO,
            'numero'  => $numero,
            'antes'   => $antes,
            'datos'   => $datos,
        ], static fn ($v) => $v !== null), $usu_id);

        if (! $auditoria || $this->db->transStatus() === false) {
            $this->db->transRollback();

            return ['success' => false, 'message' => 'No se pudo guardar el plan. La operación fue revertida.'];
        }

        $this->db->transCommit();

        return ['success' => true, 'message' => 'Plan ' . $numero . ' ' . $verbo . ' correctamente.', 'numero' => $numero];
    }
}
