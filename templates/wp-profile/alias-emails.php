<?php
use LABGENZ_CM\Core\Authentication\MultiEmailManager;

$alias_manager = new MultiEmailManager();
$user_id = get_current_user_id();
$aliases = $alias_manager->get_user_aliases($user_id);
?>

<style>
.alias-emails-wrap { margin-top:20px; }
.alias-emails-wrap table { width:100%; border-collapse:collapse; }
.alias-emails-wrap th, .alias-emails-wrap td { border:1px solid #ddd; padding:8px; }
.alias-emails-wrap th { background:#f5f5f5; text-align:left; }
.alias-emails-wrap .actions a { margin-right:8px; cursor:pointer; color:#0073aa; }
.alias-emails-wrap .actions a:hover { text-decoration:underline; }
</style>

<div class="alias-emails-wrap">
    <table>
        <thead>
            <tr>
                <th><?php esc_html_e('Email', 'textdomain'); ?></th>
                <th><?php esc_html_e('Status', 'textdomain'); ?></th>
                <th><?php esc_html_e('Actions', 'textdomain'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($aliases)) : ?>
                <?php foreach ($aliases as $alias) : ?>
                    <tr>
                        <td><?php echo esc_html($alias->alias_email); ?></td>
                        <td>
                            <?php echo $alias->is_verified
                                ? '<span style="color:green;">Verified</span>'
                                : '<span style="color:orange;">Unverified</span>'; ?>
                        </td>
                        <td class="actions">
                            <a href="?remove_alias=<?php echo urlencode($alias->alias_email); ?>">
                                <?php esc_html_e('Remove', 'textdomain'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="3"><?php esc_html_e('No alias emails added.', 'textdomain'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <form method="post">
        <input type="email" name="alias_email" required placeholder="Enter new alias">
        <button type="submit" name="add_alias"><?php esc_html_e('Add Alias', 'textdomain'); ?></button>
    </form>
</div>

<?php
// Handle add/remove directly
if (isset($_POST['add_alias']) && !empty($_POST['alias_email'])) {
    $alias_manager->handle_add_alias_direct($user_id, sanitize_email($_POST['alias_email']));
    wp_safe_redirect($_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_GET['remove_alias'])) {
    $alias_manager->handle_remove_alias_direct($user_id, sanitize_email($_GET['remove_alias']));
    wp_safe_redirect(remove_query_arg('remove_alias', $_SERVER['REQUEST_URI']));
    exit;
}
?>
