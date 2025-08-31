<?php
namespace LABGENZ_CM\Account\Templates;

defined('ABSPATH') || exit;

use LABGENZ_CM\Core\Authentication\MultiEmailManager;

function render_alias_emails_field(): void {
    $user_id = get_current_user_id();
    $aliases = [];

    $main_email = '';
    $user_info = get_userdata($user_id);
    if ($user_info) {
        $main_email = $user_info->user_email;
    }

    if (class_exists(MultiEmailManager::class)) {
        $manager = new MultiEmailManager();
        $aliases = $manager->get_user_aliases($user_id);
    }
    ?>

    <div class="alias-emails-wrapper">
        <label class="main-email-label">
            <?php esc_html_e('Primary Email', 'labgenz-cm'); ?>
        </label>
        <div class="main-email"><?php echo esc_html($main_email); ?></div>

        <label for="alias_emails" class="alias-emails-label">
            <?php esc_html_e('Other Emails', 'labgenz-cm'); ?>
            <span class="woocommerce-help-tip" 
            data-this-mean="<?php esc_attr_e('Add additional email addresses here. Each will require verification. Once verified, they can be used as alternative logins alongside your primary email.', 'labgenz-cm'); ?>">?</span>
        </label>

        <input 
            type="text"
            class="woocommerce-Input woocommerce-Input--text input-text"
            name="alias_emails"
            id="alias_emails"
            value=""
            placeholder="<?php esc_attr_e('e.g. john.doe@gmail.com, jd@example.com', 'labgenz-cm'); ?>"
        />
        <ul id="alias-email-tags" class="alias-email-tags <?php echo empty($aliases) ? 'no-aliases' : ''; ?>">
            <?php foreach ($aliases as $alias): ?>
                <li class="alias-tag <?php echo $alias->is_verified ? 'verified' : 'unverified'; ?>" data-email="<?php echo esc_attr($alias->alias_email); ?>">
                    <?php echo esc_html($alias->alias_email); ?>
                    <span class="remove-tag">×</span>
                    <?php if ($alias->is_verified): ?>
                        <span data-this-mean="This email address is verified and can be used to login." class="verified-badge"><?php esc_html_e('Verified', 'labgenz-cm'); ?></span>
                    <?php else: ?>
                        <span data-this-mean="This email address is not verified yet and cannot be used to login." class="unverified-badge"><?php esc_html_e('Unverified', 'labgenz-cm'); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="button" id="alias-emails-save" class="button">
            <?php esc_html_e('Add Email', 'labgenz-cm'); ?>
        </button>
    </div>

<?php
}
