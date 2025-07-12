<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class INST3D_Group_Management {
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
        add_action( 'wp_ajax_inst3d_group_search_user', array( $this, 'search_user_ajax' ) );
        add_action( 'wp_ajax_inst3d_group_invite_user', array( $this, 'invite_user_ajax' ) );
        add_action( 'wp_ajax_inst3d_group_remove_member', array( $this, 'remove_member_ajax' ) );
        add_action( 'wp_ajax_inst3d_accept_invitation', array( $this, 'accept_invitation_ajax' ) );
        
        // Add invitation acceptance handler
        add_action( 'bp_init', array( $this, 'handle_invitation_acceptance' ) );
        // add_action( 'bp_init', array( $this, 'handle_invitation_acceptance' ) );

        // Add action to handle user addition to BuddyBoss group and update LearnDash group access
// Replace the existing action hook with this simplified version
add_action('inst3d_user_added_to_buddyboss_group', function($user_id, $ld_group_id, $role) {
    // Open log file
    $log_file = fopen(plugin_dir_path(__FILE__) . '../logs/3dinst.log', 'a');
    fwrite($log_file, sprintf("%s: Action 'inst3d_user_added_to_buddyboss_group' triggered for user %d, LearnDash group %d, role %s\n", current_time('mysql'), $user_id, $ld_group_id, $role));

    if (!$ld_group_id || !$user_id) {
        fwrite($log_file, sprintf("%s: Invalid parameters - user_id: %d, ld_group_id: %d\n", current_time('mysql'), $user_id, $ld_group_id));
        fclose($log_file);
        return;
    }

    // Add user to LearnDash group as member first
    $current_users = learndash_get_groups_user_ids($ld_group_id);
    if (!in_array($user_id, $current_users)) {
        $current_users[] = $user_id;
        learndash_set_groups_users($ld_group_id, $current_users);
        fwrite($log_file, sprintf("%s: Added user %d to LearnDash group %d as member\n", current_time('mysql'), $user_id, $ld_group_id));
    }

    // If role is admin, add as group leader
    if ($role === 'admin') {
        $current_leaders = learndash_get_groups_administrator_ids($ld_group_id);
        if (!in_array($user_id, $current_leaders)) {
            $current_leaders[] = $user_id;
            learndash_set_groups_administrators($ld_group_id, $current_leaders);
            fwrite($log_file, sprintf("%s: Added user %d as administrator to LearnDash group %d\n", current_time('mysql'), $user_id, $ld_group_id));
        }
    }

    fclose($log_file);
}, 10, 3);
    }

    public function search_user_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'inst3d_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $email = sanitize_email( $_POST['email'] );
        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
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
            // Check if already a member
            $is_member = groups_is_user_member( $user->ID, $group_id );
            $is_admin = groups_is_user_admin( $user->ID, $group_id );
            $is_mod = groups_is_user_mod( $user->ID, $group_id );
            
            $response = array(
                'user_exists' => true,
                'user_data' => array(
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => get_avatar_url( $user->ID, array( 'size' => 60 ) ),
                    'is_member' => $is_member,
                    'is_admin' => $is_admin,
                    'is_mod' => $is_mod,
                    'current_role' => $is_admin ? 'Administrator' : ($is_mod ? 'Moderator' : ($is_member ? 'Member' : 'Not a member'))
                )
            );
        } else {
            // User doesn't exist, prepare for creation
            $response = array(
                'user_exists' => false,
                'user_data' => array(
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => trim( $first_name . ' ' . $last_name ) ?: explode('@', $email)[0],
                    'avatar' => get_avatar_url( 0, array( 'size' => 60 ) ) // Default avatar for new users
                )
            );
        }
        
        wp_send_json_success( $response );
    }

    public function invite_user_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'inst3d_group_management_nonce' ) ) {
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
            // Create new user
            $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
            $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
            $username = $this->generate_unique_username( $email );
            $random_password = wp_generate_password();
            $user_id = wp_create_user( $username, $random_password, $email );
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( $user_id->get_error_message() );
            }
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );
            $user = get_user_by( 'id', $user_id );
            // Optionally, send a welcome email with password
            $this->send_welcome_email( $user_id, $email, $username, $random_password );
        }

        // Store invitation in group meta with token and role
        $invited_users = groups_get_groupmeta($group_id, '3dinst_invited', true);
        if (!is_array($invited_users)) {
            $invited_users = array();
        }
        $invited_users[$user_id] = array(
            'user_id' => $user_id,
            'email' => $email,
            'role' => $role,
            'is_organizer' => $is_organizer,
            'invited_date' => current_time('mysql'),
            'status' => 'pending',
            'token' => $token
        );
        groups_update_groupmeta($group_id, '3dinst_invited', $invited_users);

        // Send invitation email with token
        $this->send_group_invitation_email($user, $group_id, $is_organizer, $token);
        
        wp_send_json_success( array(
            'message' => "Invitation sent successfully to {$user->display_name}"
        ) );
    }

    public function accept_invitation_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'inst3d_group_management_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $group_id = intval($_POST['group_id']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('You must be logged in to accept invitations');
        }

        // Get invited users
        $invited_users = groups_get_groupmeta($group_id, '3dinst_invited', true);
        
        if (!is_array($invited_users) || !isset($invited_users[$user_id])) {
            wp_send_json_error('No pending invitation found');
        }

        $invitation = $invited_users[$user_id];

        // Add user to group
        $join_result = groups_join_group($group_id, $user_id);
        
        if (!$join_result) {
            wp_send_json_error('Failed to join group');
        }

        // If user was invited as organizer, promote them
        if ($invitation['is_organizer']) {
            groups_promote_member($user_id, $group_id, 'admin');
            
            // Add as LearnDash group leader if applicable
            // if (function_exists('learndash_get_groups')) {
                $ld_groups = learndash_get_groups(true);
                foreach ($ld_groups as $ld_group) {
                    $group_id_to_check = is_object($ld_group) ? $ld_group->ID : $ld_group;
                    $bp_group_id = get_post_meta($group_id_to_check, '_bp_group_id', true);
                    
                    if (intval($bp_group_id) === $group_id) {
                        ld_update_leader_group_access($user_id, $group_id_to_check, true);
                        break;
                    }
                }
            // }
        }

        // Remove from invited users
        unset($invited_users[$user_id]);
        groups_update_groupmeta($group_id, '3dinst_invited', $invited_users);

        wp_send_json_success(array(
            'message' => 'Successfully joined the group'
        ));
    }

    public function remove_member_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'inst3d_group_management_nonce' ) ) {
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

    private function send_welcome_email( $user_id, $email, $username, $password ) {
        $user = get_user_by( 'ID', $user_id );
        $site_name = get_bloginfo( 'name' );
        $login_url = wp_login_url();
        
        $subject = sprintf( 'Welcome to %s - Your Account Details', $site_name );
        
        $message = sprintf( '
            Hello %s,

            Welcome to %s! An account has been created for you and you have been added to a group.

            Your login details are:
            Username: %s
            Email: %s
            Password: %s

            You can log in at: %s

            Please change your password after your first login for security.

            Best regards,
            The %s Team
        ', 
            $user->display_name,
            $site_name,
            $username,
            $email,
            $password,
            $login_url,
            $site_name
        );
        
        wp_mail( $email, $subject, $message );
    }

    private function send_group_invitation_email($user, $group_id, $is_organizer, $token) {
        $group = groups_get_group($group_id);
        $site_name = get_bloginfo('name');
        $group_url = bp_get_group_permalink($group);
        $role = $is_organizer ? 'organizer' : 'member';
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

    public function handle_invitation_acceptance() {
        // Prevent processing if already processed (avoid infinite loops)
        if (defined('INST3D_PROCESSING_INVITATION')) {
            return;
        }
        define('INST3D_PROCESSING_INVITATION', true);
    
        if (isset($_GET['action']) && $_GET['action'] === 'accept_invitation') {
            $group_id = intval($_GET['group_id']);
            $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
            if (!$group_id || !$email || !$token) {
                wp_die('Invalid invitation link - missing parameters');
            }
            
            // Verify group exists
            $group = groups_get_group($group_id);
            if (!$group || !$group->id) {
                wp_die('Group not found');
            }
            
            $invited_users = groups_get_groupmeta($group_id, '3dinst_invited', true);
            
            if (!is_array($invited_users)) {
                wp_die('No pending invitations found');
            }
            
            $user_id = null;
            $invitation = null;
            
            // Search for matching invitation by email and token
            foreach ($invited_users as $uid => $inv) {
                if (!isset($inv['email']) || !isset($inv['token'])) {
                    continue;
                }
                
                if ($inv['email'] === $email && $inv['token'] === $token) {
                    $user_id = $uid;
                    $invitation = $inv;
                    break;
                }
            }
            
            if (!$user_id || !$invitation) {
                wp_die('Invalid or expired invitation link. Please contact the group administrator for a new invitation.');
            }
            
            // Get user object
            $user = get_user_by('id', $user_id);
            if (!$user) {
                wp_die('User account not found. Please contact support.');
            }
            
            // Check if user is already a member
            if (groups_is_user_member($user->ID, $group_id)) {
                // Remove from invited users since they're already a member
                unset($invited_users[$user_id]);
                groups_update_groupmeta($group_id, '3dinst_invited', $invited_users);
                
                // Redirect to group page
                wp_safe_redirect(bp_get_group_permalink($group));
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
            
            // Log the user in if not already logged in
            if (!is_user_logged_in()) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);
            }
            
            // Add user to group
            $join_result = groups_join_group($group_id, $user->ID);
            if (!$join_result) {
                wp_die('Failed to join group. Please try again or contact support.');
            }
    
            // Remove from invited users immediately after joining
            unset($invited_users[$user_id]);
            groups_update_groupmeta($group_id, '3dinst_invited', $invited_users);
    
            // Promote to admin BEFORE firing the custom action
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
                }
            }
    
            // NOW fire custom action hook with correct role
            $ld_group_id = groups_get_groupmeta($group_id, '_sync_group_id', true);
            $role = $should_be_organizer ? 'admin' : 'member';
            do_action('inst3d_user_added_to_buddyboss_group', $user->ID, $ld_group_id, $role);
            
            // Redirect to group page with success message
            $redirect_url = add_query_arg('invitation_accepted', '1', bp_get_group_permalink($group));
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Helper function to check if a user is a LearnDash group leader
     */
    private function is_learndash_group_leader($user_id, $group_id) {
        // Method 1: Check using LearnDash function if available
        // if (function_exists('learndash_get_groups_administrators')) {
            $administrators = learndash_get_groups_administrators($group_id);
            if (is_array($administrators)) {
                foreach ($administrators as $admin) {
                    $admin_id = is_object($admin) ? $admin->ID : $admin;
                    if (intval($admin_id) === intval($user_id)) {
                        return true;
                    }
                }
            }
        // }
        
        // Method 2: Check meta directly
        $group_leaders = get_post_meta($group_id, 'learndash_group_leaders', true);
        if (is_array($group_leaders) && in_array($user_id, array_map('intval', $group_leaders))) {
            return true;
        }
        
        // Method 3: Check alternative meta key
        $group_admins = get_post_meta($group_id, 'learndash_group_administrators', true);
        if (is_array($group_admins) && in_array($user_id, array_map('intval', $group_admins))) {
            return true;
        }
        
        // Method 4: Check user meta
        $user_leader_groups = get_user_meta($user_id, 'learndash_group_leaders', true);
        if (is_array($user_leader_groups) && in_array($group_id, array_map('intval', $user_leader_groups))) {
            return true;
        }
        
        return false;
    }
}

// Initialize the class
INST3D_Group_Management::get_instance();