<?php

namespace App\Models;

use CodeIgniter\Model;

class GestionPalmaModel extends Model
{
    protected $table      = 'aFinca';
    protected $returnType = 'array';

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function resumen(int $empresa): array
    {
        $resumen = ['kpis' => [], 'fincasLotes' => [], 'periodos' => ['lista' => [], 'anio' => 0, 'anioTotal' => 0, 'anioAbiertos' => 0, 'anioCerrados' => 0, 'total' => 0, 'abiertos' => 0, 'cerrados' => 0], 'presupuestoMeses' => [], 'precios' => ['conPrecio' => 0, 'sinPrecio' => 0, 'total' => 0, 'anio' => null, 'porGrupo' => []], 'empleados' => ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'contratistas' => 0, 'conductores' => 0, 'otros' => 0], 'presupuestoDisponible' => false, 'anio' => (int) date('Y'), 'mes' => (int) date('n'), 'avisos' => [], 'fallos' => []];

        if ($empresa <= 0) {
            $resumen['avisos'][] = 'No hay una empresa activa seleccionada.';

            return $resumen;
        }

        $etiquetas = ['presupuesto' => 'el presupuesto de producción', 'fincasLotes' => 'fincas y lotes', 'periodos' => 'periodos contables', 'presupuestoMeses' => 'presupuesto por mes', 'precios' => 'precios de labores', 'empleados' => 'empleados'];

        $anio = $resumen['anio'];
        $mes  = $resumen['mes'];
        $presupuestoMes = null;

