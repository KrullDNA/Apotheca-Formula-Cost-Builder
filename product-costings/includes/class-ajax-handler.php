<?php
/**
 * AJAX handlers for:
 * - Searching Trade Names (autocomplete)
 * - Fetching Trade Name meta (pH, price_per_kg, MOQ)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Ajax_Handler {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_pc_search_trade_names', array( $this, 'search_trade_names' ) );
        add_action( 'wp_ajax_pc_get_trade_name_meta', array( $this, 'get_trade_name_meta' ) );
        add_action( 'wp_ajax_pc_get_inci_composition', array( $this, 'get_inci_composition' ) );
        add_action( 'wp_ajax_pc_save_inci_composition', array( $this, 'save_inci_composition' ) );
    }

    /**
     * Search Trade Names CPT for the autocomplete dropdown.
     */
    public function search_trade_names() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

        global $wpdb;

        // Search by title only — avoid matching post content, excerpt, or meta.
        $args = array(
            'post_type'      => 'trade-names',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $args['where_title_like'] = $like;
            add_filter( 'posts_where', array( $this, 'filter_title_only' ), 10, 2 );
        }

        $query = new WP_Query( $args );

        remove_filter( 'posts_where', array( $this, 'filter_title_only' ), 10 );
        $results = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = array(
                    'id'   => get_the_ID(),
                    'text' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $results );
    }

    /**
     * Filter WP_Query WHERE clause to only search post_title.
     */
    public function filter_title_only( $where, $query ) {
        global $wpdb;
        $like = $query->get( 'where_title_like' );
        if ( $like ) {
            $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );
        }
        return $where;
    }

    /**
     * Return meta fields for a given Trade Name post.
     * Tries multiple common meta key patterns (plain, underscore-prefixed, ACF-style).
     */
    public function get_trade_name_meta() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || 'trade-names' !== get_post_type( $post_id ) ) {
            wp_send_json_error( 'Invalid trade name.' );
        }

        wp_send_json_success( array(
            'ph'             => PC_Trade_Data::get( $post_id, 'ph' ),
            'price_per_kg'   => PC_Trade_Data::get( $post_id, 'price_per_kg' ),
            'moq'            => PC_Trade_Data::get( $post_id, 'moq' ),
            'function1'      => PC_Trade_Data::get( $post_id, 'function1' ),
            'natural_origin' => PC_Trade_Data::get( $post_id, 'natural_origin' ),
            'usage_min'      => PC_Trade_Data::get( $post_id, 'usage_min' ),
            'usage_max'      => PC_Trade_Data::get( $post_id, 'usage_max' ),
            'price_tiers'    => PC_Trade_Data::get_price_tiers( $post_id ),
            'title'          => get_the_title( $post_id ),
        ) );
    }

    /**
     * Return the INCI composition rows for a trade name (for the per-ingredient
     * INCI breakdown panel in the formula builder).
     */
    public function get_inci_composition() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $trade_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $trade_id || 'trade-names' !== get_post_type( $trade_id ) ) {
            wp_send_json_error( 'Invalid trade name.' );
        }

        wp_send_json_success( array(
            'title'        => get_the_title( $trade_id ),
            'composition'  => PC_Trade_Data::get_composition( $trade_id ),
            'edit_link'    => get_edit_post_link( $trade_id, 'raw' ),
        ) );
    }

    /**
     * Save an edited INCI composition back to the trade name (source of truth).
     */
    public function save_inci_composition() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $trade_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $trade_id || 'trade-names' !== get_post_type( $trade_id ) ) {
            wp_send_json_error( 'Invalid trade name.' );
        }
        if ( ! current_user_can( 'edit_post', $trade_id ) ) {
            wp_send_json_error( 'Insufficient permissions to edit this raw material.' );
        }

        $raw   = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : array();
        $clean = array();

        foreach ( $raw as $row ) {
            $inci = sanitize_text_field( $row['inci'] ?? '' );
            if ( '' === $inci ) {
                continue;
            }
            $clean[] = array(
                'inci'    => $inci,
                'percent' => floatval( $row['percent'] ?? 0 ),
            );
        }

        update_post_meta( $trade_id, '_pc_inci_composition', $clean );

        wp_send_json_success();
    }
}
