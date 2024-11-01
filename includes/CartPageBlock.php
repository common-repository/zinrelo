<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * This file is cart page block
 *
 * @package zinrelo\approvereward
 * @version 1.0.1
 */

/**
 * Renders the custom cart HTML.
 *
 * @return void
 */
function zinrelo_custom_cart_html(): void {
	try {
		$base = __DIR__;
		$path = dirname( dirname( $base ) );
		if ( zinrelo_field_value( 'enabled' ) === 'yes' ) {
			require_once $path . '/zinrelo/templates/frontend/CartPage.php';
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_before_cart_collaterals', 'zinrelo_custom_cart_html' );


/**
 * Get reward point rules
 *
 * @return array
 */
function zinrelo_redeem_rules() {
	$current_user   = wp_get_current_user();
	$customer_email = $current_user->user_email;
	$api_url        = ZINRELO_LIVE_API_URL . '/members/' . rawurlencode( $customer_email ) . '/rewards?idParam=member_id';
	$api_key        = zinrelo_field_value( 'zinrelo_api_key' );
	$partner_id     = zinrelo_field_value( 'partner_id' );
	$args           = array(
		'headers' => array(
			'Accept'     => 'application/json',
			'Api-Key'    => $api_key,
			'Partner-Id' => $partner_id,
		),
	);
	$response       = wp_remote_get( $api_url, $args );
	if ( is_wp_error( $response ) ) {
		return array();
	} else {
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );
		zinrelo_success_rewards_rule_data( $result );
	}
}

/**
 * Success rewards rule data || This function is called in the zinrelo_redeem_rules function
 *
 * @param mixed $result result.
 * @return array
 */
function zinrelo_success_rewards_rule_data( $result ) {
	try {
		if ( isset( $result['success'] ) && $result['success'] && ! empty( $result['data']['rewards'] ) ) {
			$rules = $result['data']['rewards'];
			zinrelo_reward_rule( $rules );
		} else {
			return array();
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Reward Rule
 *
 * @param mixed $rules rules.
 * @return void
 */
function zinrelo_reward_rule( $rules ) {
	try {
		$reward_rules = array();
		$reward_types = zinrelo_default_reward_types();
		foreach ( $rules as $rule ) {
			$reward_rules[ $rule['reward_id'] ] = zinrelo_reward_sub_type_rule( $rule, $reward_types );
		}
		WC()->session->set( 'zinrelo_reward_rules', $reward_rules );
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Set Reward Sub Type Rule
 *
 * @param mixed $rule rule.
 * @param mixed $reward_types reward_types.
 * @return array
 */
function zinrelo_reward_sub_type_rule( $rule, $reward_types ) {
	try {
		if ( in_array( $rule['reward_sub_type'], array_values( $reward_types ), true ) ) {
			$reward_rules = array();
			foreach ( $reward_types as $key => $value ) {
				if ( $rule['reward_sub_type'] === $value ) {
					$reward_rules = array(
						'rule'                => $key,
						'reward_sub_type'     => $rule['reward_sub_type'],
						'reward_id'           => $rule['reward_id'],
						'id'                  => $rule['id'],
						'implementation_type' => $rule['implementation_type'],
						'reward_name'         => $rule['reward_name'],
						'reward_value'        => ! empty( $rule['reward_value'] ) ? $rule['reward_value'] : '',
						'product_id'          => ! empty( $rule['product_id'] ) ? $rule['product_id'] : '',
					);
				}
			}
			return $reward_rules;
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}


/**
 * Get default reward types for redeeming
 *
 * @return array
 */
function zinrelo_default_reward_types(): array {
	return array(
		'product_redemption'    => esc_html__( 'Product Redemption', 'zinrelo' ),
		'fixed_amount_discount' => esc_html__( 'Fixed Amount Discount', 'zinrelo' ),
		'percentage_discount'   => esc_html__( 'Percentage Discount', 'zinrelo' ),
		'free_shipping'         => esc_html__( 'Free Shipping', 'zinrelo' ),
	);
}

/**
 * Get available reward points
 *
 * @return mixed|string|void
 */
function zinrelo_reward_points() {
	try {
		$current_user   = wp_get_current_user();
		$customer_email = $current_user->user_email;
		$api_url        = ZINRELO_LIVE_API_URL . '/members/' . rawurlencode( $customer_email ) . '?idParam=member_id';
		$api_key        = zinrelo_field_value( 'zinrelo_api_key' );
		$partner_id     = zinrelo_field_value( 'partner_id' );
		$args           = array(
			'headers' => array(
				'accept'     => 'application/json',
				'api-key'    => $api_key,
				'partner-id' => $partner_id,
			),
		);
		$response       = wp_remote_get( $api_url, $args );
		$body           = wp_remote_retrieve_body( $response );
		$result         = json_decode( $body, true );
		if ( isset( $result['success'] ) && $result['success'] ) {
			$points = $result['data']['available_points'];
			return $points > 0 ? $points : 'error';
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Zinrelo Free Product Set Price Zero
 *
 * @param mixed $cart_object cart_object.
 */
function zinrelo_set_free_product_price( $cart_object ) {
	try {
		foreach ( $cart_object->get_cart() as $item ) {
			if ( array_key_exists( 'free_product_for_zinrelo_discount', $item ) ) {
				$item['data']->set_price( $item['free_product_for_zinrelo_discount'] );
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_before_calculate_totals', 'zinrelo_set_free_product_price' );


/**
 * Zinrelo Disable Quantity Input Field
 *
 * @param mixed $args args.
 * @param mixed $product product.
 * @return mixed
 */
function zinrelo_disable_cart_quantity_input_field( $args, $product ) {
	try {
		if ( ! $product->get_price() ) {
			$input_value       = $args['input_value'];
			$args['min_value'] = $input_value;
			$args['max_value'] = $input_value;
		}
		return $args;
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_filter( 'woocommerce_quantity_input_args', 'zinrelo_disable_cart_quantity_input_field', 20, 2 );


/**
 * Zinrelo Remove Item Button From Cart
 *
 * @param mixed $link link.
 * @param mixed $cart_item_key cart_item_key.
 * @return string
 */
function zinrelo_remove_item_button_from_cart( $link, $cart_item_key ) {
	try {
		if ( WC()->cart->find_product_in_cart( $cart_item_key ) ) {
			$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
			if ( array_key_exists( 'free_product_for_zinrelo_discount', $cart_item ) ) {
				$link = '';
			}
		}
		return $link;
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Remove product button from cart page for free product
 */
add_filter( 'woocommerce_cart_item_remove_link', 'zinrelo_remove_item_button_from_cart', 10, 2 );

/**
 * Redeem a reward by initiating the Zinrelo transaction.
 */
function zinrelo_redeem_reward_callback(): void {
	try {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
			if ( ! empty( $_POST['reward_id'] ) && ! empty( $_POST['action'] ) ) {
				$reward_id         = sanitize_text_field( wp_unslash( $_POST['reward_id'] ) ) ?? '';
				$current_date_time = new DateTime();
				$admin_config      = intval( zinrelo_field_value( 'cart_session_timeout' ) );
				$current_date_time->modify( "+$admin_config minutes" );
				zinrelo_remove_reward();
				zinrelo_applied_reward( $current_date_time, $reward_id );
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'wp_ajax_redeem_reward', 'zinrelo_redeem_reward_callback' );
add_action( 'wp_ajax_nopriv_redeem_reward', 'zinrelo_redeem_reward_callback' );

/**
 * Filter woocommerce cart totals coupon html
 *
 * @param mixed $coupon_html coupon_html.
 * @param mixed $coupon coupon.
 * @return mixed
 */
function zinrelo_filter_woocommerce_cart_totals_coupon_html( $coupon_html, $coupon ) {

	if ( strtolower( WC()->session->get( 'zinrelo_coupon_code' ) ) === $coupon->get_code() ) {
		$coupon_html = str_replace( '[Remove]', '', $coupon_html );
	}
	return $coupon_html;
}

add_filter( 'woocommerce_cart_totals_coupon_html', 'zinrelo_filter_woocommerce_cart_totals_coupon_html', 10, 3 );
