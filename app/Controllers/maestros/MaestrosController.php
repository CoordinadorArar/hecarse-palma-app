<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Controlador de aterrizaje ("onboarding") de la loseta "Maestros".
 */
class MaestrosController extends BaseController
{
    private $session;

    public function __construct()
    {
        $this->session = session();
    }

    /**
     * Vista inicial de la loseta "Maestros".
     *
     * @param int $id_loseta Identificador de la loseta.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('maestros/onboarding', $data);
    }
}
