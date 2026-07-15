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
        $tiers    = PC_Trade_Data::get_price_tiers_raw( $post->ID );
        $sg       = PC_Trade_Data::get_specific_gravity( $post->ID );
        $currency = get_option( 'pc_currency_symbol', '$' );
        ?>
        <p class="description">
            <?php esc_html_e( 'Optional supplier pricing. Each row is one quantity break. Set the Unit (Kg or L), the Quantity it applies from, and fill in EITHER a Price / kg OR a Pack price (total) — not both. Use Price / kg for supplier lists quoted per kg at quantity ranges (e.g. 1–4 kg = 1053.08/kg, 5–9 kg = 956.92/kg): costing buys the exact kg needed at the applicable rate. Use Pack price for fixed pack sizes bought whole (e.g. a 20 kg pack for 3750): costing buys whole packs and can combine sizes. It always picks the cheapest overall. These replace MOQ. Leave empty to use the single Price/KG field.', 'product-costings' ); ?>
        </p>
        <p>
            <label>
                <strong><?php esc_html_e( 'Specific Gravity (kg/L)', 'product-costings' ); ?></strong>
                <input type="number" step="any" min="0" id="pc-specific-gravity" name="pc_specific_gravity" value="<?php echo esc_attr( $sg ? $sg : '' ); ?>" style="width:100px;" placeholder="0.95">
            </label>
            <span class="description" id="pc-sg-note"><?php esc_html_e( 'Density relative to water. Required to convert litre pricing to per-kg. Becomes active when any price break uses L.', 'product-costings' ); ?></span>
        </p>
        <table class="widefat striped" id="pc-price-tier-table" style="max-width:620px;">
            <thead>
                <tr>
                    <th style="width:24px;">&nbsp;</th>
                    <th style="width:70px;"><?php esc_html_e( 'Unit', 'product-costings' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Qty from', 'product-costings' ); ?></th>
                    <th style="width:110px;"><?php esc_html_e( 'Price / kg', 'product-costings' ); ?></th>
                    <th style="width:110px;"><?php esc_html_e( 'Pack price', 'product-costings' ); ?></th>
                    <th style="width:130px;"><?php esc_html_e( '≈ Price / kg', 'product-costings' ); ?></th>
                    <th style="width:40px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="pc-price-tier-body">
                <?php
                if ( empty( $tiers ) ) {
                    $tiers = array( array( 'qty' => '', 'unit' => 'kg', 'price_per_kg' => '', 'pack_price' => '' ) );
                }
                foreach ( $tiers as $i => $tier ) :
                    $v_perkg = ! empty( $tier['price_per_kg'] ) ? $tier['price_per_kg'] : '';
                    $v_pack  = ! empty( $tier['pack_price'] ) ? $tier['pack_price'] : '';
                    ?>
                    <tr>
                        <td class="pc-tier-drag" title="<?php esc_attr_e( 'Drag to reorder', 'product-costings' ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>
                        <td>
                            <select name="pc_price_tiers[<?php echo (int) $i; ?>][unit]" class="pc-tier-unit">
                                <option value="kg" <?php selected( $tier['unit'], 'kg' ); ?>><?php esc_html_e( 'Kg', 'product-costings' ); ?></option>
                                <option value="L" <?php selected( $tier['unit'], 'L' ); ?>><?php esc_html_e( 'L', 'product-costings' ); ?></option>
                            </select>
                        </td>
                        <td><input type="number" step="any" min="0" name="pc_price_tiers[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $tier['qty'] ); ?>" class="widefat pc-tier-qty" placeholder="1"></td>
                        <td><input type="number" step="any" min="0" name="pc_price_tiers[<?php echo (int) $i; ?>][price_per_kg]" value="<?php echo esc_attr( $v_perkg ); ?>" class="widefat pc-tier-perkg-input" placeholder="—"></td>
                        <td><input type="number" step="any" min="0" name="pc_price_tiers[<?php echo (int) $i; ?>][pack_price]" value="<?php echo esc_attr( $v_pack ); ?>" class="widefat pc-tier-pack-input" placeholder="—"></td>
                        <td class="pc-tier-effkg">&mdash;</td>
                        <td><button type="button" class="button pc-tier-remove">&times;</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p id="pc-tier-both-warning" style="display:none;color:#d63638;font-weight:600;">
            <?php esc_html_e( '⚠ A row has both a Price / kg and a Pack price. Enter only one per row.', 'product-costings' ); ?>
        </p>
        <p><button type="button" class="button" id="pc-tier-add"><?php esc_html_e( '+ Add Price Break', 'product-costings' ); ?></button></p>

        <script>
        jQuery(function ($) {
            var currency = <?php echo wp_json_encode( $currency ); ?>;

            function rowMarkup(idx) {
                return '<tr>' +
                    '<td class="pc-tier-drag" title="<?php echo esc_js( __( 'Drag to reorder', 'product-costings' ) ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>' +
                    '<td><select name="pc_price_tiers[' + idx + '][unit]" class="pc-tier-unit">' +
                        '<option value="kg"><?php echo esc_js( __( 'Kg', 'product-costings' ) ); ?></option>' +
                        '<option value="L"><?php echo esc_js( __( 'L', 'product-costings' ) ); ?></option>' +
                    '</select></td>' +
                    '<td><input type="number" step="any" min="0" name="pc_price_tiers[' + idx + '][qty]" class="widefat pc-tier-qty"></td>' +
                    '<td><input type="number" step="any" min="0" name="pc_price_tiers[' + idx + '][price_per_kg]" class="widefat pc-tier-perkg-input" placeholder="—"></td>' +
                    '<td><input type="number" step="any" min="0" name="pc_price_tiers[' + idx + '][pack_price]" class="widefat pc-tier-pack-input" placeholder="—"></td>' +
                    '<td class="pc-tier-effkg">&mdash;</td>' +
                    '<td><button type="button" class="button pc-tier-remove">&times;</button></td>' +
                    '</tr>';
            }

            function refresh() {
                var $sg   = $('#pc-specific-gravity');
                var sg    = parseFloat($sg.val()) || 0;
                var anyL  = false;
                var anyBoth = false;

                $('#pc-price-tier-body tr').each(function () {
                    var unit   = $(this).find('.pc-tier-unit').val();
                    var qty    = parseFloat($(this).find('.pc-tier-qty').val()) || 0;
                    var perkg  = parseFloat($(this).find('.pc-tier-perkg-input').val()) || 0;
                    var pack   = parseFloat($(this).find('.pc-tier-pack-input').val()) || 0;
                    var $cell  = $(this).find('.pc-tier-effkg');
                    if (unit === 'L') { anyL = true; }

                    // Warn if both price types are filled on one row.
                    if (perkg > 0 && pack > 0) {
                        anyBoth = true;
                        $cell.html('<span style="color:#d63638;font-weight:600;">use one</span>');
                        return;
                    }

                    if (perkg > 0) {
                        $cell.text(currency + perkg.toFixed(2) + '/kg');
                        return;
                    }

                    if (pack > 0 && qty > 0) {
                        var qKg = qty;
                        if (unit === 'L') {
                            if (sg > 0) { qKg = qty * sg; }
                            else { $cell.html('<em>set SG</em>'); return; }
                        }
                        $cell.text(currency + (pack / qKg).toFixed(2) + '/kg');
                        return;
                    }

                    $cell.text('—');
                });

                $('#pc-tier-both-warning').toggle(anyBoth);

                // Specific Gravity field only active when a litre break exists.
                if (anyL) {
                    $sg.prop('readonly', false).css({opacity: 1});
                    $('#pc-sg-note').css('color', sg > 0 ? '' : '#d63638');
                } else {
                    $sg.prop('readonly', true).css({opacity: 0.5});
                    $('#pc-sg-note').css('color', '');
                }
            }

            $('#pc-tier-add').on('click', function () {
                $('#pc-price-tier-body').append(rowMarkup($('#pc-price-tier-body tr').length));
                refresh();
            });
            $('#pc-price-tier-table').on('click', '.pc-tier-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });
            $('#pc-price-tier-table').on('input change', '.pc-tier-unit, .pc-tier-qty, .pc-tier-perkg-input, .pc-tier-pack-input', refresh);
            $('#pc-specific-gravity').on('input change', refresh);

            // Drag to reorder price breaks (display only — costing sorts by pack size).
            if ($.fn.sortable) {
                $('#pc-price-tier-body').sortable({ handle: '.pc-tier-drag', axis: 'y', opacity: 0.7 });
            }

            refresh();
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

        // Bulk pricing tiers. Each row is either a per-kg quantity break or a
        // pack price. If both are entered, the per-kg value takes precedence.
        $raw_tiers   = isset( $_POST['pc_price_tiers'] ) && is_array( $_POST['pc_price_tiers'] ) ? wp_unslash( $_POST['pc_price_tiers'] ) : array();
        $clean_tiers = array();
        foreach ( $raw_tiers as $tier ) {
            $qty   = floatval( $tier['qty'] ?? 0 );
            $unit  = ( isset( $tier['unit'] ) && 'L' === $tier['unit'] ) ? 'L' : 'kg';
            $perkg = floatval( $tier['price_per_kg'] ?? 0 );
            $pack  = floatval( $tier['pack_price'] ?? 0 );

            if ( $qty <= 0 || ( $perkg <= 0 && $pack <= 0 ) ) {
                continue;
            }
            if ( $perkg > 0 ) {
                $pack = 0; // Per-kg wins if both were entered.
            }
            $clean_tiers[] = array(
                'qty'          => $qty,
                'unit'         => $unit,
                'price_per_kg' => $perkg,
                'pack_price'   => $pack,
            );
        }
        if ( empty( $clean_tiers ) ) {
            delete_post_meta( $post_id, '_pc_price_tiers' );
        } else {
            update_post_meta( $post_id, '_pc_price_tiers', $clean_tiers );
        }

        // Specific gravity (kg/L) — needed to convert litre pricing to per-kg.
        $sg = isset( $_POST['pc_specific_gravity'] ) ? floatval( wp_unslash( $_POST['pc_specific_gravity'] ) ) : 0;
        if ( $sg > 0 ) {
            update_post_meta( $post_id, '_pc_specific_gravity', $sg );
        } else {
            delete_post_meta( $post_id, '_pc_specific_gravity' );
        }
    }
}
