<?php

namespace App\Libraries\Informes\Administracion;

use App\Libraries\Informes\Informe;

class PesoPromedioRacimosLote extends Informe
{
    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function opciones(string $filtro, array $dependencias): array
    {
        return $filtro === 'lote' ? $this->lotes((string) ($dependencias['finca'] ?? '')) : [];
    }

    private function lotes(string $finca): array
    {
        $sql   = "SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(codigo)) AS codigo, ISNULL(descripcion, '') AS nombre FROM aLotes WHERE empresa = ?";
        $binds = [$this->empresa];

        if ($finca !== '') {
            $sql    .= ' AND LTRIM(RTRIM(finca)) = ?';
            $binds[] = $finca;
        }

        return array_map(
            static fn ($l) => [
                'valor' => $l['finca'] . '|' . $l['codigo'],
                'texto' => ($finca === '' ? $l['finca'] . ' · ' : '') . $l['codigo'] . (trim($l['nombre']) === '' ? '' : ' — ' . trim($l['nombre'])),
            ],
            $this->db->query($sql . ' ORDER BY finca ASC, codigo ASC', $binds)->getResultArray()
        );
    }

    public function filtros(): array
    {
        $anios = array_map(
            static fn ($a) => ['valor' => (string) $a['anio'], 'texto' => (string) $a['anio']],
            $this->db->query('SELECT DISTINCT [año] AS anio FROM aLotePesosPeriodo WHERE empresa = ? AND [año] > 0 ORDER BY anio DESC', [$this->empresa])->getResultArray()
        );
        $valores = array_column($anios, 'valor');
        $meses   = array_map(static fn ($m, $n) => ['valor' => (string) $m, 'texto' => $n], array_keys(self::MESES), self::MESES);

        return [
            ['clave' => 'anio', 'etiqueta' => 'Año', 'tipo' => 'anio', 'obligatorio' => true, 'defecto' => in_array(date('Y'), $valores, true) ? date('Y') : ($valores[0] ?? ''), 'opciones' => $anios],
            ['clave' => 'mesDesde', 'etiqueta' => 'Mes desde', 'tipo' => 'mes', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Sin límite', 'ayuda' => 'Mes cosechado; su peso se liquida en el mes siguiente.', 'opciones' => $meses],
            ['clave' => 'mesHasta', 'etiqueta' => 'Mes hasta', 'tipo' => 'mes', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Sin límite', 'opciones' => $meses],
            ['clave' => 'finca', 'etiqueta' => 'Finca', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todas las fincas', 'opciones' => (new LotesPorFincas($this->empresa))->opciones('finca', [])],
            ['clave' => 'lote', 'etiqueta' => 'Lote', 'tipo' => 'select2', 'obligatorio' => false, 'defecto' => '', 'placeholder' => 'Todos los lotes', 'ayuda' => 'Depende de la finca elegida.', 'depende' => ['finca'], 'opciones' => $this->lotes('')],
        ];
    }

    private static function n(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private static function ponderado(array $filas): array
    {
        $racimos = array_sum(array_column($filas, 'racimos'));
        $control = array_sum(array_column($filas, 'racimosControl'));

        return [
            'racimos'    => $racimos,
            'kg'         => array_sum(array_map(static fn ($f) => $f['kg'] ?? 0.0, $filas)),
            'registrado' => $control > 0 ? array_sum(array_column($filas, 'pesoXRacimos')) / $control : null,
            'calculado'  => $control > 0 ? array_sum(array_column($filas, 'kgConRacimos')) / $control : null,
            'diferencia' => $control > 0 ? array_sum(array_column($filas, 'difXRacimos')) / $control : null,
        ];
    }

    public function consultar(array $filtros): array
    {
        $avisos = [];
        $desde  = $filtros['mesDesde'] === '' ? 1 : (int) $filtros['mesDesde'];
        $hasta  = $filtros['mesHasta'] === '' ? 12 : (int) $filtros['mesHasta'];

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
            $avisos[]        = 'Se invirtió el rango de meses.';
        }

        $where = 'p.empresa = ? AND p.[año] = ? AND p.mes BETWEEN ? AND ?';
        $binds = [$this->empresa, (int) $filtros['anio'], $desde, $hasta];
        $lote  = $filtros['lote'] === '' ? null : explode('|', $filtros['lote'], 2);

        if ($filtros['finca'] !== '') {
            $where  .= ' AND p.finca = ?';
            $binds[] = $filtros['finca'];
        }

        if ($lote !== null) {
            $where  .= ' AND p.finca = ? AND p.lote = ?';
            $binds[] = $lote[0];
            $binds[] = $lote[1];
        }

        $eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id === null ? ''
            : ' AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = c.empresa AND el.tipo = c.tipo AND el.numero = LTRIM(RTRIM(c.numero)))';

        $sql = "WITH p AS (SELECT p.* FROM aLotePesosPeriodo p WHERE {$where}),
                     per AS (SELECT DISTINCT p.[año] AS anio, p.mes, cp.fechaInicial AS fi, cp.fechaFinal AS ff
                               FROM p JOIN cPeriodo cp ON cp.empresa = p.empresa AND cp.[año] = p.[año] AND cp.mes = p.mes),
                     kg AS (SELECT per.anio, per.mes, a.lote, SUM(ISNULL(a.cantidad, 0)) AS kg
                              FROM per
                              JOIN aTransaccionTercero a ON a.empresa = ? AND a.fechaNovedad BETWEEN per.fi AND per.ff
                              JOIN aNovedad b ON b.codigo = a.novedad AND b.empresa = a.empresa AND b.claseLabor = 2
                              JOIN aTransaccion c ON c.tipo = a.tipo AND c.numero = a.numero AND c.empresa = a.empresa AND c.anulado = 0{$eliminadas}
                             WHERE a.lote IN (SELECT lote FROM p)
                             GROUP BY per.anio, per.mes, a.lote),
                     rac AS (SELECT per.anio, per.mes, a.lote, SUM(ISNULL(a.racimos, 0)) AS racimos
                               FROM per
                               JOIN aTransaccionNovedad a ON a.empresa = ? AND a.tipo = 'TLC' AND a.fecha BETWEEN per.fi AND per.ff
                               JOIN aTransaccion c ON c.tipo = a.tipo AND c.numero = a.numero AND c.empresa = a.empresa AND c.anulado = 0{$eliminadas}
                              WHERE a.lote IN (SELECT lote FROM p)
                              GROUP BY per.anio, per.mes, a.lote)
                SELECT p.[año] AS anio, p.mes, LTRIM(RTRIM(p.finca)) AS fincaCodigo, ISNULL(f.descripcion, '') AS fincaNombre,
                       LTRIM(RTRIM(ISNULL(p.seccion, ''))) AS seccion, LTRIM(RTRIM(p.lote)) AS loteCodigo, l.descripcion AS loteNombre,
                       p.pesoRacimo, ISNULL(p.automatico, 0) AS automatico, p.fechaInicial, p.fechaFinal, kg.kg, rac.racimos
                  FROM p
                 OUTER APPLY (SELECT TOP 1 descripcion FROM aFinca WHERE empresa = p.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(p.finca))) f
                 OUTER APPLY (SELECT TOP 1 ISNULL(descripcion, '') AS descripcion FROM aLotes WHERE empresa = p.empresa AND codigo = p.lote) l
                  LEFT JOIN kg ON kg.anio = p.[año] AND kg.mes = p.mes AND kg.lote = p.lote
                  LEFT JOIN rac ON rac.anio = p.[año] AND rac.mes = p.mes AND rac.lote = p.lote
                 ORDER BY p.[año] DESC, p.mes DESC, p.finca, p.lote";

        $crudas = $this->db->query($sql, [...$binds, $this->empresa, $this->empresa])->getResultArray();
        $filas  = [];
        $fecha  = static fn ($f) => date('d/m/Y', strtotime((string) $f));

        foreach ($crudas as $c) {
            $peso    = round((float) $c['pesoRacimo'], 3);
            $kg      = $c['kg'] === null ? null : (float) $c['kg'];
            $racimos = $c['racimos'] === null ? null : (int) $c['racimos'];
            $control = $racimos > 0 && $kg !== null;
            $calc    = $control ? round($kg / $racimos, 3) : null;
            $pxr     = $control ? round($peso * $racimos, 3) : 0.0;

            $filas[] = [
                'grupo'         => $c['anio'] . '-' . str_pad((string) $c['mes'], 2, '0', STR_PAD_LEFT) . ' — ' . self::MESES[(int) $c['mes']],
                'anio'          => (int) $c['anio'],
                'mes'           => (int) $c['mes'],
                'fincaCodigo'   => $c['fincaCodigo'],
                'loteCodigo'    => $c['loteCodigo'],
                'loteNombre'    => $c['loteCodigo'] . ($c['loteNombre'] === null ? '' : ' — ' . trim($c['loteNombre'])),
                'finca'         => $c['fincaCodigo'] . (trim($c['fincaNombre']) === '' ? '' : ' — ' . trim($c['fincaNombre'])),
                'seccion'       => $c['seccion'],
                'lote'          => $c['loteCodigo'] . ($c['loteNombre'] === null ? '' : ' — ' . trim($c['loteNombre'])),
                'vigencia'      => $c['fechaInicial'] === null || $c['fechaFinal'] === null ? '' : $fecha($c['fechaInicial']) . ' – ' . $fecha($c['fechaFinal']),
                'origen'        => (int) $c['automatico'] === 1 ? 'Automático' : 'Manual',
                'pesoRacimo'    => $peso,
                'pesoCalculado' => $calc,
                'diferencia'    => $calc === null ? null : round($peso - $calc, 3),
                'kg'            => $kg,
                'racimos'        => $racimos,
                'pesoXRacimos'   => $pxr,
                'difXRacimos'    => $control ? round($pxr - $kg, 3) : 0.0,
                'kgConRacimos'   => $control ? $kg : 0.0,
                'racimosControl' => $control ? $racimos : 0,
            ];
        }

        $general = self::ponderado($filas);
        $totales = [
            'kg'            => round($general['kg'], 3),
            'racimos'       => $general['racimos'],
            'pesoRacimo'    => $general['registrado'] === null ? null : round($general['registrado'], 3),
            'pesoCalculado' => $general['calculado'] === null ? null : round($general['calculado'], 3),
            'diferencia'    => $general['diferencia'] === null ? null : round($general['diferencia'], 3),
        ];

        if ($filas === []) {
            $promedio = [null, 'Sin registros de peso'];
        } elseif ($general['calculado'] !== null) {
            $dif      = round($general['diferencia'], 2);
            $promedio = [round($general['registrado'], 3), 'Calculado: ' . self::n($general['calculado']) . ' · Dif. ' . ($dif < 0 ? '-' : '+') . self::n(abs($dif))];
        } else {
            $pesos    = array_filter(array_column($filas, 'pesoRacimo'), static fn ($v) => $v > 0);
            $promedio = $pesos === [] ? [null, 'Sin pesos registrados'] : [round(array_sum($pesos) / count($pesos), 3), 'Promedio simple · sin cosecha registrada'];
        }

        $extremos = [];
        $sufijo   = $general['calculado'] === null ? ' · sin cosecha registrada' : '';

        foreach ($filas as $f) {
            if ($sufijo === '' ? $f['racimosControl'] > 0 : $f['pesoRacimo'] > 0) {
                $clave = $lote === null ? $f['fincaCodigo'] . '|' . $f['loteCodigo'] : $f['mes'];
                $extremos[$clave] ??= ['suma' => 0.0, 'n' => 0, 'ayuda' => ($lote === null ? $f['loteNombre'] . ' · ' . $f['fincaCodigo'] : self::MESES[$f['mes']] . ' ' . $f['anio']) . $sufijo];
                $extremos[$clave]['suma'] += $f['pesoRacimo'];
                $extremos[$clave]['n']++;
            }
        }

        $extremos = array_map(static fn ($e) => ['valor' => round($e['suma'] / $e['n'], 3), 'ayuda' => $e['ayuda']], $extremos);

        usort($extremos, static fn ($a, $b) => $b['valor'] <=> $a['valor']);

        $mayor    = $extremos[0] ?? ['valor' => null, 'ayuda' => 'Sin pesos registrados'];
        $menor    = $extremos === [] ? $mayor : $extremos[count($extremos) - 1];
        $manuales = count(array_keys(array_column($filas, 'origen'), 'Manual', true));
        $grafica  = $filas === [] ? null : ($filtros['mesDesde'] !== '' && $filtros['mesDesde'] === $filtros['mesHasta'] ? $this->barras($filas) : $this->lineas($filas, $lote !== null));

        if ($filas !== [] && $grafica === null) {
            $avisos[] = 'Sin cosecha registrada en el periodo; no hay control para graficar.';
        }

        return [
            'kpis' => [
                ['etiqueta' => 'Peso promedio (kg/racimo)', 'valor' => $promedio[0], 'formato' => 'decimal', 'icono' => 'bi bi-speedometer2', 'ayuda' => $promedio[1]],
                ['etiqueta' => $lote === null ? 'Mayor peso (kg/racimo)' : 'Mes con mayor peso', 'valor' => $mayor['valor'], 'formato' => 'decimal', 'icono' => 'bi bi-arrow-up-circle', 'ayuda' => $mayor['ayuda']],
                ['etiqueta' => $lote === null ? 'Menor peso (kg/racimo)' : 'Mes con menor peso', 'valor' => $menor['valor'], 'formato' => 'decimal', 'icono' => 'bi bi-arrow-down-circle', 'ayuda' => $menor['ayuda']],
                ['etiqueta' => 'Registros manuales', 'valor' => $filas === [] ? '—' : number_format($manuales * 100 / count($filas), 1, ',', '.') . '%', 'formato' => 'texto', 'icono' => 'bi bi-pencil-square',
                    'ayuda' => $filas === [] ? 'Sin registros de peso' : ($manuales === 0 ? 'Todos automáticos' : number_format($manuales, 0, ',', '.') . ' de ' . number_format(count($filas), 0, ',', '.') . ' registros')],
            ],
            'grafica' => $grafica,
            'columnas' => [
                ['clave' => 'grupo', 'titulo' => 'Periodo', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'finca', 'titulo' => 'Finca', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'seccion', 'titulo' => 'Sección', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'lote', 'titulo' => 'Lote', 'tipo' => 'texto', 'alineacion' => 'izquierda', 'total' => false],
                ['clave' => 'vigencia', 'titulo' => 'Vigencia liquidación', 'tipo' => 'texto', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'origen', 'titulo' => 'Origen', 'tipo' => 'estado', 'alineacion' => 'centro', 'total' => false],
                ['clave' => 'pesoRacimo', 'titulo' => 'Peso racimo (kg)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'pesoXRacimos', 'denominador' => 'racimosControl', 'ayuda' => 'Promedio ponderado por racimos con cosecha'],
                ['clave' => 'pesoCalculado', 'titulo' => 'Peso calculado (kg)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'kgConRacimos', 'denominador' => 'racimosControl', 'ayuda' => 'Σ kg / Σ racimos'],
                ['clave' => 'diferencia', 'titulo' => 'Diferencia (kg)', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => 'ponderado', 'numerador' => 'difXRacimos', 'denominador' => 'racimosControl', 'ayuda' => 'Registrado − calculado, ponderado por racimos'],
                ['clave' => 'kg', 'titulo' => 'Kg cosechados', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'racimos', 'titulo' => 'Racimos', 'tipo' => 'entero', 'alineacion' => 'derecha', 'total' => true],
                ['clave' => 'pesoXRacimos', 'titulo' => 'Peso × racimos', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
                ['clave' => 'difXRacimos', 'titulo' => 'Diferencia × racimos', 'tipo' => 'decimal', 'alineacion' => 'derecha', 'total' => false],
            ],
            'filas'   => $filas,
            'totales' => $totales,
            'agrupar' => 'grupo',
            'avisos'  => $avisos,
        ];
    }

    private function barras(array $filas): array
    {
        $pares  = array_unique(array_map(static fn ($f) => $f['fincaCodigo'] . '|' . $f['loteCodigo'], $filas));
        $fincas = array_count_values(array_map(static fn ($c) => explode('|', $c, 2)[1], $pares));

        usort($filas, static fn ($a, $b) => $b['pesoRacimo'] <=> $a['pesoRacimo']);

        $top = array_slice($filas, 0, 15);
        $mes = self::MESES[$filas[0]['mes']] . ' ' . $filas[0]['anio'];

        return [
            'tipo'       => 'bar',
            'horizontal' => true,
            'apilada'    => false,
            'titulo'     => (count($filas) > 15 ? 'Top 15 lotes por peso registrado · ' : 'Peso registrado por lote · ') . $mes,
            'categorias' => array_map(static fn ($f) => ($fincas[$f['loteCodigo']] > 1 ? $f['fincaCodigo'] . ' · ' : '') . $f['loteNombre'], $top),
            'series'     => [['nombre' => 'Peso registrado', 'datos' => array_column($top, 'pesoRacimo')]],
            'detalle'    => array_map(static fn ($f) => ['pesoCalculado' => $f['pesoCalculado'], 'kg' => $f['kg'], 'racimos' => $f['racimos'], 'origen' => $f['origen']], $top),
            'ejeX'       => 'Kg por racimo',
            'formato'    => 'decimal',
        ];
    }

    private function lineas(array $filas, bool $conLote): ?array
    {
        $meses = [];

        foreach ($filas as $f) {
            $meses[$f['mes']][] = $f;
        }

        $categorias = [];
        $registrado = [];
        $calculado  = [];
        $detalle    = [];

        for ($m = min(array_keys($meses)); $m <= max(array_keys($meses)); $m++) {
            $p            = isset($meses[$m]) ? self::ponderado($meses[$m]) : null;
            $categorias[] = mb_substr(self::MESES[$m], 0, 3);
            $registrado[] = $p === null || $p['registrado'] === null ? null : round($p['registrado'], 3);
            $calculado[]  = $p === null || $p['calculado'] === null ? null : round($p['calculado'], 3);
            $detalle[]    = $p === null ? null : ['kg' => round($p['kg'], 3), 'racimos' => $p['racimos']] + ($conLote ? ['origen' => $meses[$m][0]['origen']] : ['lotes' => count($meses[$m])]);
        }

        return array_filter($calculado, static fn ($v) => $v !== null) === [] ? null : [
            'tipo'       => 'line',
            'horizontal' => false,
            'titulo'     => $conLote ? 'Evolución mensual · ' . $filas[0]['loteNombre'] : 'Evolución mensual del peso promedio',
            'categorias' => $categorias,
            'series'     => [['nombre' => 'Registrado', 'datos' => $registrado], ['nombre' => 'Calculado', 'datos' => $calculado]],
            'colores'    => ['#2f7a4b', '#8795a4'],
            'guiones'    => [0, 5],
            'detalle'    => $detalle,
            'ejeX'       => 'Mes',
            'ejeY'       => 'Kg por racimo',
            'formato'    => 'decimal',
        ];
    }
}
