<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $returnType = 'array';

    public function getDepartamentos()
    {
        $builder = $this->db->query("SELECT * FROM UNOEEARAR..t012_mm_deptos WHERE f012_id_pais = 169");

        return $builder->getResultArray();
    }

    public function getCiudades($id_departamento)
    {
        $builder = $this->db->query("SELECT * FROM UNOEEARAR..t013_mm_ciudades WHERE f013_id_depto = '$id_departamento'");

        return $builder->getResultArray();
    }

    public function resumen(): array
    {
        $resumen = ['kpis' => [], 'usuariosPorRol' => [], 'modulosPorLoseta' => [], 'sesionesPorDia' => [], 'ultimosAccesos' => [], 'rolesSinUsuarios' => [], 'modulosSinRol' => [], 'avisos' => [], 'fallos' => []];

        $etiquetas = ['kpis' => 'indicadores generales', 'usuariosPorRol' => 'usuarios por rol', 'modulosPorLoseta' => 'módulos por loseta', 'sesionesPorDia' => 'sesiones por día', 'ultimosAccesos' => 'últimos accesos', 'rolesSinUsuarios' => 'roles sin usuarios', 'modulosSinRol' => 'módulos sin rol'];

        $desde30 = date('Ymd', strtotime('-29 days'));
        $desde7 = date('Ymd', strtotime('-6 days'));

        $consultas = [
            'kpis' => function () use ($desde30, $desde7) {
                $row = $this->db->query("SELECT
                    (SELECT COUNT(*) FROM Usuarios WHERE FechaFinalizacion IS NULL) AS usuariosActivos,
                    (SELECT COUNT(*) FROM Usuarios WHERE FechaFinalizacion IS NOT NULL) AS usuariosInactivos,
                    (SELECT COUNT(*) FROM Usuarios u WHERE u.FechaFinalizacion IS NULL AND NOT EXISTS (SELECT 1 FROM UsuariosRoles ur JOIN Roles r ON r.Id = ur.IdRol AND r.FechaFinalizacion IS NULL WHERE ur.IdUsuario = u.Id AND ur.FechaFinalizacion IS NULL)) AS usuariosSinRol,
                    (SELECT COUNT(*) FROM Roles WHERE FechaFinalizacion IS NULL) AS rolesActivos,
                    (SELECT COUNT(*) FROM Modulos WHERE FechaFinalizacion IS NULL AND IdLoseta IS NULL) AS losetasActivas,
                    (SELECT COUNT(*) FROM Modulos WHERE FechaFinalizacion IS NULL AND IdLoseta IS NOT NULL AND IdModulo IS NULL) AS modulosActivos,
                    (SELECT COUNT(*) FROM Modulos WHERE FechaFinalizacion IS NULL AND IdModulo IS NOT NULL) AS submodulosActivos,
                    (SELECT COUNT(*) FROM ModulosAcciones WHERE FechaFinalizacion IS NULL) AS accionesActivas,
                    (SELECT COUNT(*) FROM ModulosTabs WHERE FechaFinalizacion IS NULL) AS tabsActivos,
                    (SELECT COUNT(*) FROM AuditoriaSesiones WHERE FechaInicio >= ?) AS sesiones30d,
                    (SELECT COUNT(DISTINCT IdUsuario) FROM AuditoriaSesiones WHERE FechaInicio >= ?) AS usuariosSesion30d,
                    (SELECT COUNT(DISTINCT IdUsuario) FROM AuditoriaSesiones WHERE FechaInicio >= ?) AS usuariosSesion7d", [$desde30, $desde30, $desde7])->getRowArray() ?? [];

                return array_map('intval', $row);
            },
            'usuariosPorRol' => function () {
                return array_map(fn($r) => ['rol' => $r['rol'], 'abreviatura' => $r['abreviatura'], 'usuarios' => (int) $r['usuarios']], $this->db->query("SELECT r.Nombre AS rol, r.Abreviatura AS abreviatura, COUNT(u.Id) AS usuarios
                    FROM Roles r
                    LEFT JOIN UsuariosRoles ur ON ur.IdRol = r.Id AND ur.FechaFinalizacion IS NULL
                    LEFT JOIN Usuarios u ON u.Id = ur.IdUsuario AND u.FechaFinalizacion IS NULL
                    WHERE r.FechaFinalizacion IS NULL
                    GROUP BY r.Id, r.Nombre, r.Abreviatura
                    ORDER BY COUNT(u.Id) DESC, r.Nombre")->getResultArray());
            },
            'modulosPorLoseta' => function () {
                return array_map(fn($r) => ['loseta' => $r['loseta'], 'modulos' => (int) $r['modulos'], 'submodulos' => (int) $r['submodulos'], 'acciones' => (int) $r['acciones']], $this->db->query("SELECT l.Nombre AS loseta,
                    (SELECT COUNT(*) FROM Modulos m WHERE m.IdLoseta = l.Id AND m.IdModulo IS NULL AND m.FechaFinalizacion IS NULL) AS modulos,
                    (SELECT COUNT(*) FROM Modulos m WHERE m.IdLoseta = l.Id AND m.IdModulo IS NOT NULL AND m.FechaFinalizacion IS NULL) AS submodulos,
                    (SELECT COUNT(*) FROM ModulosAcciones ma JOIN Modulos m ON m.Id = ma.IdModulo WHERE (m.Id = l.Id OR m.IdLoseta = l.Id) AND m.FechaFinalizacion IS NULL AND ma.FechaFinalizacion IS NULL) AS acciones
                    FROM Modulos l
                    WHERE l.IdLoseta IS NULL AND l.FechaFinalizacion IS NULL
                    ORDER BY l.Orden, l.Id")->getResultArray());
            },
            'sesionesPorDia' => function () use ($desde30) {
                $rows = $this->db->query("SELECT CONVERT(varchar(10), CAST(FechaInicio AS date), 23) AS fecha, COUNT(*) AS sesiones, COUNT(DISTINCT IdUsuario) AS usuarios
                    FROM AuditoriaSesiones
                    WHERE FechaInicio >= ?
                    GROUP BY CAST(FechaInicio AS date)", [$desde30])->getResultArray();
                $porFecha = array_column($rows, null, 'fecha');
                $dias = [];
                for ($i = 29; $i >= 0; $i--) {
                    $fecha = date('Y-m-d', strtotime("-$i days"));
                    $dias[] = ['fecha' => $fecha, 'sesiones' => (int) ($porFecha[$fecha]['sesiones'] ?? 0), 'usuarios' => (int) ($porFecha[$fecha]['usuarios'] ?? 0)];
                }

                return $dias;
            },
            'ultimosAccesos' => function () {
                return array_map(fn($r) => ['usuario' => $r['usuario'], 'nombre' => trim($r['nombre']), 'fecha' => substr($r['fecha'], 0, 16)], $this->db->query("SELECT TOP 8 u.Usuario AS usuario, CONCAT(u.Nombre, ' ', u.Apellido) AS nombre, CONVERT(varchar(19), s.FechaInicio, 120) AS fecha
                    FROM AuditoriaSesiones s
                    JOIN Usuarios u ON u.Id = s.IdUsuario
                    ORDER BY s.FechaInicio DESC, s.Id DESC")->getResultArray());
            },
            'rolesSinUsuarios' => function () {
                return array_column($this->db->query("SELECT r.Nombre FROM Roles r
                    WHERE r.FechaFinalizacion IS NULL
                    AND NOT EXISTS (SELECT 1 FROM UsuariosRoles ur JOIN Usuarios u ON u.Id = ur.IdUsuario AND u.FechaFinalizacion IS NULL WHERE ur.IdRol = r.Id AND ur.FechaFinalizacion IS NULL)
                    ORDER BY r.Nombre")->getResultArray(), 'Nombre');
            },
            'modulosSinRol' => function () {
                return array_column($this->db->query("SELECT m.Nombre FROM Modulos m
                    WHERE m.FechaFinalizacion IS NULL
                    AND NOT EXISTS (SELECT 1 FROM ModulosRoles mr JOIN Roles r ON r.Id = mr.IdRol AND r.FechaFinalizacion IS NULL WHERE mr.IdModulo = m.Id AND mr.FechaFinalizacion IS NULL)
                    ORDER BY m.Nombre")->getResultArray(), 'Nombre');
            },
        ];

        foreach ($consultas as $clave => $consulta) {
            try {
                $resumen[$clave] = $consulta();
            } catch (\Throwable $e) {
                log_message('error', 'AdminModel::resumen [' . $clave . '] ' . $e->getMessage());
                $resumen['fallos'][] = $clave;
                $resumen['avisos'][] = 'No fue posible cargar ' . $etiquetas[$clave] . '.';
            }
        }

        return $resumen;
    }

}