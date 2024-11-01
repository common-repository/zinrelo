<?php
/**
 * Step 1: Add the custom field to the customer add/edit page
 *
 * @package zinrelo\rewarddiscount
 */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'show_user_profile', 'zinrelo_user_profile_field' );
add_action( 'edit_user_profile', 'zinrelo_user_profile_field' );
/**
 * Custom user profile field
 *
 * @param mixed $user user.
 */
function zinrelo_user_profile_field( $user ) {
	?>
	<table class="form-table">
		<tr>
			<th><label for="custom_attribute_1"><?php esc_html_e( 'custom_attribute_1', 'zinrelo' ); ?></label></th>
			<td>
				<input type="text" name="custom_attribute_1" id="custom_attribute_1"
						value="<?php echo esc_attr( get_user_meta( $user->ID, 'custom_attribute_1', true ) ); ?>"
						class="regular-text"/>
				<p class="description"><?php esc_html_e( 'Enter custom text information.', 'zinrelo' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

// Step 2: Save the custom field when adding or updating a customer.
add_action( 'personal_options_update', 'zinrelo_save_custom_user_profile_field' );
add_action( 'edit_user_profile_update', 'zinrelo_save_custom_user_profile_field' );

/**
 * Save custom user profile field
 *
 * @param mixed $user_id user_id.
 */
function zinrelo_save_custom_user_profile_field( $user_id ) {
	if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'redeem_ajax_nonce' ) ) {
		if ( isset( $_POST['custom_attribute_1'] ) ) {
			$custom_attribute_1 = sanitize_text_field( wp_unslash( $_POST['custom_attribute_1'] ) ) ?? '';
			update_user_meta( $user_id, 'custom_attribute_1', $custom_attribute_1 );
		}
	}
}
