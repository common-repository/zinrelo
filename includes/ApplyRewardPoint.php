<?php
/**
 * This file is applied redeem reward
 *
 * @package zinrelo\appliedredeemreward
 * @version 1.0.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Applied Redeem Reward
 *
 * @param mixed $current_date_time currentDateTime.
 * @param mixed $reward_id reward_id.
 */
function zinrelo_applied_reward( $current_date_time, $reward_id ) {
	try {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
			if ( isset( $_POST['action_type'] ) && isset( $_POST['reward_id'] ) ) {
				$reward_id_sanitised = sanitize_text_field( wp_unslash( $_POST['reward_id'] ) ) ?? '';
				$action_type         = sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) ?? '';
				if ( '' !== $reward_id_sanitised && 'redeem' === $action_type ) {
					$sanitized_data = array(
			            'zinrelo_reward_value' => isset( $_POST['zinrelo_reward_value'] ) ? sanitize_text_field( wp_unslash($_POST['zinrelo_reward_value']) ) : '',
			            'reward_sub_type'      => isset( $_POST['reward_sub_type'] ) ? sanitize_text_field( wp_unslash($_POST['reward_sub_type']) ) : '',
			            'product_id'           => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
			            'reward_name'          => isset( $_POST['reward_name'] ) ? sanitize_text_field( wp_unslash($_POST['reward_name']) ) : '',
			            'reward_id'            => isset( $_POST['reward_id'] ) ? sanitize_text_field( wp_unslash($_POST['reward_id']) ) : '',
			        );
					zinrelo_set_session_data( $sanitized_data, $current_date_time );
					zinrelo_redeem_transaction( $reward_id );
				}
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Set session data
 *
 * @param mixed $data data.
 * @param mixed $current_date_time currentDateTime.
 */
function zinrelo_set_session_data( $data, $current_date_time ) {
	try {
		WC()->session->set( 'zinrelo_reward_value', $data['zinrelo_reward_value'] );
		WC()->session->set( 'reward_sub_type', $data['reward_sub_type'] );
		WC()->session->set( 'product_id', $data['product_id'] );
		WC()->session->set( 'reward_name', $data['reward_name'] );
		WC()->session->set( 'reward_id', $data['reward_id'] );
		WC()->session->set( 'applied', 'yes' );
		WC()->session->set( 'cart_session_timeout', $current_date_time->format( 'Y-m-d H:i:s' ) );
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Redeem a transaction by making a request to the Zinrelo API.
 *
 * @param string $reward_id The ID of the reward to redeem.
 * @return void
 */
function zinrelo_redeem_transaction( $reward_id ): void {
	try {
		$api_url        = ZINRELO_LIVE_API_URL . '/transactions/redeem';
		$current_user   = wp_get_current_user();
		$customer_email = $current_user->user_email;
		$data           = array(
			'member_id'              => $customer_email,
			'reward_id'              => $reward_id,
			'transaction_attributes' => array(
				'reason' => 'redeem',
			),
			'status'                 => 'pending',
		);
		$response       = zinrelo_request( $api_url, $data, 'post', 'live_api' );
		zinrelo_redeem_reward( $response );
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Redeem Reward
 *
 * @param mixed $response response.
 */
function zinrelo_redeem_reward( $response ) {
	try {
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$response_body    = wp_remote_retrieve_body( $response );
			$decoded_response = json_decode( $response_body, true );
			if ( isset( $decoded_response['error_code'] ) && $decoded_response['error_code'] ) {
				WC()->session->set( 'zinrelo_reward_rules', null );
				zinrelo_clear_cart_session_data();
				wc_add_notice( __( 'Invalid coupon code.', 'zinrelo' ), 'zinrelo' );
			} else {
				WC()->session->set( 'zinrelo_transaction_id', $decoded_response['data']['id'] );
				$coupon_code = ! empty( $decoded_response['data']['reward_info']['coupon_code'] ) ? $decoded_response['data']['reward_info']['coupon_code'] : null;
				WC()->session->set( 'zinrelo_coupon_code', $coupon_code );
				if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
					if ( isset( $_POST['reward_sub_type'] ) && 'Product Redemption' === sanitize_text_field( wp_unslash( $_POST['reward_sub_type'] ) ) ) {
						$free_product = zinrelo_add_to_cart_free_product();
						if ( ! $free_product ) {
							$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
							zinrelo_reject_zinrelo_transaction( $transaction_id );
							zinrelo_clear_cart_session_data();
							wc_add_notice( __( 'Product that you are trying to add is not available.', 'zinrelo' ), 'zinrelo' );
						} else {
							wc_add_notice( __( 'Reward applied successfully.', 'zirelo' ) );
						}
					} else {
						$coupon_code = WC()->session->get( 'zinrelo_coupon_code' );
						$coupon_data = new WC_Coupon( $coupon_code );
						if ( $coupon_data->id && $coupon_data->usage_limit !== $coupon_data->usage_count ) {
							wc_add_notice( __( 'Reward applied successfully.', 'zirelo' ) );
						}
					}
					if ( 'Free Shipping' === $_POST['reward_sub_type'] ) {
						wc_add_notice( __( 'Reward applied successfully.', 'zinrelo' ) );
					}

					$current_user      = wp_get_current_user();
					$current_date_time = new DateTime();
					$admin_config      = intval( zinrelo_field_value( 'cart_session_timeout' ) );
					$current_date_time->modify( "+$admin_config minutes" );
					zinrelo_insert_zinrelo_cart_data(
						$current_user->ID,
						$decoded_response['data']['id'],
						0,
						$current_date_time->format( 'Y-m-d H:i:s' )
					);
					zinrelo_reward_discount();
					wp_send_json_success( __( 'Reward redeemed successfully!', 'zinrelo' ), 200 );
				} else {
					zinrelo_logger( 'Request failed' );
					wp_send_json_error( 'Error: Request failed', 500 );
				}
			}
		} else {
			zinrelo_logger( 'Request failed' );
			wp_send_json_error( 'Error: Request failed', 500 );
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Zinrelo partially Refunded
 *
 * @param mixed $order_id order_id.
 */
function zinrelo_partially_refunded( $order_id ) {
	zinrelo_update_coupon_code_usage_limit( $order_id );
}

/**
 * Zinrelo Fully Refunded
 *
 * @param mixed $order_id order_id.
 */
function zinrelo_fully_refunded( $order_id ) {
	zinrelo_update_coupon_code_usage_limit( $order_id );
}

/**
 * Update Coupon Code Usage Limit
 *
 * @param mixed $order_id order_id.
 */
function zinrelo_update_coupon_code_usage_limit( $order_id ) {
	$order   = new WC_Order( $order_id );
	$coupons = $order->get_coupon_codes();
	foreach ( $coupons as $coupon ) {
		$coupon_post_object = new WP_Query( $coupon, OBJECT, 'shop_coupon' );
		$coupon_id          = $coupon_post_object->ID;
		if ( $coupon_id ) {
			$coupon_object      = new WC_Coupon( $coupon_id );
			$usage_count        = $coupon_object->usage_count;
			if ( $coupon_object->usage_limit >= $coupon_object->usage_count ) {
				$usage_count = $coupon_object->usage_limit;
			} else {
				$usage_count = $coupon_object->usage_count - 1;
			}
			$coupon_object->set_usage_count( $usage_count );
			$coupon_object->save();
		}
	}
}

add_action( 'woocommerce_order_status_refunded', 'zinrelo_fully_refunded' );
add_action( 'woocommerce_order_partially_refunded', 'zinrelo_partially_refunded', 10, 2 );
add_action( 'woocommerce_order_status_cancelled', 'zinrelo_fully_refunded' );
add_action( 'woocommerce_order_status_completed', 'zinrelo_fully_refunded' );
