<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file is dash board
 *
 * @package zinrelo\dashboard
 * @version 1.0.1
 */

/**
 * Output JavaScript with escaped variables
 *
 * @return void
 */
function zinrelo_enqueue_zinrelo_script() {
    if ( zinrelo_field_value( 'enabled' ) === 'yes' ) {
        // Get partner_id and sanitize it for JavaScript
        $partner_id = zinrelo_field_value( 'partner_id' );
        $partner_id = esc_js( $partner_id ); // This is acceptable but we'll use wp_json_encode for consistency
        
        // Generate or retrieve JWT token
        $jwt_token = zinrelo_zinrelo_cookie() ?? zinrelo_generate_jwt_token();
        $jwt_token = esc_js( $jwt_token ); // Ensure the JWT token is escaped

        // Enqueue the main script
        wp_enqueue_script( 'zinrelo-script', '//cdn.zinrelo.com/js/all.js', array( 'jquery' ), '1.0.0', true );

        // Prepare data for inline script
        $init_data = array(
            'partner_id' => $partner_id,
            'jwt_token'  => $jwt_token,
            'version'    => 'v2',
            'server'     => 'https://app.zinrelo.com'
        );

        // Use wp_json_encode() to ensure safe JSON encoding
        $init_data_json = wp_json_encode( $init_data );

        // Add inline script
        wp_add_inline_script(
            'zinrelo-script',
            "jQuery(document).ready(function($) {
                window._zrl = window._zrl || [];
                var init_data = $init_data_json;
                _zrl.push(['init', init_data]);
            });"
        );
    }
}

add_action( 'wp_enqueue_scripts', 'zinrelo_enqueue_zinrelo_script' );

/**
 * Delete zinrelo cookie
 */
function zinrelo_delete_zinrelo_cookie() {
	setcookie( 'zinrelo_cookie', '', time() - 300, COOKIEPATH, COOKIE_DOMAIN );
}

add_action( 'wp_login', 'zinrelo_delete_zinrelo_cookie', 10, 2 );

/**
 * Create Customer jwt_token
 */
function zinrelo_create_customer_jwt_token() {
	setcookie( 'zinrelo_cookie', '', time() - 300, COOKIEPATH, COOKIE_DOMAIN );
}

add_action( 'user_register', 'zinrelo_create_customer_jwt_token' );

/**
 * Generates a JWT token for authentication.
 *
 * @return string The generated JWT token.
 */
function zinrelo_generate_jwt_token(): string {
	$secret               = zinrelo_field_value( 'zinrelo_api_key' );
	$current_user         = wp_get_current_user();
	$user_id              = $current_user->ID;
	$current_date_time    = gmdate( 'm/d/Y' );
	$last_visit_timestamp = $current_date_time;
	$telephone            = get_user_meta( $current_user->ID, 'billing_phone', true );
	$user_info            = array(
		'member_id'          => $current_user->user_email,
		'email_address'      => $current_user->user_email,
		'first_name'         => $current_user->first_name,
		'last_name'          => $current_user->last_name,
		'phone_number'       => $telephone ? ( preg_match( '/^\+[0-9]{2}-[0-9]{10}+$/', $telephone ) ? $telephone : '' ) : '',
		'preferred_language' => zinrelo_preferred_language(),
		'address'            => array(
			'line1'       => get_user_meta( $current_user->ID, 'billing_address_1', true ),
			'line2'       => get_user_meta( $current_user->ID, 'billing_address_2', true ),
			'city'        => get_user_meta( $current_user->ID, 'billing_city', true ),
			'state'       => get_user_meta( $current_user->ID, 'billing_state', true ),
			'country'     => get_user_meta( $current_user->ID, 'billing_country', true ),
			'postal_code' => get_user_meta( $current_user->ID, 'billing_postcode', true ),
		),
		'custom_attributes'  => array(
			'Last website visit timestamp' => $last_visit_timestamp,
			'External member id'           => $user_id,
		),
		'exp'                => round( microtime( true ) * 1000 ),
	);
	$data                 = Firebase\JWT\JWT::encode( $user_info, $secret, 'HS256' );
	zinrelo_set_cookie( $data );
	return $data;
}

/**
 * Get Preferred Language
 *
 * @return mixed|string
 */
function zinrelo_preferred_language() {
	$json_config_language = zinrelo_field_value( 'zinrelo_languages' );
	if ( $json_config_language ) {
		$lang            = get_locale() ?? '';
		$config_language = (array) json_decode( $json_config_language );
		$config          = stristr( $lang, '_', true );
		if ( isset( $config_language[ $config ] ) && $config_language[ $config ] ) {
			return $config_language[ $config ];
		} else {
			return '';
		}
	}
}
