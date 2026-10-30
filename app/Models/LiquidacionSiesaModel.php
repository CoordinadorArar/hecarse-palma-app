<?php

namespace App\Models;

class LiquidacionSiesaModel extends ProduccionModel
{
    public const ACCION = 'marcar-liquidacion';

    private const RUTA    = 'informes/liquidacion';
    private const EMPRESA = 1;
    private const CIA     = 1;
    private const SIESA   = '[LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].dbo.';

    private const ESTADOS   = [0 => 'En elaboración', 1 => 'Aprobado', 2 => 'Rechazado', 5 => 'Facturado'];
    private const MARCABLES = [1, 5];

    public function idModulo(): int
    {
        $fila = $this->db->query(
            'SELECT TOP 1 Id FROM Modulos WHERE (Ruta = ? OR Ruta LIKE ?) AND FechaFinalizacion IS NULL ORDER BY Id',
            [self::RUTA, self::RUTA . '/%']
        )->getRowArray();

        return $fila === null ? 0 : (int) $fila['Id'];
    }

    public function puedeMarcar(int $usuario): bool
    {
        $modulo = $this->idModulo();

        return $modulo > 0 && in_array(self::ACCION, $this->accionesPermitidas($usuario, $modulo), true);
    }

    public function documentos(int $anio, int $mes): array
    {
        $filas = $this->siesa(
            "SELECT d.c0600_rowid AS rowid, LTRIM(RTRIM(CAST(d.c0600_consec_docto AS varchar(20)))) AS consecutivo,
                    d.c0600_ind_estado AS estado, CONVERT(varchar(10), d.c0600_fecha_inicial, 23) AS fechaInicial,
                    CONVERT(varchar(10), d.c0600_fecha_final, 23) AS fechaFinal, d.c0600_total_devengos AS totalDevengos
               FROM " . self::SIESA . "w0600_docto_nomina d
              WHERE d.c0600_id_cia = ? AND d.c0600_id_tipo_docto = 'NNM' AND d.c0600_ano = ? AND d.c0600_nro_periodo = ?
                AND d.c0600_rowid_tipo_nomina = 2
              ORDER BY d.c0600_consec_docto",
            [self::CIA, $anio, $mes]
        );

        return array_map(fn ($d) => [
            'rowid'         => (int) $d['rowid'],
            'consecutivo'   => $d['consecutivo'],
            'estado'        => (int) $d['estado'],
            'estadoTexto'   => self::ESTADOS[(int) $d['estado']] ?? 'Desconocido',
            'fechaInicial'  => $d['fechaInicial'],
            'fechaFinal'    => $d['fechaFinal'],
            'totalDevengos' => (float) $d['totalDevengos'],
            'marcable'      => in_array((int) $d['estado'], self::MARCABLES, true),
        ], $filas);
    }

    public function empleados(array $documento): array
    {
        $filas = $this->siesa(
            "SELECT LTRIM(RTRIM(t.f200_id)) AS cedula, MAX(LTRIM(RTRIM(t.f200_razon_social))) AS nombre,
                    SUM(CASE WHEN LTRIM(RTRIM(c.c0501_id)) BETWEEN '070' AND '078' THEN ISNULL(m.c0602_valor_devengo, 0) ELSE 0 END) AS valorSiesa
               FROM " . self::SIESA . "w0602_movto_nomina m
               JOIN " . self::SIESA . "t200_mm_terceros t ON t.f200_rowid = m.c0602_rowid_tercero
               JOIN " . self::SIESA . "w0501_conceptos c ON c.c0501_rowid = m.c0602_rowid_concepto
              WHERE m.c0602_rowid_docto_nm = ?
              GROUP BY LTRIM(RTRIM(t.f200_id))",
            [$documento['rowid']]
        );

        $cedulas = $this->cedulas($filas);
        $locales = [];

        if ($cedulas !== []) {
            [$criterio, $binds] = $this->criterio($documento, $cedulas);

            $resumen = $this->db->query(
                "SELECT x.cedula, SUM(CASE WHEN x.signo = 2 THEN -ISNULL(x.valorTotal, 0) ELSE ISNULL(x.valorTotal, 0) END) AS valorAppPalma,
                        SUM(CASE WHEN x.ejecutado = 1 THEN 0 ELSE 1 END) AS lineasPendientes,
                        SUM(CASE WHEN x.ejecutado = 1 THEN 1 ELSE 0 END) AS lineasMarcadas
                   FROM (SELECT LTRIM(RTRIM(CAST(tt.tercero AS varchar(30)))) AS cedula, tt.valorTotal,
                                CAST(ISNULL(tt.ejecutado, 0) AS int) AS ejecutado,
                                (SELECT TOP 1 n.signo FROM aTransaccionNovedad n
                                  WHERE n.empresa = tt.empresa AND n.tipo = tt.tipo AND n.numero = tt.numero AND n.registro = tt.registroNovedad
                                    AND n.novedad = CASE WHEN n.novedad = '' THEN '' ELSE tt.novedad END) AS signo
                           FROM {$criterio}) x
                  GROUP BY x.cedula",
                $binds
            )->getResultArray();

            foreach ($resumen as $r) {
                $locales[$r['cedula']] = $r;
            }
        }

        $empleados = [];

        foreach ($filas as $f) {
            $local       = $locales[$f['cedula']] ?? [];
            $valorSiesa  = (float) $f['valorSiesa'];
            $valorLocal  = (float) ($local['valorAppPalma'] ?? 0);
            $empleados[] = [
                'cedula'           => $f['cedula'],
                'nombre'           => $f['nombre'],
                'valorSiesa'       => $valorSiesa,
                'valorAppPalma'    => $valorLocal,
                'diferencia'       => $valorSiesa - $valorLocal,
                'lineasPendientes' => (int) ($local['lineasPendientes'] ?? 0),
                'lineasMarcadas'   => (int) ($local['lineasMarcadas'] ?? 0),
            ];
        }

        usort($empleados, fn ($a, $b) => strcmp((string) $a['nombre'], (string) $b['nombre']));

        return $empleados;
    }

