<?php
/**
 * INCI label declaration generator.
 *
 * Builds the ingredient declaration for a product from the formula rows and
 * each trade name's INCI composition: contributions are summed per INCI name,
 * sorted in descending order, with the ≤1% group separated (those may be
 * listed in any order under EU/UK labelling rules) and EU fragrance
 * allergens flagged.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_INCI {

    private static $instance = null;

    /**
     * The 26 EU fragrance allergens subject to declaration (INCI names).
     * Note: EU Regulation 2023/1545 expands this list — extend via the
     * 'pc_fragrance_allergens' filter as your assessor requires.
     */
    private static $allergens = array(
        'amyl cinnamal',
        'amylcinnamyl alcohol',
        'anise alcohol',
        'benzyl alcohol',
        'benzyl benzoate',
        'benzyl cinnamate',
        'benzyl salicylate',
        'butylphenyl methylpropional',
        'cinnamal',
        'cinnamyl alcohol',
        'citral',
        'citronellol',
        'coumarin',
        'eugenol',
        'evernia furfuracea extract',
        'evernia prunastri extract',
        'farnesol',
        'geraniol',
        'hexyl cinnamal',
        'hydroxycitronellal',
        'hydroxyisohexyl 3-cyclohexene carboxaldehyde',
        'isoeugenol',
        'limonene',
        'linalool',
        'methyl 2-octynoate',
        'alpha-isomethyl ionone',
    );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
    }

    public function register_metabox() {
        add_meta_box(
            'pc_inci_declaration',
            __( 'INCI Label Declaration', 'product-costings' ),
            array( $this, 'render_metabox' ),
            'products',
            'normal',
            'low'
        );
    }

    /**
     * Generate the INCI declaration from formula rows.
     *
     * @param array $rows Formula rows (_pc_formula_rows format).
     * @return array {
     *   'over_1'  => array of array( 'inci', 'percent', 'is_allergen' ) sorted desc,
     *   'under_1' => same, for the ≤1% group,
     *   'missing' => array of trade name titles with no INCI composition,
     * }
     */
    public static function generate( $rows ) {
        $totals  = array(); // normalized name => percent
        $display = array(); // normalized name => display name (first seen)
        $missing = array();

        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        foreach ( $rows as $row ) {
            $ww       = floatval( $row['percent_w_w'] ?? 0 );
            $trade_id = absint( $row['trade_name_id'] ?? 0 );

            if ( $ww <= 0 || ! $trade_id ) {
                continue;
            }

            $composition = PC_Trade_Data::get_composition( $trade_id );

            if ( empty( $composition ) ) {
                $missing[] = get_the_title( $trade_id );
                continue;
            }

            foreach ( $composition as $comp ) {
                // Merge INCI synonyms (Water/Aqua/Eau, …) so they total as one line.
                $canonical    = self::canonicalize_inci( trim( $comp['inci'] ) );
                $norm         = $canonical['key'];
                $contribution = $ww * ( $comp['percent'] / 100 );

                if ( ! isset( $totals[ $norm ] ) ) {
                    $totals[ $norm ]  = 0;
                    $display[ $norm ] = $canonical['display'];
                }
                $totals[ $norm ] += $contribution;
            }
        }

        arsort( $totals );

        $allergens = apply_filters( 'pc_fragrance_allergens', self::$allergens );
        $allergens = array_map( 'strtolower', $allergens );

        $over_1  = array();
        $under_1 = array();

        foreach ( $totals as $norm => $percent ) {
            $entry = array(
                'inci'        => $display[ $norm ],
                'percent'     => $percent,
                'is_allergen' => in_array( $norm, $allergens, true ),
            );
            if ( $percent > 1 ) {
                $over_1[] = $entry;
            } else {
                $under_1[] = $entry;
            }
        }

        return array(
            'over_1'  => $over_1,
            'under_1' => $under_1,
            'missing' => array_unique( $missing ),
        );
    }

    /**
     * Map an INCI name to its canonical form, merging equivalent synonyms so
     * they total as a single declaration line (e.g. Water + Aqua + Eau → Aqua).
     *
     * A name is merged only when *every* alphabetic word in it belongs to one
     * synonym group, so combined forms like "Aqua/Water/Eau" and "Aqua (Water)"
     * merge, while distinct names that merely contain a group word — e.g.
     * "Rosa Damascena Flower Water", "Maris Aqua" (sea water) — do not.
     *
     * Groups are filterable via 'pc_inci_synonym_groups' (canonical => members).
     *
     * @param string $name Raw INCI name.
     * @return array{key:string,display:string}
     */
    public static function canonicalize_inci( $name ) {
        $name = trim( $name );

        $groups = apply_filters( 'pc_inci_synonym_groups', array(
            // Canonical label name => equivalent INCI words.
            'Aqua' => array( 'aqua', 'water', 'eau' ),
        ) );

        // Alphabetic words only, lowercased, ignoring the blend connector "and".
        $words = preg_split( '/[^a-z]+/i', strtolower( $name ), -1, PREG_SPLIT_NO_EMPTY );
        $words = array_values( array_filter( (array) $words, function ( $w ) {
            return 'and' !== $w;
        } ) );

        if ( ! empty( $words ) ) {
            foreach ( $groups as $canonical => $members ) {
                $members = array_map( 'strtolower', (array) $members );
                // Every word in the name is a member of this group → it's a synonym.
                if ( array() === array_diff( $words, $members ) ) {
                    return array(
                        'key'     => strtolower( $canonical ),
                        'display' => $canonical,
                    );
                }
            }
        }

        return array(
            'key'     => strtolower( $name ),
            'display' => $name,
        );
    }

    public function render_metabox( $post ) {
        $rows = get_post_meta( $post->ID, '_pc_formula_rows', true );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Add formula ingredients and save the product to generate the INCI declaration.', 'product-costings' ) . '</p>';
            return;
        }

        $result = self::generate( $rows );
        $all    = array_merge( $result['over_1'], $result['under_1'] );

        if ( ! empty( $result['missing'] ) ) {
            echo '<div class="pc-inci-missing"><p><strong>' . esc_html__( 'Missing INCI data:', 'product-costings' ) . '</strong> ';
            echo esc_html( implode( ', ', $result['missing'] ) );
            echo ' &mdash; ' . esc_html__( 'add an INCI Composition on each of these Trade Names, then re-save this product. The declaration below is incomplete until then.', 'product-costings' ) . '</p></div>';
        }

        if ( empty( $all ) ) {
            echo '<p>' . esc_html__( 'No INCI data available yet.', 'product-costings' ) . '</p>';
            return;
        }

        echo '<ol class="pc-inci-list">';
        foreach ( $result['over_1'] as $entry ) {
            $this->render_entry( $entry );
        }
        if ( ! empty( $result['under_1'] ) ) {
            echo '<li class="pc-inci-divider">' . esc_html__( '— 1% threshold: ingredients below may be listed in any order —', 'product-costings' ) . '</li>';
            foreach ( $result['under_1'] as $entry ) {
                $this->render_entry( $entry );
            }
        }
        echo '</ol>';

        // Copy-ready string.
        $names = array();
        foreach ( $all as $entry ) {
            $names[] = $entry['inci'];
        }
        ?>
        <p><strong><?php esc_html_e( 'Copy-ready declaration:', 'product-costings' ); ?></strong></p>
        <textarea class="widefat" rows="3" readonly onclick="this.select();"><?php echo esc_textarea( implode( ', ', $names ) ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Percentages shown are calculated contributions and appear here for your reference only — they are not part of the label declaration. Verify allergen declarations against your CPSR; allergens over 0.001% (leave-on) / 0.01% (rinse-off) must be declared.', 'product-costings' ); ?>
        </p>
        <?php
    }

    private function render_entry( $entry ) {
        echo '<li>';
        echo '<span class="pc-inci-name">' . esc_html( $entry['inci'] ) . '</span>';
        echo ' <span class="pc-inci-pct">' . esc_html( number_format( $entry['percent'], 3 ) . '%' ) . '</span>';
        if ( $entry['is_allergen'] ) {
            echo ' <span class="pc-inci-allergen">' . esc_html__( 'allergen', 'product-costings' ) . '</span>';
        }
        echo '</li>';
    }
}
