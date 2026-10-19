<?php

namespace App\Models;

use CodeIgniter\Model;

class InformesGerencialesModel extends Model
{
    protected $table      = 'aPresupuestoProduccion';
    protected $returnType = 'array';

    private const LABORES_CICLO = [
        'ciclos-cosecha'       => ['TLC', "d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'"],
        'ciclos-poda'          => ['TLA', "d.grupo = '04' AND d.descripcion LIKE '%PODA%'"],
        'ciclos-fertilizacion' => [FertilizacionTransaccionModel::TIPO, "LTRIM(RTRIM(d.grupo)) = '" . FertilizacionTransaccionModel::GRUPO . "'"],
        'ciclos-sanidad'       => ['TLA', "LTRIM(RTRIM(d.grupo)) = '03'"],
        'ciclos-mantenimiento' => ['TLA', "LTRIM(RTRIM(d.grupo)) = '04' AND ISNULL(d.descripcion, '') NOT LIKE '%PODA%'"],
    ];

    public const CICLOS = [
        'ciclos-cosecha'       => ['ventana' => 400, 'brecha' => 1, 'ciclo' => 10, 'umbrales' => null, 'porLabor' => false],
        'ciclos-poda'          => ['ventana' => 730, 'brecha' => 7, 'ciclo' => 180, 'umbrales' => [45, 90], 'porLabor' => false],
        'ciclos-fertilizacion' => ['ventana' => 730, 'brecha' => 7, 'ciclo' => 180, 'umbrales' => [30, 60], 'porLabor' => false],
        'ciclos-sanidad'       => ['ventana' => 365, 'brecha' => 3, 'ciclo' => 30, 'umbrales' => [7, 15], 'porLabor' => true],
        'ciclos-mantenimiento' => ['ventana' => 730, 'brecha' => 7, 'ciclo' => 90, 'umbrales' => [15, 30], 'porLabor' => true],
    ];

    private const ABANDONO = 90;

    private ?string $eliminadas = null;

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

    public function presupuestoExiste(): bool
    {
        return $this->db->query("SELECT OBJECT_ID('dbo.aPresupuestoProduccion', 'U') AS id")->getRow()->id !== null;
    }

    public function getAnios(int $empresa): array
    {
        $sql = "SELECT DISTINCT YEAR(fecha) AS anio FROM aTransaccion WHERE empresa = ? AND tipo = 'TLC' AND anulado = 0 AND fecha IS NOT NULL";
        $binds = [$empresa];

        if ($this->presupuestoExiste()) {
            $sql .= ' UNION SELECT DISTINCT anio FROM dbo.aPresupuestoProduccion WHERE empresa = ?';
            $binds[] = $empresa;
        }

        return array_map('intval', array_column($this->db->query($sql . ' ORDER BY anio DESC', $binds)->getResultArray(), 'anio'));
    }

