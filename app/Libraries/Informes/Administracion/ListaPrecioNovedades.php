<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class ListaPrecioNovedades extends Informe
{
    private const SIN = "(LTRIM(RTRIM(ISNULL(a.grupo, ''))) = '' OR g.codigo IS NULL)";

    private const ANIO = 'TRY_CAST(LTRIM(RTRIM([año])) AS int)';

    private static function pct(float $valor, float $total): string
    {
        return $total == 0 ? '0%' : number_format($valor * 100 / $total, 1, ',', '.') . '%';
    }

    private static function n(int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }

    private static function nulo(?float $valor): ?float
    {
        return $valor === null || $valor == 0 ? null : $valor;
    }

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query('SELECT DISTINCT ' . self::ANIO . ' AS anio FROM aNovedadLotePrecio WHERE empresa = ? AND ' . self::ANIO . ' IS NOT NULL ORDER BY anio DESC', [$this->empresa])->getResultArray()
        );

        $grupos = array_map(
            static fn ($g) => ['valor' => $g['codigo'], 'texto' => $g['codigo'] . ' — ' . trim($g['descripcion'])],
            $this->db->query("SELECT LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC", [$this->empresa])->getResultArray()
        );

        $grupos[] = ['valor' => '__SIN__', 'texto' => 'Sin grupo'];

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => $anios[0]['valor'] ?? '', 'ayuda' => 'Años con precios registrados.', 'opciones' => $anios],
            ['clave' => 'grupo', 'etiqueta' => 'Grupo de labor', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los grupos', 'ayuda' => 'Incluye «Sin grupo».', 'opciones' => $grupos],
            ['clave' => 'precio', 'etiqueta' => 'Precio', 'tipo' => 'select', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas', 'ayuda' => 'Sin precio: sin registro o con todos los precios en 0.',
                'opciones' => [['valor' => 'C', 'texto' => 'Con precio'], ['valor' => 'S', 'texto' => 'Sin precio']]],
        ];
    }

    public function consultar(array $filtros): array
    {
        $anio     = (int) $filtros['anio'];
        $previo   = $anio - 1;
        $precios  = 'SELECT RTRIM(novedad) AS novedad, ' . self::ANIO . ' AS anio, precioDestajo, precioContratistas, precioOtros, porcentaje, ISNULL(baseSueldo, 0) AS baseSueldo,
                            ROW_NUMBER() OVER (PARTITION BY ' . self::ANIO . ', RTRIM(novedad) ORDER BY registro DESC) AS r
                       FROM aNovedadLotePrecio WHERE empresa = ? AND ' . self::ANIO . ' IN (?, ?)';

        $sql = "WITH p AS ({$precios})
                SELECT CASE WHEN " . self::SIN . " THEN 1 ELSE 0 END AS sinGrupo,
                       ISNULL(LTRIM(RTRIM(g.codigo)), '') AS grupoCodigo, ISNULL(g.descripcion, '') AS grupoNombre,
                       LTRIM(RTRIM(a.codigo)) AS codigo, ISNULL(a.descripcion, '') AS descripcion, ISNULL(a.uMedida, '') AS uMedida,
                       act.novedad AS existe, act.precioDestajo, act.precioContratistas, act.precioOtros, act.porcentaje, ISNULL(act.baseSueldo, 0) AS baseSueldo,
                       ant.novedad AS existeAnterior, ant.precioDestajo AS destajoAnterior, ISNULL(ant.baseSueldo, 0) AS baseAnterior
                  FROM aNovedad a
                 OUTER APPLY (SELECT TOP 1 codigo, descripcion FROM aGrupoNovedad WHERE empresa = a.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(a.grupo))) g
                  LEFT JOIN p act ON act.novedad = RTRIM(a.codigo) AND act.anio = ? AND act.r = 1
                  LEFT JOIN p ant ON ant.novedad = RTRIM(a.codigo) AND ant.anio = ? AND ant.r = 1
                 WHERE a.empresa = ? AND a.activo = 1";

        $binds = [$this->empresa, $anio, $previo, $anio, $previo, $this->empresa];

        if ($filtros['grupo'] === '__SIN__') {
            $sql .= ' AND ' . self::SIN;
        } elseif ($filtros['grupo'] !== '') {
            $sql    .= ' AND NOT ' . self::SIN . ' AND LTRIM(RTRIM(g.codigo)) = ?';
            $binds[] = $filtros['grupo'];
        }

        $crudas = $this->db->query($sql . ' ORDER BY sinGrupo, grupoCodigo, codigo', $binds)->getResultArray();

        $conteo = $this->db->query("WITH p AS ({$precios})
            SELECT ISNULL(SUM(CASE WHEN p.anio = ? AND a.codigo IS NULL THEN 1 ELSE 0 END), 0) AS huerfanos,
                   ISNULL(SUM(CASE WHEN p.anio = ? THEN 1 ELSE 0 END), 0) AS anteriores
              FROM p OUTER APPLY (SELECT TOP 1 codigo FROM aNovedad WHERE empresa = ? AND activo = 1 AND RTRIM(codigo) = p.novedad) a
             WHERE p.r = 1", [$this->empresa, $anio, $previo, $anio, $previo, $this->empresa])->getRow();

        $huerfanos = (int) $conteo->huerfanos;

        $salarios = array_column($this->db->query('SELECT ano, MAX(vSalarioMinimo) AS salario FROM nParametrosAno WHERE empresa = ? AND ano IN (?, ?) GROUP BY ano', [$this->empresa, $anio, $previo])->getResultArray(), 'salario', 'ano');
        $diario   = array_map(static fn ($s) => round((float) $s / 30, 2), $salarios);

        $filas  = [];
        $conPre = 0;
        $base   = 0;

        foreach ($crudas as $c) {
            $sin       = (int) $c['sinGrupo'] === 1;
            $existe    = $c['existe'] !== null;
            $esBase    = (int) $c['baseSueldo'] === 1;
            $destajo   = $existe ? (float) $c['precioDestajo'] : null;
            $actual    = $esBase ? ($diario[$anio] ?? null) : $destajo;
            $anterior  = $c['existeAnterior'] === null ? null : ((int) $c['baseAnterior'] === 1 ? ($diario[$previo] ?? null) : (float) $c['destajoAnterior']);
            $sinPrecio = ! $existe || (! $esBase && (float) $c['precioDestajo'] == 0 && (float) $c['precioContratistas'] == 0 && (float) $c['precioOtros'] == 0 && (float) $c['porcentaje'] == 0);

            if (($filtros['precio'] === 'C' && $sinPrecio) || ($filtros['precio'] === 'S' && ! $sinPrecio)) {
                continue;
            }

            $conPre += (int) ! $sinPrecio;
            $base   += (int) $esBase;

            $filas[] = [
                'grupo'             => $sin ? 'S/G — Sin grupo' : $c['grupoCodigo'] . ' — ' . trim($c['grupoNombre']),
                'sinGrupo'          => $sin,
                'sinPrecio'         => $sinPrecio,
                'codigo'            => $c['codigo'],
                'labor'             => trim($c['descripcion']),
                'uMedida'           => trim($c['uMedida']),
                'destajo'           => self::nulo($actual),
                'contratistas'      => $existe ? self::nulo((float) $c['precioContratistas']) : null,
                'otros'             => $existe ? self::nulo((float) $c['precioOtros']) : null,
                'porcentaje'        => $existe ? self::nulo(round((float) $c['porcentaje'] / 100, 6)) : null,
                'baseSueldo'        => $esBase ? 'Sí' : '',
                'destajoAnterior'   => self::nulo($anterior),
                'variacion'         => $actual > 0 && $anterior > 0 ? round(($actual - $anterior) / $anterior, 4) : null,
                'destajoRegistrado' => self::nulo($destajo),
            ];
        }

        $avisos = [];

        if ($huerfanos > 0) {
            $avisos[] = $huerfanos === 1
                ? "1 precio registrado en {$anio} corresponde a una labor inexistente o inactiva y no se lista."
                : self::n($huerfanos) . " precios registrados en {$anio} corresponden a labores inexistentes o inactivas y no se listan.";
        }

        if ($base > 0 && ! isset($diario[$anio])) {
            $avisos[] = "No hay parámetros de nómina para {$anio}: el destajo de las labores a base sueldo no se puede calcular.";
        }

        if ((int) $conteo->anteriores === 0) {
            $avisos[] = "No hay precios de {$previo} para comparar; la variación no se calcula.";
        }

        $total       = count($crudas);
        $sinPrecios  = count($filas) - $conPre;
        $variaciones = array_values(array_filter(array_column($filas, 'variacion'), static fn ($v) => $v !== null));
        $comparables = count($variaciones);

        sort($variaciones);

        $mediana = $comparables === 0 ? null : round($comparables % 2 === 1 ? $variaciones[intdiv($comparables, 2)] : ($variaciones[$comparables / 2 - 1] + $variaciones[$comparables / 2]) / 2, 4);

        $top      = array_values(array_filter($filas, static fn ($f) => $f['variacion'] !== null && $f['variacion'] != 0 && abs($f['variacion']) <= 1));
        $extremas = array_values(array_filter($filas, static fn ($f) => $f['variacion'] !== null && abs($f['variacion']) > 1));

        usort($extremas, static fn ($a, $b) => $b['variacion'] <=> $a['variacion']);

        if ($extremas !== []) {
            $lista    = implode(', ', array_map(static fn ($f) => $f['codigo'] . ' (' . ($f['variacion'] > 0 ? '+' : '') . number_format($f['variacion'] * 100, 1, ',', '.') . '%)', array_slice($extremas, 0, 5))) . (count($extremas) > 5 ? ' y ' . self::n(count($extremas) - 5) . ' más' : '');
            $avisos[] = (count($extremas) === 1 ? '1 variación superior a 100% no se grafica' : self::n(count($extremas)) . ' variaciones superiores a 100% no se grafican') . ' para no distorsionar la escala: ' . $lista . '.';
        }

        usort($top, static fn ($a, $b) => abs($b['variacion']) <=> abs($a['variacion']));

        $top = array_slice($top, 0, 15);

        usort($top, static fn ($a, $b) => $b['variacion'] <=> $a['variacion']);

        return [
            'kpis' => [
                ['etiqueta' => 'Labores con precio', 'valor' => $conPre, 'formato' => 'entero', 'icono' => 'bi bi-tag',
                    'ayuda' => self::pct($conPre, $total) . ' de ' . self::n($total) . ' labores activas'],
                ['etiqueta' => 'Labores sin precio', 'valor' => $sinPrecios, 'formato' => 'entero', 'icono' => 'bi bi-exclamation-circle',
                    'ayuda' => $sinPrecios === 0 ? 'Todas con precio' : self::pct($sinPrecios, $total) . ' de las labores'],
                ['etiqueta' => "Variación mediana vs {$previo}", 'valor' => $mediana, 'formato' => 'variacion', 'icono' => 'bi bi-graph-up-arrow',
                    'ayuda' => $comparables === 0 ? ((int) $conteo->anteriores === 0 ? "Sin precios de {$previo}" : 'Sin labores comparables') : ($comparables === 1 ? 'Sobre 1 labor comparable' : 'Sobre ' . self::n($comparables) . ' labores comparables')],
                ['etiqueta' => 'Labores a base sueldo', 'valor' => $base, 'formato' => 'entero', 'icono' => 'bi bi-person-badge',
                    'ayuda' => isset($salarios[$anio]) ? 'SMLV diario: $ ' . number_format((float) $salarios[$anio] / 30, 0, ',', '.') : "Sin parámetros de {$anio}"],
            ],
            'grafica' => $top === [] ? null : [
                'tipo'       => 'bar',
                'horizontal' => true,
                'apilada'    => false,
                'titulo'     => "Top 15 variaciones del destajo · {$anio} vs {$previo}",
                'categorias' => array_map(static fn ($f) => $f['codigo'] . ' — ' . $f['labor'], $top),
                'series'     => [['nombre' => 'Variación', 'datos' => array_column($top, 'variacion')]],
                'detalle'    => array_map(static fn ($f) => ['actual' => (float) $f['destajo'], 'anterior' => (float) $f['destajoAnterior'], 'grupo' => $f['sinGrupo'] ? 'Sin grupo' : explode(' — ', $f['grupo'], 2)[1]], $top),
                'ejeX'       => "Variación vs {$previo}",
                'formato'    => 'variacion',
            ],
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Grupo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'codigo', 'titulo' => 'Código', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'labor', 'titulo' => 'Labor', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'uMedida', 'titulo' => 'U. medida', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'destajo', 'titulo' => 'Destajo', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => 'Precio efectivo. Con base sueldo = salario mínimo / 30 del año.'],
                ['clave' => 'contratistas', 'titulo' => 'Contratistas', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'otros', 'titulo' => 'Otros', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'porcentaje', 'titulo' => 'Porcentaje', 'tipo' => 'porcentaje', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'baseSueldo', 'titulo' => 'Base sueldo', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'destajoAnterior', 'titulo' => "Destajo {$previo}", 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'variacion', 'titulo' => "Var. vs {$previo}", 'tipo' => 'variacion', 'alineacion' => 'derecha', 'total' => false, 'ayuda' => "(Destajo {$anio} − Destajo {$previo}) / Destajo {$previo}."],
                ['clave' => 'destajoRegistrado', 'titulo' => 'Destajo registrado', 'tipo' => 'moneda', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => [],
            'agrupar' => 'grupo',
            'avisos'  => $avisos,
        ];
    }
}
