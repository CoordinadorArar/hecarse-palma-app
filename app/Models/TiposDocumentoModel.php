<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de los tipos de documento de identificación del legado (dbo.gTipoDocumento):
 * CC, TI, CE, NIT. Es el catálogo que consume cTercero.tipoDocumento.
 *
 * NO confundir con App\Models\TiposDocumentosModel (tabla "TiposDocumentos"), que
 * son los tipos de documento de los USUARIOS de esta aplicación: otra tabla, otro
 * propósito y sin relación entre ambas.
 *
 * Llave primaria (empresa, codigo). No hay ninguna FK declarada hacia esta tabla,
 * así que el uso se verifica contra cTercero por comparación de texto.
 */
class TiposDocumentoModel extends Model
{
    protected $table      = 'gTipoDocumento';
    protected $returnType = 'array';

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

    public function listar(int $empresa): array
    {
        $filas = $this->db->query(
            'SELECT d.empresa,
                    LTRIM(RTRIM(d.codigo)) AS codigo,
                    LTRIM(RTRIM(d.descripcion)) AS descripcion,
                    LTRIM(RTRIM(d.descripcionCorta)) AS descripcionCorta,
                    d.codigoTD,
                    CASE WHEN ISNULL(d.mNit, 0) = 0 THEN 0 ELSE 1 END AS mNit,
                    LTRIM(RTRIM(d.equivalencia)) AS equivalencia,
                    ISNULL(u.usos, 0) AS usos
               FROM gTipoDocumento d
               LEFT JOIN (SELECT LTRIM(RTRIM(tipoDocumento)) AS codigo, COUNT(*) AS usos
                            FROM cTercero
                           WHERE empresa = ?
                           GROUP BY LTRIM(RTRIM(tipoDocumento))) u
                      ON u.codigo = LTRIM(RTRIM(d.codigo))
              WHERE d.empresa = ?
              ORDER BY d.codigo ASC',
            [$empresa, $empresa]
        )->getResultArray();

        foreach ($filas as &$fila) {
            $fila['usos'] = (int) $fila['usos'];
        }

        return $filas;
    }

    public function obtener(int $empresa, string $codigo): ?array
    {
        return $this->db->query(
            'SELECT TOP 1 empresa,
                    LTRIM(RTRIM(codigo)) AS codigo,
                    LTRIM(RTRIM(descripcion)) AS descripcion,
                    LTRIM(RTRIM(descripcionCorta)) AS descripcionCorta,
                    codigoTD,
                    CASE WHEN ISNULL(mNit, 0) = 0 THEN 0 ELSE 1 END AS mNit,
                    LTRIM(RTRIM(equivalencia)) AS equivalencia
               FROM gTipoDocumento
              WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
            [$empresa, $codigo]
        )->getRowArray();
    }

    public function existe(int $empresa, string $codigo): bool
    {
        return $this->obtener($empresa, $codigo) !== null;
    }

    public function crear(
        int $empresa,
        string $codigo,
        string $descripcion,
        string $descripcionCorta,
        int $codigoTD,
        int $mNit,
        ?string $equivalencia,
        ?int $usu_id
    ): bool {
        $this->db->transBegin();

        try {
            $this->db->query(
                'INSERT INTO gTipoDocumento (empresa, codigo, descripcion, descripcionCorta, codigoTD, mNit, equivalencia)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$empresa, $codigo, $descripcion, $descripcionCorta, $codigoTD, $mNit, $equivalencia]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('INSERT', [
                'empresa'          => $empresa,
                'codigo'           => $codigo,
                'descripcion'      => $descripcion,
                'descripcionCorta' => $descripcionCorta,
                'codigoTD'         => $codigoTD,
                'mNit'             => $mNit,
                'equivalencia'     => $equivalencia,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error creando el tipo de documento: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * No toca empresa ni codigo: son la llave primaria.
     */
    public function actualizar(
        int $empresa,
        string $codigo,
        string $descripcion,
        string $descripcionCorta,
        int $codigoTD,
        int $mNit,
        ?string $equivalencia,
        ?int $usu_id
    ): bool {
        $this->db->transBegin();

        try {
            $this->db->query(
                'UPDATE gTipoDocumento
                    SET descripcion = ?, descripcionCorta = ?, codigoTD = ?, mNit = ?, equivalencia = ?
                  WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
                [$descripcion, $descripcionCorta, $codigoTD, $mNit, $equivalencia, $empresa, $codigo]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('UPDATE', [
                'empresa'          => $empresa,
                'codigo'           => $codigo,
                'descripcion'      => $descripcion,
                'descripcionCorta' => $descripcionCorta,
                'codigoTD'         => $codigoTD,
                'mNit'             => $mNit,
                'equivalencia'     => $equivalencia,
            ], $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error actualizando el tipo de documento: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Borrado físico. El asiento de auditoría guarda la fila leída antes de borrarla
     * porque es el único rastro que queda.
     */
    public function eliminar(int $empresa, string $codigo, ?int $usu_id, array $fila = []): bool
    {
        $this->db->transBegin();

        try {
            $this->db->query(
                'DELETE FROM gTipoDocumento WHERE empresa = ? AND LTRIM(RTRIM(codigo)) = ?',
                [$empresa, $codigo]
            );

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $auditoria = $this->insertarAuditoria('DELETE', $fila === [] ? [
                'empresa' => $empresa,
                'codigo'  => $codigo,
            ] : $fila, $usu_id);

            if (! $auditoria || $this->db->transStatus() === false) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Error eliminando el tipo de documento: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Terceros que usan el tipo de documento. cTercero.tipoDocumento es char(3) y
     * viene con relleno de espacios; la llave de cTercero es (empresa, id), por eso
     * el conteo filtra siempre por empresa.
     */
    public function usoEnTerceros(int $empresa, string $codigo): int
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total FROM cTercero WHERE empresa = ? AND LTRIM(RTRIM(tipoDocumento)) = ?',
            [$empresa, $codigo]
        )->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }

    public function insertarAuditoria(string $accion, array $cambios, ?int $usu_id): bool
    {
        return (bool) $this->db->table('Auditoria')->insert([
            'TablaAfectada'     => 'gTipoDocumento',
            'Accion'            => $accion,
            'CambiosRealizados' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'IdUsuario'         => $usu_id,
            'FechaRegistro'     => date('Y-m-d\TH:i:s'),
        ]);
    }
}
