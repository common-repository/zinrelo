/**
 * This js display point on product page
 *
 * @package zinrelo\js
 * @version 1.0.1
 */

jQuery( document ).ready(
	function ($) {
		zrl_mi.price_identifier = function () {
			const product    = {};
			const price      = productData.productPrice;
			const category   = productData.productCategories;
			const product_id = productData.productId;
			if (price) {
				product.price = price;
			}
			if (product_id) {
				product.product_id = product_id;
			}
			if (category) {
				product.category = category;
			}
			return product;
		};
		zrl_mi.price_identifier();

	}
);
