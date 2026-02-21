<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ExoBooking_Meta_Box {

    /**
     * Registra os hooks do meta box.
     * Chamado no init do plugin principal.
     */
    public static function register() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add'  ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ] );
    }

    /**
     * Adiciona o meta box na tela de criação/edição do CPT "passeio".
     * O WordPress exibe automaticamente em ambos os contextos.
     */
    public static function add() {
        add_meta_box(
            'exobooking_slots',
            'Disponibilidade de Vagas',
            [ __CLASS__, 'render' ],
            'passeio',
            'normal',
            'high'
        );
    }

    /**
     * Renderiza o formulário dentro do meta box.
     * Aparece igual na criação e na edição.
     */
    public static function render( $post ) {
        global $wpdb;

        // Nonce de segurança — valida que o submit veio desta tela
        wp_nonce_field( 'exobooking_slots_save', 'exobooking_slots_nonce' );

        // Busca disponibilidades já cadastradas para este passeio
        $slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}exobooking_inventory
             WHERE passeio_id = %d
             ORDER BY date ASC",
            $post->ID
        ) );

        ?>
        <style>
            .exobooking-slots-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            .exobooking-slots-table th,
            .exobooking-slots-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; }
            .exobooking-slots-table th { background: #f9f9f9; font-weight: 600; }
            .exobooking-slots-table tr:hover td { background: #fafafa; }
            .exobooking-add-row { display: flex; gap: 10px; align-items: center; margin-top: 8px; }
            .exobooking-add-row input { padding: 6px 8px; border: 1px solid #ddd; border-radius: 3px; }
            .exobooking-note { color: #666; font-size: 12px; margin-top: 8px; }
        </style>

        <p style="color:#666; font-size:13px;">
            Defina quantas vagas este passeio terá em cada data.
            Cada linha representa um dia independente.
        </p>

        <?php if ( ! empty( $slots ) ) : ?>
            <table class="exobooking-slots-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Total de Vagas</th>
                        <th>Vagas Disponíveis</th>
                        <th>Reservadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $slots as $slot ) :
                        $reserved = $slot->total_slots - $slot->available_slots;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $slot->date ); ?></td>
                        <td><?php echo intval( $slot->total_slots ); ?></td>
                        <td>
                            <span style="color: <?php echo $slot->available_slots > 0 ? 'green' : 'red'; ?>">
                                <?php echo intval( $slot->available_slots ); ?>
                            </span>
                        </td>
                        <td><?php echo intval( $reserved ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="color:#999; font-style:italic;">
                Nenhuma disponibilidade cadastrada ainda.
                Adicione uma data abaixo.
            </p>
        <?php endif; ?>

        <hr>
        <strong>Adicionar disponibilidade para uma data:</strong>

        <div class="exobooking-add-row">
            <label>
                Data:
                <input
                    type="date"
                    name="exobooking_new_date"
                    min="<?php echo date('Y-m-d'); ?>"
                    style="width:160px"
                >
            </label>
            <label>
                Vagas:
                <input
                    type="number"
                    name="exobooking_new_slots"
                    min="1"
                    max="9999"
                    placeholder="Ex: 3"
                    style="width:80px"
                >
            </label>
        </div>

        <p class="exobooking-note">
            ⚠️ Deixe em branco para não adicionar nova data agora.<br>
            Para alterar vagas de uma data existente, use a tela
            <a href="<?php echo admin_url('admin.php?page=exobooking-bookings'); ?>">
                ExoBooking → Reservas
            </a>.
        </p>
        <?php
    }

    /**
     * Salva os dados do meta box quando o passeio é criado ou atualizado.
     */
    public static function save( $post_id ) {
        // 1. Valida nonce (segurança)
        if ( ! isset( $_POST['exobooking_slots_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['exobooking_slots_nonce'], 'exobooking_slots_save' ) ) return;

        // 2. Ignora autosave e posts que não são "passeio"
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( get_post_type( $post_id ) !== 'passeio' ) return;

        // 3. Verifica permissão
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // 4. Pega e valida os campos
        $new_date  = sanitize_text_field( $_POST['exobooking_new_date']  ?? '' );
        $new_slots = intval( $_POST['exobooking_new_slots'] ?? 0 );

        // Só salva se ambos os campos foram preenchidos
        if ( empty( $new_date ) || $new_slots < 1 ) return;

        // Valida formato da data
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $new_date );
        if ( ! $date_obj ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'exobooking_inventory';

        // 5. Verifica se já existe registro para este passeio/data
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE passeio_id = %d AND date = %s",
            $post_id,
            $new_date
        ) );

        if ( $exists ) {
            // Atualiza apenas o total (não mexe nas vagas disponíveis)
            $wpdb->update(
                $table,
                [ 'total_slots' => $new_slots ],
                [ 'id' => $exists ],
                [ '%d' ],
                [ '%d' ]
            );
        } else {
            // Insere novo registro
            $wpdb->insert(
                $table,
                [
                    'passeio_id'      => $post_id,
                    'date'            => $new_date,
                    'total_slots'     => $new_slots,
                    'available_slots' => $new_slots,
                ],
                [ '%d', '%s', '%d', '%d' ]
            );
        }
    }
}