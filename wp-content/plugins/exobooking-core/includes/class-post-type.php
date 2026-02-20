<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ExoBooking_Post_Type {

    /**
     * Registra o Custom Post Type "Passeios".
     * Chamado no hook init do WordPress.
     */
    public static function register() {
        $labels = [
            'name'               => 'Passeios',
            'singular_name'      => 'Passeio',
            'add_new'            => 'Novo Passeio',
            'add_new_item'       => 'Adicionar Passeio',
            'edit_item'          => 'Editar Passeio',
            'view_item'          => 'Ver Passeio',
            'all_items'          => 'Todos os Passeios',
            'not_found'          => 'Nenhum passeio encontrado.',
        ];

        $args = [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'supports'     => [ 'title', 'editor', 'thumbnail' ],
            'show_in_rest' => true,   // Habilita no Gutenberg e REST API
            'menu_icon'    => 'dashicons-palmtree',
            'rewrite'      => [ 'slug' => 'passeios' ],
        ];

        register_post_type( 'passeio', $args );
    }
}