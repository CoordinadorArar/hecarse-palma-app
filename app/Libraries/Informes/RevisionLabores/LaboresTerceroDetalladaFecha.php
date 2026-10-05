<?php

namespace App\Libraries\Informes\RevisionLabores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LaboresTerceroDetalladaFecha extends Informe
{
    private const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function filtros(): array
    {
        $labores = array_map(
            static fn ($l) => ['valor' => $l['codigo'], 'texto' => $l['codigo'] . ' — ' . trim($l['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );
        $tipos = array_map(
            static fn ($t) => ['valor' => $t['codigo'], 'texto' => $t['codigo'] . ' — ' . trim($t['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM gTipoTransaccion
                               WHERE empresa = ? AND codigo IN (SELECT DISTINCT tipo FROM aTransaccionTercero WHERE empresa = ?) ORDER BY codigo ASC", [$this->empresa, $this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t')],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'texto', 'obligatorio' => false, 'defecto' => '', 'ayuda' => 'Código o parte del nombre. Recomendado en rangos largos.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'labor', 'etiqueta' => 'Labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las labores', 'opciones' => $labores],
            ['clave' => 'tipoTransaccion', 'etiqueta' => 'Tipo de transacción', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos', 'opciones' => $tipos],
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

        $where = '';
        $binds = [$this->empresa, $this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        foreach (['finca' => 'c.finca', 'labor' => 'c.novedad', 'tipoTransaccion' => 'c.tipo'] as $clave => $campo) {
            if ($filtros[$clave] !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $filtros[$clave];
            }
        }

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre, CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha,
                       LTRIM(RTRIM(c.tipo)) AS tipo, CAST(SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50) AS bigint) AS numero, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca,
                       LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(c.novedad)) AS labor, ISNULL(d.descripcion, '') AS laborNombre,
                       LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL(b.racimos, 0) END AS racimos,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL((SELECT TOP 1 z.tiquete FROM aTransaccionBascula z WHERE z.tipo = a.tipo AND z.empresa = a.empresa AND z.numero = a.numero), '') ELSE '' END AS tiquete,
                       ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, ISNULL(CAST(NULLIF(c.periodo, 0) AS varchar(20)), '') AS periodo
                  FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                  LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                  LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                 OUTER APPLY (SELECT TOP 1 codigo, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) OR LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.tercero)))
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) THEN 0 ELSE 1 END) t
                 WHERE c.empresa = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}";

        if ($filtros['trabajador'] !== '') {
            $sql   .= ' AND (c.tercero LIKE ? OR t.codigo LIKE ? OR t.nombre LIKE ?)';
            $like   = '%' . $filtros['trabajador'] . '%';
            $binds  = array_merge($binds, [$like, $like, $like]);
        }

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');
        ini_set('memory_limit', '256M');

        $query   = $this->db->query($sql . ' ORDER BY nombre ASC, tercero ASC, fecha ASC, numero ASC, c.tipo ASC, c.registro ASC', $binds);
        $avisos  = [];
        $filas   = [];
        $porTerc = [];
        $porDia  = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' registros; el último trabajador puede estar incompleto. Filtre por trabajador o reduzca el rango.';

                break;
            }

            $nombre  = trim($c['nombre']);
            $valor   = (float) $c['valor'];
            $fecha   = substr((string) $c['fecha'], 0, 10);
            $dow     = (int) date('N', strtotime($fecha));
            $lote    = $c['lote'] === '' ? '' : $c['lote'] . (trim($c['loteNombre']) === '' ? '' : ' — ' . trim($c['loteNombre']));
            $filas[] = [
                'trabajador'  => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'fecha'       => $fecha,
                'dia'         => mb_substr(self::DIAS[$dow], 0, 3),
                'transaccion' => $c['tipo'] . '-' . $c['numero'],
                'finca'       => trim($c['finca']),
                'lote'        => $lote,
                'labor'       => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'uMedida'     => $c['uMedida'],
                'cantidad'    => (float) $c['cantidad'],
                'jornales'    => (float) $c['jornales'],
                'racimos'     => $c['racimos'] === null ? null : (int) $c['racimos'],
                'tiquete'     => trim((string) $c['tiquete']),
                'precio'      => (float) $c['precio'],
                'valor'       => $valor,
                'periodo'     => $c['periodo'],
                'codigo'      => $c['tercero'],
                'negativo'    => $valor < 0,
            ];

            $t                = &$porTerc[$c['tercero']];
            $t['conNegativo'] = ($t['conNegativo'] ?? false) || $valor < 0;
            $t['dias'][$fecha] = true;
            $t['fincas'][trim($c['finca'])] = true;
            $t['periodos'][$c['periodo']] = true;
            $t['sinNombre']   = $nombre === '';
            unset($t);

            $d                 = &$porDia[$fecha];
            $d['valor']        = ($d['valor'] ?? 0) + $valor;
            $d['jornales']     = ($d['jornales'] ?? 0) + (float) $c['jornales'];
            $d['registros']    = ($d['registros'] ?? 0) + 1;
            $d['trabajadores'][$c['tercero']] = true;
            $d['dow']          = $dow;
            unset($d);
        }

        $query->freeResult();

        foreach ($porTerc as &$t) {
            $t['dias']     = count($t['dias']);
            $t['fincas']   = array_values(array_filter(array_map('strval', array_keys($t['fincas'])), static fn ($v) => $v !== ''));
            $t['periodos'] = array_values(array_filter(array_map('strval', array_keys($t['periodos'])), static fn ($v) => $v !== ''));
        }

        unset($t);

        foreach ($filas as &$f) {
            $t                       = $porTerc[$f['codigo']];
            $f['conNegativo']        = $t['conNegativo'];
            $f['diasTrabajador']     = $t['dias'];
            $f['fincasTrabajador']   = $t['fincas'];
            $f['periodosTrabajador'] = $t['periodos'];
        }

        unset($f);

        ksort($porDia);

        $unaUnidad    = count(array_unique(array_column($filas, 'uMedida'))) === 1;
        $totales      = ['jornales' => round(array_sum(array_column($filas, 'jornales')), 2), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $totales      = $unaUnidad ? ['cantidad' => round(array_sum(array_column($filas, 'cantidad')), 2)] + $totales : $totales;
        $trabajadores = count($porTerc);
        $transacciones = count(array_unique(array_column($filas, 'transaccion')));
        $negativos    = array_sum(array_filter(array_column($filas, 'valor'), static fn ($v) => $v < 0));
        $sinNombre    = count(array_filter(array_column($porTerc, 'sinNombre')));
        $mayor        = $porDia === [] ? null : array_search(max(array_column($porDia, 'valor')), array_combine(array_keys($porDia), array_column($porDia, 'valor')), true);

        if ($sinNombre > 0) {
            $avisos[] = self::n($sinNombre) . ($sinNombre === 1 ? ' trabajador no se encontró' : ' trabajadores no se encontraron') . ' en el maestro de terceros.';
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Registros', 'valor' => count($filas), 'formato' => 'entero', 'icono' => 'bi bi-list-ul',
                    'ayuda' => $filas === [] ? '' : self::n($trabajadores) . ($trabajadores === 1 ? ' trabajador' : ' trabajadores') . ' · ' . self::n($transacciones) . ($transacciones === 1 ? ' transacción' : ' transacciones')],
                ['etiqueta' => 'Jornales', 'valor' => $totales['jornales'], 'formato' => 'decimal', 'icono' => 'bi bi-calendar-check',
                    'ayuda' => $totales['jornales'] == 0 ? 'Sin jornales registrados en el periodo' : 'Promedio ' . self::n($totales['jornales'] / $trabajadores, 2) . ' por trabajador'],
                ['etiqueta' => 'Valor total', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Días con labor', 'valor' => count($porDia), 'formato' => 'entero', 'icono' => 'bi bi-calendar3',
                    'ayuda' => $mayor === null ? '' : 'Mayor: $ ' . self::n($porDia[$mayor]['valor']) . ' el ' . date(substr($filtros['fechaInicial'], 0, 4) === substr($filtros['fechaFinal'], 0, 4) ? 'd/m' : 'd/m/Y', strtotime($mayor))],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => false,
                'apilada'    => false,
                'titulo'     => 'Valor devengado por día',
                'categorias' => array_map(static fn ($f) => date('d/m/Y', strtotime($f)), array_keys($porDia)),
                'series'     => [['nombre' => 'Valor devengado', 'datos' => array_map(static fn ($d) => round($d['valor'], 2), array_values($porDia))]],
                'detalle'    => array_map(static fn ($d) => ['dia' => self::DIAS[$d['dow']], 'registros' => $d['registros'], 'trabajadores' => count($d['trabajadores'])]
                    + ($totales['jornales'] > 0 ? ['jornales' => round($d['jornales'], 2)] : []), array_values($porDia)),
                'ejeY'       => 'Valor devengado',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'dia', 'titulo' => 'Día', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'transaccion', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => $unaUnidad] + ($unaUnidad ? [] : ['ayuda' => 'No se totaliza porque mezcla unidades.']),
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'racimos', 'titulo' => 'Racimos línea', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Racimos de toda la línea de cosecha; se repiten en cada trabajador de la línea, por eso no se totalizan.'],
                ['clave' => 'tiquete', 'titulo' => 'Tiquete', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'precio', 'titulo' => 'Precio', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
                ['clave' => 'periodo', 'titulo' => 'Periodo nómina', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'trabajador',
            'avisos'  => $avisos,
        ];
    }
}
