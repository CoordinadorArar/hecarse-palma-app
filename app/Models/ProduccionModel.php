<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de la transacción de producción tipo TLC.
 *
 * Una transacción se reparte en cuatro tablas: aTransaccion (encabezado),
 * aTransaccionBascula (el tiquete), aTransaccionNovedad (las líneas por lote) y
 * aTransaccionTercero (los trabajadores de cada línea).
 *
 * Particularidades verificadas contra los datos reales:
 *  - aTransaccionBascula.interno es la casilla "¿Tiquete externo?" invertida y
 *    empresaExtractora/terceroExtractrora (sic) guardan el NIT del tercero.
 *  - En aTransaccionNovedad la columna "novedad" es la CADENA VACÍA: hace parte
 *    de la llave primaria y no admite NULL. "seccion" siempre va NULL.
 *  - En aTransaccionTercero "novedad" sí es el código de labor, "registro" es un
 *    secuencial sobre toda la transacción y "registroNovedad" apunta al registro
 *    de la línea de aTransaccionNovedad a la que pertenece el trabajador.
 *  - El operario reparte RACIMOS; los kilos de cada línea los deriva el servidor
 *    con repartirKilos() para que sumen exactamente el peso neto del tiquete.
 */
class ProduccionModel extends Model
{
    protected $table      = 'aTransaccion';
    protected $returnType = 'array';

    /** Tipo de transacción de esta pantalla. */
    public const TIPO = 'TLC';

    /** Centro de costo con el que el legado registra los trabajadores de cosecha. */
    private const CCOSTO_POR_DEFECTO = '309901';

    private ?bool $eliminadas = null;

