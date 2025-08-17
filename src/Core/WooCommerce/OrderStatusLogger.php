<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles order status logging
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class OrderStatusLogger {
    
    /**
     * Log order status change with backtrace
     *
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param string $context Context of the status change
     * @return void
     */
    public static function log_order_status_change(int $order_id, string $old_status = '', string $new_status = '', string $context = ''): void {
        $log_file = LABGENZ_CM_PATH . 'src/logs/order-status-changes.log';
        
        // Create logs directory if it doesn't exist
        $logs_dir = LABGENZ_CM_PATH . 'src/logs';
        if (!file_exists($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }
        
        // Get current user info
        $current_user = wp_get_current_user();
        $user_info = ($current_user->ID > 0) ? 
            "User: {$current_user->user_login} (ID: {$current_user->ID})" : 
            "System or Guest";
            
        // Get order information
        $order = wc_get_order($order_id);
        $order_info = '';
        if ($order) {
            $order_info = "Total: {$order->get_total()} {$order->get_currency()} | " .
                "Billing Email: {$order->get_billing_email()} | " .
                "Customer ID: {$order->get_customer_id()}";
        }
        
        // Get debug backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $trace_info = [];
        
        foreach ($backtrace as $index => $trace) {
            if ($index === 0) continue; // Skip this function call
            
            $class = $trace['class'] ?? '';
            $type = $trace['type'] ?? '';
            $function = $trace['function'] ?? '';
            $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
            $line = $trace['line'] ?? 'unknown';
            
            $trace_info[] = "{$file}:{$line} - {$class}{$type}{$function}()";
            
            // Limit to 3 trace entries
            if ($index >= 3) break;
        }
        
        // Format the log entry
        $log_entry = sprintf(
            "[%s] Order #%d: %s → %s | Context: %s | %s | %s | Backtrace: %s\n",
            current_time('mysql'),
            $order_id,
            $old_status ?: 'created',
            $new_status ?: 'unknown',
            $context,
            $user_info,
            $order_info,
            implode(' ← ', $trace_info)
        );
        
        // Write to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
