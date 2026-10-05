<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LaboresFecha extends Informe
{
    public function filtros(): array
    {
        $tipos = array_map(
            static fn ($t) => ['valor' => $t['codigo'], 'texto' => $t['codigo'] . ' — ' . trim($t['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM gTipoTransaccion
                               WHERE empresa = ? AND codigo IN (SELECT DISTINCT tipo FROM aTransaccionTercero WHERE empresa = ?) ORDER BY codigo ASC", [$this->empresa, $this->empresa])->getResultArray()
        );
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha del documento digitado, no de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'tipoTransaccion', 'etiqueta' => 'Tipo de transacción', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos', 'opciones' => $tipos],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
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

        return $v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year')) ? 'El rango no puede superar un año.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        foreach (['tipoTransaccion' => 'c.tipo', 'finca' => 'c.finca', 'grupo' => 'd.grupo'] as $clave => $campo) {
            if ($filtros[$clave] !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $filtros[$clave];
            }
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(c.tipo)) AS tipo, CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS numero, CONVERT(date, a.fecha) AS fechaDocumento,
                       CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca, LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote,
                       CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre,
                       ISNULL(d.manejaLote, 0) AS manejaLote, LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre, CAST(ISNULL(p.nombre, '') AS varchar(100)) AS contratista,
                       LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales,
                       ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, b.registro AS linea,
                       LTRIM(RTRIM(ISNULL(a.usuarioRegistro, ''))) AS usuario, CAST(ISNULL(NULLIF(LTRIM(RTRIM(a.referencia)), ''), ISNULL(LTRIM(RTRIM(a.observacion)), '')) AS varchar(61)) AS referencia
                  FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                  LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                  LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                 OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) OR LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.tercero)))
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) THEN 0 ELSE 1 END) t
                 OUTER APPLY (SELECT TOP 1 ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion)) AS nombre
                                FROM nFuncionario f
                                LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                                LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END
                               WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero))) p
                 WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}
                 ORDER BY fechaDocumento ASC, tipo ASC, numero ASC, b.registro ASC, tercero ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '102400');
        ini_set('memory_limit', '256M');

        $query     = $this->db->query($sql, $binds);
        $avisos    = [];
        $filas     = [];
        $docs      = [];
        $sinNombre = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' registros; el último documento puede estar incompleto. Filtre por finca, tipo o grupo, o reduzca el rango.';

                break;
            }

            $nombre    = trim($c['nombre']);
            $valor     = (float) $c['valor'];
            $documento = $c['tipo'] . '-' . $c['numero'];
            $faltaLote = $c['lote'] === '' && (int) $c['manejaLote'] === 1;
            $finca     = trim($c['finca']);
            $filas[]   = [
                'documento'   => $documento,
                'fecha'       => substr((string) $c['fecha'], 0, 10),
                'finca'       => $finca,
                'lote'        => $c['lote'] === '' ? '' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre'])),
                'labor'       => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'trabajador'  => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'contratista' => trim($c['contratista']),
                'uMedida'     => $c['uMedida'],
                'cantidad'    => (float) $c['cantidad'],
                'jornales'    => (float) $c['jornales'],
                'precio'      => (float) $c['precio'],
                'valor'       => $valor,
                'codigo'      => $c['tercero'],
                'negativo'    => $valor < 0,
                'faltaLote'   => $faltaLote,
            ];

            $d                = &$docs[$documento];
            $d['tipo']        = $c['tipo'];
            $d['fecha']       = substr((string) $c['fechaDocumento'], 0, 10);
            $d['conNegativo'] = ($d['conNegativo'] ?? false) || $valor < 0;
            $d['fincas'][$finca] = true;
            $d['lineas'][$c['linea']] = true;
            $d['trabajadores'][$c['tercero']] = true;
            $d['unidades'][$c['uMedida']] = true;
            $d['usuario']     = $c['usuario'];
            $d['referencia']  = trim($c['referencia']);
            unset($d);

            if ($nombre === '') {
                $sinNombre[$c['tercero']] = true;
            }
        }

        $query->freeResult();

        foreach ($docs as &$d) {
            $d['fincas'] = array_values(array_filter(array_map('strval', array_keys($d['fincas'])), static fn ($v) => $v !== ''));
        }

        unset($d);

        foreach ($filas as &$f) {
            $d                          = $docs[$f['documento']];
            $f['conNegativo']           = $d['conNegativo'];
            $f['fechaDocumento']        = date('d/m/Y', strtotime($d['fecha']));
            $f['fincasDocumento']       = $d['fincas'];
            $f['lineasDocumento']       = count($d['lineas']);
            $f['trabajadoresDocumento'] = count($d['trabajadores']);
            $f['usuario']               = $d['usuario'];
            $f['referencia']            = mb_strlen($d['referencia']) > 60 ? rtrim(mb_substr($d['referencia'], 0, 60)) . '…' : $d['referencia'];
        }

        unset($f);

        $unaUnidad    = count(array_unique(array_column($filas, 'uMedida'))) === 1;
        $porDocumento = $unaUnidad || array_filter($docs, static fn ($d) => count($d['unidades']) > 1) === [];
        $totalCant    = $unaUnidad ? true : ($porDocumento ? 'grupo' : false);
        $totales      = ['jornales' => round(array_sum(array_column($filas, 'jornales')), 2), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $totales      = $unaUnidad ? ['cantidad' => round(array_sum(array_column($filas, 'cantidad')), 2)] + $totales : $totales;
        $negativos    = array_sum(array_filter(array_column($filas, 'valor'), static fn ($v) => $v < 0));
        $trabajadores = count(array_unique(array_column($filas, 'codigo')));
        $documentos   = count($docs);
        $faltantes    = count(array_filter(array_column($filas, 'faltaLote')));
        $porTipo      = array_count_values(array_column($docs, 'tipo'));

        arsort($porTipo);

        if ($sinNombre !== []) {
            $avisos[] = self::n(count($sinNombre)) . (count($sinNombre) === 1 ? ' trabajador no se encontró' : ' trabajadores no se encontraron') . ' en el maestro de terceros.';
        }

        $semanal  = (strtotime($filtros['fechaFinal']) - strtotime($filtros['fechaInicial'])) / 86400 > 45;
        $lunes    = static fn (string $f) => date('Y-m-d', strtotime($f . ' -' . (date('N', strtotime($f)) - 1) . ' days'));
        $anio     = substr($semanal ? $lunes($filtros['fechaInicial']) : $filtros['fechaInicial'], 0, 4) === substr($filtros['fechaFinal'], 0, 4) ? 'd/m' : 'd/m/Y';
        $periodos = [];

        foreach ($filas as $f) {
            $fecha             = $docs[$f['documento']]['fecha'];
            $p                 = &$periodos[$semanal ? $lunes($fecha) : $fecha];
            $p['valor']        = ($p['valor'] ?? 0) + $f['valor'];
            $p['jornales']     = ($p['jornales'] ?? 0) + $f['jornales'];
            $p['registros']    = ($p['registros'] ?? 0) + 1;
            $p['documentos'][$f['documento']] = true;
            $p['trabajadores'][$f['codigo']] = true;
            unset($p);
        }

        ksort($periodos);

        $detalle = [];

        foreach ($periodos as $clave => $p) {
            $detalle[] = ($semanal ? ['rango' => date('d/m', strtotime(max($clave, $filtros['fechaInicial']))) . ' – ' . date('d/m', strtotime(min(date('Y-m-d', strtotime($clave . ' +6 days')), $filtros['fechaFinal'])))] : [])
                + ['documentos' => count($p['documentos']), 'trabajadores' => count($p['trabajadores']), 'registros' => $p['registros']]
                + ($totales['jornales'] > 0 ? ['jornales' => round($p['jornales'], 2)] : []);
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Transacciones', 'valor' => $documentos, 'formato' => 'entero', 'icono' => 'bi bi-file-earmark-text',
                    'ayuda' => implode(' · ', array_map(static fn ($t, $n) => $t . ' ' . self::n($n), array_keys($porTipo), $porTipo))],
                $totales['jornales'] > 0
                    ? ['etiqueta' => 'Jornales', 'valor' => $totales['jornales'], 'formato' => 'decimal', 'icono' => 'bi bi-calendar-check', 'ayuda' => 'Promedio ' . self::n($totales['jornales'] / $trabajadores, 2) . ' por trabajador']
                    : ['etiqueta' => 'Registros', 'valor' => count($filas), 'formato' => 'entero', 'icono' => 'bi bi-list-ul',
                        'ayuda' => 'Sin jornales registrados' . ($documentos > 0 ? ' · ' . self::n(count($filas) / $documentos, 1) . ' por documento en promedio' : '')],
                ['etiqueta' => 'Trabajadores', 'valor' => $trabajadores, 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $filas === [] ? '' : self::plural(count(array_unique(array_column($docs, 'fecha'))), 'día', 'días') . ' con documentos'
                        . ($faltantes > 0 ? ' · ' . self::plural($faltantes, 'línea sin lote requerido', 'líneas sin lote requerido') : '')],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => false,
                'titulo'     => 'Valor digitado' . ($semanal ? ' por semana' : ' por día'),
                'categorias' => array_map(static fn ($k) => ($semanal ? 'Sem. ' : '') . date($anio, strtotime((string) $k)), array_keys($periodos)),
                'series'     => [['nombre' => 'Valor', 'datos' => array_map(static fn ($p) => round($p['valor'], 2), array_values($periodos))]],
                'detalle'    => $detalle,
                'ejeY'       => 'Valor',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'documento', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha labor', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'contratista', 'titulo' => 'Contratista', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => $totalCant]
                    + ($unaUnidad ? [] : ['ayuda' => $porDocumento ? 'Se subtotaliza por documento; el total general no se calcula porque mezcla unidades.' : 'No se totaliza porque mezcla unidades.']),
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'precio', 'titulo' => 'Precio', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'documento',
            'avisos'  => $avisos,
        ];
    }
}