    // ── Catálogos ────────────────────────────────────────────────────────────

    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->where('activo', 1)->countAllResults() > 0;
    }

    public function extractoras(int $empresa): array
    {
        return $this->db->query(
            "SELECT id, LTRIM(RTRIM(nit)) AS nit,
                    ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS razonSocial
               FROM cTercero
              WHERE empresa = ? AND extractora = 1
              ORDER BY razonSocial ASC",
            [$empresa]
        )->getResultArray();
    }

    public function fincas(int $empresa): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, descripcion, activo
               FROM aFinca WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function lotes(int $empresa, string $finca): array
    {
        return $this->db->query(
            'SELECT LTRIM(RTRIM(codigo)) AS codigo, LTRIM(RTRIM(finca)) AS finca, descripcion, activo
               FROM aLotes WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? ORDER BY codigo ASC',
            [$empresa, trim($finca)]
        )->getResultArray();
    }

    /**
     * Labores activas agrupadas por código: la llave de aNovedad incluye el
     * concepto. claseLabor = 2 son las de cosecha; sin filtro devuelve todas,
     * que es lo que necesitan Carga y Transporte.
     */
    public function labores(int $empresa, string $clase = ''): array
    {
        $sql = "SELECT RTRIM(codigo) AS codigo, MIN(descripcion) AS descripcion,
                       MIN(claseLabor) AS claseLabor, MIN(RTRIM(ISNULL(uMedida, ''))) AS uMedida
                  FROM aNovedad WHERE empresa = ? AND activo = 1";

        $binds = [$empresa];

        if ($clase !== '') {
            $sql    .= ' AND claseLabor = ?';
            $binds[] = (int) $clase;
        }

        $sql .= ' GROUP BY RTRIM(codigo) ORDER BY codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    /**
     * Typeahead de terceros. Se devuelve el NIT porque es lo que guardan
     * aTransaccionTercero.tercero y las columnas de extractora.
     */
    public function buscarTercero(int $empresa, string $termino, int $tope = 15): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return [];
        }

        return $this->db->query(
            "SELECT TOP " . max(1, min($tope, 50)) . " id, LTRIM(RTRIM(nit)) AS nit,
                    ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS razonSocial, activo
               FROM cTercero
              WHERE empresa = ?
                AND (LTRIM(RTRIM(nit)) LIKE ? OR razonSocial LIKE ? OR descripcion LIKE ?)
              ORDER BY razonSocial ASC",
            [$empresa, $termino . '%', '%' . $termino . '%', '%' . $termino . '%']
        )->getResultArray();
    }

    /** Peso promedio del racimo del lote en el periodo. Devuelve 0 si no hay fila. */
    public function pesoPromedio(int $empresa, int $anio, int $mes, string $finca, string $lote): float
    {
        $fila = $this->db->query(
            'SELECT TOP 1 pesoRacimo FROM aLotePesosPeriodo
              WHERE empresa = ? AND [año] = ? AND mes = ?
                AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(lote)) = ?',
            [$empresa, $anio, $mes, trim($finca), trim($lote)]
        )->getRowArray();

        return $fila === null ? 0.0 : (float) $fila['pesoRacimo'];
    }

    /**
     * Transacciones en las que ya se usó un tiquete: el mismo tiquete se reparte
     * hasta en seis, y cada una guarda SU PORCIÓN. No existe un "neto del
     * tiquete" agregable (el mismo peso bruto convive con taras y netos
     * distintos), así que el prellenado toma los datos del viaje —fecha, bruto,
     * conductor, vehículo, extractora— del uso más reciente no anulado y deja
     * fuera pesoTara y pesoNeto, que son propios de cada tramo.
     *
     * @return array{tiquete: string, usos: array<int, array<string, mixed>>, prellenado: array<string, mixed>|null}
     */
    public function buscarTiquete(int $empresa, string $tiquete): array
    {
        $usos = $this->db->query(
            "SELECT LTRIM(RTRIM(b.numero)) AS numero, CONVERT(varchar(10), b.fecha, 23) AS fecha,
                    b.pesoBruto, b.pesoTara, b.pesoNeto, b.sacos, b.racimos,
                    LTRIM(RTRIM(ISNULL(b.vehiculo, ''))) AS vehiculo,
                    LTRIM(RTRIM(ISNULL(b.remolque, ''))) AS remolque,
                    CASE WHEN ISNULL(b.interno, 0) = 0 THEN 0 ELSE 1 END AS interno,
                    LTRIM(RTRIM(ISNULL(b.codigoConductor, ''))) AS codigoConductor,
                    LTRIM(RTRIM(ISNULL(b.nombreConductor, ''))) AS nombreConductor,
                    LTRIM(RTRIM(ISNULL(b.empresaExtractora, ''))) AS extractora,
                    CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                    ISNULL((SELECT SUM(n.racimos) FROM aTransaccionNovedad n
                             WHERE n.empresa = b.empresa AND n.tipo = b.tipo AND n.numero = b.numero), 0) AS racimosDistribuidos
               FROM aTransaccionBascula b
               LEFT JOIN aTransaccion t
                      ON t.empresa = b.empresa AND t.tipo = b.tipo AND t.numero = b.numero
              WHERE b.empresa = ? AND b.tipo = ? AND LTRIM(RTRIM(b.tiquete)) = ?" . $this->sinEliminadas('b') . "
              ORDER BY b.numero ASC",
            [$empresa, static::TIPO, trim($tiquete)]
        )->getResultArray();

        $prellenado = null;

        foreach ($usos as &$uso) {
            $uso['pesoBruto']           = (int) $uso['pesoBruto'];
            $uso['pesoTara']            = (int) $uso['pesoTara'];
            $uso['pesoNeto']            = (int) $uso['pesoNeto'];
            $uso['sacos']               = (int) $uso['sacos'];
            $uso['racimos']             = (int) $uso['racimos'];
            $uso['interno']             = (int) $uso['interno'];
            $uso['anulado']             = (int) $uso['anulado'];
            $uso['racimosDistribuidos'] = (int) $uso['racimosDistribuidos'];

            if ($uso['anulado'] === 0) {
                $prellenado = [
                    'fecha'           => $uso['fecha'],
                    'pesoBruto'       => $uso['pesoBruto'],
                    'codigoConductor' => $uso['codigoConductor'],
                    'nombreConductor' => $uso['nombreConductor'],
                    'vehiculo'        => $uso['vehiculo'],
                    'remolque'        => $uso['remolque'],
                    'extractora'      => $uso['extractora'],
                    'interno'         => $uso['interno'],
                ];
            }
        }

        unset($uso);

        return [
            'tiquete'    => trim($tiquete),
            'usos'       => $usos,
            'prellenado' => $prellenado,
        ];
    }

    public function consultar(int $empresa, string $desde, string $hasta, string $tiquete, string $numero, string $estado): array
    {
        $sql = "SELECT TOP 501 LTRIM(RTRIM(t.numero)) AS numero, CONVERT(varchar(10), t.fecha, 23) AS fecha,
                       t.[año] AS anio, t.mes,
                       LTRIM(RTRIM(ISNULL(b.tiquete, ''))) AS tiquete,
                       LTRIM(RTRIM(ISNULL(b.vehiculo, ''))) AS vehiculo,
                       CASE WHEN b.numero IS NOT NULL AND ISNULL(b.interno, 0) = 0 THEN 1 ELSE 0 END AS externo,
                       ISNULL(b.racimos, 0) AS racimos, ISNULL(b.pesoNeto, 0) AS pesoNeto,
                       (SELECT COUNT(*) FROM aTransaccionNovedad n
                         WHERE n.empresa = t.empresa AND n.tipo = t.tipo AND n.numero = t.numero) AS lineas,
                       (SELECT COUNT(*) FROM aTransaccionTercero r
                         WHERE r.empresa = t.empresa AND r.tipo = t.tipo AND r.numero = t.numero) AS trabajadores,
                       ISNULL(t.observacion, '') AS observacion,
                       CASE WHEN ISNULL(t.anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                       CASE WHEN EXISTS (SELECT 1 FROM cPeriodo p WHERE p.empresa = t.empresa AND p.[año] = t.[año]
                                            AND p.mes = t.mes AND p.cerrado = 1) THEN 1 ELSE 0 END AS cerrado,
                       CASE WHEN " . $this->liquidadaSql('t') . " THEN 1 ELSE 0 END AS bloqueada
                  FROM aTransaccion t
                 OUTER APPLY (SELECT TOP 1 * FROM aTransaccionBascula x
                               WHERE x.empresa = t.empresa AND x.tipo = t.tipo AND x.numero = t.numero) b
                 WHERE t.empresa = ? AND t.tipo = ?" . $this->sinEliminadas('t');

        $binds = [$empresa, static::TIPO];

        if ($desde !== '') {
            $sql    .= ' AND t.fecha >= ?';
            $binds[] = $desde;
        }

        if ($hasta !== '') {
            $sql    .= ' AND t.fecha <= ?';
            $binds[] = $hasta;
        }

        if ($tiquete !== '') {
            $sql    .= ' AND LTRIM(RTRIM(b.tiquete)) LIKE ?';
            $binds[] = '%' . strtr($tiquete, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
        }

        if ($numero !== '') {
            $sql    .= ' AND LTRIM(RTRIM(t.numero)) LIKE ?';
            $binds[] = '%' . strtr($numero, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
        }

        if ($estado === '0') {
            $sql .= ' AND ISNULL(t.anulado, 0) = 0';
        } elseif ($estado === '1') {
            $sql .= ' AND t.anulado = 1';
        }

        $sql .= ' ORDER BY t.fecha DESC, t.numero DESC';

        $filas = $this->db->query($sql, $binds)->getResultArray();

        foreach ($filas as &$fila) {
            foreach (['anio', 'mes', 'externo', 'racimos', 'pesoNeto', 'lineas', 'trabajadores', 'anulado', 'cerrado', 'bloqueada'] as $campo) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        unset($fila);

        return $filas;
    }

    public function detalle(int $empresa, string $numero): ?array
    {
        $binds = [$empresa, static::TIPO, trim($numero)];

        $encabezado = $this->db->query(
            "SELECT LTRIM(RTRIM(numero)) AS numero, CONVERT(varchar(10), fecha, 23) AS fecha,
                    [año] AS anio, mes, ISNULL(observacion, '') AS observacion,
                    CASE WHEN ISNULL(anulado, 0) = 0 THEN 0 ELSE 1 END AS anulado,
                    LTRIM(RTRIM(ISNULL(usuarioRegistro, ''))) AS usuarioRegistro,
                    CONVERT(varchar(16), fechaRegistro, 120) AS fechaRegistro,
                    CONVERT(varchar(16), fechaAnulado, 120) AS fechaAnulado,
                    LTRIM(RTRIM(ISNULL(usuarioAnulado, ''))) AS usuarioAnulado
               FROM aTransaccion
              WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?" . $this->sinEliminadas('aTransaccion'),
            $binds
        )->getRowArray();

        if ($encabezado === null) {
            return null;
        }

        $encabezado['anio']    = (int) $encabezado['anio'];
        $encabezado['mes']     = (int) $encabezado['mes'];
        $encabezado['anulado'] = (int) $encabezado['anulado'];

        if ($encabezado['anulado'] === 0) {
            $encabezado['fechaAnulado']   = null;
            $encabezado['usuarioAnulado'] = '';
        }

        $bascula = $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(ISNULL(b.tiquete, ''))) AS tiquete,
                    LTRIM(RTRIM(ISNULL(b.planta, ''))) AS planta,
                    CONVERT(varchar(10), b.fecha, 23) AS fecha,
                    b.pesoBruto, b.pesoTara, b.pesoNeto, b.sacos, b.racimos,
                    LTRIM(RTRIM(ISNULL(b.vehiculo, ''))) AS vehiculo,
                    LTRIM(RTRIM(ISNULL(b.remolque, ''))) AS remolque,
                    CASE WHEN ISNULL(b.interno, 0) = 0 THEN 1 ELSE 0 END AS externo,
                    CASE WHEN ISNULL(b.interno, 0) = 0 THEN 0 ELSE 1 END AS interno,
                    LTRIM(RTRIM(ISNULL(b.codigoConductor, ''))) AS codigoConductor,
                    LTRIM(RTRIM(ISNULL(b.nombreConductor, ''))) AS nombreConductor,
                    LTRIM(RTRIM(ISNULL(b.empresaExtractora, ''))) AS extractora,
                    ISNULL(e.nombre, '') AS extractoraNombre
               FROM aTransaccionBascula b
               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(c.razonSocial)), ''), c.descripcion) AS nombre
                              FROM cTercero c
                             WHERE c.empresa = b.empresa AND LTRIM(RTRIM(c.nit)) = LTRIM(RTRIM(b.empresaExtractora))) e
              WHERE b.empresa = ? AND b.tipo = ? AND LTRIM(RTRIM(b.numero)) = ?",
            $binds
        )->getRowArray();

        if ($bascula !== null) {
            foreach (['pesoBruto', 'pesoTara', 'pesoNeto', 'sacos', 'racimos', 'externo', 'interno'] as $campo) {
                $bascula[$campo] = (int) $bascula[$campo];
            }
        }

        $lineas = $this->db->query(
            "SELECT n.registro, LTRIM(RTRIM(n.finca)) AS finca, ISNULL(f.descripcion, '') AS fincaNombre,
                    LTRIM(RTRIM(n.lote)) AS lote, ISNULL(l.descripcion, '') AS loteNombre,
                    ISNULL(n.racimos, 0) AS racimos, ISNULL(n.sacos, 0) AS sacos,
                    ISNULL(n.pesoRacimo, 0) AS pesoRacimo, ISNULL(n.cantidad, 0) AS cantidad
               FROM aTransaccionNovedad n
               OUTER APPLY (SELECT TOP 1 descripcion FROM aFinca
                             WHERE empresa = n.empresa AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(n.finca))) f
               OUTER APPLY (SELECT TOP 1 descripcion FROM aLotes
                             WHERE empresa = n.empresa AND LTRIM(RTRIM(finca)) = LTRIM(RTRIM(n.finca))
                               AND LTRIM(RTRIM(codigo)) = LTRIM(RTRIM(n.lote))) l
              WHERE n.empresa = ? AND n.tipo = ? AND LTRIM(RTRIM(n.numero)) = ?
              ORDER BY n.registro ASC",
            $binds
        )->getResultArray();

        $trabajadores = $this->db->query(
            "SELECT r.registroNovedad, LTRIM(RTRIM(r.tercero)) AS tercero, ISNULL(c.nombre, '') AS terceroNombre,
                    RTRIM(ISNULL(r.novedad, '')) AS novedad,
                    ISNULL((SELECT MIN(a.descripcion) FROM aNovedad a
                             WHERE a.empresa = r.empresa AND RTRIM(a.codigo) = RTRIM(r.novedad)), '') AS novedadNombre,
                    CONVERT(varchar(10), r.fechaNovedad, 23) AS fechaNovedad,
                    ISNULL(r.cantidad, 0) AS cantidad, ISNULL(r.jornales, 0) AS jornales,
                    ISNULL(r.precioLabor, 0) AS precioLabor, ISNULL(r.valorTotal, 0) AS valorTotal,
                    CASE WHEN ISNULL(r.contratista, 0) = 0 THEN 0 ELSE 1 END AS contratista,
                    ISNULL(r.racimos, 0) AS racimos
               FROM aTransaccionTercero r
               OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
                              FROM cTercero
                             WHERE empresa = r.empresa AND LTRIM(RTRIM(nit)) = LTRIM(RTRIM(r.tercero))) c
              WHERE r.empresa = ? AND r.tipo = ? AND LTRIM(RTRIM(r.numero)) = ?
              ORDER BY r.registroNovedad ASC, r.registro ASC",
            $binds
        )->getResultArray();

        $porLinea = [];

        foreach ($trabajadores as $trabajador) {
            $registro = (int) $trabajador['registroNovedad'];
            unset($trabajador['registroNovedad']);

            foreach (['cantidad', 'jornales', 'precioLabor', 'valorTotal'] as $campo) {
                $trabajador[$campo] = (float) $trabajador[$campo];
            }

            $trabajador['contratista'] = (int) $trabajador['contratista'];
            $trabajador['racimos']     = (int) $trabajador['racimos'];

            $porLinea[$registro][] = $trabajador;
        }

        foreach ($lineas as &$linea) {
            $linea['registro']     = (int) $linea['registro'];
            $linea['racimos']      = (int) $linea['racimos'];
            $linea['sacos']        = (int) $linea['sacos'];
            $linea['pesoRacimo']   = (float) $linea['pesoRacimo'];
            $linea['cantidad']     = (float) $linea['cantidad'];
            $linea['trabajadores'] = $porLinea[$linea['registro']] ?? [];
        }

        unset($linea);

        return ['encabezado' => $encabezado, 'bascula' => $bascula, 'lineas' => $lineas];
    }

    public function anios(int $empresa): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT [año] AS anio FROM cPeriodo WHERE empresa = ? ORDER BY anio DESC',
            [$empresa]
        )->getResultArray();

        return array_map(static fn ($f) => (int) $f['anio'], $filas);
    }

    public function periodoActual(int $empresa): ?array
    {
        $hoy = date('Y-m-d');

        $fila = $this->db->query(
            'SELECT TOP 1 [año] AS anio, mes FROM cPeriodo WHERE empresa = ?
              ORDER BY CASE WHEN (fechaInicial IS NOT NULL AND fechaFinal IS NOT NULL AND ? BETWEEN fechaInicial AND fechaFinal)
                              OR (fechaInicial IS NULL AND [año] = ? AND mes = ?) THEN 0 ELSE 1 END,
                       [año] DESC, mes DESC',
            [$empresa, $hoy, (int) date('Y'), (int) date('n')]
        )->getRowArray();

        return $fila === null ? null : ['anio' => (int) $fila['anio'], 'mes' => (int) $fila['mes']];
    }

    public function periodos(int $empresa, int $anio): array
    {
        $filas = $this->db->query(
            "SELECT mes, ISNULL(descripcion, '') AS descripcion,
                    CONVERT(varchar(10), fechaInicial, 23) AS fechaInicial,
                    CONVERT(varchar(10), fechaFinal, 23) AS fechaFinal,
                    CASE WHEN ISNULL(cerrado, 0) = 0 THEN 0 ELSE 1 END AS cerrado
               FROM cPeriodo WHERE empresa = ? AND [año] = ? ORDER BY mes ASC",
            [$empresa, $anio]
        )->getResultArray();

        foreach ($filas as &$fila) {
            $fila['mes']     = (int) $fila['mes'];
            $fila['cerrado'] = (int) $fila['cerrado'];
        }

        unset($fila);

        return $filas;
    }

    public function periodo(int $empresa, int $anio, int $mes): ?array
    {
        $fila = $this->db->query(
            "SELECT CONVERT(varchar(10), fechaInicial, 23) AS fechaInicial,
                    CONVERT(varchar(10), fechaFinal, 23) AS fechaFinal,
                    CASE WHEN ISNULL(cerrado, 0) = 0 THEN 0 ELSE 1 END AS cerrado
               FROM cPeriodo WHERE empresa = ? AND [año] = ? AND mes = ?",
            [$empresa, $anio, $mes]
        )->getRowArray();

        if ($fila !== null) {
            $fila['cerrado'] = (int) $fila['cerrado'];
        }

        return $fila;
    }

    public function accionesPermitidas(int $usuario, int $modulo): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT ma.Nombre
               FROM UsuariosRoles ur
               JOIN Roles r ON r.Id = ur.IdRol AND r.FechaFinalizacion IS NULL
               JOIN ModulosPermisosAcciones mpa ON mpa.IdRol = ur.IdRol AND mpa.FechaFinalizacion IS NULL
               JOIN ModulosAcciones ma ON ma.Id = mpa.IdAccion AND ma.IdModulo = ? AND ma.FechaFinalizacion IS NULL
               JOIN Modulos m ON m.Id = ma.IdModulo AND m.FechaFinalizacion IS NULL
              WHERE ur.IdUsuario = ? AND ur.FechaFinalizacion IS NULL
                AND EXISTS (SELECT 1 FROM ModulosRoles mr
                              JOIN UsuariosRoles ux ON ux.IdRol = mr.IdRol AND ux.IdUsuario = ur.IdUsuario AND ux.FechaFinalizacion IS NULL
                              JOIN Roles rx ON rx.Id = ux.IdRol AND rx.FechaFinalizacion IS NULL
                             WHERE mr.IdModulo = ? AND mr.FechaFinalizacion IS NULL)',
            [$modulo, $usuario, $modulo]
        )->getResultArray();

        return array_column($filas, 'Nombre');
    }

    public function liquidada(int $empresa, string $numero): bool
    {
        return $this->db->query(
            'SELECT TOP 1 1 AS x FROM aTransaccion a
              WHERE a.empresa = ? AND a.tipo = ? AND LTRIM(RTRIM(a.numero)) = ? AND ' . $this->liquidadaSql('a'),
            [$empresa, static::TIPO, trim($numero)]
        )->getRowArray() !== null;
    }

    protected function liquidadaSql(string $alias): string
    {
        $filtro = "empresa = {$alias}.empresa AND tipo = {$alias}.tipo AND numero = {$alias}.numero"
            . ' AND (periodo IS NOT NULL OR ISNULL(ejecutado, 0) = 1 OR ISNULL(saldo, 0) <> ISNULL(cantidad, 0))';

        return "(EXISTS (SELECT 1 FROM aTransaccionNovedad WHERE {$filtro}) OR EXISTS (SELECT 1 FROM aTransaccionTercero WHERE {$filtro}))";
    }

    public function tablaEliminadas(): bool
    {
        if ($this->eliminadas === null) {
            $this->eliminadas = $this->db->query("SELECT OBJECT_ID('dbo.aTransaccionEliminada', 'U') AS id")->getRow()->id !== null;
        }

        return $this->eliminadas;
    }

    protected function sinEliminadas(string $alias): string
    {
        if (! $this->tablaEliminadas()) {
            return '';
        }

        return " AND NOT EXISTS (SELECT 1 FROM aTransaccionEliminada el WHERE el.empresa = {$alias}.empresa"
            . " AND el.tipo = {$alias}.tipo AND el.numero = LTRIM(RTRIM({$alias}.numero)))";
    }

    // ── Validadores ──────────────────────────────────────────────────────────

    public function fincaValida(int $empresa, string $finca): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aFinca WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, trim($finca)]
        )->getRowArray() !== null;
    }

    public function loteDeFinca(int $empresa, string $finca, string $lote): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aLotes
              WHERE empresa = ? AND LTRIM(RTRIM(finca)) = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, trim($finca), trim($lote)]
        )->getRowArray() !== null;
    }

    public function laborValida(int $empresa, string $novedad): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aNovedad WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($novedad)]
        )->getRowArray() !== null;
    }

    /** El tercero se identifica por NIT, que es lo que guarda la transacción. */
    public function terceroValido(int $empresa, string $nit): bool
    {
        return $this->db->query(
            'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND LTRIM(RTRIM(nit)) = ?',
            [$empresa, trim($nit)]
        )->getRowArray() !== null;
    }

    public function extractoraValida(int $empresa, string $nit): bool
    {
        return $this->db->query(
            'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND extractora = 1 AND LTRIM(RTRIM(nit)) = ?',
            [$empresa, trim($nit)]
        )->getRowArray() !== null;
    }

    /**
     * Precio sugerido de una labor para un trabajador en un lote, resuelto con la
     * función del legado: es la que decide si aplica precioDestajo, precioContratistas
     * o precioOtros y la cascada lote → sección → finca → lista general del año.
     * Solo lee; devuelve 0 si no se puede resolver.
     */
    public function precioLabor(
        int $empresa,
        string $novedad,
        int $anio,
        string $tercero,
        string $fecha,
        string $finca,
        string $lote
    ): float {
        try {
            $fila = $this->db->query(
                'SELECT dbo.fRetornaPrecioLaboresTercero(?, ?, ?, ?, ?, ?, ?, ?, ?) AS precio',
                [$empresa, trim($novedad), $anio, (int) $tercero, $fecha, trim($finca), '', trim($lote), 0]
            )->getRowArray();
        } catch (\Throwable $e) {
            log_message('error', 'Error resolviendo el precio de la labor: ' . $e->getMessage());

            return 0.0;
        }

        return $fila === null ? 0.0 : round((float) $fila['precio'], 2);
    }

    // ── Reparto de kilos ─────────────────────────────────────────────────────

    /**
     * Reparte el peso neto del tiquete entre las líneas, proporcionalmente a
     * racimos × pesoRacimo, con el método de mayores restos sobre dos decimales
     * para que la suma dé exactamente el neto. El residuo va a la línea de mayor
     * ponderador.
     *
     * Casos borde: si ninguna línea tiene pesoRacimo, el reparto es proporcional
     * solo a los racimos; si algunas no lo tienen, esas quedan en cero kilos y se
     * informan en "sinPeso"; con peso neto cero todas quedan en cero.
     *
     * @param array<int, array{racimos?: int|float|string, pesoRacimo?: int|float|string}> $lineas
     *
     * @return array{kilos: array<int, float>, sinPeso: array<int, int>, total: float}
     */
    public function conservarReparto(array $datos, ?array $antes): array
    {
        if ($antes === null || $antes['bascula'] === null || (int) $antes['bascula']['pesoNeto'] !== (int) $datos['bascula']['pesoNeto']
            || count($antes['lineas']) !== count($datos['lineas'])) {
            return $datos;
        }

        $lineas = array_values($datos['lineas']);
        $kilos  = [];

        foreach (array_values($antes['lineas']) as $i => $previa) {
            if (trim($previa['finca']) !== trim($lineas[$i]['finca']) || trim($previa['lote']) !== trim($lineas[$i]['lote'])
                || (int) $previa['racimos'] !== (int) $lineas[$i]['racimos']) {
                return $datos;
            }

            $lineas[$i]['pesoRacimo'] = (float) $previa['pesoRacimo'];
            $kilos[]                  = (float) $previa['cantidad'];
        }

        $datos['lineas'] = $lineas;
        $datos['kilos']  = $kilos;

        return $datos;
    }

    public function reparto(array $datos): array
    {
        if (isset($datos['kilos'])) {
            return ['kilos' => $datos['kilos'], 'sinPeso' => [], 'total' => round(array_sum($datos['kilos']), 2)];
        }

        return $this->repartirKilos((int) $datos['bascula']['pesoNeto'], array_values($datos['lineas']));
    }

    public function repartirKilos(int $pesoNeto, array $lineas): array
    {
        $lineas  = array_values($lineas);
        $kilos   = array_fill(0, count($lineas), 0.0);
        $sinPeso = [];

        if ($lineas === []) {
            return ['kilos' => [], 'sinPeso' => [], 'total' => 0.0];
        }

        $pesos = [];
        $suma  = 0.0;

        foreach ($lineas as $i => $linea) {
            $racimos    = max(0, (int) ($linea['racimos'] ?? 0));
            $pesoRacimo = max(0, (float) ($linea['pesoRacimo'] ?? 0));
            $pesos[$i]  = $racimos * $pesoRacimo;
            $suma      += $pesos[$i];
        }

        if ($suma <= 0) {
            $pesos = [];

            foreach ($lineas as $i => $linea) {
                $pesos[$i] = max(0, (int) ($linea['racimos'] ?? 0));
                $suma     += $pesos[$i];
            }
        } else {
            foreach ($lineas as $i => $linea) {
                if ((float) ($linea['pesoRacimo'] ?? 0) <= 0 && (int) ($linea['racimos'] ?? 0) > 0) {
                    $sinPeso[] = $i;
                }
            }
        }

        if ($suma <= 0 || $pesoNeto <= 0) {
            return ['kilos' => $kilos, 'sinPeso' => $sinPeso, 'total' => 0.0];
        }

        $centavos = $pesoNeto * 100;
        $base     = [];
        $restos   = [];
        $asignado = 0;

        foreach ($pesos as $i => $peso) {
            $exacto     = $centavos * $peso / $suma;
            $base[$i]   = (int) floor($exacto);
            $restos[$i] = $exacto - $base[$i];
            $asignado  += $base[$i];
        }

        $sobrante = $centavos - $asignado;

        if ($sobrante > 0) {
            $orden = array_keys($pesos);

            usort($orden, static function ($a, $b) use ($restos, $pesos) {
                if ($restos[$a] !== $restos[$b]) {
                    return $restos[$b] <=> $restos[$a];
                }

                return $pesos[$b] <=> $pesos[$a];
            });

            foreach ($orden as $i) {
                if ($sobrante <= 0) {
                    break;
                }

                if ($pesos[$i] <= 0) {
                    continue;
                }

                $base[$i]++;
                $sobrante--;
            }

            if ($sobrante > 0) {
                $mayor = array_keys($pesos, max($pesos), true)[0];
                $base[$mayor] += $sobrante;
            }
        }

        $total = 0.0;

        foreach ($base as $i => $centavosLinea) {
            $kilos[$i] = round($centavosLinea / 100, 2);
            $total    += $kilos[$i];
        }

        return ['kilos' => $kilos, 'sinPeso' => $sinPeso, 'total' => round($total, 2)];
    }

    // ── Escritura ────────────────────────────────────────────────────────────

    /**
     * Registra la transacción completa dentro de UNA transacción de base de
     * datos: consecutivo, encabezado, tiquete, líneas, trabajadores y auditoría.
     * Si algo falla se revierte todo, incluido el consecutivo consumido.
     *
     * @param array{empresa: int, fecha: string, observacion: string, usuario: string,
     *              bascula: array<string, mixed>, lineas: array<int, array<string, mixed>>} $datos
     *
     * @return array{success: bool, message: string, numero?: string, avisos?: array<int, string>, lineas?: array<int, array<string, mixed>>}
     */
    public function guardar(array $datos, ?int $usu_id): array
    {
        $empresa = (int) $datos['empresa'];
        $fecha   = $datos['fecha'];
        $anio    = (int) $datos['anio'];
        $mes     = (int) $datos['mes'];
        $lineas  = array_values($datos['lineas']);
        $reparto = $this->reparto($datos);
        $avisos  = $this->avisosSinPeso($reparto['sinPeso'], $lineas, $fecha);

        $this->db->transBegin();

        try {
            $numero = $this->consecutivo($empresa);

            if ($numero === null) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo obtener el consecutivo del tipo de transacción ' . static::TIPO . '.'];
            }

            if ($this->numeroUsado($empresa, $numero)) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'El consecutivo ' . $numero . ' ya está usado por otra transacción. Intente de nuevo.'];
            }

            $registro = date('Y-m-d\TH:i:s');

            $this->db->query(
                'INSERT INTO aTransaccion (empresa, [año], mes, tipo, numero, fecha, fechaFinal, observacion,
                                           fechaRegistro, usuarioRegistro, anulado, cerrado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
                [$empresa, $anio, $mes, static::TIPO, $numero, $fecha, $registro, $datos['observacion'], $registro, $datos['usuario']]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar el encabezado de la transacción.'];
            }

            $detalle = $this->insertarDetalle($empresa, $anio, $mes, $numero, $datos, $reparto);

            if (is_string($detalle)) {
                $this->db->transRollback();

                return ['success' => false, 'message' => $detalle];
            }

            $auditoria = $this->insertarAuditoria('INSERT', [
                'empresa'      => $empresa,
                'tipo'         => static::TIPO,
                'numero'       => $numero,
                'año'          => $anio,
                'mes'          => $mes,
                'fecha'        => $fecha,
                'bascula'      => $datos['bascula'],
                'lineas'       => $detalle['lineas'],
                'trabajadores' => $detalle['trabajadores'],
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo registrar la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'message' => 'Transacción ' . $numero . ' registrada correctamente.',
                'numero'  => $numero,
                'avisos'  => $avisos,
                'lineas'  => $detalle['lineas'],
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error registrando la transacción de producción: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo registrar la transacción. La operación fue revertida.'];
        }
    }

    public function actualizar(array $datos, array $antes, ?int $usu_id): array
    {
        $empresa = (int) $datos['empresa'];
        $fecha   = $datos['fecha'];
        $anio    = (int) $datos['anio'];
        $mes     = (int) $datos['mes'];
        $numero  = $antes['encabezado']['numero'];
        $lineas  = array_values($datos['lineas']);
        $reparto = $this->reparto($datos);
        $avisos  = $this->avisosSinPeso($reparto['sinPeso'], $lineas, $fecha);
        $llave   = [$empresa, static::TIPO, $numero];

        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE aTransaccion SET fecha = ?, [año] = ?, mes = ?, observacion = ?
                  WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ? AND ISNULL(anulado, 0) = 0',
                array_merge([$fecha, $anio, $mes, $datos['observacion']], $llave)
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar el encabezado de la transacción.'];
            }

            foreach (['aTransaccionTercero', 'aTransaccionNovedad', 'aTransaccionBascula'] as $tabla) {
                $this->db->query('DELETE FROM ' . $tabla . ' WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?', $llave);
            }

            $detalle = $this->insertarDetalle($empresa, $anio, $mes, $numero, $datos, $reparto);

            if (is_string($detalle)) {
                $this->db->transRollback();

                return ['success' => false, 'message' => $detalle];
            }

            $auditoria = $this->insertarAuditoria('UPDATE', [
                'empresa' => $empresa,
                'tipo'    => static::TIPO,
                'numero'  => $numero,
                'antes'   => $antes,
                'despues' => [
                    'año'          => $anio,
                    'mes'          => $mes,
                    'fecha'        => $fecha,
                    'observacion'  => $datos['observacion'],
                    'usuario'      => $datos['usuario'],
                    'bascula'      => $datos['bascula'],
                    'lineas'       => $datos['lineas'],
                    'kilos'        => $detalle['lineas'],
                    'trabajadores' => $detalle['trabajadores'],
                ],
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo actualizar la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'message' => 'Transacción ' . $numero . ' actualizada correctamente.',
                'numero'  => $numero,
                'avisos'  => $avisos,
                'lineas'  => $detalle['lineas'],
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando la transacción de producción: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo actualizar la transacción. La operación fue revertida.'];
        }
    }

    public function eliminar(int $empresa, array $antes, string $motivo, string $usuario, ?int $usu_id): array
    {
        $numero = $antes['encabezado']['numero'];
        $fecha  = date('Y-m-d\TH:i:s');

        $this->db->transBegin();

        try {
            $this->db->query(
                'INSERT INTO aTransaccionEliminada (empresa, tipo, numero, fechaEliminado, usuarioEliminado, usu_id, motivo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$empresa, static::TIPO, $numero, $fecha, $usuario, $usu_id, $motivo === '' ? null : $motivo]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo eliminar la transacción ' . $numero . '.'];
            }

            $auditoria = $this->insertarAuditoria('DELETE', [
                'empresa'     => $empresa,
                'tipo'        => static::TIPO,
                'numero'      => $numero,
                'logico'      => true,
                'eliminacion' => ['fecha' => $fecha, 'usuario' => $usuario, 'motivo' => $motivo],
                'detalle'     => $antes,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return ['success' => false, 'message' => 'No se pudo eliminar la transacción. La operación fue revertida.'];
            }

            $this->db->transCommit();

            return ['success' => true, 'message' => 'Transacción ' . $numero . ' eliminada correctamente.', 'numero' => $numero];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando la transacción de producción: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudo eliminar la transacción. La operación fue revertida.'];
        }
    }

    private function avisosSinPeso(array $sinPeso, array $lineas, string $fecha): array
    {
        $avisos = [];

        foreach ($sinPeso as $i) {
            $avisos[] = 'El lote ' . $lineas[$i]['lote'] . ' de la finca ' . $lineas[$i]['finca']
                . ' no tiene peso promedio registrado en el periodo ' . substr($fecha, 0, 7)
                . ', por lo que quedó en 0 kilos.';
        }

        return $avisos;
    }

    private function insertarDetalle(int $empresa, int $anio, int $mes, string $numero, array $datos, array $reparto): array|string
    {
        $fecha   = $datos['fecha'];
        $bascula = $datos['bascula'];
        $lineas  = array_values($datos['lineas']);

        $this->db->query(
            'INSERT INTO aTransaccionBascula (empresa, tipo, numero, planta, tiquete, fecha, pesoBruto, pesoTara,
                                              pesoNeto, sacos, racimos, codigoConductor, nombreConductor,
                                              vehiculo, remolque, interno, empresaExtractora, terceroExtractrora)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa,
                static::TIPO,
                $numero,
                $bascula['planta'],
                $bascula['tiquete'],
                $fecha . 'T00:00:00',
                (int) $bascula['pesoBruto'],
                (int) $bascula['pesoTara'],
                (int) $bascula['pesoNeto'],
                (int) $bascula['sacos'],
                (int) $bascula['racimos'],
                $bascula['codigoConductor'],
                $bascula['nombreConductor'],
                $bascula['vehiculo'],
                $bascula['remolque'],
                (int) $bascula['interno'],
                $bascula['extractora'],
                $bascula['extractora'],
            ]
        );

        if ($this->db->affectedRows() !== 1) {
            return 'No se pudo registrar el tiquete de la transacción.';
        }

        $ccosto    = $this->ccostoPorDefecto($empresa);
        $resultado = [];
        $secuencia = 0;

        foreach ($lineas as $i => $linea) {
            $orden    = $i + 1;
            $cantidad = $reparto['kilos'][$i];

            $this->db->query(
                'INSERT INTO aTransaccionNovedad (empresa, [año], mes, tipo, numero, novedad, registro, finca,
                                                  lote, fecha, cantidad, jornales, racimos, pesoRacimo, saldo,
                                                  ejecutado, signo, precioLabor, registroNovedad, sacos)
                 VALUES (?, ?, ?, ?, ?, \'\', ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, 0, 0, ?, ?)',
                [
                    $empresa,
                    $anio,
                    $mes,
                    static::TIPO,
                    $numero,
                    $orden,
                    $linea['finca'],
                    $linea['lote'],
                    $fecha . 'T00:00:00',
                    $cantidad,
                    (int) $linea['racimos'],
                    (float) $linea['pesoRacimo'],
                    $cantidad,
                    $orden,
                    (int) $linea['sacos'],
                ]
            );

            if ($this->db->affectedRows() !== 1) {
                return 'No se pudo registrar la línea ' . $orden . ' de la transacción.';
            }

            foreach ($linea['trabajadores'] as $trabajador) {
                $this->db->query(
                    'INSERT INTO aTransaccionTercero (empresa, [año], mes, tipo, numero, novedad, registro,
                                                      registroNovedad, finca, lote, tercero, cantidad, jornales,
                                                      saldo, ejecutado, precioLabor, valorTotal, ccosto,
                                                      contratista, racimos, fechaNovedad)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)',
                    [
                        $empresa,
                        $anio,
                        $mes,
                        static::TIPO,
                        $numero,
                        $trabajador['novedad'],
                        $secuencia,
                        $orden,
                        $linea['finca'],
                        $linea['lote'],
                        $trabajador['tercero'],
                        (float) $trabajador['cantidad'],
                        (float) $trabajador['jornales'],
                        (float) $trabajador['cantidad'],
                        (float) $trabajador['precioLabor'],
                        round((float) $trabajador['cantidad'] * (float) $trabajador['precioLabor'], 2),
                        $ccosto,
                        (int) $trabajador['contratista'],
                        (int) $trabajador['racimos'],
                        $trabajador['fechaNovedad'] . 'T00:00:00',
                    ]
                );

                if ($this->db->affectedRows() !== 1) {
                    return 'No se pudo registrar el trabajador ' . $trabajador['tercero'] . ' de la línea ' . $orden . '.';
                }

                $secuencia++;
            }

            $resultado[] = [
                'registro'   => $orden,
                'finca'      => $linea['finca'],
                'lote'       => $linea['lote'],
                'racimos'    => (int) $linea['racimos'],
                'pesoRacimo' => (float) $linea['pesoRacimo'],
                'cantidad'   => $cantidad,
            ];
        }

        return ['lineas' => $resultado, 'trabajadores' => $secuencia];
    }

    /**
     * Consume el consecutivo del tipo de transacción: primero incrementa y
     * después lee, dentro de la transacción ya abierta. El UPDATE deja la fila
     * bloqueada en exclusiva hasta el commit, así que la lectura posterior ve su
     * propio valor y ninguna otra sesión puede tomar el mismo número; si la
     * transacción se revierte, el consecutivo vuelve atrás.
     *
     * No se usan spRetornaConsecutivoTransaccion ni
     * spActualizaConsecutivoTransaccion: entre los dos hacen SELECT y luego
     * UPDATE, que es justo la carrera que hay que evitar con el legado corriendo
     * en paralelo, y el segundo abre y confirma su propia transacción con
     * nombre, lo que rompería la nuestra. El número se arma con la misma fórmula
     * del legado.
     */
    protected function consecutivo(int $empresa): ?string
    {
        $this->db->query(
            'UPDATE gTipoTransaccion SET actual = ISNULL(actual, 0) + 1
              WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, static::TIPO]
        );

        if ($this->db->affectedRows() !== 1) {
            return null;
        }

        $fila = $this->db->query(
            "SELECT TOP 1 actual, longitud, LTRIM(RTRIM(ISNULL(prefijo, ''))) AS prefijo,
                    CASE WHEN ISNULL(numeracion, 0) = 0 THEN 0 ELSE 1 END AS numeracion
               FROM gTipoTransaccion WITH (UPDLOCK, ROWLOCK)
              WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?",
            [$empresa, static::TIPO]
        )->getRowArray();

        if ($fila === null || (int) $fila['numeracion'] !== 1) {
            return null;
        }

        $actual   = (string) (int) $fila['actual'];
        $longitud = (int) $fila['longitud'];

        return $fila['prefijo'] . str_pad($actual, max($longitud, strlen($actual)), '0', STR_PAD_LEFT);
    }

    protected function numeroUsado(int $empresa, string $numero): bool
    {
        return $this->db->query(
            'SELECT TOP 1 numero FROM aTransaccion WHERE empresa = ? AND tipo = ? AND LTRIM(RTRIM(numero)) = ?',
            [$empresa, static::TIPO, $numero]
        )->getRowArray() !== null;
    }

    /** Centro de costo con el que el legado viene registrando los TLC de la empresa. */
    private function ccostoPorDefecto(int $empresa): string
    {
        $fila = $this->db->query(
            "SELECT TOP 1 LTRIM(RTRIM(ccosto)) AS ccosto FROM aTransaccionTercero
              WHERE empresa = ? AND tipo = ? AND ccosto IS NOT NULL AND LTRIM(RTRIM(ccosto)) <> ''
              ORDER BY numero DESC",
            [$empresa, static::TIPO]
        )->getRowArray();

        return $fila === null ? self::CCOSTO_POR_DEFECTO : (string) $fila['ccosto'];
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aTransaccion',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
