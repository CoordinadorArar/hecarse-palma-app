<?php

namespace App\Models;

class DominicalesModel extends LaboresTransaccionModel
{
    public const REFERENCIA = 'DOMAUTO';

    private ?bool $dominicalesDisponible = null;

    public function disponible(): bool
    {
        if ($this->dominicalesDisponible === null) {
            $this->dominicalesDisponible = $this->planeacionDisponible() && (int) $this->db->query(
                "SELECT CASE WHEN COL_LENGTH('dbo.pMotivoAusencia', 'remunerado') IS NOT NULL
                              AND COL_LENGTH('dbo.pPlaneacionAusencia', 'codigoMotivo') IS NOT NULL THEN 1 ELSE 0 END AS existe"
            )->getRow()->existe === 1;
        }

        return $this->dominicalesDisponible;
    }

    public function semana(string $domingo): array
    {
        return [date('Y-m-d', strtotime($domingo . ' -6 days')), date('Y-m-d', strtotime($domingo . ' -1 day'))];
    }

    public function calcular(int $empresa, string $domingo): array
    {
        [$lunes, $sabado] = $this->semana($domingo);

        $dominicales  = $this->dominicales($empresa);
        $nombres      = array_column($dominicales, 'descripcion', 'codigo');
        $transaccion  = $this->automatica($empresa, $domingo);
        $trabajadores = [];
        $nuevo        = static fn () => ['labores' => [], 'dias' => [], 'ausencias' => [], 'manuales' => []];

        foreach ($this->laboresBase($empresa, $lunes, $sabado) as $fila) {
            $trabajadores[$fila['tercero']] ??= $nuevo();
            $trabajadores[$fila['tercero']]['labores'][]            = $fila;
            $trabajadores[$fila['tercero']]['dias'][$fila['fecha']] = true;
        }

        foreach ($this->ausencias($empresa, $lunes, $sabado) as $fila) {
            $trabajadores[$fila['tercero']] ??= $nuevo();
            $trabajadores[$fila['tercero']]['ausencias'][] = [
                'fecha'      => $fila['fecha'],
                'codigo'     => $fila['codigo'],
                'motivo'     => $fila['motivo'],
                'remunerado' => (int) $fila['remunerado'],
            ];
        }

        foreach ($this->manuales($empresa, $domingo) as $fila) {
            $trabajadores[$fila['tercero']] ??= $nuevo();
            $trabajadores[$fila['tercero']]['manuales'][$fila['numero']] = $fila['numero'];
        }

        $terceros = $this->terceros($empresa, array_map('strval', array_keys($trabajadores)));
        $anio     = (int) substr($domingo, 0, 4);
        $filas    = [];
        $resumen  = ['trabajadores' => count($trabajadores), 'conDerecho' => 0, 'sinDerecho' => 0, 'manuales' => 0, 'sinConfiguracion' => 0, 'valorTotal' => 0];

        foreach ($trabajadores as $tercero => $datos) {
            $tercero      = (string) $tercero;
            $noRemunerada = array_values(array_filter($datos['ausencias'], static fn ($a) => $a['remunerado'] === 0));
            $estado       = 'con_derecho';
            $texto        = 'Con derecho';
            $precio       = 0.0;
            $reparto      = [];

            if ($datos['manuales'] !== []) {
                [$estado, $texto] = ['manual', 'Registrado manualmente'];
                $resumen['manuales']++;
            } elseif ($datos['labores'] === []) {
                [$estado, $texto] = ['sin_derecho', 'Sin derecho: sin labores en la semana'];
                $resumen['sinDerecho']++;
            } elseif ($noRemunerada !== []) {
                [$estado, $texto] = ['sin_derecho', 'Sin derecho: ' . ($noRemunerada[0]['motivo'] !== '' ? $noRemunerada[0]['motivo'] : 'ausencia sin motivo') . ' (' . $noRemunerada[0]['fecha'] . ')'];
                $resumen['sinDerecho']++;
            } else {
                $validas = array_values(array_filter($datos['labores'], static fn ($l) => (int) $l['loteValido'] === 1));
                $pesos   = $validas === [] ? [] : $this->pesos($validas, $dominicales);

                if ($pesos !== []) {
                    $principal = $pesos[count($pesos) - 1];
                    $precio    = $this->precioLabor($empresa, $principal['novedad'], $anio, $tercero, $domingo, $principal['finca'], $principal['lote'], $principal['seccion']);
                }

                if ($pesos === [] || $precio <= 0) {
                    $texto = match (true) {
                        $validas === [] => 'Sin labor dominical configurada: ' . $datos['labores'][0]['novedad'] . ' en finca ' . $datos['labores'][0]['finca'] . ' no tiene lote válido',
                        $pesos === []   => 'Sin labor dominical configurada',
                        default         => 'Sin labor dominical configurada: la labor ' . $principal['novedad'] . ' no tiene precio',
                    };
                    $estado = 'sin_configuracion';
                    $resumen['sinConfiguracion']++;
                } else {
                    $reparto = $this->repartir($pesos, $precio, $nombres);
                    $resumen['conDerecho']++;
                    $resumen['valorTotal'] += (int) round($precio);
                }
            }

            $dias = array_keys($datos['dias']);
            sort($dias);

            $filas[] = [
                'tercero'      => $tercero,
                'codigo'       => $terceros[$tercero]['codigo'] ?? '',
                'nombre'       => $terceros[$tercero]['nombre'] ?? '',
                'dias'         => $dias,
                'diasConLabor' => count($dias),
                'ausencias'    => $datos['ausencias'],
                'manuales'     => array_values($datos['manuales']),
                'estado'       => $estado,
                'estadoTexto'  => $texto,
                'precio'       => $precio,
                'valor'        => $reparto === [] ? 0 : (int) round($precio),
                'reparto'      => $reparto,
            ];
        }

        usort($filas, static fn ($a, $b) => [$a['nombre'], $a['tercero']] <=> [$b['nombre'], $b['tercero']]);

        return [
            'domingo'      => $domingo,
            'semana'       => ['desde' => $lunes, 'hasta' => $sabado],
            'resumen'      => $resumen,
            'transaccion'  => $transaccion,
            'bloqueo'      => $this->bloqueo($empresa, $domingo, $transaccion),
            'trabajadores' => $filas,
        ];
    }

