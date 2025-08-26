<?php
namespace LABGENZ_CM\Core\Authentication\Profile;

// defined('ABSPATH') || exit;

/**
 * WpProfile
 *
 * Loads the alias emails template in the WordPress admin profile page.
 */
class WpProfile {

    /**
     * Register hooks to include template in user profile
     */
    public function __construct() {
        add_action('show_user_profile', [$this, 'load_template']);
        add_action('edit_user_profile', [$this, 'load_template']);
    }

    /**
     * Include the alias emails template
     *
     * @param \WP_User $user
     */
    public function load_template( $user ) {
        require_once LABGENZ_CM_TEMPLATES_DIR . '/wp-profile/alias-emails.php';
        // display_alias_emails_admin_profile($user);
    }
}
