<?php
/**
 * Plugin Name: ExoBooking Core
 * Description: Sistema anti-overbooking para reservas de passeios
 * Version:     1.0.0
 * Author:      Gabriel Rodrigues
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EXOBOOKING_VERSION', '1.0.0' );
define( 'EXOBOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'EXOBOOKING_URL',  plugin_dir_url( __FILE__ ) );  // Reservado para assets futuros (CSS/JS)

require_once EXOBOOKING_PATH . 'includes/class-database.php';
require_once EXOBOOKING_PATH . 'includes/class-post-type.php';
require_once EXOBOOKING_PATH . 'includes/class-api.php';
require_once EXOBOOKING_PATH . 'admin/class-admin.php';

// ---------------------------------------------------------------
// ATIVAÇÃO
// Cria tabelas, seed e grava flag para flush seguro no próximo init
// ---------------------------------------------------------------
register_activation_hook( __FILE__, function () {
    ExoBooking_Database::create_tables();
    ExoBooking_Database::seed_test_data();

    if ( get_option( 'permalink_structure' ) === '' ) {
        update_option( 'permalink_structure', '/%postname%/' );
    }

    update_option( 'exobooking_flush_rewrite', true );
} );


// ---------------------------------------------------------------
// DESATIVAÇÃO
// ---------------------------------------------------------------
register_deactivation_hook( __FILE__, function () {
    delete_option( 'exobooking_flush_rewrite' );
} );

// ---------------------------------------------------------------
// INIT — executa a cada requisição
// ---------------------------------------------------------------
add_action( 'init', function () {
    ExoBooking_Post_Type::register();

    if ( get_option( 'exobooking_flush_rewrite' ) ) {
        flush_rewrite_rules();
        delete_option( 'exobooking_flush_rewrite' );
    }

}, 10 );

// REST API
add_action( 'rest_api_init', [ 'ExoBooking_API', 'register_routes' ] );

// Admin
add_action( 'admin_menu', [ 'ExoBooking_Admin', 'register_menu' ] );