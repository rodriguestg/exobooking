<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ExoBooking_API {

    /**
     * Registra as rotas REST.
     * Chamado no hook rest_api_init.
     */
    public static function register_routes() {
        register_rest_route( 'exobooking/v1', '/bookings', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'passeio_id'    => [ 'required' => true, 'type' => 'integer' ],
                'customer_name' => [ 'required' => true, 'type' => 'string'  ],
                'customer_email'=> [ 'required' => true, 'type' => 'string'  ],
                'booking_date'  => [ 'required' => true, 'type' => 'string'  ],
                'quantity'      => [ 'required' => false, 'type' => 'integer', 'default' => 1 ],
            ],
        ] );

        // Rota auxiliar: lista estoque atual (útil para testes)
        register_rest_route( 'exobooking/v1', '/inventory', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_inventory' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Processa uma nova reserva com proteção anti-overbooking.
     * CRÍTICO: SELECT FOR UPDATE dentro de transação InnoDB.
     */
    public static function create_booking( WP_REST_Request $request ) {
        global $wpdb;

        // 1. Sanitiza e valida inputs
        $passeio_id    = intval( $request->get_param( 'passeio_id' ) );
        $customer_name = sanitize_text_field( $request->get_param( 'customer_name' ) );
        $customer_email= sanitize_email( $request->get_param( 'customer_email' ) );
        $booking_date  = sanitize_text_field( $request->get_param( 'booking_date' ) );
        $quantity      = intval( $request->get_param( 'quantity' ) );

        if ( $quantity < 1 ) $quantity = 1;

        // Valida formato de data
        $date = DateTime::createFromFormat( 'Y-m-d', $booking_date );
        if ( ! $date ) {
            return new WP_Error(
                'invalid_date',
                'Formato de data inválido. Use YYYY-MM-DD.',
                [ 'status' => 400 ]
            );
        }

        // --------------------------------------------------
        // 2. TRANSAÇÃO — A regra de ouro anti-overbooking
        // --------------------------------------------------
        $wpdb->query( 'START TRANSACTION' );

        // SELECT FOR UPDATE: trava esta linha no banco.
        // Outras requisições simultâneas AGUARDAM aqui até o COMMIT/ROLLBACK.
        // É isso que impede o overbooking em race conditions.
        $inventory = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}exobooking_inventory
             WHERE passeio_id = %d AND date = %s
             FOR UPDATE",
            $passeio_id,
            $booking_date
        ) );

        // 3. Valida disponibilidade
        if ( ! $inventory ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error(
                'not_found',
                'Passeio ou data não encontrado.',
                [ 'status' => 404 ]
            );
        }

        if ( $inventory->available_slots < $quantity ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error(
                'no_availability',
                "Vagas insuficientes. Disponível: {$inventory->available_slots}",
                [ 'status' => 409 ]
            );
        }

        // 4. Decrementa o estoque
        $updated = $wpdb->update(
            $wpdb->prefix . 'exobooking_inventory',
            [ 'available_slots' => $inventory->available_slots - $quantity ],
            [ 'id' => $inventory->id ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error(
                'db_error',
                'Erro ao atualizar estoque.',
                [ 'status' => 500 ]
            );
        }

        // 5. Cria a reserva
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'exobooking_bookings',
            [
                'passeio_id'     => $passeio_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'booking_date'   => $booking_date,
                'quantity'       => $quantity,
                'status'         => 'confirmed',
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error(
                'db_error',
                'Erro ao criar reserva.',
                [ 'status' => 500 ]
            );
        }

        // 6. COMMIT — só aqui os dados são gravados de verdade
        $wpdb->query( 'COMMIT' );

        return rest_ensure_response( [
            'success'    => true,
            'booking_id' => $wpdb->insert_id,
            'message'    => 'Reserva confirmada.',
            'remaining'  => $inventory->available_slots - $quantity,
        ] );
    }

    /**
     * Retorna estoque atual.
     */
    public static function get_inventory( WP_REST_Request $request ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT i.*, p.post_title as passeio_name
             FROM {$wpdb->prefix}exobooking_inventory i
             LEFT JOIN {$wpdb->posts} p ON p.ID = i.passeio_id
             ORDER BY i.date ASC"
        );

        return rest_ensure_response( $rows );
    }
}