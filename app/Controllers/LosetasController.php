<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\InformesGerencialesModel;
use App\Models\LosetasModel;
use App\Models\PlaneacionModel;

class LosetasController extends BaseController
{
    private const DIAS_ALERTA = 7;

    private const LIMITE_PLANEADAS = 300;

    private const CICLOS_ALERTA = [
        'ciclos-cosecha'       => ['Cosecha', ['alerta', 'sobremaduracion']],
        'ciclos-poda'          => ['Poda', ['vencido', 'proximo']],
        'ciclos-fertilizacion' => ['Fertilización', ['vencido', 'proximo']],
        'ciclos-sanidad'       => ['Sanidad', ['vencido', 'proximo']],
        'ciclos-mantenimiento' => ['Mantenimiento', ['vencido', 'proximo']],
    ];

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

        $data = ['title' => sitio('nombre_app'), 'empresa' => lang('Losetas.empresa')];
        $data['subtitle'] = lang('Losetas.subtitle_welcome');
        $data['losetas'] = $losetas;
        $data['nombre_usuario'] = trim($session->get('usu_nombres') . ' ' . $session->get('usu_apellidos'));
        $data['ultimo_acceso'] = $session->get('ultimo_acceso')
            ? date('d/m/Y H:i', $session->get('ultimo_acceso'))
            : null;
        $data['usu_id'] = (int) $session->get('usu_id');
        $data['alerta_labores'] = (bool) $session->get('alerta_labores') && alerta_labores_permitida();

        $session->remove('alerta_labores');

        return view('losetas', $data);
    }

    public function alertaLabores(): ResponseInterface
    {
        $usuario = (int) session()->get('usu_id');

        if ($usuario <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'])->setStatusCode(401);
        }

        if (! alerta_labores_permitida()) {
            return $this->response->setJSON(['success' => false, 'message' => 'No tiene permiso para consultar las próximas labores.'])->setStatusCode(403);
        }

        $hoy       = date('Y-m-d');
        $informes  = new InformesGerencialesModel();
        $avisos    = [];
        $empresas  = [];
        $planeadas = [];
        $total     = 0;
        $ciclos    = [];
        $rangos    = [];

        try {
            $empresas = array_map(static fn ($v) => trim((string) $v), array_column($informes->getEmpresas(), 'razonSocial', 'id'));
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando empresas para la alerta de labores: ' . $e->getMessage());

            $avisos[] = 'No se pudieron consultar las empresas.';
        }

        try {
            $modelo = new PlaneacionModel();

            if ($modelo->planeacionDisponible()) {
                $lineas = $modelo->proximasPendientes($hoy, date('Y-m-d', strtotime('+' . self::DIAS_ALERTA . ' days')));

                usort($lineas, static fn ($a, $b) => [$a['fecha'], trim($a['finca']), trim($a['lote']), $a['empresa'], $a['orden'], $a['id']]
                    <=> [$b['fecha'], trim($b['finca']), trim($b['lote']), $b['empresa'], $b['orden'], $b['id']]);

                $total = count($lineas);

                foreach (array_slice($lineas, 0, self::LIMITE_PLANEADAS) as $l) {
                    $planeadas[] = [
                        'empresa'       => $l['empresa'],
                        'empresaNombre' => $empresas[$l['empresa']] ?? '',
                        'fecha'         => $l['fecha'],
                        'finca'         => trim($l['finca']),
                        'fincaNombre'   => trim($l['fincaNombre']),
                        'lote'          => trim($l['lote']),
                        'loteNombre'    => trim($l['loteNombre']),
                        'novedad'       => trim($l['novedad']),
                        'labor'         => trim($l['novedadNombre']),
                        'uMedida'       => trim((string) $l['uMedida']),
                        'cantidad'      => $l['cantidad'],
                        'horaInicio'    => $l['horaInicio'],
                        'horaFin'       => $l['horaFin'],
                        'trabajadores'  => array_map('trim', array_column($l['trabajadores'], 'nombre')),
                    ];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando las labores planeadas para la alerta: ' . $e->getMessage());

            $planeadas = [];
            $total     = 0;
            $avisos[]  = 'No se pudieron consultar las labores planeadas.';
        }

        foreach (self::CICLOS_ALERTA as $informe => [, $estados]) {
            $rangos[$informe] = array_column(array_filter($informes->rangosCiclo($informe), static fn ($r) => in_array($r['clave'], $estados, true)), 'etiqueta', 'clave');
        }

        try {
            foreach ($empresas as $empresa => $empresaNombre) {
                foreach ($informes->getFincas((int) $empresa) as $finca) {
                    $lotes = $informes->getLotesCiclo((int) $empresa, $finca['codigo']);

                    if ($lotes === []) {
                        continue;
                    }

                    foreach (self::CICLOS_ALERTA as $informe => [$nombre, $estados]) {
                        foreach ($informes->calcularCiclos($informe, (int) $empresa, $finca['codigo'], $hoy, $lotes)['lotes'] as $l) {
                            if (! in_array($l['estado'], $estados, true)) {
                                continue;
                            }

                            $ciclos[] = [
                                'informe'           => $informe,
                                'nombre'            => $nombre,
                                'empresa'           => (int) $empresa,
                                'empresaNombre'     => $empresaNombre,
                                'finca'             => $finca['codigo'],
                                'fincaNombre'       => $finca['nombre'],
                                'lote'              => $l['codigo'],
                                'loteDescripcion'   => $l['descripcion'],
                                'estado'            => $l['estado'],
                                'estadoNombre'      => $rangos[$informe][$l['estado']],
                                'diasParaIngreso'   => $l['diasParaIngreso'],
                                'diasTranscurridos' => $l['diasTranscurridos'],
                                'proximo'           => $l['proximo'],
                                'labor'             => $l['labor'],
                            ];
                        }
                    }
                }
            }

            $orden = array_flip(array_keys(self::CICLOS_ALERTA));

            usort($ciclos, static fn ($a, $b) => [$orden[$a['informe']], $a['informe'] === 'ciclos-cosecha' ? -$a['diasTranscurridos'] : $a['diasParaIngreso'], $a['empresa'], $a['finca'], $a['lote']]
                <=> [$orden[$b['informe']], $b['informe'] === 'ciclos-cosecha' ? -$b['diasTranscurridos'] : $b['diasParaIngreso'], $b['empresa'], $b['finca'], $b['lote']]);
        } catch (\Throwable $e) {
            log_message('error', 'Error calculando los ciclos para la alerta de labores: ' . $e->getMessage());

            $ciclos   = [];
            $avisos[] = 'No se pudieron calcular los ciclos de labores.';
        }

        $planeacionUrl = null;

        try {
            $plan   = new PlaneacionModel();
            $modulo = $plan->idModulo();
            $loseta = $modulo > 0 ? $plan->idLoseta($modulo) : 0;

            if ($loseta > 0 && array_intersect(['consultar-planeacion', 'registrar-planeacion', 'editar-planeacion'], $plan->accionesPermitidas($usuario, $modulo)) !== []) {
                $planeacionUrl = base_url('transacciones/planeacion/' . $loseta);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error validando el acceso a la planeación para la alerta: ' . $e->getMessage());
        }

        return $this->response->setJSON([
            'success'         => true,
            'planeacionUrl'   => $planeacionUrl,
            'message'         => '',
            'fecha'           => $hoy,
            'dias'            => self::DIAS_ALERTA,
            'planeadas'       => $planeadas,
            'planeadasTotal'  => $total,
            'ciclos'          => $ciclos,
            'rangos'          => $rangos,
            'avisos'          => $avisos,
        ]);
    }
}
