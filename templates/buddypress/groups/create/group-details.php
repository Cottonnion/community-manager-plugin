<?php
// At the top of your template file
wp_nonce_field( 'groups_create_save_' . bp_get_groups_current_create_step() );
?>

<!-- Then your form content -->
<div class="custom-group-details-step">
    <h2><?php _e( 'Group Details Upper', LABGENZTEXTDOMAIN ); ?></h2>
    
    <input type="text" name="custom_group_field" placeholder="Custom field..." />
    <textarea name="group_description" placeholder="Description..."></textarea>
    
    <!-- Your other form fields -->
</div>