<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file is product page block
 *
 * @package zinrelo\productpageblock
 * @version 1.0.1
 */

/**
 * Adds custom HTML to the product detail page.
 *
 * @return void
 */
function zinrelo_html_to_product_detail_page(): void {
	try {
		if ( ( zinrelo_field_value( 'enabled' ) === 'yes' ) && is_product() ) {
			global $product;
			$product_id    = $product->get_id();
			$product_price = $product->get_price();
			$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			$product_data       = array(
				'productId'         => $product_id,
				'productPrice'      => $product_price,
				'productCategories' => $product_categories,
			);
			$script_url = plugins_url( '/assets/js/zinrelo-product-point.js', dirname(__FILE__) );
			wp_register_script( 'zinrelo-js', $script_url, array(), '1.0.0', true );
			wp_localize_script( 'zinrelo-js', 'productData', $product_data );
			wp_enqueue_script( 'zinrelo-js' );
			$inline_script = "
            jQuery(function ($) {
                var isSetPoint = false;
                var pointValue = 0;
                $('[name=quantity]').change(function () {
                    if (!(this.value < 1)) {
                        if (!isSetPoint) {
                            pointValue = $('.zinrelo-product-price').text();
                            isSetPoint = true;
                        }
                        var product_total = parseFloat(pointValue * this.value);
                        $('.zinrelo-product-price').text(product_total);
                    }
                });
            });
        ";
        wp_add_inline_script( 'zinrelo-js', $inline_script );
			?>
			<?php
			$base = __DIR__;
			$path = dirname( dirname( $base ) );
			require_once $path . '/zinrelo/templates/frontend/ProductPages.php';
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_after_add_to_cart_button', 'zinrelo_html_to_product_detail_page' );
