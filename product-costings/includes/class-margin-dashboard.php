<?php
/**
 * Costings Dashboard: margins across all products at a glance, with
 * below-target highlighting and stale-price flags.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Margin_Dashboard {

    private static $instance = null;

    const TARGET_OPTION = 'pc_target_margin';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_target_save' ) );
        add_action( 'admin_init', array( $this, 'handle_import_pricing' ) );
    }

    /* ───────────────────────────────────────────────
     * Import initial bulk pricing from MOQ / Price per kg
     * ─────────────────────────────────────────────── */

    /**
     * Trade names that have a price per kg but no bulk pricing yet.
     */
    public static function count_pricing_import_candidates() {
        $ids   = self::trade_name_ids();
        $count = 0;
        foreach ( $ids as $id ) {
            $existing = get_post_meta( $id, '_pc_price_tiers', true );
            if ( is_array( $existing ) && ! empty( $existing ) ) {
                continue;
            }
            if ( floatval( PC_Trade_Data::get( $id, 'price_per_kg' ) ) > 0 ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Seed the first bulk pricing pack on each eligible trade name from its
     * existing MOQ (pack size, default 1 kg) and price per kg. Idempotent:
     * trade names that already have bulk pricing are skipped.
     *
     * @return int Number updated.
     */
    public static function import_pricing_from_moq() {
        $ids     = self::trade_name_ids();
        $updated = 0;
        foreach ( $ids as $id ) {
            $existing = get_post_meta( $id, '_pc_price_tiers', true );
            if ( is_array( $existing ) && ! empty( $existing ) ) {
                continue;
            }
            $price = floatval( PC_Trade_Data::get( $id, 'price_per_kg' ) );
            if ( $price <= 0 ) {
                continue;
            }
            $moq = floatval( PC_Trade_Data::get( $id, 'moq' ) );
            $qty = $moq > 0 ? $moq : 1;

            // The existing field is a per-kg price; store it as a per-kg break
            // that applies from the MOQ quantity.
            update_post_meta( $id, '_pc_price_tiers', array(
                array( 'qty' => $qty, 'unit' => 'kg', 'price_per_kg' => $price, 'pack_price' => 0 ),
            ) );
            $updated++;
        }
        return $updated;
    }

    private static function trade_name_ids() {
        return get_posts( array(
            'post_type'      => 'trade-names',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
    }

    public function handle_import_pricing() {
        if ( ! isset( $_POST['pc_import_pricing_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_import_pricing_nonce'] ) ), 'pc_import_pricing' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $updated = self::import_pricing_from_moq();
        wp_safe_redirect( admin_url( 'edit.php?post_type=products&page=pc-costings-dashboard&imported=' . (int) $updated ) );
        exit;
    }

    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=products',
            __( 'Costings Dashboard', 'product-costings' ),
            __( 'Costings Dashboard', 'product-costings' ),
            'manage_options',
            'pc-costings-dashboard',
            array( $this, 'render_page' )
        );
    }

    public function handle_target_save() {
        if ( ! isset( $_POST['pc_target_margin_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_target_margin_nonce'] ) ), 'pc_save_target_margin' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $target = isset( $_POST['pc_target_margin'] ) ? floatval( wp_unslash( $_POST['pc_target_margin'] ) ) : 50;
        update_option( self::TARGET_OPTION, max( 0, min( 100, $target ) ) );

        if ( isset( $_POST['pc_currency_symbol'] ) ) {
            $symbol = sanitize_text_field( wp_unslash( $_POST['pc_currency_symbol'] ) );
            update_option( 'pc_currency_symbol', '' !== $symbol ? $symbol : '$' );
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=products&page=pc-costings-dashboard&updated=1' ) );
        exit;
    }

    public function render_page() {
        $target   = floatval( get_option( self::TARGET_OPTION, 50 ) );
        $currency = get_option( 'pc_currency_symbol', '$' );

        $products = get_posts( array(
            'post_type'      => 'products',
            'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Costings Dashboard', 'product-costings' ); ?></h1>
            <p><?php esc_html_e( 'Unit costs and margins across all products, using each product\'s saved formula, waste %, and pricing multipliers. Margins below target are highlighted; the Stale column flags products whose saved ingredient prices no longer match the current Trade Name prices.', 'product-costings' ); ?></p>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Target margin updated.', 'product-costings' ); ?></p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['imported'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( __( 'Imported initial bulk pricing for %d Trade Name(s).', 'product-costings' ), absint( $_GET['imported'] ) ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php $import_candidates = self::count_pricing_import_candidates(); ?>
            <?php if ( $import_candidates > 0 ) : ?>
                <div class="notice notice-info" style="padding:10px 12px;">
                    <p style="margin:0 0 8px;">
                        <strong><?php echo esc_html( sprintf( __( '%d Trade Name(s) have a Price/kg but no bulk pricing yet.', 'product-costings' ), $import_candidates ) ); ?></strong>
                        <?php esc_html_e( 'Import their MOQ and Price/kg as a first bulk-pricing pack (MOQ becomes the pack size, or 1 kg if blank). Trade Names that already have bulk pricing are left untouched.', 'product-costings' ); ?>
                    </p>
                    <form method="post" style="margin:0;">
                        <?php wp_nonce_field( 'pc_import_pricing', 'pc_import_pricing_nonce' ); ?>
                        <button type="submit" class="button button-secondary">
                            <?php echo esc_html( sprintf( __( 'Import initial pricing for %d Trade Names', 'product-costings' ), $import_candidates ) ); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <form method="post" style="margin:12px 0 20px;">
                <?php wp_nonce_field( 'pc_save_target_margin', 'pc_target_margin_nonce' ); ?>
                <label>
                    <strong><?php esc_html_e( 'Target margin %', 'product-costings' ); ?></strong>
                    <input type="number" name="pc_target_margin" value="<?php echo esc_attr( $target ); ?>" step="0.5" min="0" max="100" style="width:80px;">
                </label>
                &nbsp;&nbsp;
                <label>
                    <strong><?php esc_html_e( 'Currency symbol', 'product-costings' ); ?></strong>
                    <input type="text" name="pc_currency_symbol" value="<?php echo esc_attr( $currency ); ?>" maxlength="5" style="width:60px;">
                </label>
                <button type="submit" class="button"><?php esc_html_e( 'Save', 'product-costings' ); ?></button>
                <span class="description" style="margin-left:8px;"><?php esc_html_e( 'The currency symbol is used across the admin Cost Summary, this dashboard, and as the default for the Elementor widgets.', 'product-costings' ); ?></span>
            </form>

            <?php if ( empty( $products ) ) : ?>
                <p><?php esc_html_e( 'No products found.', 'product-costings' ); ?></p>
            <?php else : ?>
                <table class="widefat striped pc-dashboard-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Product', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'Unit Cost', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'My Cost Price', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'Wholesale', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'W. Margin', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'RRP', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'R. Margin', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'Nat. Origin', 'product-costings' ); ?></th>
                            <th><?php esc_html_e( 'Stale Prices', 'product-costings' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $products as $product_id ) : ?>
                            <?php
                            $m = PC_Costing_Calculator::metrics( $product_id );

                            $has_formula = ! empty( get_post_meta( $product_id, '_pc_formula_rows', true ) );
                            $unit_cost   = $m['final_unit_cost'];

                            $w_margin = $m['wholesale_price'] > 0 ? ( ( $m['wholesale_price'] - $unit_cost ) / $m['wholesale_price'] ) * 100 : null;
                            $r_margin = $m['rrp'] > 0 ? ( ( $m['rrp'] - $unit_cost ) / $m['rrp'] ) * 100 : null;

                            $stale = $has_formula ? PC_Costing_Calculator::has_stale_prices( $product_id ) : false;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><strong><?php echo esc_html( get_the_title( $product_id ) ); ?></strong></a>
                                    <?php if ( 'publish' !== get_post_status( $product_id ) ) : ?>
                                        <em>(<?php echo esc_html( get_post_status( $product_id ) ); ?>)</em>
                                    <?php endif; ?>
                                </td>
                                <?php if ( ! $has_formula || $unit_cost <= 0 ) : ?>
                                    <td colspan="7"><em><?php esc_html_e( 'No formula / costing data', 'product-costings' ); ?></em></td>
                                <?php else : ?>
                                    <td><?php echo esc_html( $currency . number_format( $unit_cost, 2 ) ); ?></td>
                                    <td><?php echo $m['my_cost_price'] > 0 ? esc_html( $currency . number_format( $m['my_cost_price'], 2 ) ) : '&mdash;'; ?></td>
                                    <td><?php echo $m['wholesale_price'] > 0 ? esc_html( $currency . number_format( $m['wholesale_price'], 2 ) ) : '&mdash;'; ?></td>
                                    <td class="<?php echo ( null !== $w_margin && $w_margin < $target ) ? 'pc-margin-low' : ''; ?>">
                                        <?php echo null !== $w_margin ? esc_html( number_format( $w_margin, 1 ) . '%' ) : '&mdash;'; ?>
                                    </td>
                                    <td><?php echo $m['rrp'] > 0 ? esc_html( $currency . number_format( $m['rrp'], 2 ) ) : '&mdash;'; ?></td>
                                    <td class="<?php echo ( null !== $r_margin && $r_margin < $target ) ? 'pc-margin-low' : ''; ?>">
                                        <?php echo null !== $r_margin ? esc_html( number_format( $r_margin, 1 ) . '%' ) : '&mdash;'; ?>
                                    </td>
                                    <td><?php echo $m['natural_origin'] > 0 ? esc_html( number_format( $m['natural_origin'], 1 ) . '%' ) : '&mdash;'; ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ( $stale ) : ?>
                                        <span class="pc-stale-flag"><?php esc_html_e( 'Stale', 'product-costings' ); ?></span>
                                    <?php else : ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">
                    <?php esc_html_e( 'Margin = (price − unit cost) ÷ price. Open a product flagged Stale and use "Refresh Ingredient Data" in the Formula Ingredients box to pull current prices.', 'product-costings' ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
