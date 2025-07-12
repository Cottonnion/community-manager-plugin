<?php
// At the top of your template file
wp_nonce_field( 'groups_create_save_' . bp_get_groups_current_create_step() );
?>

<!-- Then your form content -->
<div class="custom-group-details-step bb-card bb-card--padding">
    <h2 class="bb-card__title"><?php _e( 'Group Details Upper', LABGENZTEXTDOMAIN ); ?></h2>
    
    <div class="bb-form">
        <div class="bb-form__row">
            <input type="text" name="custom_group_field" class="bb-input" placeholder="Custom field..." />
        </div>
        <div class="bb-form__row">
            <textarea name="group_description" class="bb-textarea" placeholder="Description..."></textarea>
        </div>
    </div>
    
    <!-- Your other form fields -->
</div>