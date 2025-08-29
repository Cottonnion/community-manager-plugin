<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles checkout process functionality
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class CheckoutHandler {

	/**
	 * Redirect to checkout page after adding item to cart
	 *
	 * @param string $url Default redirect URL
	 * @return string Checkout URL
	 */
	public static function redirect_to_checkout( $url ): string {
		return wc_get_checkout_url();
	}

	/**
	 * Customize add to cart message
	 *
	 * @param string $cart_item_key Cart item key
	 * @param int    $product_id Product ID
	 * @param int    $quantity Quantity
	 * @param int    $variation_id Variation ID
	 * @param array  $variation Variation attributes
	 * @param array  $cart_item_data Cart item data
	 * @return void
	 */
	public static function add_to_cart_message( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		// Add a custom message for when item is added to cart
		// This will appear on the checkout page
		if ( function_exists( 'wc_add_notice' ) ) {
			$product      = wc_get_product( $product_id );
			$product_name = $product ? $product->get_name() : 'Product';

			wc_add_notice(
				sprintf(
					'<div class="direct-checkout-notice">"%s" has been added to your cart. Complete your purchase below.</div>',
					esc_html( $product_name )
				),
				'success'
			);
		}
	}

	/**
	 * Handle guest checkout processing
	 *
	 * @param int       $order_id Order ID
	 * @param array     $posted_data Posted checkout data
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public static function handle_guest_checkout( $order_id, $posted_data, $order ): void {
		// Only process if this is a guest order (no user ID)
		if ( $order->get_user_id() > 0 ) {
			return;
		}

		// Check if this order contains subscription products
		$subscription_type = SubscriptionProcessor::get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		// Store guest data in WC session for later user creation
		if ( function_exists( 'WC' ) && WC()->session ) {
			$guest_data = [
				'order_id'          => $order_id,
				'email'             => $order->get_billing_email(),
				'first_name'        => $order->get_billing_first_name(),
				'last_name'         => $order->get_billing_last_name(),
				'phone'             => $order->get_billing_phone(),
				'subscription_type' => $subscription_type,
				'created_at'        => current_time( 'mysql' ),
			];

			WC()->session->set( 'guest_subscription_data', $guest_data );
		}
	}

	/**
	 * Reload the thank you page after checkout
	 *
	 * This ensures that all data is processed correctly and the user sees the updated state.
	 * Only reloads once using a session flag to prevent reload loops.
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function reload_thank_you_page( int $order_id ): void {
		// Only proceed if we can access the session
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		// Check if this order has already been reloaded
		$reloaded_orders = WC()->session->get( 'reloaded_thank_you_pages', [] );

		// If this order hasn't been reloaded yet
		if ( ! in_array( $order_id, $reloaded_orders ) ) {
			// Add this order to the reloaded list
			$reloaded_orders[] = $order_id;
			WC()->session->set( 'reloaded_thank_you_pages', $reloaded_orders );

			// Get the current URL
			$current_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

			// Only reload if we're on a thank you page
			if ( is_checkout() && isset( $_GET['key'] ) ) {
				// Add JavaScript to reload the page once
				add_action(
					'wp_footer',
					function () use ( $current_url ) {
						?>
					<script type="text/javascript">
					(function($) {
						// Set a flag in sessionStorage to prevent multiple reloads
						if (!sessionStorage.getItem('thank_you_reloaded_<?php echo esc_js( sanitize_key( $_GET['key'] ) ); ?>')) {
							sessionStorage.setItem('thank_you_reloaded_<?php echo esc_js( sanitize_key( $_GET['key'] ) ); ?>', 'true');
							
							// Reload after a short delay to allow initial page render
							setTimeout(function() {
								window.location.reload(true);
							}, 1000);
						}
					})(jQuery);
					</script>
						<?php
					}
				);
			}
		}
	}

	/**
	 * Clear session when the cart is emptied
	 *
	 * @return void
	 */
	public static function clear_session_on_cart_empty(): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'pending_subscription', null );
			WC()->session->set( 'active_subscription', null );
		}
	}

	/**
	 * Hide billing fields with CSS for logged-in users who have complete billing data
	 */
	public static function maybe_remove_billing_fields() {
		if ( ! is_checkout() || ! is_user_logged_in() ) {
			return;
		}
		
		$user_id = get_current_user_id();
		$required_keys = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_state',
			'billing_email',
		];

		$all_filled = true;
		foreach ( $required_keys as $key ) {
			if ( ! get_user_meta( $user_id, $key, true ) ) {
				$all_filled = false;
				break;
			}
		}

		if ( $all_filled ) {
			echo '<style>
			.wc-block-checkout__billing-fields,
			.wp-block-woocommerce-checkout-shipping-method-block,
			.wp-block-woocommerce-checkout-pickup-options-block,
			.wc-block-components-checkout-step#billing-fields,
			fieldset.wc-block-checkout__billing-fields {
				display: none !important;
			}
			</style>';
		}
	}

}