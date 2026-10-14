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

    public function listar(int $empresa, int $anio): array
    {
        $existe = $this->tablaExiste();
        $sql    = "SELECT LTRIM(RTRIM(codigo)) AS finca, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS fincaNombre FROM aFinca WHERE empresa = ? AND (activo = 1";
        $binds  = [$empresa];

        if ($existe) {
            $sql .= " OR EXISTS (SELECT 1 FROM dbo.aPresupuestoProduccion p WHERE p.empresa = aFinca.empresa AND p.anio = ? AND p.lote = '' AND LTRIM(RTRIM(p.finca)) = LTRIM(RTRIM(aFinca.codigo)))";
            $binds[] = $anio;
        }

        $fincas = $this->db->query($sql . ') ORDER BY codigo ASC', $binds)->getResultArray();
        $kilos  = [];

        if ($existe) {
            $filas = $this->db->query(
                "SELECT LTRIM(RTRIM(finca)) AS finca, mes, kilos FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND lote = ''",
                [$empresa, $anio]
            )->getResultArray();

            foreach ($filas as $f) {
                $kilos[$f['finca']][(int) $f['mes']] = (float) $f['kilos'];
            }
        }

        $resultado = [];

        foreach ($fincas as $f) {
            $meses = [];

            for ($mes = 1; $mes <= 12; $mes++) {
                $meses[(string) $mes] = $kilos[$f['finca']][$mes] ?? null;
            }

            $resultado[] = [
                'finca'       => $f['finca'],
                'fincaNombre' => $f['fincaNombre'],
                'meses'       => $meses,
                'total'       => round(array_sum(array_filter($meses, static fn ($k) => $k !== null)), 2),
            ];
        }

        return $resultado;
    }

    public function guardar(int $empresa, int $anio, string $finca, array $meses, string $usuario): bool
    {
        $this->db->transStart();

        foreach ($meses as $mes => $kilos) {
            if ($kilos === null || $kilos === '') {
                $this->db->query(
                    "DELETE FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND mes = ? AND finca = ? AND lote = ''",
                    [$empresa, $anio, (int) $mes, $finca]
                );
                continue;
            }

            $actualizado = $this->db->query(
                "UPDATE dbo.aPresupuestoProduccion SET kilos = ?, usuario = ?, fechaRegistro = GETDATE()
                  WHERE empresa = ? AND anio = ? AND mes = ? AND finca = ? AND lote = ''",
                [(string) $kilos, $usuario, $empresa, $anio, (int) $mes, $finca]
            );

            if ($actualizado === false) {
                $this->db->transRollback();

                return false;
            }

            if ($this->db->affectedRows() === 0) {
                $this->db->query(
                    "INSERT INTO dbo.aPresupuestoProduccion (empresa, anio, mes, finca, lote, kilos, usuario) VALUES (?, ?, ?, ?, '', ?, ?)",
                    [$empresa, $anio, (int) $mes, $finca, (string) $kilos, $usuario]
                );
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }

    public function eliminar(int $empresa, int $anio, string $finca, ?int $mes = null): bool
    {
        $sql   = "DELETE FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? AND finca = ? AND lote = ''";
        $binds = [$empresa, $anio, $finca];

        if ($mes !== null) {
            $sql .= ' AND mes = ?';
            $binds[] = $mes;
        }

        return $this->db->query($sql, $binds) !== false;
    }
}
