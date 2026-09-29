<?php

namespace App\Controllers\Maestros;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\EmpresasModel;

/**
 * Administración del maestro de empresas (tabla gEmpresa).
 */
class EmpresasController extends BaseController
{
    private $session;
    private $empresasModel;

    public function __construct()
    {
        $this->session       = session();
        $this->empresasModel = new EmpresasModel();
    }

    /**
     * Vista principal del módulo "Empresas".
     *
     * @param int $id_loseta Identificador de la loseta "Maestros".
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $data['tabla_empresas']   = $this->renderizarListaEmpresas();
        $data['siguiente_codigo'] = $this->empresasModel->siguienteId();

        return view('maestros/empresas', $data);
    }

    /**
     * Filas (<tr>) del listado de empresas; el <thead> vive en la vista.
     */
    private function renderizarListaEmpresas(): string
    {
        $empresas = $this->empresasModel->getAll();

        if ($empresas === []) {
            return '<tr><td colspan="9" class="text-center text-muted py-4">No se encontraron empresas registradas.</td></tr>';
        }

        $esc = static fn ($texto) => htmlspecialchars((string) $texto, ENT_QUOTES);
        $aJs = static fn ($texto) => htmlspecialchars(
            str_replace(['\\', "'"], ['\\\\', "\\'"], str_replace(["\r", "\n", "\t"], ' ', (string) $texto)),
            ENT_QUOTES
        );

        $html = '';

        foreach ($empresas as $empresa) {
            $id          = (int) $empresa['id'];
            $activo      = (int) $empresa['activo'] === 1 ? 1 : 0;
            $extractora  = (int) $empresa['extractora'] === 1 ? 1 : 0;
            $tercero     = (int) ($empresa['tercero'] ?? 0);
            $razonSocial = trim((string) $empresa['razonSocial']);
            $nit         = trim((string) $empresa['nit']);
            $dv          = trim((string) $empresa['dv']);

            $nitCompleto = $nit . ($dv !== '' ? '-' . $dv : '');

            $celdaNit = '<span class="font-monospace text-nowrap">' . $esc($nit)
                . ($dv !== '' ? '<span class="maestros-sep">-</span>' . $esc($dv) : '')
                . '</span>';

            $nombreRepresentante = trim((string) ($empresa['RepresentanteNombre'] ?? $empresa['NombreTercero'] ?? ''));
            $ccRepresentante     = trim((string) ($empresa['RepresentanteCc'] ?? ''));

            $lineasRepresentante = [];

            if ($tercero > 0 && $nombreRepresentante !== '') {
                $lineasRepresentante[] = '<span class="maestros-truncar" title="' . $esc($nombreRepresentante) . '">' . $esc($nombreRepresentante) . '</span>';
            }

            if ($tercero > 0 && $ccRepresentante !== '') {
                $lineasRepresentante[] = '<span class="maestros-subtexto"><span class="maestros-etiqueta-mini">CC</span><span class="font-monospace">' . $esc($ccRepresentante) . '</span></span>';
            }

            $celdaRepresentante = $lineasRepresentante === []
                ? '<span class="maestros-vacio">Sin representante</span>'
                : implode('', $lineasRepresentante);

            $telefono  = trim((string) ($empresa['RepresentanteTelefono'] ?? ''));
            $email     = trim((string) ($empresa['RepresentanteEmail'] ?? ''));
            $direccion = trim((string) ($empresa['RepresentanteDireccion'] ?? ''));

            $lineasContacto = [];

            if ($tercero > 0 && $telefono !== '') {
                $lineasContacto[] = '<span class="d-block text-nowrap"><i class="bi bi-telephone maestros-icono-guia" aria-hidden="true"></i><span class="font-monospace">' . $esc($telefono) . '</span></span>';
            }

            if ($tercero > 0 && $email !== '') {
                $lineasContacto[] = '<span class="maestros-subtexto maestros-truncar maestros-truncar-contacto" title="' . $esc($email) . '"><i class="bi bi-envelope maestros-icono-guia" aria-hidden="true"></i>' . $esc($email) . '</span>';
            }

            if ($tercero > 0 && $direccion !== '') {
                $lineasContacto[] = '<span class="maestros-subtexto maestros-truncar maestros-truncar-contacto" title="' . $esc($direccion) . '"><i class="bi bi-geo-alt maestros-icono-guia" aria-hidden="true"></i>' . $esc($direccion) . '</span>';
            }

            $celdaContacto = $lineasContacto === []
                ? '<span class="maestros-vacio">Sin datos de contacto</span>'
                : implode('', $lineasContacto);

            $celdaExtractora = $extractora === 1
                ? '<span class="badge maestros-badge-si rounded-pill px-3 py-2"><i class="bi bi-droplet-fill me-1" aria-hidden="true"></i>Sí</span>'
                : '<span class="maestros-vacio">No</span>';

            $celdaEstado = $activo === 1
                ? '<span class="badge maestros-badge-activo rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Activa</span>'
                : '<span class="badge maestros-badge-inactivo rounded-pill px-3 py-2"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Inactiva</span>';

            $marcaTiempo = !empty($empresa['fechaRegistro']) ? strtotime((string) $empresa['fechaRegistro']) : false;
            $orden       = $marcaTiempo !== false ? date('Y-m-d', $marcaTiempo) : '';
            $celdaFecha  = $marcaTiempo !== false
                ? '<span class="text-muted small text-nowrap">' . date('d M, Y', $marcaTiempo) . '</span>'
                : '<span class="maestros-vacio">Sin fecha</span>';

            $html .= '<tr data-id="' . $id . '" data-tercero="' . ($tercero > 0 ? 1 : 0) . '"'
                . ($activo === 1 ? '' : ' class="maestros-fila-inactiva"') . '>'
                . /* 1 Código */ '<td class="text-center" data-order="' . $id . '"><span class="badge maestros-badge-doc rounded-pill px-2 py-1 font-monospace">' . $id . '</span></td>'
                . /* 2 Nit */ '<td data-order="' . $esc($nit) . '">' . $celdaNit . '</td>'
                . /* 3 Razón social */ '<td><span class="fw-semibold maestros-razon maestros-truncar" title="' . $esc($razonSocial) . '">' . $esc($razonSocial) . '</span></td>'
                . /* 4 Representante legal */ '<td data-order="' . $esc($nombreRepresentante) . '">' . $celdaRepresentante . '</td>'
                . /* 5 Contacto */ '<td data-order="' . $esc($telefono) . '">' . $celdaContacto . '</td>'
                . /* 6 Extractora */ '<td class="text-center" data-order="' . $extractora . '">' . $celdaExtractora . '</td>'
                . /* 7 Estado */ '<td class="text-center" data-order="' . $activo . '">' . $celdaEstado . '</td>'
                . /* 8 Fecha registro */ '<td data-order="' . $orden . '">' . $celdaFecha . '</td>'
                . /* 9 Acciones */ '<td class="text-center maestros-col-sticky">'
                . '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-secondary" title="Editar empresa" aria-label="Editar empresa"'
                . ' data-bs-toggle="modal" data-bs-target="#modalEditarEmpresa" onclick="obtenerEmpresa(' . $id . ')"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
                . '<button type="button" class="btn btn-outline-danger" title="Eliminar empresa" aria-label="Eliminar empresa"'
                . ' onclick="eliminarEmpresa(' . $id . ',&#039;' . $aJs($razonSocial) . '&#039;,&#039;' . $aJs($nitCompleto) . '&#039;)"><i class="bi bi-trash" aria-hidden="true"></i></button>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        return $html;
    }

    public function getEmpresaById(int $id): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        return $this->response->setJSON([
            'success' => true,
            'empresa' => $this->empresasModel->getEmpresaById($id),
        ]);
    }

