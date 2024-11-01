<?php
/**
 * This file is helper
 *
 * @package zinrelo\data
 * @version 1.0.1
 */
if ( ! defined( 'ABSPATH' ) ) exit; 
const ZINRELO_LIVE_API_URL = 'https://api.zinrelo.com/v2/loyalty';
/**
 * Request to zinrelo for specific event URL
 *
 * @param mixed  $url url.
 * @param mixed  $params params.
 * @param string $request_type request type.
 * @return array|WP_Error
 */
function zinrelo_request( $url, $params, $request_type = 'post' ) {
	try {
		$api_key    = zinrelo_field_value( 'zinrelo_api_key' );
		$partner_id = zinrelo_field_value( 'partner_id' );
		zinrelo_logger( '==============Start==============' );
		zinrelo_logger( 'URL: ' . $url );
		zinrelo_logger( 'RequestType: ' . $request_type );
		$headers = array(
			'content-type' => 'application/json',
			'api-key'      => $api_key,
			'partner-id'   => $partner_id,
		);
		zinrelo_logger( 'Headers: ' . wp_json_encode( $headers ) );
		zinrelo_logger( 'Params: ' . wp_json_encode( $params ) );
		if ( $params ) {
			$args = array(
				'headers' => $headers,
				'body'    => wp_json_encode( $params ),
			);
		} else {
			$args = array(
				'headers' => $headers,
			);
		}
		$response      = wp_remote_post( $url, $args );
		$response_body = wp_remote_retrieve_body( $response );
		zinrelo_logger( 'Response: ' . $response_body );
		zinrelo_logger( '==============End===============' );
		return $response;
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Get Zinrelo Field Value
 *
 * @param mixed $field_id field id.
 * @return string|null
 */
function zinrelo_field_value( $field_id ): ?string {
	try {
		$fields      = zinrelo_settings_fields();
		$field_value = '';
		foreach ( $fields as $field ) {
			if ( $field['id'] === $field_id ) {
				$field_value = get_option( $field_id, $field['default'] );
				break;
			}
		}
		return $field_value;
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Clear data from session storage
 */
function zinrelo_clear_cart_session_data() {
	try {
		WC()->session->set( 'zinrelo_transaction_id', null );
		WC()->session->set( 'cart_session_timeout', null );
		WC()->session->set( 'zinrelo_reward_value', null );
		WC()->session->set( 'applied', null );
		WC()->session->set( 'reward_id', null );
		WC()->session->set( 'reward_name', null );
		WC()->session->set( 'product_id', null );
		WC()->session->set( 'reward_sub_type', null );
		WC()->session->set( 'zinrelo_coupon_code', null );
	} catch ( Exception $e ) {
		zinrelo_logger( $e->getMessage() );
	}
}

/**
 * Delete by transaction_id
 *
 * @param mixed $zinrelo_transaction_id zinrelo transaction id.
 */
function zinrelo_delete_by_transaction_id( $zinrelo_transaction_id ) {
	global $wpdb;
	if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) && isset( $_POST['zinrelo_transaction_id'] ) ) {
		$table_name             = $wpdb->prefix . 'zinrelo_cart';
		$zinrelo_transaction_id = sanitize_text_field( wp_unslash($_POST['zinrelo_transaction_id']) ); // Sanitize user input (if applicable)

		$wpdb->query( $wpdb->prepare( "DELETE FROM %s WHERE zinrelo_transaction_id = %s", $table_name, $zinrelo_transaction_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange

	}
}

/**
 * Set zinrelo cookie
 *
 * @param mixed $data data.
 */
function zinrelo_set_cookie( $data ) {
	setcookie( 'zinrelo_cookie', $data, time() + 300, COOKIEPATH, COOKIE_DOMAIN );
}

/**
 * Get zinrelo cookie
 */
function zinrelo_zinrelo_cookie() {
	if ( isset( $_COOKIE['zinrelo_cookie'] ) && ! empty( $_COOKIE['zinrelo_cookie'] ) ) {
		return sanitize_text_field( wp_unslash( $_COOKIE['zinrelo_cookie'] ) );
	}
}

/**
 * Destroy zinrelo cookie
 */
function zinrelo_destroy_zinrelo_cookie() {
	setcookie( 'zinrelo_cookie', '', time() - 300, COOKIEPATH, COOKIE_DOMAIN );
}

add_action( 'clear_auth_cookie', 'zinrelo_destroy_zinrelo_cookie' );
