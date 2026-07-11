<?php
/**
 * Elementor Widget – INCI Ingredients List.
 *
 * Front-end display of the auto-generated INCI declaration for a product,
 * in standard label format (descending order, ≤1% group last).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Widget_INCI_List extends \Elementor\Widget_Base {

    public function get_name() {
        return 'pc_inci_list';
    }

    public function get_title() {
        return esc_html__( 'INCI Ingredients List', 'product-costings' );
    }

    public function get_icon() {
        return 'eicon-editor-list-ul';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'inci', 'ingredients', 'label', 'declaration', 'product', 'formula' );
    }

    /* ─────────────────────────────────────
     * Controls
     * ───────────────────────────────────── */

    protected function register_controls() {

        /* ── Content ── */
        $this->start_controls_section( 'section_content', array(
            'label' => esc_html__( 'Content', 'product-costings' ),
        ) );

        $this->add_control( 'product_id', array(
            'label'       => esc_html__( 'Product', 'product-costings' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'description' => esc_html__( 'Leave blank to use the current product. Or enter a Product post ID.', 'product-costings' ),
            'default'     => '',
        ) );

        $this->add_control( 'heading_text', array(
            'label'   => esc_html__( 'Heading', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => esc_html__( 'Ingredients', 'product-costings' ),
            'description' => esc_html__( 'Leave blank to hide the heading.', 'product-costings' ),
        ) );

        $this->add_control( 'format', array(
            'label'   => esc_html__( 'Format', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'inline' => esc_html__( 'Inline (comma separated, label style)', 'product-costings' ),
                'list'   => esc_html__( 'Bulleted list', 'product-costings' ),
            ),
            'default' => 'inline',
        ) );

        $this->add_control( 'uppercase', array(
            'label'        => esc_html__( 'Uppercase INCI names', 'product-costings' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
        ) );

        $this->add_control( 'highlight_allergens', array(
            'label'        => esc_html__( 'Emphasise fragrance allergens', 'product-costings' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'description'  => esc_html__( 'Wraps the EU fragrance allergens in an emphasised style (see the Allergens style section).', 'product-costings' ),
        ) );

        $this->add_control( 'empty_message', array(
            'label'   => esc_html__( 'Empty Message', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'description' => esc_html__( 'Shown when no INCI data exists. Leave blank to output nothing.', 'product-costings' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Heading ── */
        $this->start_controls_section( 'section_style_heading', array(
            'label' => esc_html__( 'Heading', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'heading_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-inci-front-heading' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'heading_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-inci-front-heading',
        ) );

        $this->end_controls_section();

        /* ── Style: Text ── */
        $this->start_controls_section( 'section_style_text', array(
            'label' => esc_html__( 'Ingredients Text', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'text_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-inci-front-text, {{WRAPPER}} .pc-inci-front-list li' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'text_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-inci-front-text, {{WRAPPER}} .pc-inci-front-list li',
        ) );

        $this->end_controls_section();

        /* ── Style: Allergens ── */
        $this->start_controls_section( 'section_style_allergens', array(
            'label'     => esc_html__( 'Allergens', 'product-costings' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => array( 'highlight_allergens' => 'yes' ),
        ) );

        $this->add_control( 'allergen_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-inci-front-allergen' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'allergen_style', array(
            'label'   => esc_html__( 'Emphasis', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'italic' => esc_html__( 'Italic', 'product-costings' ),
                'bold'   => esc_html__( 'Bold', 'product-costings' ),
                'none'   => esc_html__( 'None (colour only)', 'product-costings' ),
            ),
            'default' => 'italic',
        ) );

        $this->end_controls_section();
    }

    /* ─────────────────────────────────────
     * Render
     * ───────────────────────────────────── */

    protected function render() {
        $settings = $this->get_settings_for_display();

        $product_id = ! empty( $settings['product_id'] ) ? absint( $settings['product_id'] ) : get_the_ID();

        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();

        if ( ! $product_id || 'products' !== get_post_type( $product_id ) ) {
            if ( $is_editor ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'INCI Ingredients List — please view on a Products post or enter a Product ID.', 'product-costings' ) . '</p>';
            }
            return;
        }

        $rows   = get_post_meta( $product_id, '_pc_formula_rows', true );
        $result = PC_INCI::generate( is_array( $rows ) ? $rows : array() );
        $all    = array_merge( $result['over_1'], $result['under_1'] );

        if ( empty( $all ) ) {
            if ( $settings['empty_message'] ) {
                echo '<p class="pc-inci-front-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            } elseif ( $is_editor ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'No INCI data yet — add INCI Compositions to this product\'s Trade Names.', 'product-costings' ) . '</p>';
            }
            return;
        }

        // Editor-only completeness notice; never shown to site visitors.
        if ( $is_editor && ! empty( $result['missing'] ) ) {
            echo '<p style="padding:8px 12px;background:#fcf6e5;border-left:4px solid #dba617;color:#6d5a13;">'
                . esc_html__( 'Incomplete declaration — missing INCI data for: ', 'product-costings' )
                . esc_html( implode( ', ', $result['missing'] ) )
                . '</p>';
        }

        $uppercase = 'yes' === $settings['uppercase'];
        $highlight = 'yes' === $settings['highlight_allergens'];

        $emphasis_css = '';
        if ( $highlight ) {
            if ( 'italic' === $settings['allergen_style'] ) {
                $emphasis_css = 'font-style:italic;';
            } elseif ( 'bold' === $settings['allergen_style'] ) {
                $emphasis_css = 'font-weight:700;';
            }
        }

        $render_name = function ( $entry ) use ( $uppercase, $highlight, $emphasis_css ) {
            $name = $uppercase
                ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $entry['inci'] ) : strtoupper( $entry['inci'] ) )
                : $entry['inci'];

            if ( $highlight && $entry['is_allergen'] ) {
                return '<span class="pc-inci-front-allergen"' . ( $emphasis_css ? ' style="' . esc_attr( $emphasis_css ) . '"' : '' ) . '>' . esc_html( $name ) . '</span>';
            }
            return esc_html( $name );
        };

        echo '<div class="pc-inci-front">';

        if ( ! empty( $settings['heading_text'] ) ) {
            echo '<h3 class="pc-inci-front-heading">' . esc_html( $settings['heading_text'] ) . '</h3>';
        }

        if ( 'list' === $settings['format'] ) {
            echo '<ul class="pc-inci-front-list">';
            foreach ( $all as $entry ) {
                echo '<li>' . $render_name( $entry ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $render_name.
            }
            echo '</ul>';
        } else {
            $parts = array();
            foreach ( $all as $entry ) {
                $parts[] = $render_name( $entry );
            }
            echo '<p class="pc-inci-front-text">' . implode( ', ', $parts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $render_name.
        }

        echo '</div>';
    }
}
