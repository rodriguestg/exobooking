<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ExoBooking_Admin {

    /**
     * Registra o menu no wp-admin.
     */
    public static function register_menu() {
        add_menu_page(
            'ExoBooking — Reservas',
            'ExoBooking',
            'manage_options',
            'exobooking-bookings',
            [ __CLASS__, 'render_page' ],
            'dashicons-calendar-alt',
            30
        );
    }

    /**
     * Renderiza a listagem de reservas.
     */
    public static function render_page() {
        global $wpdb;

        $bookings = $wpdb->get_results(
            "SELECT b.*, p.post_title as passeio_name
             FROM {$wpdb->prefix}exobooking_bookings b
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.passeio_id
             ORDER BY b.created_at DESC"
        );
        ?>
        <div class="wrap">
            <h1>ExoBooking — Reservas</h1>

            <?php if ( empty( $bookings ) ) : ?>
                <p>Nenhuma reserva encontrada.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Passeio</th>
                            <th>Data</th>
                            <th>Qtd</th>
                            <th>Status</th>
                            <th>Criado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $bookings as $b ) : ?>
                        <tr>
                            <td><?php echo intval( $b->id ); ?></td>
                            <td><?php echo esc_html( $b->customer_name ); ?></td>
                            <td><?php echo esc_html( $b->customer_email ); ?></td>
                            <td><?php echo esc_html( $b->passeio_name ?? "Passeio #{$b->passeio_id}" ); ?></td>
                            <td><?php echo esc_html( $b->booking_date ); ?></td>
                            <td><?php echo intval( $b->quantity ); ?></td>
                            <td>
                                <span style="color:<?php echo $b->status === 'confirmed' ? 'green' : 'red'; ?>">
                                    <?php echo esc_html( $b->status ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $b->created_at ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}