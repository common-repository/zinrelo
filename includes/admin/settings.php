<?php
/**
 * This is setting page
 *
 * @package zinrelo\js
 * @version 1.0.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;
add_filter( 'woocommerce_settings_tabs_array', 'zinrelo_zinrelo_settings_tab', 50 );

/**
 * Adds the Zinrelo settings tab to the WooCommerce settings page.
 *
 * @param mixed $tabs The array of settings tabs.
 * @return array The modified array of settings tabs.
 */
function zinrelo_zinrelo_settings_tab( $tabs ): array {
	$tabs['zinrelo'] = __( 'Zinrelo', 'zinrelo' );
	return $tabs;
}

add_action( 'woocommerce_settings_tabs_zinrelo', 'zinrelo_settings_tab_content' );

/**
 * Renders the content for the Zinrelo settings tab.
 *
 * @return void
 */
function zinrelo_settings_tab_content(): void {
	echo '<h2>' . esc_html__( 'Zinrelo Settings', 'zinrelo' ) . '</h2>';
	woocommerce_admin_fields( zinrelo_settings_fields() );
}

/**
 * Returns the Zinrelo settings fields.
 *
 * @return array[] The array of settings fields.
 */
function zinrelo_settings_fields(): array {
	return array(
		array(
			'title'   => '',
			'zinrelo',
			'type'    => 'checkbox',
			'id'      => 'enabled',
			'default' => 'yes',
			'desc'    => __( 'Enable or disable the Zinrelo integration', 'zinrelo' ),
		),
		array(
			'title'    => __( 'Partner Id', 'zinrelo' ),
			'type'     => 'text',
			'id'       => 'partner_id',
			'default'  => '',
			'desc_tip' => __( 'Enter your Zinrelo partner Id here.', 'zinrelo' ),
			'desc'     => __( 'Enter your Zinrelo partner Id here.', 'zinrelo' ),
		),

		array(
			'title'    => __( 'API Key', 'zinrelo' ),
			'desc'     => __( 'Enter your Zinrelo API key here.', 'zinrelo' ),
			'desc_tip' => __( 'Enter your Zinrelo API key here.', 'zinrelo' ),
			'id'       => 'zinrelo_api_key',
			'default'  => '',
			'type'     => 'text',
		),
		array(
			'title'   => '',
			'zinrelo',
			'type'    => 'checkbox',
			'id'      => 'enable_product_page',
			'label'   => __( 'Enable reward points text on product pages', 'zinrelo' ),
			'default' => 'no',
			'desc'    => __( 'Enable reward points text on product pages', 'zinrelo' ),
		),
		array(
			'title'    => __( 'Zinrelo Reward Text On Product Page', 'zinrelo' ),
			'type'     => 'textarea',
			'id'       => 'reward_text_product_page',
			'desc_tip' => __( 'Enter the reward text that will be displayed on product pages.', 'zinrelo' ),
			'desc'     => __( 'Enter the reward text that will be displayed on product pages.', 'zinrelo' ),
			'default'  => 'Earn {{EARN_POINTS}} points by ordering this product.',
		),
		array(
			'title'   => '',
			'zinrelo',
			'type'    => 'checkbox',
			'id'      => 'enable_cart',
			'label'   => __( 'Enable Zinrelo on the cart page', 'zinrelo' ),
			'default' => 'no',
			'desc'    => __( 'Enable or disable Zinrelo on the cart page', 'zinrelo' ),
		),
		array(
			'title'    => __( 'Zinrelo Text On Cart Text', 'zinrelo' ),
			'type'     => 'textarea',
			'id'       => 'reward_text_cart',
			'desc_tip' => __( 'Enter the reward text that will be displayed on the cart page.', 'zinrelo' ),
			'desc'     => __( 'Enter the reward text that will be displayed on the cart page.', 'zinrelo' ),
			'default'  => 'You have {{AVAILABLE_POINTS}} points. Select your reward to redeem.',
		),
		array(
			'title'    => __( 'Free Shipping Reward Label', 'zinrelo' ),
			'type'     => 'text',
			'id'       => 'zinrelo_free_shipping_reward_label',
			'desc_tip' => __( 'Enter Free Shipping Reward Label.', 'zinrelo' ),
			'desc'     => __( 'Enter Free Shipping Reward Label.', 'zinrelo' ),
			'default'  => 'Free Shipping',
		),
		array(
			'title'    => __( 'Languages(Locale)', 'zinrelo' ),
			'type'     => 'text',
			'id'       => 'zinrelo_languages',
			'desc_tip' => __( 'Enter Languages(Locale).', 'zinrelo' ),
			'desc'     => __( 'Value Pass Like : {"fr": "french", "es": "spanish", "hi": "custom language one"}.', 'zinrelo' ),
			'default'  => '{"fr": "french", "en": "english", "hi": "custom language one"}',
		),
		array(
			'title'    => __( 'Zinrelo Cart Session Timeout', 'zinrelo' ),
			'type'     => 'text',
			'id'       => 'cart_session_timeout',
			'desc_tip' => __( 'Enter the timeout value for the Zinrelo cart session in minutes.', 'zinrelo' ),
			'desc'     => __( 'Enter the timeout value for the Zinrelo cart session in minutes.', 'zinrelo' ),
			'default'  => '30',
		),
		array(
			'title'   => '',
			'zinrelo',
			'type'    => 'checkbox',
			'id'      => 'auto_rejection',
			'label'   => __( 'Enable auto reject on page refresh', 'zinrelo' ),
			'default' => 'no',
			'desc'    => __( 'Enable auto reject on page refresh', 'zinrelo' ),
		),
	);
}

add_action( 'woocommerce_update_options_zinrelo', 'zinrelo_update_settings' );
/**
 * Zinrelo update settings
 */
function zinrelo_update_settings(): void {
	woocommerce_update_options( zinrelo_settings_fields() );
}
