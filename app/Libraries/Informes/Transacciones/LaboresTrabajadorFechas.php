<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Informe;

class LaboresTrabajadorFechas extends Informe
{
    private const DIAS = [1 => 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    public function filtros(): array
    {
        $trabajadores = array_map(
            static fn ($t) => ['valor' => $t['tercero'], 'texto' => $t['tercero'] . ' — ' . (trim($t['nombre']) === '' ? 'Sin nombre en terceros' : trim($t['nombre']))],
            $this->db->query("SELECT x.tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre
                                FROM (SELECT DISTINCT LTRIM(RTRIM(c.tercero)) AS tercero FROM aTransaccionTercero c
                                        JOIN aTransaccion a ON a.numero = c.numero AND a.tipo = c.tipo AND a.empresa = c.empresa AND a.anulado = 0
                                       WHERE c.empresa = ?) x
                               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                                             WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                                             ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) t
                               ORDER BY CASE WHEN ISNULL(LTRIM(RTRIM(t.nombre)), '') = '' THEN 1 ELSE 0 END, t.nombre, x.tercero", [$this->empresa, $this->empresa])->getResultArray()
        );
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'select2', 'obligatorio' => true, 'defecto' => '', 'placeholder' => 'Buscar por cédula o nombre', 'ayuda' => 'Solo trabajadores con labores registradas.', 'opciones' => $trabajadores],
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha en que se ejecutó la labor, no la de registro.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t')],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => count($grupos) <= 15 ? 'select' : 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
        ];
    }

    private static function n(float $valor, int $decimales = 0): string
    {
        return number_format($valor, $decimales, ',', '.');
    }

    public function validar(array $entrada): array|string
    {
        $valores = parent::validar($entrada);

        return is_array($valores) && $valores['fechaInicial'] > $valores['fechaFinal'] ? 'La fecha inicial no puede ser mayor que la final.' : $valores;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $tercero = $this->db->query("SELECT (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) FROM cTercero
                                              WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = ? OR LTRIM(RTRIM(codigo)) = ?)
                                              ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = ? THEN 0 ELSE 1 END) AS nombre,
                                            (SELECT TOP 1 ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion))
                                               FROM nFuncionario f
                                               LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                                               LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END
                                              WHERE f.empresa = ? AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = ?) AS contratista",
            array_merge([$this->empresa], array_fill(0, 3, $filtros['trabajador']), [$this->empresa, $filtros['trabajador']]))->getRowArray();
        $nombre      = trim((string) $tercero['nombre']);
        $contratista = trim((string) $tercero['contratista']);
        $trabajador  = $filtros['trabajador'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre);

        $where = '';
        $binds = [$this->empresa, $filtros['trabajador'], $filtros['fechaInicial'], $filtros['fechaFinal']];

        if ($filtros['grupo'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(d.grupo)) = ?';
            $binds[] = $filtros['grupo'];
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha,
                       LTRIM(RTRIM(c.tipo)) AS tipo, CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS numero, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca,
                       LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(c.novedad)) AS labor, ISNULL(d.descripcion, '') AS laborNombre,
                       LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL(b.racimos, 0) END AS racimos,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL((SELECT TOP 1 z.tiquete FROM aTransaccionBascula z WHERE z.tipo = a.tipo AND z.empresa = a.empresa AND z.numero = a.numero), '') ELSE '' END AS tiquete,
                       ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, ISNULL(c.periodo, 0) AS periodo, c.[año] AS anio
                  FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                  LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                  LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                 WHERE c.empresa = ? AND c.tercero = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}
                 ORDER BY fecha ASC, numero ASC, c.tipo ASC, c.registro ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $query  = $this->db->query($sql, $binds);
        $avisos = [];
        $filas  = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' registros. Reduzca el rango.';

                break;
            }

            $fecha   = substr((string) $c['fecha'], 0, 10);
            $valor   = (float) $c['valor'];
            $filas[] = [
                'periodo'     => (int) $c['periodo'] === 0 ? '' : sprintf('%04d-%03d', $c['anio'], $c['periodo']),
                'trabajador'  => $trabajador,
                'fecha'       => $fecha,
                'dia'         => self::DIAS[(int) date('N', strtotime($fecha))],
                'transaccion' => $c['tipo'] . '-' . $c['numero'],
                'finca'       => trim($c['finca']),
                'lote'        => $c['lote'] === '' ? '' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre'])),
                'labor'       => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'uMedida'     => $c['uMedida'],
                'tiquete'     => trim((string) $c['tiquete']),
                'racimos'     => $c['racimos'] === null ? null : (int) $c['racimos'],
                'cantidad'    => (float) $c['cantidad'],
                'jornales'    => (float) $c['jornales'],
                'precio'      => (float) $c['precio'],
                'valor'       => $valor,
                'negativo'    => $valor < 0,
                'codigo'      => $c['labor'],
                'laborNombre' => trim($c['laborNombre']),
            ];
        }

        $query->freeResult();

        if ($nombre === '') {
            $avisos[] = 'El trabajador no se encontró en el maestro de terceros.';
        }

        $semanal = count(array_filter(array_column($filas, 'periodo'), static fn ($p) => $p === '')) > count($filas) / 2;
        $grupos  = [];

        foreach ($filas as &$f) {
            $f['periodo'] = $semanal ? date('Y-m-d', strtotime($f['fecha'] . ' -' . (date('N', strtotime($f['fecha'])) - 1) . ' days')) : ($f['periodo'] === '' ? 'z' : $f['periodo']);
            $g            = &$grupos[$f['periodo']];
            $g['min']     = min($g['min'] ?? $f['fecha'], $f['fecha']);
            $g['max']     = max($g['max'] ?? $f['fecha'], $f['fecha']);
            $g['dias'][$f['fecha']] = true;
            $g['alerta']  = ($g['alerta'] ?? false) || $f['negativo'];
            unset($g);
        }

        unset($f);

        $claves = array_keys($grupos);
        sort($claves, SORT_STRING);
        $orden  = array_flip($claves);
        $fincas = array_values(array_filter(array_unique(array_column($filas, 'finca')), static fn ($v) => $v !== ''));

        usort($filas, static fn ($a, $b) => $orden[$a['periodo']] <=> $orden[$b['periodo']]);

        foreach ($filas as &$f) {
            $g                = $grupos[$f['periodo']];
            $inicio           = max($f['periodo'], $filtros['fechaInicial']);
            $fin              = min(date('Y-m-d', strtotime($f['periodo'] . ' +6 days')), $filtros['fechaFinal']);
            $formato          = substr($filtros['fechaInicial'], 0, 4) !== substr($filtros['fechaFinal'], 0, 4) || substr($inicio, 0, 4) !== substr($fin, 0, 4) ? 'd/m/Y' : 'd/m';
            $f['periodo']     = $semanal ? 'Semana — ' . date($formato, strtotime($inicio)) . ' – ' . date($formato, strtotime($fin))
                : ($f['periodo'] === 'z' ? 'S/P — Sin periodo asignado' : (int) substr($f['periodo'], 5) . '/' . substr($f['periodo'], 0, 4) . ' — ' . date('d/m', strtotime($g['min'])) . ' – ' . date('d/m', strtotime($g['max'])));
            $f['alertaGrupo']   = $g['alerta'];
            $f['diasGrupo']     = count($g['dias']);
            $f['fincaSubtexto'] = count($fincas) > 1 ? $f['finca'] : '';
        }

        unset($f);

        $porDia   = [];
        $porLabor = [];

        foreach ($filas as $f) {
            $porDia[$f['fecha']] = ($porDia[$f['fecha']] ?? 0) + $f['valor'];
            $l                   = &$porLabor[$f['codigo']];
            $l['nombre']         = $f['laborNombre'];
            $l['valor']          = ($l['valor'] ?? 0) + $f['valor'];
            $l['registros']      = ($l['registros'] ?? 0) + 1;
            $l['cantidad']       = ($l['cantidad'] ?? 0) + $f['cantidad'];
            $l['uMedida']        = $f['uMedida'];
            $l['dias'][$f['fecha']] = true;
            unset($l);
        }

        uasort($porLabor, static fn ($a, $b) => $b['valor'] <=> $a['valor']);

        $top       = array_slice($porLabor, 0, 10, true);
        $unaUnidad = count(array_unique(array_column($filas, 'uMedida'))) === 1;
        $totales   = ['jornales' => round(array_sum(array_column($filas, 'jornales')), 2), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $totales   = $unaUnidad ? ['cantidad' => round(array_sum(array_column($filas, 'cantidad')), 2)] + $totales : $totales;
        $negativos = array_sum(array_filter(array_column($filas, 'valor'), static fn ($v) => $v < 0));
        $dias      = count($porDia);
        $mayor     = $porDia === [] ? null : array_search(max($porDia), $porDia, true);
        $trans     = count(array_unique(array_column($filas, 'transaccion')));

        return [
            'kpis' => [
                ['etiqueta' => 'Valor devengado', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos'],
                count(array_filter(array_column($filas, 'jornales'), static fn ($j) => $j > 0)) > count($filas) / 2
                    ? ['etiqueta' => 'Jornales', 'valor' => $totales['jornales'], 'formato' => 'decimal', 'icono' => 'bi bi-calendar-check', 'ayuda' => 'Promedio ' . self::n($totales['jornales'] / $dias, 2) . ' por día']
                    : ['etiqueta' => 'Registros', 'valor' => count($filas), 'formato' => 'entero', 'icono' => 'bi bi-list-ul',
                        'ayuda' => self::n($trans) . ($trans === 1 ? ' transacción' : ' transacciones') . ' · ' . self::n(count($porLabor)) . (count($porLabor) === 1 ? ' labor' : ' labores')],
                ['etiqueta' => 'Días con labor', 'valor' => $dias, 'formato' => 'entero', 'icono' => 'bi bi-calendar3',
                    'ayuda' => implode(' · ', array_filter([count($fincas) === 1 ? 'Finca ' . $fincas[0] : (count($fincas) > 1 ? self::n(count($fincas)) . ' fincas' : ''), $contratista === '' ? '' : 'Contratista ' . $contratista]))],
                ['etiqueta' => 'Promedio por día', 'valor' => $dias === 0 ? 0 : round($totales['valor'] / $dias, 2), 'formato' => 'moneda', 'icono' => 'bi bi-graph-up',
                    'ayuda' => $mayor === null ? '' : 'Mayor: $ ' . self::n($porDia[$mayor]) . ' el ' . date(substr($filtros['fechaInicial'], 0, 4) === substr($filtros['fechaFinal'], 0, 4) ? 'd/m' : 'd/m/Y', strtotime($mayor))],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => count($porLabor) > 10 ? 'Valor devengado por labor (top 10)' : 'Valor devengado por labor',
                'categorias' => array_values(array_column($top, 'nombre')),
                'series'     => [['nombre' => 'Valor devengado', 'datos' => array_map(static fn ($l) => round($l['valor'], 2), array_values($top))]],
                'detalle'    => array_map(static fn ($c, $l) => ['codigo' => (string) $c, 'registros' => $l['registros'],
                    'cantidad' => self::n($l['cantidad'], floor($l['cantidad']) == $l['cantidad'] ? 0 : 2) . ($l['uMedida'] === '' ? '' : ' ' . $l['uMedida']), 'dias' => count($l['dias'])], array_keys($top), array_values($top)),
                'ejeX'       => 'Valor devengado',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'periodo', 'titulo' => $semanal ? 'Semana' : 'Periodo nómina', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'dia', 'titulo' => 'Día', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'transaccion', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'tiquete', 'titulo' => 'Tiquete', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'racimos', 'titulo' => 'Racimos línea', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Racimos de toda la línea de cosecha; se repiten en cada trabajador de la línea, por eso no se totalizan.'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => $unaUnidad] + ($unaUnidad ? [] : ['ayuda' => 'No se totaliza porque mezcla unidades.']),
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'precio', 'titulo' => 'Precio', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'periodo',
            'avisos'  => $avisos,
        ];
    }
}
