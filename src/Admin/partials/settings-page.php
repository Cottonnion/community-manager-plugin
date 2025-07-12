<?php
/**
 * Labgenz Community Management - Settings Page
 *
 * @package LABGENZ_CM\Admin\Partials
 */

$settings          = new \LABGENZ_CM\Core\Settings();
$current_menu_name = $settings->get( 'menu_page_name', 'Labgenz Community' );
?>
<div id="labgenz-cm-settings-wrapper">
	<h1 class="labgenz-cm-title">Organization Settings</h1>
	<form id="labgenz-cm-settings-form" method="post" action="#">
		<div class="labgenz-cm-form-group">
			<label for="menu_page_name">Sidebar Menu Name</label>
			<input name="menu_page_name" id="menu_page_name" type="text" value="<?php echo esc_attr( $current_menu_name ); ?>" class="labgenz-cm-input" />
			<p class="labgenz-cm-description">Change the name of the sidebar menu.</p>
		</div>
		<button type="button" class="button button-primary labgenz-cm-save-btn" id="labgenz-cm-save-settings">
			<span class="dashicons dashicons-yes"></span> Save Settings
		</button>
		<input type="hidden" id="labgenz-cm-settings-nonce" value="<?php echo esc_attr( wp_create_nonce( 'labgenz_cm_save_menu_settings_nonce' ) ); ?>" />
		<span id="labgenz-cm-settings-status" class="labgenz-cm-status"></span>
	</form>
</div>
