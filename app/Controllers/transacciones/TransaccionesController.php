<?php

namespace App\Controllers\Transacciones;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Controlador de aterrizaje ("onboarding") de la loseta "Transacciones".
 */
class TransaccionesController extends BaseController
{
    private $session;

    public function __construct()
    {
        $this->session = session();
    }

    /**
     * Vista inicial de la loseta "Transacciones".
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

        return view('transacciones/onboarding', $data);
    }
}
