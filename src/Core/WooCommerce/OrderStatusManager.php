<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles order status management for WooCommerce orders
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class OrderStatusManager {

	/**
	 * Auto-complete all orders regardless of product type
	 */
	public static function auto_complete_virtual_orders( string $payment_complete_status, int $order_id, $order ): string {
		// Always mark orders as completed after payment
		return 'completed';
	}

	/**
	 * Force complete order status on thank you page
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function force_complete_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// If order is not completed already, update it
		if ( $order->get_status() !== 'completed' ) {
			$current_status = $order->get_status();

			// Log before changing status
			OrderStatusLogger::log_order_status_change( $order_id, $current_status, 'completed', 'force_complete_order' );

			$order->update_status( 'completed', __( 'Order auto-completed by Labgenz Community Management.', 'labgenz-community-management' ) );
		}
	}

	/**
	 * Change order status from on-hold to complete
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function change_on_hold_to_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Log before changing status
		OrderStatusLogger::log_order_status_change( $order_id, 'on-hold', 'completed', 'change_on_hold_to_complete' );

		// Update status to completed
		$order->update_status( 'completed', __( 'Order auto-completed by Labgenz Community Management - changed from on-hold.', 'labgenz-community-management' ) );
	}

	/**
	 * Track new order creation
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function track_new_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$status = $order->get_status();
		OrderStatusLogger::log_order_status_change( $order_id, 'new', $status, 'new_order_created' );
	}

	/**
	 * Track order status changes
	 *
	 * @param int    $order_id Order ID
	 * @param string $status_from Old status
	 * @param string $status_to New status
	 * @param object $order Order object
	 * @return void
	 */
	public static function track_order_status_changed( int $order_id, string $status_from, string $status_to, $order ): void {
		OrderStatusLogger::log_order_status_change( $order_id, $status_from, $status_to, 'status_changed_hook' );
	}

	/**
	 * Track payment completion
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function track_payment_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		OrderStatusLogger::log_order_status_change( $order_id, 'payment_pending', 'payment_complete', 'payment_complete_hook' );
	}
}
