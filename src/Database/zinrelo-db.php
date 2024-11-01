<?php
/**
 * This file is zinrelo-databse
 *
 * @package zinrelo\zinrelo-db
 * @version 1.0.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Create the zinrelo_cart table.
 */
function zinrelo_create_zinrelo_cart_table() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'zinrelo_cart';
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        zinrelo_transaction_id varchar(255) NOT NULL,
        cron_status ENUM('1', '0') NOT NULL DEFAULT '0',
        cart_timeout datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        INDEX zinrelo_transaction_id_index (zinrelo_transaction_id)
    ) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}


/**
 * Insert data into the zinrelo_cart table.
 *
 * @param int    $user_id The user ID.
 * @param string $zinrelo_transaction_id The Zinrelo transaction ID.
 * @param string $cron_status The cron status.
 * @param string $cart_timeout The cart timeout date and time.
 */
function zinrelo_insert_zinrelo_cart_data(
	$user_id,
	$zinrelo_transaction_id,
	$cron_status,
	$cart_timeout
) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'zinrelo_cart';

	$data = array(
		'user_id'                => $user_id,
		'zinrelo_transaction_id' => $zinrelo_transaction_id,
		'cart_timeout'           => $cart_timeout,
		'cron_status'            => $cron_status,
	);
	wp_insert_post( $table_name, $data );
	// $wpdb->insert( $table_name, $data );
}

/**
 * Get cron_status by zinrelo_transaction_id from the zinrelo_cart table.
 *
 * @param string $zinrelo_transaction_id The Zinrelo transaction ID.
 * @return int|null The cron_status if found, or null if not found.
 */
function zinrelo_cron_status_by_transaction_id( $zinrelo_transaction_id ) {
	global $wpdb;
	$zinrelo_transaction_id = sanitize_text_field( $zinrelo_transaction_id );
	$table_name             = $wpdb->prefix . 'zinrelo_cart';
	$cron_status            = $wpdb->prepare('SELECT cron_status FROM %s WHERE zinrelo_transaction_id = %s', $table_name, $zinrelo_transaction_id);
	return null !== $cron_status ? intval( $cron_status ) : null;
}
