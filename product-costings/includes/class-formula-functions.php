<?php
/**
 * Manages the list of formula functions (Solvent, Emulsifier, etc.)
 * stored as a WordPress option.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Formula_Functions {

    private static $instance = null;

    const OPTION_KEY = 'pc_formula_functions';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        $this->maybe_seed_defaults();
    }

    /**
     * Seed default functions on first activation.
     */
    private function maybe_seed_defaults() {
        if ( false === get_option( self::OPTION_KEY ) ) {
            $defaults = array(
                'Solvent',
                'Non-ionic Emulsifier',
                'Anionic Emulsifier',
                'Cationic Emulsifier',
                'Emollient',
                'Humectant',
                'Preservative',
                'Thickener',
                'Fragrance',
                'Active',
                'Colorant',
                'Surfactant',
                'Chelating Agent',
                'Antioxidant',
                'pH Adjuster',
                'Film Former',
                'Conditioning Agent',
                'UV Filter',
                'Opacifier',
            );
            update_option( self::OPTION_KEY, $defaults );
        }
    }

    /**
     * Get all formula functions.
     *
     * @return array
     */
    public static function get_functions() {
        $functions = get_option( self::OPTION_KEY, array() );
        sort( $functions );
        return $functions;
    }

    /**
     * Handle add / delete actions from settings page.
     */
    public function handle_form_submission() {
        if ( ! isset( $_POST['pc_functions_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_functions_nonce'] ) ), 'pc_save_functions' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $functions = self::get_functions();

        // Add new function.
        if ( ! empty( $_POST['pc_new_function'] ) ) {
            $new = sanitize_text_field( wp_unslash( $_POST['pc_new_function'] ) );
            if ( $new && ! in_array( $new, $functions, true ) ) {
                $functions[] = $new;
            }
        }

        // Delete function.
        if ( ! empty( $_POST['pc_delete_function'] ) ) {
            $delete = sanitize_text_field( wp_unslash( $_POST['pc_delete_function'] ) );
            $functions = array_values( array_diff( $functions, array( $delete ) ) );
        }

        update_option( self::OPTION_KEY, $functions );

        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'edit.php?post_type=products&page=pc-formula-functions' ) ) );
        exit;
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        $functions = self::get_functions();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Formula Functions', 'product-costings' ); ?></h1>
            <p><?php esc_html_e( 'Manage the list of ingredient functions available in the formula builder dropdown.', 'product-costings' ); ?></p>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Functions updated.', 'product-costings' ); ?></p></div>
            <?php endif; ?>

            <div class="pc-functions-wrap">
                <div class="pc-functions-list">
                    <h2><?php esc_html_e( 'Current Functions', 'product-costings' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Function Name', 'product-costings' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Action', 'product-costings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $functions ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'No functions defined yet.', 'product-costings' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $functions as $fn ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $fn ); ?></td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field( 'pc_save_functions', 'pc_functions_nonce' ); ?>
                                                <input type="hidden" name="pc_delete_function" value="<?php echo esc_attr( $fn ); ?>">
                                                <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this function?', 'product-costings' ); ?>');">
                                                    <?php esc_html_e( 'Delete', 'product-costings' ); ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pc-functions-add">
                    <h2><?php esc_html_e( 'Add New Function', 'product-costings' ); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field( 'pc_save_functions', 'pc_functions_nonce' ); ?>
                        <p>
                            <input type="text" name="pc_new_function" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Emollient', 'product-costings' ); ?>" required>
                        </p>
                        <p>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Function', 'product-costings' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
