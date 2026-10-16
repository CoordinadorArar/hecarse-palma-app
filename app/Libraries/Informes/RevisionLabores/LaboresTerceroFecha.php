<?php

namespace App\Libraries\Informes\RevisionLabores;

use App\Libraries\Informes\Administracion\LotesPorFincas;
use App\Libraries\Informes\Informe;

class LaboresTerceroFecha extends Informe
{
    public function filtros(): array
    {
        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        return [
            ['clave' => 'fechaInicial', 'etiqueta' => 'Fecha inicial', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-01'), 'ayuda' => 'Fecha de ejecución de la labor.'],
            ['clave' => 'fechaFinal', 'etiqueta' => 'Fecha final', 'tipo' => 'fecha', 'obligatorio' => true, 'defecto' => date('Y-m-t')],
            ['clave' => 'trabajador', 'etiqueta' => 'Trabajador', 'tipo' => 'texto', 'obligatorio' => false, 'defecto' => '', 'ayuda' => 'Código o parte del nombre.'],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'opciones' => $grupos],
            ['clave' => 'tipo', 'etiqueta' => 'Tipo de trabajador', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos', 'ayuda' => 'Nómina o contratista según el registro de la labor.',
                'opciones' => [['valor' => 'N', 'texto' => 'Nómina'], ['valor' => 'C', 'texto' => 'Contratista']]],
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
        $binds = [$this->empresa, $filtros['fechaInicial'], $filtros['fechaFinal']];

        if ($filtros['finca'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(c.finca)) = ?';
            $binds[] = $filtros['finca'];
        }

        if ($filtros['grupo'] !== '') {
            $where  .= ' AND LTRIM(RTRIM(d.grupo)) = ?';
            $binds[] = $filtros['grupo'];
        }

        if ($filtros['tipo'] !== '') {
            $where  .= ' AND ISNULL(c.contratista, 0) = ?';
            $binds[] = $filtros['tipo'] === 'C' ? 1 : 0;
        }

        $sql = "WITH base AS (
                    SELECT LTRIM(RTRIM(c.tercero)) AS tercero, LTRIM(RTRIM(c.novedad)) AS labor, ISNULL(d.descripcion, '') AS laborNombre, LTRIM(RTRIM(ISNULL(d.uMedida, ''))) AS uMedida,
                           CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) AS fecha, CAST(ISNULL(c.contratista, 0) AS int) AS contratista, CASE WHEN b.signo = 2 THEN -1 ELSE 1 END AS signo,
                           ISNULL(c.cantidad, 0) AS cantidad, ISNULL(c.jornales, 0) AS jornales, ISNULL(c.valorTotal, 0) AS valor
                      FROM aTransaccionTercero c
                      JOIN aTransaccionNovedad b ON b.numero = c.numero AND b.tipo = c.tipo AND b.empresa = c.empresa AND b.registro = c.registroNovedad
                      JOIN aTransaccion a ON a.numero = b.numero AND a.tipo = b.tipo AND a.empresa = b.empresa AND a.anulado = 0{$eliminadas}
                      JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa
                     WHERE c.empresa = ? AND CONVERT(date, ISNULL(c.fechaNovedad, b.fecha)) BETWEEN ? AND ?{$where}
                ), tr AS (
                    SELECT tercero, COUNT(DISTINCT fecha) AS diasTrabajador, COUNT(DISTINCT labor) AS laboresTrabajador FROM base GROUP BY tercero
                )
                SELECT TOP " . (self::MAX_FILAS + 1) . " g.*, tr.diasTrabajador, tr.laboresTrabajador, ISNULL(t.nombre, '') AS nombre, ISNULL(cg.cargo, '') AS cargo
                  FROM (SELECT tercero, labor, laborNombre, uMedida, fecha, SUM(signo * cantidad) AS cantidad, SUM(jornales) AS jornales,
                               SUM(signo * valor) AS valor, MAX(contratista) AS contratista
                          FROM base GROUP BY tercero, labor, laborNombre, uMedida, fecha) g
                  JOIN tr ON tr.tercero = g.tercero
                 OUTER APPLY (SELECT TOP 1 id, codigo, ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre FROM cTercero
                               WHERE empresa = ? AND (LTRIM(RTRIM(nit)) = g.tercero OR LTRIM(RTRIM(codigo)) = g.tercero)
                               ORDER BY CASE WHEN LTRIM(RTRIM(nit)) = g.tercero THEN 0 ELSE 1 END) t
                 OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(n.descripcion)) AS cargo FROM nContratos m JOIN nCargo n ON n.codigo = m.cargo AND n.empresa = m.empresa
                               WHERE m.empresa = ? AND (m.tercero = t.id OR LTRIM(RTRIM(m.codigoTercero)) = g.tercero)
                               ORDER BY m.activo DESC, m.fechaIngreso DESC) cg";

        $binds[] = $this->empresa;
        $binds[] = $this->empresa;

        if ($filtros['trabajador'] !== '') {
            $sql    .= ' WHERE g.tercero LIKE ? OR t.codigo LIKE ? OR t.nombre LIKE ?';
            $like    = '%' . $filtros['trabajador'] . '%';
            $binds   = array_merge($binds, [$like, $like, $like]);
        }

        ini_set('sqlsrv.ClientBufferMaxKBSize', '51200');

        $crudas = $this->db->query($sql . ' ORDER BY nombre ASC, g.tercero ASC, g.labor ASC, g.fecha ASC', $binds)->getResultArray();
        $avisos = [];

        if (count($crudas) > self::MAX_FILAS) {
            $crudas   = array_slice($crudas, 0, self::MAX_FILAS);
            $avisos[] = 'El resultado se limitó a ' . self::n(self::MAX_FILAS) . ' filas.';
        }

        $filas   = [];
        $porTerc = [];

        foreach ($crudas as $c) {
            $nombre  = trim($c['nombre']);
            $valor   = (float) $c['valor'];
            $filas[] = [
                'trabajador'        => $c['tercero'] . ' — ' . ($nombre === '' ? 'Sin nombre en terceros' : $nombre),
                'codigo'            => $c['tercero'],
                'cargo'             => $c['cargo'],
                'labor'             => $c['labor'] . ' — ' . trim($c['laborNombre']),
                'fecha'             => substr((string) $c['fecha'], 0, 10),
                'uMedida'           => $c['uMedida'],
                'cantidad'          => (float) $c['cantidad'],
                'jornales'          => (float) $c['jornales'],
                'valor'             => $valor,
                'diasTrabajador'    => (int) $c['diasTrabajador'],
                'laboresTrabajador' => (int) $c['laboresTrabajador'],
                'contratista'       => (int) $c['contratista'] === 1,
                'negativo'          => $valor < 0,
                'conNegativo'       => false,
            ];

            $t                   = &$porTerc[$c['tercero']];
            $t['nombre']         = $nombre;
            $t['categoria']      = end($filas)['trabajador'];
            $t['valor']          = ($t['valor'] ?? 0) + $valor;
            $t['jornales']       = ($t['jornales'] ?? 0) + (float) $c['jornales'];
            $t['dias']           = (int) $c['diasTrabajador'];
            $t['labores']        = (int) $c['laboresTrabajador'];
            $t['contratista']    = ($t['contratista'] ?? false) || (int) $c['contratista'] === 1;
            $t['conNegativo']    = ($t['conNegativo'] ?? false) || $valor < 0;
            $t['sinNombre']      = $nombre === '';
            unset($t);
        }

        foreach ($filas as &$f) {
            $f['conNegativo'] = $porTerc[$f['codigo']]['conNegativo'];
        }

        unset($f);

        $totales      = ['jornales' => round(array_sum(array_column($filas, 'jornales')), 2), 'valor' => round(array_sum(array_column($filas, 'valor')), 2)];
        $trabajadores = count($porTerc);
        $contratistas = count(array_filter(array_column($porTerc, 'contratista')));
        $negativos    = array_sum(array_filter(array_column($filas, 'valor'), static fn ($v) => $v < 0));
        $sinNombre    = count(array_filter(array_column($porTerc, 'sinNombre')));
        $ranking      = array_values($porTerc);

        usort($ranking, static fn ($a, $b) => $b['valor'] <=> $a['valor']);

        $top = array_slice($ranking, 0, 15);

        if ($sinNombre > 0) {
            $avisos[] = self::n($sinNombre) . ($sinNombre === 1 ? ' trabajador no se encontró' : ' trabajadores no se encontraron') . ' en el maestro de terceros.';
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Trabajadores con labores', 'valor' => $trabajadores, 'formato' => 'entero', 'icono' => 'bi bi-people',
                    'ayuda' => $trabajadores === 0 ? '' : ($contratistas === 0 ? 'Todos de nómina' : ($contratistas === $trabajadores ? 'Todos contratistas' : self::n($trabajadores - $contratistas) . ' nómina · ' . self::n($contratistas) . ' contratistas'))],
                ['etiqueta' => 'Jornales totales', 'valor' => $totales['jornales'], 'formato' => 'decimal', 'icono' => 'bi bi-calendar-check',
                    'ayuda' => $totales['jornales'] == 0 ? 'Sin jornales registrados en el periodo' : 'Promedio ' . self::n($totales['jornales'] / $trabajadores, 2) . ' por trabajador'],
                ['etiqueta' => 'Valor total devengado', 'valor' => $totales['valor'], 'formato' => 'moneda', 'icono' => 'bi bi-cash-coin',
                    'ayuda' => $negativos < 0 ? 'Incluye ajustes negativos por $ ' . self::n(abs($negativos)) : 'Sin ajustes negativos'],
                ['etiqueta' => 'Valor promedio por trabajador', 'valor' => $trabajadores > 0 ? round($totales['valor'] / $trabajadores, 2) : null, 'formato' => 'moneda', 'icono' => 'bi bi-person-badge',
                    'ayuda' => $top === [] ? '' : 'Máximo: $ ' . self::n($top[0]['valor']) . ' · ' . ($top[0]['nombre'] === '' ? $top[0]['categoria'] : $top[0]['nombre'])],
            ],
            'grafica' => $filas === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => 'Top 15 trabajadores por valor devengado',
                'categorias' => array_column($top, 'categoria'),
                'series'     => [['nombre' => 'Valor devengado', 'datos' => array_map(static fn ($t) => round($t['valor'], 2), $top)]],
                'detalle'    => array_map(static fn ($t) => ['jornales' => $t['jornales'], 'dias' => $t['dias'], 'labores' => $t['labores']], $top),
                'ejeX'       => 'Valor devengado',
                'formato'    => 'moneda',
            ],
            'columnas' => [
                ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'codigo', 'titulo' => 'Código', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cargo', 'titulo' => 'Cargo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'fecha', 'titulo' => 'Fecha ejecución', 'tipo' => 'fecha', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Suma por labor y fecha; no se totaliza porque mezcla unidades.'],
                ['clave' => 'jornales', 'titulo' => 'Jornales', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'valor', 'titulo' => 'Valor devengado', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => true, 'ayuda' => 'Los ajustes con signo negativo restan.'],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'trabajador',
            'avisos'  => $avisos,
        ];
    }
}
