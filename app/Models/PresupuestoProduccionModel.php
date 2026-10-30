<?php

namespace App\Models;

use CodeIgniter\Model;

class PresupuestoProduccionModel extends Model
{
    protected $table      = 'aPresupuestoProduccion';
    protected $returnType = 'array';

    public function tablaExiste(): bool
    {
        return $this->db->query("SELECT OBJECT_ID('dbo.aPresupuestoProduccion', 'U') AS id")->getRow()->id !== null;
    }

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial AS nombre')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->where('activo', 1)->countAllResults() > 0;
    }

    public function fincaExiste(int $empresa, string $finca): bool
    {
        return $this->db->query('SELECT TOP 1 1 AS x FROM aFinca WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?', [$empresa, $finca])->getRowArray() !== null;
    }

    public function loteValido(int $empresa, string $finca, string $lote): bool
    {
        return $this->db->query('SELECT TOP 1 1 AS x FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = ? AND activo = 1', [$empresa, $finca, $lote])->getRowArray() !== null;
    }

    public function tieneHistorico(int $empresa, int $anio, int $mes, string $finca): bool
    {
        return $this->db->query("SELECT TOP 1 1 AS x FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND mes = ? AND finca = ? AND lote = ''", [$empresa, $anio, $mes, $finca])->getRowArray() !== null;
    }

    public function getAnios(): array
    {
        $actual = (int) date('Y');
        $anios  = range($actual - 2, $actual + 1);

        if ($this->tablaExiste()) {
            $anios = array_merge($anios, array_map('intval', array_column(
                $this->db->query('SELECT DISTINCT anio FROM dbo.aPresupuestoProduccion')->getResultArray(),
                'anio'
            )));
        }

        $anios = array_values(array_unique($anios));
        rsort($anios);

        return $anios;
    }

    public function listar(int $empresa, int $anio, string $finca = ''): array
    {
        $existe = $this->tablaExiste();
        $base   = array_merge([$empresa], $finca !== '' ? [$finca] : []);

        $sqlFincas = "SELECT LTRIM(RTRIM(codigo)) AS finca, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS fincaNombre FROM aFinca WHERE empresa = ?"
            . ($finca !== '' ? ' AND LTRIM(RTRIM(codigo)) = ?' : '') . ' AND (activo = 1';
        $sqlLotes  = "SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(codigo)) AS lote, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS loteNombre, ISNULL(hNetas, 0) AS hNetas, CAST(activo AS INT) AS activo FROM aLotes WHERE empresa = ?"
            . ($finca !== '' ? ' AND LTRIM(RTRIM(finca)) = ?' : '') . ' AND (activo = 1';
        $bindsFincas = $base;
        $bindsLotes  = $base;

        if ($existe) {
            $sqlFincas .= ' OR EXISTS (SELECT 1 FROM dbo.aPresupuestoProduccion p WHERE p.empresa = aFinca.empresa AND p.anio = ? AND LTRIM(RTRIM(p.finca)) = LTRIM(RTRIM(aFinca.codigo)))';
            $sqlLotes  .= ' OR EXISTS (SELECT 1 FROM dbo.aPresupuestoProduccion p WHERE p.empresa = aLotes.empresa AND p.anio = ? AND LTRIM(RTRIM(p.finca)) = LTRIM(RTRIM(aLotes.finca)) AND LTRIM(RTRIM(p.lote)) = LTRIM(RTRIM(aLotes.codigo)))';
            $bindsFincas[] = $anio;
            $bindsLotes[]  = $anio;
        }

        $vacio     = array_fill_keys(range(1, 12), null);
        $resultado = [];

        foreach ($this->db->query($sqlFincas . ') ORDER BY codigo ASC', $bindsFincas)->getResultArray() as $f) {
            $resultado[$f['finca']] = $f + ['historico' => $vacio, 'totalHistorico' => 0, 'lotes' => [], 'meses' => $vacio, 'totalLotes' => 0, 'total' => 0];
        }

        foreach ($this->db->query($sqlLotes . ') ORDER BY LTRIM(RTRIM(finca)), LTRIM(RTRIM(codigo))', $bindsLotes)->getResultArray() as $l) {
            if (isset($resultado[$l['finca']])) {
                $resultado[$l['finca']]['lotes'][$l['lote']] = ['lote' => $l['lote'], 'loteNombre' => $l['loteNombre'], 'hNetas' => (float) $l['hNetas'], 'activo' => (int) $l['activo'] === 1, 'meses' => $vacio, 'total' => 0];
            }
        }

        if ($existe) {
            $filas = $this->db->query(
                'SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(lote)) AS lote, mes, kilos FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ?' . ($finca !== '' ? ' AND LTRIM(RTRIM(finca)) = ?' : ''),
                array_merge([$empresa, $anio], $finca !== '' ? [$finca] : [])
            )->getResultArray();

            foreach ($filas as $p) {
                if (! isset($resultado[$p['finca']])) {
                    continue;
                }

                $mes   = (int) $p['mes'];
                $kilos = (float) $p['kilos'];
                $fila  = &$resultado[$p['finca']];

                if ($p['lote'] === '') {
                    $fila['historico'][$mes] = $kilos;
                } else {
                    $fila['lotes'][$p['lote']] ??= ['lote' => $p['lote'], 'loteNombre' => '', 'hNetas' => 0.0, 'activo' => false, 'meses' => $vacio, 'total' => 0];
                    $fila['lotes'][$p['lote']]['meses'][$mes] = $kilos;
                }

                $fila['meses'][$mes] = round(($fila['meses'][$mes] ?? 0) + $kilos, 2);
                unset($fila);
            }
        }

        $suma = static fn (array $meses) => round(array_sum(array_filter($meses, static fn ($k) => $k !== null)), 2);

        foreach ($resultado as &$fila) {
            foreach ($fila['lotes'] as &$lote) {
                $lote['total'] = $suma($lote['meses']);
            }
            unset($lote);

            $fila['lotes']          = array_values($fila['lotes']);
            $fila['totalHistorico'] = $suma($fila['historico']);
            $fila['totalLotes']     = round(array_sum(array_column($fila['lotes'], 'total')), 2);
            $fila['total']          = $suma($fila['meses']);
        }
        unset($fila);

        return array_values($resultado);
    }

    public function guardar(int $empresa, int $anio, int $mes, string $finca, string $lote, string $kilos, string $usuario): bool
    {
        $this->db->transStart();

        if ((float) $kilos == 0) {
            $this->db->query(
                'DELETE FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND mes = ? AND finca = ? AND lote = ?',
                [$empresa, $anio, $mes, $finca, $lote]
            );
        } else {
            $actualizado = $this->db->query(
                'UPDATE dbo.aPresupuestoProduccion SET kilos = ?, usuario = ?, fechaRegistro = GETDATE()
                  WHERE empresa = ? AND anio = ? AND mes = ? AND finca = ? AND lote = ?',
                [$kilos, $usuario, $empresa, $anio, $mes, $finca, $lote]
            );

            if ($actualizado === false) {
                $this->db->transRollback();

                return false;
            }

            if ($this->db->affectedRows() === 0) {
                $this->db->query(
                    'INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$empresa, $anio, $mes, $finca, $lote, $kilos, $usuario]
                );
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    public function metasExisten(): bool
    {
        $r = $this->db->query("SELECT OBJECT_ID('dbo.aPresupuestoMeta', 'U') AS m, OBJECT_ID('dbo.aPresupuestoCurva', 'U') AS c")->getRow();

        return $r->m !== null && $r->c !== null;
    }

    public function lotesConArea(int $empresa, string $finca): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS lote, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS loteNombre, CAST(hNetas AS FLOAT) AS hNetas
               FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND activo = 1 AND ISNULL(hNetas, 0) > 0
              ORDER BY hNetas DESC, LTRIM(RTRIM(codigo))",
            [$empresa, $finca]
        )->getResultArray();
    }

    public function meta(int $empresa, int $anio, string $finca): array
    {
        $meta = $this->db->query(
            'SELECT kilosAnual, metaTonHa FROM dbo.aPresupuestoMeta WHERE empresa = ? AND anio = ? AND finca = ?',
            [$empresa, $anio, $finca]
        )->getRowArray();

        $curva = array_fill(1, 12, null);

        foreach ($this->db->query('SELECT mes, porcentaje FROM dbo.aPresupuestoCurva WHERE empresa = ? AND anio = ? AND finca = ?', [$empresa, $anio, $finca])->getResultArray() as $c) {
            $curva[(int) $c['mes']] = (float) $c['porcentaje'];
        }

        $hay = array_filter($curva, static fn ($p) => $p !== null);

        return [
            'existe'     => $meta !== null,
            'kilosAnual' => $meta === null || $meta['kilosAnual'] === null ? null : (float) $meta['kilosAnual'],
            'metaTonHa'  => $meta === null || $meta['metaTonHa'] === null ? null : (float) $meta['metaTonHa'],
            'curva'      => $hay === [] ? null : $curva,
            'suma'       => round(array_sum($hay), 4),
            'completa'   => count($hay) === 12 && abs(array_sum($hay) - 100) <= 0.01,
        ];
    }

    public function mesesHistorico(int $empresa, int $anio, string $finca, array $meses = []): array
    {
        $sql = "SELECT mes FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND lote = ''";

        if ($meses !== []) {
            $sql .= ' AND mes IN (' . implode(',', array_map('intval', $meses)) . ')';
        }

        return array_map('intval', array_column($this->db->query($sql . ' ORDER BY mes', [$empresa, $anio, $finca])->getResultArray(), 'mes'));
    }

    public function metas(int $empresa, int $anio): array
    {
        $tablas = $this->metasExisten();
        $existe = $this->tablaExiste();
        $sql    = "SELECT LTRIM(RTRIM(codigo)) AS finca, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS fincaNombre FROM aFinca WHERE empresa = ? AND (activo = 1"
            . ($tablas ? ' OR EXISTS (SELECT 1 FROM dbo.aPresupuestoMeta m WHERE m.empresa = aFinca.empresa AND m.anio = ? AND m.finca = LTRIM(RTRIM(aFinca.codigo)))' : '')
            . ') ORDER BY codigo ASC';
        $vacia  = ['existe' => false, 'kilosAnual' => null, 'metaTonHa' => null, 'curva' => null, 'suma' => 0, 'completa' => false];
        $fincas = [];

        foreach ($this->db->query($sql, $tablas ? [$empresa, $anio] : [$empresa])->getResultArray() as $f) {
            $meta  = $tablas ? $this->meta($empresa, $anio, $f['finca']) : $vacia;
            $lotes = $this->lotesConArea($empresa, $f['finca']);
            $meses = array_fill(1, 12, null);

            if ($existe) {
                foreach ($this->db->query("SELECT mes, SUM(kilos) AS kilos FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND lote <> '' GROUP BY mes", [$empresa, $anio, $f['finca']])->getResultArray() as $p) {
                    $meses[(int) $p['mes']] = round((float) $p['kilos'], 2);
                }
            }

            $historico = $existe ? $this->mesesHistorico($empresa, $anio, $f['finca']) : [];

            $fincas[] = $f + [
                'kilosAnual'       => $meta['kilosAnual'],
                'metaTonHa'        => $meta['metaTonHa'],
                'curva'            => $meta['curva'] === null ? null : array_values($meta['curva']),
                'suma'             => $meta['suma'],
                'curvaCompleta'    => $meta['completa'],
                'hectareas'        => round(array_sum(array_column($lotes, 'hNetas')), 2),
                'lotesConArea'     => count($lotes),
                'presupuestoLotes' => array_values($meses),
                'totalLotes'       => round(array_sum(array_filter($meses, static fn ($k) => $k !== null)), 2),
                'tieneHistorico'   => $historico !== [],
                'mesesHistorico'   => $historico,
            ];
        }

        $meta = $tablas ? $this->meta($empresa, $anio, '') : $vacia;

        return [
            'tablasMetas'       => $tablas,
            'tablaPresupuesto'  => $existe,
            'empresa'           => ['kilosAnual' => $meta['kilosAnual'], 'metaTonHa' => $meta['metaTonHa'], 'hectareas' => round(array_sum(array_column($fincas, 'hectareas')), 2)],
            'fincas'            => $fincas,
        ];
    }

    public function guardarMeta(int $empresa, int $anio, string $finca, array $campos, ?array $curva, string $usuario): bool
    {
        $this->db->transStart();

        if ($campos !== []) {
            $set = implode(', ', array_map(static fn ($c) => "{$c} = ?", array_keys($campos)));
            $this->db->query(
                "UPDATE dbo.aPresupuestoMeta SET {$set}, usuarioModificacion = ?, fechaModificacion = GETDATE() WHERE empresa = ? AND anio = ? AND finca = ?",
                array_merge(array_values($campos), [$usuario, $empresa, $anio, $finca])
            );

            if ($this->db->affectedRows() === 0) {
                $campos += ['kilosAnual' => null, 'metaTonHa' => null];
                $this->db->query(
                    'INSERT INTO dbo.aPresupuestoMeta (empresa, anio, finca, kilosAnual, metaTonHa, usuario) VALUES (?, ?, ?, ?, ?, ?)',
                    [$empresa, $anio, $finca, $campos['kilosAnual'], $campos['metaTonHa'], $usuario]
                );
            }
        }

        if ($curva !== null) {
            $this->db->query('DELETE FROM dbo.aPresupuestoCurva WHERE empresa = ? AND anio = ? AND finca = ?', [$empresa, $anio, $finca]);

            foreach ($curva as $i => $p) {
                $this->db->query(
                    'INSERT INTO dbo.aPresupuestoCurva (empresa, anio, finca, mes, porcentaje, usuario) VALUES (?, ?, ?, ?, ?, ?)',
                    [$empresa, $anio, $finca, $i + 1, $p, $usuario]
                );
            }

            if ($curva !== [] && $this->db->query('SELECT 1 AS x FROM dbo.aPresupuestoMeta WHERE empresa = ? AND anio = ? AND finca = ?', [$empresa, $anio, $finca])->getRowArray() === null) {
                $this->db->query(
                    'INSERT INTO dbo.aPresupuestoMeta (empresa, anio, finca, usuario) VALUES (?, ?, ?, ?)',
                    [$empresa, $anio, $finca, $usuario]
                );
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    public function distribucion(int $empresa, int $anio, string $finca, array $meses, float $kilosAnual, array $curva): ?array
    {
        $lotes = $this->lotesConArea($empresa, $finca);
        $area  = array_sum(array_column($lotes, 'hNetas'));
        $filas = [];

        if ($area <= 0) {
            return null;
        }

        foreach ($lotes as $l) {
            $filas[$l['lote']] = ['lote' => $l['lote'], 'loteNombre' => $l['loteNombre'], 'hNetas' => round((float) $l['hNetas'], 2), 'meses' => [], 'total' => 0];
        }

        $totales = [];

        $suma    = array_sum($curva);
        $mensual = [];

        foreach ($curva as $m => $p) {
            $mensual[$m] = round($kilosAnual * $p / $suma, 2);
        }

        $ultimo = max(array_keys(array_filter($curva, static fn ($p) => $p > 0)));
        $mensual[$ultimo] = round($mensual[$ultimo] + $kilosAnual - array_sum($mensual), 2);

        foreach ($meses as $m) {
            $kilosMes = $mensual[$m];
            $totales[$m] = $kilosMes;
            $resto = $kilosMes;

            foreach ($lotes as $l) {
                $k = round($kilosMes * (float) $l['hNetas'] / $area, 2);
                $filas[$l['lote']]['meses'][$m] = $k;
                $resto = round($resto - $k, 2);
            }

            foreach ($lotes as $l) {
                if (abs($resto) < 0.005) {
                    break;
                }

                $actual = $filas[$l['lote']]['meses'][$m];
                $nuevo  = max(0, round($actual + $resto, 2));
                $resto  = round($resto - ($nuevo - $actual), 2);
                $filas[$l['lote']]['meses'][$m] = $nuevo;
            }
        }

        foreach ($filas as &$f) {
            $f['total'] = round(array_sum($f['meses']), 2);
        }
        unset($f);

        $actuales = [];

        foreach ($this->db->query(
            "SELECT LTRIM(RTRIM(lote)) AS lote, mes, kilos FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND lote <> '' AND mes IN (" . implode(',', array_map('intval', $meses)) . ')',
            [$empresa, $anio, $finca]
        )->getResultArray() as $p) {
            $actuales[$p['lote'] . '|' . (int) $p['mes']] = (float) $p['kilos'];
        }

        $sobrescritas = 0;

        foreach ($actuales as $clave => $kilos) {
            [$lote, $m] = explode('|', $clave);

            if (abs(($filas[$lote]['meses'][(int) $m] ?? 0) - $kilos) >= 0.005) {
                $sobrescritas++;
            }
        }

        return [
            'lotes'              => array_values($filas),
            'totalesMes'         => $totales,
            'total'              => round(array_sum($totales), 2),
            'hectareas'          => round($area, 2),
            'celdasExistentes'   => count($actuales),
            'celdasSobrescritas' => $sobrescritas,
        ];
    }

    public function aplicarDistribucion(int $empresa, int $anio, string $finca, array $meses, array $lotes, bool $borrarHistorico, string $usuario): bool
    {
        $lista = implode(',', array_map('intval', $meses));

        $this->db->transStart();

        $this->db->query(
            "DELETE FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND mes IN ({$lista})" . ($borrarHistorico ? '' : " AND lote <> ''"),
            [$empresa, $anio, $finca]
        );

        foreach ($lotes as $l) {
            foreach ($l['meses'] as $m => $kilos) {
                if ($kilos > 0) {
                    $this->db->query(
                        'INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$empresa, $anio, $m, $finca, $l['lote'], number_format($kilos, 2, '.', ''), $usuario]
                    );
                }
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    public function eliminar(int $empresa, int $anio, string $finca, ?int $mes = null, string $lote = ''): bool
    {
        $sql   = 'DELETE FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND lote = ?';
        $binds = [$empresa, $anio, $finca, $lote];

        if ($mes !== null) {
            $sql .= ' AND mes = ?';
            $binds[] = $mes;
        }

        return $this->db->query($sql, $binds) !== false;
    }
}
