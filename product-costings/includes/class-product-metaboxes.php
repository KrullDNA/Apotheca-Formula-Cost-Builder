<?php
/**
 * Registers and renders metaboxes on the Products CPT edit screen.
 * - Formula Ingredients repeater
 * - Costing & Pricing inputs (batch size, packaging, overheads, multipliers,
 *   pH, method) — stored under the plain meta keys below so the calculator
 *   and any legacy data keep working.
 * - Cost Summary (calculated from the formula + the fields above)
 *
 * Product costing meta keys managed here:
 *   batch_size, labour, facility_running_costs, misc_costs,
 *   packaging_unit_cost, packaging_units_per_batch, unit_size,
 *   final_ph, cost_price, wholesale, rrp, method
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Product_Metaboxes {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
        add_action( 'save_post_products', array( $this, 'save_meta' ), 10, 2 );
        // Priority 99 so it saves after (and wins over) any legacy field plugin.
        add_action( 'save_post_products', array( $this, 'save_costing_meta' ), 99, 2 );
    }

    public function register_metaboxes() {
        add_meta_box(
            'pc_formula_ingredients',
            __( 'Formula Ingredients & Method', 'product-costings' ),
            array( $this, 'render_formula_metabox' ),
            'products',
            'normal',
            'high'
        );

        add_meta_box(
            'pc_costing_inputs',
            __( 'Costing & Pricing', 'product-costings' ),
            array( $this, 'render_costing_metabox' ),
            'products',
            'normal',
            'high'
        );

        add_meta_box(
            'pc_cost_summary',
            __( 'Cost Summary', 'product-costings' ),
            array( $this, 'render_cost_summary_metabox' ),
            'products',
            'normal',
            'default'
        );
    }

    /* ───────────────────────────────────────────────
     * Costing & Pricing inputs
     * ─────────────────────────────────────────────── */

    /**
     * Current value of a costing field: the plain meta key (as used by the
     * calculator and by legacy custom-field plugins), falling back to ACF.
     */
    private function costing_field_value( $post_id, $key ) {
        $val = get_post_meta( $post_id, $key, true );
        if ( ( '' === $val || null === $val ) && function_exists( 'get_field' ) ) {
            $f = get_field( $key, $post_id );
            if ( null !== $f && false !== $f ) {
                $val = $f;
            }
        }
        return $val;
    }

    public function render_costing_metabox( $post ) {
        wp_nonce_field( 'pc_save_costing', 'pc_costing_nonce' );

        // Grouped rows. Each entry is key => label; 'final_ph' renders as text.
        $groups = array(
            array(
                'batch_size' => __( 'Batch Size (kg)', 'product-costings' ),
                'final_ph'   => __( 'Final pH', 'product-costings' ),
            ),
            array(
                'unit_size'           => __( 'Packaging Size (g)', 'product-costings' ),
                'packaging_unit_cost' => __( 'Packaging unit cost', 'product-costings' ),
            ),
            array(
                'labour'                 => __( 'Labour cost', 'product-costings' ),
                'facility_running_costs' => __( 'Facility running cost', 'product-costings' ),
                'misc_costs'             => __( 'Miscellaneous costs', 'product-costings' ),
            ),
            array(
                'cost_price' => __( 'Cost price multiplier', 'product-costings' ),
                'wholesale'  => __( 'Wholesale price multiplier', 'product-costings' ),
                'rrp'        => __( 'RRP multiplier', 'product-costings' ),
            ),
        );
        ?>
        <p class="description"><?php esc_html_e( 'Product costing inputs — these feed the Cost Summary below, the Batch Costings widget and the Costings Dashboard.', 'product-costings' ); ?></p>
        <?php foreach ( $groups as $group ) : ?>
            <div class="pc-costing-row" style="display:flex;gap:16px;margin:0 0 12px;flex-wrap:wrap;">
                <?php foreach ( $group as $key => $label ) :
                    $is_text = ( 'final_ph' === $key );
                    ?>
                    <label style="flex:1 1 160px;display:flex;flex-direction:column;font-weight:600;font-size:12px;">
                        <span><?php echo esc_html( $label ); ?><?php if ( 'final_ph' === $key ) : ?> <span class="pc-ph-window" id="pc-ph-window" style="font-weight:400;"></span><?php endif; ?></span>
                        <input type="<?php echo $is_text ? 'text' : 'number'; ?>"<?php echo $is_text ? '' : ' step="any" min="0"'; ?>
                            name="pc_cost[<?php echo esc_attr( $key ); ?>]"
                            class="pc-cost-field" data-pc-field="<?php echo esc_attr( $key ); ?>"
                            value="<?php echo esc_attr( $this->costing_field_value( $post->ID, $key ) ); ?>"
                            style="width:100%;margin-top:4px;">
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <p class="description"><?php esc_html_e( 'Multipliers set price points from the manufacture unit cost (e.g. Cost price 4 → 4× unit cost). Units per batch are calculated automatically from Batch Size ÷ Packaging Size. The Method field is in the Formula Ingredients & Method box above.', 'product-costings' ); ?></p>
        <?php
    }

    /**
     * Save the Costing & Pricing inputs to their plain meta keys.
     */
    public function save_costing_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['pc_costing_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_costing_nonce'] ) ), 'pc_save_costing' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw = ( isset( $_POST['pc_cost'] ) && is_array( $_POST['pc_cost'] ) ) ? wp_unslash( $_POST['pc_cost'] ) : array();

        $num_keys = array(
            'batch_size', 'unit_size', 'labour', 'facility_running_costs', 'misc_costs',
            'packaging_unit_cost', 'cost_price', 'wholesale', 'rrp',
        );
        foreach ( $num_keys as $key ) {
            if ( ! array_key_exists( $key, $raw ) ) {
                continue;
            }
            $v = trim( (string) $raw[ $key ] );
            if ( '' === $v ) {
                delete_post_meta( $post_id, $key );
            } else {
                update_post_meta( $post_id, $key, floatval( $v ) );
            }
        }

        if ( array_key_exists( 'final_ph', $raw ) ) {
            $v = sanitize_text_field( $raw['final_ph'] );
            if ( '' === $v ) {
                delete_post_meta( $post_id, 'final_ph' );
            } else {
                update_post_meta( $post_id, 'final_ph', $v );
            }
        }
        if ( array_key_exists( 'method', $raw ) ) {
            $v = trim( wp_kses_post( $raw['method'] ) );
            if ( '' === $v || '<p></p>' === $v ) {
                delete_post_meta( $post_id, 'method' );
            } else {
                update_post_meta( $post_id, 'method', $v );
            }
        }
    }

    /* ───────────────────────────────────────────────
     * Formula Ingredients Repeater
     * ─────────────────────────────────────────────── */

    public function render_formula_metabox( $post ) {
        wp_nonce_field( 'pc_save_formula', 'pc_formula_nonce' );

        $rows = get_post_meta( $post->ID, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $functions = PC_Formula_Functions::get_functions();
        ?>
        <div id="pc-formula-wrap">
            <table id="pc-formula-table" class="widefat pc-formula-table">
                <thead>
                    <tr>
                        <th class="pc-col-sort">&nbsp;</th>
                        <th class="pc-col-to100"><?php esc_html_e( 'To 100%', 'product-costings' ); ?></th>
                        <th class="pc-col-phase"><?php esc_html_e( 'Phase', 'product-costings' ); ?></th>
                        <th class="pc-col-ww"><?php esc_html_e( '% w/w', 'product-costings' ); ?></th>
                        <th class="pc-col-trade"><?php esc_html_e( 'Trade Name', 'product-costings' ); ?></th>
                        <th class="pc-col-function"><?php esc_html_e( 'Function', 'product-costings' ); ?></th>
                        <th class="pc-col-ph"><?php esc_html_e( 'pH Range', 'product-costings' ); ?></th>
                        <th class="pc-col-price"><?php esc_html_e( 'Price/KG', 'product-costings' ); ?></th>
                        <th class="pc-col-moq"><?php esc_html_e( 'MOQ', 'product-costings' ); ?></th>
                        <th class="pc-col-nat-origin"><?php esc_html_e( 'Nat. Origin %', 'product-costings' ); ?></th>
                        <th class="pc-col-kgbatch"><?php esc_html_e( 'Kg / batch', 'product-costings' ); ?></th>
                        <th class="pc-col-actions">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="pc-formula-body">
                    <?php
                    if ( ! empty( $rows ) ) {
                        foreach ( $rows as $i => $row ) {
                            $this->render_row( $i, $row, $functions );
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="pc-total-label"><strong><?php esc_html_e( 'Total % w/w:', 'product-costings' ); ?></strong></td>
                        <td id="pc-total-ww"><strong>0.00</strong></td>
                        <td colspan="6"></td>
                        <td id="pc-total-kgbatch"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <p style="margin-top:12px;">
                <button type="button" id="pc-add-row" class="button button-primary">
                    <?php esc_html_e( '+ Add Ingredient', 'product-costings' ); ?>
                </button>
                <button type="button" id="pc-refresh-meta" class="button">
                    <?php esc_html_e( '↻ Refresh Ingredient Data', 'product-costings' ); ?>
                </button>
                <span id="pc-refresh-status"></span>
            </p>

            <div id="pc-formula-warnings"></div>

            <?php $preservative_ack = get_post_meta( $post->ID, '_pc_preservative_ack', true ); ?>
            <p style="margin-top:8px;">
                <label>
                    <input type="checkbox" id="pc-preservative-ack" name="pc_preservative_ack" value="1" <?php checked( $preservative_ack, '1' ); ?>>
                    <?php esc_html_e( 'This formula is anhydrous or self-preserving (no preservative required)', 'product-costings' ); ?>
                </label>
                <span class="description"><?php esc_html_e( 'Tick to acknowledge and hide the "no preservative" reminder. Does not replace CPSR / challenge-test sign-off.', 'product-costings' ); ?></span>
            </p>

            <p style="margin-top:12px;">
                <label>
                    <input type="checkbox" id="pc-save-version" name="pc_save_version" value="1">
                    <strong><?php esc_html_e( 'Save this as a new formula version', 'product-costings' ); ?></strong>
                </label>
                <span class="description"><?php esc_html_e( 'Versions are only saved when you tick this — quick edits won\'t create one. See the Formula Versions box below.', 'product-costings' ); ?></span>
            </p>
            <p>
                <label for="pc-version-note"><strong><?php esc_html_e( 'Version note', 'product-costings' ); ?></strong></label>
                <input type="text" id="pc-version-note" name="pc_version_note" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Increased glycerin to 3%, swapped preservative', 'product-costings' ); ?>" style="width:55%;">
                <span class="description"><?php esc_html_e( 'Optional label, saved with the version when you tick the box above.', 'product-costings' ); ?></span>
            </p>
        </div>

        <div class="pc-method-wrap" style="margin-top:18px;border-top:1px solid #e0e0e0;padding-top:14px;">
            <h3 style="margin:0 0 6px;"><?php esc_html_e( 'Method', 'product-costings' ); ?></h3>
            <p class="description" style="margin-top:0;"><?php esc_html_e( 'Manufacturing method / batch instructions. Rich text — shown on the printable batch sheet.', 'product-costings' ); ?></p>
            <?php
            wp_editor(
                (string) $this->costing_field_value( $post->ID, 'method' ),
                'pc_cost_method',
                array(
                    'textarea_name' => 'pc_cost[method]',
                    'media_buttons' => false,
                    'teeny'         => true,
                    'textarea_rows' => 10,
                )
            );
            ?>
        </div>

        <!-- Row template (hidden) -->
        <script type="text/html" id="tmpl-pc-row">
            <tr class="pc-row" data-index="{{data.i}}">
                <td class="pc-col-sort pc-drag-handle">&#9776;</td>
                <td class="pc-col-to100">
                    <input type="checkbox" name="pc_rows[{{data.i}}][is_to_100]" value="1" class="pc-field-to100">
                </td>
                <td class="pc-col-phase">
                    <input type="text" name="pc_rows[{{data.i}}][phase]" value="" placeholder="A" class="pc-field-phase" maxlength="5">
                </td>
                <td class="pc-col-ww">
                    <input type="text" inputmode="decimal" name="pc_rows[{{data.i}}][percent_w_w]" value="" class="pc-field-ww" placeholder="0.00 / q.s.">
                </td>
                <td class="pc-col-trade">
                    <select name="pc_rows[{{data.i}}][trade_name_id]" class="pc-field-trade-name">
                        <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    </select>
                </td>
                <td class="pc-col-function">
                    <select name="pc_rows[{{data.i}}][function]" class="pc-field-function">
                        <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                        <?php foreach ( $functions as $fn ) : ?>
                            <option value="<?php echo esc_attr( $fn ); ?>"><?php echo esc_html( $fn ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="pc-col-ph">
                    <input type="text" name="pc_rows[{{data.i}}][ph]" value="" class="pc-field-ph" readonly>
                </td>
                <td class="pc-col-price">
                    <input type="text" name="pc_rows[{{data.i}}][price_per_kg]" value="" class="pc-field-price" readonly>
                </td>
                <td class="pc-col-moq">
                    <input type="text" name="pc_rows[{{data.i}}][moq]" value="" class="pc-field-moq" readonly>
                </td>
                <td class="pc-col-nat-origin">
                    <input type="text" name="pc_rows[{{data.i}}][natural_origin]" value="" class="pc-field-natural-origin" readonly>
                </td>
                <td class="pc-col-kgbatch pc-cell-kgbatch">&mdash;</td>
                <td class="pc-col-actions">
                    <button type="button" class="button pc-inci-toggle" title="<?php esc_attr_e( 'INCI breakdown for packaging', 'product-costings' ); ?>"><?php esc_html_e( 'INCI', 'product-costings' ); ?></button>
                    <button type="button" class="button pc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'product-costings' ); ?>">&#x2398;</button>
                    <button type="button" class="button pc-remove-row" title="<?php esc_attr_e( 'Remove', 'product-costings' ); ?>">&#x1F5D1;</button>
                </td>
            </tr>
        </script>
        <?php
    }

    /**
     * Render a single repeater row.
     */
    private function render_row( $i, $row, $functions ) {
        $phase     = isset( $row['phase'] ) ? $row['phase'] : '';
        $ww        = isset( $row['percent_w_w'] ) ? $row['percent_w_w'] : '';
        $trade_id  = isset( $row['trade_name_id'] ) ? (int) $row['trade_name_id'] : 0;
        $fn_val    = isset( $row['function'] ) ? $row['function'] : '';
        $ph        = isset( $row['ph'] ) ? $row['ph'] : ( isset( $row['ph_range'] ) ? $row['ph_range'] : '' );
        $price     = isset( $row['price_per_kg'] ) ? $row['price_per_kg'] : '';
        $moq       = isset( $row['moq'] ) ? $row['moq'] : '';
        $nat_orig  = isset( $row['natural_origin'] ) ? $row['natural_origin'] : '';
        $is_to_100 = isset( $row['is_to_100'] ) ? (bool) $row['is_to_100'] : false;

        // Live data for guardrails + stale-price detection.
        $usage_min = '';
        $usage_max = '';
        $stale     = false;
        $cur_price = '';
        $tiers     = array();
        if ( $trade_id ) {
            $usage_min = PC_Trade_Data::get( $trade_id, 'usage_min' );
            $usage_max = PC_Trade_Data::get( $trade_id, 'usage_max' );
            $cur_price = PC_Trade_Data::get( $trade_id, 'price_per_kg' );
            $tiers     = PC_Trade_Data::get_price_tiers( $trade_id );

            // Reflect the bulk pricing table: MOQ = smallest quantity, Price/KG
            // = per-kg rate at that quantity. Fall back to the snapshot / tn_*
            // fields when the material has no bulk pricing.
            $eff_moq = PC_Trade_Data::get_effective_moq( $trade_id );
            if ( null !== $eff_moq ) {
                $moq = $eff_moq;
            }
            $base_price = PC_Trade_Data::get_base_price_per_kg( $trade_id );
            if ( null !== $base_price ) {
                $price = $base_price;
            }

            if ( '' !== $price && '' !== $cur_price && abs( floatval( $price ) - floatval( $cur_price ) ) > 0.0001 && null === $base_price ) {
                $stale = true;
            }
        }
        ?>
        <tr class="pc-row <?php echo $is_to_100 ? 'pc-row-to100' : ''; ?>" data-index="<?php echo (int) $i; ?>" data-usage-min="<?php echo esc_attr( $usage_min ); ?>" data-usage-max="<?php echo esc_attr( $usage_max ); ?>" data-price-tiers="<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>">
            <td class="pc-col-sort pc-drag-handle">&#9776;</td>
            <td class="pc-col-to100">
                <input type="checkbox" name="pc_rows[<?php echo (int) $i; ?>][is_to_100]" value="1" class="pc-field-to100" <?php checked( $is_to_100 ); ?>>
            </td>
            <td class="pc-col-phase">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][phase]" value="<?php echo esc_attr( $phase ); ?>" placeholder="A" class="pc-field-phase" maxlength="5">
            </td>
            <td class="pc-col-ww">
                <input type="text" inputmode="decimal" name="pc_rows[<?php echo (int) $i; ?>][percent_w_w]" value="<?php echo esc_attr( $ww ); ?>" class="pc-field-ww" placeholder="0.00 / q.s." <?php echo $is_to_100 ? 'readonly' : ''; ?>>
                <?php if ( $is_to_100 ) : ?>
                    <span class="pc-to100-badge"><?php esc_html_e( 'to 100%', 'product-costings' ); ?></span>
                <?php endif; ?>
            </td>
            <td class="pc-col-trade">
                <select name="pc_rows[<?php echo (int) $i; ?>][trade_name_id]" class="pc-field-trade-name">
                    <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    <?php if ( $trade_id ) : ?>
                        <option value="<?php echo $trade_id; ?>" selected><?php echo esc_html( get_the_title( $trade_id ) ); ?></option>
                    <?php endif; ?>
                </select>
            </td>
            <td class="pc-col-function">
                <select name="pc_rows[<?php echo (int) $i; ?>][function]" class="pc-field-function">
                    <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    <?php foreach ( $functions as $fn ) : ?>
                        <option value="<?php echo esc_attr( $fn ); ?>" <?php selected( $fn_val, $fn ); ?>><?php echo esc_html( $fn ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="pc-col-ph">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][ph]" value="<?php echo esc_attr( $ph ); ?>" class="pc-field-ph" readonly>
            </td>
            <td class="pc-col-price">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][price_per_kg]" value="<?php echo esc_attr( $price ); ?>" class="pc-field-price" readonly>
                <?php if ( $stale ) : ?>
                    <span class="pc-stale-badge" title="<?php echo esc_attr( sprintf( __( 'Current Trade Name price is %s — use Refresh Ingredient Data to update.', 'product-costings' ), $cur_price ) ); ?>">!</span>
                <?php endif; ?>
            </td>
            <td class="pc-col-moq">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][moq]" value="<?php echo esc_attr( $moq ); ?>" class="pc-field-moq" readonly>
            </td>
            <td class="pc-col-nat-origin">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][natural_origin]" value="<?php echo esc_attr( $nat_orig ); ?>" class="pc-field-natural-origin" readonly>
            </td>
            <td class="pc-col-kgbatch pc-cell-kgbatch">&mdash;</td>
            <td class="pc-col-actions">
                <button type="button" class="button pc-inci-toggle" title="<?php esc_attr_e( 'INCI breakdown for packaging', 'product-costings' ); ?>"><?php esc_html_e( 'INCI', 'product-costings' ); ?></button>
                <button type="button" class="button pc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'product-costings' ); ?>">&#x2398;</button>
                <button type="button" class="button pc-remove-row" title="<?php esc_attr_e( 'Remove', 'product-costings' ); ?>">&#x1F5D1;</button>
            </td>
        </tr>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Cost Summary (reads existing CPT meta fields)
     * ─────────────────────────────────────────────── */

    public function render_cost_summary_metabox( $post ) {
        $waste = get_post_meta( $post->ID, '_pc_waste_percent', true );
        if ( '' === $waste ) {
            $waste = 2;
        }
        ?>
        <div id="pc-cost-summary" class="pc-cost-summary">
            <p class="description"><?php esc_html_e( 'Values are calculated automatically from the formula ingredients and the product meta fields above. Ingredient purchasing uses each Trade Name\'s bulk pricing tiers — buying up to a cheaper price break when that lowers the total — matching the front-end Batch Costings widget.', 'product-costings' ); ?></p>
            <p>
                <label for="pc-waste-percent"><strong><?php esc_html_e( 'Waste %', 'product-costings' ); ?></strong></label>
                <input type="number" id="pc-waste-percent" name="pc_waste_percent" value="<?php echo esc_attr( $waste ); ?>" step="0.5" min="0" max="50" style="width:70px;">
                <span class="description"><?php esc_html_e( 'Manufacturing waste allowance added to the batch size (e.g. 2% → batch × 1.02). Ingredient quantities and costs use this larger figure, while units per batch use the batch size WITHOUT waste — so the cost of the wasted material is paid for and spread across the sellable units, raising the final unit cost. Set this to the same value as the Batch Costings widget to match front-end figures.', 'product-costings' ); ?></span>
            </p>
            <table class="widefat striped">
                <tr>
                    <th><?php esc_html_e( 'Raw Material Cost per KG', 'product-costings' ); ?></th>
                    <td id="pc-raw-cost-kg">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Ingredient Cost per Batch (bulk pricing)', 'product-costings' ); ?></th>
                    <td id="pc-raw-cost-batch">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Units per Batch', 'product-costings' ); ?></th>
                    <td id="pc-units-batch">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Total Batch Cost', 'product-costings' ); ?></th>
                    <td id="pc-batch-cost">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Cost per Unit', 'product-costings' ); ?></th>
                    <td id="pc-cost-unit">&mdash;</td>
                </tr>
            </table>

            <h4><?php esc_html_e( 'Batch Requirements', 'product-costings' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Per ingredient: kg needed for this batch (including waste %) and the kg to purchase using the cheapest bulk-pricing pack combination, with its line cost.', 'product-costings' ); ?></p>
            <div id="pc-batch-requirements"><em><?php esc_html_e( 'Set Batch Size and add ingredients to see quantities.', 'product-costings' ); ?></em></div>

            <h4><?php esc_html_e( 'Cost Drivers', 'product-costings' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Each ingredient\'s share of formula weight vs its share of raw material cost (at nominal price, before bulk-pricing purchase effects). Big gaps between the two bars show where reformulation saves the most money.', 'product-costings' ); ?></p>
            <div id="pc-cost-drivers"><em><?php esc_html_e( 'Add ingredients with prices to see the breakdown.', 'product-costings' ); ?></em></div>

            <h4><?php esc_html_e( 'Batch Size Sweet Spot', 'product-costings' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Cost per unit at different batch sizes. Because larger batches drop into cheaper bulk price breaks, unit cost is not linear — this shows where the savings kick in. Assumes labour, facility and misc costs are fixed per batch.', 'product-costings' ); ?></p>
            <div id="pc-sweet-spot"><em><?php esc_html_e( 'Requires Batch Size and Unit Size to be set.', 'product-costings' ); ?></em></div>
        </div>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Save
     * ─────────────────────────────────────────────── */

    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // --- Formula rows ---
        if ( isset( $_POST['pc_formula_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_formula_nonce'] ) ), 'pc_save_formula' ) ) {
            $raw_rows = isset( $_POST['pc_rows'] ) && is_array( $_POST['pc_rows'] ) ? wp_unslash( $_POST['pc_rows'] ) : array();
            $clean    = array();

            if ( is_array( $raw_rows ) ) {
                foreach ( $raw_rows as $row ) {
                    $clean[] = array(
                        'phase'          => sanitize_text_field( $row['phase'] ?? '' ),
                        'percent_w_w'    => self::normalize_ww( $row['percent_w_w'] ?? '' ),
                        'trade_name_id'  => absint( $row['trade_name_id'] ?? 0 ),
                        'function'       => sanitize_text_field( $row['function'] ?? '' ),
                        'ph'             => sanitize_text_field( $row['ph'] ?? '' ),
                        'price_per_kg'   => sanitize_text_field( $row['price_per_kg'] ?? '' ),
                        'moq'            => sanitize_text_field( $row['moq'] ?? '' ),
                        'natural_origin' => sanitize_text_field( $row['natural_origin'] ?? '' ),
                        'is_to_100'      => ! empty( $row['is_to_100'] ) ? true : false,
                    );
                }
            }

            update_post_meta( $post_id, '_pc_formula_rows', $clean );

            // --- Waste % (used by the admin Cost Summary calculation) ---
            if ( isset( $_POST['pc_waste_percent'] ) ) {
                $waste = floatval( wp_unslash( $_POST['pc_waste_percent'] ) );
                $waste = max( 0, min( 50, $waste ) );
                update_post_meta( $post_id, '_pc_waste_percent', $waste );
            }

            // --- Preservative acknowledgement (suppresses the "no preservative" reminder) ---
            if ( ! empty( $_POST['pc_preservative_ack'] ) ) {
                update_post_meta( $post_id, '_pc_preservative_ack', '1' );
            } else {
                delete_post_meta( $post_id, '_pc_preservative_ack' );
            }
        }
    }

    /**
     * Normalise a %w/w input: "q.s." (quantum satis, in any casing/spacing)
     * is kept as the string 'q.s.'; anything else becomes a float (invalid
     * text → 0). q.s. rows count as 0 in all calculations.
     *
     * @param mixed $value Raw input.
     * @return string|float 'q.s.' or a float.
     */
    private static function normalize_ww( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return 0;
        }
        if ( preg_match( '/^q\.?\s*s\.?$/i', $value ) ) {
            return 'q.s.';
        }
        return floatval( $value );
    }
}
