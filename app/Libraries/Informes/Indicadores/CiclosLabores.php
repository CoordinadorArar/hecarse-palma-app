<?php

namespace App\Libraries\Informes\Indicadores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class CiclosLabores extends Informe
{
    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const NOMBRES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function opciones(string $filtro, array $dependencias): array
    {
        if ($filtro !== 'labor') {
            return [];
        }

        $grupo = trim((string) ($dependencias['grupo'] ?? ''));

        return array_map(
            static fn ($l) => ['valor' => $l['codigo'], 'texto' => $l['codigo'] . ' — ' . trim($l['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aNovedad WHERE empresa = ? AND ISNULL(claseLabor, 0) <> 2"
                . ($grupo === '' ? " UNION SELECT DISTINCT LTRIM(RTRIM(c.novedad)), 'Sin descripción en el maestro' FROM aTransaccionTercero c WHERE c.empresa = ? AND NOT EXISTS (SELECT 1 FROM aNovedad d WHERE d.empresa = c.empresa AND d.codigo = c.novedad)" : ' AND LTRIM(RTRIM(grupo)) = ?')
                . ' ORDER BY codigo ASC', $grupo === '' ? [$this->empresa, $this->empresa] : [$this->empresa, $grupo])->getResultArray()
        );
    }

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query("SELECT DISTINCT YEAR(fecha) AS anio FROM aTransaccion WHERE empresa = ? AND anulado = 0 AND tipo IN ('TLA', 'RLF') ORDER BY anio DESC", [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $anio    = in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? '');
        $grupos  = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => $anio, 'ayuda' => 'Fecha de ejecución de la labor.', 'opciones' => $anios],
            ['clave' => 'mes', 'etiqueta' => 'Mes de corte', 'tipo' => 'mes', 'obligatorio' => true, 'defecto' => $anio === date('Y') ? date('n') : '12', 'ayuda' => 'Se acumula de enero hasta este mes.',
                'opciones' => array_map(static fn ($m, $n) => ['valor' => (string) $m, 'texto' => $n], array_keys(self::NOMBRES), self::NOMBRES)],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
            ['clave' => 'labor', 'etiqueta' => 'Labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las labores', 'ayuda' => 'Depende del grupo elegido.', 'depende' => ['grupo'], 'opciones' => $this->opciones('labor', [])],
            ['clave' => 'meta', 'etiqueta' => 'Meta', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'todas',
                'opciones' => [['valor' => 'todas', 'texto' => 'Todas'], ['valor' => 'con', 'texto' => 'Solo con meta'], ['valor' => 'atrasadas', 'texto' => 'Solo atrasadas']]],
        ];
    }

    private static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    private static function plural(int $n, string $uno, string $varios): string
    {
        return self::n($n) . ' ' . ($n === 1 ? $uno : $varios);
    }

