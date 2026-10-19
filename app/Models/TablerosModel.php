<?php

namespace App\Models;

class TablerosModel extends InformesGerencialesModel
{
    private function sinEliminadas(string $alias): string
    {
        return $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : " AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = {$alias}.empresa AND el.tipo = {$alias}.tipo AND el.numero = LTRIM(RTRIM({$alias}.numero)))";
    }

    public function lotes(int $empresa): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(finca, ''))) AS finca, LTRIM(RTRIM(ISNULL(seccion, ''))) AS seccion,
                    LTRIM(RTRIM(ISNULL(descripcion, ''))) AS nombre, ISNULL([añoSiembra], 0) AS siembra, ISNULL(hNetas, 0) AS hectareas,
                    ISNULL(palmasProduccion, 0) AS palmas, CASE WHEN ISNULL(activo, 0) = 1 AND ISNULL(hNetas, 0) > 0.1
                    AND ISNULL(descripcion, '') NOT LIKE 'DISTRIBUIR%' AND ISNULL(descripcion, '') NOT LIKE 'VIVERO%' THEN 1 ELSE 0 END AS vigente
               FROM aLotes WHERE empresa = ? ORDER BY finca, numero, codigo",
            [$empresa]
        )->getResultArray();
    }

    public function racimos(int $empresa, int $anio): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(k.codigo)) AS lote, MONTH(a.fecha) AS mes, SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(b.racimos, 0)) AS racimos
               FROM aTransaccionNovedad b
               JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero AND a.anulado = 0" . $this->sinEliminadas('a') . "
               JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
              WHERE b.empresa = ? AND b.tipo = 'TLC' AND YEAR(a.fecha) = ?
                AND EXISTS (SELECT 1 FROM aTransaccionTercero c
                              JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                             WHERE c.empresa = b.empresa AND c.tipo = b.tipo AND c.numero = b.numero AND c.registroNovedad = b.registro)
              GROUP BY LTRIM(RTRIM(k.codigo)), MONTH(a.fecha)",
            [$empresa, $anio]
        )->getResultArray();
    }

    public function aniosFertilizacion(int $empresa): array
    {
        return array_map('intval', array_column($this->db->query(
            "SELECT DISTINCT YEAR(a.fecha) AS anio FROM aTransaccion a WHERE a.empresa = ? AND a.tipo IN (?, ?) AND ISNULL(a.anulado, 0) = 0 AND a.fecha IS NOT NULL"
                . $this->sinEliminadas('a') . ' ORDER BY anio DESC',
            [$empresa, FertilizacionTransaccionModel::TIPO, FertilizacionTransaccionModel::PLAN]
        )->getResultArray(), 'anio'));
    }

    public function fertilizacionAplicada(int $empresa, string $desde, string $hasta): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(r.lote)) AS lote, LTRIM(RTRIM(r.item)) AS item, MAX(ISNULL(i.descripcion, '')) AS itemNombre,
                    LTRIM(RTRIM(ISNULL(r.uMedida, ''))) AS uMedida, MONTH(r.fecha) AS mes, SUM(ISNULL(r.cantidad, 0)) AS cantidad,
                    SUM(CASE WHEN ISNULL(p.pBulto, 0) > 0 THEN ISNULL(r.cantidad, 0) / p.pBulto ELSE 0 END) AS bultos,
                    SUM(CASE WHEN ISNULL(p.pBulto, 0) > 0 THEN 0 ELSE ISNULL(r.cantidad, 0) END) AS sinBulto
               FROM aTransaccionItem r
               JOIN aTransaccion a ON a.empresa = r.empresa AND a.tipo = r.tipo AND LTRIM(RTRIM(a.numero)) = LTRIM(RTRIM(r.numero)) AND ISNULL(a.anulado, 0) = 0" . $this->sinEliminadas('a') . "
               LEFT JOIN iItems i ON i.empresa = r.empresa AND CAST(i.codigo AS varchar(20)) = LTRIM(RTRIM(r.item))
              OUTER APPLY (SELECT TOP 1 ISNULL(x.pBulto, 0) AS pBulto FROM aTransaccionItem x
                            WHERE x.empresa = r.empresa AND x.tipo = ? AND LTRIM(RTRIM(x.numero)) = LTRIM(RTRIM(ISNULL(a.referencia, '')))
                              AND LTRIM(RTRIM(x.lote)) = LTRIM(RTRIM(r.lote)) AND LTRIM(RTRIM(x.item)) = LTRIM(RTRIM(r.item))
                            ORDER BY CASE WHEN ISNULL(x.pBulto, 0) > 0 THEN 0 ELSE 1 END, x.registro) p
              WHERE r.empresa = ? AND r.tipo = ? AND CAST(r.fecha AS date) BETWEEN ? AND ?
              GROUP BY LTRIM(RTRIM(r.lote)), LTRIM(RTRIM(r.item)), LTRIM(RTRIM(ISNULL(r.uMedida, ''))), MONTH(r.fecha)",
            [FertilizacionTransaccionModel::PLAN, $empresa, FertilizacionTransaccionModel::TIPO, $desde, $hasta]
        )->getResultArray();
    }

    public function fertilizacionPlan(int $empresa, string $desde, string $hasta): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(r.lote)) AS lote, LTRIM(RTRIM(r.item)) AS item, MAX(ISNULL(i.descripcion, '')) AS itemNombre,
                    LTRIM(RTRIM(ISNULL(r.uMedida, ''))) AS uMedida, SUM(ISNULL(r.cantidad, 0)) AS cantidad, SUM(ISNULL(r.mBulto, 0)) AS bultos,
                    MAX(ISNULL(r.noPalmas, 0)) AS palmas
               FROM aTransaccionItem r
               JOIN aTransaccion a ON a.empresa = r.empresa AND a.tipo = r.tipo AND LTRIM(RTRIM(a.numero)) = LTRIM(RTRIM(r.numero)) AND ISNULL(a.anulado, 0) = 0" . $this->sinEliminadas('a') . "
               LEFT JOIN iItems i ON i.empresa = r.empresa AND CAST(i.codigo AS varchar(20)) = LTRIM(RTRIM(r.item))
              WHERE r.empresa = ? AND r.tipo = ? AND CAST(a.fecha AS date) <= ? AND CAST(ISNULL(a.fechaFinal, a.fecha) AS date) >= ?
              GROUP BY LTRIM(RTRIM(r.lote)), LTRIM(RTRIM(r.item)), LTRIM(RTRIM(ISNULL(r.uMedida, '')))",
            [$empresa, FertilizacionTransaccionModel::PLAN, $hasta, $desde]
        )->getResultArray();
    }
}
