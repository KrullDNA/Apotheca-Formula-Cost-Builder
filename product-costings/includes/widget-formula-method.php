<?php
/**
 * Elementor Widget – Formula Method.
 *
 * Front-end display of a product's manufacturing Method (the WYSIWYG content
 * entered in the Formula Ingredients & Method box), with an optional heading.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Widget_Formula_Method extends \Elementor\Widget_Base {

    public function get_name() {
        return 'pc_formula_method';
    }

    public function get_title() {
        return esc_html__( 'Formula Method', 'product-costings' );
    }

    public function get_icon() {
        return 'eicon-document-file';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'method', 'instructions', 'process', 'formula', 'product', 'batch' );
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

        $this->add_control( 'show_heading', array(
            'label'        => esc_html__( 'Show heading', 'product-costings' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ) );

        $this->add_control( 'heading_text', array(
            'label'     => esc_html__( 'Heading', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::TEXT,
            'default'   => esc_html__( 'Method', 'product-costings' ),
            'condition' => array( 'show_heading' => 'yes' ),
        ) );

        $this->add_control( 'heading_tag', array(
            'label'     => esc_html__( 'Heading HTML tag', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::SELECT,
            'options'   => array(
                'h2'   => 'H2',
                'h3'   => 'H3',
                'h4'   => 'H4',
                'h5'   => 'H5',
                'div'  => 'div',
                'span' => 'span',
            ),
            'default'   => 'h3',
            'condition' => array( 'show_heading' => 'yes' ),
        ) );

        $this->add_control( 'empty_message', array(
            'label'       => esc_html__( 'Empty Message', 'product-costings' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => esc_html__( 'Shown when the product has no method. Leave blank to output nothing.', 'product-costings' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Heading ── */
        $this->start_controls_section( 'section_style_heading', array(
            'label'     => esc_html__( 'Heading', 'product-costings' ),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_heading' => 'yes' ),
        ) );

        $this->add_control( 'heading_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-method-front-heading' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'heading_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-method-front-heading',
        ) );

        $this->add_responsive_control( 'heading_spacing', array(
            'label'      => esc_html__( 'Spacing below', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-method-front-heading' => 'margin: 0 0 {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Method text ── */
        $this->start_controls_section( 'section_style_body', array(
            'label' => esc_html__( 'Method Text', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'text_color', array(
            'label'     => esc_html__( 'Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-method-front-body' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'text_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-method-front-body',
        ) );

        $this->add_responsive_control( 'text_align', array(
            'label'     => esc_html__( 'Alignment', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::CHOOSE,
            'options'   => array(
                'left'    => array( 'title' => esc_html__( 'Left', 'product-costings' ),    'icon' => 'eicon-text-align-left' ),
                'center'  => array( 'title' => esc_html__( 'Center', 'product-costings' ),  'icon' => 'eicon-text-align-center' ),
                'right'   => array( 'title' => esc_html__( 'Right', 'product-costings' ),   'icon' => 'eicon-text-align-right' ),
                'justify' => array( 'title' => esc_html__( 'Justify', 'product-costings' ), 'icon' => 'eicon-text-align-justify' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pc-method-front-body' => 'text-align: {{VALUE}};',
            ),
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
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'Formula Method — please view on a Products post or enter a Product ID.', 'product-costings' ) . '</p>';
            }
            return;
        }

        $method = PC_Costing_Calculator::get_product_meta_text( $product_id, 'method' );

        if ( '' === trim( wp_strip_all_tags( (string) $method ) ) ) {
            if ( ! empty( $settings['empty_message'] ) ) {
                echo '<p class="pc-method-front-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            } elseif ( $is_editor ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'No method entered yet — add one in the Formula Ingredients & Method box on this product.', 'product-costings' ) . '</p>';
            }
            return;
        }

        echo '<div class="pc-method-front">';

        if ( 'yes' === $settings['show_heading'] && ! empty( $settings['heading_text'] ) ) {
            $allowed_tags = array( 'h2', 'h3', 'h4', 'h5', 'div', 'span' );
            $tag          = in_array( $settings['heading_tag'], $allowed_tags, true ) ? $settings['heading_tag'] : 'h3';
            printf(
                '<%1$s class="pc-method-front-heading">%2$s</%1$s>',
                $tag, // Whitelisted above.
                esc_html( $settings['heading_text'] )
            );
        }

        echo '<div class="pc-method-front-body">' . wp_kses_post( $method ) . '</div>';

        echo '</div>';
    }
}
