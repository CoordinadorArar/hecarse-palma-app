<?php

if (!function_exists('asset_url')) {
    function asset_url(string $ruta): string
    {
        $archivo = FCPATH . $ruta;
        return is_file($archivo) ? base_url($ruta) . '?v=' . filemtime($archivo) : base_url($ruta);
    }
}
