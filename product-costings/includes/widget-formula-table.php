<?php
/**
 * Elementor Widget – Formula Ingredients Table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Widget_Formula_Table extends \Elementor\Widget_Base {

    public function get_name() {
        return 'pc_formula_table';
    }

    public function get_title() {
        return esc_html__( 'Formula Ingredients Table', 'product-costings' );
    }

    public function get_icon() {
        return 'eicon-table';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'formula', 'ingredients', 'table', 'product', 'costing' );
    }

    public function get_style_depends() {
        return array( 'pc-formula-table-front' );
    }

    /**
     * Column definitions used for registering per-column style controls.
     */
    private function get_column_defs() {
        return array(
            'phase'    => array( 'label' => 'Phase',        'class' => 'pc-ft-phase',    'default_align' => 'center' ),
            'ww'       => array( 'label' => '%w/w',         'class' => 'pc-ft-ww',       'default_align' => 'center' ),
            'trade'    => array( 'label' => 'Trade Name',   'class' => 'pc-ft-trade',    'default_align' => 'left'   ),
            'function' => array( 'label' => 'Function',     'class' => 'pc-ft-function', 'default_align' => 'center' ),
            'ph'       => array( 'label' => 'pH range',     'class' => 'pc-ft-ph',       'default_align' => 'center' ),
            'cost'     => array( 'label' => 'Cost/Kg',      'class' => 'pc-ft-cost',     'default_align' => 'center' ),
            'moq'      => array( 'label' => 'MOQ',          'class' => 'pc-ft-moq',      'default_align' => 'center' ),
            'kgbatch'  => array( 'label' => 'Kg per batch', 'class' => 'pc-ft-kgbatch',  'default_align' => 'center' ),
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

        $this->add_control( 'currency_symbol', array(
            'label'   => esc_html__( 'Currency Symbol', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => get_option( 'pc_currency_symbol', '$' ),
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

        $this->add_control( 'empty_message', array(
            'label'   => esc_html__( 'Empty Table Message', 'product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'No formula ingredients have been added yet.',
        ) );

        $this->end_controls_section();

        /* ── Header Style ── */
        $this->start_controls_section( 'section_style_header', array(
            'label' => esc_html__( 'Header', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'header_bg_color', array(
            'label'     => esc_html__( 'Background Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#1a1a1a',
            'selectors' => array(
                '{{WRAPPER}} .pc-ft thead th' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'header_text_color', array(
            'label'     => esc_html__( 'Text Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .pc-ft thead th' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'header_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-ft thead th',
        ) );

        $this->add_responsive_control( 'header_align', array(
            'label'     => esc_html__( 'Text Alignment', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => esc_html__( 'Left', 'product-costings' ),   'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => esc_html__( 'Center', 'product-costings' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => esc_html__( 'Right', 'product-costings' ),  'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => 'center',
            'selectors' => array(
                '{{WRAPPER}} .pc-ft thead th' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'header_padding', array(
            'label'      => esc_html__( 'Padding', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array(
                'top'    => '14',
                'right'  => '16',
                'bottom' => '14',
                'left'   => '16',
                'unit'   => 'px',
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-ft thead th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Body Rows Style ── */
        $this->start_controls_section( 'section_style_body', array(
            'label' => esc_html__( 'Body Rows', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'row_border_color', array(
            'label'     => esc_html__( 'Row Border Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e5e5',
            'selectors' => array(
                '{{WRAPPER}} .pc-ft tbody td' => 'border-bottom-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'body_padding', array(
            'label'      => esc_html__( 'Row Cell Padding', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array(
                'top'    => '12',
                'right'  => '16',
                'bottom' => '12',
                'left'   => '16',
                'unit'   => 'px',
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-ft tbody td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Footer Row Style ── */
        $this->start_controls_section( 'section_style_footer', array(
            'label' => esc_html__( 'Footer Row', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'footer_bg_color', array(
            'label'     => esc_html__( 'Background Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .pc-ft tfoot td' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'footer_text_color', array(
            'label'     => esc_html__( 'Text Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-ft tfoot td' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'footer_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-ft tfoot td',
        ) );

        $this->end_controls_section();

        /* ── Per-column style sections ── */
        foreach ( $this->get_column_defs() as $key => $col ) {
            $this->register_column_style_section( $key, $col );
        }

        /* ── Phase Colours ── */
        $this->start_controls_section( 'section_style_phase_colours', array(
            'label' => esc_html__( 'Phase Colours', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $phase_defaults = array(
            'A' => '#e8f5e9',
            'B' => '#e3f2fd',
            'C' => '#fff3e0',
            'D' => '#fce4ec',
            'E' => '#f3e5f5',
            'F' => '#e0f7fa',
            'G' => '#fff9c4',
            'H' => '#efebe9',
            'I' => '#e8eaf6',
            'J' => '#f1f8e9',
        );

        foreach ( $phase_defaults as $letter => $default_color ) {
            $this->add_control( 'phase_color_' . strtolower( $letter ), array(
                'label'   => sprintf( esc_html__( 'Phase %s', 'product-costings' ), $letter ),
                'type'    => \Elementor\Controls_Manager::COLOR,
                'default' => $default_color,
                'selectors' => array(
                    '{{WRAPPER}} .pc-ft tbody tr[data-phase="' . $letter . '"]' => 'background-color: {{VALUE}} !important;',
                ),
            ) );
        }

        $this->end_controls_section();

        /* ── Table Style ── */
        $this->start_controls_section( 'section_style_table', array(
            'label' => esc_html__( 'Table', 'product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'table_border',
            'label'    => esc_html__( 'Table Border', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-ft',
        ) );

        $this->add_control( 'table_border_radius', array(
            'label'      => esc_html__( 'Border Radius', 'product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pc-ft-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'table_box_shadow',
            'label'    => esc_html__( 'Box Shadow', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-ft-wrapper',
        ) );

        $this->end_controls_section();
    }

    /**
     * Register a style section for a single column (typography, color, alignment).
     */
    private function register_column_style_section( $key, $col ) {
        $class = $col['class'];

        $this->start_controls_section( 'section_style_col_' . $key, array(
            'label' => sprintf( esc_html__( 'Column: %s', 'product-costings' ), $col['label'] ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'col_' . $key . '_typography',
            'label'    => esc_html__( 'Typography', 'product-costings' ),
            'selector' => '{{WRAPPER}} .pc-ft td.' . $class,
        ) );

        $this->add_control( 'col_' . $key . '_color', array(
            'label'     => esc_html__( 'Text Color', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pc-ft td.' . $class => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'col_' . $key . '_align', array(
            'label'     => esc_html__( 'Alignment', 'product-costings' ),
            'type'      => \Elementor\Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => esc_html__( 'Left', 'product-costings' ),   'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => esc_html__( 'Center', 'product-costings' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => esc_html__( 'Right', 'product-costings' ),  'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => $col['default_align'],
            'selectors' => array(
                '{{WRAPPER}} .pc-ft td.' . $class     => 'text-align: {{VALUE}};',
                '{{WRAPPER}} .pc-ft th.pc-ft-col-' . $key => 'text-align: {{VALUE}};',
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

        if ( ! $product_id || 'products' !== get_post_type( $product_id ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'Formula Ingredients Table — please view on a Products post or enter a Product ID.', 'product-costings' ) . '</p>';
            }
            return;
        }

        $rows = get_post_meta( $product_id, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) || empty( $rows ) ) {
            if ( $settings['empty_message'] ) {
                echo '<p class="pc-ft-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            }
            return;
        }

        $batch_size_raw = PC_Costing_Calculator::get_product_meta_value( $product_id, 'batch_size' );
        $waste_pct      = isset( $settings['waste_percent'] ) ? floatval( $settings['waste_percent'] ) : 2;
        $batch_size     = $batch_size_raw * ( 1 + $waste_pct / 100 );
        $currency       = $settings['currency_symbol'];

        // Sort rows by phase letter, preserving manual order within each phase.
        $rows = $this->sort_by_phase( $rows );

        ?>
        <div class="pc-ft-wrapper">
            <table class="pc-ft">
                <thead>
                    <tr>
                        <th class="pc-ft-col-phase"><?php esc_html_e( 'Phase', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-ww"><?php esc_html_e( '%w/w', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-trade"><?php esc_html_e( 'Trade Name', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-function"><?php esc_html_e( 'Function', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-ph"><?php esc_html_e( 'pH range', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-cost"><?php esc_html_e( 'Cost/Kg', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-moq"><?php esc_html_e( 'MOQ', 'product-costings' ); ?></th>
                        <th class="pc-ft-col-kgbatch"><?php esc_html_e( 'Kg per batch', 'product-costings' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_ww = 0; ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $phase     = isset( $row['phase'] ) ? $row['phase'] : '';
                        $ww        = isset( $row['percent_w_w'] ) ? floatval( $row['percent_w_w'] ) : 0;
                        $total_ww += $ww;
                        $trade_id  = isset( $row['trade_name_id'] ) ? (int) $row['trade_name_id'] : 0;
                        $fn        = isset( $row['function'] ) ? $row['function'] : '';
                        $ph        = isset( $row['ph'] ) ? $row['ph'] : ( isset( $row['ph_range'] ) ? $row['ph_range'] : '' );
                        $price     = isset( $row['price_per_kg'] ) ? $row['price_per_kg'] : '';
                        $moq       = isset( $row['moq'] ) ? $row['moq'] : '';

                        // Prefer the minimum quantity from the bulk pricing table
                        // (the real minimum order) over the old MOQ snapshot.
                        if ( $trade_id ) {
                            $eff_moq = PC_Trade_Data::get_effective_moq( $trade_id );
                            if ( null !== $eff_moq ) {
                                $moq = $eff_moq;
                            }
                        }

                        $trade_name = $trade_id ? get_the_title( $trade_id ) : '';
                        $kg_batch   = $batch_size > 0 ? ( $ww / 100 ) * $batch_size : 0;

                        $price_num = is_numeric( $price ) ? floatval( $price ) : 0;

                        // Format MOQ with kg suffix.
                        $moq_display = '';
                        if ( '' !== $moq && is_numeric( $moq ) ) {
                            $moq_num     = floatval( $moq );
                            $moq_display = ( floor( $moq_num ) == $moq_num )
                                ? number_format( $moq_num, 0 ) . ' kg'
                                : rtrim( rtrim( number_format( $moq_num, 2 ), '0' ), '.' ) . ' kg';
                        } elseif ( '' !== $moq ) {
                            // Already contains text like "1 kg".
                            $moq_display = $moq;
                        }
                        ?>
                        <tr<?php echo $phase ? ' data-phase="' . esc_attr( strtoupper( $phase ) ) . '"' : ''; ?>>
                            <td class="pc-ft-phase"><?php echo esc_html( $phase ); ?></td>
                            <td class="pc-ft-ww"><?php echo $ww > 0 ? esc_html( $ww . '%' ) : ''; ?></td>
                            <td class="pc-ft-trade"><?php echo esc_html( $trade_name ); ?></td>
                            <td class="pc-ft-function"><?php echo esc_html( $fn ); ?></td>
                            <td class="pc-ft-ph"><?php echo esc_html( $ph ); ?></td>
                            <td class="pc-ft-cost"><?php echo $price_num > 0 ? esc_html( $currency . number_format( $price_num, 2 ) ) : ''; ?></td>
                            <td class="pc-ft-moq"><?php echo esc_html( $moq_display ); ?></td>
                            <td class="pc-ft-kgbatch"><?php echo $kg_batch > 0 ? esc_html( number_format( $kg_batch, 2 ) . ' kg' ) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="pc-ft-phase"></td>
                        <td class="pc-ft-ww pc-ft-total"><strong><?php echo esc_html( number_format( $total_ww, 2 ) . '%' ); ?></strong></td>
                        <td class="pc-ft-trade" colspan="6"><strong><?php esc_html_e( 'Total %w/w', 'product-costings' ); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    /**
     * Sort rows by phase letter (A, B, C …) while preserving the manual
     * ordering within each phase group.
     */
    private function sort_by_phase( $rows ) {
        foreach ( $rows as $idx => &$row ) {
            $row['_orig_idx'] = $idx;
        }
        unset( $row );

        usort( $rows, function ( $a, $b ) {
            $pa = strtoupper( isset( $a['phase'] ) ? $a['phase'] : '' );
            $pb = strtoupper( isset( $b['phase'] ) ? $b['phase'] : '' );

            if ( $pa === $pb ) {
                return $a['_orig_idx'] - $b['_orig_idx'];
            }

            if ( '' === $pa ) return 1;
            if ( '' === $pb ) return -1;

            return strcmp( $pa, $pb );
        } );

        foreach ( $rows as &$row ) {
            unset( $row['_orig_idx'] );
        }

        return $rows;
    }

}
