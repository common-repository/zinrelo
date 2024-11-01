<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/**
 * This file is cart pages
 *
 * @package zinrelo\cartpage
 * @version 1.0.1
 */

$cart_status = zinrelo_field_value( 'enable_cart' );
if ( $cart_status && is_user_logged_in() ) {
	if ( WC()->session->get( 'applied' ) !== 'yes' ) {
		WC()->session->set( 'zinrelo_reward_rules', null );
	}
	$message = zinrelo_field_value( 'reward_text_cart' );
	if ( ! WC()->session->get( 'zinrelo_reward_rules' ) ) {
		zinrelo_redeem_rules();
	}
	$redeem_rules = WC()->session->get( 'zinrelo_reward_rules' );
	$points       = zinrelo_reward_points();
	if ( isset( $points ) && ! empty( $redeem_rules ) ) {
		if ( $cart_status && zinrelo_reward_points() !== 'error' ) {
			if ( strpos( $message, '{{AVAILABLE_POINTS}}' ) ) {
				$message = str_replace( '{{AVAILABLE_POINTS}}', "<strong>$points</strong>", $message );
			} else {
				$message = "<strong>$points</strong> " . $message;
			}
		} else {
			$message = '';
		}
		$transaction_id = WC()->session->get( 'zinrelo_transaction_id' );
		$disable        = '';
		if ( WC()->session->get( 'zinrelo_reward_value' ) !== null
			&& WC()->session->get( 'zinrelo_transaction_id' ) !== null ) {
			$disable = 'disabled';
		}
		if ( zinrelo_cron_status_by_transaction_id( $transaction_id ) === 1 ) {
			$disable = '';
		}
		?>
		<?php $woocommerce_version = get_option( 'woocommerce_version' ); ?>
		<?php if ( zinrelo_field_value( 'enable_cart' ) === 'yes' ) { ?>
			<div class="reward-point">
				<?php if ( ! empty( $message ) ) { ?>
					<div class="redeem-label">
						<?php echo wp_kses_post( $message ); ?>
					</div>
				<?php } ?>
				<div class="reward-list" id="reward-list">
					<label for="redeem-reward"></label>
					<select class="redeem-reward-selector" name="redeem-reward"
							id="redeem-reward" <?php echo esc_html( $disable ); ?>>
						<option value=""> <?php echo esc_html__( 'Select a reward', 'zinrelo'); ?> </option>
						<?php foreach ( $redeem_rules as $key => $value ) { ?>
							<option <?php echo ( WC()->session->get( 'reward_id' ) === $value['reward_id'] ) ? 'selected' : ''; ?>
									value="<?php echo esc_attr( $value['reward_id'] ); ?>"
									data-zinrelo_reward_value="<?php echo esc_html( $value['reward_value'] ); ?>"
									data-reward_sub_type="<?php echo esc_html( $value['reward_sub_type'] ); ?>"
									data-product_id="<?php echo esc_html( $value['product_id'] ); ?>"
									data-reward_name="<?php echo esc_html( $value['reward_name'] ); ?>">
								<?php echo esc_html( $value['reward_name'] ); ?>
							</option>
						<?php } ?>
					</select>
					<?php if ( WC()->session->get( 'applied' ) === 'yes' && zinrelo_cron_status_by_transaction_id( $transaction_id ) === 0 ) { ?>
						<input type="submit" class="cancel" value="<?php echo esc_html__( 'Cancel Reward', 'zinrelo' ); ?>"
								id="cancel-redeem"
								title="<?php echo esc_html__( 'Cancel reward', 'zinrelo' ); ?>"
							<?php
							if ( $woocommerce_version >= '8.0.0' ) {
								?>
								style="padding: 7px 15px !important;"
								<?php
							} elseif ( $woocommerce_version < '8.0.0' ) {
								?>
								style="padding: 5px 15px !important;"
								<?php
							}
							?>
						>
					<?php } else { ?>
						<input type="submit" class="redeem" value="<?php echo esc_html__( 'Apply Reward', 'zinrelo' ); ?>"
								id="apply-redeem"
								title="<?php echo esc_html__( 'Apply reward', 'zinrelo' ); ?>"
							<?php
							if ( $woocommerce_version >= '8.0.0' ) {
								?>
								style="padding: 7px 15px !important;"
								<?php
							} elseif ( $woocommerce_version < '8.0.0' ) {
								?>
								style="padding: 5px 15px !important;"
								<?php
							}
							?>
						>
					<?php } ?>

				</div>
				<div id="loader" class="woocommerce-spinner"></div>
			</div>
		<?php } ?>
	<?php }
} ?>
