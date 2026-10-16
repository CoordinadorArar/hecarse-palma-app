<?php

namespace App\Models;

class PlaneacionModel extends LaboresTransaccionModel
{
    public const ESTADOS = ['COMPLETADA', 'PARCIAL', 'NO_EJECUTADA'];

    protected const RUTA = 'transacciones/planeacion';

    private ?bool $horas = null;

    public function columnasHoras(): bool
    {
        if ($this->horas === null) {
            $this->horas = (int) $this->db->query(
                "SELECT CASE WHEN COL_LENGTH('pPlaneacionLinea', 'horaInicio') IS NOT NULL
                              AND COL_LENGTH('pPlaneacionLinea', 'horaFin') IS NOT NULL THEN 1 ELSE 0 END AS existe"
            )->getRow()->existe === 1;
        }

        return $this->horas;
    }

    public function labores(int $empresa, string $clase = ''): array
    {
        return $this->laboresActivas($empresa, '');
    }

    public function labor(int $empresa, string $codigo): ?array
    {
        return $this->laboresActivas($empresa, trim($codigo))[0] ?? null;
    }

    private function laboresActivas(int $empresa, string $codigo): array
    {
        $filas = $this->db->query(
            "SELECT RTRIM(codigo) AS codigo, MIN(descripcion) AS descripcion, MIN(RTRIM(ISNULL(uMedida, ''))) AS uMedida,
                    MIN(LTRIM(RTRIM(grupo))) AS grupo, MIN(ISNULL(claseLabor, 0)) AS claseLabor
               FROM aNovedad WHERE empresa = ? AND activo = 1" . ($codigo === '' ? '' : ' AND RTRIM(codigo) = ?') . '
              GROUP BY RTRIM(codigo) ORDER BY codigo ASC',
            $codigo === '' ? [$empresa] : [$empresa, $codigo]
        )->getResultArray();

        foreach ($filas as &$fila) {
            $fila['claseLabor'] = (int) $fila['claseLabor'];
        }

        unset($fila);

        return $filas;
    }

