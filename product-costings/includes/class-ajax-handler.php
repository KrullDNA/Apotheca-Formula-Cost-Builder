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

        // Try multiple meta key variants for each field.
        $ph           = $this->get_meta_value( $post_id, array(
            'ph-range', 'ph_range', '_ph_range', 'pH_range', 'ph', '_ph', 'pH',
        ) );
        $price_per_kg = $this->get_meta_value( $post_id, array(
            'tn_price_per_kg', 'price_per_kg', '_price_per_kg', 'price_kg', '_price_kg', 'price',
        ) );
        $moq          = $this->get_meta_value( $post_id, array(
            'tn_moq', 'moq', '_moq', 'MOQ', '_MOQ',
        ) );
        $function1    = $this->get_meta_value( $post_id, array(
            'function1', '_function1', 'function', '_function',
        ) );
        $natural_origin = $this->get_meta_value( $post_id, array(
            '-natural-origin', '_-natural-origin',
            'natural-origin', '_natural-origin',
            'natural_origin', '_natural_origin',
        ) );

        wp_send_json_success( array(
            'ph'             => $ph,
            'price_per_kg'   => $price_per_kg,
            'moq'            => $moq,
            'function1'      => $function1,
            'natural_origin' => $natural_origin,
            'title'          => get_the_title( $post_id ),
        ) );
    }

    /**
     * Try multiple meta key variants and return the first non-empty value.
     */
    private function get_meta_value( $post_id, $keys ) {
        foreach ( $keys as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return $val;
            }
        }
        return '';
    }
}
