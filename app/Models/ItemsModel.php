<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para la administración de los ítems que se consumen en las labores (tabla iItems).
 *
 * La llave primaria es compuesta (empresa, codigo), con codigo entero, por lo que las
 * operaciones se resuelven con SQL crudo. El formulario tiene un único selector "Unidad medida"
 * que escribe tanto uMedidaCompra como uMedidaConsumo con el mismo valor. Columnas NOT NULL no
 * administradas (tipo, compras, ventas, manejaIR, tiempoReposicion, minimo, maximo) se fijan en
 * sus constantes al crear y no se tocan al actualizar. usuarioRegistro/fechaRegistro se preservan
 * en el UPDATE; usuarioActualiza/fechaActualiza (nullables) sí se escriben en cada actualización.
 */
class ItemsModel extends Model
{
    protected $table      = 'iItems';
    protected $returnType = 'array';

    private const CAMPOS = 'i.empresa, i.codigo, i.descripcion, i.descripcionAbreviada, i.referencia,
        i.uMedidaCompra AS uMedida, i.notas, i.activo, i.usuarioRegistro, i.fechaRegistro,
        i.usuarioActualiza, i.fechaActualiza';

    private const CAMPOS_CATALOGOS = ', um.descripcion AS uMedidaDescripcion';

    private const JOIN_CATALOGOS = ' LEFT JOIN gUnidadMedida um ON um.empresa = i.empresa AND RTRIM(um.codigo) = RTRIM(i.uMedidaCompra)';

    /** Tablas grandes que referencian el ítem por la columna "item" y bloquean el borrado. */
    private const TABLAS_USO_INT     = ['bRegistroBascula', 'atransaccionItemSaldo'];
    private const TABLAS_USO_VARCHAR = ['aTransaccionItem', 'aSanidadDetalle'];

    /**
     * Subconsulta única con el total de usos por (empresa, item), agregado de una sola pasada por
     * tabla en vez de una subconsulta correlacionada por cada fila de iItems. Las tablas con "item"
     * varchar se convierten con TRY_CAST (no CAST): un valor no numérico ajeno a esta pantalla
     * (por ejemplo, en aTransaccionItem, alimentada por las transacciones) no debe reventar el
     * listado completo; simplemente no casa con ningún item, que es el comportamiento correcto.
     */
    private function sqlUsosAgregados(): string
    {
        $partes = [];

        foreach (self::TABLAS_USO_INT as $tabla) {
            $partes[] = "SELECT empresa, item, COUNT(*) AS n FROM {$tabla}"
                . ' WHERE empresa = ? AND item IS NOT NULL GROUP BY empresa, item';
        }

        foreach (self::TABLAS_USO_VARCHAR as $tabla) {
            $partes[] = "SELECT empresa, TRY_CAST(item AS int) AS item, COUNT(*) AS n FROM {$tabla}"
                . ' WHERE empresa = ? AND item IS NOT NULL AND TRY_CAST(item AS int) IS NOT NULL GROUP BY empresa, TRY_CAST(item AS int)';
        }

        return '(SELECT empresa, item, SUM(n) AS usos FROM (' . implode(' UNION ALL ', $partes) . ') u GROUP BY empresa, item)';
    }

    /** Binds de sqlUsosAgregados(), en el mismo orden en que aparecen sus placeholders. */
    private function usosBinds(int $empresa): array
    {
        return array_fill(0, count(self::TABLAS_USO_INT) + count(self::TABLAS_USO_VARCHAR), $empresa);
    }

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

    /** "desCorta" es char(3) con relleno de espacios ('Gr ', 'ha ', 'Kg '): se devuelve con RTRIM. */
    public function getUnidadesMedida(int $empresa): array
    {
        return $this->db->query(
            'SELECT RTRIM(codigo) AS codigo, descripcion, RTRIM(desCorta) AS desCorta FROM gUnidadMedida WHERE empresa = ? ORDER BY codigo ASC',
            [$empresa]
        )->getResultArray();
    }

