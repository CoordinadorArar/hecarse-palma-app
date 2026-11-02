<?php

use App\Models\SiteModel;

if (!function_exists('sitio_defectos')) {
    function sitio_defectos(): array
    {
        return [
            'nombre_app'       => 'ELAEIX',
            'color_primary'    => '#287d55',
            'color_secundario' => '#192d46',
            'logo'             => 'public/assets/img/elaeix_logo.png',
            'logo_claro'       => 'public/assets/img/elaeix_logo_claro.png',
            'favicon'          => 'public/assets/img/elaeix_favicon_64.png',
        ];
    }
}

if (!function_exists('sitio_config')) {
    function sitio_config(bool $recargar = false): array
    {
        static $config = null;

        if ($config !== null && !$recargar) {
            return $config;
        }

        $config = sitio_defectos();

        try {
            $guardada = (new SiteModel())->getConfiguracionCompleta();
        } catch (\Throwable $e) {
            log_message('error', 'Error consultando la configuración del sitio: ' . $e->getMessage());
            $guardada = [];
        }

        foreach ($config as $clave => $defecto) {
            $valor = trim((string) ($guardada[$clave] ?? ''));

            if ($valor === '' || (str_starts_with($clave, 'color_') && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor))) {
                continue;
            }

            $config[$clave] = str_starts_with($clave, 'color_') ? strtolower($valor) : $valor;
        }

        return $config;
    }
}

if (!function_exists('sitio')) {
    function sitio(string $clave): string
    {
        return (string) (sitio_config()[$clave] ?? '');
    }
}

if (!function_exists('sitio_img')) {
    function sitio_img(string $clave): string
    {
        $ruta = sitio($clave);

        if ($ruta === '' || !is_file(FCPATH . $ruta)) {
            $ruta = sitio_defectos()[$clave] ?? '';
        }

        return base_url($ruta);
    }
}

if (!function_exists('sitio_colores')) {
    function sitio_colores(): array
    {
        $rgb = static fn (string $hex): array => array_map('hexdec', str_split(substr($hex, 1), 2));
        $mezcla = static fn (array $c, int $destino, float $peso): string => '#' . implode('', array_map(
            static fn ($v) => sprintf('%02x', (int) round($v + ($destino - $v) * $peso)),
            $c
        ));

        $primary = $rgb(sitio('color_primary'));
        $secundario = $rgb(sitio('color_secundario'));

        return [
            'primary'        => sitio('color_primary'),
            'primary_rgb'    => implode(',', $primary),
            'hover'          => $mezcla($primary, 0, 0.15),
            'active'         => $mezcla($primary, 0, 0.25),
            'subtle'         => $mezcla($primary, 255, 0.90),
            'border'         => $mezcla($primary, 255, 0.70),
            'emphasis'       => $mezcla($primary, 0, 0.45),
            'secundario'     => sitio('color_secundario'),
            'secundario_rgb' => implode(',', $secundario),
        ];
    }
}
