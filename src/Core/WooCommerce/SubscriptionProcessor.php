<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subscription processing for WooCommerce orders
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class SubscriptionProcessor {

	/**
	 * Get subscription type from order
	 *
	 * @param \WC_Order $order Order object
	 * @return string|null
	 */
	public static function get_subscription_type_from_order( $order ): ?string {
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$sku = $product->get_sku();

				if ( in_array( $sku, SubscriptionHandler::SUBSCRIPTION_SKUS, true ) ) {
					$subscription_data = SubscriptionHandler::SUBSCRIPTION_TYPES[ $sku ] ?? null;
					return $subscription_data ? $subscription_data['type'] : null;
				}
			}
		}

		return null;
	}

	/**
	 * Auto-complete subscription orders after payment
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function auto_complete_subscription_order( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if this order contains subscription products
		$subscription_type = self::get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		// Auto-complete the order if it's not already completed
		if ( $order->get_status() !== 'completed' ) {
			$current_status = $order->get_status();

			// Log before changing status
			OrderStatusLogger::log_order_status_change( $order_id, $current_status, 'completed', 'auto_complete_subscription_order' );

			$order->update_status( 'completed', __( 'Subscription order auto-completed after payment.', 'labgenz-community-management' ) );
		}
	}

	/**
	 * Store subscription in WC session
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function store_subscription_in_wc_session( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get subscription type from order or meta
		$subscription_type = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_TYPE_META );
		if ( ! $subscription_type ) {
			$subscription_type = self::get_subscription_type_from_order( $order );
		}

		if ( ! $subscription_type ) {
			return;
		}

		// Store subscription data in WC session
		if ( function_exists( 'WC' ) && WC()->session ) {
			$expires   = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_EXPIRES_META ) ?: self::calculate_expiry_date();
			$resources = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_RESOURCES_META ) ?: self::get_allowed_resources( $subscription_type );

			$session_data = [
				'type'      => $subscription_type,
				'status'    => 'active',
				'expires'   => $expires,
				'resources' => $resources,
				'order_id'  => $order_id,
				'user_id'   => $order->get_user_id(),
			];

			WC()->session->set( 'active_subscription', $session_data );
		}
	}

	/**
	 * Calculate subscription expiry date (default 1 year from now)
	 *
	 * @return string
	 */
	public static function calculate_expiry_date(): string {
		return date( 'Y-m-d H:i:s', strtotime( SubscriptionHandler::DEFAULT_EXPIRY_DURATION ) );
	}

	/**
	 * Get allowed resources for subscription type
	 *
	 * @param string $subscription_type Subscription type
	 * @return array
	 */
	public static function get_allowed_resources( string $subscription_type ): array {
		$subscription_handler = SubscriptionHandler::get_instance();
		return $subscription_handler->get_allowed_resources( $subscription_type );
	}

	/**
	 * Filter thank you page text for subscription purchases
	 *
	 * @param string    $text The default thank you text
	 * @param \WC_Order $order The order object
	 * @return string Modified thank you text
	 */
	public static function filter_subscription_thank_you_text( $text, $order ): string {
		// if ( ! $order ) {
			return $text;
		// }

		// Check if this order contains subscription products
		$subscription_type = self::get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return $text;
		}

		// Get subscription name
		$subscription_name = $subscription_type === 'organization' ? 'Organization' : 'Basic';

		// Get user info
		$user_id   = $order->get_user_id();
		$user_name = '';
		if ( $user_id ) {
			$user      = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : '';
		}

		if ( ! $user_name ) {
			$user_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		// Create custom thank you message
		$custom_text = sprintf(
			'<div class="subscription-thank-you" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                <h3 style="color: #28a745; margin-top: 0;">🎉 Welcome to the club, %s!</h3>
                <p style="font-size: 16px; line-height: 1.6;">Thank you for subscribing to our <strong>%s Subscription</strong>!</p>
                <div style="margin: 15px 0;">
                    <h4 style="color: #333; margin-bottom: 10px;">Your subscription includes:</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">',
			esc_html( trim( $user_name ) ),
			esc_html( $subscription_name )
		);

		// Add subscription benefits based on type
		if ( $subscription_type === 'organization' ) {
			$custom_text .= '
                <li>✅ Organization creation and management</li>
                <li>✅ Organization access features</li>
                <li>✅ MLM articles access</li>
                <li>✅ and some other things</li>';
		} else {
			$custom_text .= '
                <li>✅ Access to basic course categories</li>
                <li>✅ Community participation</li>
                <li>✅ Basic support</li>
                <li>✅ and some other things</li>';
		}

		$custom_text .= '
                    </ul>
                </div>
                <p style="font-size: 14px; color: #666; margin-bottom: 0;">
                    Your subscription is now active and expires on <strong>' . date( 'F j, Y', strtotime( self::calculate_expiry_date() ) ) . '</strong>.
                </p>
                <p style="font-size: 14px; color: #666; margin-bottom: 0;">
                    You have been automatically logged in and can start exploring your new features immediately!
                </p>
            </div>';

		return $custom_text . $text;
	}
}
