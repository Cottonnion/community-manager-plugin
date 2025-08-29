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
	public static function redirect_to_checkout( string $url ): string {
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
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

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

	/**
	 * Handle guest checkout processing
	 *
	 * @param int       $order_id Order ID
	 * @param array     $posted_data Posted checkout data
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public static function handle_guest_checkout( int $order_id, array $posted_data, \WC_Order $order ): void {
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
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

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

	/**
	 * Reload the thank you page after checkout
	 *
	 * Ensures all data is processed correctly and user sees updated state.
	 * Uses dual protection (WC session + browser sessionStorage) to prevent reload loops.
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function reload_thank_you_page( int $order_id ): void {
		// Validate prerequisites
		if ( ! self::should_reload_thank_you_page() ) {
			return;
		}

		// Check server-side reload protection
		if ( self::has_order_been_reloaded( $order_id ) ) {
			return;
		}

		// Mark order as reloaded server-side
		self::mark_order_as_reloaded( $order_id );

		// Queue client-side reload script
		self::enqueue_reload_script();
	}

	/**
	 * Check if thank you page should be reloaded
	 *
	 * @return bool True if conditions are met for reload
	 */
	private static function should_reload_thank_you_page(): bool {
		return function_exists( 'WC' ) 
			&& WC()->session 
			&& is_user_logged_in() 
			&& is_checkout() 
			&& isset( $_GET['key'] );
	}

	/**
	 * Check if order has already been reloaded
	 *
	 * @param int $order_id Order ID
	 * @return bool True if already reloaded
	 */
	private static function has_order_been_reloaded( int $order_id ): bool {
		$reloaded_orders = WC()->session->get( 'reloaded_thank_you_pages', [] );
		return in_array( $order_id, $reloaded_orders, true );
	}

	/**
	 * Mark order as reloaded in session
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	private static function mark_order_as_reloaded( int $order_id ): void {
		$reloaded_orders = WC()->session->get( 'reloaded_thank_you_pages', [] );
		$reloaded_orders[] = $order_id;
		WC()->session->set( 'reloaded_thank_you_pages', $reloaded_orders );
	}

	/**
	 * Enqueue JavaScript to reload page once
	 *
	 * @return void
	 */
	private static function enqueue_reload_script(): void {
		add_action( 'wp_footer', function () {
			$order_key = sanitize_key( $_GET['key'] ?? '' );
			
			if ( empty( $order_key ) ) {
				return;
			}
			?>
			<script type="text/javascript">
			(function() {
				'use strict';
				
				const orderKey = '<?php echo esc_js( $order_key ); ?>';
				const storageKey = 'labgenz_thankyou_reloaded_' + orderKey;
				
				// Prevent multiple reloads using browser storage
				if ( sessionStorage.getItem( storageKey ) ) {
					return;
				}

				// Mark as processed
				sessionStorage.setItem( storageKey, Date.now().toString() );
				
				// Reload after DOM is ready
				if ( document.readyState === 'loading' ) {
					document.addEventListener( 'DOMContentLoaded', function() {
						setTimeout( () => window.location.reload( true ), 800 );
					});
				} else {
					setTimeout( () => window.location.reload( true ), 800 );
				}
			})();
			</script>
			<?php
		}, 999 ); // High priority to ensure it runs
	}

	/**
	 * Clear session data when cart is emptied
	 *
	 * @return void
	 */
	public static function clear_session_on_cart_empty(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		// Clear subscription-related session data
		$session_keys_to_clear = [
			'pending_subscription',
			'active_subscription',
			'guest_subscription_data',
		];

		foreach ( $session_keys_to_clear as $key ) {
			WC()->session->set( $key, null );
		}
	}

	/**
	 * Hide billing fields for logged-in users with complete billing data
	 *
	 * @return void
	 */
	public static function maybe_remove_billing_fields(): void {
		if ( ! is_checkout() || ! is_user_logged_in() ) {
			return;
		}
		
		$user_id = get_current_user_id();
		
		// Required billing fields for checkout
		$required_billing_fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_state',
			'billing_email',
		];

		// Check if all required fields are filled
		if ( ! self::user_has_complete_billing_data( $user_id, $required_billing_fields ) ) {
			return;
		}

		// Hide billing fields with CSS
		self::output_billing_fields_hide_css();
	}

	/**
	 * Check if user has complete billing data
	 *
	 * @param int   $user_id User ID
	 * @param array $required_fields Required billing fields
	 * @return bool True if all fields are filled
	 */
	private static function user_has_complete_billing_data( int $user_id, array $required_fields ): bool {
		foreach ( $required_fields as $field ) {
			$value = get_user_meta( $user_id, $field, true );
			if ( empty( $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Output CSS to hide billing fields
	 *
	 * @return void
	 */
	private static function output_billing_fields_hide_css(): void {
		?>
		<style type="text/css">
		/* Hide WooCommerce billing and shipping fields for users with complete data */
		.wc-block-checkout__billing-fields,
		.wp-block-woocommerce-checkout-shipping-method-block,
		.wp-block-woocommerce-checkout-pickup-options-block,
		.wc-block-components-checkout-step#billing-fields,
		fieldset.wc-block-checkout__billing-fields {
			display: none !important;
		}
		</style>
		<?php
	}

	/**
	 * Get secure current URL using WordPress functions
	 *
	 * @return string Current page URL
	 */
	public static function get_current_url(): string {
		return esc_url_raw( home_url( add_query_arg( [] ) ) );
	}

	/**
	 * Clean up expired session data
	 * 
	 * This method can be called periodically to clean up old session data
	 *
	 * @return void
	 */
	public static function cleanup_expired_session_data(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$guest_data = WC()->session->get( 'guest_subscription_data' );
		
		if ( ! $guest_data || ! isset( $guest_data['created_at'] ) ) {
			return;
		}

		// Remove guest data older than 24 hours
		$created_time = strtotime( $guest_data['created_at'] );
		$expiry_time = $created_time + ( 24 * HOUR_IN_SECONDS );
		
		if ( time() > $expiry_time ) {
			WC()->session->set( 'guest_subscription_data', null );
		}
	}
}