    public function consultar(array $filtros): array
    {
        $anio   = (int) $filtros['anio'];
        $mes    = (int) $filtros['mes'];
        $inicio = $anio . '-01-01';
        $fin    = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes)));
        $corte  = min($fin, date('Y-m-d'));
        $mesAct = $corte < $inicio ? 0 : (int) substr($corte, 5, 2);
        $diasT  = $corte < $inicio ? 0 : (int) date('z', strtotime($corte)) + 1;
        $unaLab = $filtros['labor'] !== '';

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $inicio, $fin];

        foreach (['ISNULL(k.finca, c.finca)' => $filtros['finca'], 'd.grupo' => $filtros['grupo'], 'c.novedad' => $filtros['labor']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT lote, loteNombre, finca, fincaNombre, anioSiembra, palmas, hNetas, numero, letra, labor, laborNombre, uMedida, ciclos, mes, SUM(cantidad) AS cantidad, COUNT(*) AS registros
                  FROM (SELECT ISNULL(LTRIM(RTRIM(k.codigo)), '') AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(ISNULL(k.finca, ''))) AS finca,
                               CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS fincaNombre, ISNULL(k.[añoSiembra], 0) AS anioSiembra, ISNULL(k.palmasProduccion, 0) AS palmas,
                               ISNULL(k.hNetas, 0) AS hNetas, ISNULL(k.numero, 0) AS numero, ISNULL(k.letra, '') AS letra, LTRIM(RTRIM(c.novedad)) AS labor,
                               CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre, LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, ISNULL(d.ciclos, 0) AS ciclos,
                               MONTH(ISNULL(c.fechaNovedad, b.fecha)) AS mes, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad
                          FROM aTransaccionTercero c
                          JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                          JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                     LEFT JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                     LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                     LEFT JOIN aFinca i ON LTRIM(RTRIM(i.codigo)) = LTRIM(RTRIM(k.finca)) AND i.empresa = k.empresa
                         WHERE c.empresa = ? AND ISNULL(d.claseLabor, 0) <> 2 AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}) x
                 GROUP BY lote, loteNombre, finca, fincaNombre, anioSiembra, palmas, hNetas, numero, letra, labor, laborNombre, uMedida, ciclos, mes";

        $grupos = [];
        $sinReg = 0;

        foreach ($this->db->query($sql, $binds)->getResultArray() as $c) {
            if ($c['lote'] === '') {
                $sinReg += (int) $c['registros'];

                continue;
            }

            $g = &$grupos[$c['labor'] . '|' . $c['finca'] . '|' . $c['lote']];
            $g = ($g ?? []) + $c;
            $g['meses'][(int) $c['mes']] = ($g['meses'][(int) $c['mes']] ?? 0) + (float) $c['cantidad'];
            unset($g);
        }

        uasort($grupos, static fn ($x, $y) => [$x['labor'], $x['finca'], (int) $x['numero'], (int) $x['anioSiembra'], trim($x['letra']), $x['lote']]
            <=> [$y['labor'], $y['finca'], (int) $y['numero'], (int) $y['anioSiembra'], trim($y['letra']), $y['lote']]);

        $filas   = [];
        $labores = [];

        foreach ($grupos as $g) {
            $u      = $g['uMedida'];
            $ciclos = (int) $g['ciclos'];
            $p      = (int) $g['palmas'];
            $h      = (float) $g['hNetas'];
            $total  = array_sum($g['meses']);
            $div    = $u === 'PAL' && $p > 0 ? $p : ($u === 'HA' && $h > 0 ? $h : 0);
            $prog   = $ciclos > 0 ? round($diasT / $ciclos, 2) : null;
            $pases  = $div > 0 ? round($total / $div, 2) : count(array_filter($g['meses'], static fn ($v) => round($v, 2) > 0));
            $cumpl  = $prog ? round(($div > 0 ? $total / $div : $pases) / ($diasT / $ciclos), 4) : null;
            $labor  = $g['labor'] . ' — ' . (trim($g['laborNombre']) === '' ? 'Sin descripción en el maestro' : trim($g['laborNombre']));
            $fila   = [
                'labor'       => $labor,
                'lote'        => $g['lote'] . ' — ' . trim($g['loteNombre']),
                'finca'       => $g['finca'] . ' — ' . trim($g['fincaNombre']),
                'anioSiembra' => (int) $g['anioSiembra'] === 0 ? '' : (string) (int) $g['anioSiembra'],
                'palmas'      => $p,
                'hNetas'      => $h,
                'uMedida'     => $u,
            ];

            for ($i = 1; $i <= 12; $i++) {
                $fila['m' . $i] = $i > $mesAct ? null : round($g['meses'][$i] ?? 0, 2);
            }

            $filas[] = $fila + [
                'total'        => round($total, 2),
                'pases'        => $pases,
                'programados'  => $prog,
                'cumplimiento' => $cumpl,
                'ciclos'       => $ciclos > 0 ? $ciclos : null,
                'pasesAnio'    => $ciclos > 0 ? round(365 / $ciclos, 1) : null,
                'porMeses'     => $div == 0,
                'atrasada'     => $cumpl !== null && $cumpl < 1,
                'pasesMeta'    => $prog ? $pases : 0,
                'progMeta'     => $prog ?: 0,
            ];

            $l = &$labores[$labor];
            $l['lotes']     = ($l['lotes'] ?? 0) + 1;
            $l['pases']     = ($l['pases'] ?? 0) + $pases;
            $l['prog']      = $prog;
            $l['atrasados'] = ($l['atrasados'] ?? 0) + ($cumpl !== null && $cumpl < 1 ? 1 : 0);
            $l['codigo']    = $g['labor'];
            $l['uMedida']   = $u;
            unset($l);
        }

        foreach ($filas as &$f) {
            $l  = $labores[$f['labor']];
            $f += [
                'cumplimientoLabor' => $l['prog'] ? round($l['pases'] / $l['lotes'] / $l['prog'], 4) : null,
                'atrasadosLabor'    => $l['atrasados'],
                'lotesLabor'        => $l['lotes'],
            ];
        }

        unset($f);

        $conMeta   = array_filter($filas, static fn ($f) => $f['programados'] !== null);
        $nMeta     = count($conMeta);
        $atrasados = count(array_filter($conMeta, static fn ($f) => $f['atrasada']));
        $progSum   = array_sum(array_column($conMeta, 'progMeta'));
        $promedio  = $progSum > 0 ? round(array_sum(array_column($conMeta, 'pasesMeta')) / $progSum, 4) : null;
        $labMeta   = array_filter($labores, static fn ($l) => $l['prog'] !== null);

        $catalogo  = "SELECT COUNT(*) AS n FROM aNovedad WHERE empresa = ? AND ISNULL(activo, 1) = 1 AND ISNULL(claseLabor, 0) <> 2 AND ciclos > 0";
        $catBinds  = [$this->empresa];

        foreach (['grupo' => $filtros['grupo'], 'codigo' => $filtros['labor']] as $campo => $valor) {
            if ($valor !== '') {
                $catalogo  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $catBinds[] = $valor;
            }
        }

        $codigos = array_column($labMeta, 'codigo');

        if ($codigos !== []) {
            $catalogo .= ' AND LTRIM(RTRIM(codigo)) NOT IN (' . implode(',', array_fill(0, count($codigos), '?')) . ')';
            $catBinds  = array_merge($catBinds, $codigos);
        }

        $sinEjec = (int) $this->db->query($catalogo, $catBinds)->getRow()->n;

        uasort($labMeta, static fn ($x, $y) => $x['pases'] / $x['lotes'] / $x['prog'] <=> $y['pases'] / $y['lotes'] / $y['prog']);
        $barras = array_slice($labMeta, 0, 15, true);

        if ($filtros['meta'] !== 'todas') {
            $filas = array_values(array_filter($filas, static fn ($f) => $filtros['meta'] === 'con' ? $f['programados'] !== null : $f['atrasada']));
        }

        $unidad  = $unaLab ? ($filas[0]['uMedida'] ?? array_values($labores)[0]['uMedida'] ?? '') : '';
        $tipo    = $unidad === 'UND' ? 'entero' : 'decimal';
        $suma    = static fn (string $c) => round(array_sum(array_column($filas, $c)), 2);
        $vacio   = $filas === [];
        $nomCorte = self::NOMBRES[$mes] . ' ' . $anio;
        $avisos  = [];

        if ($labores === []) {
            $avisos[] = 'No hay labores registradas entre enero y ' . strtolower($nomCorte) . ' con los filtros seleccionados.';
        } elseif ($vacio) {
            $avisos[] = 'Ningún lote cumple el filtro de meta seleccionado.';
        }

        if ($sinReg > 0) {
            $avisos[] = self::plural($sinReg, 'registro sin lote no se incluye.', 'registros sin lote no se incluyen.');
        }

        if ($corte < $fin) {
            $avisos[] = 'Corte al ' . date('d/m/Y', strtotime($corte)) . ': los programados se calculan con los días transcurridos hasta hoy.';
        }

        if (! $unaLab && $labores !== []) {
            $avisos[] = 'Elija una labor para ver el mapa por lote y los totales de cantidad.';
        }

        $columnas = [
            ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
            ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'palmas', 'titulo' => 'Palmas prod.', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'hNetas', 'titulo' => 'Ha netas', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
        ];
        $totales = [];

        for ($i = 1; $i <= 12; $i++) {
            $columnas[] = ['clave' => 'm' . $i, 'titulo' => self::MESES[$i], 'tipo' => $tipo, 'alineacion' => 'derecha']
                + ($i > $mesAct ? ['total' => false, 'futuro' => true, 'ayuda' => $i > $mes ? 'Posterior al mes de corte' : 'Mes no transcurrido'] : ['total' => $unaLab]);

            if ($unaLab && $i <= $mesAct) {
                $totales['m' . $i] = $suma('m' . $i);
            }
        }

        $columnas = array_merge($columnas, [
            ['clave' => 'total', 'titulo' => 'Total', 'tipo' => $tipo, 'alineacion' => 'derecha', 'total' => $unaLab],
            ['clave' => 'pases', 'titulo' => 'Pases ejec.', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false,
                'ayuda' => 'Cantidad acumulada / palmas en producción (PAL) o Ha netas (HA); otras unidades: meses con labor'],
            ['clave' => 'programados', 'titulo' => 'Prog. al corte', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Días transcurridos al corte / ciclo de la labor'],
            ['clave' => 'cumplimiento', 'titulo' => '% cumpl.', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'pasesMeta', 'denominador' => 'progMeta',
                'ayuda' => 'Pases ejecutados / programados al corte'],
            ['clave' => 'ciclos', 'titulo' => 'Ciclo (días)', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'pasesAnio', 'titulo' => 'Pases al año', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'porMeses', 'titulo' => 'Pases por meses con labor', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'atrasada', 'titulo' => 'Atrasada', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ['clave' => 'pasesMeta', 'titulo' => 'Pases con meta', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'progMeta', 'titulo' => 'Programados con meta', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'cumplimientoLabor', 'titulo' => '% cumpl. de la labor', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'atrasadosLabor', 'titulo' => 'Lotes atrasados de la labor', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
            ['clave' => 'lotesLabor', 'titulo' => 'Lotes con la labor', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false],
        ]);

        $progF = $suma('progMeta');
        $totales += ['cumplimiento' => $progF > 0 ? round($suma('pasesMeta') / $progF, 4) : null] + ($unaLab ? ['total' => $suma('total')] : []);

        $graficas = $labMeta === [] ? [] : [[
            'tipo'       => 'bar',
            'horizontal' => true,
            'titulo'     => '% cumplimiento por labor (con meta)',
            'icono'      => 'bi bi-bar-chart',
            'categorias' => array_keys($barras),
            'series'     => [['nombre' => '% cumplimiento', 'datos' => array_map(static fn ($l) => round($l['pases'] / $l['lotes'] / $l['prog'], 4), array_values($barras))]],
            'colores'    => ['#41a867'],
            'detalle'    => array_map(static fn ($l) => ['pasesProm' => round($l['pases'] / $l['lotes'], 2), 'metaCorte' => $l['prog'], 'atrasados' => $l['atrasados'], 'lotes' => $l['lotes']], array_values($barras)),
            'ejeX'       => '% cumplimiento',
            'formato'    => 'porcentaje',
            'referencia' => ['valor' => 1, 'etiqueta' => 'Meta 100 %', 'color' => '#f0a500'],
        ]];

        if ($unaLab && ! $vacio) {
            $graficas[] = [
                'tipo'       => 'heatmap',
                'titulo'     => 'Mapa por lote · ' . $filas[0]['labor'] . ' · ' . $anio,
                'icono'      => 'bi bi-grid-3x3',
                'categorias' => array_values(self::MESES),
                'series'     => array_map(static fn ($f) => ['nombre' => explode(' — ', $f['lote'])[0], 'datos' => array_map(static fn ($i) => $f['m' . $i] === null ? -2 : ($f['m' . $i] > 0 ? $f['m' . $i] : -1), range(1, 12))], $filas),
                'detalle'    => array_map(static fn ($f) => ['lote' => $f['lote'], 'finca' => $f['finca'], 'siembra' => $f['anioSiembra']], $filas),
                'medida'     => $unidad === '' ? 'Cantidad' : $unidad,
                'formato'    => $tipo,
                'ejeX'       => 'Mes',
                'sufijoX'    => ' ' . $anio,
                'centinelas' => ['-1' => ['nombre' => 'Sin labor', 'color' => '#f1f3f5'], '-2' => ['nombre' => 'Mes no transcurrido', 'color' => '#e3e7eb']],
            ];
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Cumplimiento promedio', 'valor' => $promedio, 'formato' => 'porcentaje', 'icono' => 'bi bi-speedometer2',
                    'ayuda' => $nMeta === 0 ? 'Sin lote-labor con meta' : self::plural($nMeta, 'lote-labor con meta', 'lote-labor con meta') . ' · corte ' . strtolower($nomCorte)],
                ['etiqueta' => 'Labores con meta', 'valor' => $labores === [] ? null : self::n(count($labMeta)) . ' / ' . self::n(count($labores)), 'formato' => 'texto', 'icono' => 'bi bi-list-check',
                    'ayuda' => $labores === [] ? '' : 'Con ciclo definido / labores ejecutadas en el periodo'],
                ['etiqueta' => 'Lote-labor atrasados', 'valor' => $nMeta === 0 ? null : $atrasados, 'formato' => 'entero', 'icono' => 'bi bi-exclamation-triangle',
                    'ayuda' => $nMeta === 0 ? 'Sin lote-labor con meta' : self::n($atrasados / $nMeta * 100, 1) . ' % de los que tienen meta'],
                ['etiqueta' => 'Labores con meta sin ejecución', 'valor' => $sinEjec, 'formato' => 'entero', 'icono' => 'bi bi-slash-circle',
                    'ayuda' => 'Sin ningún pase en el periodo'],
            ],
            'grafica'  => null,
            'graficas' => $graficas,
            'columnas' => $columnas,
            'filas'    => $filas,
            'totales'  => $totales,
            'agrupar'  => 'labor',
            'avisos'   => $avisos,
        ];
    }
}
