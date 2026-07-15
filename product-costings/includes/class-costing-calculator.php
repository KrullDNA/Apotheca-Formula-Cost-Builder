<?php
/**
 * Shared costing calculator.
 *
 * Single source of truth for all costing maths, used by the Batch Costings
 * Elementor widget, the Costings Dashboard, and the Formula Versions
 * comparison so every surface reports identical numbers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Costing_Calculator {

    /**
     * Run all costing calculations for a product.
     *
     * @param int        $product_id Product post ID.
     * @param float|null $waste_pct  Waste %. Null = use the product's saved
     *                               _pc_waste_percent (default 2).
     * @param array|null $rows       Formula rows override (e.g. a saved
     *                               version snapshot). Null = saved rows.
     * @return array Metric key => value.
     */
    public static function metrics( $product_id, $waste_pct = null, $rows = null ) {

        if ( null === $waste_pct ) {
            $saved     = get_post_meta( $product_id, '_pc_waste_percent', true );
            $waste_pct = ( '' === $saved ) ? 2 : floatval( $saved );
        }

        if ( null === $rows ) {
            $rows = get_post_meta( $product_id, '_pc_formula_rows', true );
        }
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        // Base data.
        $batch_size_raw = self::get_product_meta_value( $product_id, 'batch_size' );
        $batch_size     = $batch_size_raw * ( 1 + $waste_pct / 100 );
        $unit_size      = self::get_product_meta_value( $product_id, 'unit_size' ); // grams or ml.
        $labour         = self::get_product_meta_value( $product_id, 'labour' );
        $facility       = self::get_product_meta_value( $product_id, 'facility_running_costs' );
        $misc           = self::get_product_meta_value( $product_id, 'misc_costs' );
        $pkg_unit_cost  = self::get_product_meta_value( $product_id, 'packaging_unit_cost' );
        $cost_price_mul = self::get_product_meta_value( $product_id, 'cost_price' );
        $wholesale_mul  = self::get_product_meta_value( $product_id, 'wholesale' );
        $rrp_mul        = self::get_product_meta_value( $product_id, 'rrp' );

        // ── Total Packaging Units ──
        // Uses base batch size (without waste); unit_size is in grams/ml.
        $pkg_volume_kg         = $unit_size > 0 ? $unit_size / 1000 : 0;
        $total_packaging_units = $pkg_volume_kg > 0 ? floor( $batch_size_raw / $pkg_volume_kg ) : 0;

        // ── Batch Cost ──
        // Round each ingredient's kg requirement up to the next MOQ multiple,
        // then price that quantity using the trade name's bulk pricing tiers.
        $batch_cost = 0;
        foreach ( $rows as $row ) {
            $ww       = isset( $row['percent_w_w'] ) ? floatval( $row['percent_w_w'] ) : 0;
            $price    = isset( $row['price_per_kg'] ) ? floatval( $row['price_per_kg'] ) : 0;
            $moq      = isset( $row['moq'] ) ? floatval( $row['moq'] ) : 0;
            $trade_id = isset( $row['trade_name_id'] ) ? absint( $row['trade_name_id'] ) : 0;

            $kg_needed = $batch_size > 0 ? ( $ww / 100 ) * $batch_size : 0;

            if ( $kg_needed <= 0 ) {
                continue;
            }

            $kg_to_purchase = $moq > 0 ? ceil( $kg_needed / $moq ) * $moq : $kg_needed;

            // Bulk pricing: price the purchased quantity against the tier table
            // (falls back to the snapshot price when no tiers are defined).
            $unit_price = $trade_id
                ? PC_Trade_Data::price_for_qty( $trade_id, $kg_to_purchase, $price )
                : $price;

            if ( $unit_price <= 0 ) {
                continue;
            }

            $batch_cost += $kg_to_purchase * $unit_price;
        }

        $total_cost_per_kg          = $batch_size > 0 ? $batch_cost / $batch_size : 0;
        $single_product_ingredients = $total_packaging_units > 0 ? $batch_cost / $total_packaging_units : 0;
        $packaging_cost_per_batch   = $pkg_unit_cost * $total_packaging_units;
        $final_batch_cost           = $batch_cost + $labour + $facility + $misc + $packaging_cost_per_batch;
        $final_unit_cost            = $total_packaging_units > 0 ? $final_batch_cost / $total_packaging_units : 0;
        $my_cost_price              = $final_unit_cost * $cost_price_mul;
        $wholesale_price            = $final_unit_cost * $wholesale_mul;
        $rrp_value                  = ceil( $final_unit_cost * $rrp_mul );

        // ── % Natural Origin (weighted average) ──
        $nat_weighted_sum = 0;
        $nat_ww_sum       = 0;
        foreach ( $rows as $row ) {
            $ww      = isset( $row['percent_w_w'] ) ? floatval( $row['percent_w_w'] ) : 0;
            $nat_val = isset( $row['natural_origin'] ) ? floatval( $row['natural_origin'] ) : 0;
            if ( $ww <= 0 ) {
                continue;
            }
            $nat_weighted_sum += $ww * $nat_val;
            $nat_ww_sum       += $ww;
        }
        $natural_origin = $nat_ww_sum > 0 ? $nat_weighted_sum / $nat_ww_sum : 0;

        return array(
            'batch_cost'                 => $batch_cost,
            'total_cost_per_kg'          => $total_cost_per_kg,
            'total_packaging_units'      => $total_packaging_units,
            'single_product_ingredients' => $single_product_ingredients,
            'packaging_cost_per_batch'   => $packaging_cost_per_batch,
            'final_batch_cost'           => $final_batch_cost,
            'final_unit_cost'            => $final_unit_cost,
            'my_cost_price'              => $my_cost_price,
            'wholesale_price'            => $wholesale_price,
            'rrp'                        => $rrp_value,
            'packaging_unit_cost'        => $pkg_unit_cost,
            'labour'                     => $labour,
            'facility_running_costs'     => $facility,
            'misc_costs'                 => $misc,
            'batch_size'                 => $batch_size_raw,
            'batch_size_with_waste'      => $batch_size,
            'natural_origin'             => $natural_origin,
        );
    }

    /**
     * Whether any formula row's saved price differs from the trade name's
     * current price (i.e. the snapshot has gone stale).
     */
    public static function has_stale_prices( $product_id ) {
        $rows = get_post_meta( $product_id, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) ) {
            return false;
        }

        foreach ( $rows as $row ) {
            $trade_id = absint( $row['trade_name_id'] ?? 0 );
            if ( ! $trade_id ) {
                continue;
            }
            $saved   = $row['price_per_kg'] ?? '';
            $current = PC_Trade_Data::get( $trade_id, 'price_per_kg' );
            if ( '' === $saved || '' === $current ) {
                continue;
            }
            if ( abs( floatval( $saved ) - floatval( $current ) ) > 0.0001 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Read a numeric product meta value from common key patterns.
     */
    public static function get_product_meta_value( $post_id, $field ) {
        $variants = array( $field, '_' . $field );

        foreach ( $variants as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return floatval( $val );
            }
        }

        if ( function_exists( 'get_field' ) ) {
            $val = get_field( $field, $post_id );
            if ( $val ) {
                return floatval( $val );
            }
        }

        return 0;
    }

    /**
     * Read a text product meta value (e.g. method, final_ph) from common key patterns.
     */
    public static function get_product_meta_text( $post_id, $field ) {
        $variants = array( $field, '_' . $field );

        foreach ( $variants as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return $val;
            }
        }

        if ( function_exists( 'get_field' ) ) {
            $val = get_field( $field, $post_id );
            if ( $val ) {
                return $val;
            }
        }

        return '';
    }
}
