<?php

namespace App\Controllers\gestionpalma;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\PresupuestoProduccionModel;
use App\Models\ReportesModel;

class PresupuestoProduccionController extends GestionPalmaController
{
    private const RUTA = 'gestion-palma/contabilidad/presupuesto';

    private $presupuestoModel;
    private ReportesModel $reportesModel;

    public function __construct()
    {
        parent::__construct();

        $this->presupuestoModel = new PresupuestoProduccionModel();
        $this->reportesModel    = new ReportesModel();
    }

    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return redirect()->to(base_url('losetas'));
        }

        $empresas = $this->presupuestoModel->getEmpresas();

        $data['title']        = 'Gestión Palma · Presupuesto de producción';
        $data['empresas']     = $empresas;
        $data['empresa_sel']  = $this->empresaSeleccionada($empresas);
        $data['anios']        = $this->presupuestoModel->getAnios();
        $data['anio_sel']     = (int) date('Y');
        $data['tabla_existe'] = $this->presupuestoModel->tablaExiste();

        return view('gestionpalma/presupuesto_produccion', $data);
    }

    public function listar(): ResponseInterface
    {
        $base = $this->validarBase(false);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio] = $base;
        $existe = $this->presupuestoModel->tablaExiste();

        return $this->response->setJSON([
            'success'     => true,
            'message'     => $existe ? '' : 'La tabla de presupuesto de producción aún no ha sido creada.',
            'tablaExiste' => $existe,
            'data'        => $this->presupuestoModel->listar($empresa, $anio),
            'anterior'    => $this->presupuestoModel->listar($empresa, $anio - 1),
        ]);
    }

    public function guardar(): ResponseInterface
    {
        $base = $this->validarBase(true);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio, $finca] = $base;

        $meses = $this->request->getPost('meses');

        if (is_string($meses)) {
            $meses = json_decode($meses, true);
        }

        if (! is_array($meses) || $meses === []) {
            return $this->error('Debe enviar al menos un mes.');
        }

        $limpios = [];

        foreach ($meses as $mes => $kilos) {
            if ($kilos !== null && ! is_scalar($kilos)) {
                return $this->error("Los kilos del mes {$mes} no son válidos.");
            }

            if (! ctype_digit((string) $mes) || (int) $mes < 1 || (int) $mes > 12) {
                return $this->error('El mes debe estar entre 1 y 12.');
            }

            $kilos = str_replace(' ', '', trim((string) $kilos));

            if ($kilos !== '' && ! preg_match('/^\d{1,16}(\.\d{1,2})?$/', $kilos)) {
                return $this->error("Los kilos del mes {$mes} deben ser un número mayor o igual a 0 con máximo 2 decimales.");
            }

            $limpios[(int) $mes] = $kilos;
        }

        if (! $this->presupuestoModel->guardar($empresa, $anio, $finca, $limpios, $this->usuarioRegistro())) {
            return $this->error('No se pudo guardar el presupuesto. La operación fue revertida.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Presupuesto guardado correctamente.',
        ]);
    }

    public function eliminar(): ResponseInterface
    {
        $base = $this->validarBase(true);

        if ($base instanceof ResponseInterface) {
            return $base;
        }

        [$empresa, $anio, $finca] = $base;

        $mes = trim((string) $this->request->getPost('mes'));

        if ($mes !== '' && (! ctype_digit($mes) || (int) $mes < 1 || (int) $mes > 12)) {
            return $this->error('El mes debe estar entre 1 y 12.');
        }

        if (! $this->presupuestoModel->eliminar($empresa, $anio, $finca, $mes === '' ? null : (int) $mes)) {
            return $this->error('No se pudo eliminar el presupuesto.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $mes === '' ? 'Presupuesto de la finca eliminado correctamente.' : 'Presupuesto del mes eliminado correctamente.',
        ]);
    }

    private function validarBase(bool $escritura): array|ResponseInterface
    {
        if (empty($this->session->get('usu_id'))) {
            return $this->error('Sesión expirada. Vuelva a iniciar sesión.', 401);
        }

        if (! $this->reportesModel->moduloPermitido((int) $this->session->get('usu_id'), self::RUTA)) {
            return $this->error('No tiene permiso para gestionar el presupuesto de producción.', 403);
        }

        $empresa = $this->request->getPost('empresa');

        if ($empresa === null || ! ctype_digit((string) $empresa) || ! $this->presupuestoModel->empresaExiste((int) $empresa)) {
            return $this->error('Debe seleccionar una empresa válida.');
        }

        $anio = trim((string) $this->request->getPost('anio'));

        if (! ctype_digit($anio) || (int) $anio < 2000 || (int) $anio > 2100) {
            return $this->error('El año debe estar entre 2000 y 2100.');
        }

        if (! $escritura) {
            return [(int) $empresa, (int) $anio];
        }

        if (! $this->presupuestoModel->tablaExiste()) {
            return $this->error('La tabla de presupuesto de producción aún no ha sido creada.', 409);
        }

        $finca = trim((string) $this->request->getPost('finca'));

        if ($finca === '' || ! $this->presupuestoModel->fincaExiste((int) $empresa, $finca)) {
            return $this->error('La finca seleccionada no existe para esta empresa.');
        }

        return [(int) $empresa, (int) $anio, $finca];
    }

    private function usuarioRegistro(): string
    {
        $usuario = trim((string) $this->session->get('usu_login'));

        return $usuario === '' ? 'WEB' : mb_substr($usuario, 0, 50);
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }
}
