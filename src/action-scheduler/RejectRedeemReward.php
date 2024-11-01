<?php
/**
 * This file is reject redeem reward
 *
 * @package zinrelo\rejectredeemreward
 * @version 1.0.1
 */
if ( ! defined( 'ABSPATH' ) ) exit; 
const ZINRELO_TYPE = 'Y-m-d H:i:s';
/**
 * Update status
 */
function zinrelo_update_status() {
	try {
		global $wpdb;
		$current_datetime = current_time( 'Y-m-d H:i:s' );
		$cart_records     = $wpdb->query($wpdb->prepare("SELECT id, zinrelo_transaction_id FROM $wpdb->prefix.'zinrelo_cart' WHERE cart_timeout < %s AND cron_status = %s",$current_datetime,0));// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		if ( $cart_records ) {
			foreach ( $cart_records as $cart_record ) {
				$transaction_id = $cart_record->zinrelo_transaction_id;
				zinrelo_reject_zinrelo_transaction( $transaction_id );
				$updated_rows = $wpdb->query($wpdb->prepare("UPDATE $wpdb->prefix.'zinrelo_cart' SET cron_status = '1' WHERE zinrelo_transaction_id = %s AND cart_timeout < %s AND cron_status = '0'",$transaction_id,$current_datetime));// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
				if ( false === $updated_rows ) {
					zinrelo_logger( 'Error updating table: ' . $wpdb->last_error );
				} else {
					zinrelo_logger( "$updated_rows rows updated successfully." );
				}
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Call a function when the abandoned cart crontab occurs.
 */
function zinrelo_handle_abandoned_cart_timeout_using_cron() {
	try {
		if ( isset( WC()->session ) ) {
			$cart_session_time = WC()->session->get( 'cart_session_timeout' );
			if ( gmdate( ZINRELO_TYPE ) > $cart_session_time ) {
				$product_id     = WC()->session->get( 'product_id' );
				$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
				if ( null !== $transaction_id ) {
					zinrelo_reject_zinrelo_transaction( $transaction_id );
				}
				if ( null !== $product_id && 'Product Redemption' === WC()->session->get( 'reward_sub_type' ) ) {
					zinrelo_remove_cart_item( $product_id );
				}
				if ( null !== WC()->session->get( 'zinrelo_coupon_code' ) ) {
					WC()->cart->remove_coupon( WC()->session->get( 'zinrelo_coupon_code' ) );
				}
				zinrelo_clear_cart_session_data();
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'template_redirect', 'zinrelo_handle_abandoned_cart_timeout_using_cron' );

/**
 * Call a function when the abandoned cart timeout occurs.
 */
function zinrelo_handle_abandoned_cart_timeout() {
	try {
		if ( ( zinrelo_field_value( 'auto_rejection' ) === 'yes' ) && isset( WC()->session ) ) {
			$cart_session_time = WC()->session->get( 'cart_session_timeout' );
			if ( gmdate( ZINRELO_TYPE ) > $cart_session_time ) {
				$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
				if ( null !== $transaction_id ) {
					zinrelo_reject_zinrelo_transaction( $transaction_id );
					$product_id = WC()->session->get( 'product_id' );
					zinrelo_remove_cart_item( $product_id );
					zinrelo_clear_cart_session_data();
					if ( null !== WC()->session->get( 'zinrelo_coupon_code' ) ) {
						WC()->cart->remove_coupon( WC()->session->get( 'zinrelo_coupon_code' ) );
					}
				}
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'template_redirect', 'zinrelo_handle_abandoned_cart_timeout' );

/**
 * Remove Cart Item
 *
 * @param mixed $product_id product_id.
 */
function zinrelo_remove_cart_item( $product_id ) {
	if ( null !== $product_id && 'Product Redemption' === WC()->session->get( 'reward_sub_type' ) ) {
		zinrelo_remove_free_product( $product_id );
	}
}