    public function generar(int $empresa, string $domingo, string $usuario, ?int $usu_id): array
    {
        $recurso = self::REFERENCIA . '-' . $empresa . '-' . $domingo;
        $bloqueo = $this->db->query(
            "DECLARE @r INT; SET NOCOUNT ON;
             EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = 15000;
             SELECT @r AS r;",
            [$recurso]
        )->getRow();

        if ($bloqueo === null || (int) $bloqueo->r < 0) {
            return ['success' => false, 'message' => 'Otro usuario está generando los dominicales del domingo ' . $domingo . '; intente de nuevo en unos segundos.'];
        }

        try {
            return $this->generarBloqueado($empresa, $domingo, $usuario, $usu_id);
        } finally {
            $this->db->query("EXEC sp_releaseapplock @Resource = ?, @LockOwner = 'Session'", [$recurso]);
        }
    }

    private function generarBloqueado(int $empresa, string $domingo, string $usuario, ?int $usu_id): array
    {
        $calculo = $this->calcular($empresa, $domingo);

        if ($calculo['bloqueo'] !== '') {
            return ['success' => false, 'message' => $calculo['bloqueo']];
        }

        $dominicales = array_column($this->dominicales($empresa), null, 'codigo');
        $lineas      = [];

        foreach ($calculo['trabajadores'] as $trabajador) {
            if ($trabajador['estado'] !== 'con_derecho') {
                continue;
            }

            foreach ($trabajador['reparto'] as $r) {
                $clave = implode('|', [$r['novedad'], $r['finca'], $r['seccion'], $r['lote']]);

                if (! isset($lineas[$clave])) {
                    $unidad = $dominicales[$r['novedad']]['uMedida'] ?? '';

                    if ($unidad === '' || ! $this->unidadValida($empresa, $unidad)) {
                        return ['success' => false, 'message' => 'La labor dominical ' . $r['novedad'] . ' no tiene una unidad de medida válida.'];
                    }

                    $lineas[$clave] = [
                        'novedad'      => $r['novedad'],
                        'uMedida'      => $unidad,
                        'fecha'        => $domingo,
                        'finca'        => $r['finca'],
                        'seccion'      => $r['seccion'],
                        'lote'         => $r['lote'],
                        'cantidad'     => 0,
                        'jornales'     => 0,
                        'precioLabor'  => $trabajador['precio'],
                        'concepto'     => $this->concepto($empresa, $r['novedad']),
                        'trabajadores' => [],
                    ];
                }

                $lineas[$clave]['cantidad']       = round($lineas[$clave]['cantidad'] + $r['cantidad'], 2);
                $lineas[$clave]['trabajadores'][] = [
                    'tercero'     => $trabajador['tercero'],
                    'cantidad'    => $r['cantidad'],
                    'jornales'    => 0,
                    'precioLabor' => $trabajador['precio'],
                    'valorTotal'  => $r['valor'],
                ];
            }
        }

        if ($lineas === [] && $calculo['transaccion'] !== null) {
            return $this->anular($empresa, $calculo['transaccion']['numero'], $usuario, $usu_id);
        }

        if ($lineas === []) {
            return ['success' => false, 'message' => 'No hay trabajadores con derecho a dominical para el domingo ' . $domingo . '.'];
        }

        $datos = [
            'empresa'     => $empresa,
            'anio'        => (int) substr($domingo, 0, 4),
            'mes'         => (int) substr($domingo, 5, 2),
            'fecha'       => $domingo,
            'finca'       => null,
            'referencia'  => self::REFERENCIA,
            'remision'    => '',
            'observacion' => 'DOMINICALES AUTOMÁTICOS SEMANA ' . date('d/m/Y', strtotime($calculo['semana']['desde'])) . ' - ' . date('d/m/Y', strtotime($calculo['semana']['hasta'])),
            'usuario'     => $usuario,
            'lineas'      => array_values($lineas),
        ];

        $resumen = [
            'trabajadores' => $calculo['resumen']['conDerecho'],
            'lineas'       => count($lineas),
            'valorTotal'   => $calculo['resumen']['valorTotal'],
        ];

        if ($calculo['transaccion'] === null) {
            return $this->guardar($datos, $usu_id) + ['accion' => 'creada'] + $resumen;
        }

        $antes = $this->detalle($empresa, $calculo['transaccion']['numero']);

        if ($antes === null) {
            return ['success' => false, 'message' => 'No se encontró la transacción ' . $calculo['transaccion']['numero'] . '.'];
        }

        return $this->actualizar($datos, $antes, $usu_id) + ['accion' => 'actualizada'] + $resumen;
    }

