<?php
/**
 * Group invitation email template
 * 
 * Variables available:
 * $user_name - The recipient's display name
 * $group_name - The name of the group
 * $site_name - The name of the website
 * $role - The role (member/organizer) the user is invited as
 * $accept_url - The URL to accept the invitation
 */
?>
Hello <?php echo $user_name; ?>,

You have been invited to join the group "<?php echo $group_name; ?>" on <?php echo $site_name; ?> as a <?php echo $role; ?>.

To accept this invitation, please click the following link:
<?php echo $accept_url; ?>

If you do not have an account, one has been created for you. You will be automatically logged in when you click the link.

Best regards,
The <?php echo $site_name; ?> Team
