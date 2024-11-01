<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file Approve zinrelo transaction on order
 *
 * @package zinrelo\approvereward
 * @version 1.0.1
 */

/**
 * Approve a Zinrelo transaction if a user places an order.
 *
 * @param mixed $order_id order_id.
 */
function zinrelo_approve_zinrelo_transaction_on_order( $order_id ) {
	try {
		$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
		// Store the custom coupon code as order meta.
		if ( WC()->session->get( 'reward_sub_type' ) === 'Product Redemption' ) {
			update_post_meta( $order_id, 'zinrelo_product_redemption', WC()->session->get( 'reward_name' ) );
		}
		if ( WC()->session->get( 'reward_sub_type' ) === 'Product Redemption' || WC()->session->get( 'reward_sub_type' ) === 'Free Shipping' ) {
			$order = wc_get_order( $order_id );
			$order->add_coupon( WC()->session->get( 'zinrelo_coupon_code' ), ( 0 ) );
		}
		if ( ! empty( $transaction_id ) ) {
			zinrelo_perform_zinrelo_transaction_action( $transaction_id, 'approve' );
			zinrelo_delete_by_transaction_id( $transaction_id );
			zinrelo_clear_cart_session_data();
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_new_order', 'zinrelo_approve_zinrelo_transaction_on_order', 10, 1 );
