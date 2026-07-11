<?php
/**
 * Formula versioning.
 *
 * Automatically snapshots the formula rows every time they change on save,
 * keeps the last 25 versions with a note/date/user, and provides compare
 * (with cost delta) and restore via AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Versions {

    private static $instance = null;

    const META_KEY     = '_pc_formula_versions';
    const MAX_VERSIONS = 25;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Priority 20: runs after PC_Product_Metaboxes::save_meta (10) has saved the rows.
        add_action( 'save_post_products', array( $this, 'maybe_snapshot' ), 20, 2 );
        add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
        add_action( 'wp_ajax_pc_version_compare', array( $this, 'ajax_compare' ) );
        add_action( 'wp_ajax_pc_version_restore', array( $this, 'ajax_restore' ) );
        add_action( 'wp_ajax_pc_version_delete', array( $this, 'ajax_delete' ) );
    }

    /* ───────────────────────────────────────────────
     * Snapshot on save
     * ─────────────────────────────────────────────── */

    public function maybe_snapshot( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        // Only when the formula metabox was actually submitted.
        if ( ! isset( $_POST['pc_formula_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_formula_nonce'] ) ), 'pc_save_formula' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $rows = get_post_meta( $post_id, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $note = isset( $_POST['pc_version_note'] ) ? sanitize_text_field( wp_unslash( $_POST['pc_version_note'] ) ) : '';

        $versions = $this->get_versions( $post_id );
        $last     = end( $versions );

        if ( $last && $last['rows'] === $rows ) {
            return; // Formula unchanged — no new version.
        }

        $this->append_version( $post_id, $rows, $note );
    }

    private function append_version( $post_id, $rows, $note ) {
        $versions   = $this->get_versions( $post_id );
        $versions[] = array(
            'time' => time(),
            'user' => get_current_user_id(),
            'note' => $note,
            'rows' => $rows,
        );

        if ( count( $versions ) > self::MAX_VERSIONS ) {
            $versions = array_slice( $versions, -self::MAX_VERSIONS );
        }

        update_post_meta( $post_id, self::META_KEY, $versions );
    }

    private function get_versions( $post_id ) {
        $versions = get_post_meta( $post_id, self::META_KEY, true );
        return is_array( $versions ) ? array_values( $versions ) : array();
    }

    /* ───────────────────────────────────────────────
     * Metabox
     * ─────────────────────────────────────────────── */

    public function register_metabox() {
        add_meta_box(
            'pc_formula_versions',
            __( 'Formula Versions', 'product-costings' ),
            array( $this, 'render_metabox' ),
            'products',
            'normal',
            'low'
        );
    }

    public function render_metabox( $post ) {
        $versions = $this->get_versions( $post->ID );

        if ( empty( $versions ) ) {
            echo '<p>' . esc_html__( 'No versions yet. A version is saved automatically every time the formula changes. Add a note in the Formula Ingredients box before saving to describe the change.', 'product-costings' ) . '</p>';
            return;
        }

        $current_cost = PC_Costing_Calculator::metrics( $post->ID );
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th><?php esc_html_e( 'Date', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( 'By', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( 'Note', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( 'Rows', 'product-costings' ); ?></th>
                    <th><?php esc_html_e( 'Ingredient Batch Cost', 'product-costings' ); ?></th>
                    <th style="width:180px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( array_reverse( $versions, true ) as $idx => $v ) : ?>
                    <?php
                    $v_cost  = PC_Costing_Calculator::metrics( $post->ID, null, $v['rows'] );
                    $delta   = $v_cost['batch_cost'] - $current_cost['batch_cost'];
                    $user    = $v['user'] ? get_userdata( $v['user'] ) : null;
                    ?>
                    <tr>
                        <td><?php echo (int) ( $idx + 1 ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $v['time'] ) ); ?></td>
                        <td><?php echo $user ? esc_html( $user->display_name ) : '&mdash;'; ?></td>
                        <td><?php echo $v['note'] ? esc_html( $v['note'] ) : '<em>&mdash;</em>'; ?></td>
                        <td><?php echo (int) count( $v['rows'] ); ?></td>
                        <td>
                            <?php echo esc_html( number_format( $v_cost['batch_cost'], 2 ) ); ?>
                            <?php if ( abs( $delta ) > 0.005 ) : ?>
                                <span class="<?php echo $delta > 0 ? 'pc-delta-up' : 'pc-delta-down'; ?>">
                                    (<?php echo esc_html( ( $delta > 0 ? '+' : '' ) . number_format( $delta, 2 ) ); ?> <?php esc_html_e( 'vs current', 'product-costings' ); ?>)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small pc-version-compare" data-index="<?php echo (int) $idx; ?>"><?php esc_html_e( 'Compare', 'product-costings' ); ?></button>
                            <button type="button" class="button button-small pc-version-restore" data-index="<?php echo (int) $idx; ?>"><?php esc_html_e( 'Restore', 'product-costings' ); ?></button>
                            <button type="button" class="button button-small pc-version-delete" data-index="<?php echo (int) $idx; ?>" title="<?php esc_attr_e( 'Delete this version', 'product-costings' ); ?>">&#128465;</button>
                        </td>
                    </tr>
                    <tr class="pc-version-detail" id="pc-version-detail-<?php echo (int) $idx; ?>" style="display:none;">
                        <td colspan="7" class="pc-version-detail-cell"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php esc_html_e( 'Compare shows what changed between that version and the current formula, including the cost impact. Restore replaces the current saved formula with that version (the current formula is snapshotted first) and reloads the page.', 'product-costings' ); ?></p>
        <?php
    }

    /* ───────────────────────────────────────────────
     * AJAX: compare
     * ─────────────────────────────────────────────── */

    public function ajax_compare() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $index   = isset( $_GET['index'] ) ? absint( $_GET['index'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $versions = $this->get_versions( $post_id );
        if ( ! isset( $versions[ $index ] ) ) {
            wp_send_json_error( 'Unknown version.' );
        }

        $old_rows = $versions[ $index ]['rows'];
        $new_rows = get_post_meta( $post_id, '_pc_formula_rows', true );
        if ( ! is_array( $new_rows ) ) {
            $new_rows = array();
        }

        $diff = $this->diff_rows( $old_rows, $new_rows );

        $old_cost = PC_Costing_Calculator::metrics( $post_id, null, $old_rows );
        $new_cost = PC_Costing_Calculator::metrics( $post_id, null, $new_rows );
        $delta    = $new_cost['batch_cost'] - $old_cost['batch_cost'];

        ob_start();
        ?>
        <p>
            <strong><?php esc_html_e( 'Ingredient batch cost:', 'product-costings' ); ?></strong>
            <?php echo esc_html( number_format( $old_cost['batch_cost'], 2 ) ); ?> (<?php esc_html_e( 'version', 'product-costings' ); ?>)
            &rarr; <?php echo esc_html( number_format( $new_cost['batch_cost'], 2 ) ); ?> (<?php esc_html_e( 'current', 'product-costings' ); ?>)
            <span class="<?php echo $delta >= 0 ? 'pc-delta-up' : 'pc-delta-down'; ?>">
                (<?php echo esc_html( ( $delta > 0 ? '+' : '' ) . number_format( $delta, 2 ) ); ?>)
            </span>
        </p>
        <?php if ( empty( $diff ) ) : ?>
            <p><em><?php esc_html_e( 'No ingredient differences.', 'product-costings' ); ?></em></p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Change', 'product-costings' ); ?></th>
                        <th><?php esc_html_e( 'Ingredient', 'product-costings' ); ?></th>
                        <th><?php esc_html_e( 'Version % w/w', 'product-costings' ); ?></th>
                        <th><?php esc_html_e( 'Current % w/w', 'product-costings' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $diff as $d ) : ?>
                        <tr>
                            <td><span class="pc-diff-<?php echo esc_attr( $d['type'] ); ?>"><?php echo esc_html( ucfirst( $d['type'] ) ); ?></span></td>
                            <td><?php echo esc_html( $d['name'] ); ?></td>
                            <td><?php echo '' !== $d['old'] ? esc_html( number_format( (float) $d['old'], 2 ) . '%' ) : '&mdash;'; ?></td>
                            <td><?php echo '' !== $d['new'] ? esc_html( number_format( (float) $d['new'], 2 ) . '%' ) : '&mdash;'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;

        wp_send_json_success( ob_get_clean() );
    }

    /**
     * Diff two row sets, matching rows by trade_name_id occurrence.
     *
     * @return array[] Array of array( 'type' => added|removed|changed, 'name', 'old', 'new' ).
     */
    private function diff_rows( $old_rows, $new_rows ) {
        $index_rows = function ( $rows ) {
            $indexed = array();
            $counts  = array();
            foreach ( (array) $rows as $row ) {
                $tid = absint( $row['trade_name_id'] ?? 0 );
                $counts[ $tid ] = ( $counts[ $tid ] ?? 0 ) + 1;
                $indexed[ $tid . '#' . $counts[ $tid ] ] = $row;
            }
            return $indexed;
        };

        $old = $index_rows( $old_rows );
        $new = $index_rows( $new_rows );

        $name_of = function ( $row ) {
            $tid = absint( $row['trade_name_id'] ?? 0 );
            if ( $tid ) {
                $title = get_the_title( $tid );
                if ( $title ) {
                    return $title;
                }
            }
            return __( '(no trade name)', 'product-costings' );
        };

        $diff = array();

        foreach ( $old as $key => $row ) {
            if ( ! isset( $new[ $key ] ) ) {
                $diff[] = array( 'type' => 'removed', 'name' => $name_of( $row ), 'old' => $row['percent_w_w'] ?? 0, 'new' => '' );
                continue;
            }
            $old_ww = floatval( $row['percent_w_w'] ?? 0 );
            $new_ww = floatval( $new[ $key ]['percent_w_w'] ?? 0 );
            if ( abs( $old_ww - $new_ww ) > 0.0001 ) {
                $diff[] = array( 'type' => 'changed', 'name' => $name_of( $row ), 'old' => $old_ww, 'new' => $new_ww );
            }
        }

        foreach ( $new as $key => $row ) {
            if ( ! isset( $old[ $key ] ) ) {
                $diff[] = array( 'type' => 'added', 'name' => $name_of( $row ), 'old' => '', 'new' => $row['percent_w_w'] ?? 0 );
            }
        }

        return $diff;
    }

    /* ───────────────────────────────────────────────
     * AJAX: restore
     * ─────────────────────────────────────────────── */

    public function ajax_restore() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $versions = $this->get_versions( $post_id );
        if ( ! isset( $versions[ $index ] ) ) {
            wp_send_json_error( 'Unknown version.' );
        }

        // Snapshot the current state first so nothing is lost.
        $current = get_post_meta( $post_id, '_pc_formula_rows', true );
        if ( is_array( $current ) && ( ! ( $last = end( $versions ) ) || $last['rows'] !== $current ) ) {
            $this->append_version( $post_id, $current, __( 'Auto-snapshot before restore', 'product-costings' ) );
        }

        update_post_meta( $post_id, '_pc_formula_rows', $versions[ $index ]['rows'] );
        $this->append_version( $post_id, $versions[ $index ]['rows'], sprintf( __( 'Restored version #%d', 'product-costings' ), $index + 1 ) );

        wp_send_json_success();
    }

    /* ───────────────────────────────────────────────
     * AJAX: delete
     * ─────────────────────────────────────────────── */

    public function ajax_delete() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $versions = $this->get_versions( $post_id );
        if ( ! isset( $versions[ $index ] ) ) {
            wp_send_json_error( 'Unknown version.' );
        }

        unset( $versions[ $index ] );
        update_post_meta( $post_id, self::META_KEY, array_values( $versions ) );

        wp_send_json_success();
    }
}
