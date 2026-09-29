<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de las labores de campo (tabla aNovedad).
 *
 * La llave primaria es compuesta (empresa, codigo, concepto): el concepto de nómina forma parte
 * de la llave, así que codigo y concepto nunca se modifican en el UPDATE. Las columnas "añoDesde"
 * y "añoHasta" llevan eñe y se escriben siempre entre corchetes, igual que "[añoSiembra]" en Lotes.
 * "muestraInforme" y "muestraInformeContratista" son nullables y no se administran: se leen como 0
 * pero no se tocan al escribir. "calculaJornal" también es nullable pero sí está en el formulario.
 * "porHaNeta"/"porHaBruta"/"porHaProduccion" son mutuamente excluyentes: el formulario los trata
 * como un único selector "Tipo Aplicación" (ver LaboresController::TIPOS_APLICACION).
 */
class LaboresModel extends Model
{
    protected $table      = 'aNovedad';
    protected $returnType = 'array';

    private const CAMPOS = 'n.empresa, n.codigo, n.descripcion, n.desCorta, n.grupo, n.uMedida, n.concepto,
        n.equivalencia, n.ciclos, n.tarea, n.naturaleza, n.claseLabor,
        n.porHaNeta, n.porHaBruta, n.porHaProduccion,
        n.manejaRango, n.[añoDesde] AS anioDesde, n.[añoHasta] AS anioHasta,
        n.impuesto, n.grupoIR, n.manejaCanal, n.tipoCanal,
        n.manejaLote, n.manejaSaldo, n.manejaLinea, n.manejaPalma, n.manejaRacimo, n.manejaJornal, n.manejaBascula,
        n.manejaFecha, n.manejaDecimal, n.manejaCaracteristica, n.noPrestacional, n.activo,
        ISNULL(n.muestraInforme, 0) AS muestraInforme, ISNULL(n.muestraInformeContratista, 0) AS muestraInformeContratista,
        ISNULL(n.calculaJornal, 0) AS calculaJornal, n.fechaRegistro, n.usuario';

    private const CAMPOS_CATALOGOS = ', g.descripcion AS grupoDescripcion, um.descripcion AS uMedidaDescripcion,
        c.descripcion AS conceptoDescripcion, tc.descripcion AS tipoCanalDescripcion';

    private const JOIN_CATALOGOS = ' LEFT JOIN aGrupoNovedad g ON g.empresa = n.empresa AND RTRIM(g.codigo) = RTRIM(n.grupo)
        LEFT JOIN gUnidadMedida um ON um.empresa = n.empresa AND RTRIM(um.codigo) = RTRIM(n.uMedida)
        LEFT JOIN nConcepto c ON c.empresa = n.empresa AND RTRIM(c.codigo) = RTRIM(n.concepto)
        LEFT JOIN aTipoCanal tc ON tc.empresa = n.empresa AND RTRIM(tc.codigo) = RTRIM(n.tipoCanal)';

    /** Tablas grandes sin índice por "novedad" que referencian la labor y bloquean el borrado. */
    private const TABLAS_USO_VARCHAR = [
        'aTransaccionTercero',
        'aTransaccionNovedad',
        'aTransaccionItem',
        'tmpLiquidacionPepa',
        'aNovedadLotePrecio',
    ];

    /**
     * Subconsulta única con el total de usos por (empresa, novedad), agregado de una sola pasada
     * por tabla en vez de una subconsulta correlacionada por cada una de las 144 filas de aNovedad.
     * aTipoNovedad guarda "novedad" como int, así que se castea a varchar para unirla a las demás.
     */
    private function sqlUsosAgregados(): string
    {
        $partes = [];

        foreach (self::TABLAS_USO_VARCHAR as $tabla) {
            $partes[] = "SELECT empresa, novedad, COUNT(*) AS n FROM {$tabla}"
                . ' WHERE empresa = ? AND novedad IS NOT NULL AND LTRIM(RTRIM(novedad)) <> \'\''
                . ' GROUP BY empresa, novedad';
        }

        $partes[] = 'SELECT empresa, CAST(novedad AS varchar(50)) AS novedad, COUNT(*) AS n FROM aTipoNovedad'
            . ' WHERE empresa = ? GROUP BY empresa, novedad';

        return '(SELECT empresa, novedad, SUM(n) AS usos FROM (' . implode(' UNION ALL ', $partes) . ') u GROUP BY empresa, novedad)';
    }

