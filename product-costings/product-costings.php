<?php
/**
 * Plugin Name: Product Costings
 * Description: Cosmetic product formula builder and costing calculator. Adds formula ingredients repeater to Products CPT, pulling data from Trade Names CPT.
 * Version: 1.4.0
 * Author: KrullDNA
 * Text Domain: product-costings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PC_VERSION', '1.4.0' );

require_once PC_PLUGIN_DIR . 'includes/class-trade-data.php';
require_once PC_PLUGIN_DIR . 'includes/class-costing-calculator.php';
require_once PC_PLUGIN_DIR . 'includes/class-formula-functions.php';
require_once PC_PLUGIN_DIR . 'includes/class-product-metaboxes.php';
require_once PC_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once PC_PLUGIN_DIR . 'includes/class-trade-name-fields.php';
require_once PC_PLUGIN_DIR . 'includes/class-inci.php';
require_once PC_PLUGIN_DIR . 'includes/class-batch-sheet.php';
require_once PC_PLUGIN_DIR . 'includes/class-margin-dashboard.php';
require_once PC_PLUGIN_DIR . 'includes/class-versions.php';
require_once PC_PLUGIN_DIR . 'includes/class-elementor-widget.php';

/**
 * Main plugin class.
 */
final class Product_Costings {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        PC_Formula_Functions::instance();
        PC_Product_Metaboxes::instance();
        PC_Ajax_Handler::instance();
        PC_Trade_Name_Fields::instance();
        PC_INCI::instance();
        PC_Batch_Sheet::instance();
        PC_Margin_Dashboard::instance();
        PC_Versions::instance();
    }

    /**
     * Register front-end assets (loaded on demand by the Elementor widget).
     */
    public function register_front_assets() {
        wp_register_style( 'pc-formula-table-front', PC_PLUGIN_URL . 'assets/css/formula-table.css', array(), PC_VERSION );
        wp_register_style( 'pc-batch-costings-front', PC_PLUGIN_URL . 'assets/css/batch-costings.css', array(), PC_VERSION );
    }

    /**
     * Enqueue admin scripts and styles on the Products edit screen.
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        // Load on product / trade name edit screens and our admin pages.
        $is_product_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'products' === $post_type;
        $is_trade_screen   = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'trade-names' === $post_type;
        $is_settings_page  = in_array( $hook, array( 'products_page_pc-formula-functions', 'products_page_pc-costings-dashboard' ), true );

        if ( ! $is_product_screen && ! $is_trade_screen && ! $is_settings_page ) {
            return;
        }

        wp_enqueue_style( 'pc-admin', PC_PLUGIN_URL . 'assets/css/admin.css', array(), PC_VERSION );

        if ( $is_product_screen ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'pc-admin', PC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util' ), PC_VERSION, true );

            wp_localize_script( 'pc-admin', 'pcData', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'pc_nonce' ),
                'functions' => PC_Formula_Functions::get_functions(),
                'currency'  => get_option( 'pc_currency_symbol', '$' ),
            ) );
        }
    }

    /**
     * Register admin menu under Products CPT.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=products',
            __( 'Formula Functions', 'product-costings' ),
            __( 'Formula Functions', 'product-costings' ),
            'manage_options',
            'pc-formula-functions',
            array( 'PC_Formula_Functions', 'render_settings_page' )
        );
    }
}

add_action( 'plugins_loaded', array( 'Product_Costings', 'instance' ) );
