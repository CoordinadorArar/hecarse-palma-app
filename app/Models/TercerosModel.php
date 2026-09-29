<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de terceros (tabla cTercero).
 *
 * La llave primaria es compuesta (empresa, id) y la columna "id" no es
 * identity, por lo que las operaciones se resuelven con SQL crudo y el
 * consecutivo se calcula dentro de la transacción que hace el INSERT.
 */
class TercerosModel extends Model
{
    protected $table      = 'cTercero';
    protected $returnType = 'array';

    private const PESOS_DV = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];

    /** Columnas bit que representan un rol del tercero. */
    public const ROLES = [
        'cliente'          => 'Cliente',
        'proveedor'        => 'Proveedor',
        'empleado'         => 'Empleado',
        'accionista'       => 'Accionista',
        'contratista'      => 'Contratista',
        'extractora'       => 'Extractora',
        'comercializadora' => 'Comercializadora',
    ];

    /** Tablas de nómina que referencian la PK (empresa, id) del tercero. */
    private const DEPENDENCIAS = [
        'nEntidadAfc'                => 'tercero',
        'nEntidadFondo'              => 'tercero',
        'nEntidadFondoPension'       => 'tercero',
        'nEntidadIcbf'               => 'tercero',
        'nEntidadSena'               => 'tercero',
        'nLiquidacionNominaDetalle'  => 'tercero',
        'nPagosNominaDetalle'        => 'tercero',
        'nVacaciones'                => 'empleado',
        'nVacacionesDetalle'         => 'empleado',
    ];

    private const CAMPOS = 'empresa, id, codigo, tipoDocumento, tipo, nit, dv, razonSocial, apellido1, apellido2,
        nombre1, nombre2, descripcion, activo, ciudad, cliente, proveedor, empleado, accionista, contratista,
        extractora, comercializadora, contacto, fechaRegistro, telefono, direccion, barrio, fax, email,
        departamento, codigoEquivalencia, foto';

    /**
     * Empresas activas disponibles para el selector.
     */
    public function getEmpresas(): array
    {
        return $this->db->table('gEmpresa')
            ->select('id, razonSocial')
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Catálogo de tipos de documento, ya sin el relleno del char(3).
     */
    public function getTiposDocumento(): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT codigo, descripcion, descripcionCorta FROM gTipoDocumento ORDER BY descripcion ASC'
        )->getResultArray();

        return array_map(static fn ($f) => [
            'codigo'           => trim((string) $f['codigo']),
            'descripcion'      => (string) $f['descripcion'],
            'descripcionCorta' => trim((string) $f['descripcionCorta']),
        ], $filas);
    }

    public function getDepartamentos(): array
    {
        return $this->db->query(
            'SELECT DISTINCT codigo, descripcion FROM gDepartamento ORDER BY descripcion ASC'
        )->getResultArray();
    }

    public function empresaExiste(int $empresa): bool
    {
        return $this->db->table('gEmpresa')->where('id', $empresa)->countAllResults() > 0;
    }

    public function tipoDocumentoExiste(string $codigo): bool
    {
        $fila = $this->db->query(
            'SELECT TOP 1 codigo FROM gTipoDocumento WHERE LTRIM(RTRIM(codigo)) = ?',
            [$codigo]
        )->getRowArray();

        return $fila !== null;
    }

    /**
     * Lista los terceros de una empresa aplicando los filtros de la pantalla.
     *
     * @param string $rol    Columna bit a filtrar (lista blanca) o cadena vacía.
     * @param string $estado '1', '0' o cadena vacía para todos.
     */
    public function listar(int $empresa, string $tipo = '', string $rol = '', string $estado = '', string $busqueda = ''): array
    {
        $sql   = 'SELECT ' . self::CAMPOS . ' FROM cTercero WHERE empresa = ?';
        $binds = [$empresa];

        if ($tipo === '1' || $tipo === '2') {
            $sql .= ' AND tipo = ?';
            $binds[] = (int) $tipo;
        }

        if ($rol !== '' && isset(self::ROLES[$rol])) {
            $sql .= ' AND ' . $rol . ' = 1';
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND activo = ?';
            $binds[] = (int) $estado;
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= ' AND (nit LIKE ? OR codigo LIKE ? OR descripcion LIKE ? OR razonSocial LIKE ?'
                . ' OR apellido1 LIKE ? OR apellido2 LIKE ? OR nombre1 LIKE ? OR nombre2 LIKE ?)';

            for ($i = 0; $i < 8; $i++) {
                $binds[] = '%' . $busqueda . '%';
            }
        }

        $sql .= ' ORDER BY descripcion ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $id): ?array
    {
        return $this->db->query(
            'SELECT ' . self::CAMPOS . ' FROM cTercero WHERE empresa = ? AND id = ?',
            [$empresa, $id]
        )->getRowArray();
    }

    /**
     * Nombre de la ciudad y de su departamento, para poblar el typeahead.
     */
    public function nombreCiudad(int $empresa, ?string $ciudad): array
    {
        $ciudad = trim((string) $ciudad);

        if ($ciudad === '') {
            return ['nombreCiudad' => '', 'nombreDepartamento' => ''];
        }

        $fila = $this->db->query(
            'SELECT TOP 1 c.nombre, d.descripcion
             FROM gCiudad c
             LEFT JOIN gDepartamento d ON d.empresa = c.empresa AND d.codigo = c.departamento
             WHERE c.empresa = ? AND c.codigo = ?',
            [$empresa, $ciudad]
        )->getRowArray();

        return [
            'nombreCiudad'       => (string) ($fila['nombre'] ?? ''),
            'nombreDepartamento' => (string) ($fila['descripcion'] ?? ''),
        ];
    }

    /**
     * Nombres de ciudad indexados por código, para la columna Descripción.
     */
    public function nombresCiudades(int $empresa, array $codigos): array
    {
        $codigos = array_map(static fn ($c) => trim((string) $c), $codigos);
        $codigos = array_values(array_unique(array_filter($codigos, static fn ($c) => $c !== '')));

        if ($codigos === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($codigos), '?'));
        $filas  = $this->db->query(
            "SELECT codigo, nombre FROM gCiudad WHERE empresa = ? AND codigo IN ({$marcas})",
            array_merge([$empresa], $codigos)
        )->getResultArray();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[trim((string) $f['codigo'])] = (string) $f['nombre'];
        }

        return $mapa;
    }

    /**
     * Busca ciudades por nombre. Devuelve hasta el tope + 1 para saber si hay más.
     */
    public function buscarCiudades(int $empresa, string $termino, string $departamento = '', int $tope = 15): array
    {
        $sql   = 'SELECT TOP ' . ($tope + 1) . ' c.codigo, c.nombre, c.departamento, d.descripcion AS nombreDepartamento
                  FROM gCiudad c
                  LEFT JOIN gDepartamento d ON d.empresa = c.empresa AND d.codigo = c.departamento
                  WHERE c.empresa = ? AND c.nombre LIKE ?';
        $binds = [$empresa, '%' . $termino . '%'];

        if ($departamento !== '') {
            $sql .= ' AND c.departamento = ?';
            $binds[] = $departamento;
        }

        $sql .= ' ORDER BY c.nombre ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    /**
     * Consecutivo disponible para la empresa indicada.
     *
     * @param bool $bloquear Toma UPDLOCK/HOLDLOCK; solo dentro de la transacción de alta.
     */
    public function siguienteId(int $empresa, bool $bloquear = false): int
    {
        $fila = $this->db->query(
            'SELECT ISNULL(MAX(id), 0) + 1 AS siguiente FROM cTercero'
            . ($bloquear ? ' WITH (UPDLOCK, HOLDLOCK)' : '') . ' WHERE empresa = ?',
            [$empresa]
        )->getRowArray();

        return (int) ($fila['siguiente'] ?? 1);
    }

    /**
     * Indica si el nit ya está registrado en la empresa, excluyendo un id.
     */
    public function nitDuplicado(int $empresa, string $nit, ?int $excluirId = null): bool
    {
        $sql   = 'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND nit = ?';
        $binds = [$empresa, $nit];

        if ($excluirId !== null) {
            $sql .= ' AND id <> ?';
            $binds[] = $excluirId;
        }

        return $this->db->query($sql, $binds)->getRowArray() !== null;
    }

    public function codigoDuplicado(int $empresa, string $codigo, ?int $excluirId = null): bool
    {
        $sql   = 'SELECT TOP 1 id FROM cTercero WHERE empresa = ? AND codigo = ?';
        $binds = [$empresa, $codigo];

        if ($excluirId !== null) {
            $sql .= ' AND id <> ?';
            $binds[] = $excluirId;
        }

        return $this->db->query($sql, $binds)->getRowArray() !== null;
    }

    /**
     * Inserta el tercero calculando su consecutivo dentro de la transacción.
     *
     * @return int|null Id asignado, o null si la transacción falló.
     */
    public function crear(int $empresa, array $data, ?int $usu_id): ?int
    {
        $this->db->transStart();

        $id = $this->siguienteId($empresa, true);

        $columnas = array_keys($data);
        $marcas   = implode(', ', array_fill(0, count($columnas) + 3, '?'));

        $ok = $this->db->query(
            'INSERT INTO cTercero (empresa, id, fechaRegistro, ' . implode(', ', $columnas) . ')
             VALUES (' . $marcas . ')',
            array_merge([$empresa, $id, date('Y-m-d\TH:i:s')], array_values($data))
        ) !== false;

        if ($ok) {
            $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'id' => $id], $data), $usu_id);
        }

        $this->db->transComplete();

        return ($ok && $this->db->transStatus() !== false) ? $id : null;
    }

    /**
     * @return bool false si el UPDATE no se pudo ejecutar o la transacción falló.
     */
    public function actualizar(int $empresa, int $id, array $data, ?int $usu_id): bool
    {
        $asignaciones = implode(' = ?, ', array_keys($data)) . ' = ?';

        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE cTercero SET ' . $asignaciones . ' WHERE empresa = ? AND id = ?',
            array_merge(array_values($data), [$empresa, $id])
        ) !== false;

        if ($ok) {
            $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'id' => $id], $data), $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * Cambia únicamente el estado del tercero, sin revalidar el resto de columnas.
     */
    public function cambiarEstado(int $empresa, int $id, int $activo, ?int $usu_id): bool
    {
        $this->db->transStart();

        $ok = $this->db->query(
            'UPDATE cTercero SET activo = ? WHERE empresa = ? AND id = ?',
            [$activo, $empresa, $id]
        ) !== false;

        if ($ok) {
            $this->insertarAuditoria('UPDATE', ['empresa' => $empresa, 'id' => $id, 'activo' => $activo], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * @return bool false si el DELETE no se pudo ejecutar o la transacción falló.
     */
    public function eliminar(int $empresa, int $id, ?int $usu_id): bool
    {
        $this->db->transStart();

        $ok = $this->db->query('DELETE FROM cTercero WHERE empresa = ? AND id = ?', [$empresa, $id]) !== false;

        if ($ok) {
            $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'id' => $id], $usu_id);
        }

        $this->db->transComplete();

        return $ok && $this->db->transStatus() !== false;
    }

    /**
     * Indica si el tercero está referenciado desde los módulos de nómina.
     */
    public function tieneDependencias(int $empresa, int $id, ?string $codigo): bool
    {
        $codigo = trim((string) $codigo);

        if ($codigo !== '') {
            $fila = $this->db->query(
                'SELECT TOP 1 1 AS existe FROM nFuncionario WHERE proveedor = ?',
                [$codigo]
            )->getRowArray();

            if ($fila !== null) {
                return true;
            }
        }

        foreach (self::DEPENDENCIAS as $tabla => $columna) {
            $fila = $this->db->query(
                "SELECT TOP 1 1 AS existe FROM {$tabla} WHERE empresa = ? AND {$columna} = ?",
                [$empresa, $id]
            )->getRowArray();

            if ($fila !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dígito de verificación DIAN. Copia del algoritmo de EmpresasModel.
     */
    public function calcularDV(string $nit): int
    {
        $nit     = preg_replace('/\D/', '', $nit);
        $nit     = str_pad($nit, 15, '0', STR_PAD_LEFT);
        $digitos = str_split($nit);

        $suma = 0;
        foreach ($digitos as $i => $digito) {
            $suma += ((int) $digito) * self::PESOS_DV[$i];
        }

        $residuo = $suma % 11;

        return ($residuo === 0 || $residuo === 1) ? $residuo : 11 - $residuo;
    }

    /**
     * Registra un asiento de auditoría sobre la tabla cTercero.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'cTercero',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