    public function uMedidaValida(int $empresa, string $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM gUnidadMedida WHERE empresa = ? AND RTRIM(codigo) = ?',
            [$empresa, trim($codigo)]
        )->getRowArray() !== null;
    }

    /**
     * Lista los ítems de una empresa con la descripción de su unidad de medida y el número de
     * registros que los usan.
     *
     * @param string $estado   '1', '0' o cadena vacía para todas.
     * @param string $busqueda Término libre contra código, descripción, abreviada y referencia.
     */
    public function listar(int $empresa, string $estado = '', string $busqueda = ''): array
    {
        $busqueda = trim($busqueda);

        $sql = 'SELECT ' . self::CAMPOS . self::CAMPOS_CATALOGOS . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM iItems i' . self::JOIN_CATALOGOS
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = i.empresa AND u.item = i.codigo'
            . ' WHERE i.empresa = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;

        if ($estado === '1' || $estado === '0') {
            $sql .= ' AND i.activo = ?';
            $binds[] = (int) $estado;
        }

        if ($busqueda !== '') {
            $comodin = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
            $sql .= " AND (CAST(i.codigo AS varchar(50)) LIKE ? ESCAPE '\\' OR i.descripcion LIKE ? ESCAPE '\\'"
                . " OR i.descripcionAbreviada LIKE ? ESCAPE '\\' OR i.referencia LIKE ? ESCAPE '\\')";
            $binds[] = $comodin;
            $binds[] = $comodin;
            $binds[] = $comodin;
            $binds[] = $comodin;
        }

        $sql .= ' ORDER BY i.codigo ASC';

        return $this->db->query($sql, $binds)->getResultArray();
    }

    public function obtener(int $empresa, int $codigo): ?array
    {
        $sql = 'SELECT ' . self::CAMPOS . self::CAMPOS_CATALOGOS . ', ISNULL(u.usos, 0) AS usos'
            . ' FROM iItems i' . self::JOIN_CATALOGOS
            . ' LEFT JOIN ' . $this->sqlUsosAgregados() . ' u ON u.empresa = i.empresa AND u.item = i.codigo'
            . ' WHERE i.empresa = ? AND i.codigo = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $codigo;

        return $this->db->query($sql, $binds)->getRowArray();
    }

    public function existe(int $empresa, int $codigo): bool
    {
        return $this->db->query(
            'SELECT TOP 1 codigo FROM iItems WHERE empresa = ? AND codigo = ?',
            [$empresa, $codigo]
        )->getRowArray() !== null;
    }

    /**
     * Consecutivo sugerido: el mayor código existente más uno. Devuelve 1 si no hay filas.
     */
    public function siguienteCodigo(int $empresa): int
    {
        $fila = $this->db->query(
            'SELECT ISNULL(MAX(codigo), 0) + 1 AS siguiente FROM iItems WHERE empresa = ?',
            [$empresa]
        )->getRowArray();

        return (int) ($fila['siguiente'] ?? 1);
    }

    public function crear(int $empresa, int $codigo, array $data, ?int $usu_id, string $usuario): void
    {
        $hoy = date('Y-m-d H:i:s');

        $this->db->query(
            'INSERT INTO iItems (empresa, codigo, descripcion, descripcionAbreviada, referencia,
                manejaIR, tipo, compras, ventas, uMedidaCompra, uMedidaConsumo, tiempoReposicion,
                minimo, maximo, notas, activo, usuarioRegistro, fechaRegistro)
             VALUES (?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, 0, 0, 0, ?, ?, ?, ?)',
            [
                $empresa, $codigo, $data['descripcion'], $data['descripcionAbreviada'], $data['referencia'],
                'FI', $data['uMedida'], $data['uMedida'], $data['notas'], $data['activo'], $usuario, $hoy,
            ]
        );

        $this->insertarAuditoria('INSERT', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function actualizar(int $empresa, int $codigo, array $data, ?int $usu_id, string $usuario): void
    {
        $hoy = date('Y-m-d H:i:s');

        $this->db->query(
            'UPDATE iItems SET descripcion = ?, descripcionAbreviada = ?, referencia = ?, uMedidaCompra = ?,
                uMedidaConsumo = ?, notas = ?, activo = ?, usuarioActualiza = ?, fechaActualiza = ?
             WHERE empresa = ? AND codigo = ?',
            [
                $data['descripcion'], $data['descripcionAbreviada'], $data['referencia'], $data['uMedida'],
                $data['uMedida'], $data['notas'], $data['activo'], $usuario, $hoy,
                $empresa, $codigo,
            ]
        );

        $this->insertarAuditoria('UPDATE', array_merge(['empresa' => $empresa, 'codigo' => $codigo], $data), $usu_id);
    }

    public function eliminar(int $empresa, int $codigo, ?int $usu_id): void
    {
        $this->db->query('DELETE FROM iItems WHERE empresa = ? AND codigo = ?', [$empresa, $codigo]);

        $this->insertarAuditoria('DELETE', ['empresa' => $empresa, 'codigo' => $codigo], $usu_id);
    }

    public function cambiarEstado(int $empresa, int $codigo, int $activo, ?int $usu_id, string $usuario): void
    {
        $hoy = date('Y-m-d H:i:s');

        $this->db->query(
            'UPDATE iItems SET activo = ?, usuarioActualiza = ?, fechaActualiza = ? WHERE empresa = ? AND codigo = ?',
            [$activo, $usuario, $hoy, $empresa, $codigo]
        );

        $this->insertarAuditoria('UPDATE', [
            'empresa' => $empresa,
            'codigo'  => $codigo,
            'activo'  => $activo,
        ], $usu_id);
    }

    /**
     * Cantidad de registros que usan el ítem, con el mismo criterio que listar()/obtener().
     */
    public function enUso(int $empresa, int $codigo): int
    {
        $sql = 'SELECT ISNULL(usos, 0) AS total FROM ' . $this->sqlUsosAgregados()
            . ' u WHERE u.empresa = ? AND u.item = ?';
        $binds   = $this->usosBinds($empresa);
        $binds[] = $empresa;
        $binds[] = $codigo;

        $fila = $this->db->query($sql, $binds)->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Registra un asiento de auditoría sobre la tabla iItems.
     */
    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): void
    {
        $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'iItems',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
