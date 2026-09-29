<?php

namespace App\Models;

class LaboresTransaccionModel extends ProduccionModel
{
    public const TIPO = 'TLA';

    protected const RUTA              = 'transacciones/labores';
    protected const CAMPOS_NOVEDAD    = ['finca', 'seccion'];
    protected const COLUMNAS_CONSULTA = '';
    protected const COLUMNAS_DETALLE  = '';

    protected const CAMPOS = [
        'fecha'       => 't.fecha',
        'numero'      => 'LTRIM(RTRIM(t.numero))',
        'observacion' => "ISNULL(t.observacion, '')",
        'tipo'        => 'LTRIM(RTRIM(t.tipo))',
        'finca'       => 'LTRIM(RTRIM(n.finca))',
        'seccion'     => "LTRIM(RTRIM(ISNULL(n.seccion, '')))",
    ];

    private const OPERADORES = [
        'igual'      => '=',
        'diferente'  => '<>',
        'mayor'      => '>',
        'menor'      => '<',
        'mayorIgual' => '>=',
        'menorIgual' => '<=',
        'contiene'   => 'LIKE',
    ];

    public function idModulo(): int
    {
        $fila = $this->db->query(
            'SELECT TOP 1 Id FROM Modulos WHERE Ruta = ? AND FechaFinalizacion IS NULL ORDER BY Id', [static::RUTA]
        )->getRowArray();

        return $fila === null ? 0 : (int) $fila['Id'];
    }

    protected function tiposConsulta(): array
    {
        return [static::TIPO];
    }

    private function marcas(): string
    {
        return implode(', ', array_fill(0, count($this->tiposConsulta()), '?'));
    }

