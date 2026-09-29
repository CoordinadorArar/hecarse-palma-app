<?php

namespace App\Models;

class FertilizacionTransaccionModel extends LaboresTransaccionModel
{
    public const TIPO = 'RLF';

    public const PLAN = 'PFA';

    public const GRUPO = '02';

    protected const RUTA              = 'transacciones/fertilizacion';
    protected const CAMPOS_NOVEDAD    = [];
    protected const COLUMNAS_CONSULTA = ", LTRIM(RTRIM(ISNULL(t.referencia, ''))) AS referencia";
    protected const COLUMNAS_DETALLE  = ", LTRIM(RTRIM(ISNULL(t.finca, ''))) AS finca, LTRIM(RTRIM(ISNULL(t.referencia, ''))) AS referencia";

    protected const CAMPOS = [
        'fecha'       => 't.fecha',
        'numero'      => 'LTRIM(RTRIM(t.numero))',
        'observacion' => "ISNULL(t.observacion, '')",
        'tipo'        => 'LTRIM(RTRIM(t.tipo))',
        'finca'       => "LTRIM(RTRIM(ISNULL(t.finca, '')))",
        'referencia'  => "LTRIM(RTRIM(ISNULL(t.referencia, '')))",
    ];

    protected function tiposConsulta(): array
    {
        return [self::PLAN, 'RLF'];
    }

    public function tipoDe(int $empresa, string $numero): ?string
    {
        $fila = $this->db->query(
            'SELECT TOP 1 LTRIM(RTRIM(tipo)) AS tipo FROM aTransaccion WHERE empresa = ? AND tipo IN (?, ?) AND LTRIM(RTRIM(numero)) = ?',
            [$empresa, self::PLAN, 'RLF', trim($numero)]
        )->getRowArray();

        return $fila === null ? null : $fila['tipo'];
    }

    public function labores(int $empresa, string $clase = ''): array
    {
        return $this->db->query(
            "SELECT RTRIM(codigo) AS codigo, MIN(descripcion) AS descripcion, MIN(RTRIM(ISNULL(uMedida, ''))) AS uMedida
               FROM aNovedad WHERE empresa = ? AND activo = 1 AND LTRIM(RTRIM(grupo)) = ?
              GROUP BY RTRIM(codigo) ORDER BY codigo ASC",
            [$empresa, self::GRUPO]
        )->getResultArray();
    }

