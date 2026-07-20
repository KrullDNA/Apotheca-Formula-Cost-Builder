<?php
/**
 * Elementor Widget – Batch Costings.
 *
 * Displays calculated costing metrics for a product batch.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Widget_Batch_Costings extends \Elementor\Widget_Base {

    public function get_name() {
        return 'pc_batch_costings';
    }

    public function get_title() {
        return esc_html__( 'Batch Costings', 'product-costings' );
    }

    public function get_icon() {
        return 'eicon-price-list';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'batch', 'costing', 'cost', 'price', 'formula', 'product' );
    }

    public function get_style_depends() {
        return array( 'pc-batch-costings-front' );
    }

    /**
     * Available calculation definitions.
     */
    private function get_metric_options() {
        return array(
            'batch_cost'                  => 'Batch Cost',
            'total_cost_per_kg'           => 'Total Cost/Kg',
            'total_packaging_units'       => 'Total Packaging Units',
            'single_product_ingredients'  => 'Single Product Ingredients Cost',
            'packaging_cost_per_batch'    => 'Packaging Cost per Batch',
            'final_batch_cost'            => 'Final Batch Cost',
            'final_unit_cost'             => 'Final Unit Cost',
            'my_cost_price'               => 'My Cost Price',
            'wholesale_price'             => 'Wholesale Price',
            'rrp'                         => 'RRP',
            'packaging_unit_cost'         => 'Packaging Unit Cost',
            'labour'                      => 'Labour',
            'facility_running_costs'      => 'Facility Running Costs',
            'misc_costs'                  => 'Misc Costs',
            'batch_size'                  => 'Batch Size',
            'batch_size_with_waste'       => 'Batch Size with Waste',
            'unit_size'                   => 'Packaging Size',
            'natural_origin'              => '% Natural Origin',
        );
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

        $this->add_control( 'metrics', array(
            'label'       => esc_html__( 'Calculations to Display', 'product-costings' ),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->get_metric_options(),
            'default'     => array( 'batch_cost', 'final_batch_cost', 'final_unit_cost' ),
            'description' => esc_html__( 'Select which costing calculations to show.', 'product-costings' ),
        ) );

        $this->add_control( 'currency_symbol', array(
            'label'   => esc_html__( 'Currency Symbol', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => get_option( 'pc_currency_symbol', '$' ),
        ) );

        $this->add_control( 'prefix_text', array(
            'label'       => esc_html__( 'Value Prefix Text', 'product-costings' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'description' => esc_html__( 'Text displayed before the dollar figure (e.g. "AUD" or "Approx.").', 'product-costings' ),
            'default'     => '',
        ) );

        $this->add_control( 'waste_percent', array(
            'label'       => esc_html__( 'Waste %', 'product-costings' ),
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'description' => esc_html__( 'Manufacturing waste allowance added to batch size (e.g. 2 for 2%).', 'product-costings' ),
            'default'     => 2,
            'min'         => 0,
            'max'         => 50,
            'step'        => 0.5,
        ) );

        $this->add_control( 'label_overrides_heading', array(
            'label'     => esc_html__( 'Label Overrides', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        foreach ( $this->get_metric_options() as $key => $default_label ) {
            $this->add_control( 'label_' . $key, array(
                'label'       => $default_label,
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => $default_label,
                'description' => sprintf( esc_html__( 'Leave blank to use "%s".', 'product-costings' ), $default_label ),
            ) );
        }

        $this->end_controls_section();

        /* ── Style: Layout ── */
        $this->start_controls_section( 'section_style_layout', array(
            'label' => esc_html__( 'Layout', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'item_bg_color', array(
            'label'     => esc_html__( 'Row Background', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-item' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'item_alt_bg_color', array(
            'label'     => esc_html__( 'Alternate Row Background', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f9f9f9',
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-item:nth-child(even)' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'item_border_color', array(
            'label'     => esc_html__( 'Divider Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e5e5',
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-item' => 'border-bottom-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'item_padding', array(
            'label'      => esc_html__( 'Row Padding', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array(
                'top'    => '14',
                'right'  => '20',
                'bottom' => '14',
                'left'   => '20',
                'unit'   => 'px',
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-bc-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'wrapper_border',
            'label'    => esc_html__( 'Container Border', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-bc',
        ) );

        $this->add_control( 'wrapper_border_radius', array(
            'label'      => esc_html__( 'Border Radius', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-bc' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'wrapper_box_shadow',
            'label'    => esc_html__( 'Box Shadow', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-bc',
        ) );

        $this->end_controls_section();

        /* ── Style: Label ── */
        $this->start_controls_section( 'section_style_label', array(
            'label' => esc_html__( 'Label', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'label_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#1a1a1a',
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-label' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-bc-label',
        ) );

        $this->end_controls_section();

        /* ── Style: Value ── */
        $this->start_controls_section( 'section_style_value', array(
            'label' => esc_html__( 'Value', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'value_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#1a1a1a',
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-value' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'value_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-bc-value',
        ) );

        /* ── Style: Prefix ── */
        $this->add_control( 'prefix_heading', array(
            'label'     => esc_html__( 'Prefix Text', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'prefix_color', array(
            'label'     => esc_html__( 'Prefix Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-bc-prefix' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'prefix_typography',
            'label'    => esc_html__( 'Prefix Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-bc-prefix',
        ) );

        $this->end_controls_section();
    }

    /* ─────────────────────────────────────
     * Render
     * ───────────────────────────────────── */

    protected function render() {
        $settings = $this->get_settings_for_display();

        $product_id = ! empty( $settings['product_id'] ) ? absint( $settings['product_id'] ) : get_the_ID();

        if ( ! $product_id || 'products' !== get_post_type( $product_id ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'Batch Costings — please view on a Products post or enter a Product ID.', 'product-costings' ) . '</p>';
            }
            return;
        }

        $selected = ! empty( $settings['metrics'] ) ? $settings['metrics'] : array();
        if ( empty( $selected ) ) {
            return;
        }

        $currency   = $settings['currency_symbol'];
        $prefix     = $settings['prefix_text'];
        $waste_pct  = isset( $settings['waste_percent'] ) ? floatval( $settings['waste_percent'] ) : 2;

        // Gather data.
        $values = $this->calculate_metrics( $product_id, $waste_pct );
        $labels = $this->get_metric_options();

        // Determine which metrics are currency vs. plain number.
        $non_currency    = array( 'total_packaging_units', 'batch_size', 'batch_size_with_waste', 'natural_origin' );
        $whole_number    = array( 'total_packaging_units' );
        $kg_suffix       = array( 'batch_size', 'batch_size_with_waste' );
        $percent_suffix  = array( 'natural_origin' );

        echo '<div class="pc-bc">';

        foreach ( $selected as $key ) {
            if ( ! isset( $values[ $key ] ) || ! isset( $labels[ $key ] ) ) {
                continue;
            }

            $raw      = $values[ $key ];
            $override = ! empty( $settings[ 'label_' . $key ] ) ? $settings[ 'label_' . $key ] : '';
            $label    = '' !== $override ? $override : $labels[ $key ];

            if ( 'unit_size' === $key ) {
                // Packaging size with its g/ml suffix, no space (e.g. 15g, 30ml).
                $unit      = ( isset( $values['unit_size_unit'] ) && 'ml' === $values['unit_size_unit'] ) ? 'ml' : 'g';
                $num       = rtrim( rtrim( number_format( (float) $raw, 3 ), '0' ), '.' );
                $formatted = $num . $unit;
            } elseif ( in_array( $key, $kg_suffix, true ) ) {
                $formatted = number_format( $raw, 0 ) . ' kg';
            } elseif ( in_array( $key, $whole_number, true ) ) {
                $formatted = number_format( $raw, 0 );
            } elseif ( in_array( $key, $percent_suffix, true ) ) {
                $formatted = number_format( $raw, 2 ) . '%';
            } elseif ( in_array( $key, $non_currency, true ) ) {
                $formatted = number_format( $raw, 2 );
            } else {
                $formatted = $currency . number_format( $raw, 2 );
            }

            echo '<div class="pc-bc-item">';
            echo '<span class="pc-bc-label">' . esc_html( $label ) . '</span>';
            echo '<span class="pc-bc-value">';
            if ( '' !== $prefix ) {
                echo '<span class="pc-bc-prefix">' . esc_html( $prefix ) . ' </span>';
            }
            echo esc_html( $formatted );
            echo '</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Run all costing calculations for a product (delegates to the shared
     * calculator so every surface reports identical numbers).
     */
    private function calculate_metrics( $product_id, $waste_pct ) {
        return PC_Costing_Calculator::metrics( $product_id, $waste_pct );
    }
}
