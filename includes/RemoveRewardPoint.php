<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * The js for displaying cart page drop down apply remove button
 *
 * @package zinrelo\js
 * @version 1.0.1
 */

/**
 * Zinrelo Remove reward
 */
function zinrelo_remove_reward() {
	try {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
			if ( isset( $_POST['action_type'] ) && 'cancel' === sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) ) {
				if ( WC()->session->get( 'zinrelo_transaction_id' ) !== null ) {
					zinrelo_reject_zinrelo_transaction( WC()->session->get( 'zinrelo_transaction_id' ) );
				}
				$reward_id   = ( isset( $_POST['reward_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['reward_id'] ) ) : '';
				$reward_rule = WC()->session->get( 'zinrelo_reward_rules' );
				$product_id  = isset( $reward_rule[ $reward_id ]['product_id'] ) ? $reward_rule[ $reward_id ]['product_id'] : 0;
				if ( isset( $reward_rule[ $reward_id ]['product_id'] ) !== null && WC()->session->get( 'reward_sub_type' ) === 'Product Redemption' ) {
					zinrelo_remove_free_product( $product_id );
				}
				if ( WC()->session->get( 'zinrelo_coupon_code' ) !== null ) {
					WC()->cart->remove_coupon( WC()->session->get( 'zinrelo_coupon_code' ) );
				}
				zinrelo_clear_cart_session_data();
				wc_add_notice( __( 'Reward removed successfully.', 'zinrelo' ) );
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Reject a Zinrelo transaction.
 *
 * @param string $transaction_id The ID of the transaction to reject.
 *
 * @return array|null Response from the API or an error message.
 */
function zinrelo_reject_zinrelo_transaction( $transaction_id ): ?array {
	zinrelo_delete_coupon_code_from_woo_commerce( $transaction_id );
	zinrelo_delete_by_transaction_id( $transaction_id );
	return zinrelo_perform_zinrelo_transaction_action( $transaction_id, 'reject' );
}

/**
 * Delete coupon code from woo commerce
 *
 * @param mixed $transaction_id transaction_id.
 */
function zinrelo_delete_coupon_code_from_woo_commerce( $transaction_id ) {
	if ( $transaction_id ) {
		$coupon_code = WC()->session->get( 'zinrelo_coupon_code' );
		$coupon_data = new WC_Coupon( $coupon_code );
		if ( ! empty( $coupon_data->id ) ) {
			wp_delete_post( $coupon_data->id );
		}
	}
}

/**
 * Perform a Zinrelo transaction action.
 *
 * @param string $transaction_id The ID of the transaction.
 * @param string $action The action to perform (e.g., 'approve', 'reject').
 * @return array|null Response from the API or an error message.
 */
function zinrelo_perform_zinrelo_transaction_action( $transaction_id, string $action ): ?array {
	$base_url = ZINRELO_LIVE_API_URL . '/transactions/';
	$url      = $base_url . $transaction_id . '/' . $action;
	$response = zinrelo_request( $url, '', 'post' );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		return "Something went wrong: $error_message";
	} else {
		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}

/**
 * Remove Free Product
 *
 * @param mixed $product_id product_id.
 */
function zinrelo_remove_free_product( $product_id ) {
	try {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( (int) $cart_item['product_id'] === (int) $product_id &&
				isset( $cart_item['free_product_for_zinrelo_discount'] ) &&
				0 === $cart_item['free_product_for_zinrelo_discount'] ) {
				WC()->cart->remove_cart_item( $cart_item_key );
				WC()->cart->calculate_totals();
				break; // Stop the loop once the item is removed.
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Manage Cart Remove Item
 *
 * @param mixed $cart_item_key cart_item_key.
 * @param mixed $cart cart.
 */
function zinrelo_manage_cart_remove_item( $cart_item_key, $cart ) {
	try {
		$item_count = count( WC()->cart->get_cart() );
		$product_id = $cart->cart_contents[ $cart_item_key ]['product_id'];
		if ( ( (int) WC()->session->get( 'product_id' ) !== (int) $product_id ) && $item_count <= 2 &&
			'Product Redemption' === WC()->session->get( 'reward_sub_type' ) ) {
			WC()->cart->empty_cart();
			zinrelo_reject_zinrelo_transaction( WC()->session->get( 'zinrelo_transaction_id' ) );
			zinrelo_clear_cart_session_data();
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_remove_cart_item', 'zinrelo_manage_cart_remove_item', 10, 2 );

/**
 * Logout after reject transactions
 */
function zinrelo_logout_after_reject_transactions() {
	if ( WC()->session->get( 'zinrelo_transaction_id' ) !== null ) {
		$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
		zinrelo_reject_zinrelo_transaction( $transaction_id );
	}
}

add_action( 'wp_logout', 'zinrelo_logout_after_reject_transactions' );
