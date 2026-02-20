<?php
/**
 * Plugin Name: ExoBooking Core
 * Description: Sistema anti-overbooking para reservas de passeios
 * Version: 1.0.0
 * Author: Gabriel Rodrigues
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('EXOBOOKING_VERSION', '1.0.0');
define('EXOBOOKING_PATH', plugin_dir_path(__FILE__));
define('EXOBOOKING_URL', plugin_dir_url(__FILE__));

// Executado uma vez ao ativar o plugin
register_activation_hook(__FILE__, 'exobooking_activate');
function exobooking_activate() {
    flush_rewrite_rules();
}

// Executado ao desativar
register_deactivation_hook(__FILE__, 'exobooking_deactivate');
function exobooking_deactivate() {
    flush_rewrite_rules();
}
