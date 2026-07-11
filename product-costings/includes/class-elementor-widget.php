<?php
/**
 * Elementor widget: Formula Ingredients Table.
 *
 * Displays the formula ingredients saved on a Products CPT post as a
 * styled front-end table, sorted by Phase with manual order preserved
 * within each phase group.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the widget with Elementor once its autoloader is ready.
 */
function pc_register_elementor_widget( $widgets_manager ) {
    require_once __DIR__ . '/widget-formula-table.php';
    require_once __DIR__ . '/widget-batch-costings.php';
    $widgets_manager->register( new \PC_Widget_Formula_Table() );
    $widgets_manager->register( new \PC_Widget_Batch_Costings() );
}
add_action( 'elementor/widgets/register', 'pc_register_elementor_widget' );
