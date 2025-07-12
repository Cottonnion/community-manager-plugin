<?php
declare(strict_types=1);

namespace LABGENZ_CM\Groups;

/**
 * Handles AJAX organization (group) creation for Labgenz Community Management.
 *
 * @package LabgenzCommunityManagement
 */
class GroupCreationHandler {
    /**
     * Registers AJAX actions for organization creation.
     *
     * @return void
     */
    public function __construct() {
        add_action('wp_ajax_create_organization_request', [$this, "handle_ajax_create_organization_request"]);
        
        // Clean up cart on frontend hooks
        add_action('woocommerce_before_checkout_form', [$this, "ensure_single_payment_product"]);
        add_action('woocommerce_before_cart', [$this, "ensure_single_payment_product"]);
        add_action('wp_loaded', [$this, "ensure_single_payment_product"], 20);
        
        // Prevent adding multiple payment products
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_payment_product_add_to_cart'], 10, 3);
        
        // When order is created, move group_id from user meta to order meta
        add_action('woocommerce_thankyou', function($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) return;
            
            $user_id = $order->get_user_id();
            if ($user_id) {
                $group_id = get_user_meta($user_id, 'mlmmc_pending_group_id', true);
                if ($group_id) {
                    $order->add_meta_data('mlmmc_group_id', $group_id, true);
                    $order->save();
                    delete_user_meta($user_id, 'mlmmc_pending_group_id');
                }
            }
        });
        
