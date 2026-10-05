<?php

namespace App\Libraries\Informes\RevisionLabores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Administracion\PesoPromedioRacimosLote;
use App\Libraries\Informes\Informe;

class LaboresDetalladaFechaNovedad extends Informe
{
    public function opciones(string $filtro, array $dependencias): array
    {
        if ($filtro === 'lote') {
            return (new PesoPromedioRacimosLote($this->empresa))->opciones('lote', $dependencias);
        }

        $fi = (string) ($dependencias['fechaInicial'] ?? '');
        $ff = (string) ($dependencias['fechaFinal'] ?? '');

        return $filtro === 'labor' ? $this->labores((string) ($dependencias['grupo'] ?? ''), self::fecha($fi) && self::fecha($ff) ? [$fi, $ff] : null) : [];
    }

    private static function fecha(string $valor): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $p) === 1 && checkdate((int) $p[2], (int) $p[3], (int) $p[1]);
    }

    private function eliminadas(): string
    {
        return $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';
    }

    private function labores(string $grupo, ?array $rango): array
    {
        $sql   = "SELECT LTRIM(RTRIM(d.codigo)) AS codigo, ISNULL(d.descripcion, '') AS descripcion FROM aNovedad d WHERE d.empresa = ?";
        $binds = [$this->empresa];

        if ($grupo !== '') {
            $sql    .= ' AND LTRIM(RTRIM(d.grupo)) = ?';
            $binds[] = $grupo;
        }

        if ($rango !== null) {
            $sql  .= " AND EXISTS (SELECT 1 FROM aTransaccionTercero c
                                     JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                                     JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$this->eliminadas()}
                                    WHERE c.empresa = d.empresa AND c.novedad = d.codigo AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?)";
            $binds = array_merge($binds, $rango);
        }

        return array_map(
            static fn ($l) => ['valor' => $l['codigo'], 'texto' => $l['codigo'] . ' — ' . trim($l['descripcion'])],
            $this->db->query($sql . ' ORDER BY d.codigo ASC', $binds)->getResultArray()
        );
    }

    public function filtros(): array
    {
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t')],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
            ['clave' => 'labor', 'etiqueta' => 'Labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las labores', 'ayuda' => 'Solo labores con movimiento en el rango. Elija una para totalizar cantidad.',
                'depende' => ['grupo', 'fechaInicial', 'fechaFinal'], 'opciones' => $this->labores('', null)],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca elegida.', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
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

    public function validar(array $entrada): array|string
    {
        $v = parent::validar($entrada);

        if (! is_array($v)) {
            return $v;
        }

        if ($v['fechaInicial'] > $v['fechaFinal']) {
            return 'La fecha inicial no puede ser mayor que la final.';
        }

        if ($v['grupo'] !== '' && $v['labor'] !== '' && $this->db->query('SELECT 1 AS ok FROM aNovedad WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ? AND LTRIM(RTRIM(grupo)) = ?', [$this->empresa, $v['labor'], $v['grupo']])->getRow() === null) {
            return 'La labor no pertenece al grupo seleccionado.';
        }

        return $v['finca'] !== '' && $v['lote'] !== '' && explode('|', $v['lote'], 2)[0] !== $v['finca'] ? 'El lote no pertenece a la finca seleccionada.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $where = '';
        $binds = [$this->empresa, $this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];
        $lote  = $filtros['lote'] === '' ? ['', ''] : explode('|', $filtros['lote'], 2);

        foreach (['c.finca' => $filtros['finca'] !== '' ? $filtros['finca'] : $lote[0], 'b.lote' => $lote[1], 'c.novedad' => $filtros['labor'], 'd.grupo' => $filtros['grupo']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $valor;
            }
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo, CAST(ISNULL(g.descripcion, '') AS varchar(100)) AS grupoNombre, LTRIM(RTRIM(c.novedad)) AS labor,
                       CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre, ISNULL(d.tarea, 0) AS tarea, CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha, LTRIM(RTRIM(c.tipo)) AS tipo,
                       CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS numero, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote,
                       CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, ISNULL(k.[añoSiembra], 0) AS anioSiembra, LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre,
                       ISNULL(c.contratista, 0) AS contratista, LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad,
                       ISNULL(c.jornales, 0) AS jornales, ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor
                  FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$this->eliminadas()}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                  LEFT JOIN aGrupoNovedad g ON g.codigo = d.grupo AND g.empresa = d.empresa
                  LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                  LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                 OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) OR LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.tercero)))
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) THEN 0 ELSE 1 END) t
                 WHERE c.empresa = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}
                 ORDER BY grupo ASC, labor ASC, fecha ASC, c.finca ASC, lote ASC, tercero ASC, numero ASC, c.registro ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $query     = $this->db->query($sql, $binds);
        $avisos    = [];
        $filas     = [];
        $negLabor  = [];
        $sinNombre = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' registros; la última labor puede estar incompleta. Filtre por labor o reduzca el rango.';

                break;
            }

            $nombre  = trim($c['nombre']);
            $valor   = (float) $c['valor'];
            $labor   = $c['labor'] . ' — ' . trim($c['laborNombre']);
            $filas[] = [
                'labor'       => $labor,
                'grupo'       => $c['grupo'] === '' ? '' : $c['grupo'] . ' — ' . trim($c['grupoNombre']),
                'fecha'       => substr((string) $c['fecha'], 0, 10),
                'transaccion' => $c['tipo'] . '-' . $c['numero'],
                'finca'       => trim($c['finca']),
                'lote'        => $c['lote'] === '' ? '' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre'])),
                'anioSiembra' => (int) $c['anioSiembra'] === 0 ? '' : (string) (int) $c['anioSiembra'],
                'trabajador'  => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'contratista' => (int) $c['contratista'] === 1 ? 'Sí' : 'No',
                'uMedida'     => $c['uMedida'],
                'cantidad'    => (float) $c['cantidad'],
                'jornales'    => (float) $c['jornales'],
                'precio'      => (float) $c['precio'],
                'valor'       => $valor,
                'codigo'      => $c['tercero'],
                'negativo'    => $valor < 0,
                'tarea'       => (float) $c['tarea'],
            ];

            $negLabor[$labor] = ($negLabor[$labor] ?? false) || $valor < 0;

            if ($nombre === '') {
                $sinNombre[$c['tercero']] = true;
            }
        }

        $query->freeResult();

        foreach ($filas as &$f) {
            $f['conNegativo'] = $negLabor[$f['labor']];
        }

        unset($f);

        $unidades  = array_values(array_unique(array_column($filas, 'uMedida')));
        $unaUnidad = count($unidades) === 1;
        $unidad    = $unaUnidad ? $unidades[0] : '';
        $totales   = ['jornales' => round(array_sum(array_column($filas, 'jornales')), 2), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $totales   = $unaUnidad ? ['cantidad' => round(array_sum(array_column($filas, 'cantidad')), 2)] + $totales : $totales;
        $labores   = count($negLabor);
        $negativos = array_sum(array_filter(array_column($filas, 'valor'), static fn ($v) => $v < 0));
        $lotes     = count(array_filter(array_unique(array_column($filas, 'lote')), static fn ($v) => $v !== ''));
        $fincas    = count(array_filter(array_unique(array_column($filas, 'finca')), static fn ($v) => $v !== ''));
        $sinLote   = count(array_filter(array_column($filas, 'lote'), static fn ($v) => $v === ''));
        $trabDia   = count(array_unique(array_map(static fn ($f) => $f['codigo'] . '|' . $f['fecha'], $filas)));
        $tarea     = $labores === 1 && $filas[0]['tarea'] > 0 ? ' · Esperado ' . self::n($filas[0]['tarea'], floor($filas[0]['tarea']) == $filas[0]['tarea'] ? 0 : 2) : '';

        if ($sinNombre !== []) {
            $avisos[] = self::n(count($sinNombre)) . (count($sinNombre) === 1 ? ' trabajador no se encontró' : ' trabajadores no se encontraron') . ' en el maestro de terceros.';
        }

        if ($unaUnidad) {
            $kpi1 = ['etiqueta' => 'Cantidad ejecutada', 'valor' => $totales['cantidad'], 'formato' => 'decimal', 'icono' => 'bi bi-box-seam', 'ayuda' => 'En ' . $unidad . ' · ' . self::plural($labores, 'labor', 'labores')];
            $kpi4 = $totales['jornales'] > 0
                ? ['etiqueta' => 'Rendimiento', 'valor' => round($totales['cantidad'] / $totales['jornales'], 2), 'formato' => 'decimal', 'icono' => 'bi bi-speedometer2', 'ayuda' => $unidad . ' por jornal' . $tarea]
                : ['etiqueta' => 'Cantidad por trabajador-día', 'valor' => round($totales['cantidad'] / $trabDia, 2), 'formato' => 'decimal', 'icono' => 'bi bi-speedometer2', 'ayuda' => 'Sin jornales registrados' . ($tarea === '' ? '' : $tarea . ' ' . $unidad . '/jornal')];
        } else {
            $kpi1 = ['etiqueta' => 'Labores ejecutadas', 'valor' => $labores, 'formato' => 'entero', 'icono' => 'bi bi-box-seam', 'ayuda' => $filas === [] ? '' : 'Unidades mixtas: elija una labor para totalizar cantidad'];
            $kpi4 = ['etiqueta' => 'Trabajadores', 'valor' => count(array_unique(array_column($filas, 'codigo'))), 'formato' => 'entero', 'icono' => 'bi bi-people',
                'ayuda' => $filas === [] ? '' : self::plural(count(array_unique(array_column($filas, 'fecha'))), 'día', 'días') . ' con labor'];
        }

        $semanal = (strtotime($filtros['fechaFinal']) - strtotime($filtros['fechaInicial'])) / 86400 > 45;
        $lunes   = static fn (string $f) => date('Y-m-d', strtotime($f . ' -' . (date('N', strtotime($f)) - 1) . ' days'));
        $anio    = substr($semanal ? $lunes($filtros['fechaInicial']) : $filtros['fechaInicial'], 0, 4) === substr($filtros['fechaFinal'], 0, 4) ? 'd/m' : 'd/m/Y';
        $periodos = [];

        foreach ($filas as $f) {
            $p                 = &$periodos[$semanal ? $lunes($f['fecha']) : $f['fecha']];
            $p['cantidad']     = ($p['cantidad'] ?? 0) + $f['cantidad'];
            $p['valor']        = ($p['valor'] ?? 0) + $f['valor'];
            $p['registros']    = ($p['registros'] ?? 0) + 1;
            $p['trabajadores'][$f['codigo']] = true;
            $p['labores'][$f['labor']] = true;
            $p['lotes'][$f['lote']] = true;
            unset($p);
        }

        ksort($periodos);

        $detalle = [];

        foreach ($periodos as $clave => $p) {
            $rango     = $semanal ? ['rango' => date('d/m', strtotime(max($clave, $filtros['fechaInicial']))) . ' – ' . date('d/m', strtotime(min(date('Y-m-d', strtotime($clave . ' +6 days')), $filtros['fechaFinal'])))] : [];
            $detalle[] = $rango + ($unaUnidad
                ? ['valor' => round($p['valor'], 2), 'trabajadores' => count($p['trabajadores']), 'lotes' => count(array_filter(array_keys($p['lotes']), static fn ($v) => $v !== '')), 'registros' => $p['registros']]
                : ['labores' => count($p['labores']), 'trabajadores' => count($p['trabajadores']), 'registros' => $p['registros']]);
        }

        return [
            'kpis' => [
                $kpi1,
                ['etiqueta' => 'Valor total', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Lotes intervenidos', 'valor' => $lotes, 'formato' => 'entero', 'icono' => 'bi bi-geo-alt',
                    'ayuda' => implode(' · ', array_filter([$fincas > 0 ? self::plural($fincas, 'finca', 'fincas') : '', $sinLote > 0 ? self::plural($sinLote, 'registro sin lote', 'registros sin lote') : '']))],
                $kpi4,
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => false,
                'titulo'     => ($unaUnidad ? 'Cantidad ejecutada' : 'Valor') . ($semanal ? ' por semana' : ' por día'),
                'categorias' => array_map(static fn ($k) => ($semanal ? 'Sem. ' : '') . date($anio, strtotime((string) $k)), array_keys($periodos)),
                'series'     => [['nombre' => $unaUnidad ? 'Cantidad ejecutada' : 'Valor', 'datos' => array_map(static fn ($p) => round($unaUnidad ? $p['cantidad'] : $p['valor'], 2), array_values($periodos))]],
                'detalle'    => $detalle,
                'ejeY'       => $unaUnidad ? 'Cantidad (' . $unidad . ')' : 'Valor',
                'formato'    => $unaUnidad ? 'decimal' : 'moneda',
            ],
            'columnas' => [
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'grupo', 'titulo' => 'Grupo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'transaccion', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'anioSiembra', 'titulo' => 'Año siembra', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'contratista', 'titulo' => 'Contratista', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => $unaUnidad ? true : 'grupo'] + ($unaUnidad ? [] : ['ayuda' => 'Se subtotaliza por labor; el total general no se calcula porque mezcla unidades.']),
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'precio', 'titulo' => 'Precio', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'labor',
            'avisos'  => $avisos,
        ];
    }
}
