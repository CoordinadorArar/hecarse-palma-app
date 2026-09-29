<?php

namespace App\Controllers\Informes;

use App\Controllers\BaseController;
use App\Models\LosetasModel;
use App\Models\UsuariosModel;
use App\Models\InformeModel;

use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InformeController extends BaseController
{
    private $session;

    private $losetas_model;

    private $usuarios_model;

    private $informe_model;

    /**
     * Metodo constructor.
     */
    function __construct()
    {
        $this->session = session();
        $this->losetas_model = new LosetasModel();
        $this->usuarios_model = new UsuariosModel();
        $this->informe_model = new InformeModel();
    }

    /**
     * Metodo para renderizar la vista inicial del modulo "financiero".
     * Se prepara el nombre de usuario y la imagen de usuario para renderizarla en la vista.
     * 
     * @param string $idLoseta Identificador de la loseta.
     * @return string|RedirectResponse Vista de login.
     */
    public function index($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('informes/onboarding', $data);
    }

    /**
     * Módulo de informe de recaudos
     */
    public function liquidacion($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('informes/liquidacion', $data);
    }

    /**
     * Módulo de informe de recaudos
     */
    public function contabilizar($id_loseta): string|RedirectResponse
    {
        helper('ConstruirDataVista');

        $data = construirVista($this->session->get('usu_id'), $id_loseta);

        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('informes/contabilizar', $data);
    }

    public function actualizarContratos()
    {
        try {
            $this->informe_model->updateContratos();

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Contratos actualizados correctamente'
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Error al actualizar los contratos',
                'detalle' => $e->getMessage()
            ]);
        }
    }


    public function temporales()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');

        if (!$anio || !$mes) {
            return $this->response->setJSON([]);
        }

        $data = $this->informe_model->getTemporales($anio, $mes);

        return $this->response->setJSON($data);
    }


    public function empleadosSinTemporal()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');

        $data = $this->informe_model->getEmpleadosSinTemporal($anio, $mes);

        return $this->response->setJSON($data);
    }

    public function consultarReporte()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');

        $data = $this->informe_model->getReporteGeneral($anio, $mes);

        return $this->response->setJSON($data);
    }


    public function verificarProcesado()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');
        $temporal = $this->request->getPost('temporal');
        $tipoContrato = $this->request->getPost('tipoContrato');

        $procesado = $this->informe_model->verificarReporteProcesado($anio, $mes, $temporal, $tipoContrato);

        return $this->response->setJSON([
            'procesado' => $procesado
        ]);
    }

    public function procesarReporte()
    {
        try {
            $anio = $this->request->getPost('anio');
            $mes = $this->request->getPost('mes');
            $temporal = $this->request->getPost('temporal');
            $tipoContrato = $this->request->getPost('tipoContrato');
            $datos = json_decode($this->request->getPost('datos'), true);

            if (!$anio || !$mes || !$temporal || !$tipoContrato || empty($datos)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Faltan datos requeridos'
                ]);
            }

            $registrosGuardados = $this->informe_model->guardarReporteProcesado(
                $datos,
                $anio,
                $mes,
                $temporal,
                $tipoContrato
            );

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Reporte procesado y guardado correctamente',
                'registros_guardados' => $registrosGuardados
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error procesando reporte: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Error al procesar el reporte',
                'detalle' => $e->getMessage()
            ]);
        }
    }


    public function actualizarValoresTemporal()
    {
        $archivo = $this->request->getFile('archivo');

        if (!$archivo || !$archivo->isValid()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Archivo inválido'
            ]);
        }

        $spreadsheet = IOFactory::load($archivo->getTempName());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Quitar encabezado
        unset($rows[0]);

        $anioExcel = null;
        $mesExcel = null;

        $registros = [];
        $mapaCedulas = [];

        foreach ($rows as $row) {

            $anio = (int) trim($row[0]);
            $mes  = (int) trim($row[1]);
            $cedula = trim($row[2]);

            $valorRaw = trim($row[3]);

            if ($valorRaw === '') {
                continue; // fila vacía, no agregar
            }

            // Eliminar símbolos y quedarse con solo dígitos (asumiendo que es entero)
            $valor = str_replace(['$', '.', ','], '', $valorRaw);
            $valor = (float) $valor; // o (float) si necesitas decimales

            if (!$anio || !$mes || !$cedula || !is_numeric($valor)) {
                continue;
            }

            if ($anioExcel === null) {
                $anioExcel = $anio;
                $mesExcel = $mes;
            }

            if ($anio !== $anioExcel || $mes !== $mesExcel) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'El archivo contiene más de un período (año/mes)'
                ]);
            }

            $mapaCedulas[$cedula][] = $cedula;

            $registros[] = [
                'cedula' => $cedula,
                'valor' => (float) $valor
            ];
        }

        $cedulasDuplicadas = [];

        foreach ($mapaCedulas as $cedula => $items) {
            if (count($items) > 1) {
                $cedulasDuplicadas[] = $cedula;
            }
        }

        if (!empty($cedulasDuplicadas)) {
            return $this->response->setJSON([
                'status' => 'error',
                'tipo' => 'duplicados',
                'message' => 'El archivo contiene cédulas duplicadas. No se realizó ninguna actualización.',
                'duplicados' => $cedulasDuplicadas
            ]);
        }

        $actualizados = 0;
        $cedulasNoExisten = [];

        foreach ($registros as $r) {

            if (!$this->informe_model->existeCedulaPeriodo($r['cedula'], $anioExcel, $mesExcel)) {

                $consulta = $this->informe_model->buscarCedula($r['cedula']);

                if ($consulta) {

                    $mesFormateado = str_pad($mesExcel, 2, '0', STR_PAD_LEFT);

                    $this->informe_model->insertarNuevos(
                        $anioExcel,
                        $mesFormateado,
                        $consulta['Cedula'],
                        $consulta['NombreEmpleado'],
                        $consulta['TipoContrato'],
                        $consulta['RazonTemporal'],
                        $r['valor']
                    );
                } else {
                    $cedulasNoExisten[] = $r['cedula'];
                }
                continue;
            }

            if ($this->informe_model->actualizarValorTemporal($r['cedula'], $r['valor'], $anioExcel, $mesExcel)) {
                $actualizados++;
            }
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'periodo' => "$anioExcel-$mesExcel",
            'actualizados' => $actualizados,
            'no_existen' => $cedulasNoExisten
        ]);
    }

    public function exportarExcel()
    {
        $data = json_decode($this->request->getPost('datos'), true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $sheet->fromArray([
            ['Cédula', 'Empleado', 'Tipo Contrato', 'Temporal', 'Total Pagado']
        ], null, 'A1');

        $row = 2;
        foreach ($data as $item) {
            $sheet->fromArray([
                $item['cedula'],
                $item['nombreTercero'],
                $item['TipoContrato'],
                $item['RazonTemporal'],
                $item['valorTotalTercero']
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $excelOutput = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte.xlsx"')
            ->setBody($excelOutput);
    }

    public function totalPagos()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');

        $data = $this->informe_model->valorTotalPagos($anio, $mes);

        return $this->response->setJSON($data);
    }

    public function pagosLabor()
    {
        $anio = $this->request->getPost('anio');
        $mes = $this->request->getPost('mes');

        $resumen = $this->informe_model->pagosLaborResumen($anio, $mes);

        $detallado = $this->informe_model->pagosLaborDetallado($anio, $mes);

        return $this->response->setJSON([
            'resumen' => $resumen,
            'detallado' => $detallado
        ]);
    }

    public function generarTXT()
    {
        $anio = $this->request->getGet('anio') ?? date('Y');
        $mes = $this->request->getGet('mes') ?? date('m');
        $temporal = $this->request->getGet('temporal');
        $datosLiquidacion = $this->informe_model->getDatosLiquidacion($anio, $mes, $temporal);
        $nitTemporal = $this->informe_model->getNitTemporal($temporal);

        if (empty($datosLiquidacion)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No se encontraron datos para el periodo seleccionado'
            ]);
        }

        $contador_lineas = 0;
        $fechaActual = date('Ymd');

        // ==================== LINEA 1 ====================
        $contador_lineas++;
        $xmlData = "000000" . $contador_lineas . "00000001001\r\n";

        if ($nitTemporal == '804004319') {
            $conPago = '008';
        } else {
            $conPago = '030';
        }

        // ==================== LINEA 2 ====================
        $contador_lineas++;
        $numero_linea = '';
        if ($contador_lineas <= 9) {
            $numero_linea = '0' . $contador_lineas;
        } else {
            $numero_linea = $contador_lineas;
        }

        $F_NUMERO_REG = '00000' . $numero_linea;
        $F_TIPO_REG = '0420';
        $F_SUBTIPO_REG = '00';
        $F_VERSION_REG = '05';
        $F_CIA = '001';

        $F_LIQUIDA_IMPUESTO = '1';
        $F_CONSEC_AUTO_REG = '1';
        $f420_id_co = '001';
        $f420_id_tipo_docto = 'OCN';
        $f420_consec_docto = str_pad('1', 8, '0', STR_PAD_LEFT);
        $f420_fecha = $fechaActual;
        $f420_id_concepto = '401';
        $f420_id_grupo_clase_docto = '402';
        $f420_id_clase_docto = '404';
        $f420_ind_estado = '0';
        $f420_ind_impresion = '0';
        $f420_id_tercero_sol_comp = str_pad('60450334', 15, ' ', STR_PAD_RIGHT);
        $f420_id_tercero_prov = str_pad($nitTemporal, 15, ' ', STR_PAD_RIGHT);
        $f420_id_sucursal_prov = '001';
        $f420_id_cond_pago = $conPago;
        $f420_ind_tasa = '1';
        $f420_id_moneda_docto = 'COP';
        $f420_id_moneda_conv = 'COP';
        $f420_tasa_conv = str_pad('1', 13, ' ', STR_PAD_LEFT);
        $f420_id_moneda_local = 'COP';
        $f420_tasa_local = str_pad('1', 13, ' ', STR_PAD_LEFT);
        $f420_tasa_dscto_global1 = sprintf('%08.4f', '0');
        $f420_tasa_dscto_global2 = '000.0000';
        $f420_notas = str_pad('', 255, ' ', STR_PAD_RIGHT);
        $F_IND_CONTACTO = '0';
        $f419_contacto = str_pad('', 50, ' ', STR_PAD_RIGHT);
        $f419_direccion1 = str_pad('', 40, ' ', STR_PAD_RIGHT);
        $f419_direccion2 = str_pad('', 40, ' ', STR_PAD_RIGHT);
        $f419_direccion3 = str_pad('', 40, ' ', STR_PAD_RIGHT);
        $f419_id_pais = str_pad(' ', 3, ' ', STR_PAD_RIGHT);
        $f419_id_depto = str_pad(' ', 2, ' ', STR_PAD_RIGHT);
        $f419_id_ciudad = str_pad(' ', 3, ' ', STR_PAD_RIGHT);
        $f419_id_barrio = str_pad(' ', 40, ' ', STR_PAD_RIGHT);
        $f419_telefono = str_pad(' ', 20, ' ', STR_PAD_RIGHT);
        $f419_fax = str_pad(' ', 20, ' ', STR_PAD_RIGHT);
        $f419_cod_postal = str_pad(' ', 10, ' ', STR_PAD_RIGHT);
        $f419_email = str_pad(' ', 50, ' ', STR_PAD_RIGHT);
        $f420_num_docto_referencia = str_pad(' ', 15, ' ', STR_PAD_RIGHT);
        $f420_id_mandato = str_pad(' ', 15, ' ', STR_PAD_RIGHT);
        $f420_ind_consignacion = '0';
        $f420_id_tercero_solicit = str_pad('60450334', 15, ' ', STR_PAD_RIGHT);


        $xmlData .= $F_NUMERO_REG . $F_TIPO_REG . $F_SUBTIPO_REG . $F_VERSION_REG . $F_CIA;
        $xmlData .= $F_LIQUIDA_IMPUESTO . $F_CONSEC_AUTO_REG . $f420_id_co . $f420_id_tipo_docto . $f420_consec_docto . $f420_fecha . $f420_id_concepto;
        $xmlData .= $f420_id_grupo_clase_docto . $f420_id_clase_docto . $f420_ind_estado . $f420_ind_impresion . $f420_id_tercero_sol_comp . $f420_id_tercero_prov;
        $xmlData .= $f420_id_sucursal_prov . $f420_id_cond_pago . $f420_ind_tasa . $f420_id_moneda_docto . $f420_id_moneda_conv . $f420_tasa_conv . $f420_id_moneda_local;
        $xmlData .= $f420_tasa_local . $f420_tasa_dscto_global1 . $f420_tasa_dscto_global2 . $f420_notas . $F_IND_CONTACTO . $f419_contacto . $f419_direccion1;
        $xmlData .= $f419_direccion2 . $f419_direccion3 . $f419_id_pais . $f419_id_depto . $f419_id_ciudad . $f419_id_barrio . $f419_telefono . $f419_fax;
        $xmlData .= $f419_cod_postal . $f419_email . $f420_num_docto_referencia . $f420_id_mandato . $f420_ind_consignacion . $f420_id_tercero_solicit;
        $xmlData .= "\r\n";

        /* Codigos de Servicios */
        $servicios = [
            'COSECHA' => 'SV001',
            'PLATEO' => 'SV004',
            'PODA' => 'SV009',
            'FERTILIZACIÓN' => 'SV005',
            'MANTENIMIENTO CULTIVO' => 'SV006',
            'MANTENIMIENTO LOCATIVO' => 'SV007',
            'GANADERIA' => 'SV008',
            'SANIDAD' => 'SV002',
            'MAPEO' => 'SV003',
            'TEMPORALES' => 'SV010'
        ];

        $consecutivo = 0;

        foreach ($datosLiquidacion as $registro) {

            $valorTemporal = $registro['ValorTemporal'];
            $centroOperacion = $registro['centroOperacion'];
            $centroCostos = $registro['ccosto'];
            $unidadNegocio = $registro['uNegocio'];
            $labor = $registro['nombreGrupoLabor'];
            $codigoServicio = $servicios[$labor];
            $consecutivo++;

            // ==================== LINEA 3 ====================
            $contador_lineas++;
            $numero_linea = '';
            if ($contador_lineas <= 9) {
                $numero_linea = '0' . $contador_lineas;
            } else {
                $numero_linea = $contador_lineas;
            }

            $F_NUMERO_REG = '00000' . $numero_linea;
            $F_TIPO_REG = '0421';
            $F_SUBTIPO_REG = '00';
            $F_VERSION_REG = '03';
            $F_CIA = '001';

            $f421_id_co = '001';
            $f421_id_tipo_docto = 'OCN';
            $f421_consec_docto = str_pad('1', 8, '0', STR_PAD_LEFT);
            $f421_nro_registro = str_pad('1', 10, '0', STR_PAD_LEFT);
            $F_CAMPO = str_pad(' ', 55, ' ', STR_PAD_RIGHT);
            $f421_id_bodega = str_pad('1FAT', 5, ' ', STR_PAD_RIGHT);
            $f421_id_concepto = '401';
            $f421_id_motivo = '02';
            $f421_ind_obsequio = '0';
            $f421_id_co_movto = $centroOperacion;
            $F_CAMPO_2 = str_pad(' ', 2, ' ', STR_PAD_RIGHT);
            $f421_id_ccosto_movto = str_pad($centroCostos, 15, ' ', STR_PAD_RIGHT);
            $f421_id_proyecto = str_pad(' ', 15, ' ', STR_PAD_RIGHT);
            $f421_id_unidad_medida = str_pad('UND', 4, ' ', STR_PAD_RIGHT);
            $f421_cant_pedida_base = sprintf('%020.4f', '1');
            $f421_fecha_entrega = $fechaActual;
            $f421_cod_item_prov = str_pad(' ', 15, ' ', STR_PAD_RIGHT);
            $f421_precio_unitario = sprintf('%020.4f', $valorTemporal);
            $f421_notas = str_pad(' ', 255, ' ', STR_PAD_RIGHT);
            $f421_detalle = str_pad(' ', 2000, ' ', STR_PAD_RIGHT);
            $F_DESC_ITEM = str_pad(' ', 40, ' ', STR_PAD_RIGHT);
            $F_ID_UM_INVENTARIO = str_pad(' ', 4, ' ', STR_PAD_RIGHT);
            $f421_id_item = str_pad('0', 7, '0', STR_PAD_RIGHT);
            $f421_referencia_item = str_pad($codigoServicio, 50, ' ', STR_PAD_RIGHT);
            $f421_codigo_barras = str_pad(' ', 20, ' ', STR_PAD_RIGHT);
            $f421_id_ext1_detalle = str_pad(' ', 20, ' ', STR_PAD_RIGHT);
            $f421_id_ext2_detalle = str_pad(' ', 20, ' ', STR_PAD_RIGHT);
            $f421_id_un_movto = str_pad($unidadNegocio, 20, ' ', STR_PAD_RIGHT);

            $xmlData .= $F_NUMERO_REG . $F_TIPO_REG . $F_SUBTIPO_REG . $F_VERSION_REG . $F_CIA;
            $xmlData .= $f421_id_co . $f421_id_tipo_docto . $f421_consec_docto . $f421_nro_registro . $F_CAMPO . $f421_id_bodega;
            $xmlData .= $f421_id_concepto . $f421_id_motivo . $f421_ind_obsequio . $f421_id_co_movto . $F_CAMPO_2 . $f421_id_ccosto_movto;
            $xmlData .= $f421_id_proyecto . $f421_id_unidad_medida . $f421_cant_pedida_base . $f421_fecha_entrega . $f421_cod_item_prov;
            $xmlData .= $f421_precio_unitario . $f421_notas . $f421_detalle . $F_DESC_ITEM . $F_ID_UM_INVENTARIO . $f421_id_item;
            $xmlData .= $f421_referencia_item . $f421_codigo_barras . $f421_id_ext1_detalle . $f421_id_ext2_detalle . $f421_id_un_movto;
            $xmlData .= "\r\n";
        }

        // ==================== LINEA 4 ====================
        $contador_lineas++;
        $numero_linea = '';
        if ($contador_lineas <= 9) {
            $numero_linea = '0' . $contador_lineas;
        } else {
            $numero_linea = $contador_lineas;
        }

        $xmlData .= "00000" . $numero_linea . "99990001001\r\n";

        // Nombre del archivo
        $nombreArchivo = 'OrdenCompraServicio_' . $temporal . '.txt';

        // Descargar archivo
        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setBody($xmlData);
    }
}
