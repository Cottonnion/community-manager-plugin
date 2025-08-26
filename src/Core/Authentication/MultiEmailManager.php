<?php

namespace LABGENZ_CM\Core\Authentication;

/**
 * MultiEmailManager
 * 
 * Manages multiple email aliases (routes) for main accounts (romas).
 * Handles registration, verification, and security for email aliases.
 */
class MultiEmailManager {
    
    private $table_name;
    private $max_aliases_per_user = 5;
    private $token_expiry = 1800; // 30 minutes
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'user_email_aliases';
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'maybe_create_table']);
        add_action('wp_ajax_add_email_alias', [$this, 'handle_add_alias']);
        add_action('wp_ajax_verify_email_alias', [$this, 'handle_verify_alias']);
        add_action('wp_ajax_nopriv_verify_email_alias', [$this, 'handle_verify_alias']);
        add_action('wp_ajax_remove_email_alias', [$this, 'handle_remove_alias']);
        
        // Block registration attempts from alias emails
        add_filter('registration_errors', [$this, 'block_alias_registration'], 10, 3);
        // add_action('user_register', [$this, 'prevent_duplicate_alias_registration']);
        
        // Clean expired tokens
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_tokens']);
    }
    
    /**
     * Create custom table for email aliases
     */
    public function maybe_create_table() {
        global $wpdb;
        
        if (get_option('user_email_aliases_table_version') !== '1.0') {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                alias_email varchar(255) NOT NULL,
                alias_type enum('route') DEFAULT 'route',
                is_verified tinyint(1) DEFAULT 0,
                verification_token varchar(64) DEFAULT NULL,
                token_expires_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                verified_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY alias_email (alias_email),
                KEY user_id (user_id),
                KEY verification_token (verification_token),
                FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            update_option('user_email_aliases_table_version', '1.0');
        }
    }
    
    /**
     * Add email alias (route) to main account (roma)
     */
    public function handle_add_alias() {
        check_ajax_referer('email_alias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Authentication required');
        }
        
        $user_id = get_current_user_id();
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email format');
        }
        
        // Security checks
        $validation_result = $this->validate_alias_addition($user_id, $email);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Rate limiting check
        if ($this->is_rate_limited($user_id)) {
            wp_send_json_error('Too many attempts. Please wait before adding another alias.');
        }
        
        $result = $this->create_alias_with_verification($user_id, $email);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Verification email sent. Please check your inbox.');
    }
    
    /**
     * Verify email alias via token link and auto-login
     */
    public function handle_verify_alias() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        $email = sanitize_email($_GET['email'] ?? '');

        if (empty($token) || empty($email)) {
            wp_die('Missing verification data.', 'Verification Failed', ['response' => 400]);
        }

        $result = $this->verify_alias_token($email, $token);

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), 'Verification Failed', ['response' => 400]);
        }

        // Get the main user linked to this alias
        $user = $this->get_user_by_alias($email);
        if ($user) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }

        // Redirect to a success page or dashboard
        wp_safe_redirect(home_url('/email-verified/'));
        exit;
    }
 
    /**
     * Remove email alias
     */
    public function handle_remove_alias() {
        check_ajax_referer('email_alias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Authentication required');
        }
        
        $user_id = get_current_user_id();
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!$this->user_owns_alias($user_id, $email)) {
            wp_send_json_error('Unauthorized');
        }
        
        $result = $this->remove_alias($user_id, $email);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Email alias removed');
    }
    
    /**
     * Validate alias addition security
     */
    private function validate_alias_addition($user_id, $email) {
        global $wpdb;
        
        // Check if email already exists as a WordPress user
        if (email_exists($email)) {
            return new \WP_Error('email_exists', 'This email is already registered as a main account (roma)');
        }
        
        // Check if email already exists as an alias
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE alias_email = %s",
            $email
        ));
        
        if ($existing) {
            return new \WP_Error('alias_exists', 'This email is already used as an alias (route)');
        }
        
        // Check user's primary email
        $user = get_user_by('ID', $user_id);
        if ($user && $user->user_email === $email) {
            return new \WP_Error('own_primary_email', 'Cannot add your primary email as an alias');
        }
        
        // Check alias limit
        $alias_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));
        
        if ($alias_count >= $this->max_aliases_per_user) {
            return new \WP_Error('alias_limit', "Maximum {$this->max_aliases_per_user} aliases allowed per account");
        }
        
        // Block disposable email domains
        if ($this->is_disposable_email($email)) {
            return new \WP_Error('disposable_email', 'Temporary email services are not allowed');
        }
        
        return true;
    }
    
    /**
     * Create alias with verification token
     */
    private function create_alias_with_verification($user_id, $email) {
        global $wpdb;
        
        $token = wp_generate_password(32, false);
        $token_hash = wp_hash_password($token);
        $expires_at = gmdate('Y-m-d H:i:s', time() + $this->token_expiry);
        
        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'alias_email' => $email,
                'alias_type' => 'route',
                'verification_token' => $token_hash,
                'token_expires_at' => $expires_at,
                'is_verified' => 0
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );
        
        if (!$inserted) {
            return new \WP_Error('db_error', 'Failed to create alias');
        }
        
        // Send verification email
        $this->send_verification_email($email, $token, $user_id);
        
        // Log for security monitoring
        $this->log_alias_activity($user_id, 'alias_created', $email);
        
        return true;
    }
    
    /**
     * Send verification email
     */
    private function send_verification_email($email, $token, $user_id) {
        $user = get_user_by('ID', $user_id);
        $verification_url = add_query_arg([
            'action' => 'verify_email_alias',
            'token' => urlencode($token),
            'email' => urlencode($email)
        ], admin_url('admin-ajax.php'));
        
        $subject = 'Verify Your Email Alias (Route)';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Please verify your email alias (route) by clicking the link below:\n\n";
        $message .= $verification_url . "\n\n";
        $message .= "This link expires in 30 minutes.\n\n";
        $message .= "If you didn't request this, please ignore this email.";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Verify alias token
     */
    private function verify_alias_token($email, $token) {
        global $wpdb;
        
        $alias = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE alias_email = %s 
             AND is_verified = 0 
             AND token_expires_at > NOW()",
            $email
        ));
        
        if (!$alias) {
            return new \WP_Error('invalid_token', 'Invalid or expired verification token');
        }
        
        if (!wp_check_password($token, $alias->verification_token)) {
            return new \WP_Error('token_mismatch', 'Invalid verification token');
        }
        
        // Update as verified
        $wpdb->update(
            $this->table_name,
            [
                'is_verified' => 1,
                'verified_at' => current_time('mysql'),
                'verification_token' => null,
                'token_expires_at' => null
            ],
            ['id' => $alias->id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );
        
        $this->log_alias_activity($alias->user_id, 'alias_verified', $email);
        
        return true;
    }
    
    /**
     * Block registration with alias emails
     */
    public function block_alias_registration($errors, $sanitized_user_login, $user_email) {
        if ($this->is_alias_email($user_email)) {
            $errors->add('email_exists', 'This email is already registered as an alias (route). Please use a different email or contact support.');
        }
        
        return $errors;
    }
    
    /**
     * Check if email is an alias
     */
    public function is_alias_email($email) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE alias_email = %s",
            $email
        ));
        
        return (bool) $exists;
    }
    
    /**
     * Get user ID by alias email
     */
    public function get_user_by_alias($email) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name} 
             WHERE alias_email = %s AND is_verified = 1",
            $email
        ));
        
        return $user_id ? get_user_by('ID', $user_id) : false;
    }
    
    /**
     * Get user aliases
     */
    public function get_user_aliases($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT alias_email, is_verified, created_at, verified_at 
             FROM {$this->table_name} 
             WHERE user_id = %d 
             ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Rate limiting check
     */
    private function is_rate_limited($user_id) {
        $transient_key = "email_alias_rate_limit_{$user_id}";
        $attempts = get_transient($transient_key);
        
        if ($attempts >= 3) {
            return true;
        }
        
        set_transient($transient_key, ($attempts ? $attempts + 1 : 1), HOUR_IN_SECONDS);
        return false;
    }
    
    /**
     * Check if user owns alias
     */
    private function user_owns_alias($user_id, $email) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE user_id = %d AND alias_email = %s",
            $user_id, $email
        ));
        
        return (bool) $exists;
    }
    
    /**
     * Remove alias
     */
    private function remove_alias($user_id, $email) {
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $this->table_name,
            ['user_id' => $user_id, 'alias_email' => $email],
            ['%d', '%s']
        );
        
        if (!$deleted) {
            return new \WP_Error('delete_failed', 'Failed to remove alias');
        }
        
        $this->log_alias_activity($user_id, 'alias_removed', $email);
        return true;
    }
    
    /**
     * Check for disposable email domains
     */
    private function is_disposable_email($email) {
        $disposable_domains = [
            'tempmail.org', 'guerrillamail.com', 'mailinator.com',
            '10minutemail.com', 'throwaway.email', 'temp-mail.org'
        ];
        
        $domain = substr(strrchr($email, '@'), 1);
        return in_array(strtolower($domain), $disposable_domains);
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        $wpdb->delete(
            $this->table_name,
            ['is_verified' => 0, 'token_expires_at <' => current_time('mysql')],
            ['%d', '%s']
        );
    }
    
    /**
     * Log alias activities for security monitoring
     */
    private function log_alias_activity($user_id, $action, $email) {
        error_log("MultiEmailManager: User {$user_id} - {$action} - {$email}");
    }

    public function handle_add_alias_direct($user_id, $email) {
        $validation_result = $this->validate_alias_addition($user_id, $email);
        if (!is_wp_error($validation_result)) {
            $this->create_alias_with_verification($user_id, $email);
        }
    }

    public function handle_remove_alias_direct($user_id, $email) {
        if ($this->user_owns_alias($user_id, $email)) {
            $this->remove_alias($user_id, $email);
        }
    }

}