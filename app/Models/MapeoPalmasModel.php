<?php

namespace App\Models;

use CodeIgniter\Model;

class MapeoPalmasModel extends Model
{
    protected $table      = 'aPalma';
    protected $returnType = 'array';

    public const GRUPOS_CENSO = [0, 2, 3];

    public const COLUMNAS = ['id', 'linea', 'palma', 'lat', 'lon', 'estado', 'caracteristica', 'fechaCenso'];

    public function tablasExisten(): bool
    {
        $fila = $this->db->query(
            "SELECT OBJECT_ID('dbo.aPalma', 'U') AS a, OBJECT_ID('dbo.aCensoPalma', 'U') AS b, OBJECT_ID('dbo.aLotesGeometria', 'U') AS c"
        )->getRow();

        return $fila->a !== null && $fila->b !== null && $fila->c !== null;
    }

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->where('activo', 1)->countAllResults() > 0;
    }

    public function getFincas(int $empresa): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS nombre FROM aFinca WHERE empresa = ? ORDER BY codigo ASC",
            [$empresa]
        )->getResultArray();
    }

    public function fincaExiste(int $empresa, string $finca): bool
    {
        return $this->db->query('SELECT TOP 1 1 AS x FROM aFinca WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?', [$empresa, $finca])->getRowArray() !== null;
    }

    public function lotes(int $empresa, string $finca): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(l.codigo)) AS codigo, LTRIM(RTRIM(ISNULL(l.descripcion, ''))) AS descripcion, l.activo,
                    ISNULL(l.palmasBrutas, 0) AS palmasBrutas, ISNULL(p.palmas, 0) AS palmas, ISNULL(p.lineas, 0) AS lineas,
                    g.geojson, g.areaHa, g.centroLat, g.centroLon
               FROM aLotes l
               LEFT JOIN dbo.aLotesGeometria g ON g.empresa = l.empresa AND g.finca = l.finca AND g.lote = l.codigo
               LEFT JOIN (SELECT lote, SUM(CASE WHEN estado = 'ACTIVA' THEN 1 ELSE 0 END) AS palmas, MAX(linea) AS lineas
                            FROM dbo.aPalma WHERE empresa = ? AND finca = ? GROUP BY lote) p ON p.lote = l.codigo
              WHERE l.empresa = ? AND l.finca = ?
              ORDER BY l.numero, l.codigo",
            [$empresa, $finca, $empresa, $finca]
        )->getResultArray();
    }

    public function lote(int $empresa, string $finca, string $lote): ?array
    {
        return $this->db->query(
            'SELECT l.codigo, l.activo, g.geojson
               FROM aLotes l
               LEFT JOIN dbo.aLotesGeometria g ON g.empresa = l.empresa AND g.finca = l.finca AND g.lote = l.codigo
              WHERE l.empresa = ? AND l.finca = ? AND l.codigo = ?',
            [$empresa, $finca, $lote]
        )->getRowArray();
    }

    public function geometrias(int $empresa, string $finca): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(g.lote)) AS codigo, LTRIM(RTRIM(ISNULL(l.descripcion, ''))) AS descripcion, g.geojson, g.areaHa, g.centroLat, g.centroLon
               FROM dbo.aLotesGeometria g
               JOIN aLotes l ON l.empresa = g.empresa AND l.finca = g.finca AND l.codigo = g.lote
              WHERE g.empresa = ? AND g.finca = ?
              ORDER BY l.numero, l.codigo",
            [$empresa, $finca]
        )->getResultArray();
    }

    private function sqlPalmas(string $filtro): string
    {
        return "WITH u AS (
                    SELECT c.palma, c.caracteristica, c.fecha,
                           ROW_NUMBER() OVER (PARTITION BY c.palma ORDER BY c.fecha DESC, c.id DESC) AS rn
                      FROM dbo.aCensoPalma c
                      JOIN dbo.aPalma p ON p.id = c.palma
                     WHERE {$filtro}
                )
                SELECT p.id, p.linea, p.palma, p.latitud, p.longitud, p.estado, u.caracteristica, CONVERT(varchar(10), u.fecha, 23) AS fechaCenso
                  FROM dbo.aPalma p
                  LEFT JOIN u ON u.palma = p.id AND u.rn = 1
                 WHERE {$filtro}
                 ORDER BY p.linea, p.palma";
    }

    private function compacta(array $f): array
    {
        return [
            (int) $f['id'], (int) $f['linea'], (int) $f['palma'], (float) $f['latitud'], (float) $f['longitud'],
            $f['estado'], $f['caracteristica'] === null ? null : (int) $f['caracteristica'], $f['fechaCenso'],
        ];
    }

    public function palmasCompactas(int $empresa, string $finca, string $lote): array
    {
        $filas = $this->db->query(
            $this->sqlPalmas('p.empresa = ? AND p.finca = ? AND p.lote = ?'),
            [$empresa, $finca, $lote, $empresa, $finca, $lote]
        )->getResultArray();

        $codigos = array_values(array_unique(array_filter(array_column($filas, 'caracteristica'), static fn ($c) => $c !== null)));

        return [
            'columnas'        => self::COLUMNAS,
            'palmas'          => array_map(fn ($f) => $this->compacta($f), $filas),
            'caracteristicas' => (object) $this->descripciones($empresa, array_map('intval', $codigos)),
        ];
    }

    public function palmaCompacta(int $id): ?array
    {
        $fila = $this->db->query($this->sqlPalmas('p.id = ?'), [$id, $id])->getRowArray();

        return $fila === null ? null : $this->compacta($fila);
    }

    public function descripciones(int $empresa, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $filas = $this->db->query(
            'SELECT codigo, grupoCaracteristica, descripcion FROM aCaracteristica WHERE empresa = ? AND codigo IN (' . implode(',', array_fill(0, count($codigos), '?')) . ')',
            array_merge([$empresa], $codigos)
        )->getResultArray();

        $mapa = [];

        foreach ($filas as $f) {
            $mapa[(string) $f['codigo']] = [
                'descripcion' => trim((string) $f['descripcion']),
                'grupo'       => $f['grupoCaracteristica'] === null ? null : (int) $f['grupoCaracteristica'],
            ];
        }

        return $mapa;
    }

    public function obtenerPalma(int $id): ?array
    {
        return $this->db->query(
            'SELECT id, empresa, finca, lote, linea, palma, latitud, longitud, estado, CONVERT(varchar(10), fechaErradicacion, 23) AS fechaErradicacion, observacion,
                    usuarioRegistro, CONVERT(varchar(19), fechaRegistro, 120) AS fechaRegistro, usuarioModificacion, CONVERT(varchar(19), fechaModificacion, 120) AS fechaModificacion
               FROM dbo.aPalma WHERE id = ?',
            [$id]
        )->getRowArray();
    }

    public function posicionOcupada(int $empresa, string $finca, string $lote, int $linea, int $palma, ?int $excepto): bool
    {
        return $this->db->query(
            'SELECT TOP 1 id FROM dbo.aPalma WHERE empresa = ? AND finca = ? AND lote = ? AND linea = ? AND palma = ? AND id <> ?',
            [$empresa, $finca, $lote, $linea, $palma, $excepto ?? 0]
        )->getRowArray() !== null;
    }

    public function siguienteNumero(int $empresa, string $finca, string $lote, int $linea): int
    {
        return (int) $this->db->query(
            'SELECT ISNULL(MAX(palma), 0) + 1 AS siguiente FROM dbo.aPalma WHERE empresa = ? AND finca = ? AND lote = ? AND linea = ?',
            [$empresa, $finca, $lote, $linea]
        )->getRow()->siguiente;
    }

    public function crearPalma(array $data, string $usuario, ?int $usu_id): int
    {
        $this->db->transStart();

        $ok = $this->db->table('aPalma')->insert($data + ['usuarioRegistro' => $usuario]);
        $id = $ok ? (int) $this->db->insertID() : 0;

        if ($id > 0) {
            $this->auditar('aPalma', 'INSERT', ['id' => $id] + $data + ['usuarioRegistro' => $usuario], $usu_id);
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false ? $id : 0;
    }

    public function actualizarPalma(int $id, array $data, string $usuario, ?int $usu_id): bool
    {
        $anterior = $this->obtenerPalma($id);

        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE dbo.aPalma SET finca = ?, lote = ?, linea = ?, palma = ?, latitud = ?, longitud = ?, estado = ?, fechaErradicacion = ?,
                    observacion = ?, usuarioModificacion = ?, fechaModificacion = GETDATE()
              WHERE id = ?',
            [
                $data['finca'], $data['lote'], $data['linea'], $data['palma'], $data['latitud'], $data['longitud'],
                $data['estado'], $data['fechaErradicacion'], $data['observacion'], $usuario, $id,
            ]
        ) !== false;

        if ($ok) {
            $this->auditar('aPalma', 'UPDATE', ['id' => $id, 'anterior' => $anterior, 'nuevo' => $data, 'usuarioModificacion' => $usuario], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function moverPalma(int $id, string $latitud, string $longitud, string $usuario, ?int $usu_id): bool
    {
        $anterior = $this->obtenerPalma($id);

        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE dbo.aPalma SET latitud = ?, longitud = ?, usuarioModificacion = ?, fechaModificacion = GETDATE() WHERE id = ?',
            [$latitud, $longitud, $usuario, $id]
        ) !== false;

        if ($ok) {
            $this->auditar('aPalma', 'UPDATE', [
                'id'       => $id,
                'anterior' => ['latitud' => $anterior['latitud'] ?? null, 'longitud' => $anterior['longitud'] ?? null],
                'nuevo'    => ['latitud' => $latitud, 'longitud' => $longitud],
                'usuarioModificacion' => $usuario,
            ], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function eliminarPalma(int $id, ?int $usu_id): bool
    {
        $anterior = $this->obtenerPalma($id);
        $censos   = (int) $this->db->query('SELECT COUNT(*) AS n FROM dbo.aCensoPalma WHERE palma = ?', [$id])->getRow()->n;

        $this->db->transStart();

        $ok = $this->db->query('DELETE FROM dbo.aPalma WHERE id = ?', [$id]) !== false;

        if ($ok) {
            $this->auditar('aPalma', 'DELETE', ['anterior' => $anterior, 'censosEliminados' => $censos], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function guardarGeometria(int $empresa, string $finca, string $lote, string $geojson, float $areaHa, string $lat, string $lon, string $usuario, ?int $usu_id): bool
    {
        $existe = $this->db->query(
            'SELECT TOP 1 1 AS x FROM dbo.aLotesGeometria WHERE empresa = ? AND finca = ? AND lote = ?',
            [$empresa, $finca, $lote]
        )->getRowArray() !== null;

        $this->db->transStart();

        $ok = $existe
            ? $this->db->query(
                'UPDATE dbo.aLotesGeometria SET geojson = ?, areaHa = ?, centroLat = ?, centroLon = ?, usuarioModificacion = ?, fechaModificacion = GETDATE()
                  WHERE empresa = ? AND finca = ? AND lote = ?',
                [$geojson, $areaHa, $lat, $lon, $usuario, $empresa, $finca, $lote]
            ) !== false
            : $this->db->query(
                'INSERT INTO dbo.aLotesGeometria (empresa, finca, lote, geojson, areaHa, centroLat, centroLon, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$empresa, $finca, $lote, $geojson, $areaHa, $lat, $lon, $usuario]
            ) !== false;

        if ($ok) {
            $this->auditar('aLotesGeometria', $existe ? 'UPDATE' : 'INSERT', [
                'empresa' => $empresa, 'finca' => $finca, 'lote' => $lote, 'areaHa' => $areaHa,
                'centroLat' => $lat, 'centroLon' => $lon, 'usuario' => $usuario, 'geojson' => $geojson,
            ], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    public function catalogoCenso(int $empresa): array
    {
        return $this->db->query(
            'SELECT c.grupoCaracteristica AS grupo, LTRIM(RTRIM(g.descripcion)) AS grupoDescripcion, c.codigo, LTRIM(RTRIM(c.descripcion)) AS descripcion
               FROM aCaracteristica c
               JOIN aGrupoCaracteristica g ON g.empresa = c.empresa AND g.codigo = c.grupoCaracteristica
              WHERE c.empresa = ? AND c.activo = 1 AND c.grupoCaracteristica IN (' . implode(',', self::GRUPOS_CENSO) . ')
              ORDER BY c.grupoCaracteristica, c.descripcion',
            [$empresa]
        )->getResultArray();
    }

    public function caracteristicaCenso(int $empresa, int $codigo): ?array
    {
        foreach ($this->catalogoCenso($empresa) as $c) {
            if ((int) $c['codigo'] === $codigo) {
                return $c;
            }
        }

        return null;
    }

    public function registrarCenso(int $empresa, int $palma, string $fecha, int $grupo, int $caracteristica, ?string $observacion, string $usuario, ?int $usu_id): int
    {
        $data = [
            'empresa' => $empresa, 'palma' => $palma, 'fecha' => $fecha, 'grupoCaracteristica' => $grupo,
            'caracteristica' => $caracteristica, 'observacion' => $observacion, 'usuarioRegistro' => $usuario,
        ];

        $this->db->transStart();

        $ok = $this->db->table('aCensoPalma')->insert($data);
        $id = $ok ? (int) $this->db->insertID() : 0;

        if ($id > 0) {
            $this->auditar('aCensoPalma', 'INSERT', ['id' => $id] + $data, $usu_id);
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false ? $id : 0;
    }

    public function historialCenso(int $palma): array
    {
        return $this->db->query(
            "SELECT c.id, CONVERT(varchar(10), c.fecha, 23) AS fecha, c.grupoCaracteristica AS grupo, LTRIM(RTRIM(ISNULL(g.descripcion, ''))) AS grupoDescripcion,
                    c.caracteristica, LTRIM(RTRIM(ISNULL(k.descripcion, ''))) AS descripcion, c.observacion, c.usuarioRegistro AS usuario,
                    CONVERT(varchar(19), c.fechaRegistro, 120) AS fechaRegistro
               FROM dbo.aCensoPalma c
               LEFT JOIN aCaracteristica k ON k.empresa = c.empresa AND k.codigo = c.caracteristica
               LEFT JOIN aGrupoCaracteristica g ON g.empresa = c.empresa AND g.codigo = c.grupoCaracteristica
              WHERE c.palma = ?
              ORDER BY c.fecha DESC, c.id DESC",
            [$palma]
        )->getResultArray();
    }

    public function obtenerCenso(int $id): ?array
    {
        return $this->db->query(
            'SELECT id, empresa, palma, CONVERT(varchar(10), fecha, 23) AS fecha, grupoCaracteristica, caracteristica, observacion, usuarioRegistro
               FROM dbo.aCensoPalma WHERE id = ?',
            [$id]
        )->getRowArray();
    }

    public function eliminarCenso(int $id, ?int $usu_id): bool
    {
        $anterior = $this->obtenerCenso($id);

        $this->db->transStart();

        $ok = $this->db->query('DELETE FROM dbo.aCensoPalma WHERE id = ?', [$id]) !== false;

        if ($ok) {
            $this->auditar('aCensoPalma', 'DELETE', ['anterior' => $anterior], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    private function auditar(string $tabla, string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => $tabla,
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
