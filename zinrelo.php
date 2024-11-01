<?php
/**
 * Plugin Name: Zinrelo
 * Plugin URI: http://woocommerce.com/products/
 * Description: Integration with Zinrelo.
 * Version: 1.0.1
 * Author: Zinrelo
 * Author URI: https://www.zinrelo.com/
 * License:           GPL-2.0+
 * Text Domain: zinrelo
 * Domain Path: /languages
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 5
 * WC tested up to: 7.9
 *
 * @package zinrelo\zinrelo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZINRELO_VERSION', '1.0.1' );
define( 'ZINRELO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
require_once(ABSPATH . 'wp-admin/includes/file.php'); 

require_once plugin_dir_path( __FILE__ ) . 'src/Database/zinrelo-db.php';
register_activation_hook( __FILE__, 'zinrelo_create_zinrelo_cart_table' );

/**
 * Check if WooCommerce is active
 */
add_action( 'admin_init', 'zinrelo_check_plugin_activation' );

/**
 * Check plugin activation
 */
function zinrelo_check_plugin_activation() {
	if (!is_plugin_active( 'woocommerce/woocommerce.php' )) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', 'zinrelo_woocommerce_dependency_activation_notice' );
	}
}

/**
 * Woocommerce Dependency Activation Notice
 */
function zinrelo_woocommerce_dependency_activation_notice() {
	echo '<div class="notice notice-error"><p>';
	echo 'The plugin you are trying to activate requires WooCommerce to be active.';
	echo '</p></div>';
}

require_once ZINRELO_PLUGIN_DIR . 'Firebase/JWT/JWT.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ProductPageBlock.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/CartPageBlock.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ApplyRewardPoint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/RemoveRewardPoint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/RewardDiscount.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ApproveReward.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/CustomerAttribute.php';
require_once plugin_dir_path( __FILE__ ) . 'templates/frontend/Dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'src/action-scheduler/RejectRedeemReward.php';
require_once plugin_dir_path( __FILE__ ) . 'helper/Data.php';


/**
 * Enqueues custom CSS for WooCommerce admin.
 *
 * @return void
 */
function zinrelo_enqueue_woocommerce_admin_css(): void {
	wp_enqueue_style(
	 'woocommerce-admin-custom', 
	 plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', 
	 array(), 
	 '1.0.0' 
	);
}

add_action( 'admin_enqueue_scripts', 'zinrelo_enqueue_woocommerce_admin_css' );


/**
 * Enqueues custom CSS for WooCommerce frontend.
 *
 * @return void
 */
function zinrelo_enqueue_woocommerce_frontend_css(): void {
	wp_enqueue_style(
	 'woocommerce-frontend-custom',
	  plugin_dir_url( __FILE__ ) . 'assets/css/main.css', 
	  array(),
	  '1.0.0'
	);
}

add_action( 'wp_enqueue_scripts', 'zinrelo_enqueue_woocommerce_frontend_css' );

/**
 * Enqueues the AJAX scripts.
 *
 * @return void
 */
function zinrelo_enqueue_ajax_scripts(): void {
	wp_enqueue_script( 'ajax-url', plugin_dir_url( __FILE__ ) . 'assets/js/redeemAjax.js', array( 'jquery' ), '1.0.0', true );
	$ajax_nonce = wp_create_nonce( 'redeem_ajax_nonce' );
	wp_localize_script(
		'ajax-url',
		'redeemAjax',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => $ajax_nonce,
		)
	);
}

add_action( 'wp_enqueue_scripts', 'zinrelo_enqueue_ajax_scripts' );

/**
 * AJAX handler function to redeem something.
 *
 * @return void
 */
function zinrelo_redeem_ajax_handler() {
	if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
		wp_send_json_error( 'Invalid nonce.' );
	}
	wp_send_json_success( 'Success message.' );
}

add_action( 'wp_ajax_redeem_action', 'zinrelo_redeem_ajax_handler' );
add_action( 'wp_ajax_nopriv_redeem_action', 'zinrelo_redeem_ajax_handler' );

/**
 * Add new time interval for cron job.
 *
 * Here we are adding a 1-minute cron job.
 *
 * @param array $schedules schedules.
 * @return array
 */
function zinrelo_every_five_minute_schedule( $schedules ) {
	$schedules['zinrelo_every_five_minute'] = array(
		'interval' => 5 * 60,
		'display'  => __( 'Zinrelo Every Five Minute', 'zinrelo' ),
	);
	return $schedules;
}

add_filter( 'cron_schedules', 'zinrelo_every_five_minute_schedule' );
/**
 * Schedule the cron job.
 *
 * @return void
 */
function zinrelo_core_activate() {
	if ( ! wp_next_scheduled( 'zinrelo_every_five_minute_event' ) ) {
		wp_schedule_event( time(), 'zinrelo_every_five_minute', 'zinrelo_every_five_minute_event' );
	}
}

register_activation_hook( __FILE__, 'zinrelo_core_activate' );
add_action( 'zinrelo_every_five_minute_event', 'zinrelo_every_five_minute_cronjob' );

/**
 * Do whatever you want to do in the cron job.
 */
function zinrelo_every_five_minute_cronjob() {
	zinrelo_update_status();
	add_option( 'zinrelo_crone_run_at', gmdate( 'Y-m-d H:i:s', time() ) );
}

/**
 * Clear the cron scheduler.
 *
 * @return void
 */
function zinrelo_deactivation() {
	wp_clear_scheduled_hook( 'zinrelo_every_five_minute_event' );
}

register_deactivation_hook( __FILE__, 'zinrelo_deactivation' );

/**
 * Renders the content for the 'zinrelo-page' endpoint in My Account.
 *
 * @return void
 */
function zinrelo_my_account_navigation_content() {
	?>
	<div id="zrl_embed_div"></div>
	<?php
}

add_action( 'woocommerce_account_zinrelo-page_endpoint', 'zinrelo_my_account_navigation_content' );

/**
 * Log a message to the Zinrelo log file.
 *
 * @param string $message The message to log.
 * @return void
 */
function zinrelo_logger( $message ): void {
	$log_file          = WP_CONTENT_DIR . '/zinrelo.log';
	$formatted_message = gmdate( '[Y-m-d H:i:s]' ) . ' ' . $message . "\n";
	WP_Filesystem( $log_file, $formatted_message, FILE_APPEND );
}
