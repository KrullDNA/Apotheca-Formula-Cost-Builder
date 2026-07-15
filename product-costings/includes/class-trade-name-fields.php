<?php
/**
 * Metaboxes on the Trade Names CPT:
 * - Formulation Data: INCI composition repeater + usage rate limits.
 * - Where Used: reverse lookup of products using this trade name.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Trade_Name_Fields {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
        add_action( 'save_post_trade-names', array( $this, 'save_meta' ), 10, 2 );
    }

    public function register_metaboxes() {
        add_meta_box(
            'pc_trade_formulation',
            __( 'Formulation Data (Product Costings)', 'product-costings' ),
            array( $this, 'render_formulation_metabox' ),
            'trade-names',
            'normal',
            'default'
        );

        add_meta_box(
            'pc_trade_pricing',
            __( 'Bulk Pricing (quantity breaks)', 'product-costings' ),
            array( $this, 'render_pricing_metabox' ),
            'trade-names',
            'normal',
            'default'
        );

        add_meta_box(
            'pc_trade_where_used',
            __( 'Where Used', 'product-costings' ),
            array( $this, 'render_where_used_metabox' ),
            'trade-names',
            'normal',
            'default'
        );
    }

    /* ───────────────────────────────────────────────
     * Bulk Pricing (quantity breaks)
     * ─────────────────────────────────────────────── */

    public function render_pricing_metabox( $post ) {
        // Nonce is shared with the Formulation Data metabox (same edit form).
        $tiers = PC_Trade_Data::get_price_tiers( $post->ID );
        ?>
        <p class="description">
            <?php esc_html_e( 'Optional supplier price breaks. Enter the price per kg at each purchase quantity, e.g. 1 kg = 50, 5 kg = 40, 20 kg = 30. When a batch requires (after MOQ rounding) at least a tier\'s quantity, that tier\'s price per kg is used — so scaling a batch up automatically picks up bulk pricing. Leave empty to use the single Price/KG field for all quantities.', 'product-costings' ); ?>
        </p>
        <table class="widefat striped" id="pc-price-tier-table" style="max-width:420px;">
            <thead>
                <tr>
                    <th style="width:160px;"><?php esc_html_e( 'Quantity from (kg)', 'product-costings' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Price per kg', 'product-costings' ); ?></th>
                    <th style="width:50px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="pc-price-tier-body">
                <?php
                if ( empty( $tiers ) ) {
                    $tiers = array( array( 'qty' => '', 'price' => '' ) );
                }
                foreach ( $tiers as $i => $tier ) :
                    ?>
                    <tr>
                        <td><input type="number" step="any" min="0" name="pc_price_tiers[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $tier['qty'] ); ?>" class="widefat" placeholder="1"></td>
                        <td><input type="number" step="any" min="0" name="pc_price_tiers[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( $tier['price'] ); ?>" class="widefat" placeholder="50.00"></td>
                        <td><button type="button" class="button pc-tier-remove">&times;</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="pc-tier-add"><?php esc_html_e( '+ Add Price Break', 'product-costings' ); ?></button></p>

        <script>
        jQuery(function ($) {
            $('#pc-tier-add').on('click', function () {
                var idx = $('#pc-price-tier-body tr').length;
                $('#pc-price-tier-body').append(
                    '<tr>' +
                    '<td><input type="number" step="any" min="0" name="pc_price_tiers[' + idx + '][qty]" class="widefat"></td>' +
                    '<td><input type="number" step="any" min="0" name="pc_price_tiers[' + idx + '][price]" class="widefat"></td>' +
                    '<td><button type="button" class="button pc-tier-remove">&times;</button></td>' +
                    '</tr>'
                );
            });
            $('#pc-price-tier-table').on('click', '.pc-tier-remove', function () {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Formulation Data: INCI composition + usage limits
     * ─────────────────────────────────────────────── */

    public function render_formulation_metabox( $post ) {
        wp_nonce_field( 'pc_save_trade_fields', 'pc_trade_fields_nonce' );

        $composition = PC_Trade_Data::get_composition( $post->ID );
        $usage_min   = get_post_meta( $post->ID, '_pc_usage_min', true );
        $usage_max   = get_post_meta( $post->ID, '_pc_usage_max', true );
        ?>
        <h4><?php esc_html_e( 'INCI Composition', 'product-costings' ); ?></h4>
        <p class="description">
            <?php esc_html_e( 'The INCI name(s) this raw material contributes to the label declaration, with each one\'s percentage of the raw material. Enter a Min–Max range from the SDS; the midpoint is used for label ordering and each material\'s constituents are automatically normalised to total 100%. For a single-substance material add one row at 100. If this Trade Name already has a plain-text INCI field, it is detected automatically and pre-filled below.', 'product-costings' ); ?>
        </p>
        <table class="widefat striped" id="pc-inci-comp-table" style="max-width:640px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'INCI Name', 'product-costings' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Min % of material', 'product-costings' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Max % of material', 'product-costings' ); ?></th>
                    <th style="width:60px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="pc-inci-comp-body">
                <?php
                if ( empty( $composition ) ) {
                    $composition = array( array( 'inci' => '', 'percent_min' => 100, 'percent_max' => 100 ) );
                }
                foreach ( $composition as $i => $row ) :
                    $r_min = isset( $row['percent_min'] ) ? $row['percent_min'] : ( isset( $row['percent'] ) ? $row['percent'] : '' );
                    $r_max = isset( $row['percent_max'] ) ? $row['percent_max'] : ( isset( $row['percent'] ) ? $row['percent'] : '' );
                    ?>
                    <tr>
                        <td><input type="text" name="pc_inci_rows[<?php echo (int) $i; ?>][inci]" value="<?php echo esc_attr( $row['inci'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Glycerin', 'product-costings' ); ?>"></td>
                        <td><input type="number" name="pc_inci_rows[<?php echo (int) $i; ?>][percent_min]" value="<?php echo esc_attr( $r_min ); ?>" step="any" min="0" max="100" class="widefat"></td>
                        <td><input type="number" name="pc_inci_rows[<?php echo (int) $i; ?>][percent_max]" value="<?php echo esc_attr( $r_max ); ?>" step="any" min="0" max="100" class="widefat"></td>
                        <td><button type="button" class="button pc-inci-remove">&times;</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="pc-inci-add"><?php esc_html_e( '+ Add INCI Row', 'product-costings' ); ?></button></p>

        <h4><?php esc_html_e( 'Usage Rate Limits', 'product-costings' ); ?></h4>
        <p class="description">
            <?php esc_html_e( 'Recommended usage range in a finished formula (% w/w). The formula builder shows a live warning when this material is used outside this range. Leave blank for no limit.', 'product-costings' ); ?>
        </p>
        <p>
            <label><?php esc_html_e( 'Min %', 'product-costings' ); ?>
                <input type="number" name="pc_usage_min" value="<?php echo esc_attr( $usage_min ); ?>" step="any" min="0" max="100" style="width:90px;">
            </label>
            &nbsp;&nbsp;
            <label><?php esc_html_e( 'Max %', 'product-costings' ); ?>
                <input type="number" name="pc_usage_max" value="<?php echo esc_attr( $usage_max ); ?>" step="any" min="0" max="100" style="width:90px;">
            </label>
        </p>

        <script>
        jQuery(function ($) {
            $('#pc-inci-add').on('click', function () {
                var idx = $('#pc-inci-comp-body tr').length;
                $('#pc-inci-comp-body').append(
                    '<tr>' +
                    '<td><input type="text" name="pc_inci_rows[' + idx + '][inci]" class="widefat"></td>' +
                    '<td><input type="number" name="pc_inci_rows[' + idx + '][percent_min]" value="" step="any" min="0" max="100" class="widefat"></td>' +
                    '<td><input type="number" name="pc_inci_rows[' + idx + '][percent_max]" value="" step="any" min="0" max="100" class="widefat"></td>' +
                    '<td><button type="button" class="button pc-inci-remove">&times;</button></td>' +
                    '</tr>'
                );
            });
            $('#pc-inci-comp-table').on('click', '.pc-inci-remove', function () {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Where Used
     * ─────────────────────────────────────────────── */

    public function render_where_used_metabox( $post ) {
        $usages = PC_Trade_Data::get_products_using( $post->ID );

        if ( empty( $usages ) ) {
            echo '<p>' . esc_html__( 'This trade name is not used in any product formula yet.', 'product-costings' ) . '</p>';
            return;
        }

        $total_kg = 0;
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( '% w/w', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( 'Kg per batch', 'product-costings' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $usages as $u ) : ?>
                    <?php $total_kg += $u['kg_per_batch']; ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $u['product_id'] ) ); ?>">
                                <?php echo esc_html( get_the_title( $u['product_id'] ) ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( number_format( $u['percent_w_w'], 2 ) . '%' ); ?></td>
                        <td><?php echo esc_html( number_format( $u['kg_per_batch'], 3 ) . ' kg' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong><?php esc_html_e( 'Total across one batch of every product', 'product-costings' ); ?></strong></td>
                    <td><strong><?php echo esc_html( number_format( $total_kg, 3 ) . ' kg' ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <p class="description">
            <?php esc_html_e( 'Use this before discontinuing or re-sourcing a material — every product listed here will need reviewing.', 'product-costings' ); ?>
        </p>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Save
     * ─────────────────────────────────────────────── */

    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['pc_trade_fields_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_trade_fields_nonce'] ) ), 'pc_save_trade_fields' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // INCI composition (Min–Max % of material per INCI).
        $raw   = isset( $_POST['pc_inci_rows'] ) && is_array( $_POST['pc_inci_rows'] ) ? wp_unslash( $_POST['pc_inci_rows'] ) : array();
        $clean = array();
        foreach ( $raw as $row ) {
            $inci = sanitize_text_field( $row['inci'] ?? '' );
            if ( '' === $inci ) {
                continue;
            }

            $min = isset( $row['percent_min'] ) && '' !== $row['percent_min'] ? floatval( $row['percent_min'] ) : null;
            $max = isset( $row['percent_max'] ) && '' !== $row['percent_max'] ? floatval( $row['percent_max'] ) : null;

            if ( null === $min && null === $max ) {
                continue;
            }
            if ( null === $min ) {
                $min = $max;
            }
            if ( null === $max ) {
                $max = $min;
            }
            if ( $max < $min ) {
                $tmp = $min;
                $min = $max;
                $max = $tmp;
            }

            $clean[] = array(
                'inci'        => $inci,
                'percent_min' => $min,
                'percent_max' => $max,
            );
        }
        update_post_meta( $post_id, '_pc_inci_composition', $clean );

        // Usage limits ('' = no limit).
        foreach ( array( 'pc_usage_min' => '_pc_usage_min', 'pc_usage_max' => '_pc_usage_max' ) as $field => $meta_key ) {
            $val = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            if ( '' === $val ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, floatval( $val ) );
            }
        }

        // Bulk pricing tiers.
        $raw_tiers   = isset( $_POST['pc_price_tiers'] ) && is_array( $_POST['pc_price_tiers'] ) ? wp_unslash( $_POST['pc_price_tiers'] ) : array();
        $clean_tiers = array();
        foreach ( $raw_tiers as $tier ) {
            $qty   = floatval( $tier['qty'] ?? 0 );
            $price = floatval( $tier['price'] ?? 0 );
            if ( $qty > 0 && $price > 0 ) {
                $clean_tiers[] = array( 'qty' => $qty, 'price' => $price );
            }
        }
        if ( empty( $clean_tiers ) ) {
            delete_post_meta( $post_id, '_pc_price_tiers' );
        } else {
            update_post_meta( $post_id, '_pc_price_tiers', $clean_tiers );
        }
    }
}
