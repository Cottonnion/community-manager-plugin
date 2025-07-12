<?php
namespace LABGENZ_CM\Groups;

if ( ! defined( 'ABSPATH' ) ) exit;


class GroupMembersHandler
 {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Register AJAX handlers
        add_action( 'wp_ajax_lab_group_search_user', array( $this, 'search_user_ajax' ) );
        add_action( 'wp_ajax_lab_group_invite_user', array( $this, 'invite_user_ajax' ) );
        add_action( 'wp_ajax_lab_accept_invitation', array( $this, 'accept_invitation_ajax' ) );
        add_action( 'wp_ajax_lab_group_remove_member', array( $this, 'remove_member_ajax' ) );
        add_action( 'wp_ajax_lab_cancel_invitation', array( $this, 'cancel_invitation_ajax' ) );
        
        // Handle invitation acceptance from email links
        add_action( 'init', array( $this, 'handle_invitation_acceptance' ) );
        
        // Display success message after invitation acceptance
        add_action( 'bp_before_group_header', array( $this, 'display_invitation_success_message' ) );
        
        // Also run the script check on WooCommerce pages
        add_action( 'woocommerce_account_content', array( $this, 'display_invitation_success_message' ) );
    }

    /**
     * Display success message after invitation acceptance
     */
    public function display_invitation_success_message() {
        // Group invitation message (legacy support)
        if (isset($_GET['invitation_accepted']) && $_GET['invitation_accepted'] == '1' && !isset($_GET['change_password'])) {
            echo '<div class="bp-feedback success">
                    <span class="bp-icon" aria-hidden="true"></span>
                    <p>You have successfully joined this group.</p>
                  </div>';
        }
        
        // Add scripts for password change prompt
        add_action('wp_footer', array($this, 'add_password_change_script'));
    }
    
    /**
     * Add script to display SweetAlert password change prompt
     */
    public function add_password_change_script() {
        // Only show on WooCommerce edit account page with the correct parameters
        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }
        
        $show_alert = isset($_GET['invitation_accepted']) && $_GET['invitation_accepted'] == '1' 
                    && isset($_GET['change_password']) && $_GET['change_password'] == '1';
        
        if (!$show_alert) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Make sure SweetAlert is available
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Welcome to Your Account!',
                    html: 'Your account has been created and you have successfully joined the group. <br><br><strong>Please take a moment to set your password</strong> for future logins.',
                    icon: 'success',
                    confirmButtonText: 'Got it!',
                    confirmButtonColor: '#8B4513',
                    allowOutsideClick: false,
                    customClass: {
                        container: 'lab-welcome-alert'
                    }
                }).then((result) => {
                    // Scroll to password fields
                    const passwordField = document.getElementById('password_1');
                    if (passwordField) {
                        passwordField.focus();
                        passwordField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        
                        // Add highlight effect
                        $('.woocommerce-form-row--password').addClass('highlight-password-field');
                        setTimeout(function() {
                            $('.woocommerce-form-row--password').removeClass('highlight-password-field');
                        }, 2000);
                    }
                });
                
                // Add some styling for the highlighted fields
                $('<style>.highlight-password-field { animation: pulse-border 2s; } @keyframes pulse-border { 0% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(139, 69, 19, 0); } 100% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0); } }</style>').appendTo('head');
            }
        });
        </script>
        <?php
    }

    public function remove_member_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $group_id = intval( $_POST['group_id'] );
        $user_id = intval( $_POST['user_id'] );
        $current_user_id = get_current_user_id();
        
        if ( $user_id === $current_user_id ) {
            wp_send_json_error( 'You cannot remove yourself from the group' );
        }
        
        if ( groups_remove_member( $user_id, $group_id ) ) {
            wp_send_json_success( 'Member removed successfully' );
        } else {
            wp_send_json_error( 'Failed to remove member' );
        }
    }

    public function invite_user_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $group_id = intval( $_POST['group_id'] );
        $email = sanitize_email( $_POST['email'] );
        
        $is_organizer_raw = $_POST['is_organizer'] ?? 0;
        $is_organizer = ($is_organizer_raw == '1' || $is_organizer_raw === 1 || $is_organizer_raw === true);
        $role = $is_organizer ? 'organizer' : 'member';
        
        $user = get_user_by( 'email', $email );
        $user_id = $user ? $user->ID : null;

        // Generate a unique token for this invitation
        $token = wp_generate_password( 20, false );

        if ( ! $user ) {
            // Create new user - get names from POST data
            $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
            $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
            
            // If no names provided, try to extract from email
            if (empty($first_name) && empty($last_name)) {
                $email_parts = explode('@', $email);
                $first_name = ucfirst($email_parts[0]);
            }
            
            $username = $this->generate_unique_username( $email );
            $random_password = wp_generate_password();
            $user_id = wp_create_user( $username, $random_password, $email );
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( $user_id->get_error_message() );
            }
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );
            $user = get_user_by( 'id', $user_id );

        }

        // Check if user is already a member or has pending invitation
        if (groups_is_user_member($user->ID, $group_id)) {
            wp_send_json_error('User is already a member of this group');
        }

        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        if (is_array($invited_users) && isset($invited_users[$user->ID])) {
            wp_send_json_error('User already has a pending invitation');
        }

        // Store invitation in group meta with token and role
        if (!is_array($invited_users)) {
            $invited_users = array();
        }
        $invited_users[$user->ID] = array(
            'user_id' => $user->ID,
            'email' => $email,
            'role' => $role,
            'is_organizer' => $is_organizer,
            'invited_date' => current_time('mysql'),
            'status' => 'pending',
            'token' => $token
        );
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);
        
        // Send invitation email
        $this->send_group_invitation_email($user, $group_id, $is_organizer, $token);
        
        wp_send_json_success( array(
            'message' => "Invitation sent successfully to {$user->display_name}"
        ) );
    }

    public function accept_invitation_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'lab_group_management_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $group_id = intval($_POST['group_id']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('You must be logged in to accept invitations');
        }

        // Get invited users
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        
        if (!is_array($invited_users) || !isset($invited_users[$user_id])) {
            wp_send_json_error('No pending invitation found');
        }

        $invitation = $invited_users[$user_id];
        
        // Store role information before joining
        $is_organizer = isset($invitation['is_organizer']) && $invitation['is_organizer'];
        $role = isset($invitation['role']) ? $invitation['role'] : ($is_organizer ? 'organizer' : 'member');
        
        // Add user to group
        $join_result = groups_join_group($group_id, $user_id);
        
        if (!$join_result) {
            wp_send_json_error('Failed to join group');
        }
        
        // Give WordPress a moment to process the group join
        sleep(1);
        
        // If user was invited as organizer, promote them
        if ($is_organizer) {
            $promote_result = groups_promote_member($user_id, $group_id, 'admin');
            
            // If promotion failed, try direct database update as fallback
            if (!$promote_result) {
                global $wpdb, $bp;
                
                // Log the failure attempt
                error_log("Failed to promote user {$user_id} to admin in group {$group_id} via groups_promote_member");
                
                // Try direct database update as fallback
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$bp->groups->table_name_members} 
                    SET is_admin = 1, is_mod = 0 
                    WHERE user_id = %d AND group_id = %d",
                    $user_id, $group_id
                ));
                
                // Log the direct update attempt
                error_log("Attempted direct DB update to promote user {$user_id} to admin in group {$group_id}");
                
                // Clear group cache to ensure changes take effect
                groups_clear_group_object_cache($group_id);
            }
            
            // Sync with LearnDash - Add user as leader to associated LearnDash group
            $this->sync_learndash_leadership($user_id, $group_id);
        }

        // Remove from invited users
        unset($invited_users[$user_id]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);

        // Get WooCommerce account edit URL
        $edit_account_url = wc_get_endpoint_url('edit-account', '', wc_get_page_permalink('myaccount'));
        
        wp_send_json_success(array(
            'message' => 'Successfully joined the group',
            'redirect_url' => $edit_account_url,
            'show_password_alert' => true
        ));
    }

    public function cancel_invitation_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $group_id = intval( $_POST['group_id'] );
        $user_id = intval( $_POST['user_id'] );
        $current_user_id = get_current_user_id();
        
        // Check if current user is group admin
        if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
            wp_send_json_error( 'You do not have permission to cancel invitations' );
        }
        
        // Get invited users
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        
        if ( ! is_array($invited_users) || ! isset($invited_users[$user_id]) ) {
            wp_send_json_error( 'Invitation not found' );
        }
        
        // Remove the invitation
        unset($invited_users[$user_id]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);
        
        wp_send_json_success( 'Invitation cancelled successfully' );
    }

    public function search_user_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $email = sanitize_email( $_POST['email'] );
        $group_id = intval( $_POST['group_id'] );
        
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Invalid email address' );
        }

        // Validate group and user permissions
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error( 'You must be logged in to perform this action' );
        }

        // Check if user is an organizer of the target group
        if (!groups_is_user_admin($current_user_id, $group_id)) {
            wp_send_json_error( 'You do not have permission to manage this group' );
        }

        // Verify the group exists
        $group = groups_get_group($group_id);
        if (!$group || !$group->id) {
            wp_send_json_error( 'Invalid group specified' );
        }
        
        $user = get_user_by( 'email', $email );
        
        if ( $user ) {
            // User exists - check if already a member or invited
            $is_member = groups_is_user_member( $user->ID, $group_id );
            $is_admin = groups_is_user_admin( $user->ID, $group_id );
            $is_mod = groups_is_user_mod( $user->ID, $group_id );
            
            // Check if already invited
            $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
            $is_pending = is_array($invited_users) && isset($invited_users[$user->ID]);
            
            if ($is_member) {
                // Status 1: User is already in the group
                $response = array(
                    'status' => 'already_member',
                    'user_exists' => true,
                    'group_name' => $group->name,
                    'user_data' => array(
                        'ID' => $user->ID,
                        'display_name' => $user->display_name,
                        'email' => $user->user_email,
                        'avatar' => get_avatar_url( $user->ID, array( 'size' => 60 ) ),
                        'current_role' => $is_admin ? 'Administrator' : ($is_mod ? 'Moderator' : 'Member')
                    )
                );
            } elseif ($is_pending) {
                // User has pending invitation
                $response = array(
                    'status' => 'pending_invitation',
                    'user_exists' => true,
                    'group_name' => $group->name,
                    'user_data' => array(
                        'ID' => $user->ID,
                        'display_name' => $user->display_name,
                        'email' => $user->user_email,
                        'avatar' => get_avatar_url( $user->ID, array( 'size' => 60 ) )
                    )
                );
            } else {
                // Status 2: User exists but not in group - can invite
                $response = array(
                    'status' => 'can_invite',
                    'user_exists' => true,
                    'group_name' => $group->name,
                    'user_data' => array(
                        'ID' => $user->ID,
                        'display_name' => $user->display_name,
                        'email' => $user->user_email,
                        'avatar' => get_avatar_url( $user->ID, array( 'size' => 60 ) )
                    )
                );
            }
        } else {
            // Status 3: User doesn't exist - suggest creating and inviting
            $response = array(
                'status' => 'user_not_exists',
                'user_exists' => false,
                'group_name' => $group->name,
                'user_data' => array(
                    'email' => $email,
                    'display_name' => explode('@', $email)[0],
                    'avatar' => get_avatar_url( 0, array( 'size' => 60 ) ) // Default avatar
                )
            );
        }
        
        wp_send_json_success( $response );
    }

    /**
     * Handle invitation acceptance from email links
     */
    public function handle_invitation_acceptance() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'accept_invitation') {
            return;
        }

        $group_id = intval($_GET['group_id'] ?? 0);
        $email = sanitize_email($_GET['email'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$group_id || !$email || !$token) {
            wp_die('Invalid invitation link.');
        }

        // Get the user by email
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_die('User not found.');
        }

        // Get invited users and verify token
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        
        if (!is_array($invited_users) || !isset($invited_users[$user->ID])) {
            wp_die('Invitation not found or has expired.');
        }

        $invitation = $invited_users[$user->ID];
        
        if ($invitation['token'] !== $token) {
            wp_die('Invalid invitation token.');
        }

        // Check if user is already a member
        if (groups_is_user_member($user->ID, $group_id)) {
            // Redirect to group page with already member message
            $group_url = bp_get_group_permalink(groups_get_group($group_id));
            wp_redirect(add_query_arg('already_member', '1', $group_url));
            exit;
        }

        // DETERMINE ROLE FIRST - before any group operations
        $should_be_organizer = false;
        
        if (isset($invitation['role']) && $invitation['role'] === 'organizer') {
            $should_be_organizer = true;
        }
        
        if (isset($invitation['is_organizer']) && $invitation['is_organizer']) {
            $should_be_organizer = true;
        }
        
        // Auto-login the user if not logged in
        if (!is_user_logged_in()) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }

        // Add user to group
        $join_result = groups_join_group($group_id, $user->ID);
        
        if (!$join_result) {
            wp_die('Failed to join group. Please try again later.');
        }
        
        // Remove from invited users immediately after joining
        unset($invited_users[$user->ID]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);

        // If user was invited as organizer, promote them
        if ($should_be_organizer) {
            // Small delay to ensure the user is fully joined before promotion
            sleep(1);
            
            $promote_result = groups_promote_member($user->ID, $group_id, 'admin');
            
            // If promotion failed, try alternative method
            if (!groups_is_user_admin($user->ID, $group_id)) {
                // Try direct database update as fallback
                global $wpdb, $bp;
                $member_table = $bp->groups->table_name_members;
                
                $wpdb->update(
                    $member_table,
                    array('is_admin' => 1),
                    array(
                        'user_id' => $user->ID,
                        'group_id' => $group_id
                    ),
                    array('%d'),
                    array('%d', '%d')
                );
                
                // Clear group cache to ensure changes take effect
                wp_cache_delete($group_id, 'bp_groups_memberships_for_group');
                groups_clear_group_object_cache($group_id);
                
                error_log("Used direct DB update to promote user {$user->ID} to admin in group {$group_id}");
            }
            
            // Sync with LearnDash - Add user as leader to associated LearnDash group
            $this->sync_learndash_leadership($user->ID, $group_id);
        }

        // Get WooCommerce edit account URL
        $edit_account_url = '';
        if (function_exists('wc_get_page_permalink') && function_exists('wc_get_endpoint_url')) {
            $edit_account_url = wc_get_endpoint_url('edit-account', '', wc_get_page_permalink('myaccount'));
        } else {
            // Fallback to hardcoded URL if WooCommerce functions aren't available
            $edit_account_url = 'https://v2mlmmasteryclub.labgenz.com/my-account/edit-account/';
        }
        
        // Add parameter to indicate this is a new user from invitation
        $redirect_url = add_query_arg('invitation_accepted', '1', $edit_account_url);
        $redirect_url = add_query_arg('change_password', '1', $redirect_url);
        
        // Redirect to edit account page
        wp_redirect($redirect_url);
        exit;
    }

    private function generate_unique_username( $email ) {
        $base_username = explode( '@', $email )[0];
        $base_username = sanitize_user( $base_username );
        
        $username = $base_username;
        $counter = 1;
        
        while ( username_exists( $username ) ) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }

    private function send_group_invitation_email($user, $group_id, $is_organizer, $token) {
        $group = groups_get_group($group_id);
        $site_name = get_bloginfo('name');
        $role = $is_organizer ? 'organizer' : 'member';
        
        // Create acceptance URL with token
        $accept_url = add_query_arg(array(
            'action' => 'accept_invitation',
            'group_id' => $group_id,
            'email' => $user->user_email,
            'token' => $token
        ), home_url());
        
        $subject = sprintf('Invitation to join %s group on %s', $group->name, $site_name);
        
        $message = sprintf('
    Hello %s,

    You have been invited to join the group "%s" on %s as a %s.

    To accept this invitation, please click the following link:
    %s

    If you do not have an account, one has been created for you. You will be automatically logged in when you click the link.

    Best regards,
    The %s Team
            ', 
            $user->display_name,
            $group->name,
            $site_name,
            $role,
            $accept_url,
            $site_name
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Synchronize BuddyBoss organizer role with LearnDash group leader role
     * 
     * @param int $user_id  The user ID to add as a LearnDash group leader
     * @param int $bb_group_id The BuddyBoss group ID
     * @return bool Whether the synchronization was successful
     */
    private function sync_learndash_leadership($user_id, $bb_group_id) {
        // Make sure LearnDash is active
        if (!function_exists('learndash_get_groups_user_ids') || !function_exists('learndash_is_group_leader_user')) {
            error_log('LearnDash functions not available for sync_learndash_leadership');
            return false;
        }
        
        // Get the associated LearnDash group ID(s)
        $ld_group_id = $this->get_associated_ld_group_id($bb_group_id);
        if (!$ld_group_id) {
            error_log("No associated LearnDash group found for BuddyBoss group: {$bb_group_id}");
            return false;
        }
        
        // Check if user is already a leader of this group
        if (learndash_is_group_leader_user($user_id) && 
            learndash_is_user_in_group($user_id, $ld_group_id, true)) { // true = as leader
            // User is already a leader, no need to do anything
            return true;
        }
        
        // Add the user as a group leader
        $success = $this->add_user_as_ld_group_leader($user_id, $ld_group_id);
        
        if ($success) {
            error_log("Successfully added user {$user_id} as leader to LearnDash group {$ld_group_id}");
        } else {
            error_log("Failed to add user {$user_id} as leader to LearnDash group {$ld_group_id}");
        }
        
        return $success;
    }
    
    /**
     * Get the associated LearnDash group ID for a BuddyBoss group
     * 
     * @param int $bb_group_id The BuddyBoss group ID
     * @return int|false The LearnDash group ID or false if not found
     */
    private function get_associated_ld_group_id($bb_group_id) {
        // First check if there's a direct mapping stored in group meta
        $ld_group_id = groups_get_groupmeta($bb_group_id, 'learndash_group_id', true);
        
        if (!empty($ld_group_id) && is_numeric($ld_group_id)) {
            return intval($ld_group_id);
        }
        
        // If no direct mapping, check if we have BuddyBoss/LearnDash integration enabled
        // which typically stores this association somewhere
        
        // Check for BuddyBoss Platform Pro integration
        if (function_exists('bbp_pro_get_group_sync_settings')) {
            $sync_settings = bbp_pro_get_group_sync_settings($bb_group_id);
            if (!empty($sync_settings['ldg'])) {
                return intval($sync_settings['ldg']);
            }
        }
        
        // Check for Integration with LearnDash plugin
        // This is a common plugin to connect BuddyBoss with LearnDash
        $ld_group_id = get_post_meta($bb_group_id, '_sync_group_id', true);
        if (!empty($ld_group_id)) {
            return intval($ld_group_id);
        }
        
        // Try more generic approach - search for LearnDash groups with matching name
        $bb_group = groups_get_group($bb_group_id);
        if (empty($bb_group->name)) {
            return false;
        }
        
        // Query for LearnDash groups with similar name
        $args = array(
            'post_type'      => 'groups', // LearnDash group post type
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'title'          => $bb_group->name,
            'exact'          => true,
        );
        
        $matching_groups = get_posts($args);
        
        if (empty($matching_groups)) {
            return false;
        }
        
        // Store this mapping for future use
        groups_update_groupmeta($bb_group_id, 'learndash_group_id', $matching_groups[0]->ID);
        
        return $matching_groups[0]->ID;
    }
    
    /**
     * Add a user as a leader to a LearnDash group
     * 
     * @param int $user_id The user ID to add as leader
     * @param int $ld_group_id The LearnDash group ID
     * @return bool Whether the operation was successful
     */
    private function add_user_as_ld_group_leader($user_id, $ld_group_id) {
        // Make sure we have the required LearnDash functions
        if (!function_exists('learndash_set_groups_administrators')) {
            error_log('LearnDash function learndash_set_groups_administrators not available');
            return false;
        }
        
        // First, ensure the user has the group leader role
        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log("User not found: {$user_id}");
            return false;
        }
        
        // Add the group leader role if they don't have it already
        if (!in_array('group_leader', (array) $user->roles)) {
            $user->add_role('group_leader');
        }
        
        // Get current group administrators
        $current_admins = learndash_get_groups_administrator_ids($ld_group_id);
        
        // Add the new user to the administrators list
        if (!in_array($user_id, $current_admins)) {
            $current_admins[] = $user_id;
        }
        
        // Update the group administrators
        $result = learndash_set_groups_administrators($ld_group_id, $current_admins);
        
        // If the above method doesn't work (sometimes it doesn't), try the direct approach
        if (!$result) {
            // Try to use update_post_meta
            $meta_key = 'learndash_group_leaders_' . $ld_group_id;
            $success = update_user_meta($user_id, $meta_key, $ld_group_id);
            
            // Also try updating the group's list of leaders
            $leaders_meta_key = 'learndash_group_leaders';
            $current_leaders = get_post_meta($ld_group_id, $leaders_meta_key, true);
            
            if (!is_array($current_leaders)) {
                $current_leaders = array();
            }
            
            if (!in_array($user_id, $current_leaders)) {
                $current_leaders[] = $user_id;
                update_post_meta($ld_group_id, $leaders_meta_key, $current_leaders);
            }
            
            // Direct DB approach as last resort
            global $wpdb;
            $table = $wpdb->prefix . 'learndash_group_leaders';
            
            // Check if the entry already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND user_id = %d",
                $ld_group_id, $user_id
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $table,
                    array(
                        'group_id' => $ld_group_id,
                        'user_id' => $user_id
                    ),
                    array('%d', '%d')
                );
            }
            
            // Clear LearnDash caches
            if (function_exists('learndash_purge_user_group_cache')) {
                learndash_purge_user_group_cache($user_id);
            }
            
            return true;
        }
        
        return $result;
    }
}