    public function tipos(int $empresa): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(descripcion)) AS descripcion
               FROM gTipoTransaccion WHERE empresa = ? AND LTRIM(RTRIM(codigo)) IN (' . $this->marcas() . ') ORDER BY codigo',
            array_merge([$empresa], $this->tiposConsulta())
        )->getResultArray();
    }

    public function unidades(int $empresa): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, descripcion
               FROM gUnidadMedida WHERE empresa = ? AND activo = 1 ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function unidadValida(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM gUnidadMedida WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function secciones(int $empresa, string $finca): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, descripcion, activo
               FROM aSecciones WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? ORDER BY codigo ASC',
            [$empresa, trim($finca)]
        )->getResultArray();
    }

    public function seccionDeFinca(int $empresa, string $finca, string $seccion): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aSecciones WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, trim($finca), trim($seccion)]
        )->getRowArray() !== null;
    }

    public function lotes(int $empresa, string $finca, string $seccion = ''): array
    {
        $sql = "SELECT LTRIM(RTRIM(codigo)) AS codigo, descripcion, LTRIM(RTRIM(ISNULL(seccion, ''))) AS seccion, activo
                  FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ?";

        $binds = [$empresa, trim($finca)];

        if (trim($seccion) !== '') {
            $sql    .= ' AND LTRIM(RTRIM(seccion)) = ?';
            $binds[] = trim($seccion);
        }

        return $this->db->query($sql . ' ORDER BY codigo ASC', $binds)->getResultArray();
    }

    public function concepto(int $empresa, string $novedad): ?string
    {
        $fila = $this->db->query(
            'SELECT TOP 1 LTRIM(RTRIM(concepto)) AS concepto FROM aNovedad
              WHERE empresa = ? AND RTRIM(codigo) = ? ORDER BY activo DESC',
            [$empresa, trim($novedad)]
        )->getRowArray();

        return $fila === null ? null : $fila['concepto'];
    }

    public function precioLabor(
        int $empresa,
        string $novedad,
        int $anio,
        string $tercero,
        string $fecha,
        string $finca,
        string $lote,
        string $seccion = ''
    ): float {
        try {
            $fila = $this->db->query(
                'SELECT dbo.fRetornaPrecioLaboresTercero(?, ?, ?, ?, ?, ?, ?, ?, ?) AS precio',
                [$empresa, trim($novedad), $anio, (int) $tercero, $fecha, trim($finca), trim($seccion), trim($lote), 0]
            )->getRowArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error resolviendo el precio de la labor: ' . $e->getMessage());

            return 0.0;
        }

        return $fila === null ? 0.0 : round((float) $fila['precio'], 2);
    }

    public function filtrar(int $empresa, string $campo, string $operador, string $valor): array
    {
        $sql = "SELECT TOP 501 LTRIM(RTRIM(t.tipo)) AS tipo, LTRIM(RTRIM(t.numero)) AS numero,
                       CONVERT(varchar(10), t.fecha, 23) AS fecha, t.[año] AS anio, t.mes,
                       LTRIM(RTRIM(ISNULL(t.finca, (SELECT TOP 1 x.finca FROM aTransaccionNovedad x
                         WHERE x.empresa = t.empresa AND x.tipo = t.tipo AND x.numero = t.numero ORDER BY x.registro)))) AS finca,
                       ISNULL(t.observacion, '') AS observacion" . static::COLUMNAS_CONSULTA . ",
                       CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                       CASE WHEN EXISTS (SELECT 1 FROM cPeriodo p WHERE p.empresa = t.empresa AND p.[año] = t.[año]
                                            AND p.mes = t.mes AND p.cerrado = 1) THEN 1 ELSE 0 END AS cerrado,
                       CASE WHEN " . $this->liquidadaSql('t') . " THEN 1 ELSE 0 END AS bloqueada
                  FROM aTransaccion t
                 WHERE t.empresa = ? AND t.tipo IN (" . $this->marcas() . ')' . $this->sinEliminadas('t');

        $binds = array_merge([$empresa], $this->tiposConsulta());

        if ($valor !== '' && isset(static::CAMPOS[$campo], self::OPERADORES[$operador])) {
            $columna = static::CAMPOS[$campo];
            $signo   = self::OPERADORES[$operador];
            $binds[] = $signo === 'LIKE' ? '%' . strtr($valor, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%' : $valor;

            if (in_array($campo, static::CAMPOS_NOVEDAD, true)) {
                $existe = $signo === '<>' ? 'NOT EXISTS' : 'EXISTS';
                $signo  = $signo === '<>' ? '=' : $signo;
                $sql   .= " AND {$existe} (SELECT 1 FROM aTransaccionNovedad n WHERE n.empresa = t.empresa AND n.tipo = t.tipo
                                AND n.numero = t.numero AND {$columna} {$signo} ?)";
            } else {
                $sql .= " AND {$columna} {$signo} ?";
            }
        }

        $filas = $this->db->query($sql . ' ORDER BY t.fecha DESC, t.numero DESC', $binds)->getResultArray();

        foreach ($filas as &$fila) {
            foreach (['anio', 'mes', 'anulado', 'cerrado', 'bloqueada'] as $c) {
                $fila[$c] = (int) $fila[$c];
            }
        }

        unset($fila);

        return $filas;
    }

    public function detalle(int $empresa, string $numero): ?array
    {
        $binds = [$empresa, static::TIPO, trim($numero)];

        $encabezado = $this->db->query(
            "SELECT LTRIM(RTRIM(t.numero)) AS numero, LTRIM(RTRIM(t.tipo)) AS tipo, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                    t.[año] AS anio, t.mes, ISNULL(t.remision, '') AS remision, ISNULL(t.observacion, '') AS observacion" . static::COLUMNAS_DETALLE . ",
                    CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                    CASE WHEN EXISTS (SELECT 1 FROM cPeriodo p WHERE p.empresa = t.empresa AND p.[año] = t.[año]
                                         AND p.mes = t.mes AND p.cerrado = 1) THEN 1 ELSE 0 END AS cerrado,
                    CASE WHEN " . $this->liquidadaSql('t') . " THEN 1 ELSE 0 END AS bloqueada
               FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND LTRIM(RTRIM(t.numero)) = ?" . $this->sinEliminadas('t'),
            $binds
        )->getRowArray();

        if ($encabezado === null) {
            return null;
        }

        foreach (['anio', 'mes', 'anulado', 'cerrado', 'bloqueada'] as $c) {
            $encabezado[$c] = (int) $encabezado[$c];
        }

        $lineas = $this->db->query(
            "SELECT n.registro, RTRIM(ISNULL(n.novedad, '')) AS novedad,
                    ISNULL((SELECT MIN(a.descripcion) FROM aNovedad a
                             WHERE a.empresa = n.empresa AND RTRIM(a.codigo) = RTRIM(n.novedad)), '') AS novedadNombre,
                    LTRIM(RTRIM(ISNULL(n.uMedida, ''))) AS uMedida, CONVERT(varchar(10), n.fecha, 23) AS fecha,
                    LTRIM(RTRIM(n.finca)) AS finca, LTRIM(RTRIM(ISNULL(n.seccion, ''))) AS seccion, LTRIM(RTRIM(n.lote)) AS lote,
                    ISNULL(n.cantidad, 0) AS cantidad, ISNULL(n.jornales, 0) AS jornales, ISNULL(n.precioLabor, 0) AS precioLabor
               FROM aTransaccionNovedad n
              WHERE n.empresa = ? AND n.tipo = ? AND LTRIM(RTRIM(n.numero)) = ?
              ORDER BY n.registro ASC",
            $binds
        )->getResultArray();

        $trabajadores = $this->db->query(
            "SELECT r.registroNovedad, LTRIM(RTRIM(r.tercero)) AS tercero, ISNULL(c.nombre, '') AS terceroNombre,
                    ISNULL(r.cantidad, 0) AS cantidad, ISNULL(r.jornales, 0) AS jornales,
                    ISNULL(r.precioLabor, 0) AS precioLabor, ISNULL(r.valorTotal, 0) AS valorTotal
               FROM aTransaccionTercero r
               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
                              FROM cTercero
                             WHERE empresa = r.empresa AND LTRIM(RTRIM(nit)) = LTRIM(RTRIM(r.tercero))) c
              WHERE r.empresa = ? AND r.tipo = ? AND LTRIM(RTRIM(r.numero)) = ?
              ORDER BY r.registroNovedad ASC, r.registro ASC",
            $binds
        )->getResultArray();

        $porLinea = [];

        foreach ($trabajadores as $trabajador) {
            $registro = (int) $trabajador['registroNovedad'];
            unset($trabajador['registroNovedad']);

            foreach (['cantidad', 'jornales', 'precioLabor', 'valorTotal'] as $c) {
                $trabajador[$c] = (float) $trabajador[$c];
            }

            $porLinea[$registro][] = $trabajador;
        }

        foreach ($lineas as &$linea) {
            $linea['registro']     = (int) $linea['registro'];
            $linea['cantidad']     = (float) $linea['cantidad'];
            $linea['jornales']     = (float) $linea['jornales'];
            $linea['precioLabor']  = (float) $linea['precioLabor'];
            $linea['trabajadores'] = $porLinea[$linea['registro']] ?? [];
        }

        unset($linea);

        return ['encabezado' => $encabezado, 'lineas' => $lineas];
    }

    public function guardar(array $datos, ?int $usu_id): array
    {
        $empresa = (int) $datos['empresa'];
        $anio    = (int) $datos['anio'];
        $mes     = (int) $datos['mes'];

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
                'INSERT INTO aTransaccion (empresa, [año], mes, tipo, numero, fecha, finca, referencia, remision, observacion, fechaFinal,
                                           fechaRegistro, usuarioRegistro, anulado, cerrado, jornal, racimos, cantidad, precio, valorTotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0)',
                [$empresa, $anio, $mes, static::TIPO, $numero, $datos['fecha'], $datos['finca'] ?? null, $datos['referencia'] ?? null, $datos['remision'], $datos['observacion'], $registro, $registro, $datos['usuario']]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar el encabezado de la transacción.'];
            }

            $detalle = $this->insertarLineas($empresa, $anio, $mes, $numero, $datos['lineas']);

            if ($detalle !== null) {
                $this->db->transRollback();

                return ['success' => false, 'message' => $detalle];
            }

            $auditoria = $this->insertarAuditoria('INSERT', [
                'empresa' => $empresa,
                'tipo'    => static::TIPO,
                'numero'  => $numero,
                'datos'   => $datos,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return ['success' => true, 'message' => 'Transacción ' . $numero . ' registrada correctamente.', 'numero' => $numero];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error registrando la transacción de labores: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo registrar la transacción. La operación fue revertida.'];
        }
    }

    public function actualizar(array $datos, array $antes, ?int $usu_id): array
    {
        $empresa = (int) $datos['empresa'];
        $anio    = (int) $datos['anio'];
        $mes     = (int) $datos['mes'];
        $numero  = $antes['encabezado']['numero'];
        $llave   = [$empresa, static::TIPO, $numero];

        $this->db->transBegin();

        try {
            $cabecera = array_key_exists('referencia', $datos) ? ', finca = ?, referencia = ?' : '';
            $valores  = $cabecera === '' ? [] : [$datos['finca'], $datos['referencia']];

            $this->db->query(
                'UPDATE aTransaccion SET fecha = ?, [año] = ?, mes = ?, remision = ?, observacion = ?' . $cabecera . '
                  WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? AND ISNULL(anulado, 0) = 0',
                array_merge([$datos['fecha'], $anio, $mes, $datos['remision'], $datos['observacion']], $valores, $llave)
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar el encabezado de la transacción.'];
            }

            foreach (['aTransaccionTercero', 'aTransaccionNovedad'] as $tabla) {
                $this->db->query('DELETE FROM ' . $tabla . ' WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?', $llave);
            }

            $detalle = $this->insertarLineas($empresa, $anio, $mes, $numero, $datos['lineas']);

            if ($detalle !== null) {
                $this->db->transRollback();

                return ['success' => false, 'message' => $detalle];
            }

            $auditoria = $this->insertarAuditoria('UPDATE', [
                'empresa' => $empresa,
                'tipo'    => static::TIPO,
                'numero'  => $numero,
                'antes'   => $antes,
                'despues' => $datos,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return ['success' => true, 'message' => 'Transacción ' . $numero . ' actualizada correctamente.', 'numero' => $numero];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando la transacción de labores: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo actualizar la transacción. La operación fue revertida.'];
        }
    }

    protected function insertarLineas(int $empresa, int $anio, int $mes, string $numero, array $lineas): ?string
    {
        $secuencia = 0;

        foreach (array_values($lineas) as $i => $linea) {
            $orden = $i + 1;

            $this->db->query(
                'INSERT INTO aTransaccionNovedad (empresa, [año], mes, tipo, numero, novedad, registro, uMedida, finca, seccion,
                                                  lote, fecha, cantidad, jornales, racimos, pesoRacimo, saldo, ejecutado,
                                                  signo, precioLabor, concepto, registroNovedad, sacos)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, 0, ?, ?, ?, 0)',
                [
                    $empresa, $anio, $mes, static::TIPO, $numero, $linea['novedad'], $orden, $linea['uMedida'],
                    $linea['finca'], $linea['seccion'], $linea['lote'], $linea['fecha'] . 'T00:00:00',
                    $linea['cantidad'], $linea['jornales'], $linea['cantidad'], $linea['precioLabor'], $linea['concepto'], $orden,
                ]
            );

            if ($this->db->affectedRows() !== 1) {
                return 'No se pudo registrar la línea ' . $orden . ' de la transacción.';
            }

            foreach ($linea['trabajadores'] as $trabajador) {
                $this->db->query(
                    'INSERT INTO aTransaccionTercero (empresa, [año], mes, tipo, numero, novedad, registro, registroNovedad, finca,
                                                      seccion, lote, tercero, cantidad, jornales, saldo, ejecutado, precioLabor,
                                                      valorTotal, ccosto, contratista, racimos, fechaNovedad)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NULL, 0, 0, ?)',
                    [
                        $empresa, $anio, $mes, static::TIPO, $numero, $linea['novedad'], $secuencia, $orden, $linea['finca'],
                        $linea['seccion'] === '' ? null : $linea['seccion'], $linea['lote'], $trabajador['tercero'],
                        $trabajador['cantidad'], $trabajador['jornales'], $trabajador['cantidad'], $trabajador['precioLabor'],
                        $trabajador['valorTotal'], $linea['fecha'] . 'T00:00:00',
                    ]
                );

                if ($this->db->affectedRows() !== 1) {
                    return 'No se pudo registrar el tercero ' . $trabajador['tercero'] . ' de la línea ' . $orden . '.';
                }

                $secuencia++;
            }
        }

        return null;
    }
}
