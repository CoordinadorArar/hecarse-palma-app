<?php

namespace App\Libraries\Informes\Transacciones;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LaboresDetalle extends Informe
{
    private const CCOSTO = "COALESCE(NULLIF(LTRIM(RTRIM(c.ccosto)), ''), LTRIM(RTRIM(ISNULL(k.ccosto, ''))))";

    public function opciones(string $filtro, array $dependencias): array
    {
        return (new LaboresLoteFecha($this->empresa))->opciones($filtro, $dependencias);
    }

    public function filtros(): array
    {
        $tipos = array_map(
            static fn ($t) => ['valor' => $t['codigo'], 'texto' => $t['codigo'] . ' — ' . trim($t['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM gTipoTransaccion
                               WHERE empresa = ? AND codigo IN (SELECT DISTINCT tipo FROM aTransaccionTercero WHERE empresa = ?) ORDER BY codigo ASC", [$this->empresa, $this->empresa])->getResultArray()
        );
        $labores = array_map(
            static fn ($l) => ['valor' => $l['codigo'], 'texto' => $l['codigo'] . ' — ' . trim($l['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );
        $trabajadores = array_map(
            static fn ($t) => ['valor' => $t['tercero'], 'texto' => $t['tercero'] . ' — ' . (trim($t['nombre']) === '' ? 'Sin nombre en terceros' : trim($t['nombre']))],
            $this->db->query("SELECT x.tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre
                                FROM (SELECT DISTINCT LTRIM(RTRIM(tercero)) AS tercero FROM aTransaccionTercero WHERE empresa = ?) x
                               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                                             WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = x.tercero OR LTRIM(RTRIM(codigo)) = x.tercero)
                                             ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = x.tercero THEN 0 ELSE 1 END) t
                               ORDER BY CASE WHEN ISNULL(LTRIM(RTRIM(t.nombre)), '') = '' THEN 1 ELSE 0 END, t.nombre, x.tercero", [$this->empresa, $this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha del documento digitado, no de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t'), 'ayuda' => 'Rango máximo de 1 año.'],
            ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select', 'obligatorio' => true, 'defecto' => 'aprobadas', 'ayuda' => 'Por defecto se excluyen los documentos anulados.',
                'opciones' => [['valor' => 'aprobadas', 'texto' => 'Aprobadas'], ['valor' => 'anuladas', 'texto' => 'Anuladas'], ['valor' => 'todas', 'texto' => 'Todas']]],
            ['clave' => 'tipoTransaccion', 'etiqueta' => 'Tipo de transacción', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos', 'opciones' => $tipos],
            ['clave' => 'numero', 'etiqueta' => 'Número', 'tipo' => 'texto', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Ej. 1520', 'ayuda' => 'Coincidencia parcial del consecutivo, sin el prefijo del tipo.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'depende' => ['finca'], 'opciones' => $this->opciones('lote', [])],
            ['clave' => 'labor', 'etiqueta' => 'Labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las labores', 'opciones' => $labores],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los trabajadores',
                'opciones' => $trabajadores],
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

    private static function codigo(string $codigo, string $nombre): string
    {
        return $codigo === '' ? '' : $codigo . (trim($nombre) === '' ? '' : ' — ' . trim($nombre));
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

        if ($v['fechaFinal'] > date('Y-m-d', strtotime($v['fechaInicial'] . ' +1 year'))) {
            return 'El rango no puede superar un año.';
        }

        return $v['finca'] !== '' && $v['lote'] !== '' && explode('|', $v['lote'], 2)[0] !== $v['finca'] ? 'El lote no pertenece a la finca seleccionada.' : $v;
    }

    public function consultar(array $filtros): array
    {
        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = a.empresa AND el.tipo = a.tipo AND el.numero = LTRIM(RTRIM(a.numero)))';

        $where = '';
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];
        $lote  = $filtros['lote'] === '' ? ['', ''] : explode('|', $filtros['lote'], 2);
        $num   = "SUBSTRING(LTRIM(RTRIM(a.numero)), LEN(a.tipo) + 1, 50)";

        foreach (['c.tipo' => $filtros['tipoTransaccion'], 'c.finca' => $filtros['finca'] !== '' ? $filtros['finca'] : $lote[0], 'b.lote' => $lote[1], 'c.novedad' => $filtros['labor'], 'c.tercero' => $filtros['trabajador']] as $campo => $valor) {
            if ($valor !== '') {
                $where  .= " AND LTRIM(RTRIM({$campo})) = ?";
                $binds[] = $valor;
            }
        }

        if ($filtros['numero'] !== '') {
            $like    = '%' . strtr($filtros['numero'], ['!' => '!!', '%' => '!%', '_' => '!_', '[' => '![']) . '%';
            $where  .= " AND (SUBSTRING({$num}, PATINDEX('%[^0]%', {$num} + '.'), 50) LIKE ? ESCAPE '!' OR LTRIM(RTRIM(a.numero)) LIKE ? ESCAPE '!')";
            $binds   = array_merge($binds, [$like, $like]);
        }

        $from = "FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa{$eliminadas}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                 WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}";
        $estado = ['aprobadas' => ' AND a.anulado = 0', 'anuladas' => ' AND a.anulado = 1', 'todas' => ''][$filtros['estado']];
        $cc     = self::CCOSTO;

        $anulados = (int) $this->db->query("SELECT COUNT(*) AS n FROM (SELECT DISTINCT a.tipo, a.numero {$from} AND a.anulado = 1) x", $binds)->getRow()->n;

        $sql = "SELECT TOP " . (self::MAX_FILAS + 1) . " LTRIM(RTRIM(c.tipo)) AS tipo, CAST(CAST({$num} AS bigint) AS varchar(20)) AS numero, CONVERT(date, a.fecha) AS fechaDocumento,
                       a.anulado, CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha, CAST(ISNULL(i.descripcion, '') AS varchar(100)) AS finca,
                       LTRIM(RTRIM(ISNULL(d.grupo, ''))) AS grupo, CAST(ISNULL(g.descripcion, '') AS varchar(100)) AS grupoNombre,
                       LTRIM(RTRIM(ISNULL(d.concepto, ''))) AS concepto, CAST(ISNULL(l.descripcion, '') AS varchar(150)) AS conceptoNombre,
                       LTRIM(RTRIM(ISNULL(b.lote, ''))) AS lote, CAST(ISNULL(k.descripcion, '') AS varchar(100)) AS loteNombre, LTRIM(RTRIM(c.novedad)) AS labor, CAST(ISNULL(d.descripcion, '') AS varchar(150)) AS laborNombre,
                       ISNULL(d.manejaLote, 0) AS manejaLote, LTRIM(RTRIM(c.tercero)) AS tercero, CAST(ISNULL(t.nombre, '') AS varchar(150)) AS nombre, CAST(ISNULL(p.nombre, '') AS varchar(100)) AS contratista,
                       {$cc} AS ccosto, CAST(ISNULL(cc.descripcion, '') AS varchar(150)) AS ccostoNombre,
                       LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL(b.racimos, 0) END AS racimos,
                       CASE WHEN c.tipo = 'TLC' THEN ISNULL((SELECT TOP 1 z.tiquete FROM aTransaccionBascula z WHERE z.tipo = a.tipo AND z.empresa = a.empresa AND z.numero = a.numero), '') ELSE '' END AS tiquete,
                       ISNULL(c.precioLabor, 0) AS precio, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.valorTotal, 0) AS valor, b.registro AS linea,
                       LTRIM(RTRIM(ISNULL(a.usuarioRegistro, ''))) AS usuario, CAST(ISNULL(NULLIF(LTRIM(RTRIM(a.referencia)), ''), ISNULL(LTRIM(RTRIM(a.observacion)), '')) AS varchar(61)) AS referencia
                  FROM aTransaccionTercero c
                  JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                  JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa{$eliminadas}
                  JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                  LEFT JOIN aGrupoNovedad g ON g.codigo = d.grupo AND g.empresa = d.empresa
                  LEFT JOIN nConcepto l ON l.codigo = d.concepto AND l.empresa = d.empresa
                  LEFT JOIN aFinca i ON i.codigo = c.finca AND i.empresa = c.empresa
                  LEFT JOIN aLotes k ON k.codigo = b.lote AND k.empresa = b.empresa
                  LEFT JOIN cCentrosCosto cc ON cc.empresa = c.empresa AND LTRIM(RTRIM(cc.codigo)) = {$cc}
                 OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = c.empresa AND (LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) OR LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(c.tercero)))
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = LTRIM(RTRIM(c.tercero)) THEN 0 ELSE 1 END) t
                 OUTER APPLY (SELECT TOP 1 ISNULL(ISNULL(NULLIF(LTRIM(RTRIM(pn.razonSocial)), ''), pn.descripcion), ISNULL(NULLIF(LTRIM(RTRIM(pv.razonSocial)), ''), pv.descripcion)) AS nombre
                                FROM nFuncionario f
                                LEFT JOIN cTercero pn ON pn.empresa = f.empresa AND pn.nit = f.proveedor
                                LEFT JOIN cTercero pv ON pv.empresa = f.empresa AND pv.id = CASE WHEN f.proveedor NOT LIKE '%[^0-9]%' AND LEN(f.proveedor) BETWEEN 1 AND 8 THEN CONVERT(int, f.proveedor) END
                               WHERE f.empresa = c.empresa AND f.contratista = 1 AND LTRIM(RTRIM(f.codigo)) = LTRIM(RTRIM(c.tercero))) p
                 WHERE c.empresa = ? AND CONVERT(date, a.fecha) BETWEEN ? AND ?{$where}{$estado}
                 ORDER BY fechaDocumento ASC, tipo ASC, CAST({$num} AS bigint) ASC, b.registro ASC, tercero ASC";

        ini_set('sqlsrv.ClientBufferMaxKBSize', '102400');
        ini_set('memory_limit', '256M');

        $query     = $this->db->query($sql, $binds);
        $avisos    = [];
        $filas     = [];
        $docs      = [];
        $sinNombre = [];

        while (($c = $query->getUnbufferedRow('array')) !== null) {
            if (count($filas) === self::MAX_FILAS) {
                $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' líneas; el último documento puede estar incompleto. Filtre por finca, tipo, labor o trabajador, o reduzca el rango.';

                break;
            }

            $nombre    = trim($c['nombre']);
            $valor     = (float) $c['valor'];
            $documento = $c['tipo'] . '-' . $c['numero'];
            $finca     = trim($c['finca']);
            $anulado   = (int) $c['anulado'] === 1;
            $filas[]   = [
                'documento'        => $documento,
                'tipo'             => $c['tipo'],
                'numero'           => $c['numero'],
                'fechaTransaccion' => substr((string) $c['fechaDocumento'], 0, 10),
                'finca'            => $finca,
                'grupo'            => self::codigo($c['grupo'], $c['grupoNombre']),
                'concepto'         => self::codigo($c['concepto'], $c['conceptoNombre']),
                'contratista'      => trim($c['contratista']),
                'racimos'          => $c['racimos'] === null ? null : (int) $c['racimos'],
                'tiquete'          => trim((string) $c['tiquete']),
                'estado'           => $anulado ? 'Anulado' : 'Aprobado',
                'usuario'          => $c['usuario'],
                'fecha'            => substr((string) $c['fecha'], 0, 10),
                'lote'             => self::codigo($c['lote'], $c['loteNombre']),
                'labor'            => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'trabajador'       => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'ccosto'           => self::codigo($c['ccosto'], $c['ccostoNombre']),
                'uMedida'          => $c['uMedida'],
                'cantidad'         => (float) $c['cantidad'],
                'jornales'         => (float) $c['jornales'],
                'precio'           => (float) $c['precio'],
                'valor'            => $valor,
                'codigo'           => $c['tercero'],
                'negativo'         => $valor < 0,
                'anulado'          => $anulado,
                'faltaLote'        => $c['lote'] === '' && (int) $c['manejaLote'] === 1,
            ];

            $d                = &$docs[$documento];
            $d['tipo']        = $c['tipo'];
            $d['anulado']     = $anulado;
            $d['fecha']       = substr((string) $c['fechaDocumento'], 0, 10);
            $d['conNegativo'] = ($d['conNegativo'] ?? false) || $valor < 0;
            $d['fincas'][$finca] = true;
            $d['lineas'][$c['linea']] = true;
            $d['trabajadores'][$c['tercero']] = true;
            $d['unidades'][$c['uMedida']] = true;
            $d['referencia']  = trim($c['referencia']);
            $d['tiquete']     = trim((string) $c['tiquete']);
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
            $f['referencia']            = mb_strlen($d['referencia']) > 60 ? rtrim(mb_substr($d['referencia'], 0, 60)) . '…' : $d['referencia'];
            $f['tiqueteDocumento']      = $d['tiquete'];
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
        $docsAnulados = count(array_filter(array_column($docs, 'anulado')));
        $valorAnulado = array_sum(array_column(array_filter($filas, static fn ($f) => $f['anulado']), 'valor'));
        $porTipo      = array_count_values(array_column($docs, 'tipo'));

        arsort($porTipo);

        if ($sinNombre !== []) {
            $avisos[] = self::n(count($sinNombre)) . (count($sinNombre) === 1 ? ' trabajador no se encontró' : ' trabajadores no se encontraron') . ' en el maestro de terceros.';
        }

        if ($filtros['estado'] === 'todas' && $docsAnulados > 0) {
            $avisos[] = 'Los totales incluyen ' . self::plural($docsAnulados, 'documento anulado.', 'documentos anulados.');
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Valor total', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => ($negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos')
                        . ($filtros['estado'] === 'todas' && $docsAnulados > 0 ? ' · $ ' . self::n($valorAnulado) . ' de anulados' : '')],
                ['etiqueta' => 'Documentos', 'valor' => $documentos, 'formato' => 'entero', 'icono' => 'bi bi-file-earmark-text',
                    'ayuda' => implode(' · ', array_map(static fn ($t, $n) => $t . ' ' . self::n($n), array_keys($porTipo), $porTipo))],
                ['etiqueta' => 'Líneas', 'valor' => count($filas), 'formato' => 'entero', 'icono' => 'bi bi-list-ul',
                    'ayuda' => $filas === [] ? '' : self::plural($trabajadores, 'trabajador', 'trabajadores') . ' · ' . self::n(count($filas) / $documentos, 1) . ' por documento'],
                ['etiqueta' => 'Anulados', 'valor' => $anulados, 'formato' => 'entero', 'icono' => 'bi bi-x-octagon',
                    'ayuda' => $anulados === 0 ? 'Sin documentos anulados en el rango' : ['aprobadas' => 'No incluidos en el listado', 'todas' => 'Incluidos en el listado y totales', 'anuladas' => 'Solo documentos anulados'][$filtros['estado']]],
            ],
            'grafica'  => null,
            'columnas' => [
                ['clave' => 'documento', 'titulo' => 'Transacción', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'numero', 'titulo' => 'Número', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fechaTransaccion', 'titulo' => 'Fecha transacción', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'usuario', 'titulo' => 'Usuario registro', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha labor', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'grupo', 'titulo' => 'Grupo labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'concepto', 'titulo' => 'Concepto nómina', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'contratista', 'titulo' => 'Contratista', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'ccosto', 'titulo' => 'C. costo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'tiquete', 'titulo' => 'Tiquete', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => $totalCant]
                    + ($unaUnidad ? [] : ['ayuda' => $porDocumento ? 'Se subtotaliza por documento; el total general no se calcula porque mezcla unidades.' : 'No se totaliza porque mezcla unidades.']),
                ['clave' => 'racimos', 'titulo' => 'Racimos línea', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Valor de la línea repetido por trabajador; no se totaliza.'],
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