    public function tercero(int $empresa, string $nit): ?array
    {
        return $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(nit)) AS nit, ISNULL(LTRIM(RTRIM(codigoEquivalencia)), '') AS codigo,
                    ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), ISNULL(descripcion, '')) AS nombre
               FROM cTercero WHERE empresa = ? AND activo = 1 AND LTRIM(RTRIM(nit)) = ?",
            [$empresa, trim($nit)]
        )->getRowArray();
    }

    public function plan(int $empresa, string $fecha): ?array
    {
        return $this->completo('empresa = ? AND fecha = ?', [$empresa, $fecha]);
    }

    public function planPorId(int $id): ?array
    {
        return $this->completo('id = ?', [$id]);
    }

    public function linea(int $id): ?array
    {
        return $this->lineas('l.id = ?', [$id])[0] ?? null;
    }

    public function pendientes(int $empresa, string $fecha): array
    {
        return $this->lineas(
            "p.empresa = ? AND p.fecha < ? AND p.fecha >= DATEADD(day, -15, CAST(? AS date)) AND l.estado IN ('PARCIAL', 'NO_EJECUTADA')
             AND l.cantidad - ISNULL(l.cantidadEjecutada, 0) > 0 AND NOT EXISTS (SELECT 1 FROM pPlaneacionLinea x WHERE x.origen = l.id)",
            [$empresa, $fecha, $fecha]
        );
    }

    private function completo(string $filtro, array $binds): ?array
    {
        $plan = $this->db->query(
            "SELECT id, empresa, CONVERT(varchar(10), fecha, 23) AS fecha, estado, ISNULL(observacion, '') AS observacion,
                    usuarioRegistro, CONVERT(varchar(16), fechaRegistro, 120) AS fechaRegistro,
                    ISNULL(usuarioModificacion, '') AS usuarioModificacion,
                    ISNULL(CONVERT(varchar(16), fechaModificacion, 120), '') AS fechaModificacion
               FROM pPlaneacion WHERE " . $filtro,
            $binds
        )->getRowArray();

        if ($plan === null) {
            return null;
        }

        $plan['id']      = (int) $plan['id'];
        $plan['empresa'] = (int) $plan['empresa'];

        $plan['ausencias'] = $this->db->query(
            "SELECT a.tercero, ISNULL(c.codigo, '') AS codigo, ISNULL(c.nombre, '') AS nombre, ISNULL(a.motivo, '') AS motivo,
                    a.usuario, CONVERT(varchar(16), a.fecha, 120) AS fecha
               FROM pPlaneacionAusencia a
               OUTER APPLY (SELECT TOP 1 ISNULL(LTRIM(RTRIM(codigoEquivalencia)), '') AS codigo,
                                   ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
                              FROM cTercero WHERE empresa = ? AND LTRIM(RTRIM(nit)) = a.tercero) c
              WHERE a.planeacion = ? ORDER BY nombre",
            [$plan['empresa'], $plan['id']]
        )->getResultArray();

        $ausentes             = array_column($plan['ausencias'], 'tercero');
        $plan['actividades']  = $this->lineas('l.planeacion = ?', [$plan['id']], $ausentes);
        $estados              = array_count_values(array_column($plan['actividades'], 'estado'));
        $trabajadores         = [];

        foreach ($plan['actividades'] as $actividad) {
            foreach ($actividad['trabajadores'] as $trabajador) {
                $trabajadores[$trabajador['tercero']] = true;
            }
        }

        $plan['resumen'] = [
            'actividades'  => count($plan['actividades']),
            'pendientes'   => $estados['PENDIENTE'] ?? 0,
            'completadas'  => $estados['COMPLETADA'] ?? 0,
            'parciales'    => $estados['PARCIAL'] ?? 0,
            'noEjecutadas' => $estados['NO_EJECUTADA'] ?? 0,
            'vinculadas'   => count(array_filter(array_column($plan['actividades'], 'vinculada'))),
            'trabajadores' => count($trabajadores),
            'ausentes'     => count($ausentes),
        ];

        return $plan;
    }

    private function lineas(string $filtro, array $binds, array $ausentes = []): array
    {
        $lineas = $this->db->query(
            "SELECT l.id, l.planeacion, p.empresa, CONVERT(varchar(10), p.fecha, 23) AS fecha, p.estado AS planEstado, l.orden,
                    l.finca, ISNULL(f.descripcion, '') AS fincaNombre, l.lote, ISNULL(lo.descripcion, '') AS loteNombre,
                    ISNULL(lo.seccion, '') AS seccion, l.novedad, ISNULL(n.descripcion, '') AS novedadNombre,
                    ISNULL(n.grupo, '') AS grupo, ISNULL(n.claseLabor, 0) AS claseLabor, l.uMedida, l.cantidad,
                    " . ($this->columnasHoras()
                        ? "ISNULL(CONVERT(varchar(5), l.horaInicio, 108), '') AS horaInicio, ISNULL(CONVERT(varchar(5), l.horaFin, 108), '') AS horaFin"
                        : "'' AS horaInicio, '' AS horaFin") . ", ISNULL(l.observacion, '') AS observacion, ISNULL(l.referencia, '') AS referencia,
                    l.estado, l.cantidadEjecutada, ISNULL(l.observacionCierre, '') AS observacionCierre,
                    ISNULL(l.usuarioCierre, '') AS usuarioCierre, ISNULL(CONVERT(varchar(16), l.fechaCierre, 120), '') AS fechaCierre,
                    l.origen, CONVERT(varchar(10), po.fecha, 23) AS origenFecha,
                    ISNULL(l.tipoTransaccion, '') AS tipoTransaccion, ISNULL(l.numeroTransaccion, '') AS numeroTransaccion,
                    CASE WHEN EXISTS (SELECT 1 FROM pPlaneacionLinea x WHERE x.origen = l.id) THEN 1 ELSE 0 END AS trasladada
               FROM pPlaneacionLinea l
               JOIN pPlaneacion p ON p.id = l.planeacion
               LEFT JOIN pPlaneacionLinea lx ON lx.id = l.origen
               LEFT JOIN pPlaneacion po ON po.id = lx.planeacion
               OUTER APPLY (SELECT TOP 1 descripcion FROM aFinca WHERE empresa = p.empresa AND LTRIM(RTRIM(codigo)) = l.finca) f
               OUTER APPLY (SELECT TOP 1 descripcion, LTRIM(RTRIM(ISNULL(seccion, ''))) AS seccion FROM aLotes
                             WHERE empresa = p.empresa AND LTRIM(RTRIM(finca)) = l.finca AND LTRIM(RTRIM(codigo)) = l.lote) lo
               OUTER APPLY (SELECT MIN(descripcion) AS descripcion, MIN(LTRIM(RTRIM(grupo))) AS grupo, MIN(claseLabor) AS claseLabor
                              FROM aNovedad WHERE empresa = p.empresa AND RTRIM(codigo) = l.novedad) n
              WHERE " . $filtro . '
              ORDER BY p.fecha, l.orden, l.id',
            $binds
        )->getResultArray();

        if ($lineas === []) {
            return [];
        }

        $porLinea = [];

        foreach ($this->db->query(
            "SELECT t.id, t.linea, t.tercero, ISNULL(c.codigo, '') AS codigo, ISNULL(c.nombre, '') AS nombre, t.cantidad,
                    ISNULL(t.reasignadoDe, '') AS reasignadoDe, t.orden
               FROM pPlaneacionTrabajador t
               JOIN pPlaneacionLinea l ON l.id = t.linea
               JOIN pPlaneacion p ON p.id = l.planeacion
               OUTER APPLY (SELECT TOP 1 ISNULL(LTRIM(RTRIM(codigoEquivalencia)), '') AS codigo,
                                   ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
                              FROM cTercero WHERE empresa = p.empresa AND LTRIM(RTRIM(nit)) = t.tercero) c
              WHERE " . $filtro . '
              ORDER BY t.linea, t.orden, t.id',
            $binds
        )->getResultArray() as $trabajador) {
            $linea = (int) $trabajador['linea'];
            unset($trabajador['linea']);

            $trabajador['id']       = (int) $trabajador['id'];
            $trabajador['orden']    = (int) $trabajador['orden'];
            $trabajador['cantidad'] = (float) $trabajador['cantidad'];
            $trabajador['ausente']  = in_array($trabajador['tercero'], $ausentes, true) ? 1 : 0;

            $porLinea[$linea][] = $trabajador;
        }

        foreach ($lineas as &$linea) {
            foreach (['id', 'planeacion', 'empresa', 'orden', 'claseLabor', 'trasladada'] as $c) {
                $linea[$c] = (int) $linea[$c];
            }

            $linea['origen']            = $linea['origen'] === null ? null : (int) $linea['origen'];
            $linea['cantidad']          = (float) $linea['cantidad'];
            $linea['cantidadEjecutada'] = $linea['cantidadEjecutada'] === null ? null : (float) $linea['cantidadEjecutada'];
            $linea['saldo']             = in_array($linea['estado'], ['PARCIAL', 'NO_EJECUTADA'], true)
                ? round($linea['cantidad'] - (float) $linea['cantidadEjecutada'], 2) : 0.0;
            $linea['esCosecha']         = $linea['claseLabor'] === 2 ? 1 : 0;
            $linea['tipoDestino']       = $linea['esCosecha'] === 1 ? null : ($linea['grupo'] === FertilizacionTransaccionModel::GRUPO ? 'RLF' : 'TLA');
            $linea['destino']           = $linea['tipoDestino'] === null ? null : ($linea['tipoDestino'] === 'RLF' ? 'fertilizacion' : 'labores');
            $linea['vinculada']         = $linea['numeroTransaccion'] === '' ? 0 : 1;
            $linea['registrable']       = $linea['tipoDestino'] !== null && $linea['vinculada'] === 0
                && in_array($linea['estado'], ['COMPLETADA', 'PARCIAL'], true) && (float) $linea['cantidadEjecutada'] > 0 ? 1 : 0;
            $linea['trabajadores']      = $porLinea[$linea['id']] ?? [];
        }

        unset($linea);

        return $lineas;
    }

    public function guardarPlan(array $datos, ?array $antes, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($datos, $antes) {
            if ($antes === null) {
                $plan = $this->insertarId('pPlaneacion', [
                    'empresa'         => $datos['empresa'],
                    'fecha'           => $datos['fecha'],
                    'estado'          => 'ABIERTA',
                    'observacion'     => $datos['observacion'],
                    'usuarioRegistro' => $datos['usuario'],
                    'fechaRegistro'   => date('Y-m-d\TH:i:s'),
                ]);

                if ($plan === 0) {
                    return 'No se pudo registrar el plan.';
                }
            } else {
                $plan = $antes['id'];

                $this->db->query("UPDATE pPlaneacion SET observacion = ? WHERE id = ? AND estado = 'ABIERTA'", [$datos['observacion'], $plan]);

                if ($this->db->affectedRows() !== 1) {
                    return 'El plan ya no está abierto.';
                }
            }

            if ($datos['eliminar'] !== []) {
                $this->db->query(
                    "DELETE FROM pPlaneacionLinea WHERE planeacion = ? AND estado = 'PENDIENTE' AND numeroTransaccion IS NULL
                        AND id IN (" . implode(', ', array_fill(0, count($datos['eliminar']), '?')) . ')',
                    array_merge([$plan], $datos['eliminar'])
                );

                if ($this->db->affectedRows() !== count($datos['eliminar'])) {
                    return 'Una de las actividades a eliminar ya fue cerrada o registrada por otro usuario; recargue el plan.';
                }
            }

            foreach ($datos['actividades'] as $actividad) {
                if ($actividad['id'] === null) {
                    $error = $this->insertarLinea($plan, $actividad, null);
                } else {
                    $this->db->query(
                        "UPDATE pPlaneacionLinea SET orden = ?, finca = ?, lote = ?, novedad = ?, uMedida = ?, cantidad = ?, horaInicio = ?, horaFin = ?,
                                observacion = ?, referencia = ?
                          WHERE id = ? AND planeacion = ? AND estado = 'PENDIENTE'",
                        [$actividad['orden'], $actividad['finca'], $actividad['lote'], $actividad['novedad'], $actividad['uMedida'],
                         $actividad['cantidad'], $actividad['horaInicio'], $actividad['horaFin'], $actividad['observacion'], $actividad['referencia'], $actividad['id'], $plan]
                    );

                    if ($this->db->affectedRows() !== 1) {
                        return 'La actividad ' . $actividad['orden'] . ' ya fue cerrada o eliminada por otro usuario.';
                    }

                    $this->db->query('DELETE FROM pPlaneacionTrabajador WHERE linea = ?', [$actividad['id']]);

                    $error = $this->insertarTrabajadores($actividad['id'], $actividad['trabajadores']);
                }

                if ($error !== null) {
                    return $error;
                }
            }

            return $plan;
        }, $antes === null ? 'INSERT' : 'UPDATE', ['accion' => 'guardar', 'antes' => $antes, 'datos' => $datos], $usu_id, $datos['usuario'],
            'Plan del ' . $datos['fecha'] . ' guardado correctamente.');
    }

    public function trasladar(array $datos, ?array $antes, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($datos, $antes) {
            $plan = $antes['id'] ?? 0;

            if ($antes === null) {
                $plan = $this->insertarId('pPlaneacion', [
                    'empresa'         => $datos['empresa'],
                    'fecha'           => $datos['fecha'],
                    'estado'          => 'ABIERTA',
                    'observacion'     => null,
                    'usuarioRegistro' => $datos['usuario'],
                    'fechaRegistro'   => date('Y-m-d\TH:i:s'),
                ]);

                if ($plan === 0) {
                    return 'No se pudo registrar el plan.';
                }
            }

            foreach ($datos['lineas'] as $linea) {
                if ($error = $this->insertarLinea($plan, $linea, $linea['origen'])) {
                    return $error;
                }
            }

            return $plan;
        }, 'UPDATE', ['accion' => 'trasladar', 'datos' => $datos], $usu_id, $datos['usuario'], 'Pendientes trasladados al plan del ' . $datos['fecha'] . '.');
    }

    public function marcarAusencia(int $plan, string $tercero, string $motivo, string $usuario, array $afectadas, array $reparto, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($plan, $tercero, $motivo, $usuario, $afectadas, $reparto) {
            $this->db->query(
                'INSERT INTO pPlaneacionAusencia (planeacion, tercero, motivo, usuario, fecha) VALUES (?, ?, ?, ?, ?)',
                [$plan, $tercero, $motivo === '' ? null : $motivo, $usuario, date('Y-m-d\TH:i:s')]
            );

            foreach ($afectadas as $linea) {
                $this->db->query('DELETE FROM pPlaneacionTrabajador WHERE linea = ? AND tercero = ?', [$linea, $tercero]);

                foreach ($reparto[$linea] ?? [] as $asignacion) {
                    $this->db->query(
                        'UPDATE pPlaneacionTrabajador SET cantidad = cantidad + ? WHERE linea = ? AND tercero = ?',
                        [$asignacion['cantidad'], $linea, $asignacion['tercero']]
                    );

                    if ($this->db->affectedRows() === 0) {
                        $this->db->query(
                            'INSERT INTO pPlaneacionTrabajador (linea, tercero, cantidad, reasignadoDe, orden)
                             SELECT ?, ?, ?, ?, ISNULL(MAX(orden), 0) + 1 FROM pPlaneacionTrabajador WHERE linea = ?',
                            [$linea, $asignacion['tercero'], $asignacion['cantidad'], $tercero, $linea]
                        );
                    }
                }

                $this->db->query(
                    "UPDATE pPlaneacionLinea SET cantidad = (SELECT ISNULL(SUM(cantidad), 0) FROM pPlaneacionTrabajador WHERE linea = ?)
                      WHERE id = ? AND planeacion = ? AND estado = 'PENDIENTE'",
                    [$linea, $linea, $plan]
                );

                if ($this->db->affectedRows() !== 1) {
                    return 'Una de las actividades del trabajador ya fue cerrada por otro usuario.';
                }
            }

            return $plan;
        }, 'UPDATE', ['accion' => 'ausencia', 'planeacion' => $plan, 'tercero' => $tercero, 'motivo' => $motivo, 'reparto' => $reparto], $usu_id, $usuario,
            'Ausencia registrada correctamente.');
    }

    public function quitarAusencia(int $plan, string $tercero, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($plan, $tercero) {
            $this->db->query('DELETE FROM pPlaneacionAusencia WHERE planeacion = ? AND tercero = ?', [$plan, $tercero]);

            return $this->db->affectedRows() === 1 ? $plan : 'El trabajador no está marcado como ausente.';
        }, 'UPDATE', ['accion' => 'quitarAusencia', 'planeacion' => $plan, 'tercero' => $tercero], $usu_id, $usuario, 'El trabajador quedó presente.');
    }

    public function cerrarLinea(array $linea, string $estado, float $ejecutada, string $observacion, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($linea, $estado, $ejecutada, $observacion, $usuario) {
            $this->db->query(
                "UPDATE l SET estado = ?, cantidadEjecutada = ?, observacionCierre = ?, usuarioCierre = ?, fechaCierre = ?
                   FROM pPlaneacionLinea l JOIN pPlaneacion p ON p.id = l.planeacion
                  WHERE l.id = ? AND l.estado = 'PENDIENTE' AND p.estado = 'ABIERTA'",
                [$estado, $ejecutada, $observacion === '' ? null : $observacion, $usuario, date('Y-m-d\TH:i:s'), $linea['id']]
            );

            return $this->db->affectedRows() === 1 ? $linea['planeacion'] : 'La actividad ya fue cerrada por otro usuario.';
        }, 'UPDATE', ['accion' => 'cerrarActividad', 'antes' => $linea, 'estado' => $estado, 'cantidadEjecutada' => $ejecutada, 'observacion' => $observacion],
            $usu_id, $usuario, 'Actividad cerrada como ' . $estado . '.');
    }

    public function reabrirLinea(array $linea, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($linea) {
            $this->db->query(
                "UPDATE l SET estado = 'PENDIENTE', cantidadEjecutada = NULL, observacionCierre = NULL, usuarioCierre = NULL, fechaCierre = NULL
                   FROM pPlaneacionLinea l JOIN pPlaneacion p ON p.id = l.planeacion
                  WHERE l.id = ? AND l.estado <> 'PENDIENTE' AND p.estado = 'ABIERTA' AND l.numeroTransaccion IS NULL
                    AND NOT EXISTS (SELECT 1 FROM pPlaneacionLinea x WHERE x.origen = l.id)",
                [$linea['id']]
            );

            return $this->db->affectedRows() === 1 ? $linea['planeacion'] : 'La actividad no se puede reabrir.';
        }, 'UPDATE', ['accion' => 'reabrirActividad', 'antes' => $linea], $usu_id, $usuario, 'Actividad reabierta.');
    }

    public function estadoPlan(array $plan, string $estado, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($plan, $estado) {
            $this->db->query(
                'UPDATE pPlaneacion SET estado = ? WHERE id = ? AND estado <> ?' . ($estado === 'CERRADA'
                    ? " AND NOT EXISTS (SELECT 1 FROM pPlaneacionLinea WHERE planeacion = pPlaneacion.id AND estado = 'PENDIENTE')" : ''),
                [$estado, $plan['id'], $estado]
            );

            return $this->db->affectedRows() === 1 ? $plan['id'] : 'No se pudo cambiar el estado del plan.';
        }, 'UPDATE', ['accion' => $estado === 'CERRADA' ? 'cerrarPlan' : 'reabrirPlan', 'planeacion' => $plan['id'], 'fecha' => $plan['fecha']],
            $usu_id, $usuario, $estado === 'CERRADA' ? 'Plan cerrado.' : 'Plan reabierto.');
    }

    public function eliminarPlan(array $plan, string $usuario, ?int $usu_id): array
    {
        return $this->transaccion(function () use ($plan) {
            $this->db->query(
                "DELETE FROM pPlaneacion WHERE id = ? AND NOT EXISTS (SELECT 1 FROM pPlaneacionLinea l WHERE l.planeacion = pPlaneacion.id
                    AND (l.estado <> 'PENDIENTE' OR l.numeroTransaccion IS NOT NULL))",
                [$plan['id']]
            );

            return $this->db->affectedRows() === 1 ? $plan['id'] : 'El plan tiene actividades cerradas o registradas y no se puede eliminar.';
        }, 'DELETE', ['accion' => 'eliminar', 'antes' => $plan], $usu_id, $usuario, 'Plan del ' . $plan['fecha'] . ' eliminado.');
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'pPlaneacion',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }

    private function insertarId(string $tabla, array $fila): int
    {
        return $this->db->table($tabla)->insert($fila) ? (int) $this->db->insertID() : 0;
    }

    private function insertarLinea(int $plan, array $actividad, ?int $origen): ?string
    {
        $orden = $actividad['orden'] ?? null;

        if ($origen !== null && $this->db->query(
            'SELECT TOP 1 id FROM pPlaneacionLinea WITH (UPDLOCK, HOLDLOCK) WHERE origen = ?', [$origen]
        )->getRowArray() !== null) {
            return 'La actividad ' . $origen . ' ya fue trasladada.';
        }

        $orden ??= (int) $this->db->query(
            'SELECT ISNULL(MAX(orden), 0) + 1 AS orden FROM pPlaneacionLinea WITH (UPDLOCK, HOLDLOCK) WHERE planeacion = ?', [$plan]
        )->getRow()->orden;

        $linea = $this->insertarId('pPlaneacionLinea', [
            'planeacion'  => $plan,
            'orden'       => $orden,
            'finca'       => $actividad['finca'],
            'lote'        => $actividad['lote'],
            'novedad'     => $actividad['novedad'],
            'uMedida'     => $actividad['uMedida'],
            'cantidad'    => $actividad['cantidad'],
            'horaInicio'  => $actividad['horaInicio'],
            'horaFin'     => $actividad['horaFin'],
            'observacion' => $actividad['observacion'],
            'referencia'  => $actividad['referencia'],
            'estado'      => 'PENDIENTE',
            'origen'      => $origen,
        ]);

        if ($linea === 0) {
            return 'No se pudo registrar la actividad ' . $orden . '.';
        }

        return $this->insertarTrabajadores($linea, $actividad['trabajadores']);
    }

    private function insertarTrabajadores(int $linea, array $trabajadores): ?string
    {
        foreach (array_values($trabajadores) as $i => $trabajador) {
            $this->db->query(
                'INSERT INTO pPlaneacionTrabajador (linea, tercero, cantidad, reasignadoDe, orden) VALUES (?, ?, ?, ?, ?)',
                [$linea, $trabajador['tercero'], $trabajador['cantidad'], $trabajador['reasignadoDe'], $i + 1]
            );

            if ($this->db->affectedRows() !== 1) {
                return 'No se pudo registrar el trabajador ' . $trabajador['tercero'] . '.';
            }
        }

        return null;
    }

    private function transaccion(callable $pasos, string $accion, array $cambios, ?int $usu_id, string $usuario, string $mensaje): array
    {
        $this->db->transBegin();

        try {
            $plan = $pasos();

            if (is_string($plan)) {
                $this->db->transRollback();

                return ['success' => false, 'message' => $plan];
            }

            if ($accion === 'UPDATE') {
                $this->db->query(
                    'UPDATE pPlaneacion SET usuarioModificacion = ?, fechaModificacion = ? WHERE id = ?',
                    [$usuario, date('Y-m-d\TH:i:s'), $plan]
                );
            }

            $auditoria = $this->insertarAuditoria($accion, ['planeacion' => $plan] + $cambios, $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo guardar la planeación. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return ['success' => true, 'message' => $mensaje, 'id' => $plan];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error en la planeación de labores: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo guardar la planeación. La operación fue revertida.'];
        }
    }
}