    public function getFincas(int $empresa): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS nombre FROM aFinca WHERE empresa = ? ORDER BY codigo ASC",
            [$empresa]
        )->getResultArray();
    }

    public function ultimoMesFacturado(int $empresa, int $anio): int
    {
        return (int) $this->db->query(
            "SELECT MAX(MONTH(fecha)) AS mes FROM aTransaccion WHERE empresa = ? AND tipo = 'TLC' AND anulado = 0 AND YEAR(fecha) = ?",
            [$empresa, $anio]
        )->getRow()->mes;
    }

    public function getPresupuesto(int $empresa, int $anio, string $finca = ''): array
    {
        if (! $this->presupuestoExiste()) {
            return [];
        }

        $sql = "SELECT LTRIM(RTRIM(p.finca)) AS finca, ISNULL(LTRIM(RTRIM(i.descripcion)), '') AS fincaNombre, LTRIM(RTRIM(p.lote)) AS lote,
                       ISNULL(LTRIM(RTRIM(k.descripcion)), '') AS loteNombre, p.mes, SUM(p.kilos) AS kilos
                  FROM dbo.aPresupuestoProduccion p
                  LEFT JOIN aFinca i ON i.empresa = p.empresa AND LTRIM(RTRIM(i.codigo)) = LTRIM(RTRIM(p.finca))
                  LEFT JOIN aLotes k ON k.empresa = p.empresa AND LTRIM(RTRIM(k.codigo)) = LTRIM(RTRIM(p.lote)) AND LTRIM(RTRIM(p.lote)) <> ''
                 WHERE p.empresa = ? AND p.anio = ?" . ($finca !== '' ? ' AND LTRIM(RTRIM(p.finca)) = ?' : '') . "
                 GROUP BY LTRIM(RTRIM(p.finca)), i.descripcion, LTRIM(RTRIM(p.lote)), k.descripcion, p.mes";

        return $this->db->query($sql, array_merge([$empresa, $anio], $finca !== '' ? [$finca] : []))->getResultArray();
    }

    public function getEjecutado(int $empresa, int $anio, string $finca = ''): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $sql = "WITH t AS (
                    SELECT a.empresa, a.tipo, a.numero, MONTH(a.fecha) AS mes,
                           (SELECT SUM(s.pesoNeto) FROM aTransaccionBascula s WHERE s.empresa = a.empresa AND s.tipo = a.tipo AND s.numero = a.numero) AS neto,
                           (SELECT TOP 1 LTRIM(RTRIM(n.finca)) FROM aTransaccionNovedad n WHERE n.empresa = a.empresa AND n.tipo = a.tipo AND n.numero = a.numero ORDER BY n.registro) AS finca
                      FROM aTransaccion a
                     WHERE a.empresa = ? AND a.tipo = 'TLC' AND a.anulado = 0 AND YEAR(a.fecha) = ?{$eliminadas}
                ), l AS (
                    SELECT a.empresa, a.tipo, a.numero, YEAR(a.fecha) AS anioTx, LTRIM(RTRIM(k.finca)) AS finca, LTRIM(RTRIM(k.codigo)) AS lote,
                           YEAR(ISNULL(c.fechaNovedad, b.fecha)) AS anio, MONTH(ISNULL(c.fechaNovedad, b.fecha)) AS mes,
                           CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS kilos
                      FROM aTransaccionTercero c
                      JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
                      JOIN aTransaccionNovedad b ON b.empresa = c.empresa AND b.tipo = c.tipo AND b.numero = c.numero AND b.registro = c.registroNovedad
                      JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero AND a.anulado = 0{$eliminadas}
                      JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                     WHERE c.empresa = ? AND (YEAR(a.fecha) = ? OR YEAR(ISNULL(c.fechaNovedad, b.fecha)) = ?)
                ), lt AS (
                    SELECT empresa, tipo, numero, finca, lote, SUM(kilos) AS kilos FROM l WHERE anioTx = ? GROUP BY empresa, tipo, numero, finca, lote
                ), tt AS (
                    SELECT empresa, tipo, numero, SUM(kilos) AS kilos FROM lt GROUP BY empresa, tipo, numero HAVING SUM(kilos) <> 0
                ), x AS (
                    SELECT ISNULL(lt.finca, t.finca) AS finca, ISNULL(lt.lote, '') AS lote, t.mes,
                           ISNULL(t.neto, 0) * CASE WHEN tt.numero IS NULL THEN 1.0 ELSE CAST(lt.kilos AS float) / tt.kilos END AS facturado, 0.0 AS labores
                      FROM t
                      LEFT JOIN tt ON tt.empresa = t.empresa AND tt.tipo = t.tipo AND tt.numero = t.numero
                      LEFT JOIN lt ON tt.numero IS NOT NULL AND lt.empresa = t.empresa AND lt.tipo = t.tipo AND lt.numero = t.numero
                    UNION ALL
                    SELECT finca, lote, mes, 0.0, kilos FROM l WHERE anio = ?
                )
                SELECT x.finca, ISNULL(LTRIM(RTRIM(i.descripcion)), '') AS fincaNombre, x.lote,
                       CASE WHEN x.lote = '' THEN 'Sin labores registradas' ELSE ISNULL(LTRIM(RTRIM(k.descripcion)), '') END AS loteNombre,
                       x.mes, SUM(x.facturado) AS facturado, SUM(x.labores) AS labores
                  FROM x
                  LEFT JOIN aFinca i ON i.empresa = ? AND LTRIM(RTRIM(i.codigo)) = x.finca
                  LEFT JOIN aLotes k ON k.empresa = ? AND LTRIM(RTRIM(k.codigo)) = x.lote AND x.lote <> ''
                 WHERE 1 = 1" . ($finca !== '' ? ' AND x.finca = ?' : '') . "
                 GROUP BY x.finca, i.descripcion, x.lote, k.descripcion, x.mes";

        $binds = [$empresa, $anio, $empresa, $anio, $anio, $anio, $anio, $empresa, $empresa];

        if ($finca !== '') {
            $binds[] = $finca;
        }

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function getLotesCiclo(int $empresa, string $finca): array
    {
        return $this->db->query(
            "SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS descripcion, ISNULL(hNetas, 0) AS hectareas
               FROM aLotes
              WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND activo = 1 AND ISNULL(hNetas, 0) > 0.1
                AND descripcion NOT LIKE 'DISTRIBUIR%' AND descripcion NOT LIKE 'VIVERO%'
              ORDER BY numero, codigo",
            [$empresa, $finca]
        )->getResultArray();
    }

    public function getFechasLabor(int $empresa, string $finca, string $informe, string $desde, string $hasta): array
    {
        $eliminadas = $this->eliminadas ??= $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        [$tipo, $labor] = self::LABORES_CICLO[$informe];

        return $this->db->query(
            "SELECT LTRIM(RTRIM(k.codigo)) AS lote, CONVERT(varchar(10), e.fecha, 23) AS fecha, LTRIM(RTRIM(d.codigo)) AS labor,
                    LTRIM(RTRIM(ISNULL(d.descripcion, ''))) AS laborNombre, ISNULL(d.ciclos, 0) AS ciclos
               FROM aTransaccionTercero c
               JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND {$labor}
               JOIN aTransaccionNovedad b ON b.empresa = c.empresa AND b.tipo = c.tipo AND b.numero = c.numero AND b.registro = c.registroNovedad
               JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero AND a.anulado = 0{$eliminadas}
               JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa AND LTRIM(RTRIM(k.finca)) = ?
              CROSS APPLY (SELECT CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha) e
              WHERE c.empresa = ? AND a.tipo = ? AND e.fecha BETWEEN ? AND ?
              GROUP BY k.codigo, e.fecha, d.codigo, d.descripcion, d.ciclos
             HAVING SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0)) > 0
              ORDER BY lote, fecha",
            [$finca, $empresa, $tipo, $desde, $hasta]
        )->getResultArray();
    }

    public function rangosCiclo(string $informe): array
    {
        $u      = self::CICLOS[$informe]['umbrales'];
        $rangos = $u !== null ? [
            ['clave' => 'vencido', 'etiqueta' => 'Vencido', 'desde' => null, 'hasta' => -1, 'color' => '#FF0000'],
            ['clave' => 'proximo', 'etiqueta' => 'Próximo (0 a ' . $u[0] . ' días)', 'desde' => 0, 'hasta' => $u[0], 'color' => '#FF9900'],
            ['clave' => 'intermedio', 'etiqueta' => 'Intermedio (' . ($u[0] + 1) . ' a ' . $u[1] . ' días)', 'desde' => $u[0] + 1, 'hasta' => $u[1], 'color' => '#FFD600'],
            ['clave' => 'al-dia', 'etiqueta' => 'Al día (más de ' . $u[1] . ' días)', 'desde' => $u[1] + 1, 'hasta' => null, 'color' => '#00B050'],
        ] : [
            ['clave' => 'seguimiento', 'etiqueta' => 'En seguimiento (menos de 10 días)', 'desde' => 0, 'hasta' => 9, 'color' => '#0d6efd'],
            ['clave' => 'normal', 'etiqueta' => 'Próximo a cosechar (10 a 12 días)', 'desde' => 10, 'hasta' => 12, 'color' => '#00B050'],
            ['clave' => 'alerta', 'etiqueta' => 'Listo para cosechar (13 a 15 días)', 'desde' => 13, 'hasta' => 15, 'color' => '#FF9900'],
            ['clave' => 'sobremaduracion', 'etiqueta' => 'Sobremaduración (más de 15 días)', 'desde' => 16, 'hasta' => null, 'color' => '#FF0000'],
        ];
        $rangos[] = ['clave' => 'sin-dato', 'etiqueta' => 'Sin registro', 'desde' => null, 'hasta' => null, 'color' => '#ADB5BD'];

        return $rangos;
    }

    public function calcularCiclos(string $informe, int $empresa, string $finca, string $corte, ?array $lotesFinca = null): array
    {
        $cfg    = self::CICLOS[$informe];
        $u      = $cfg['umbrales'];
        $dia    = static fn (string $f) => intdiv(strtotime($f . ' 00:00:00 UTC'), 86400);
        $fechaD = static fn (int $d) => gmdate('Y-m-d', $d * 86400);
        $hoy    = $dia($corte);
        $brecha = $cfg['brecha'];
        $rangos = $this->rangosCiclo($informe);

        $fechas = [];

        foreach ($this->getFechasLabor($empresa, $finca, $informe, $fechaD($hoy - $cfg['ventana']), $corte) as $r) {
            if ($cfg['porLabor']) {
                $fechas[$r['lote']][$r['labor']][$r['fecha']] = $r;

                continue;
            }

            $actual = $fechas[$r['lote']][$r['fecha']] ?? null;

            if ($actual === null || (int) $r['ciclos'] > (int) $actual['ciclos']) {
                $fechas[$r['lote']][$r['fecha']] = $r;
            }
        }

        $porEstado = array_fill_keys(array_column($rangos, 'clave'), 0);
        $lotes     = [];
        $hectareas = 0;

        foreach ($lotesFinca ?? $this->getLotesCiclo($empresa, $finca) as $l) {
            $grupos = $cfg['porLabor'] ? ($fechas[$l['codigo']] ?? []) : (isset($fechas[$l['codigo']]) ? [$fechas[$l['codigo']]] : []);
            $item   = ['codigo' => $l['codigo'], 'descripcion' => $l['descripcion'], 'hectareas' => round((float) $l['hectareas'], 2), 'inicio' => '', 'fin' => '', 'diasLabor' => null,
                'ciclo' => $cfg['ciclo'], 'proximo' => '', 'diasTranscurridos' => null, 'diasParaIngreso' => null, 'estado' => 'sin-dato', 'labor' => ''];
            $sel    = null;
            $vigen  = 0;

            foreach ($grupos as $filas) {
                $dias   = array_keys($filas);
                $fin    = $dia(end($dias));
                $inicio = $fin;

                for ($i = count($dias) - 2; $i >= 0 && $inicio - $dia($dias[$i]) <= $brecha; $i--) {
                    $inicio = $dia($dias[$i]);
                }

                $ultima = $filas[$fechaD($fin)];
                $ciclo  = $u !== null && (int) $ultima['ciclos'] > 0 ? (int) $ultima['ciclos'] : $cfg['ciclo'];
                $trans  = $hoy - $fin;
                $para   = $fin + $ciclo - $hoy;

                if ($cfg['porLabor'] && $trans > 2 * $ciclo && $trans > self::ABANDONO) {
                    continue;
                }

                $vigen++;

                if ($sel === null || $para < $sel['diasParaIngreso']) {
                    $sel = [
                        'inicio'            => $fechaD($inicio),
                        'fin'               => $fechaD($fin),
                        'diasLabor'         => $fin - $inicio + 1,
                        'ciclo'             => $ciclo,
                        'proximo'           => $fechaD($fin + $ciclo),
                        'diasTranscurridos' => $trans,
                        'diasParaIngreso'   => $para,
                        'estado'            => $u !== null
                            ? ($para < 0 ? 'vencido' : ($para <= $u[0] ? 'proximo' : ($para <= $u[1] ? 'intermedio' : 'al-dia')))
                            : ($trans < 10 ? 'seguimiento' : ($trans <= 12 ? 'normal' : ($trans <= 15 ? 'alerta' : 'sobremaduracion'))),
                        'labor'             => $ultima['labor'] . ' — ' . $ultima['laborNombre'],
                    ];
                }
            }

            $item = $sel === null ? $item : array_merge($item, $sel);

            if ($cfg['porLabor']) {
                $item['labores'] = $vigen;
            }

            $porEstado[$item['estado']]++;
            $hectareas += $item['hectareas'];
            $lotes[]    = $item;
        }

        return ['rangos' => $rangos, 'porEstado' => $porEstado, 'lotes' => $lotes, 'hectareas' => $hectareas];
    }
}
