<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles payment gateway filtering
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class PaymentGatewayManager {

	/**
	 * Filter payment gateways based on product currency
	 *
	 * @param array $available_gateways Available payment gateways
	 * @return array Filtered payment gateways
	 */
	public static function filter_payment_gateways_by_currency( array $available_gateways ): array {
		// Skip filtering in admin, but allow it during checkout and REST API requests
		if ( is_admin() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
			return $available_gateways;
		}

		// Check if WooCommerce cart exists and has items
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $available_gateways;
		}

		// Direct filtering approach: Define allowed gateways based on currency
		$cart_items = WC()->cart->get_cart_contents();
		if ( ! empty( $cart_items ) ) {
			$first_item      = reset( $cart_items );
			$product_id      = $first_item['product_id'];
			$currency_select = get_post_meta( $product_id, 'currency_select', true );
			$currency        = strtoupper( trim( $currency_select ) );

			// Define allowed gateways by currency
			$allowed_gateways = [];

			if ( $currency === 'POINTS' ) {
				// Allow only POINTS payment
				$allowed_ids = [ 'gamipress_reward_points' ];
			} elseif ( $currency === 'CREDITS' ) {
				// Allow only CREDITS payment
				$allowed_ids = [ 'gamipress_credits' ];
			} elseif ( $currency === 'USD' ) {
				// Allow regular payment methods but not virtual currency
				$allowed_ids = [ 'paypal', 'stripe_cc', 'cod', 'cheque', 'bacs' ];
			} else {
				// If no currency specified, or unknown currency, keep all gateways
				return $available_gateways;
			}

			// Filter the gateways to only include allowed ones
			foreach ( $available_gateways as $gateway_id => $gateway ) {
				if ( ! in_array( $gateway_id, $allowed_ids ) ) {
					unset( $available_gateways[ $gateway_id ] );
				}
			}

			// If we end up with no gateways, put back the most relevant one based on currency
			if ( empty( $available_gateways ) ) {
				if ( $currency === 'POINTS' && isset( WC()->payment_gateways->payment_gateways()['gamipress_reward_points'] ) ) {
					$available_gateways['gamipress_reward_points'] = WC()->payment_gateways->payment_gateways()['gamipress_reward_points'];
				} elseif ( $currency === 'CREDITS' && isset( WC()->payment_gateways->payment_gateways()['gamipress_credits'] ) ) {
					$available_gateways['gamipress_credits'] = WC()->payment_gateways->payment_gateways()['gamipress_credits'];
				} elseif ( $currency === 'USD' ) {
					// Try to add at least one USD payment method
					foreach ( [ 'paypal', 'cod', 'bacs' ] as $fallback ) {
						if ( isset( WC()->payment_gateways->payment_gateways()[ $fallback ] ) ) {
							$available_gateways[ $fallback ] = WC()->payment_gateways->payment_gateways()[ $fallback ];
							break;
						}
					}
				}
			}

			return $available_gateways;
		}

		// If we reached here, no specific currency filtering was applied
		return $available_gateways;
	}

	/**
	 * Filter out GamiPress payment gateways for users with subscriptions
	 * or when subscription products are in the cart
	 *
	 * @param array $available_gateways List of available payment gateways
	 * @return array Filtered list of payment gateways
	 */
	public static function filter_gamipress_gateways_for_subscription( array $available_gateways ): array {
		// If there are no available gateways, return as is
		if ( empty( $available_gateways ) ) {
			return $available_gateways;
		}

		// Check if user has active subscription
		$user_id = get_current_user_id();
		if ( $user_id && SubscriptionHandler::user_has_active_subscription( $user_id ) ) {
			// Remove GamiPress payment gateways
			foreach ( $available_gateways as $gateway_id => $gateway ) {
				if ( strpos( $gateway_id, 'gamipress' ) !== false ) {
					unset( $available_gateways[ $gateway_id ] );
				}
			}
			return $available_gateways;
		}

		// Check if cart contains subscription products
		if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$has_subscription = false;

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product = $cart_item['data'];
				if ( $product && method_exists( $product, 'get_sku' ) ) {
					$sku = $product->get_sku();
					if ( in_array( $sku, SubscriptionHandler::SUBSCRIPTION_SKUS, true ) ) {
						$has_subscription = true;
						break;
					}
				}
			}

			// If cart has subscription products, remove GamiPress payment gateways
			if ( $has_subscription ) {
				foreach ( $available_gateways as $gateway_id => $gateway ) {
					if ( strpos( $gateway_id, 'gamipress' ) !== false ) {
						unset( $available_gateways[ $gateway_id ] );
					}
				}
			}
		}

		return $available_gateways;
	}
}