        $consultas = [
            'presupuesto' => function () {
                return $this->db->query("SELECT OBJECT_ID('dbo.aPresupuestoProduccion', 'U') AS id")->getRow()->id !== null;
            },
            'fincasLotes' => function () use ($empresa, $anio, $mes, &$resumen, &$presupuestoMes) {
                $fincas = $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(LTRIM(RTRIM(descripcion)), '') AS nombre, activo
                    FROM aFinca WHERE empresa = ? ORDER BY codigo", [$empresa])->getResultArray();
                $lotes = $this->db->query("SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(codigo)) AS codigo, ISNULL(LTRIM(RTRIM(descripcion)), '') AS nombre,
                    ISNULL(hNetas, 0) AS hectareas, ISNULL(palmasProduccion, 0) AS palmas
                    FROM aLotes WHERE empresa = ? AND activo = 1 ORDER BY LTRIM(RTRIM(codigo))", [$empresa])->getResultArray();

                $kilos = [];
                $kilosFinca = [];
                $kilosLote = [];

                if ($resumen['presupuestoDisponible']) {
                    $presupuesto = $this->db->query('SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(lote)) AS lote, SUM(kilos) AS kilos FROM dbo.aPresupuestoProduccion
                        WHERE empresa = ? AND anio = ? AND mes = ? GROUP BY LTRIM(RTRIM(finca)), LTRIM(RTRIM(lote))', [$empresa, $anio, $mes])->getResultArray();

                    foreach ($presupuesto as $p) {
                        $kilos[$p['finca']] = ($kilos[$p['finca']] ?? 0) + (float) $p['kilos'];

                        if ($p['lote'] === '') {
                            $kilosFinca[$p['finca']] = ($kilosFinca[$p['finca']] ?? 0) + (float) $p['kilos'];
                        } else {
                            $kilosLote[$p['finca']][$p['lote']] = (float) $p['kilos'];
                        }
                    }

                    $presupuestoMes = round(array_sum($kilos), 2);
                }

                $porFinca = [];

                foreach ($lotes as $l) {
                    $kg = isset($kilosLote[$l['finca']][$l['codigo']]) ? round($kilosLote[$l['finca']][$l['codigo']], 2) : null;
                    $porFinca[$l['finca']][] = ['codigo' => $l['codigo'], 'nombre' => $l['nombre'], 'hectareas' => round((float) $l['hectareas'], 2), 'palmas' => (int) $l['palmas'], 'presupuestoMes' => $kg, 'tienePresupuesto' => $kg !== null];
                }

                $filas = [];

                foreach ($fincas as $f) {
                    $kg = isset($kilos[$f['codigo']]) ? round($kilos[$f['codigo']], 2) : null;
                    $detalle = $porFinca[$f['codigo']] ?? [];

                    if ((int) $f['activo'] !== 1 && ! $detalle && $kg === null) {
                        continue;
                    }

                    $con = count(array_filter($detalle, static fn ($l) => $l['tienePresupuesto']));
                    $filas[] = ['codigo' => $f['codigo'], 'nombre' => $f['nombre'], 'activa' => (int) $f['activo'] === 1, 'lotes' => count($detalle), 'hectareas' => round(array_sum(array_column($detalle, 'hectareas')), 2), 'palmas' => array_sum(array_column($detalle, 'palmas')), 'presupuestoMes' => $kg, 'presupuestoFincaKg' => round($kilosFinca[$f['codigo']] ?? 0, 2), 'lotesConPresupuesto' => $con, 'lotesSinPresupuesto' => count($detalle) - $con, 'tienePresupuesto' => $kg !== null, 'lotesDetalle' => $detalle];
                }

                $resumen['kpis']['fincas'] = count($fincas);
                $resumen['kpis']['fincasActivas'] = count(array_filter($fincas, static fn ($f) => (int) $f['activo'] === 1));

                return $filas;
            },
            'periodos' => function () use ($empresa, $anio) {
                $filas = $this->db->query("SELECT [año] AS anio, mes, ISNULL(LTRIM(RTRIM(descripcion)), '') AS nombre, cerrado,
                    CONVERT(varchar(10), fechaInicial, 23) AS fechaInicial, CONVERT(varchar(10), fechaFinal, 23) AS fechaFinal
                    FROM cPeriodo WHERE empresa = ? AND [año] = ? ORDER BY mes", [$empresa, $anio])->getResultArray();
                $total = $this->db->query('SELECT COUNT(*) AS total, ISNULL(SUM(CASE WHEN cerrado = 1 THEN 1 ELSE 0 END), 0) AS cerrados FROM cPeriodo WHERE empresa = ?', [$empresa])->getRowArray();
                $lista = array_map(static fn ($r) => ['anio' => (int) $r['anio'], 'mes' => (int) $r['mes'], 'nombre' => $r['nombre'], 'estado' => (int) $r['cerrado'] === 1 ? 'CERRADO' : 'ABIERTO', 'fechaInicial' => $r['fechaInicial'], 'fechaFinal' => $r['fechaFinal']], $filas);
                $cerradosAnio = count(array_filter($lista, static fn ($p) => $p['estado'] === 'CERRADO'));

                return ['lista' => $lista, 'anio' => $anio, 'anioTotal' => count($lista), 'anioAbiertos' => count($lista) - $cerradosAnio, 'anioCerrados' => $cerradosAnio, 'total' => (int) $total['total'], 'abiertos' => (int) $total['total'] - (int) $total['cerrados'], 'cerrados' => (int) $total['cerrados']];
            },
            'presupuestoMeses' => function () use ($empresa, $anio, &$resumen) {
                if (! $resumen['presupuestoDisponible']) {
                    return [];
                }

                $kilos = array_column($this->db->query('SELECT mes, SUM(kilos) AS kilos FROM dbo.aPresupuestoProduccion WHERE empresa = ? AND anio = ? GROUP BY mes', [$empresa, $anio])->getResultArray(), 'kilos', 'mes');

                return array_map(static fn ($m) => ['mes' => $m, 'kilos' => round((float) ($kilos[$m] ?? 0), 2)], range(1, 12));
            },
            'precios' => function () use ($empresa, $anio) {
                $anios = array_map(static fn ($f) => trim((string) $f['anio']), $this->db->query('SELECT DISTINCT [año] AS anio FROM aNovedadLotePrecio WHERE empresa = ? ORDER BY anio DESC', [$empresa])->getResultArray());
                $vigente = in_array((string) $anio, $anios, true) ? (string) $anio : (string) (array_values(array_filter($anios, static fn ($a) => (int) $a <= $anio))[0] ?? $anio);

                $grupos = array_map(static fn ($r) => ['grupo' => $r['grupo'], 'nombre' => $r['nombre'], 'conPrecio' => (int) $r['conPrecio'], 'sinPrecio' => (int) $r['sinPrecio']], $this->db->query("WITH n AS (SELECT RTRIM(codigo) AS codigo, ISNULL(MIN(RTRIM(grupo)), '') AS grupo FROM aNovedad WHERE empresa = ? AND activo = 1 GROUP BY RTRIM(codigo))
                    SELECT n.grupo, ISNULL((SELECT TOP 1 g.descripcion FROM aGrupoNovedad g WHERE g.empresa = ? AND RTRIM(g.codigo) = n.grupo), '') AS nombre,
                    SUM(CASE WHEN p.novedad IS NOT NULL THEN 1 ELSE 0 END) AS conPrecio, SUM(CASE WHEN p.novedad IS NULL THEN 1 ELSE 0 END) AS sinPrecio
                    FROM n
                    LEFT JOIN (SELECT DISTINCT RTRIM(novedad) AS novedad FROM aNovedadLotePrecio WHERE empresa = ? AND [año] = ? AND precioDestajo <> 0) p ON p.novedad = n.codigo
                    GROUP BY n.grupo
                    ORDER BY n.grupo", [$empresa, $empresa, $empresa, $vigente])->getResultArray());

                $con = array_sum(array_column($grupos, 'conPrecio'));
                $sin = array_sum(array_column($grupos, 'sinPrecio'));

                return ['conPrecio' => $con, 'sinPrecio' => $sin, 'total' => $con + $sin, 'anio' => (int) $vigente, 'porGrupo' => $grupos];
            },
            'empleados' => function () use ($empresa) {
                $row = $this->db->query('SELECT COUNT(*) AS total,
                    ISNULL(SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END), 0) AS activos,
                    ISNULL(SUM(CASE WHEN activo = 1 AND contratista = 1 THEN 1 ELSE 0 END), 0) AS contratistas,
                    ISNULL(SUM(CASE WHEN activo = 1 AND conductor = 1 THEN 1 ELSE 0 END), 0) AS conductores,
                    ISNULL(SUM(CASE WHEN activo = 1 AND otros = 1 THEN 1 ELSE 0 END), 0) AS otros
                    FROM nFuncionario WHERE empresa = ?', [$empresa])->getRowArray();
                $row = array_map('intval', $row);
                $row['inactivos'] = $row['total'] - $row['activos'];

                return $row;
            },
        ];

        foreach ($consultas as $clave => $consulta) {
            try {
                $valor = $consulta();

                if ($clave === 'presupuesto') {
                    $resumen['presupuestoDisponible'] = $valor;

                    if (! $valor) {
                        $resumen['avisos'][] = 'La tabla de presupuesto de producción no existe; los indicadores de presupuesto no están disponibles.';
                    }

                    continue;
                }

                $resumen[$clave] = $valor;
            } catch (\Throwable $e) {
                log_message('error', 'GestionPalmaModel::resumen [' . $clave . '] ' . $e->getMessage());
                $resumen['fallos'][] = $clave;
                $resumen['avisos'][] = 'No fue posible cargar ' . $etiquetas[$clave] . '.';
            }
        }

        $fl = $resumen['fincasLotes'];
        $fallo = fn ($clave) => in_array($clave, $resumen['fallos'], true);
        $conPpto = $resumen['presupuestoDisponible'] && ! $fallo('fincasLotes');

        $resumen['kpis'] += [
            'fincas' => null,
            'fincasActivas' => null,
            'empleados' => $fallo('empleados') ? null : $resumen['empleados']['total'],
            'empleadosActivos' => $fallo('empleados') ? null : $resumen['empleados']['activos'],
            'empleadosInactivos' => $fallo('empleados') ? null : $resumen['empleados']['inactivos'],
            'lotesActivos' => $fallo('fincasLotes') ? null : array_sum(array_column($fl, 'lotes')),
            'hectareasNetas' => $fallo('fincasLotes') ? null : round(array_sum(array_column($fl, 'hectareas')), 2),
            'palmas' => $fallo('fincasLotes') ? null : array_sum(array_column($fl, 'palmas')),
            'periodos' => $fallo('periodos') ? null : $resumen['periodos']['total'],
            'periodosAbiertos' => $fallo('periodos') ? null : $resumen['periodos']['abiertos'],
            'periodosCerrados' => $fallo('periodos') ? null : $resumen['periodos']['cerrados'],
            'periodosAnio' => $fallo('periodos') ? null : $resumen['periodos']['anioTotal'],
            'periodosAnioAbiertos' => $fallo('periodos') ? null : $resumen['periodos']['anioAbiertos'],
            'periodosAnioCerrados' => $fallo('periodos') ? null : $resumen['periodos']['anioCerrados'],
            'presupuestoMesKg' => $conPpto ? $presupuestoMes : null,
            'presupuestoMesTon' => $conPpto ? round($presupuestoMes / 1000, 2) : null,
            'fincasConPresupuesto' => $conPpto ? count(array_filter($fl, static fn ($f) => $f['tienePresupuesto'])) : null,
            'fincasSinPresupuesto' => $conPpto ? count(array_filter($fl, static fn ($f) => ! $f['tienePresupuesto'])) : null,
            'lotesConPresupuesto' => $conPpto ? array_sum(array_column($fl, 'lotesConPresupuesto')) : null,
            'lotesSinPresupuesto' => $conPpto ? array_sum(array_column($fl, 'lotesSinPresupuesto')) : null,
            'laboresConPrecio' => $fallo('precios') ? null : $resumen['precios']['conPrecio'],
            'laboresSinPrecio' => $fallo('precios') ? null : $resumen['precios']['sinPrecio'],
            'laboresActivas' => $fallo('precios') ? null : $resumen['precios']['total'],
        ];

        return $resumen;
    }
}
