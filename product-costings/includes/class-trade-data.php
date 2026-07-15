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
     * Meta keys checked for a pre-existing plain-text INCI field on the
     * Trade Names CPT (used as a fallback when no structured composition
     * has been saved via the Formulation Data metabox).
     */
    private static $inci_text_keys = array(
        'inci', '_inci', 'INCI', '_INCI',
        'inci_name', '_inci_name', 'inci-name', '_inci-name',
        'inci_list', '_inci_list', 'tn_inci', '_tn_inci',
    );

    /**
     * Get the INCI composition rows for a trade name.
     *
     * Prefers the structured composition saved in the Formulation Data
     * metabox (_pc_inci_composition). Falls back to any plain-text INCI
     * field already on the Trade Name: a single name becomes one row at
     * 100%; a comma/semicolon/slash-separated list is split evenly across
     * its parts (edit the Formulation Data box to set real percentages
     * for accurate label ordering).
     *
     * @param int $post_id Trade name post ID.
     * @return array[] Array of array( 'inci' => string, 'percent' => float ).
     */
    public static function get_composition( $post_id ) {
        $rows  = get_post_meta( $post_id, '_pc_inci_composition', true );
        $clean = array();

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $inci = isset( $row['inci'] ) ? trim( $row['inci'] ) : '';
                if ( '' === $inci ) {
                    continue;
                }
                $percent = isset( $row['percent'] ) ? floatval( $row['percent'] ) : 100;

                // A single row may itself hold a blend written with (and)/and/&/comma.
                $names = self::split_inci_names( $inci );
                if ( empty( $names ) ) {
                    continue;
                }
                $share = $percent / count( $names );
                foreach ( $names as $name ) {
                    $clean[] = array(
                        'inci'    => $name,
                        'percent' => $share,
                    );
                }
            }
        }

        if ( ! empty( $clean ) ) {
            return $clean;
        }

        // Fallback: existing plain-text INCI field on the Trade Name.
        $keys = apply_filters( 'pc_inci_text_meta_keys', self::$inci_text_keys );
        foreach ( $keys as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( ! is_string( $val ) || '' === trim( $val ) ) {
                continue;
            }

            $names = self::split_inci_names( $val );
            if ( empty( $names ) ) {
                continue;
            }

            $share = 100 / count( $names );
            foreach ( $names as $name ) {
                $clean[] = array(
                    'inci'    => $name,
                    'percent' => $share,
                );
            }
            return $clean;
        }

        return array();
    }

    /**
     * Split a raw INCI string into its individual INCI names.
     *
     * Splits only on genuine blend connectors: "(and)", the standalone word
     * "and", "&", ";", and a comma *followed by whitespace*. It deliberately
     * does NOT split on:
     *   - "/"     — part of names like "Acrylates/C10-30 Alkyl Acrylate
     *               Crosspolymer" or "PEG-8/SMDI Copolymer".
     *   - a comma with no following space — part of names like
     *               "1,2-Hexanediol" or "2-Bromo-2-Nitropropane-1,3-Diol".
     *   - parenthetical qualifiers — "Simmondsia Chinensis (Jojoba) Seed Oil"
     *               (only the literal "(and)" is a separator).
     * Case-insensitive; surrounding whitespace is trimmed.
     *
     * @param string $string Raw INCI text (single name or blend).
     * @return string[] Individual INCI names.
     */
    public static function split_inci_names( $string ) {
        $string = wp_strip_all_tags( (string) $string );

        $parts = preg_split(
            '/\s*\(\s*and\s*\)\s*|\s*&\s*|\s*;\s*|\s+and\s+|\s*,\s+/i',
            $string,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ( ! is_array( $parts ) ) {
            return array();
        }

        return array_values( array_filter( array_map( 'trim', $parts ) ) );
    }

    /**
     * Get the bulk pricing tiers (quantity breaks) for a trade name.
     *
     * Each tier: array( 'qty' => float (kg threshold), 'price' => float (per kg) ).
     * Sorted ascending by quantity. Empty when none are defined.
     *
     * @param int $post_id Trade name post ID.
     * @return array[]
     */
    public static function get_price_tiers( $post_id ) {
        $rows = get_post_meta( $post_id, '_pc_price_tiers', true );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            $qty   = isset( $row['qty'] ) ? floatval( $row['qty'] ) : 0;
            $price = isset( $row['price'] ) ? floatval( $row['price'] ) : 0;
            if ( $qty > 0 && $price > 0 ) {
                $clean[] = array( 'qty' => $qty, 'price' => $price );
            }
        }

        usort( $clean, function ( $a, $b ) {
            if ( $a['qty'] == $b['qty'] ) {
                return 0;
            }
            return ( $a['qty'] < $b['qty'] ) ? -1 : 1;
        } );

        return $clean;
    }

    /**
     * Resolve the price per kg for a given purchase quantity using the trade
     * name's bulk pricing tiers, falling back to a base price when no tier
     * applies or no tiers are defined.
     *
     * The applicable tier is the one with the largest quantity threshold that
     * is still ≤ the quantity purchased. Below the smallest threshold, the
     * smallest tier's price is used.
     *
     * @param int   $post_id        Trade name post ID.
     * @param float $qty            Quantity being purchased (kg).
     * @param float $fallback_price Price per kg to use when no tiers exist.
     * @return float
     */
    public static function price_for_qty( $post_id, $qty, $fallback_price ) {
        $tiers = self::get_price_tiers( $post_id );
        if ( empty( $tiers ) ) {
            return $fallback_price;
        }

        $price = $tiers[0]['price']; // Smallest-quantity price as the base.
        foreach ( $tiers as $tier ) {
            if ( $qty >= $tier['qty'] ) {
                $price = $tier['price'];
            }
        }
        return $price;
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