    public function cambiarMarca(array $documento, int $anio, int $mes, bool $marcar, ?int $usu_id): array
    {
        $filas = $this->siesa(
            'SELECT DISTINCT LTRIM(RTRIM(t.f200_id)) AS cedula
               FROM ' . self::SIESA . 'w0602_movto_nomina m
               JOIN ' . self::SIESA . 't200_mm_terceros t ON t.f200_rowid = m.c0602_rowid_tercero
              WHERE m.c0602_rowid_docto_nm = ?',
            [$documento['rowid']]
        );

        $cedulas = $this->cedulas($filas);

        if ($cedulas === []) {
            return ['success' => true, 'message' => 'El documento no tiene empleados para cruzar.', 'empleados' => 0, 'lineas' => 0];
        }

        [$criterio, $binds] = $this->criterio($documento, $cedulas);
        $condicion          = ' AND ISNULL(tt.ejecutado, 0) = ' . ($marcar ? 0 : 1);

        $this->db->transBegin();

        try {
            $conteo = $this->db->query(
                "SELECT COUNT(*) AS lineas, COUNT(DISTINCT tt.tercero) AS empleados FROM {$criterio}{$condicion}", $binds
            )->getRowArray();

            $this->db->query("UPDATE tt SET ejecutado = " . ($marcar ? 1 : 0) . " FROM {$criterio}{$condicion}", $binds);

            $lineas    = $this->db->affectedRows();
            $empleados = (int) ($conteo['empleados'] ?? 0);

            $auditoria = $this->insertarAuditoria('UPDATE', [
                'operacion' => $marcar ? 'marcar-liquidacion' : 'revertir-liquidacion',
                'anio'      => $anio,
                'mes'       => $mes,
                'documento' => [
                    'rowid'        => $documento['rowid'],
                    'consecutivo'  => $documento['consecutivo'],
                    'estado'       => $documento['estado'],
                    'fechaInicial' => $documento['fechaInicial'],
                    'fechaFinal'   => $documento['fechaFinal'],
                ],
                'empleados' => $empleados,
                'lineas'    => $lineas,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar la marca de liquidación. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return [
                'success'   => true,
                'message'   => ($marcar ? 'Líneas marcadas como liquidadas: ' : 'Líneas desmarcadas: ') . $lineas . ' de ' . $empleados . ' empleado(s).',
                'empleados' => $empleados,
                'lineas'    => $lineas,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error cambiando la marca de liquidación Siesa: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo actualizar la marca de liquidación. La operación fue revertida.'];
        }
    }

    private function criterio(array $documento, array $cedulas): array
    {
        return [
            'aTransaccionTercero tt
               JOIN aTransaccion a ON a.empresa = tt.empresa AND a.tipo = tt.tipo AND a.numero = tt.numero
              WHERE a.empresa = ' . self::EMPRESA . ' AND ISNULL(a.anulado, 0) = 0' . $this->sinEliminadas('a') . '
                AND a.fecha >= ? AND a.fecha < DATEADD(day, 1, ?)
                AND CHARINDEX(\',\' + LTRIM(RTRIM(CAST(tt.tercero AS varchar(30)))) COLLATE DATABASE_DEFAULT + \',\', ?) > 0',
            [str_replace('-', '', $documento['fechaInicial']), str_replace('-', '', $documento['fechaFinal']), ',' . implode(',', $cedulas) . ','],
        ];
    }

    private function cedulas(array $filas): array
    {
        return array_values(array_unique(array_filter(array_column($filas, 'cedula'), fn ($c) => ctype_digit((string) $c))));
    }

    private function siesa(string $sql, array $binds): array
    {
        $query = $this->db->query($sql, $binds);

        if ($query === false) {
            throw new \RuntimeException('Error consultando Siesa: ' . json_encode($this->db->error(), JSON_UNESCAPED_UNICODE));
        }

        return $query->getResultArray();
    }
}
