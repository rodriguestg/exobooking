<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ExoBooking_Database {

    /**
     * Cria as tabelas customizadas no banco.
     * Chamado no hook de ativação do plugin.
     */
    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        // Tabela de estoque de vagas por passeio/dia
        $inventory = "CREATE TABLE {$wpdb->prefix}exobooking_inventory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            passeio_id BIGINT UNSIGNED NOT NULL,
            date DATE NOT NULL,
            total_slots INT NOT NULL DEFAULT 0,
            available_slots INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_passeio_date (passeio_id, date)
        ) ENGINE=InnoDB $charset;";

        // Tabela de reservas realizadas
        $bookings = "CREATE TABLE {$wpdb->prefix}exobooking_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            passeio_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            booking_date DATE NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_passeio (passeio_id),
            INDEX idx_date (booking_date)
        ) ENGINE=InnoDB $charset;";

        // dbDelta cria a tabela se não existe, ou atualiza se mudou
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $inventory );
        dbDelta( $bookings );
    }

    /**
     * Insere dados de teste para o cenário do overbooking.
     * Passeio ID dinâmico, dia 20/03/2026, 3 vagas.
     */
    public static function seed_test_data() {
        global $wpdb;

        $table = $wpdb->prefix . 'exobooking_inventory';

        // Busca o primeiro passeio cadastrado (dinâmico)
        $passeio_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'passeio'
            AND post_status = 'publish'
            ORDER BY ID ASC
            LIMIT 1"
        );

        // Se não houver passeio publicado, cria um automaticamente
        if ( ! $passeio_id ) {
            $passeio_id = wp_insert_post([
                'post_title'  => 'Trilha da Serra',
                'post_type'   => 'passeio',
                'post_status' => 'publish',
            ]);
        }

        if ( ! $passeio_id || is_wp_error($passeio_id) ) {
            return; // Não conseguiu criar, abort silencioso
        }

        // Evita duplicar se já existir
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE passeio_id = %d AND date = %s",
            $passeio_id,
            '2026-03-20'
        ));

        if ( ! $exists ) {
            $wpdb->insert(
                $table,
                [
                    'passeio_id'      => $passeio_id,
                    'date'            => '2026-03-20',
                    'total_slots'     => 3,
                    'available_slots' => 3,
                ],
                [ '%d', '%s', '%d', '%d' ]
            );
        }
    }
}