<?php

use App\Models\LosetasModel;

if (! function_exists('alerta_labores_permitida')) {
    function alerta_labores_permitida(): bool
    {
        $session = session();
        $usuario = (int) $session->get('usu_id');

        if ($usuario <= 0) {
            return false;
        }

        if ($session->has('alerta_labores_permitida')) {
            return (bool) $session->get('alerta_labores_permitida');
        }

        try {
            $permitida = (new LosetasModel())->alertaLaboresPermitida($usuario);
        } catch (\Throwable $e) {
            log_message('error', 'Error validando permiso de alerta de labores: ' . $e->getMessage());

            return false;
        }

        $session->set('alerta_labores_permitida', $permitida);

        return $permitida;
    }
}
