<?php
/**
 * Central accessor for Trade Name (trade-names CPT) meta data.
 *
 * All reads of trade name fields go through here so the multi-key
 * fallback logic (plain / underscore / ACF-style keys) lives in one place.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Trade_Data {

    /**
     * Meta key fallback map. First non-empty value wins.
     */
    private static $key_map = array(
        'ph'             => array( 'ph-range', 'ph_range', '_ph_range', 'pH_range', 'ph', '_ph', 'pH' ),
        'price_per_kg'   => array( 'tn_price_per_kg', 'price_per_kg', '_price_per_kg', 'price_kg', '_price_kg', 'price' ),
        'moq'            => array( 'tn_moq', 'moq', '_moq', 'MOQ', '_MOQ' ),
        'function1'      => array( 'function1', '_function1', 'function', '_function' ),
        'natural_origin' => array(
            '-natural-origin', '_-natural-origin',
            'natural-origin', '_natural-origin',
            'natural_origin', '_natural_origin',
        ),
        'usage_min'      => array( '_pc_usage_min', 'usage_min', '_usage_min', 'usage-min' ),
        'usage_max'      => array( '_pc_usage_max', 'usage_max', '_usage_max', 'usage-max' ),
    );

    /**
     * Get a trade name field, trying each known meta key variant.
     *
     * @param int    $post_id Trade name post ID.
     * @param string $field   Logical field name (key of $key_map).
     * @return string Value or empty string.
     */
    public static function get( $post_id, $field ) {
        if ( ! isset( self::$key_map[ $field ] ) ) {
            return '';
        }

        foreach ( self::$key_map[ $field ] as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return $val;
            }
        }
        return '';
    }

    /**
     * Get the INCI composition rows for a trade name.
     *
     * @param int $post_id Trade name post ID.
     * @return array[] Array of array( 'inci' => string, 'percent' => float ).
     */
    public static function get_composition( $post_id ) {
        $rows = get_post_meta( $post_id, '_pc_inci_composition', true );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            $inci = isset( $row['inci'] ) ? trim( $row['inci'] ) : '';
            if ( '' === $inci ) {
                continue;
            }
            $clean[] = array(
                'inci'    => $inci,
                'percent' => isset( $row['percent'] ) ? floatval( $row['percent'] ) : 100,
            );
        }
        return $clean;
    }

    /**
     * Products whose formula uses a given trade name.
     *
     * @param int $trade_id Trade name post ID.
     * @return array[] Array of array( 'product_id', 'percent_w_w', 'kg_per_batch' ).
     */
    public static function get_products_using( $trade_id ) {
        global $wpdb;

        // _pc_formula_rows is a serialized array; match the serialized int fragment.
        $fragment = 's:13:"trade_name_id";i:' . absint( $trade_id ) . ';';
        $like     = '%' . $wpdb->esc_like( $fragment ) . '%';

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_pc_formula_rows'
               AND pm.meta_value LIKE %s
               AND p.post_type = 'products'
               AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
            $like
        ) );

        $usages = array();
        foreach ( $post_ids as $product_id ) {
            $rows = get_post_meta( $product_id, '_pc_formula_rows', true );
            if ( ! is_array( $rows ) ) {
                continue;
            }
            $batch_size = PC_Costing_Calculator::get_product_meta_value( $product_id, 'batch_size' );

            foreach ( $rows as $row ) {
                if ( absint( $row['trade_name_id'] ?? 0 ) !== absint( $trade_id ) ) {
                    continue;
                }
                $ww = floatval( $row['percent_w_w'] ?? 0 );
                $usages[] = array(
                    'product_id'   => (int) $product_id,
                    'percent_w_w'  => $ww,
                    'kg_per_batch' => $batch_size > 0 ? ( $ww / 100 ) * $batch_size : 0,
                );
            }
        }
        return $usages;
    }
}