    /** Binds de sqlUsosAgregados(), en el mismo orden en que aparecen sus placeholders. */
    private function usosBinds(int $empresa): array
    {
        return array_fill(0, count(self::TABLAS_USO_VARCHAR) + 1, $empresa);
    }

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
        return $this->db->table('gEmpresa')->where('id', $empresa)->countAllResults() > 0;
    }

    public function getGrupos(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM aGrupoNovedad WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    /** "desCorta" es char(3) con relleno de espacios ('Gr ', 'ha ', 'Kg '): se devuelve con RTRIM. */
    public function getUnidadesMedida(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion, RTRIM(desCorta) AS desCorta FROM gUnidadMedida WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getConceptos(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM nConcepto WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function getTiposCanal(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion FROM aTipoCanal WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    // ── Validadores ──────────────────────────────────────────────────────────

    public function grupoValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aGrupoNovedad WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function uMedidaValida(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM gUnidadMedida WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function conceptoValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM nConcepto WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    public function tipoCanalValido(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aTipoCanal WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    // ── Maestro ──────────────────────────────────────────────────────────────

    /**
     * Lista las labores de una empresa con el nombre de sus catálogos y el número de registros
     * que las usan.
     *
     * @param string $grupo    Código de grupo de labor o cadena vacía para todos.
     * @param string $estado   '1', '0' o cadena vacía para todas.
     * @param string $busqueda Término libre contra código, descripción y descripción corta, o
     *                         cadena vacía para no filtrar. Va dentro de la misma consulta (con su
     *                         propio agregado de uso en una sola pasada), sin añadir otra pasada.
     */
    public function listar(int $empresa, string $grupo = '', string $estado = '', string $busqueda = ''): array
    {
        $grupo    = trim($grupo);
        $busqueda = trim($busqueda);

        $sql = 'SELECT ' . self::CAMPOS . self::CAMPOS_CATALOGOS . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM aNovedad n' . self::JOIN_CATALOGOS
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = n.empresa AND u.novedad = n.codigo'
            . ' WHERE n.empresa = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;

        if ($grupo !== '') {
            $sql .= ' AND RTRIM(n.grupo) = ?';
            $binds[] = $grupo;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND n.activo = ?';
            $binds[] = (int) $estado;
        }

        if ($busqueda !== '') {
            $comodin = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
            $sql .= " AND (n.codigo LIKE ? ESCAPE '\\' OR n.descripcion LIKE ? ESCAPE '\\' OR n.desCorta LIKE ? ESCAPE '\\')";
            $binds[] = $comodin;
            $binds[] = $comodin;
            $binds[] = $comodin;
        }

        $sql .= ' ORDER BY n.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, string $codigo, string $concepto): ?array
    {
        $codigo   = trim($codigo);
        $concepto = trim($concepto);

        $sql = 'SELECT ' . self::CAMPOS . self::CAMPOS_CATALOGOS . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM aNovedad n' . self::JOIN_CATALOGOS
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = n.empresa AND u.novedad = n.codigo'
            . ' WHERE n.empresa = ? AND n.codigo = ? AND n.concepto = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $codigo;
        $binds[] = $concepto;

        return $this->db->query($sql, $binds)->getRowArray();
    }

    public function existe(int $empresa, string $codigo, string $concepto): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM aNovedad WHERE empresa = ? AND codigo = ? AND concepto = ?',
            [$empresa, trim($codigo), trim($concepto)]
        )->getRowArray() !== null;
    }

    public function crear(int $empresa, string $codigo, string $concepto, array $data, ?int $usu_id, string $usuario): void
    {
        $codigo   = trim($codigo);
        $concepto = trim($concepto);
        $hoy      = date('Y-m-d');

        $this->db->query(
            'INSERT INTO aNovedad (empresa, codigo, descripcion, desCorta, grupo, uMedida, concepto, equivalencia,
                ciclos, tarea, naturaleza, claseLabor, porHaNeta, porHaBruta, porHaProduccion,
                manejaRango, [añoDesde], [añoHasta], impuesto, grupoIR, manejaCanal, tipoCanal,
                manejaLote, manejaSaldo, manejaLinea, manejaPalma, manejaRacimo, manejaJornal, manejaBascula,
                manejaFecha, manejaDecimal, manejaCaracteristica, noPrestacional, activo, calculaJornal,
                fechaRegistro, usuario)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresa, $codigo, $data['descripcion'], $data['desCorta'], $data['grupo'], $data['uMedida'],
                $concepto, $data['equivalencia'], $data['ciclos'], $data['tarea'], $data['naturaleza'], $data['claseLabor'],
                $data['porHaNeta'], $data['porHaBruta'], $data['porHaProduccion'],
                $data['manejaRango'], $data['anioDesde'], $data['anioHasta'], $data['impuesto'], $data['grupoIR'],
                $data['manejaCanal'], $data['tipoCanal'],
                $data['manejaLote'], $data['manejaSaldo'], $data['manejaLinea'], $data['manejaPalma'],
                $data['manejaRacimo'], $data['manejaJornal'], $data['manejaBascula'],
                $data['manejaFecha'], $data['manejaDecimal'], $data['manejaCaracteristica'], $data['noPrestacional'],
                $data['activo'], $data['calculaJornal'], $hoy, $usuario,
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo, 'concepto' => $concepto], $data), $usu_id);
    }

    /**
     * No toca codigo, concepto (llave), fechaRegistro, usuario, muestraInforme ni
     * muestraInformeContratista: fuera de alcance, así conservan su valor sin necesidad de leerlo antes.
     */
    public function actualizar(int $empresa, string $codigo, string $concepto, array $data, ?int $usu_id): void
    {
        $codigo   = trim($codigo);
        $concepto = trim($concepto);

        $this->db->query(
            'UPDATE aNovedad SET descripcion = ?, desCorta = ?, grupo = ?, uMedida = ?, equivalencia = ?,
                ciclos = ?, tarea = ?, naturaleza = ?, claseLabor = ?, porHaNeta = ?, porHaBruta = ?, porHaProduccion = ?,
                manejaRango = ?, [añoDesde] = ?, [añoHasta] = ?, impuesto = ?, grupoIR = ?, manejaCanal = ?, tipoCanal = ?,
                manejaLote = ?, manejaSaldo = ?, manejaLinea = ?, manejaPalma = ?, manejaRacimo = ?, manejaJornal = ?,
                manejaBascula = ?, manejaFecha = ?, manejaDecimal = ?, manejaCaracteristica = ?, noPrestacional = ?,
                activo = ?, calculaJornal = ?
             WHERE empresa = ? AND codigo = ? AND concepto = ?',
            [
                $data['descripcion'], $data['desCorta'], $data['grupo'], $data['uMedida'], $data['equivalencia'],
                $data['ciclos'], $data['tarea'], $data['naturaleza'], $data['claseLabor'],
                $data['porHaNeta'], $data['porHaBruta'], $data['porHaProduccion'],
                $data['manejaRango'], $data['anioDesde'], $data['anioHasta'], $data['impuesto'], $data['grupoIR'],
                $data['manejaCanal'], $data['tipoCanal'],
                $data['manejaLote'], $data['manejaSaldo'], $data['manejaLinea'], $data['manejaPalma'],
                $data['manejaRacimo'], $data['manejaJornal'], $data['manejaBascula'],
                $data['manejaFecha'], $data['manejaDecimal'], $data['manejaCaracteristica'], $data['noPrestacional'],
                $data['activo'], $data['calculaJornal'],
                $empresa, $codigo, $concepto,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo, 'concepto' => $concepto], $data), $usu_id);
    }

    public function eliminar(int $empresa, string $codigo, string $concepto, ?int $usu_id): void
    {
        $codigo   = trim($codigo);
        $concepto = trim($concepto);

        $this->db->query('DELETE FROM aNovedad WHERE empresa = ? AND codigo = ? AND concepto = ?', [$empresa, $codigo, $concepto]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo, 'concepto' => $concepto], $usu_id);
    }

    public function cambiarEstado(int $empresa, string $codigo, string $concepto, int $activo, ?int $usu_id): void
    {
        $codigo   = trim($codigo);
        $concepto = trim($concepto);

        $this->db->query(
            'UPDATE aNovedad SET activo = ? WHERE empresa = ? AND codigo = ? AND concepto = ?',
            [$activo, $empresa, $codigo, $concepto]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa'  => $empresa,
            'codigo'   => $codigo,
            'concepto' => $concepto,
            'activo'   => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan la labor, con el mismo criterio que listar()/obtener().
     */
    public function enUso(int $empresa, string $codigo): int
    {
        $codigo = trim($codigo);

        $sql = 'SELECT ISNULL(usos, 0) AS total FROM ' . $this->sqlUsosAgregados()
            . ' u WHERE u.empresa = ? AND u.novedad = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $codigo;

        $fila = $this->db->query($sql, $binds)->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla aNovedad.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'aNovedad',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