    private function anular(int $empresa, string $numero, string $usuario, ?int $usu_id): array
    {
        $antes = $this->detalle($empresa, $numero);
        $fecha = date('Y-m-d\TH:i:s');

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE aTransaccion SET anulado = 1, usuarioAnulado = ?, fechaAnulado = ?
                  WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? AND ISNULL(anulado, 0) = 0 AND NOT ' . $this->liquidadaSql('aTransaccion'),
                [$usuario, $fecha, $empresa, static::TIPO, $numero]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo anular la transacción ' . $numero . '.'];
            }

            $auditoria = $this->insertarAuditoria('UPDATE', [
                'empresa'   => $empresa,
                'tipo'      => static::TIPO,
                'numero'    => $numero,
                'anulacion' => ['fecha' => $fecha, 'usuario' => $usuario, 'motivo' => 'Sin trabajadores con derecho a dominical'],
                'detalle'   => $antes,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo anular la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return [
                'success'      => true,
                'message'      => 'No hay trabajadores con derecho a dominical; se anuló la TLA ' . $numero . '.',
                'numero'       => $numero,
                'accion'       => 'anulada',
                'trabajadores' => 0,
                'lineas'       => 0,
                'valorTotal'   => 0,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error anulando la transacción de dominicales: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo anular la transacción. La operación fue revertida.'];
        }
    }

    private function bloqueo(int $empresa, string $domingo, ?array $transaccion): string
    {
        $anio     = (int) substr($domingo, 0, 4);
        $mes      = (int) substr($domingo, 5, 2);
        $etiqueta = substr($domingo, 0, 7);
        $periodo  = $this->periodo($empresa, $anio, $mes);

        if ($periodo === null) {
            return 'El periodo ' . $etiqueta . ' no existe para la empresa.';
        }

        if ($periodo['cerrado'] === 1) {
            return 'El periodo ' . $etiqueta . ' está cerrado.';
        }

        if ($periodo['fechaInicial'] !== null && $periodo['fechaFinal'] !== null && ($domingo < $periodo['fechaInicial'] || $domingo > $periodo['fechaFinal'])) {
            return 'La fecha ' . $domingo . ' no pertenece al periodo ' . $etiqueta . ' (del ' . $periodo['fechaInicial'] . ' al ' . $periodo['fechaFinal'] . ').';
        }

        if ($transaccion !== null && $transaccion['liquidada'] === 1) {
            return 'La transacción ' . $transaccion['numero'] . ' ya fue liquidada en nómina y no se puede regenerar.';
        }

        return '';
    }

    private function pesos(array $labores, array $dominicales): array
    {
        $porGrupo = [];

        foreach ($labores as $l) {
            $porGrupo[$l['grupo']] = ($porGrupo[$l['grupo']] ?? 0) + (float) $l['valor'];
        }

        arsort($porGrupo);

        $respaldo = null;

        foreach (array_keys($porGrupo) as $grupo) {
            if (isset($dominicales[(string) $grupo])) {
                $respaldo = $dominicales[(string) $grupo]['codigo'];
                break;
            }
        }

        if ($respaldo === null) {
            return [];
        }

        $combos = [];

        foreach ($labores as $l) {
            $novedad = $dominicales[$l['grupo']]['codigo'] ?? $respaldo;
            $clave   = implode('|', [$novedad, $l['finca'], $l['seccion'], $l['lote']]);

            $combos[$clave] ??= ['novedad' => $novedad, 'finca' => $l['finca'], 'seccion' => $l['seccion'], 'lote' => $l['lote'], 'valor' => 0.0];
            $combos[$clave]['valor'] += (float) $l['valor'];
        }

        $total = array_sum(array_column($combos, 'valor'));

        uasort($combos, static fn ($a, $b) => [$a['valor'], $a['lote']] <=> [$b['valor'], $b['lote']]);

        $combos = array_values($combos);
        $ultimo = count($combos) - 1;
        $combos = array_values(array_filter($combos, static fn ($c, $i) => $i === $ultimo || round($c['valor'] / $total, 2) > 0, ARRAY_FILTER_USE_BOTH));
        $total  = array_sum(array_column($combos, 'valor'));

        foreach ($combos as &$c) {
            $c['peso'] = $c['valor'] / $total;
        }

        unset($c);

        return $combos;
    }

    private function repartir(array $pesos, float $precio, array $nombres): array
    {
        $objetivo = (int) round($precio);
        $ultimo   = count($pesos) - 1;
        $valores  = 0;
        $centavos = 0;
        $reparto  = [];

        foreach ($pesos as $i => $p) {
            $valor    = $i === $ultimo ? $objetivo - $valores : (int) round($p['peso'] * $precio);
            $cantidad = $i === $ultimo ? 100 - $centavos : (int) round($p['peso'] * 100);

            if ($valor <= 0 || $cantidad <= 0) {
                continue;
            }

            $valores  += $valor;
            $centavos += $cantidad;

            $reparto[] = [
                'novedad'       => $p['novedad'],
                'novedadNombre' => $nombres[$p['novedad']] ?? '',
                'finca'         => $p['finca'],
                'seccion'       => $p['seccion'],
                'lote'          => $p['lote'],
                'devengado'     => round($p['valor'], 2),
                'porcentaje'    => round($p['peso'] * 100, 2),
                'cantidad'      => $cantidad / 100,
                'valor'         => $valor,
            ];
        }

        return array_reverse($reparto);
    }

    private function dominicales(int $empresa): array
    {
        $filas = $this->db->query(
            "SELECT LTRIM(RTRIM(ISNULL(grupo, ''))) AS grupo, RTRIM(codigo) AS codigo, MIN(LTRIM(RTRIM(descripcion))) AS descripcion,
                    MIN(LTRIM(RTRIM(ISNULL(uMedida, '')))) AS uMedida
               FROM aNovedad
              WHERE empresa = ? AND activo = 1 AND LTRIM(descripcion) LIKE 'DOMINICAL%'
              GROUP BY LTRIM(RTRIM(ISNULL(grupo, ''))), RTRIM(codigo)
              ORDER BY codigo",
            [$empresa]
        )->getResultArray();

        $mapa = [];

        foreach ($filas as $fila) {
            $mapa[$fila['grupo']] ??= $fila;
        }

        return $mapa;
    }

    private function laboresBase(int $empresa, string $lunes, string $sabado): array
    {
        return $this->db->query(
            "SELECT b.tercero, b.novedad, b.grupo, b.finca, b.seccion, b.lote, b.fecha, b.loteValido, SUM(b.valor) AS valor
               FROM (SELECT LTRIM(RTRIM(r.tercero)) AS tercero, RTRIM(r.novedad) AS novedad, LTRIM(RTRIM(ISNULL(nv.grupo, ''))) AS grupo,
                            LTRIM(RTRIM(ISNULL(r.finca, ''))) AS finca, ISNULL(lt.seccion, LTRIM(RTRIM(ISNULL(r.seccion, '')))) AS seccion,
                            LTRIM(RTRIM(ISNULL(r.lote, ''))) AS lote, CONVERT(varchar(10), r.fechaNovedad, 23) AS fecha, r.valorTotal AS valor,
                            CASE WHEN lt.seccion IS NULL THEN 0 ELSE 1 END AS loteValido
                       FROM aTransaccionTercero r
                       JOIN aTransaccion t ON t.empresa = r.empresa AND t.tipo = r.tipo AND t.numero = r.numero AND ISNULL(t.anulado, 0) = 0
                      OUTER APPLY (SELECT TOP 1 x.grupo FROM aNovedad x
                                    WHERE x.empresa = r.empresa AND RTRIM(x.codigo) = RTRIM(r.novedad) ORDER BY x.activo DESC) nv
                      OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(ISNULL(l.seccion, ''))) AS seccion FROM aLotes l
                                    WHERE l.empresa = r.empresa AND LTRIM(RTRIM(l.finca)) = LTRIM(RTRIM(r.finca))
                                      AND LTRIM(RTRIM(l.codigo)) = LTRIM(RTRIM(r.lote))) lt
                      WHERE r.empresa = ? AND r.tipo IN ('TLA', 'TLC', 'RLF') AND r.fechaNovedad BETWEEN ? AND ?
                        AND ISNULL(r.valorTotal, 0) > 0 AND ISNULL(r.contratista, 0) = 0
                        AND NOT EXISTS (SELECT 1 FROM aNovedad x WHERE x.empresa = r.empresa AND RTRIM(x.codigo) = RTRIM(r.novedad)
                                           AND x.descripcion LIKE '%DOMINICAL%')" . $this->sinEliminadas('t') . ") b
              GROUP BY b.tercero, b.novedad, b.grupo, b.finca, b.seccion, b.lote, b.fecha, b.loteValido",
            [$empresa, str_replace('-', '', $lunes), str_replace('-', '', $sabado)]
        )->getResultArray();
    }

    private function ausencias(int $empresa, string $lunes, string $sabado): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(a.tercero)) AS tercero, CONVERT(varchar(10), p.fecha, 23) AS fecha,
                    LTRIM(RTRIM(ISNULL(a.codigoMotivo, ''))) AS codigo,
                    LTRIM(RTRIM(ISNULL(m.descripcion, ISNULL(a.motivo, '')))) AS motivo,
                    ISNULL(CAST(m.remunerado AS int), 0) AS remunerado
               FROM pPlaneacionAusencia a
               JOIN pPlaneacion p ON p.id = a.planeacion
               LEFT JOIN pMotivoAusencia m ON m.empresa = p.empresa AND m.codigo = a.codigoMotivo
              WHERE p.empresa = ? AND p.fecha BETWEEN ? AND ?
              ORDER BY p.fecha, a.tercero",
            [$empresa, str_replace('-', '', $lunes), str_replace('-', '', $sabado)]
        )->getResultArray();
    }

    private function manuales(int $empresa, string $domingo): array
    {
        return $this->db->query(
            "SELECT DISTINCT LTRIM(RTRIM(r.tercero)) AS tercero, LTRIM(RTRIM(r.tipo)) AS tipo, LTRIM(RTRIM(r.numero)) AS numero
               FROM aTransaccionTercero r
               JOIN aTransaccion t ON t.empresa = r.empresa AND t.tipo = r.tipo AND t.numero = r.numero AND ISNULL(t.anulado, 0) = 0
              WHERE r.empresa = ? AND r.fechaNovedad = ?
                AND NOT (t.tipo = ? AND ISNULL(t.referencia, '') = ?)
                AND EXISTS (SELECT 1 FROM aNovedad x WHERE x.empresa = r.empresa AND RTRIM(x.codigo) = RTRIM(r.novedad)
                               AND x.activo = 1 AND LTRIM(x.descripcion) LIKE 'DOMINICAL%')" . $this->sinEliminadas('t'),
            [$empresa, str_replace('-', '', $domingo), static::TIPO, self::REFERENCIA]
        )->getResultArray();
    }

    private function automatica(int $empresa, string $domingo): ?array
    {
        $fila = $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(t.numero)) AS numero, CONVERT(varchar(16), t.fechaRegistro, 120) AS fechaRegistro,
                    CASE WHEN " . $this->liquidadaSql('t') . " THEN 1 ELSE 0 END AS liquidada" . $this->auditoriaSql('t') . "
               FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND t.referencia = ? AND t.fecha = ? AND ISNULL(t.anulado, 0) = 0" . $this->sinEliminadas('t') . "
              ORDER BY t.numero DESC",
            [$empresa, static::TIPO, self::REFERENCIA, str_replace('-', '', $domingo)]
        )->getRowArray();

        if ($fila !== null) {
            $fila['liquidada'] = (int) $fila['liquidada'];
        }

        return $fila;
    }

    private function terceros(int $empresa, array $nits): array
    {
        if ($nits === []) {
            return [];
        }

        $filas = $this->db->query(
            "SELECT LTRIM(RTRIM(nit)) AS nit, MIN(ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion)) AS nombre,
                    MIN(ISNULL(LTRIM(RTRIM(codigoEquivalencia)), '')) AS codigo
               FROM cTercero
              WHERE empresa = ? AND CHARINDEX(',' + LTRIM(RTRIM(nit)) + ',', ?) > 0
              GROUP BY LTRIM(RTRIM(nit))",
            [$empresa, ',' . implode(',', $nits) . ',']
        )->getResultArray();

        return array_column($filas, null, 'nit');
    }
}
