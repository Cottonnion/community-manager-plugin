<?php
// namespace LABGENZ_CM\Groups;

if ( ! defined( 'ABSPATH' ) ) exit;

class GroupMembersHandler
{
    private static $instance = null;
    private $log_file = '';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Set up log file path
        $upload_dir = wp_upload_dir();
        $this->log_file = plugin_dir_path(dirname(__FILE__)) . '../logs/invitation_log.txt';
        
        // Create logs directory if it doesn't exist
        $logs_dir = dirname($this->log_file);
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        $this->init_hooks();
    }
    
    /**
     * Log a message to the plugin's log file
     *
     * @param string $message The message to log
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $level = 'info') {
        return; // Disable logging for now
        global $inst3d_logger;
        
        if ($inst3d_logger) {
            $inst3d_logger->log($message, $level);
        } else {
            // Fallback if logger is not initialized
            $timestamp = date('[Y-m-d H:i:s] ');
            $log_message = $timestamp . "[{$level}] " . $message . "\n";
            error_log($log_message);
            
            if (isset($this->log_file)) {
                file_put_contents($this->log_file, $log_message, FILE_APPEND);
            }
        }
    }

    private function init_hooks() {
        // Register AJAX handlers
        add_action( 'wp_ajax_lab_group_search_user', array( $this, 'search_user_ajax' ) );
        add_action( 'wp_ajax_lab_group_invite_user', array( $this, 'invite_user_ajax' ) );
        add_action( 'wp_ajax_lab_accept_invitation', array( $this, 'accept_invitation_ajax' ) );
        add_action( 'wp_ajax_lab_group_remove_member', array( $this, 'remove_member_ajax' ) );
        add_action( 'wp_ajax_lab_cancel_invitation', array( $this, 'cancel_invitation_ajax' ) );
        add_action( 'wp_ajax_lab_resend_invitation', array($this, 'resend_invitation_ajax'));
        
        // Handle invitation acceptance from email links
        add_action( 'bp_init', array( $this, 'handle_invitation_acceptance' ) );
        
        // Display success message after invitation acceptance
        add_action( 'bp_before_group_header', array( $this, 'display_invitation_success_message' ) );
        
        // Also run the script check on WooCommerce pages
        add_action( 'bp_init', array( $this, 'display_invitation_success_message' ) );

        // Enforce password change for users with changed_password = 0
        add_action('template_redirect', function() {
            if (!is_user_logged_in()) return;
            $user_id = get_current_user_id();
            $changed = get_user_meta($user_id, 'changed_password', true);
            // Allow on password reset page
            $is_reset = false;
            if (isset($_GET['action']) && $_GET['action'] === 'lostpassword') $is_reset = true;
            if (strpos($_SERVER['REQUEST_URI'], 'lostpassword') !== false) $is_reset = true;
            if ($changed === '0' && !$is_reset) {
                // Redirect to password reset page with email param
                $user = get_userdata($user_id);
                $redirect_url = add_query_arg(array(
                    'action' => 'lostpassword',
                    'force_password' => '1',
                    'email' => urlencode($user->user_email)
                ), site_url('wp-login.php'));
                wp_redirect($redirect_url);
                exit;
            }
        });

        // Set changed_password to 1 when user changes password and auto-login
        add_action('after_password_reset', function($user) {
            if ($user && $user->ID) {
                update_user_meta($user->ID, 'changed_password', 1);
                // Auto-login after password reset
                wp_set_auth_cookie($user->ID);
                wp_set_current_user($user->ID);
                do_action('wp_login', $user->user_login, $user);
            }
        });
    }

    /**
     * Prevent duplicate AJAX requests within a short time window
     */
    private function prevent_duplicate_request($action, $group_id, $user_id = null) {
        return;
        $request_key = $action . '_' . $group_id . '_' . ($user_id ? $user_id : get_current_user_id());
        $transient_key = 'lab_request_' . md5($request_key);
        
        // Check if this request was made recently (within 3 seconds)
        if (get_transient($transient_key)) {
            wp_send_json_error('Request in progress. Please wait before trying again.');
        }
        
        // Set transient to prevent duplicate requests
        set_transient($transient_key, true, 3);
    }

    /**
     * Centralized method to update available seats with logging
     */
    private function update_available_seats($group_id, $change, $action = '') {
        return;
        // Only update seats for normal seat type groups
        if (groups_get_groupmeta($group_id, 'seats_type', true) !== 'normal') {
            error_log("SEAT_SKIP: Group {$group_id} - Action: {$action} - Not normal seat type");
            return;
        }

        $current_seats = (int) groups_get_groupmeta($group_id, 'available_seats', true);
        $new_seats = max(0, $current_seats + $change); // Prevent negative seats
        
        // Log the change for debugging
        error_log("SEAT_CHANGE: Group {$group_id} - Action: {$action} - Current: {$current_seats} - Change: {$change} - New: {$new_seats}");
        
        groups_update_groupmeta($group_id, 'available_seats', $new_seats);
        return $new_seats;
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
        add_action('login_footer', array($this, 'add_password_change_script'));
    }
    
    /**
     * Add script to redirect to password reset page
     */
    public function add_password_change_script() {
        $show_alert = isset($_GET['invitation_accepted']) && $_GET['invitation_accepted'] == '1' 
                    && isset($_GET['change_password']) && $_GET['change_password'] == '1';
        
        // Always add the script if we're on login page or if we have invitation parameters
        $is_login_page = (isset($_GET['action']) && $_GET['action'] == 'lostpassword') ||
                        isset($_GET['invitation_accepted']) ||
                        isset($_GET['change_password']);
        
        if (!$show_alert && !$is_login_page) {
            return;
        }
        
        // Get the email from the current user (for redirect scenario)
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        
        // Build the redirect URL with all parameters (for redirect scenario)
        $redirect_url = add_query_arg(array(
            'action' => 'lostpassword',
            'invitation_accepted' => '1',
            'change_password' => '1',
            'email' => urlencode($email)
        ), 'https://3dinstitute.labgenz.com/wp-login.php');
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Password change script loaded');
            console.log('Current URL:', window.location.href);
            
            // Check if we're on the right page and should redirect
            const shouldRedirect = <?php echo json_encode($show_alert); ?>;
            const redirectUrl = <?php echo json_encode($redirect_url); ?>;
            const userEmail = <?php echo json_encode($email); ?>;
            
            // Check if we're already on the password reset page
            const isOnPasswordResetPage = window.location.href.indexOf('wp-login.php') !== -1 && 
                                        window.location.href.indexOf('action=lostpassword') !== -1;
            
            if (shouldRedirect && !isOnPasswordResetPage) {
                console.log('Should redirect to password reset page');
                // Show welcome message with SweetAlert if available, then redirect
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Welcome to Your Account!',
                        html: 'You have successfully joined the group. <br><br><strong>You will now be redirected to set your password.</strong>',
                        icon: 'success',
                        confirmButtonText: 'Set Password',
                        confirmButtonColor: '#8B4513',
                        allowOutsideClick: false,
                        customClass: {
                            container: 'lab-welcome-alert'
                        }
                    }).then((result) => {
                        window.location.href = redirectUrl;
                    });
                } else {
                    // Fallback if SweetAlert is not available
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 2000);
                }
            }
            
            // If we're on the password reset page, auto-fill the email
            if (window.location.href.indexOf('wp-login.php') !== -1 && 
                window.location.href.indexOf('action=lostpassword') !== -1) {
                console.log('Detected password reset page');
                const urlParams = new URLSearchParams(window.location.search);
                const emailParam = urlParams.get('email');
                console.log('Email parameter from URL:', emailParam);
                
                if (emailParam) {
                    console.log('Email parameter found, setting timeout');
                    // Wait 2000ms before auto-filling the email
                    setTimeout(function() {
                        const userLoginField = document.getElementById('user_login');
                        console.log('User login field:', userLoginField);
                        console.log('Auto-filling email:', decodeURIComponent(emailParam));
                        if (userLoginField) {
                            userLoginField.value = decodeURIComponent(emailParam);
                            console.log('Email field value set to:', userLoginField.value);
                            
                            // Add a welcome message above the form
                            const form = userLoginField.closest('form');
                            if (form && urlParams.get('invitation_accepted') === '1') {
                                console.log('Adding welcome message');
                                const welcomeDiv = document.createElement('div');
                                welcomeDiv.style.cssText = 'background: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; padding: 15px; margin-bottom: 20px; border-radius: 4px;';
                                welcomeDiv.innerHTML = '<strong>Welcome!</strong> You have successfully joined the group. Please request your reset link to complete your account setup.';
                                form.parentNode.insertBefore(welcomeDiv, form);
                            }
                        } else {
                            console.log('User login field not found');
                        }
                    }, 2000);
                } else {
                    console.log('No email parameter found in URL');
                }
            } else {
                console.log('Not on password reset page');
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
        
        // Prevent duplicate requests
        $this->prevent_duplicate_request('remove_member', $group_id, $user_id);
        
        if ( $user_id === $current_user_id ) {
            wp_send_json_error( 'You cannot remove yourself from the group' );
        }
        
        if ( groups_remove_member( $user_id, $group_id ) ) {
            // Only update seats after successful removal
            $this->update_available_seats($group_id, 1, 'member_removed');
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
        
        // Prevent duplicate requests
        $this->prevent_duplicate_request('invite_user', $group_id);
        
        // Check available seats before processing
        $seats = (int) groups_get_groupmeta($group_id, 'available_seats', true);
        $current_members_count = $this->get_total_group_members_count($group_id);
        
        if ($current_members_count >= $seats) {
            $invited = groups_get_groupmeta( $group_id, 'lab_invited', true );
            wp_send_json_error(array(
                'message' => ['No available seats to invite, please contact the group administrator for more seats.'],
                'current_members_count' => $current_members_count,
                'available_seats' => $seats,
                'invited' => $invited ? count($invited) : 0
            ));
        }
        
        $is_organizer_raw = $_POST['is_organizer'] ?? 0;
        $is_organizer = ($is_organizer_raw == '1' || $is_organizer_raw === 1 || $is_organizer_raw === true);
        
        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
        
        $result = $this->create_group_invitation($group_id, $email, $is_organizer, $first_name, $last_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => "Invitation sent successfully to {$result['user']->display_name}"
        ));
    }

    function get_total_group_members_count($group_id) {
        global $wpdb;

        $users_table   = $wpdb->users;
        $members_table = $wpdb->prefix . 'bp_groups_members';

        // Count confirmed group members
        $confirmed_count = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*) FROM $members_table m
                INNER JOIN $users_table u ON m.user_id = u.ID
                WHERE m.group_id = %d
                AND m.is_confirmed = 1
                AND u.user_status = 0
                ",
                $group_id
            )
        );

        // Get invited users from groupmeta
        $invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );
        if ( ! is_array( $invited_users ) ) {
            $invited_users = array();
        }

        // Total = confirmed + invited
        return (int) $confirmed_count + count( $invited_users );
    }

        public function accept_invitation_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'lab_group_management_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $group_id = intval($_POST['group_id']);
        $user_id = get_current_user_id();

        // Prevent duplicate requests
        $this->prevent_duplicate_request('accept_invitation', $group_id, $user_id);

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
        
        // PRE-CHECK LEARNDASH ENROLLMENT CAPABILITY
        $this->log("AJAX INVITATION: Pre-checking LearnDash enrollment capability for user {$user_id}", 'info');
        
        // FIRST ENROLL IN LEARNDASH COURSES
        // Do this before joining the group so we can bail out if it fails
        $enrollment_result = $this->auto_enroll_user_in_group_courses($user_id, $group_id);
        
        // Log enrollment results
        if (!$enrollment_result['success']) {
            $error_message = "Course enrollment failed or no courses found for user {$user_id}: " . $enrollment_result['message'];
            $this->log("AJAX INVITATION ERROR: " . $error_message, 'error');
            wp_send_json_error($error_message);
            return;
        }
        
        $this->log("AJAX INVITATION: Successfully enrolled user {$user_id} in " . count($enrollment_result['enrolled_courses']) . " courses", 'info');
        
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
                    "UPDATE {$wpdb->prefix}bp_groups_members 
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
        $this->log("AJAX INVITATION: Removing user {$user_id} from invited users list for group {$group_id}", 'info');
        unset($invited_users[$user_id]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);

        // Note: No seat adjustment here since accepting invitation doesn't change total count
        // The seat was already decremented when invitation was created

        // Get WooCommerce account edit URL
        $edit_account_url = wc_get_endpoint_url('my-account', '', wc_get_page_permalink('myaccount'));
        
        $this->log("AJAX INVITATION SUCCESS: User {$user_id} successfully processed invitation for group {$group_id}", 'info');
        
        wp_send_json_success(array(
            'message' => 'Successfully joined the group',
            'redirect_url' => $edit_account_url,
            'show_password_alert' => true,
            'courses_enrolled' => $enrollment_result['enrolled_courses']
        ));
        
        wp_send_json_success(array(
            'message' => 'Successfully joined the group',
            'redirect_url' => $edit_account_url,
            'show_password_alert' => true,
            'courses_enrolled' => $enrollment_result['enrolled_courses']
        ));
    }

    public function cancel_invitation_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }

        $group_id = intval( $_POST['group_id'] );
        $user_id = intval( $_POST['user_id'] );
        $current_user_id = get_current_user_id();

        // Prevent duplicate requests
        $this->prevent_duplicate_request('cancel_invitation', $group_id, $user_id);

        if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
            wp_send_json_error( 'You do not have permission to cancel invitations' );
        }

        $invited_users = groups_get_groupmeta( $group_id, 'lab_invited', true );

        if ( ! is_array( $invited_users ) || ! isset( $invited_users[ $user_id ] ) ) {
            wp_send_json_error( 'Invitation not found' );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_send_json_error( 'User not found' );
        }

        // Email before removal
        $this->send_invitation_cancellation_email( $user, $group_id, $invited_users[ $user_id ] );

        // Remove invitation first
        unset( $invited_users[ $user_id ] );
        groups_update_groupmeta( $group_id, 'lab_invited', $invited_users );

        // Then update available seats (only for normal seat type)
        $this->update_available_seats($group_id, 1, 'invitation_cancelled');

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

    public function resend_invitation_ajax() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'lab_group_management_nonce' ) ) {
            wp_send_json_error( 'Security check failed' );
        }
        
        $group_id = intval( $_POST['group_id'] );
        $user_id = intval( $_POST['user_id'] );
        $current_user_id = get_current_user_id();
        
        // Prevent duplicate requests
        $this->prevent_duplicate_request('resend_invitation', $group_id, $user_id);
        
        // Check if current user is group admin
        if ( ! groups_is_user_admin( $current_user_id, $group_id ) ) {
            wp_send_json_error( 'You do not have permission to resend invitations' );
        }
        
        // Get invited users
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        
        if ( ! is_array($invited_users) || ! isset($invited_users[$user_id]) ) {
            wp_send_json_error( 'Invitation not found' );
        }
        
        $invitation = $invited_users[$user_id];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error( 'User not found' );
        }
        
        // Generate a new token for security
        $new_token = wp_generate_password( 20, false );
        
        // Update the invitation with new token and timestamp
        $invited_users[$user_id]['token'] = $new_token;
        $invited_users[$user_id]['invited_date'] = current_time('mysql');
        $invited_users[$user_id]['resent_count'] = isset($invitation['resent_count']) ? $invitation['resent_count'] + 1 : 1;
        
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);
        
        // Send the invitation email
        $is_organizer = isset($invitation['is_organizer']) ? $invitation['is_organizer'] : false;
        $this->send_group_invitation_email($user, $group_id, $is_organizer, $new_token, true);
        
        wp_send_json_success( array(
            'message' => "Invitation resent successfully to {$user->display_name}"
        ) );
    }

    
    /**
     * Handle invitation acceptance from email links
     */
    public function handle_invitation_acceptance() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'accept_invitation') {
            return;
        }

        $group_id = intval($_GET['group_id'] ?? 0);
        $email_raw = $_GET['email'] ?? '';
        $email = sanitize_email(urldecode($email_raw));
        $token = sanitize_text_field($_GET['token'] ?? '');

        // Enhanced logging with detailed request information
        $this->log("Invitation acceptance attempt - REQUEST DATA:", 'info');
        $this->log("Group ID: $group_id", 'info');
        $this->log("Email (raw): $email_raw", 'info');
        $this->log("Email (decoded): $email", 'info');
        $this->log("Token: $token", 'info');
        $this->log("All GET params: " . json_encode($_GET), 'info');
        
        if (!$group_id || !$email || !$token) {
            $this->log("Missing required parameters - group_id: $group_id, email: $email, token: $token", 'error');
            wp_die('Invalid invitation link. Missing required parameters.');
        }

        // Get the user by email
        $user = get_user_by('email', $email_raw);
        if (!$user) {
            $this->log("No user found with email: $email", 'error');
            
            // Check if there are any similar emails in the system (for troubleshooting)
            global $wpdb;
            $similar_users = $wpdb->get_results($wpdb->prepare("SELECT ID, user_email FROM {$wpdb->users} WHERE user_email LIKE %s", '%' . $wpdb->esc_like(explode('@', $email)[1]) . '%'));
            
            if (!empty($similar_users)) {
                $this->log("Found similar emails: " . json_encode($similar_users), 'info');
            }
            
            wp_die("User not found for email: $email - Please contact the administrator.");
        }
        
        $this->log("User found: ID {$user->ID}, Display Name: {$user->display_name}, Email: {$user->user_email}", 'info');

        // Get invited users and verify token
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        
        // Debug invited users
        if (!is_array($invited_users)) {
            $this->log("No invited users array for group ID: $group_id", 'error');
            
            // Check if group exists
            $group = groups_get_group($group_id);
            if (!$group || !$group->id) {
                $this->log("Group with ID $group_id does not exist!", 'error');
            } else {
                $this->log("Group $group_id exists with name: {$group->name}", 'info');
                
                // Check all group meta
                $all_meta = groups_get_groupmeta($group_id);
                $this->log("All group meta keys: " . json_encode(array_keys($all_meta)), 'info');
            }
            
            wp_die('No invitations exist for this group. Please contact the administrator.');
        } else {
            $this->log("Group $group_id has " . count($invited_users) . " invited users", 'info');
            $this->log("Invited user IDs: " . implode(', ', array_keys($invited_users)), 'info');
            $this->log("Full invited users data: " . json_encode($invited_users), 'info');
        }
        
        if (!isset($invited_users[$user->ID])) {
            $this->log("User ID {$user->ID} not found in invited users for group $group_id", 'error');
            
            // Check for email mismatch in invitations
            foreach ($invited_users as $invited_user_id => $invitation) {
                if (isset($invitation['email']) && $invitation['email'] === $email) {
                    $this->log("Found matching email but different user ID: invited ID $invited_user_id vs current user ID {$user->ID}", 'warning');
                }
            }
            
            wp_die('Your invitation was not found or has expired. Please contact the group administrator for a new invitation.');
        }

        $invitation = $invited_users[$user->ID];
        $this->log("Invitation found for user ID {$user->ID}", 'info');
        $this->log("Stored invitation data: " . json_encode($invitation), 'info');
        $this->log("Token in DB: {$invitation['token']}, Provided token: $token", 'info');
        
        if ($invitation['token'] !== $token) {
            $this->log("Token mismatch for user ID {$user->ID}", 'error');
            wp_die('Invalid invitation token. Please use the exact link from your invitation email.');
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
        
        // PRE-CHECK LEARNDASH ENROLLMENT CAPABILITY BEFORE JOINING
        // This prevents having to recreate invitations if enrollment fails
        $this->log("INVITATION: Pre-checking LearnDash enrollment capability for user {$user->ID} in group {$group_id}", 'info');
        
        // Check if LearnDash group exists first
        global $wpdb;
        $ld_group_id = get_post_meta($group_id, '_sync_group_id', true);
        if (empty($ld_group_id)) {
            $ld_group_id = groups_get_groupmeta($group_id, '_sync_group_id', true);
        }
        if (empty($ld_group_id)) {
            $ld_group_id = groups_get_groupmeta($group_id, 'learndash_group_id', true);
        }
        
        if (empty($ld_group_id)) {
            $this->log("INVITATION ERROR: No LearnDash group found for BuddyBoss group {$group_id}", 'error');
            
            // Get group name for better error reporting
            $group_obj = groups_get_group($group_id);
            $group_name = $group_obj ? $group_obj->name : 'Unknown';
            
            $error_message = "Course enrollment not available: No LearnDash group found for '{$group_name}' group. Please contact the administrator.";
            $this->log($error_message, 'error');
            wp_die($error_message);
        }
        
        // Check if courses exist in the LearnDash group
        $this->log("INVITATION: Checking for courses in LearnDash group {$ld_group_id}", 'info');
        
        $meta_key = 'learndash_group_enrolled_' . $ld_group_id;

        // Get courses associated with this LearnDash group using the correct meta key pattern
        $course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE %s 
            AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses')",
            $meta_key
        ));
        
        if (empty($course_ids)) {
            $this->log("INVITATION ERROR: No courses found in LearnDash group {$ld_group_id}", 'error');
            
            // Get LearnDash group name for better error reporting
            $ld_group = get_post($ld_group_id);
            $ld_group_name = $ld_group ? $ld_group->post_title : 'Unknown';
            
            $error_message = "Course enrollment not available: No courses found in LearnDash group '{$ld_group_name}'. and id is '{$ld_group_id}'  and meta is {$meta_key} Please contact the administrator.";
            $this->log($error_message, 'error');
            wp_die($error_message);
        }
        
        $this->log("INVITATION: Pre-check successful. Found LearnDash group {$ld_group_id} with " . count($course_ids) . " courses", 'info');
        
        // Auto-login the user if not logged in
        if (!is_user_logged_in()) {
            $this->log("INVITATION: Auto-logging in user {$user->ID} ({$user->user_email})", 'info');
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }

        // FIRST ENROLL IN LEARNDASH COURSES
        // Do this before joining the group so we can bail out if it fails
        $this->log("INVITATION: Enrolling user {$user->ID} in LearnDash courses before joining group", 'info');
        $enrollment_result = $this->auto_enroll_user_in_group_courses($user->ID, $group_id);
        
        // Log enrollment results
        if ($enrollment_result['success']) {
            $this->log("INVITATION SUCCESS: Successfully enrolled user {$user->ID} in " . count($enrollment_result['enrolled_courses']) . " courses from email invitation", 'info');
            
            // Detailed enrollment log
            foreach ($enrollment_result['enrolled_courses'] as $course) {
                $this->log("INVITATION: Enrolled in course ID: {$course['course_id']}, Title: {$course['course_title']}", 'info');
            }
        } else {
            $error_message = "Course enrollment failed for user {$user->ID} from email invitation: " . $enrollment_result['message'];
            $this->log("INVITATION ERROR: " . $error_message, 'error');
            
            // If we have LD group ID but enrollment failed, provide more detailed error
            if (!empty($enrollment_result['ld_group_id'])) {
                wp_die("Course enrollment failed. Please contact support with this error message. User ID: {$user->ID}, BuddyBoss Group: {$group_id}, LearnDash Group: {$enrollment_result['ld_group_id']}");
            } else {
                wp_die("Course enrollment failed. Please contact support.");
            }
        }
        
        // NOW PROCEED WITH GROUP JOINING
        $this->log("INVITATION: Now adding user {$user->ID} to BuddyBoss group {$group_id}", 'info');
        $join_result = groups_join_group($group_id, $user->ID);
        
        if (!$join_result) {
            $this->log("INVITATION ERROR: Failed to join group {$group_id} for user {$user->ID}", 'error');
            wp_die('Failed to join group. Please try again later.');
        }
        
        // Remove from invited users immediately after joining
        $this->log("INVITATION: Removing user {$user->ID} from invited users list for group {$group_id}", 'info');
        unset($invited_users[$user->ID]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);

        // If user was invited as organizer, promote them
        if ($should_be_organizer) {
            $this->log("INVITATION: Promoting user {$user->ID} to organizer in group {$group_id}", 'info');
            
            // Small delay to ensure the user is fully joined before promotion
            sleep(1);
            
            $promote_result = groups_promote_member($user->ID, $group_id, 'admin');
            
            // If promotion failed, try alternative method
            if (!groups_is_user_admin($user->ID, $group_id)) {
                $this->log("INVITATION WARNING: Standard promotion failed, trying direct DB update", 'warning');
                
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
                
                $this->log("INVITATION: Used direct DB update to promote user {$user->ID} to admin in group {$group_id}", 'info');
            }
            
            // Sync with LearnDash - Add user as leader to associated LearnDash group
            $this->log("INVITATION: Syncing leadership for user {$user->ID} with LearnDash", 'info');
            $sync_result = $this->sync_learndash_leadership($user->ID, $group_id);
            $this->log("INVITATION: Leadership sync result: " . ($sync_result ? "Success" : "Failed"), 'info');
        }

        // Successfully processed invitation - log for debugging
        $this->log("INVITATION SUCCESS: Full invitation process completed for user ID {$user->ID}, email {$user->user_email}, group {$group_id}", 'info');
        
        // Generate a password reset key and redirect user directly to the reset form
        $reset_key = get_password_reset_key($user);
        $reset_url = add_query_arg(array(
            'action' => 'rp',
            'key' => $reset_key,
            'login' => rawurlencode($user->user_login),
            'redirect_to' => urlencode(home_url('/dashboard'))
        ), wp_login_url());
        
        // Optionally, you can add a welcome or info message via query param
        //$reset_url = add_query_arg('invitation_accepted', '1', $reset_url);
        
        $this->log("INVITATION: Redirecting user to password reset page", 'info');
        wp_redirect($reset_url);
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

    private function send_group_invitation_email($user, $group_id, $is_organizer, $token, $is_resend = false) {
        $group = groups_get_group($group_id);
        $site_name = get_bloginfo('name');
        $role = $is_organizer ? 'organizer' : 'member';
        
        // Create acceptance URL with all required parameters properly encoded
        $accept_url = add_query_arg(array(
            'action' => 'accept_invitation',
            'group_id' => $group_id,
            'email' => urlencode($user->user_email),
            'token' => $token
        ), home_url());
        
        // Log the generated URL for debugging
        $this->log("Generated invitation URL for {$user->user_email}: $accept_url");
        
        $subject_prefix = $is_resend ? 'Reminder: ' : '';
        $subject = sprintf('%sInvitation to join %s group on %s', $subject_prefix, $group->name, $site_name);
        
        $greeting_text = $is_resend ? 'This is a reminder that you' : 'You';
        
        $message = sprintf('
            Hello %s,

            %s have been invited to join the group "%s" on %s as a %s.

            To accept this invitation, please click the following link:
            %s

            If you do not have an account, one has been created for you. You will be automatically logged in when you click the link.

            Best regards,
            The %s Team
            ', 
            $user->display_name,
            $greeting_text,
            $group->name,
            $site_name,
            $role,
            $accept_url,
            $site_name
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send invitation cancellation email
     * 
     * @param WP_User $user The user whose invitation is being cancelled
     * @param int $group_id The group ID
     * @param array $invitation The invitation data
     * @return bool Whether the email was sent successfully
     */
    private function send_invitation_cancellation_email($user, $group_id, $invitation) {
        $group = groups_get_group($group_id);
        $site_name = get_bloginfo('name');
        $role = isset($invitation['is_organizer']) && $invitation['is_organizer'] ? 'organizer' : 'member';
        
        $subject = sprintf('Invitation to join %s group has been cancelled', $group->name);
        
        $message = sprintf('
            Hello %s,

            We wanted to let you know that your invitation to join the group "%s" on %s as a %s has been cancelled.

            If you believe this is an error or have any questions, please contact the group organizer.

            Best regards,
            The %s Team
            ', 
            $user->display_name,
            $group->name,
            $site_name,
            $role,
            $site_name
        );
        
        return wp_mail($user->user_email, $subject, $message);
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
     * Auto-enroll user in all courses from the associated LearnDash group
     * 
     * @param int $user_id The user ID to enroll
     * @param int $bb_group_id The BuddyBoss group ID
     * @return array Array with success status, message, and enrolled courses
     */
    private function auto_enroll_user_in_group_courses($user_id, $bb_group_id) {
        global $wpdb;
        
        // Get the LearnDash group ID using _sync_group_id meta
        $ld_group_id = get_post_meta($bb_group_id, '_sync_group_id', true);
        
        // If not found, try the BuddyBoss groupmeta table
        if (empty($ld_group_id)) {
            $ld_group_id = groups_get_groupmeta($bb_group_id, '_sync_group_id', true);
        }
        
        // Try learndash_group_id as fallback
        if (empty($ld_group_id)) {
            $ld_group_id = groups_get_groupmeta($bb_group_id, 'learndash_group_id', true);
        }
        
        if (empty($ld_group_id)) {
            $group = groups_get_group($bb_group_id);
            $group_name = $group ? $group->name : 'Unknown';
            wp_die("Course enrollment error: No LearnDash group found for BuddyBoss group {$bb_group_id} ({$group_name}). Please contact support.");
        }
        
        // Get courses associated with this LearnDash group using the correct meta key pattern
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses')",
            'learndash_group_enrolled_' . $ld_group_id
        );
        
        $course_ids = $wpdb->get_col($query);
        
        if (empty($course_ids)) {
            $ld_group = get_post($ld_group_id);
            $group_title = $ld_group ? $ld_group->post_title : 'Unknown';
            wp_die("Course enrollment error: No courses found in LearnDash group {$ld_group_id} ({$group_title}). Please contact support.");
        }
        
        $enrolled_courses = array();
        $failed_enrollments = array();
        
        // Enroll user in each course
        foreach ($course_ids as $course_id) {
            $course_title = get_the_title($course_id);
            
            // Check if already enrolled
            $already_enrolled = get_user_meta($user_id, 'course_' . $course_id . '_access_from', true);
            if (!empty($already_enrolled)) {
                continue;
            }
            
            // Enroll user by setting access timestamp
            $current_time = time();
            $enrollment_success = update_user_meta($user_id, 'course_' . $course_id . '_access_from', $current_time);
            
            if ($enrollment_success) {
                $enrolled_courses[] = array(
                    'course_id' => $course_id,
                    'course_title' => $course_title ?: "Course #$course_id" 
                );
            } else {
                $failed_enrollments[] = $course_id;
            }
        }
        
        // Add user to LearnDash group members
        $group_users = get_post_meta($ld_group_id, 'learndash_group_users', true);
        
        if (!is_array($group_users)) {
            $group_users = array();
        }
        
        if (!in_array($user_id, $group_users)) {
            $group_users[] = $user_id;
            update_post_meta($ld_group_id, 'learndash_group_users', $group_users);
            update_user_meta($user_id, 'learndash_group_users_' . $ld_group_id, $ld_group_id);
        }
        
        // Check results and show appropriate message
        $success = count($enrolled_courses) > 0;
        
        if (!$success && count($course_ids) > 0) {
            wp_die("Course enrollment error: Failed to enroll in any courses. User ID: {$user_id}, BuddyBoss Group: {$bb_group_id}, LearnDash Group: {$ld_group_id}, Course Count: " . count($course_ids));
        }
        
        $message = $success ? 
            'Enrolled in ' . count($enrolled_courses) . ' courses' : 
            'No courses enrolled';
            
        if (!empty($failed_enrollments)) {
            $message .= ', ' . count($failed_enrollments) . ' failed';
        }
        
        return array(
            'success' => $success,
            'message' => $message,
            'enrolled_courses' => $enrolled_courses,
            'failed_enrollments' => $failed_enrollments,
            'ld_group_id' => $ld_group_id
        );
    } 
    
    /**
     * Add a user to a LearnDash group as a member (not leader)
     *
     * @param int $user_id
     * @param int $ld_group_id
     * @return bool
     */
    private function add_user_to_ld_group($user_id, $ld_group_id) {
        // Get current group users
        $group_users = learndash_get_groups_user_ids($ld_group_id);
        
        if (!is_array($group_users)) {
            $group_users = array();
        }
        
        // Add user if not already in group
        if (!in_array($user_id, $group_users)) {
            $group_users[] = $user_id;
            
            // Update the group users
            update_post_meta($ld_group_id, 'learndash_group_users', $group_users);
            
            // Also update user's groups meta
            $user_groups = get_user_meta($user_id, 'learndash_group_users_' . $ld_group_id, true);
            if ($user_groups !== $ld_group_id) {
                update_user_meta($user_id, 'learndash_group_users_' . $ld_group_id, $ld_group_id);
            }
            
            // Clear LearnDash cache
            if (function_exists('learndash_purge_user_group_cache')) {
                learndash_purge_user_group_cache($user_id);
            }
            
            return true;
        }
        
        return false; // User was already in group
    }
    
    /**
     * Helper method to create a group invitation
     * 
     * @param int $group_id The group ID
     * @param string $email The user's email address
     * @param bool $is_organizer Whether the user should be invited as organizer
     * @param string $first_name First name (optional)
     * @param string $last_name Last name (optional)
     * @return array|WP_Error Success array with user data or WP_Error on failure
     */
    private function create_group_invitation($group_id, $email, $is_organizer = false, $first_name = '', $last_name = '') {
        $role = $is_organizer ? 'organizer' : 'member';
        
        $user = get_user_by( 'email', $email );
        $user_id = $user ? $user->ID : null;

        // Generate a unique token for this invitation
        $token = wp_generate_password( 20, false );

        if ( ! $user ) {
            // Create new user - get names from parameters or extract from email
            if (empty($first_name) && empty($last_name)) {
                $email_parts = explode('@', $email);
                $first_name = ucfirst($email_parts[0]);
            }
            
            $username = $this->generate_unique_username( $email );
            $random_password = wp_generate_password();
            $user_id = wp_create_user( $username, $random_password, $email );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );
            update_user_meta( $user_id, 'can_manage_members', 0 ); // Used to block access to manage members tab
            $user = get_user_by( 'id', $user_id );
        }

        // Check if user is already a member or has pending invitation
        if (groups_is_user_member($user->ID, $group_id)) {
            return new WP_Error('already_member', 'User is already a member of this group');
        }

        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        if (is_array($invited_users) && isset($invited_users[$user->ID])) {
            return new WP_Error('pending_invitation', 'User already has a pending invitation');
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
            'token' => $token,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $user->display_name,
            'invitation_created' => current_time('timestamp')
        );
        
        // Log the invitation details
        $this->log("Creating invitation for user ID: {$user->ID}, Email: {$email}, Group ID: {$group_id}, Token: {$token}");
        
        // Store the invitation data
        $update_result = groups_update_groupmeta($group_id, 'lab_invited', $invited_users);
        
        if ($update_result === false) {
            $this->log("Failed to update group meta with invitation data", 'error');
        } else {
            $this->log("Successfully stored invitation data in group meta", 'info');
        }
        
        // Update available seats (only after successful invitation creation)
        $this->update_available_seats($group_id, -1, 'invitation_created');

        // Set changed_password meta to 0 for invited user
        if ($user && $user->ID) {
            update_user_meta($user->ID, 'changed_password', 0);
            update_user_meta($user->ID, 'can_manage_members', 0); // Used to block access to manage members tab
        }

        // Send invitation email
        $email_sent = $this->send_group_invitation_email($user, $group_id, $is_organizer, $token);

        return array(
            'user' => $user,
            'invitation' => $invited_users[$user->ID],
            'email_sent' => $email_sent
        );
    }
}

GroupMembersHandler::get_instance();