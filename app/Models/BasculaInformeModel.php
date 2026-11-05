<?php

namespace App\Models;

use CodeIgniter\Model;

class BasculaInformeModel extends Model
{
    protected $table      = 'bBasculaRegistro';
    protected $returnType = 'array';

    private const PLACA = "CAST(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(LTRIM(RTRIM(ISNULL(%s, '')))), '*', ''), ' ', ''), '-', ''), '.', '') AS varchar(50))";

    private const SIESA = '[LinkedServerRDSSQL].[SUnoEE_AgroInvHecarse_Real].dbo.';

    private const NIT = "COALESCE(NULLIF(LTRIM(RTRIM(b.terceroExtractrora)), ''), NULLIF(LTRIM(RTRIM(b.empresaExtractora)), ''), NULLIF(LTRIM(RTRIM(b.planta)), ''))";

    public function ultimoPesaje(): ?string
    {
        return $this->db->query('SELECT CONVERT(char(19), MAX(f_entrada), 120) AS f FROM dbo.bBasculaRegistro')->getRow()->f;
    }

    public function pesajes(string $desde, string $hasta): array
    {
        $coFinca = $this->db->query("SELECT COL_LENGTH('dbo.bBasculaRegistro', 'coFinca') AS l")->getRow()->l === null ? 'NULL' : "NULLIF(LTRIM(RTRIM(coFinca)), '')";

        return $this->db->query(
            "SELECT numero, {$coFinca} AS coFinca, " . sprintf(self::PLACA, 'placa') . " AS placa, LTRIM(RTRIM(ISNULL(conductor, ''))) AS conductor, LTRIM(RTRIM(ISNULL(cedula, ''))) AS cedula,
                    LTRIM(RTRIM(ISNULL(origen, ''))) AS origen, LTRIM(RTRIM(ISNULL(destino, ''))) AS destino, CONVERT(char(19), f_entrada, 120) AS entrada,
                    CONVERT(char(19), f_salida, 120) AS salida, ISNULL(pesada1, 0) AS pesada1, ISNULL(pesada2, 0) AS pesada2, ISNULL(peso_neto, 0) AS neto
               FROM dbo.bBasculaRegistro WHERE CONVERT(date, f_entrada) BETWEEN ? AND ? ORDER BY f_entrada, numero",
            [$desde, $hasta]
        )->getResultArray();
    }

    public function tlc(int $empresa, string $desde, string $hasta): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        return $this->db->query(
            'SELECT CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS tlc, CONVERT(char(10), a.fecha, 120) AS fecha, ' . sprintf(self::PLACA, 'b.vehiculo') . " AS placa,
                    LTRIM(RTRIM(ISNULL(b.tiquete, ''))) AS tiquete, ISNULL(b.pesoNeto, 0) AS kg, LTRIM(RTRIM(ISNULL(b.codigoConductor, ''))) AS codigoConductor,
                    LTRIM(RTRIM(ISNULL(b.nombreConductor, ''))) AS conductor, ISNULL(x.nit, '') AS nit,
                    (SELECT MIN(LTRIM(RTRIM(n.finca))) FROM aTransaccionNovedad n WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero) AS finca, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre
               FROM aTransaccion a
               JOIN aTransaccionBascula b ON b.empresa = a.empresa AND b.tipo = a.tipo AND b.numero = a.numero
              CROSS APPLY (SELECT " . self::NIT . " AS nit) x
              OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion))) AS nombre FROM cTercero
                            WHERE empresa = a.empresa AND (LTRIM(RTRIM(nit)) = x.nit OR (LEN(x.nit) > 6 AND LTRIM(RTRIM(nit)) = LEFT(x.nit, LEN(x.nit) - 1)))
                            ORDER BY ISNULL(extractora, 0) DESC, CASE WHEN LTRIM(RTRIM(nit)) = x.nit THEN 0 ELSE 1 END) t
              WHERE a.empresa = ? AND a.tipo = 'TLC' AND a.anulado = 0{$eliminadas} AND CONVERT(date, a.fecha) BETWEEN ? AND ?
              ORDER BY a.fecha, a.numero",
            [$empresa, $desde, $hasta]
        )->getResultArray();
    }

    public function fincasEmpresa(int $empresa): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS nombre, LTRIM(RTRIM(ISNULL(centroOperacion, ''))) AS co FROM aFinca WHERE empresa = ? AND activo = 1 ORDER BY codigo",
            [$empresa]
        )->getResultArray();
    }

    public function nombreExtractora(int $empresa, string $nit): ?string
    {
        $fila = $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion))) AS nombre FROM cTercero
              WHERE empresa = ? AND extractora = 1 AND (LTRIM(RTRIM(nit)) = ? OR LTRIM(RTRIM(nit)) LIKE ? OR LTRIM(RTRIM(nit)) LIKE ?)
              ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = ? THEN 0 ELSE 1 END",
            [$empresa, $nit, $nit . '_', $nit . '-_', $nit]
        )->getRow();

        return $fila === null || trim((string) $fila->nombre) === '' ? null : trim((string) $fila->nombre);
    }

    public function facturasSiesa(string $desde, string $hasta): array
    {
        return $this->siesa(
            "SELECT LTRIM(RTRIM(b.f350_id_tipo_docto)) AS tipo, b.f350_consec_docto AS consec, CONVERT(char(10), b.f350_fecha, 23) AS fecha,
                    CAST(ISNULL(b.f350_notas, '') AS varchar(2000)) AS notas, LTRIM(RTRIM(a.f200_id)) AS nit, LTRIM(RTRIM(ISNULL(a.f200_razon_social, ''))) AS razon,
                    SUM(c.f470_cant_base) AS kg, SUM(c.f470_vlr_bruto - c.f470_vlr_dscto_linea) AS valor
               FROM " . self::SIESA . 't350_co_docto_contable b
               JOIN ' . self::SIESA . 't200_mm_terceros a ON a.f200_rowid = b.f350_rowid_tercero
               JOIN ' . self::SIESA . 't470_cm_movto_invent c ON c.f470_rowid_docto_fact = b.f350_rowid
               JOIN ' . self::SIESA . 't121_mc_items_extensiones ie ON ie.f121_id_cia = c.f470_id_cia AND ie.f121_rowid = c.f470_rowid_item_ext
               JOIN ' . self::SIESA . "t120_mc_items d ON d.f120_rowid = ie.f121_rowid_item AND d.f120_id_cia = ie.f121_id_cia
              WHERE b.f350_id_cia = 1 AND b.f350_id_tipo_docto IN ('FVE', 'NCE') AND b.f350_ind_estado <> 2
                AND b.f350_fecha >= ? AND b.f350_fecha < DATEADD(day, 1, ?) AND LTRIM(RTRIM(d.f120_referencia)) = 'FRU0001'
              GROUP BY b.f350_rowid, b.f350_id_tipo_docto, b.f350_consec_docto, b.f350_fecha, CAST(ISNULL(b.f350_notas, '') AS varchar(2000)), a.f200_id, a.f200_razon_social
              ORDER BY b.f350_fecha, b.f350_id_tipo_docto, b.f350_consec_docto",
            [str_replace('-', '', $desde), str_replace('-', '', $hasta)]
        );
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
