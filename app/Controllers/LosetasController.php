<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

use App\Models\LosetasModel;

class LosetasController extends BaseController
{
    /**
     * @var LosetasModel $losetas_model Modelo de losetas.
     */
    private $losetas_model;

    function __construct()
    {
        $this->losetas_model = new LosetasModel();
    }

    /**
     * Metodo para renderizar la vista de login.
     * 
     * @return string|RedirectResponse Vista de login.
     */
    public function index(): string|RedirectResponse
    {
        $session = session();

        if (!$session->has('usu_id')) {
            return redirect()->to(base_url(''));
        }

        $losetas = $this->losetas_model->getLosetasByUsuario($session->get('usu_id'));

        $data = ['title' => lang('Losetas.welcome')];
        $data['subtitle'] = lang('Losetas.subtitle_welcome');
        $data['losetas'] = $losetas;
        $data['nombre_usuario'] = trim($session->get('usu_nombres') . ' ' . $session->get('usu_apellidos'));
        $data['ultimo_acceso'] = $session->get('ultimo_acceso')
            ? date('d/m/Y H:i', $session->get('ultimo_acceso'))
            : null;

        return view('losetas', $data);
    }
}
