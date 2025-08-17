<?php
namespace LABGENZ_CM\Groups;

use LABGENZ_CM\Groups\Helpers\SearchUserHelper;
use LABGENZ_CM\Groups\Helpers\InviteUserHelper;
use LABGENZ_CM\Groups\Helpers\CancelInvitationHelper;
use LABGENZ_CM\Groups\Helpers\ResendInvitationHelper;
use LABGENZ_CM\Groups\Helpers\RemoveMemberHelper;
use LABGENZ_CM\Groups\Helpers\AcceptInvitationHelper;
use LABGENZ_CM\Helpers\LearndasHelper;
use LABGENZ_CM\Core\AjaxHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles group member operations including invitations, acceptance, and user management
 */
class GroupMembersHandler
{

    /*
    * Ajax handler class
    * @var AjaxHandler
    */
    private AjaxHandler $ajax_handler;

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Initialize AjaxHandler
        $this->ajax_handler = new AjaxHandler();

        // Register AJAX actions
        $this->ajax_handler->register_ajax_actions([
            'lab_group_search_user'         => [$this, 'search_user_ajax'],
            'lab_group_invite_user'         => [$this, 'invite_user_ajax'],
            'lab_group_remove_member'       => [$this, 'remove_member_ajax'],
            'lab_group_cancel_invitation'   => [$this, 'cancel_invitation_ajax'],
            'lab_group_resend_invitation'   => [$this, 'resend_invitation_ajax'],
            // 'lab_accept_invitation' => [$this, 'accept_invitation_ajax'],
        ]);

        // Invitation handling
        add_action('init', [$this, 'handle_invitation_acceptance']);

