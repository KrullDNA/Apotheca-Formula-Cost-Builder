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
     * @return array[] Array of array( 'inci' => string, 'percent_min' => float,
     *                 'percent_max' => float, 'percent' => float (midpoint) ).
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

                // Range support: percent_min / percent_max, falling back to a
                // single stored percent for older data.
                if ( isset( $row['percent_min'] ) || isset( $row['percent_max'] ) ) {
                    $min = isset( $row['percent_min'] ) && '' !== $row['percent_min'] ? floatval( $row['percent_min'] ) : 0;
                    $max = isset( $row['percent_max'] ) && '' !== $row['percent_max'] ? floatval( $row['percent_max'] ) : $min;
                } else {
                    $single = isset( $row['percent'] ) ? floatval( $row['percent'] ) : 100;
                    $min    = $single;
                    $max    = $single;
                }
                if ( $max < $min ) {
                    $tmp = $min;
                    $min = $max;
                    $max = $tmp;
                }

                // A single row may itself hold a blend written with (and)/and/&/comma.
                $names = self::split_inci_names( $inci );
                if ( empty( $names ) ) {
                    continue;
                }
                $count = count( $names );
                foreach ( $names as $name ) {
                    $row_min = $min / $count;
                    $row_max = $max / $count;
                    $clean[] = array(
                        'inci'        => $name,
                        'percent_min' => $row_min,
                        'percent_max' => $row_max,
                        'percent'     => ( $row_min + $row_max ) / 2, // Midpoint (nominal).
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
                    'inci'        => $name,
                    'percent_min' => $share,
                    'percent_max' => $share,
                    'percent'     => $share,
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
     * Specific gravity (density relative to water, kg/L) of a trade name.
     * Used to convert litre-based supplier pricing to a per-kg basis.
     *
     * @param int $post_id Trade name post ID.
     * @return float 0 when not set.
     */
    public static function get_specific_gravity( $post_id ) {
        $sg = get_post_meta( $post_id, '_pc_specific_gravity', true );
        return ( '' !== $sg && null !== $sg ) ? floatval( $sg ) : 0;
    }

    /**
     * Raw bulk pricing tiers exactly as entered (for the editor).
     *
     * Each tier: array( 'qty' => float, 'price' => float, 'unit' => 'kg'|'L' ).
     *
     * @param int $post_id Trade name post ID.
     * @return array[]
     */
    public static function get_price_tiers_raw( $post_id ) {
        $rows = get_post_meta( $post_id, '_pc_price_tiers', true );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            $qty   = isset( $row['qty'] ) ? floatval( $row['qty'] ) : 0;
            $price = isset( $row['price'] ) ? floatval( $row['price'] ) : 0;
            $unit  = ( isset( $row['unit'] ) && 'L' === $row['unit'] ) ? 'L' : 'kg';
            if ( $qty > 0 && $price > 0 ) {
                $clean[] = array( 'qty' => $qty, 'price' => $price, 'unit' => $unit );
            }
        }
        return $clean;
    }

    /**
     * Bulk pricing tiers resolved for costing.
     *
     * Each stored tier is a pack: a pack size (Kg or L) and the TOTAL price of
     * that pack. Litre packs are converted to kg using the material's specific
     * gravity: qty(kg) = qty(L) × SG (the pack's total price is unchanged).
     *
     * Each returned tier: array( 'qty' => float (kg pack size), 'cost' => float
     * (total pack price), 'price' => float (derived per-kg = cost ÷ qty) ).
     * Sorted ascending by quantity. Empty when none are defined.
     *
     * @param int $post_id Trade name post ID.
     * @return array[]
     */
    public static function get_price_tiers( $post_id ) {
        $raw = self::get_price_tiers_raw( $post_id );
        if ( empty( $raw ) ) {
            return array();
        }

        $sg    = self::get_specific_gravity( $post_id );
        $clean = array();
        foreach ( $raw as $tier ) {
            $pack_cost = $tier['price']; // Stored value is the total pack price.
            if ( 'L' === $tier['unit'] && $sg > 0 ) {
                $qty_kg = $tier['qty'] * $sg;
            } else {
                $qty_kg = $tier['qty'];
            }
            if ( $qty_kg <= 0 ) {
                continue;
            }
            $clean[] = array(
                'qty'   => $qty_kg,
                'cost'  => $pack_cost,
                'price' => $pack_cost / $qty_kg, // Per-kg, for display.
            );
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
     * Cheapest total cost to obtain at least $kg_needed of a material.
     *
     * Each bulk pricing tier is a pack size bought in whole multiples at its
     * per-kg price (the tiers replace MOQ). Packs of different sizes may be
     * combined, and the cheapest combination that covers the need is chosen —
     * e.g. 2.2 kg → 3 × 1 kg = $150, 6 kg → 1 × 5 kg + 1 × 1 kg = $250, and a
     * single large pack is used whenever it is cheapest. Without tiers it is
     * simply needed × base price.
     *
     * @param int   $trade_id       Trade name post ID (0 when none selected).
     * @param float $kg_needed       Kilograms required for the batch.
     * @param float $fallback_price  Price per kg when no tiers are defined.
     * @return array{qty:float,price:float,cost:float}
     */
    public static function cheapest_purchase( $trade_id, $kg_needed, $fallback_price ) {
        $kg_needed = max( 0, floatval( $kg_needed ) );
        $tiers     = $trade_id ? self::get_price_tiers( $trade_id ) : array();

        if ( empty( $tiers ) ) {
            return array(
                'qty'   => $kg_needed,
                'price' => $fallback_price,
                'cost'  => $kg_needed * $fallback_price,
            );
        }

        if ( $kg_needed <= 0 ) {
            return array( 'qty' => 0, 'price' => 0, 'cost' => 0 );
        }

        // Build integer-gram packs (pack size + whole-pack total price).
        $packs = array();
        foreach ( $tiers as $tier ) {
            if ( $tier['qty'] <= 0 ) {
                continue;
            }
            $grams = (int) round( $tier['qty'] * 1000 );
            if ( $grams > 0 ) {
                $packs[] = array( 'g' => $grams, 'cost' => $tier['cost'] );
            }
        }
        if ( empty( $packs ) ) {
            return array( 'qty' => $kg_needed, 'price' => $fallback_price, 'cost' => $kg_needed * $fallback_price );
        }

        $need_g = (int) ceil( $kg_needed * 1000 );

        // Cheapest single pack size (safety answer + fallback for huge needs).
        $single = null;
        foreach ( $packs as $p ) {
            $count = (int) ceil( $need_g / $p['g'] );
            $cost  = $count * $p['cost'];
            if ( null === $single || $cost < $single['cost'] ) {
                $single = array( 'qty' => ( $count * $p['g'] ) / 1000, 'cost' => $cost );
            }
        }

        // Reduce the problem size by the GCD of all pack sizes and the need.
        $unit = $need_g;
        foreach ( $packs as $p ) {
            $unit = self::gcd_int( $unit, $p['g'] );
        }
        if ( $unit < 1 ) {
            $unit = 1;
        }
        $target = (int) ceil( $need_g / $unit );

        // Guard against pathological sizes.
        if ( $target > 300000 ) {
            return array( 'qty' => $single['qty'], 'price' => 0, 'cost' => $single['cost'] );
        }

        // DP: minimum cost to cover at least w units (overfill allowed).
        // Primary objective cost; when two options cost the same, prefer the
        // GREATER quantity — the extra material is free usable stock for
        // another product, so more-for-the-same-money wins the tie.
        $dp_cost = array_fill( 0, $target + 1, INF );
        $dp_qty  = array_fill( 0, $target + 1, -1 ); // Grams purchased.
        $dp_cost[0] = 0.0;
        $dp_qty[0]  = 0;
        for ( $w = 1; $w <= $target; $w++ ) {
            foreach ( $packs as $p ) {
                $u    = (int) ( $p['g'] / $unit );
                $prev = ( $w - $u > 0 ) ? $w - $u : 0;
                if ( INF === $dp_cost[ $prev ] ) {
                    continue;
                }
                $c = $p['cost'] + $dp_cost[ $prev ];
                $q = $p['g'] + $dp_qty[ $prev ];
                if ( $c < $dp_cost[ $w ] - 1e-9
                    || ( abs( $c - $dp_cost[ $w ] ) <= 1e-9 && $q > $dp_qty[ $w ] ) ) {
                    $dp_cost[ $w ] = $c;
                    $dp_qty[ $w ]  = $q;
                }
            }
        }

        return array(
            'qty'   => $dp_qty[ $target ] / 1000,
            'price' => 0,
            'cost'  => $dp_cost[ $target ],
        );
    }

    /**
     * Greatest common divisor of two non-negative integers.
     */
    private static function gcd_int( $a, $b ) {
        $a = (int) abs( $a );
        $b = (int) abs( $b );
        while ( $b ) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a;
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
