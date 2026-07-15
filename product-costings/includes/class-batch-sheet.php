<?php
/**
 * Printable batch manufacturing record.
 *
 * Adds a "Batch Sheet" metabox to the product edit screen; the button opens
 * a standalone, print-ready page (via admin-post.php) with target weights
 * scaled to any batch size, columns for actual weights and ingredient lot
 * numbers, the method, and a QC/sign-off section.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Batch_Sheet {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
        add_action( 'admin_post_pc_batch_sheet', array( $this, 'render_sheet' ) );
    }

    public function register_metabox() {
        add_meta_box(
            'pc_batch_sheet',
            __( 'Batch Sheet', 'product-costings' ),
            array( $this, 'render_metabox' ),
            'products',
            'side',
            'default'
        );
    }

    public function render_metabox( $post ) {
        $batch_size = PC_Costing_Calculator::get_product_meta_value( $post->ID, 'batch_size' );
        $url        = admin_url( 'admin-post.php' );
        $nonce      = wp_create_nonce( 'pc_batch_sheet_' . $post->ID );
        ?>
        <p class="description"><?php esc_html_e( 'Print a manufacturing record with target weights at any batch size — including quick lab batches.', 'product-costings' ); ?></p>
        <p>
            <label><?php esc_html_e( 'Batch size (kg)', 'product-costings' ); ?><br>
                <input type="number" id="pc-sheet-size" value="<?php echo esc_attr( $batch_size > 0 ? $batch_size : 1 ); ?>" step="any" min="0.001" style="width:100%;">
            </label>
        </p>
        <p>
            <label><?php esc_html_e( 'Batch code', 'product-costings' ); ?><br>
                <input type="text" id="pc-sheet-code" value="" placeholder="<?php esc_attr_e( 'e.g. B2026-014', 'product-costings' ); ?>" style="width:100%;">
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" id="pc-sheet-waste" checked>
                <?php esc_html_e( 'Add waste allowance to weights', 'product-costings' ); ?>
            </label>
        </p>
        <p>
            <button type="button" class="button button-primary" id="pc-sheet-open" style="width:100%;">
                <?php esc_html_e( 'Open Printable Batch Sheet', 'product-costings' ); ?>
            </button>
        </p>
        <script>
        jQuery(function ($) {
            $('#pc-sheet-open').on('click', function () {
                var url = <?php echo wp_json_encode( $url ); ?> +
                    '?action=pc_batch_sheet' +
                    '&product_id=<?php echo (int) $post->ID; ?>' +
                    '&_wpnonce=<?php echo esc_js( $nonce ); ?>' +
                    '&size=' + encodeURIComponent($('#pc-sheet-size').val()) +
                    '&code=' + encodeURIComponent($('#pc-sheet-code').val()) +
                    '&waste=' + ($('#pc-sheet-waste').is(':checked') ? 1 : 0);
                window.open(url, '_blank');
            });
        });
        </script>
        <?php
    }

    /**
     * Render the standalone printable sheet.
     */
    public function render_sheet() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

        if ( ! $product_id || 'products' !== get_post_type( $product_id ) ) {
            wp_die( esc_html__( 'Invalid product.', 'product-costings' ) );
        }
        check_admin_referer( 'pc_batch_sheet_' . $product_id );
        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'product-costings' ) );
        }

        $size_kg   = isset( $_GET['size'] ) ? floatval( $_GET['size'] ) : 0;
        $code      = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        $add_waste = ! empty( $_GET['waste'] );

        if ( $size_kg <= 0 ) {
            $size_kg = PC_Costing_Calculator::get_product_meta_value( $product_id, 'batch_size' );
        }
        if ( $size_kg <= 0 ) {
            $size_kg = 1;
        }

        $waste_saved = get_post_meta( $product_id, '_pc_waste_percent', true );
        $waste_pct   = ( '' === $waste_saved ) ? 2 : floatval( $waste_saved );
        $size_eff    = $add_waste ? $size_kg * ( 1 + $waste_pct / 100 ) : $size_kg;

        $rows = get_post_meta( $product_id, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $method   = PC_Costing_Calculator::get_product_meta_text( $product_id, 'method' );
        $final_ph = PC_Costing_Calculator::get_product_meta_text( $product_id, 'final_ph' );
        $title    = get_the_title( $product_id );

        header( 'Content-Type: text/html; charset=utf-8' );
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<title><?php echo esc_html( $title ); ?> — <?php esc_html_e( 'Batch Sheet', 'product-costings' ); ?></title>
<style>
    body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a1a1a; margin: 32px; font-size: 13px; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 14px; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #1a1a1a; padding-bottom: 4px; }
    .pc-meta { color: #555; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #999; padding: 7px 9px; text-align: left; }
    th { background: #f0f0f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; }
    .num { text-align: right; }
    .blank { min-width: 90px; }
    .pc-sign { margin-top: 28px; display: flex; gap: 40px; }
    .pc-sign div { flex: 1; border-top: 1px solid #1a1a1a; padding-top: 6px; }
    .pc-noprint { margin-bottom: 20px; }
    .pc-method { white-space: pre-wrap; border: 1px solid #999; padding: 12px; }
    @media print { .pc-noprint { display: none; } body { margin: 10mm; } }
</style>
</head>
<body>
    <div class="pc-noprint">
        <button onclick="window.print();" style="padding:8px 18px;font-size:14px;cursor:pointer;"><?php esc_html_e( 'Print', 'product-costings' ); ?></button>
    </div>

    <h1><?php echo esc_html( $title ); ?> — <?php esc_html_e( 'Batch Manufacturing Record', 'product-costings' ); ?></h1>
    <p class="pc-meta">
        <?php esc_html_e( 'Batch code:', 'product-costings' ); ?> <strong><?php echo $code ? esc_html( $code ) : '________________'; ?></strong>
        &nbsp;|&nbsp; <?php esc_html_e( 'Date:', 'product-costings' ); ?> <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></strong>
        &nbsp;|&nbsp; <?php esc_html_e( 'Batch size:', 'product-costings' ); ?> <strong><?php echo esc_html( number_format( $size_kg, 3 ) ); ?> kg</strong>
        <?php if ( $add_waste ) : ?>
            &nbsp;(<?php echo esc_html( sprintf( __( 'weights include %s%% waste allowance → %s kg total', 'product-costings' ), rtrim( rtrim( number_format( $waste_pct, 2 ), '0' ), '.' ), number_format( $size_eff, 3 ) ) ); ?>)
        <?php endif; ?>
    </p>

    <h2><?php esc_html_e( 'Ingredients', 'product-costings' ); ?></h2>
    <table>
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th style="width:55px;"><?php esc_html_e( 'Phase', 'product-costings' ); ?></th>
                <th><?php esc_html_e( 'Trade Name', 'product-costings' ); ?></th>
                <th class="num" style="width:70px;"><?php esc_html_e( '% w/w', 'product-costings' ); ?></th>
                <th class="num" style="width:100px;"><?php esc_html_e( 'Target (g)', 'product-costings' ); ?></th>
                <th class="blank"><?php esc_html_e( 'Actual (g)', 'product-costings' ); ?></th>
                <th class="blank"><?php esc_html_e( 'Lot No.', 'product-costings' ); ?></th>
                <th style="width:60px;"><?php esc_html_e( 'Added ✓', 'product-costings' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $n = 0;
            $total_g = 0;
            foreach ( $rows as $row ) :
                $n++;
                $ww_raw   = $row['percent_w_w'] ?? '';
                $is_qs    = ( '' !== $ww_raw && ! is_numeric( $ww_raw ) );
                $ww       = $is_qs ? 0 : floatval( $ww_raw );
                $trade_id = absint( $row['trade_name_id'] ?? 0 );
                $target_g = ( $ww / 100 ) * $size_eff * 1000;
                $total_g += $target_g;
                ?>
                <tr>
                    <td><?php echo (int) $n; ?></td>
                    <td><?php echo esc_html( strtoupper( $row['phase'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $trade_id ? get_the_title( $trade_id ) : '' ); ?></td>
                    <td class="num"><?php echo $is_qs ? 'q.s.' : esc_html( number_format( $ww, 2 ) ); ?></td>
                    <td class="num"><strong><?php echo $is_qs ? 'q.s.' : esc_html( number_format( $target_g, 2 ) ); ?></strong></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4"><?php esc_html_e( 'Total', 'product-costings' ); ?></th>
                <th class="num"><?php echo esc_html( number_format( $total_g, 2 ) ); ?> g</th>
                <th colspan="3"></th>
            </tr>
        </tfoot>
    </table>

    <?php if ( $method ) : ?>
        <h2><?php esc_html_e( 'Method', 'product-costings' ); ?></h2>
        <div class="pc-method"><?php echo wp_kses_post( $method ); ?></div>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Quality Control', 'product-costings' ); ?></h2>
    <table>
        <tr>
            <th style="width:220px;"><?php esc_html_e( 'Target pH', 'product-costings' ); ?></th>
            <td><?php echo $final_ph ? esc_html( $final_ph ) : '—'; ?></td>
            <th style="width:220px;"><?php esc_html_e( 'Measured pH', 'product-costings' ); ?></th>
            <td></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Appearance', 'product-costings' ); ?></th>
            <td></td>
            <th><?php esc_html_e( 'Odour', 'product-costings' ); ?></th>
            <td></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Viscosity / texture', 'product-costings' ); ?></th>
            <td></td>
            <th><?php esc_html_e( 'Units filled', 'product-costings' ); ?></th>
            <td></td>
        </tr>
    </table>

    <div class="pc-sign">
        <div><?php esc_html_e( 'Manufactured by / date', 'product-costings' ); ?></div>
        <div><?php esc_html_e( 'Checked by / date', 'product-costings' ); ?></div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