    public function laborValida(int $empresa, string $novedad): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aNovedad WHERE empresa = ? AND RTRIM(codigo) = ? AND activo = 1 AND LTRIM(RTRIM(grupo)) = ?',
            [$empresa, trim($novedad), self::GRUPO]
        )->getRowArray() !== null;
    }

    public function planes(int $empresa, string $finca): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(t.numero)) AS numero, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                    CONVERT(varchar(10), t.fechaFinal, 23) AS fechaFinal, ISNULL(t.observacion, '') AS observacion
               FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND LTRIM(RTRIM(t.finca)) = ? AND ISNULL(t.anulado, 0) = 0" . $this->sinEliminadas('t') . '
              ORDER BY t.numero DESC',
            [$empresa, self::PLAN, trim($finca)]
        )->getResultArray();
    }

    public function planValido(int $empresa, string $numero, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 t.numero FROM aTransaccion t
              WHERE t.empresa = ? AND t.tipo = ? AND LTRIM(RTRIM(t.numero)) = ? AND LTRIM(RTRIM(t.finca)) = ?
                AND ISNULL(t.anulado, 0) = 0' . $this->sinEliminadas('t'),
            [$empresa, self::PLAN, trim($numero), trim($finca)]
        )->getRowArray() !== null;
    }

    public function lotesPlan(int $empresa, string $finca, string $referencia, string $seccion = ''): array
    {
        $sql = "SELECT LTRIM(RTRIM(l.codigo)) AS codigo, l.descripcion, LTRIM(RTRIM(ISNULL(l.seccion, ''))) AS seccion,
                       ISNULL(MAX(i.noPalmas), 0) AS palmas
                  FROM aTransaccionItem i
                  JOIN aLotes l ON l.empresa = i.empresa AND LTRIM(RTRIM(l.codigo)) = LTRIM(RTRIM(i.lote)) AND LTRIM(RTRIM(l.finca)) = ?
                 WHERE i.empresa = ? AND i.tipo = ? AND LTRIM(RTRIM(i.numero)) = ?";

        $binds = [trim($finca), $empresa, self::PLAN, trim($referencia)];

        if (trim($seccion) !== '') {
            $sql    .= ' AND LTRIM(RTRIM(l.seccion)) = ?';
            $binds[] = trim($seccion);
        }

        $filas = $this->db->query(
            $sql . ' GROUP BY LTRIM(RTRIM(l.codigo)), l.descripcion, LTRIM(RTRIM(ISNULL(l.seccion, \'\'))) ORDER BY codigo ASC',
            $binds
        )->getResultArray();

        $insumos = [];

        foreach ($this->db->query(
            "SELECT LTRIM(RTRIM(lote)) AS lote, LTRIM(RTRIM(item)) AS item, LTRIM(RTRIM(ISNULL(uMedida, ''))) AS uMedida, ISNULL(dosis, 0) AS dosis
               FROM aTransaccionItem WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? ORDER BY registro ASC",
            [$empresa, self::PLAN, trim($referencia)]
        )->getResultArray() as $insumo) {
            $insumos[$insumo['lote']][] = ['item' => $insumo['item'], 'uMedida' => $insumo['uMedida'], 'dosis' => (float) $insumo['dosis']];
        }

        foreach ($filas as &$fila) {
            $fila['palmas']  = (float) $fila['palmas'];
            $fila['insumos'] = $insumos[$fila['codigo']] ?? [];
        }

        unset($fila);

        return $filas;
    }

    public function items(int $empresa): array
    {
        return $this->db->query(
            "SELECT CAST(codigo AS varchar(20)) AS codigo, descripcion, LTRIM(RTRIM(ISNULL(uMedidaConsumo, ''))) AS uMedida
               FROM iItems WHERE empresa = ? AND activo = 1 AND LTRIM(RTRIM(tipo)) = 'FI' ORDER BY descripcion ASC",
            [$empresa]
        )->getResultArray();
    }

    public function itemValido(int $empresa, string $item): bool
    {
        return $this->db->query(
            "SELECT TOP 1 codigo FROM iItems WHERE empresa = ? AND codigo = ? AND activo = 1 AND LTRIM(RTRIM(tipo)) = 'FI'",
            [$empresa, (int) $item]
        )->getRowArray() !== null;
    }

    public function detalle(int $empresa, string $numero): ?array
    {
        $datos = parent::detalle($empresa, $numero);

        if ($datos === null) {
            return null;
        }

        $insumos = [];

        foreach ($this->db->query(
            "SELECT r.registror, LTRIM(RTRIM(r.lote)) AS lote, LTRIM(RTRIM(r.item)) AS item, ISNULL(i.descripcion, '') AS itemNombre,
                    LTRIM(RTRIM(ISNULL(r.uMedida, ''))) AS uMedida, ISNULL(r.dosis, 0) AS dosis, ISNULL(r.cantidad, 0) AS cantidad
               FROM aTransaccionItem r
               LEFT JOIN iItems i ON i.empresa = r.empresa AND CAST(i.codigo AS varchar(20)) = LTRIM(RTRIM(r.item))
              WHERE r.empresa = ? AND r.tipo = ? AND LTRIM(RTRIM(r.numero)) = ?
              ORDER BY r.registro ASC",
            [$empresa, static::TIPO, trim($numero)]
        )->getResultArray() as $insumo) {
            $insumos[(int) $insumo['registror'] . '|' . $insumo['lote']][] = [
                'item'       => $insumo['item'],
                'itemNombre' => $insumo['itemNombre'],
                'uMedida'    => $insumo['uMedida'],
                'dosis'      => (float) $insumo['dosis'],
                'cantidad'   => (float) $insumo['cantidad'],
            ];
        }

        foreach ($datos['lineas'] as &$linea) {
            $linea['insumos'] = $insumos[$linea['registro'] . '|' . $linea['lote']] ?? [];
        }

        unset($linea);

        return $datos;
    }

    protected function insertarLineas(int $empresa, int $anio, int $mes, string $numero, array $lineas): ?string
    {
        $this->db->query(
            'DELETE FROM aTransaccionItem WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?',
            [$empresa, static::TIPO, $numero]
        );

        $error = parent::insertarLineas($empresa, $anio, $mes, $numero, $lineas);

        if ($error !== null) {
            return $error;
        }

        $secuencia = 0;

        foreach (array_values($lineas) as $i => $linea) {
            foreach ($linea['insumos'] ?? [] as $insumo) {
                $this->db->query(
                    'INSERT INTO aTransaccionItem (empresa, tipo, numero, registro, lote, [año], mes, fecha, fechaFinal, item, uMedida,
                                                   novedad, cantidad, saldo, mBulto, pBulto, noPalmas, dosis, registror)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)',
                    [
                        $empresa, static::TIPO, $numero, $secuencia, $linea['lote'], $anio, $mes,
                        $linea['fecha'] . 'T00:00:00', $linea['fecha'] . 'T00:00:00', $insumo['item'], $insumo['uMedida'],
                        $linea['novedad'], $insumo['cantidad'], $linea['cantidad'], $insumo['dosis'], $i + 1,
                    ]
                );

                if ($this->db->affectedRows() !== 1) {
                    return 'No se pudo registrar el insumo ' . $insumo['item'] . ' de la línea ' . ($i + 1) . '.';
                }

                $secuencia++;
            }
        }

        return null;
    }

    public function loteDePlan(int $empresa, string $referencia, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 lote FROM aTransaccionItem WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? AND LTRIM(RTRIM(lote)) = ?',
            [$empresa, self::PLAN, trim($referencia), trim($lote)]
        )->getRowArray() !== null;
    }
}
