<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LosetasModel;
use App\Models\ReportesModel;
use App\Models\SiteModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class SiteController extends BaseController
{
    private $session;

    private $losetas_model;
    private $site_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->site_model = new SiteModel();
    }

    /**
     * Metodo para renderizar la vista de configuración del sitio.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de los usuarios.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $data['config'] = sitio_config();
        $data['defectos'] = sitio_defectos();

        return view('admin/site', $data);
    }

    /**
     * Guardar configuración del sitio
     */
    public function guardar(): ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->response->setJSON(['success' => false, 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'])->setStatusCode(401);
        }

        if (!(new ReportesModel())->moduloPermitido((int) $this->session->get('usu_id'), 'admin/sitio')) {
            return $this->response->setJSON(['success' => false, 'message' => 'No tiene permiso para modificar la configuración del sitio.'])->setStatusCode(403);
        }

        $defectos = sitio_defectos();
        $errores = [];
        $data = [
            'nombre_app' => trim((string) $this->request->getPost('nombre_app')),
            'color_primary' => strtolower(trim((string) $this->request->getPost('color_primary'))),
            'color_secundario' => strtolower(trim((string) $this->request->getPost('color_secundario')))
        ];

        if ($data['nombre_app'] === '' || mb_strlen($data['nombre_app']) > 60) {
            $errores['nombre_app'] = 'El nombre del sitio es obligatorio y debe tener máximo 60 caracteres.';
        }

        foreach (['color_primary' => 'primario', 'color_secundario' => 'secundario'] as $campo => $etiqueta) {
            if (!preg_match('/^#[0-9a-f]{6}$/', $data[$campo])) {
                $errores[$campo] = 'El color ' . $etiqueta . ' debe tener el formato #RRGGBB.';
            }
        }

        $tipos = [
            'logo' => ['png' => ['image/png'], 'webp' => ['image/webp']],
            'logo_claro' => ['png' => ['image/png'], 'webp' => ['image/webp']],
            'favicon' => ['png' => ['image/png'], 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon']]
        ];
        $archivos = [];

        foreach ($tipos as $campo => $permitidos) {
            if ($this->request->getPost('restablecer_' . $campo) === '1') {
                $data[$campo] = $defectos[$campo];
            }

            $archivo = $this->request->getFile($campo);

            if (!$archivo || $archivo->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $extension = strtolower($archivo->getClientExtension());

            if (!$archivo->isValid() || $archivo->hasMoved()) {
                $errores[$campo] = 'No se pudo recibir el archivo.';
            } elseif ($archivo->getSize() > 2 * 1024 * 1024) {
                $errores[$campo] = 'El archivo no debe superar los 2 MB.';
            } elseif (!isset($permitidos[$extension]) || !in_array($archivo->getMimeType(), $permitidos[$extension], true)) {
                $errores[$campo] = 'Formato no permitido. Use ' . strtoupper(implode(' o ', array_keys($permitidos))) . '.';
            } else {
                $archivos[$campo] = [$archivo, bin2hex(random_bytes(16)) . '.' . $extension];
            }
        }

        if ($errores) {
            return $this->response->setJSON(['success' => false, 'message' => 'Revise los campos indicados.', 'errores' => $errores])->setStatusCode(422);
        }

        $movidos = [];

        try {
            foreach ($archivos as $campo => [$archivo, $nombre]) {
                $archivo->move(FCPATH . 'public/uploads/config', $nombre);
                $movidos[] = FCPATH . 'public/uploads/config/' . $nombre;
                $data[$campo] = 'public/uploads/config/' . $nombre;
            }

            $result = $this->site_model->upsertConfiguracion($data);
        } catch (\Throwable $e) {
            log_message('error', 'Error guardando la configuración del sitio: ' . $e->getMessage());
            $result = false;
        }

        if (!$result) {
            array_map(static fn ($ruta) => is_file($ruta) && unlink($ruta), $movidos);

            return $this->response->setJSON(['success' => false, 'message' => 'No se pudo guardar la configuración. Intente de nuevo.'])->setStatusCode(500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Configuración guardada', 'config' => sitio_config(true)]);
    }
}
