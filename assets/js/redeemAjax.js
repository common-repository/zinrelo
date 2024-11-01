/**
 * The js for displaying cart page drop down apply remove button
 *
 * @package zinrelo\js
 * @version 1.0.1
 */

jQuery( document ).ready(
	function ($) {
		const redeemRewardSelect = $( '#redeem-reward' );
		const loader             = $( '#loader' );
		/*apply-redeem button click to redeem reward applied*/
		$( document ).on(
			'click',
			'#apply-redeem',
			function (e) {
				e.preventDefault();
				const rewardId     = redeemRewardSelect.val();
				var selectedOption = $( "#redeem-reward option:selected" );
				if (rewardId) {
					const data = {
						action: 'redeem_reward',
						reward_id: rewardId,
						action_type: 'redeem',
						zinrelo_reward_value: selectedOption.data( 'zinrelo_reward_value' ),
						reward_sub_type: selectedOption.data( 'reward_sub_type' ),
						product_id: selectedOption.data( 'product_id' ),
						reward_name: selectedOption.data( 'reward_name' ),
						nonce: redeemAjax.nonce
					};
					loader.show();
					$.ajax(
						{
							url: redeemAjax.ajaxurl,
							type: 'POST',
							data: data,
							success: function (response) {
								location.reload();
							},
							error: function (xhr, status, error) {
								console.error( error );
							},
							complete: function () {
								loader.hide();
							}
						}
					);
				}
			}
		);
		/*cancel-redeem button click to redeem reward canceled*/
		$( document ).on(
			'click',
			'#cancel-redeem',
			function (e) {
				e.preventDefault();
				const rewardId = redeemRewardSelect.val();
				const data     = {
					action: 'redeem_reward',
					reward_id: rewardId,
					action_type: 'cancel',
					nonce: redeemAjax.nonce
				};
				loader.show();
				$.ajax(
					{
						url: redeemAjax.ajaxurl,
						type: 'POST',
						data: data,
						success: function (response) {
							location.reload();
						},
						error: function (xhr, status, error) {
							console.error( error );
						},
						complete: function () {
							loader.hide();
						}
					}
				);
			}
		);
	}
);
