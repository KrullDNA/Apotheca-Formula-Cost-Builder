<?php
/**
 * Plugin Name: PC Bulk Pricing Migrator (temporary)
 * Description: One-time helper for Product Costings. Creates the first Bulk Pricing pack on each Trade Name from its existing price-per-kg and MOQ fields, so you don't have to enter the original pricing manually. Preview first, then apply. Safe to delete once run.
 * Version: 1.0.1
 * Author: KrullDNA
 * Text Domain: pc-bulk-pricing-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta key fall-backs (first non-empty wins). The exact fields shown in the
 * Trade Name editor (tn_price_per_kg / tn_moq) are tried first.
 */
function pc_bpm_price_keys() {
    return array( 'tn_price_per_kg', 'price_per_kg', '_price_per_kg', 'price_kg', '_price_kg', 'price' );
}
function pc_bpm_moq_keys() {
    return array( 'tn_moq', 'moq', '_moq', 'MOQ', '_MOQ' );
}

function pc_bpm_get_meta( $post_id, $keys ) {
    foreach ( $keys as $k ) {
        $v = get_post_meta( $post_id, $k, true );
        if ( '' !== $v && null !== $v && false !== $v ) {
            return $v;
        }
    }
    return '';
}

/**
 * Scan all Trade Names. When $apply is true, write the first pack tier for
 * every eligible one; otherwise just report what would happen (dry run).
 */
function pc_bpm_scan( $apply ) {
    $ids = get_posts( array(
        'post_type'      => 'trade-names',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $rows    = array();
    $updated = 0;

    foreach ( $ids as $id ) {
        $existing  = get_post_meta( $id, '_pc_price_tiers', true );
        $has_tiers = is_array( $existing ) && ! empty( $existing );

        $price = floatval( pc_bpm_get_meta( $id, pc_bpm_price_keys() ) );
        $moq   = floatval( pc_bpm_get_meta( $id, pc_bpm_moq_keys() ) );
        $pack  = $moq > 0 ? $moq : 1; // MOQ becomes the pack size; default 1 kg.

        if ( $has_tiers ) {
            $status = 'skip';
            $detail = __( 'already has bulk pricing', 'pc-bulk-pricing-migrator' );
        } elseif ( $price <= 0 ) {
            $status = 'skip';
            $detail = __( 'no price found', 'pc-bulk-pricing-migrator' );
        } else {
            $pack_price = $price * $pack; // Stored price is the total pack price.
            $detail = sprintf( '%s kg pack for %s (%s / kg)', rtrim( rtrim( number_format( $pack, 3 ), '0' ), '.' ), $pack_price, $price );
            if ( $apply ) {
                update_post_meta( $id, '_pc_price_tiers', array(
                    array( 'qty' => $pack, 'price' => $pack_price, 'unit' => 'kg' ),
                ) );
                $updated++;
                $status = 'added';
            } else {
                $status = 'ready';
            }
        }

        $rows[] = array(
            'id'     => $id,
            'title'  => get_the_title( $id ),
            'price'  => $price,
            'moq'    => $moq,
            'status' => $status,
            'detail' => $detail,
        );
    }

    return array( 'rows' => $rows, 'updated' => $updated );
}

add_action( 'admin_menu', function () {
    add_submenu_page(
        'tools.php',
        __( 'Bulk Pricing Migrator', 'pc-bulk-pricing-migrator' ),
        __( 'Bulk Pricing Migrator', 'pc-bulk-pricing-migrator' ),
        'manage_options',
        'pc-bulk-pricing-migrator',
        'pc_bpm_render_page'
    );
} );

function pc_bpm_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $apply = false;
    if ( isset( $_POST['pc_bpm_apply'] ) ) {
        check_admin_referer( 'pc_bpm_run' );
        $apply = true;
    }

    $result = pc_bpm_scan( $apply );
    $rows   = $result['rows'];

    $counts = array( 'ready' => 0, 'added' => 0, 'skip' => 0 );
    foreach ( $rows as $r ) {
        $counts[ $r['status'] ] = ( $counts[ $r['status'] ] ?? 0 ) + 1;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bulk Pricing Migrator', 'pc-bulk-pricing-migrator' ); ?></h1>
        <p><?php esc_html_e( 'Creates the first Bulk Pricing pack on each Trade Name from its existing price-per-kg and MOQ fields (MOQ becomes the pack size, or 1 kg if blank). Trade Names that already have bulk pricing are left untouched, so this is safe to run more than once. Delete this plugin once you are done.', 'pc-bulk-pricing-migrator' ); ?></p>

        <?php if ( $apply ) : ?>
            <div class="notice notice-success"><p>
                <?php echo esc_html( sprintf( __( 'Done — added a first pack to %d Trade Name(s).', 'pc-bulk-pricing-migrator' ), $result['updated'] ) ); ?>
            </p></div>
        <?php endif; ?>

        <p>
            <strong><?php echo esc_html( sprintf( __( '%d will be added', 'pc-bulk-pricing-migrator' ), $apply ? $counts['added'] : $counts['ready'] ) ); ?></strong>
            &nbsp;|&nbsp; <?php echo esc_html( sprintf( __( '%d skipped', 'pc-bulk-pricing-migrator' ), $counts['skip'] ) ); ?>
        </p>

        <?php if ( ! $apply && $counts['ready'] > 0 ) : ?>
            <form method="post">
                <?php wp_nonce_field( 'pc_bpm_run' ); ?>
                <p>
                    <button type="submit" name="pc_bpm_apply" value="1" class="button button-primary">
                        <?php echo esc_html( sprintf( __( 'Apply — add first pack to %d Trade Names', 'pc-bulk-pricing-migrator' ), $counts['ready'] ) ); ?>
                    </button>
                </p>
            </form>
        <?php endif; ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Trade Name', 'pc-bulk-pricing-migrator' ); ?></th>
                    <th><?php esc_html_e( 'Price / kg', 'pc-bulk-pricing-migrator' ); ?></th>
                    <th><?php esc_html_e( 'MOQ', 'pc-bulk-pricing-migrator' ); ?></th>
                    <th><?php esc_html_e( 'Result', 'pc-bulk-pricing-migrator' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $r ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['title'] ? $r['title'] : ( '#' . $r['id'] ) ); ?></a>
                        </td>
                        <td><?php echo $r['price'] > 0 ? esc_html( $r['price'] ) : '&mdash;'; ?></td>
                        <td><?php echo $r['moq'] > 0 ? esc_html( $r['moq'] ) : '&mdash;'; ?></td>
                        <td>
                            <?php if ( 'added' === $r['status'] ) : ?>
                                <span style="color:#007017;font-weight:600;">✓ <?php echo esc_html( $r['detail'] ); ?></span>
                            <?php elseif ( 'ready' === $r['status'] ) : ?>
                                <span style="color:#996800;"><?php echo esc_html( $r['detail'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#777;"><?php echo esc_html( $r['detail'] ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="4"><?php esc_html_e( 'No Trade Names found.', 'pc-bulk-pricing-migrator' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