        // When order is completed, set group meta
        add_action('woocommerce_order_status_completed', function($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) return;
            
            $group_id = $order->get_meta('mlmmc_group_id');
            if ($group_id) {
                groups_update_groupmeta($group_id, 'mlmmc_checkout_status', 'true');
            }
        });
    }

    /**
     * Validates adding payment products to cart - prevents duplicates
     */
    public function validate_payment_product_add_to_cart($passed, $product_id, $quantity) {
        if (!$passed) return $passed;
        
        $product = wc_get_product($product_id);
        if (!$product) return $passed;
        
        $sku = $product->get_sku();
        $payment_skus = ['group-payments', 'individual-payments'];
        
        if (!in_array($sku, $payment_skus)) {
            return $passed;
        }
        
        // Check if cart already has payment products
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $cart_product = $cart_item['data'];
                if ($cart_product && method_exists($cart_product, 'get_sku')) {
                    $cart_sku = $cart_product->get_sku();
                    if (in_array($cart_sku, $payment_skus)) {
                        wc_add_notice('You can only have one payment product in your cart at a time.', 'error');
                        return false;
                    }
                }
            }
        }
        
        return $passed;
    }

    /**
     * Handles AJAX request to create an organization (group).
     *
     * @return void
     */
    public static function handle_ajax_create_organization_request() {
        try {
            // Clear any existing cart issues first
            if (function_exists('WC') && WC()->cart) {
                self::cleanup_cart_payment_products();
            }
            
            $post = $_POST;
            $files = $_FILES;
            
            self::validate_post_data($post);
            self::validate_nonce($post['organization_nonce']);
            
            $group_id = self::create_group($post);
            self::update_group_meta($group_id, $post);
            self::update_group_type($group_id);
            
            $logo_url = self::handle_avatar_upload($group_id, $files, $post);
            $bp_avatar_nonce = wp_create_nonce('bp_avatar_cropstore');
            
            // Store group_id in user meta for checkout
            $user_id = get_current_user_id();
            if ($user_id) {
                update_user_meta($user_id, 'mlmmc_pending_group_id', $group_id);
            }
            
            // Set group_id in WooCommerce session
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('mlmmc_group_id', $group_id);
                if (method_exists(WC()->session, 'set_customer_session_cookie')) {
                    WC()->session->set_customer_session_cookie(true);
                }
            }
            
            // Build checkout URL
            $selected_plan = sanitize_text_field($post['selected_plan']);
            $product_id = self::get_product_id_by_sku($selected_plan);
            
            if (!$product_id) {
                throw new \Exception('Payment product not found');
            }
            
            $checkout_url = add_query_arg(
                array('add-to-cart' => $product_id),
                wc_get_checkout_url()
            );
            
            $response_message = 'Organization created! Please complete checkout.';
            if ($logo_url) {
                $response_message .= ' Avatar uploaded.';
            }
            
            wp_send_json_success([
                'message' => $response_message,
                'group_id' => $group_id,
                'logo_url' => $logo_url,
                'bp_avatar_nonce' => $bp_avatar_nonce,
                'checkout_url' => $checkout_url
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'A critical error occurred: ' . $e->getMessage(),
                'exception_type' => get_class($e),
            ]);
        } catch (\Error $e) {
            wp_send_json_error([
                'message' => 'A fatal error occurred: ' . $e->getMessage(),
                'error_type' => get_class($e),
            ]);
        }
    }

    /**
     * Clean up cart payment products - ensure only 1 payment product with quantity 1
     */
    private static function cleanup_cart_payment_products(): void {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }
        
        $payment_skus = ['group-payments', 'individual-payments'];
        $cart_contents = WC()->cart->get_cart();
        $payment_items = [];
        
        // Find all payment items in cart
        foreach ($cart_contents as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if ($product && method_exists($product, 'get_sku')) {
                $sku = $product->get_sku();
                if (in_array($sku, $payment_skus)) {
                    $payment_items[] = [
                        'key' => $cart_item_key,
                        'sku' => $sku,
                        'quantity' => $cart_item['quantity']
                    ];
                }
            }
        }
        
        if (empty($payment_items)) {
            return;
        }
        
        // If multiple payment items exist, keep only group-payments
        if (count($payment_items) > 1) {
            $keep_item = null;
            
            // Prefer group-payments over individual-payments
            foreach ($payment_items as $item) {
                if ($item['sku'] === 'group-payments') {
                    $keep_item = $item;
                    break;
                }
            }
            
            // If no group-payments, keep first individual-payments
            if (!$keep_item) {
                $keep_item = $payment_items[0];
            }
            
            // Remove all except the one we're keeping
            foreach ($payment_items as $item) {
                if ($item['key'] !== $keep_item['key']) {
                    WC()->cart->remove_cart_item($item['key']);
                }
            }
            
            // Update quantity to 1 for kept item
            if ($keep_item['quantity'] > 1) {
                WC()->cart->set_quantity($keep_item['key'], 1);
            }
        } else {
            // Single payment item - ensure quantity is 1
            $item = $payment_items[0];
            if ($item['quantity'] > 1) {
                WC()->cart->set_quantity($item['key'], 1);
            }
        }
        
        WC()->cart->calculate_totals();
    }

    /**
     * Public wrapper for cart cleanup
     */
    public function ensure_single_payment_product(): void {
        self::cleanup_cart_payment_products();
    }

    /**
     * Validates the POST data for required fields and types.
     */
    private static function validate_post_data(array $post): void {
        if (!is_array($post)) {
            throw new \Exception('Server error: POST data is not an array.');
        }
        
        $required_fields = ['organization_nonce', 'organization_name', 'selected_plan'];
        foreach ($required_fields as $field) {
            if (!isset($post[$field])) {
                throw new \Exception(sprintf(__('Missing required field: %s', 'buddyboss'), $field));
            }
        }
        
        if (empty(sanitize_text_field($post['organization_name']))) {
            throw new \Exception('Organization name is required');
        }
        
        if (empty(sanitize_text_field($post['selected_plan']))) {
            throw new \Exception('Please select a pricing plan');
        }
    }

    /**
     * Validates the nonce for security.
     */
    private static function validate_nonce(string $nonce): void {
        if (!wp_verify_nonce($nonce, 'create_organization')) {
            throw new \Exception(__('Nonce verification failed. Please try again.', 'buddyboss'));
        }
    }

    /**
     * Creates a BuddyBoss/BuddyPress group.
     */
    private static function create_group(array $post): int {
        if (!function_exists('groups_create_group')) {
            throw new \Exception('BuddyPress/BuddyBoss is not active or loaded');
        }
        
        $group_id = groups_create_group([
            'name'        => sanitize_text_field($post['organization_name']),
            'description' => isset($post['organization_description']) ? sanitize_textarea_field($post['organization_description']) : '',
            'status'      => 'private',
            'creator_id'  => get_current_user_id(),
        ]);
        
        if (!$group_id || is_wp_error($group_id)) {
            $error_msg = is_wp_error($group_id) ? $group_id->get_error_message() : 'Failed to create organization';
            throw new \Exception($error_msg);
        }
        
        return (int)$group_id;
    }

    /**
     * Updates group meta.
     */
    private static function update_group_meta(int $group_id, array $post): void {
        groups_update_groupmeta($group_id, 'mlmmc_checkout_status', "false");
        groups_update_groupmeta($group_id, 'mlmmc_organization_payment_type', sanitize_text_field($post['selected_plan']));
        groups_update_groupmeta($group_id, 'mlmmc_total_seats', 30);
        groups_update_groupmeta($group_id, 'mlmmc_used_seats', 0);
        groups_update_groupmeta($group_id, 'mlmmc_subscription_status', 'active');
    }

    /**
     * Update group type.
     */
    private static function update_group_type(int $group_id): string {
        if (function_exists('bp_groups_set_group_type')) {
            bp_groups_set_group_type($group_id, 'organization');
            return 'organization';
        }
        return '';
    }

    /**
     * Handles avatar upload and moves it to the group-avatars directory.
     */
    private static function handle_avatar_upload(int $group_id, array $files, array $post): string {
        if (empty($files['organization_logo']['name']) || $files['organization_logo']['error'] !== UPLOAD_ERR_OK) {
            return '';
        }
        
        try {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            $uploaded_file = $files['organization_logo'];
            $upload_dir = wp_upload_dir();
            $group_avatar_dir = trailingslashit($upload_dir['basedir']) . 'group-avatars/' . $group_id . '/';
            
            if (!file_exists($group_avatar_dir)) {
                wp_mkdir_p($group_avatar_dir);
            }
            
            $ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
            $avatar_filename = 'group-avatar-bpfull.' . $ext;
            $avatar_path = $group_avatar_dir . $avatar_filename;
            
            $movefile = wp_handle_upload($uploaded_file, ['test_form' => false]);
            
            if ($movefile && empty($movefile['error'])) {
                if (!@copy($movefile['file'], $avatar_path)) {
                    throw new \Exception('Failed to copy uploaded file to group avatar directory.');
                }
                
                @chmod($avatar_path, 0644);
                @unlink($movefile['file']);
                
                $avatar_url = trailingslashit($upload_dir['baseurl']) . 'group-avatars/' . $group_id . '/' . $avatar_filename;
                
                $item_id = $group_id;
                $item_type = isset($post['item_type']) ? sanitize_text_field($post['item_type']) : null;
                
                do_action('groups_avatar_uploaded', $item_id, $item_type, [
                    'avatar' => $avatar_url,
                    'item_id' => $item_id,
                    'item_type' => $item_type,
                    'object' => 'group',
                    'avatar_dir' => 'group-avatars',
                ]);
                
                return $avatar_url;
            } else {
                throw new \Exception('Avatar upload failed: ' . $movefile['error']);
            }
        } catch (\Exception $e) {
            // Optionally log error
            return '';
        }
    }

    /**
     * Gets the group URL if available.
     */
    private static function get_group_url(int $group_id): string {
        return function_exists('bp_get_group_permalink') ? bp_get_group_permalink(groups_get_group(['group_id' => $group_id])) : '';
    }

    /**
     * Get WooCommerce product ID by SKU
     */
    private static function get_product_id_by_sku(string $sku): ?int {
        if (!function_exists('wc_get_product_id_by_sku')) {
            return null;
        }
        return wc_get_product_id_by_sku($sku);
    }
}