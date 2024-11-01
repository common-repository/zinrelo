<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file is reward discount
 *
 * @package zinrelo\rewarddiscount
 * @version 1.0.1
 */

/**
 * Add to cart free product
 *
 * @return bool
 */
function zinrelo_add_to_cart_free_product() {
	try {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
			$reward_id = ( isset( $_POST['reward_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['reward_id'] ) ) : '';
			if ( ( 'Product Redemption' === WC()->session->get( 'reward_sub_type' ) ) && '' !== $reward_id
				&& WC()->session->get( 'zinrelo_transaction_id' ) !== null ) {
				$reward_rule = WC()->session->get( 'zinrelo_reward_rules' );
				$product_id  = $reward_rule[ $reward_id ]['product_id'];
				$product     = wc_get_product( $product_id );
				if ( $product ) {
					if ( null !== $product_id ) {
						WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'free_product_for_zinrelo_discount' => 0 ) );
					}
				} else {
					return false;
				}
				return true;
			}
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Conditional Shipping Discount
 */
function zinrelo_free_shipping_discount() {
	try {
		$reward_sub_type = WC()->session->get( 'reward_sub_type' );
		if ( ( WC()->session->get( 'reward_sub_type' ) !== null )
			&& ( WC()->session->get( 'zinrelo_transaction_id' ) !== null )
			&& 'Free Shipping' === $reward_sub_type ) {
			$current_shipping_method_cost = WC()->cart->get_shipping_total();
			WC()->cart->add_fee( get_option( 'zinrelo_free_shipping_reward_label' ), -1 * abs( $current_shipping_method_cost ) );
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Apply free shipping programmatically based on a reward sub type.
 *
 * @return void
 */
add_action( 'woocommerce_cart_calculate_fees', 'zinrelo_free_shipping_discount' );


/**
 * Zinrelo Fix Percentage Reward Discount
 */
function zinrelo_reward_discount() {
	try {
		switch ( WC()->session->get( 'reward_sub_type' ) ) {
			case 'Percentage Discount':
				if ( ! WC()->cart->has_discount( WC()->session->get( 'zinrelo_coupon_code' ) ) ) {
					WC()->cart->apply_coupon( WC()->session->get( 'zinrelo_coupon_code' ) );
				}
				break;
			case 'Fixed Amount Discount':
				if ( ! WC()->cart->has_discount( WC()->session->get( 'zinrelo_coupon_code' ) ) ) {
					WC()->cart->apply_coupon( WC()->session->get( 'zinrelo_coupon_code' ) );
				}
				break;
		}
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

add_action( 'woocommerce_cart_calculate_fees', 'zinrelo_reward_discount' );


/**
 * Change the coupon label.
 *
 * @param mixed $label label.
 * @param mixed $coupon coupon.
 * @return string
 */
function zinrelo_reward_coupon_label( $label, $coupon ) {
	if ( strtolower( WC()->session->get( 'zinrelo_coupon_code' ) ) === $coupon->get_code() ) {
		$label = 'Reward : ' . WC()->session->get( 'reward_name' );
	}
	return $label;
}

add_filter( 'woocommerce_cart_totals_coupon_label', 'zinrelo_reward_coupon_label', 10, 2 );

/**
 * Add row cart summery for free product
 */
function zinrelo_row_cart_summery_for_free_product() {
	if ( WC()->session->get( 'reward_sub_type' ) === 'Product Redemption' ) {
		?>
		<tr>
			<th><?php echo 'Reward : ' . esc_html( WC()->session->get( 'reward_name' ) ); ?></th>
			<td><?php echo esc_html( get_woocommerce_currency_symbol( get_option( 'woocommerce_currency' ) ) ); ?>0.00</td>
		</tr>
		<?php
	}
}

add_action( 'woocommerce_cart_totals_before_shipping', 'zinrelo_row_cart_summery_for_free_product', 99 );
add_action( 'woocommerce_review_order_before_shipping', 'zinrelo_row_cart_summery_for_free_product', 99 );

/**
 * Add custom order totals row
 *
 * @param mixed $total_rows total_rows.
 * @param mixed $order order.
 * @return mixed
 */
function zinrelo_order_totals_row( $total_rows, $order ) {

	// Set last total row in a variable and remove it.
	$gran_total = $total_rows['order_total'];
	unset( $total_rows['order_total'] );

	// Insert a new row.
	$reward_name = get_post_meta( $order->get_id(), 'zinrelo_product_redemption', true );
	if ( $reward_name ) {
		$reward_label             = 'Reward : ' . $reward_name;
		$total_rows['recurr_not'] = array(
			// 'label' => __( $reward_label, 'zinrelo' ),
			'label' => sprintf(
				/* translators: 1: provided value 2: provided type. */
				esc_html__( 'Reward "%1$s"', 'zinrelo' ),
				esc_html( $reward_name )
			),
			'value' => esc_html( get_woocommerce_currency_symbol( get_option( 'woocommerce_currency' ) ) . '0.00' ),
		);
	}

	// Set back last total row.
	$total_rows['order_total'] = $gran_total;

	return $total_rows;
}

add_filter( 'woocommerce_get_order_item_totals', 'zinrelo_order_totals_row', 30, 3 );

/**
 * Add custom admin order totals row
 *
 * @param mixed $order_id order_id.
 */
function zinrelo_admin_order_totals_row( $order_id ) {
	$reward_name = get_post_meta( $order_id, 'zinrelo_product_redemption', true );
	if ( $reward_name ) {
		$reward_label = 'Reward : ' . $reward_name;
		// Here set your data and calculations.
		// $label = __( $reward_label, 'zinrelo' );
		$label = sprintf(
				/* translators: 1: provided value 2: provided type. */
				esc_html__( 'Reward "%1$s"', 'zinrelo' ),
				esc_html( $reward_name )
			);
		$value = esc_html( get_woocommerce_currency_symbol( get_option( 'woocommerce_currency' ) ) . '0.00' );
		?>
		<tr>
			<td class="label"><?php echo esc_html( $label ); ?>:</td>
			<td width="1%"></td>
			<td class="custom-total"><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}
}

add_action( 'woocommerce_admin_order_totals_after_tax', 'zinrelo_admin_order_totals_row', 10, 1 );

/**
 * Filter woo commerce zinrelo coupon error
 *
 * @param mixed $err err.
 * @return string
 */
function zinrelo_filter_woocommerce_zinrelo_coupon_error( $err ) {
	$coupon_code    = WC()->session->get( 'zinrelo_coupon_code' );
	$coupon_data    = new WC_Coupon( $coupon_code );
	$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
	if ( $transaction_id ) {
		if ( ! $coupon_data->id || $coupon_data->usage_limit >= $coupon_data->usage_count ) {
			zinrelo_reject_zinrelo_transaction( $transaction_id );
			zinrelo_clear_cart_session_data();
			global $woocommerce;
			$err = sprintf( esc_html__( 'Invalid coupon code.', 'zinrelo' ) );
		}
	}
	return $err;
}

add_filter( 'woocommerce_coupon_error', 'zinrelo_filter_woocommerce_zinrelo_coupon_error', 10, 3 );

/**
 * Filter woo commerce zinrelo coupon message
 *
 * @param mixed $msg msg.
 * @param mixed $msg_code msg_code.
 * @param mixed $coupon coupon.
 * @return string
 */
function zinrelo_filter_woocommerce_zinrelo_coupon_message( $msg, $msg_code, $coupon ) {
	if ( WC()->session->get( 'zinrelo_coupon_code' ) ) {
		$msg = sprintf(
			'',
			'zinrelo',
			'<strong>' . $coupon->get_code() . '</strong>'
		);
	}
	return $msg;
}

add_filter( 'woocommerce_coupon_message', 'zinrelo_filter_woocommerce_zinrelo_coupon_message', 10, 3 );
