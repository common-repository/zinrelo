<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file is for product pages
 *
 * @package zinrelo\productpages
 * @version 1.0.1
 */

/**
 * Enqueue custom scripts and add inline JavaScript.
 *
 * @return void
 */
function zinrelo_scripts_and_add_inline_js() {
    if ( zinrelo_field_value( 'enable_product_page' ) === 'yes' ) {
        if ( ! is_product() ) {
            return;
        }
        
        // Prepare the reward label
        $reward_text_product_page = zinrelo_field_value( 'reward_text_product_page' );
        $reward_label = $reward_text_product_page;
        
        if ( strpos( $reward_label, '{{EARN_POINTS}}' ) !== false ) {
            $reward_label = str_replace( '{{EARN_POINTS}}', "<div class='zinrelo-product-price' data-zrl-product-points></div>", $reward_label );
        } else {
            $reward_label = "<div class='zinrelo-product-price' data-zrl-product-points></div>" . $reward_label;
        }

        // Register and enqueue the main JavaScript file
        wp_register_script( 'zinrelo-js', plugins_url( '/assets/js/zinrelo-product-point.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
        wp_enqueue_script( 'zinrelo-js' );

        // Escape the reward label for JavaScript
        $reward_label_escaped = wp_json_encode( $reward_label );

        // Prepare and add the inline script
        $inline_script = "
            jQuery(document).ready(function ($) {
                $('.qty').prop('disabled', true);
                $('.cart').append('<div class=\"zinrelo-earn-product-points\">' + 
                    {$reward_label_escaped} + 
                    '</div>');

                $('.zinrelo-product-price').on('DOMSubtreeModified', function () {
                    $('.qty').prop('disabled', false);
                });
            });
        ";
        wp_add_inline_script( 'zinrelo-js', $inline_script );
    }
}

add_action( 'wp_enqueue_scripts', 'zinrelo_scripts_and_add_inline_js' );