    /**
     * Consecutivo que tomara la proxima empresa (gEmpresa.id no es identity).
     */
    public function siguienteCodigo(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        return $this->response->setJSON([
            'success' => true,
            'codigo'  => $this->empresasModel->siguienteId(),
        ]);
    }

    /**
     * Alta de la empresa junto con el tercero de su representante.
     */
    public function createEmpresa(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $nit         = preg_replace('/\D/', '', (string) $this->request->getVar('Nit'));
        $razonSocial = trim((string) $this->request->getVar('RazonSocial'));
        $cc          = preg_replace('/\D/', '', (string) $this->request->getVar('CcRepresentante'));
        $nombre      = trim((string) $this->request->getVar('NombreRepresentante'));
        $telefono    = trim((string) $this->request->getVar('Telefono'));
        $direccion   = trim((string) $this->request->getVar('Direccion'));
        $email       = trim((string) $this->request->getVar('Email'));

        if (strlen($nit) < 5) {
            return $this->error('El Nit ingresado no es válido.');
        }

        if ($this->empresasModel->nitExiste($nit)) {
            return $this->error("El Nit '{$nit}' ya está registrado.");
        }

        if ($razonSocial === '') {
            return $this->error('La razón social es obligatoria.');
        }

        if ($cc === '') {
            return $this->error('La cédula del representante es obligatoria y solo admite dígitos.');
        }

        if ($nombre === '') {
            return $this->error('El nombre del representante es obligatorio.');
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('El correo electrónico ingresado no es válido.');
        }

        $dv = $this->request->getVar('Dv');
        $dv = ($dv === null || $dv === '') ? $this->empresasModel->calcularDV($nit) : (int) $dv;
        $dv = substr((string) $dv, 0, 1);

        $creada = $this->empresasModel->crearEmpresaConRepresentante([
            'nit'         => substr($nit, 0, 15),
            'dv'          => $dv,
            'razonSocial' => mb_substr($razonSocial, 0, 550),
            'activo'      => $this->request->getVar('Activo') == 1 ? 1 : 0,
            'extractora'  => $this->request->getVar('Extractora') == 1 ? 1 : 0,
        ], [
            'codigo'              => substr($cc, 0, 50),
            'nit'                 => substr($cc, 0, 25),
            'razonSocial'         => mb_substr($nombre, 0, 550),
            'descripcion'         => mb_substr($nombre, 0, 950),
            'nombre1'             => mb_substr($nombre, 0, 550),
            'telefono'            => mb_substr($telefono, 0, 50),
            'direccion'           => mb_substr($direccion, 0, 550),
            'email'               => mb_substr($email, 0, 250),
            'actividadEconomica'  => mb_substr(trim((string) $this->request->getVar('ActividadEconomica')), 0, 250),
            'notas'               => mb_substr(trim((string) $this->request->getVar('Notas')), 0, 1000),
        ], $this->session->get('usu_id'));

        if ($creada === null) {
            return $this->error('No se pudo registrar la empresa y su representante.', 500);
        }

        return $this->response->setBody($this->renderizarListaEmpresas());
    }

