<?php

namespace App\Models;

use CodeIgniter\Model;

class BasculaModel extends Model
{
    protected $table      = 'bBasculaRegistro';
    protected $returnType = 'array';

    public function insertarNuevos(string $tabla, string $llave, array $filas, array $filtro = []): ?int
    {
        if ($filas === []) {
            return 0;
        }

        $filas = array_column($filas, null, $llave);

        $this->db->transBegin();

        try {
            $existentes = $this->db->table($tabla)
                ->select($llave)
                ->where($filtro)
                ->whereIn($llave, array_keys($filas))
                ->get()->getResultArray();

            $nuevas = array_values(array_diff_key($filas, array_flip(array_map('intval', array_column($existentes, $llave)))));

            if ($nuevas !== [] && $this->db->table($tabla)->insertBatch($nuevas) === false) {
                $this->db->transRollback();

                return null;
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();

                return null;
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Báscula, ' . $tabla . ': ' . $e->getMessage());

            return null;
        }

        return count($nuevas);
    }

    public function columnasFinca(): bool
    {
        return (int) $this->db->query("SELECT CASE WHEN COL_LENGTH('dbo.bBasculaRegistro', 'coEquipo') IS NULL OR COL_LENGTH('dbo.bBasculaRegistro', 'coFinca') IS NULL THEN 0 ELSE 1 END AS ok")->getRow()->ok === 1;
    }

    public function fincas(): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS nombre, LTRIM(RTRIM(ISNULL(centroOperacion, ''))) AS co FROM aFinca WHERE empresa = 1 ORDER BY codigo"
        )->getResultArray();
    }

    public static function normalizar(string $texto): string
    {
        return strtr(mb_strtoupper(trim(preg_replace('/\s+/', ' ', $texto)), 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    }

    public static function porLargo(array $fincas): array
    {
        $porLargo = array_map(static fn ($n) => self::normalizar((string) $n), array_column($fincas, 'nombre', 'codigo'));
        uksort($porLargo, static fn ($a, $b) => strlen($porLargo[$b]) <=> strlen($porLargo[$a]) ?: strcmp((string) $a, (string) $b));

        return $porLargo;
    }

    public static function fincasOrigen(string $origen, array $porLargo): array
    {
        $texto  = preg_replace(['/\bCRUEIRO\b/', '/\bCR\b/', '/\bCG\b/'], ['CRUCEIRO', 'CRUCEIRO', 'CANAGUATE'], self::normalizar($origen));
        $fincas = [];

        foreach ($porLargo as $codigo => $nombre) {
            if ($nombre !== '' && str_contains($texto, $nombre)) {
                $fincas[] = (string) $codigo;
                $texto    = str_replace($nombre, '|', $texto);
            }
        }

        return $fincas;
    }

    public static function coFinca(string $origen, string $coEquipo, array $porLargo, array $cos): string
    {
        $fincas = self::fincasOrigen($origen, $porLargo);
        $co     = count($fincas) === 1 ? (string) ($cos[$fincas[0]] ?? '') : '';

        return $co !== '' ? $co : $coEquipo;
    }
}
