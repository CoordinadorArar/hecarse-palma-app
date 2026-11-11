<?php

namespace App\Controllers\api;

use App\Controllers\BaseController;
use App\Models\BasculaModel;
use CodeIgniter\HTTP\ResponseInterface;

class BasculaController extends BaseController
{
    private const TABLAS = [
        'registros' => [
            'destino'  => 'bBasculaRegistro',
            'llave'    => 'numero',
            'fechas'   => ['f_entrada', 'f_salida'],
            'columnas' => [
                'numero', 'placa', 'cliente', 'nit', 'direccion', 'telefono', 'conductor', 'cedula', 'origen', 'destino',
                'transportista', 'observaciones', 'producto', 'codigo', 'proceso', 'f_entrada', 'f_salida', 'tipo',
                'pesada1', 'pesada2', 'peso_neto', 'estado', 'usuario', 'n_registro', 'costo', 'camion',
                'eje1', 'eje2', 'eje3', 'eje4', 'eje5', 'eje6', 'eje7', 'eje8', 'ctda_eje',
            ],
        ],
    ];

    public function receiveData(): ResponseInterface
    {
        $inicio = hrtime(true);
        $modelo = new BasculaModel();
        $log = ['ip' => $this->request->getIPAddress()];
        $responder = function (array $json, int $estado) use (&$log, $inicio, $modelo): ResponseInterface {
            $modelo->registrarLog($log + [
                'estadoHttp' => $estado,
                'ok'         => $estado === 200 ? 1 : 0,
                'error'      => $json['error'] ?? null,
                'duracionMs' => intdiv(hrtime(true) - $inicio, 1000000),
                'detalle'    => in_array($estado, [400, 500], true) ? mb_substr(mb_scrub((string) $this->request->getBody(), 'UTF-8'), 0, 4000) : null,
            ]);

            return $this->response->setJSON($json)->setStatusCode($estado);
        };

        $token = (string) env('bascula.token');

        if ($token === '' || !hash_equals($token, $this->request->getHeaderLine('X-Api-Token'))) {
            return $responder(['ok' => false, 'error' => 'no autorizado'], 401);
        }

        $datos = json_decode((string) $this->request->getBody(), true);

        if (!is_array($datos) || empty($datos['tabla']) || !is_string($datos['tabla']) || !isset($datos['filas']) || !is_array($datos['filas'])) {
            return $responder(['ok' => false, 'error' => 'payload inválido'], 400);
        }

        $partes = explode('.', $datos['tabla']);
        $tabla = strtolower(trim(end($partes), " []\t\r\n"));
        $lote = (int) ($datos['lote'] ?? 0);
        $log['tabla'] = mb_substr($tabla, 0, 50);
        $log['lote'] = $lote;

        if (!isset(self::TABLAS[$tabla])) {
            return $responder(['ok' => false, 'error' => 'tabla no soportada'], 400);
        }

        $fincas = $modelo->fincas();
        $cos = array_column($fincas, 'co', 'codigo');
        $codigo = $datos['codigoFinca'] ?? null;
        $codigo = is_string($codigo) || is_int($codigo) ? trim((string) $codigo) : '';
        $coEquipo = $codigo === '' ? '' : str_pad($codigo, 3, '0', STR_PAD_LEFT);
        $log['codigoFinca'] = mb_substr($coEquipo, 0, 10) ?: null;

        if ($coEquipo === '' || !in_array($coEquipo, $cos, true)) {
            return $responder(['ok' => false, 'error' => 'codigoFinca inválido'], 400);
        }

        if (!$modelo->columnasFinca()) {
            return $responder(['ok' => false, 'error' => 'pendiente actualización de base de datos'], 503);
        }

        $porLargo = BasculaModel::porLargo($fincas);
        $config = self::TABLAS[$tabla];
        $filas = [];
        $filasOmitidas = [];
        $numerosFecha = [];
        $fechasInvalidas = 0;

        foreach ($datos['filas'] as $i => $fila) {
            $numero = is_array($fila) ? filter_var($fila[$config['llave']] ?? null, FILTER_VALIDATE_INT) : false;

            if ($numero === false || isset($filas[$numero])) {
                $filasOmitidas[] = $i;

                continue;
            }

            $registro = [];

            foreach ($config['columnas'] as $columna) {
                $valor = $fila[$columna] ?? null;
                $registro[$columna] = is_scalar($valor) ? $valor : null;
            }

            foreach ($config['fechas'] as $columna) {
                $fecha = is_string($registro[$columna]) && $registro[$columna] !== '' ? \DateTime::createFromFormat('Y-m-d g:i:s A', trim($registro[$columna])) : false;

                if (!$fecha && is_string($registro[$columna]) && trim($registro[$columna]) !== '') {
                    $fechasInvalidas++;
                    $numerosFecha[$numero] = $numero;
                }

                $registro[$columna] = $fecha ? $fecha->format('Y-m-d\TH:i:s') : null;
            }

            $registro[$config['llave']] = $numero;
            $registro['lote'] = $lote;
            $registro['coEquipo'] = $coEquipo;
            $registro['coFinca'] = BasculaModel::coFinca((string) $registro['origen'], $coEquipo, $porLargo, $cos);
            $filas[$numero] = $registro;
        }

        $log += [
            'recibidas'       => count($datos['filas']),
            'fechasInvalidas' => $fechasInvalidas,
            'numeroMin'       => $filas ? min(array_keys($filas)) : null,
            'numeroMax'       => $filas ? max(array_keys($filas)) : null,
        ];

        $insertadas = $modelo->insertarNuevos($config['destino'], $config['llave'], array_values($filas), ['coEquipo' => $coEquipo]);

        if ($insertadas === null) {
            $log['error'] = mb_substr(implode(': ', array_filter(['no se pudo guardar el lote', $modelo->ultimoError])), 0, 500);

            return $responder(['ok' => false, 'error' => 'no se pudo guardar el lote'], 500);
        }

        $respuesta = [
            'ok'          => true,
            'tabla'       => $tabla,
            'lote'        => $lote,
            'codigoFinca' => $coEquipo,
            'recibidas'   => count($datos['filas']),
            'insertadas'  => $insertadas,
            'existentes'  => count($filas) - $insertadas,
            'omitidas'    => count($datos['filas']) - count($filas),
        ];
        $log += ['insertadas' => $insertadas, 'existentes' => $respuesta['existentes'], 'omitidas' => $respuesta['omitidas']];

        if ($filasOmitidas || $numerosFecha) {
            $log['detalle'] = json_encode(['filasOmitidas' => array_slice($filasOmitidas, 0, 100), 'numerosFechaInvalida' => array_slice(array_values($numerosFecha), 0, 100)]);
        }

        return $responder($respuesta, 200);
    }
}