    private function sesionRequerida(): ?ResponseInterface
    {
        if (! empty($this->session->get('usu_id'))) {
            return null;
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Sesión expirada. Vuelva a iniciar sesión.',
        ])->setStatusCode(401);
    }

    private function error(string $mensaje, int $codigo = 422): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $mensaje,
        ])->setStatusCode($codigo);
    }

    public function updateEmpresa(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $id  = (int) $this->request->getVar('Id');
        $nit = preg_replace('/\D/', '', (string) $this->request->getVar('Nit'));

        if (strlen($nit) < 5) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El Nit ingresado no es válido.',
            ])->setStatusCode(422);
        }

        if ($this->empresasModel->nitExiste($nit, $id)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => "El Nit '{$nit}' ya está registrado.",
            ])->setStatusCode(422);
        }

        $dv = $this->request->getVar('Dv');
        $dv = ($dv === null || $dv === '') ? $this->empresasModel->calcularDV($nit) : (int) $dv;

        $tercero = $this->request->getVar('Tercero');

        $actualizada = $this->empresasModel->updateEmpresa($id, [
            'nit'         => $nit,
            'dv'          => $dv,
            'razonSocial' => $this->request->getVar('RazonSocial'),
            'activo'      => $this->request->getVar('Activo') == 1 ? 1 : 0,
            'extractora'  => $this->request->getVar('Extractora') == 1 ? 1 : 0,
            'tercero'     => !empty($tercero) ? (int) $tercero : null,
        ], $this->session->get('usu_id'));

        if (! $actualizada) {
            return $this->error('No se pudo actualizar la empresa.', 500);
        }

        return $this->response->setBody($this->renderizarListaEmpresas());
    }

    /**
     * Baja de la empresa; se rechaza si alguna tabla la referencia.
     */
    public function eliminarEmpresa(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $id = (int) $this->request->getVar('Id');

        if ($id <= 0 || ($empresa = $this->empresasModel->getEmpresaById($id)) === null) {
            return $this->error('La empresa que intenta eliminar no existe.');
        }

        $dependencias = $this->empresasModel->dependencias($id);

        if ($dependencias !== []) {
            return $this->response->setJSON([
                'success'      => false,
                'message'      => 'No se puede eliminar: la empresa tiene registros asociados.',
                'dependencias' => $dependencias,
            ])->setStatusCode(422);
        }

        if (! $this->empresasModel->eliminarEmpresa($id, $this->session->get('usu_id'), $empresa)) {
            return $this->error('No se pudo eliminar la empresa.', 500);
        }

        return $this->response->setBody($this->renderizarListaEmpresas());
    }

    /**
     * Buscador (typeahead) de terceros para vincular a la empresa.
     */
    public function buscarTercero(): ResponseInterface
    {
        if ($sinSesion = $this->sesionRequerida()) {
            return $sinSesion;
        }

        $termino = (string) $this->request->getVar('termino');

        if (strlen($termino) < 2) {
            return $this->response->setJSON(['success' => true, 'terceros' => []]);
        }

        return $this->response->setJSON([
            'success'  => true,
            'terceros' => $this->empresasModel->buscarTercero($termino),
        ]);
    }
}