        // UI feedback
        add_action('bp_before_group_header', [$this, 'display_invitation_success_message']);
        add_action('woocommerce_account_content', [$this, 'display_invitation_success_message']);
    }

    /**
     * AJAX Handlers
     */
    public function remove_member_ajax()
    {
        $remove_helper = new RemoveMemberHelper();
        $remove_helper->remove_member($_POST);
    }

    public function invite_user_ajax()
    {
        $invite_helper = new InviteUserHelper();
        $invite_helper->invite_user($_POST);
    }

    public function accept_invitation_ajax()
    {
        $accept_helper = new AcceptInvitationHelper();
        $accept_helper->accept_invitation($_POST);
    }

    public function cancel_invitation_ajax()
    {
        $cancel_helper = new CancelInvitationHelper();
        $cancel_helper->cancel_invitation($_POST);
    }
    
    public function resend_invitation_ajax()
    {
        $resend_helper = new ResendInvitationHelper();
        $resend_helper->resend_invitation($_POST);
    }

    public function search_user_ajax()
    {
        $search_helper = new SearchUserHelper();
        $search_helper->search_user($_POST);
    }

    /**
     * Display success message after invitation acceptance
     */
    public function display_invitation_success_message()
    {
        if ($this->should_show_success_message()) {
            $this->render_success_message();
        }

        add_action('wp_footer', [$this, 'add_password_change_script']);
    }

    /**
     * Handle invitation acceptance from email links
     */
    public function handle_invitation_acceptance()
    {
        if (!$this->is_invitation_acceptance_request()) {
            return;
        }

        $invitation_data = $this->validate_invitation_request();
        if (!$invitation_data) {
            wp_die('Invalid invitation link.');
        }

        $user = $this->get_user_by_email($invitation_data['email']);
        if (!$user) {
            wp_die('User not found.');
        }

        $invitation = $this->validate_invitation_token($user->ID, $invitation_data);
        if (!$invitation) {
            wp_die('Invalid invitation token.');
        }

        if ($this->is_user_already_member($user->ID, $invitation_data['group_id'])) {
            $this->redirect_already_member($invitation_data['group_id']);
            return;
        }

        $this->process_invitation_acceptance($user, $invitation_data['group_id'], $invitation);
    }

    /**
     * Add script to display SweetAlert password change prompt
     */
    public function add_password_change_script()
    {
        if (!$this->should_show_password_change_script()) {
            return;
        }

        $this->render_password_change_script();
    }

    /**
     * Private Helper Methods
     */
    private function should_show_success_message()
    {
        return isset($_GET['invitation_accepted']) 
            && $_GET['invitation_accepted'] == '1' 
            && !isset($_GET['change_password']);
    }

    private function render_success_message()
    {
        echo '<div class="bp-feedback success">
                <span class="bp-icon" aria-hidden="true"></span>
                <p>You have successfully joined this group.</p>
              </div>';
    }

    private function should_show_password_change_script()
    {
        if (!function_exists('is_account_page') || !is_account_page()) {
            return false;
        }

        return isset($_GET['invitation_accepted']) 
            && $_GET['invitation_accepted'] == '1'
            && isset($_GET['change_password']) 
            && $_GET['change_password'] == '1';
    }

    private function render_password_change_script()
    {
        $user_id = get_current_user_id();
        $temp_password = '';
        if ($user_id && class_exists('LABGENZ_CM\Core\UserAccountManager')) {
            $temp_password = \LABGENZ_CM\Core\UserAccountManager::get_temp_password($user_id);
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
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
                    const passwordCurrentField = document.getElementById('password_current');
                    if (passwordCurrentField) {
                        passwordCurrentField.value = <?php echo json_encode($temp_password); ?>;
                        passwordCurrentField.type = 'text'; // Show password for first time
                        setTimeout(function() {
                            passwordCurrentField.type = 'password';
                        }, 3000);
                    }
                    const passwordField = document.getElementById('password_1');
                    if (passwordField) {
                        passwordField.focus();
                        passwordField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        $('.woocommerce-form-row--password').addClass('highlight-password-field');
                        setTimeout(function() {
                            $('.woocommerce-form-row--password').removeClass('highlight-password-field');
                        }, 2000);
                    }
                });
                $('<style>.highlight-password-field { animation: pulse-border 2s; } @keyframes pulse-border { 0% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(139, 69, 19, 0); } 100% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0); } }</style>').appendTo('head');
            }
        });
        </script>
        <?php
    }

    private function is_invitation_acceptance_request()
    {
        return isset($_GET['action']) && $_GET['action'] === 'accept_invitation';
    }

    private function validate_invitation_request()
    {
        $group_id = intval($_GET['group_id'] ?? 0);
        $email = sanitize_email($_GET['email'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$group_id || !$email || !$token) {
            return false;
        }

        return [
            'group_id' => $group_id,
            'email' => $email,
            'token' => $token
        ];
    }

    private function get_user_by_email($email)
    {
        return get_user_by('email', $email);
    }

    private function validate_invitation_token($user_id, $invitation_data)
    {
        $invited_users = groups_get_groupmeta($invitation_data['group_id'], 'lab_invited', true);

        if (!is_array($invited_users) || !isset($invited_users[$user_id])) {
            return false;
        }

        $invitation = $invited_users[$user_id];

        if ($invitation['token'] !== $invitation_data['token']) {
            return false;
        }

        return $invitation;
    }

    private function is_user_already_member($user_id, $group_id)
    {
        return groups_is_user_member($user_id, $group_id);
    }

    private function redirect_already_member($group_id)
    {
        $group_url = bp_get_group_permalink(groups_get_group($group_id));
        wp_redirect(add_query_arg('already_member', '1', $group_url));
        exit;
    }

    private function process_invitation_acceptance($user, $group_id, $invitation)
    {
        $should_be_organizer = $this->determine_organizer_role($invitation);

        $this->auto_login_user($user);
        
        if (!$this->add_user_to_group($user->ID, $group_id)) {
            wp_die('Failed to join group. Please try again later.');
        }

        $this->remove_from_invited_users($user->ID, $group_id);

        if ($should_be_organizer) {
            $this->promote_user_to_organizer($user->ID, $group_id);
        }

        $this->redirect_to_account_page();
    }

    private function determine_organizer_role($invitation)
    {
        return (isset($invitation['role']) && $invitation['role'] === 'organizer')
            || (isset($invitation['is_organizer']) && $invitation['is_organizer']);
    }

    private function auto_login_user($user)
    {
        if (!is_user_logged_in()) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }
    }

    private function add_user_to_group($user_id, $group_id)
    {
        return groups_join_group($group_id, $user_id);
    }

    private function remove_from_invited_users($user_id, $group_id)
    {
        $invited_users = groups_get_groupmeta($group_id, 'lab_invited', true);
        unset($invited_users[$user_id]);
        groups_update_groupmeta($group_id, 'lab_invited', $invited_users);
    }

    private function promote_user_to_organizer($user_id, $group_id)
    {
        sleep(1); // Ensure user is fully joined

        $promote_result = groups_promote_member($user_id, $group_id, 'admin');

        // Fallback if promotion failed
        if (!groups_is_user_admin($user_id, $group_id)) {
            $this->force_admin_promotion($user_id, $group_id);
        }

        $this->sync_learndash_leadership($user_id, $group_id);
    }

    private function force_admin_promotion($user_id, $group_id)
    {
        global $wpdb, $bp;
        
        $wpdb->update(
            $bp->groups->table_name_members,
            ['is_admin' => 1],
            ['user_id' => $user_id, 'group_id' => $group_id],
            ['%d'],
            ['%d', '%d']
        );

        // Clear cache
        wp_cache_delete($group_id, 'bp_groups_memberships_for_group');
        groups_clear_group_object_cache($group_id);
    }

    private function redirect_to_account_page()
    {
        $edit_account_url = $this->get_edit_account_url();
        $redirect_url = add_query_arg([
            'invitation_accepted' => '1',
            'change_password' => '1'
        ], $edit_account_url);

        wp_redirect($redirect_url);
        exit;
    }

    private function get_edit_account_url()
    {
        if (function_exists('wc_get_page_permalink') && function_exists('wc_get_endpoint_url')) {
            return wc_get_endpoint_url('edit-account', '', wc_get_page_permalink('myaccount'));
        }
        
        return 'https://v2mlmmasteryclub.labgenz.com/my-account/edit-account/';
    }

    /**
     * Sync LearnDash leadership roles
     * 
     * @param int $user_id The user ID
     * @param int $bb_group_id The BuddyBoss group ID
     */
    private function sync_learndash_leadership($user_id, $bb_group_id)
    {
        $learndash_helper = new LearndasHelper();
        return $learndash_helper->sync_learndash_leadership($user_id, $bb_group_id);
    }

    private function get_associated_ld_group_id($bb_group_id)
    {
        $learndash_helper = new LearndasHelper();
        return $learndash_helper->get_associated_ld_group_id($bb_group_id);
    }

    private function add_user_as_ld_group_leader($user_id, $ld_group_id)
    {
        $learndash_helper = new LearndasHelper();
        return $learndash_helper->add_user_as_ld_group_leader($user_id, $ld_group_id);
    }

    /**
     * Generate a unique username based on email
     *
     * @param string $email The user's email address
     * @return string A unique username
     */
    public function generate_unique_username($email)
    {
        $base_username = sanitize_user(explode('@', $email)[0]);
        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Send group invitation email
     *
     * @param WP_User $user The user to invite
     * @param int $group_id The group ID
     * @param bool $is_organizer Whether the user is an organizer
     * @param string $token The invitation token
     */
    public function send_group_invitation_email($user, $group_id, $is_organizer, $token, $type = 'invitation')
    {
        $group = groups_get_group($group_id);
        $site_name = get_bloginfo('name');
        $role = $is_organizer ? 'organizer' : 'member';

        $accept_url = add_query_arg([
            'action' => 'accept_invitation',
            'group_id' => $group_id,
            'email' => $user->user_email,
            'token' => $token,
        ], home_url());

        if($type === 'reminder') {
            $subject = sprintf('Reminder: Invitation to join %s group on %s', $group->name, $site_name);
        } else {
            $subject = sprintf('Invitation to join %s group on %s', $group->name, $site_name);
        }
        $message = sprintf(
            "Hello %s,\n\n" .
            "You have been invited to join the group \"%s\" on %s as a %s.\n\n" .
            "To accept this invitation, please click the following link:\n%s\n\n" .
            "If you do not have an account, one has been created for you. " .
            "You will be automatically logged in when you click the link.\n\n" .
            "Best regards,\nThe %s Team",
            $user->display_name,
            $group->name,
            $site_name,
            $role,
            $accept_url,
            $site_name
        );

        wp_mail($user->user_email, $subject, $message);
    }
}