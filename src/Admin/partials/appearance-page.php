<?php
// Appearance settings page for Labgenz Community Management
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current appearance settings
$appearance_settings = get_option( 'labgenz_cm_appearance', [] );

// Default settings
$defaults = [
	'primary_color'    => '#3498db',
	'secondary_color'  => '#2c3e50',
	'accent_color'     => '#e74c3c',
	'success_color'    => '#27ae60',
	'warning_color'    => '#f39c12',
	'background_color' => '#ffffff',
	'text_color'       => '#2c3e50',
	'border_color'     => '#e0e0e0',
	'font_family'      => 'system',
	'font_size'        => '14',
	'border_radius'    => '4',
	'button_style'     => 'modern',
	'table_style'      => 'modern',
	'modal_style'      => 'modern',
];

$settings = wp_parse_args( $appearance_settings, $defaults );
?>

<div class="wrap labgenz-appearance-wrap">
	<h1><?php esc_html_e( 'Appearance Settings', 'labgenz-community-management' ); ?></h1>
	
	<div class="labgenz-appearance-container">
		<!-- Live Preview Section -->
		<div class="labgenz-preview-section">
			<h2><?php esc_html_e( 'Live Preview', 'labgenz-community-management' ); ?></h2>
			<div id="labgenz-preview-container">
				<!-- Sample elements to preview changes -->
				<div class="labgenz-preview-card">
					<h3>Sample Card</h3>
					<p>This is how your content will look with the selected appearance settings.</p>
					<button class="labgenz-btn labgenz-btn-primary">Primary Button</button>
					<button class="labgenz-btn labgenz-btn-secondary">Secondary Button</button>
					<button class="labgenz-btn labgenz-btn-accent">Accent Button</button>
				</div>
				
				<div class="labgenz-preview-table">
					<table class="labgenz-table">
						<thead>
							<tr>
								<th>Name</th>
								<th>Email</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>John Doe</td>
								<td>john@example.com</td>
								<td><span class="labgenz-status-active">Active</span></td>
								<td><a href="#" class="labgenz-action-link">Edit</a></td>
							</tr>
							<tr>
								<td>Jane Smith</td>
								<td>jane@example.com</td>
								<td><span class="labgenz-status-pending">Pending</span></td>
								<td><a href="#" class="labgenz-action-link">Remove</a></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Settings Form -->
		<div class="labgenz-settings-section">
			<form id="labgenz-appearance-form">
				<?php wp_nonce_field( 'labgenz_appearance_nonce', 'labgenz_appearance_nonce' ); ?>
				
				<div class="labgenz-settings-tabs">
					<nav class="nav-tab-wrapper">
						<a href="#colors" class="nav-tab nav-tab-active" data-tab="colors">Colors</a>
						<a href="#typography" class="nav-tab" data-tab="typography">Typography</a>
						<a href="#layout" class="nav-tab" data-tab="layout">Layout</a>
						<a href="#components" class="nav-tab" data-tab="components">Components</a>
					</nav>

					<!-- Colors Tab -->
					<div id="colors" class="labgenz-tab-content active">
						<h3><?php esc_html_e( 'Color Settings', 'labgenz-community-management' ); ?></h3>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="primary_color"><?php esc_html_e( 'Primary Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Main brand color used for buttons and links.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="secondary_color"><?php esc_html_e( 'Secondary Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr( $settings['secondary_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Secondary color for text and borders.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="accent_color"><?php esc_html_e( 'Accent Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="accent_color" name="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Color for warnings and important actions.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="success_color"><?php esc_html_e( 'Success Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="success_color" name="success_color" value="<?php echo esc_attr( $settings['success_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Color for success messages and positive actions.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="background_color"><?php esc_html_e( 'Background Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="background_color" name="background_color" value="<?php echo esc_attr( $settings['background_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Main background color for cards and containers.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="text_color"><?php esc_html_e( 'Text Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="text_color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Primary text color.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="border_color"><?php esc_html_e( 'Border Color', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="color" id="border_color" name="border_color" value="<?php echo esc_attr( $settings['border_color'] ); ?>" class="color-picker">
									<p class="description"><?php esc_html_e( 'Color for borders and dividers.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Typography Tab -->
					<div id="typography" class="labgenz-tab-content">
						<h3><?php esc_html_e( 'Typography Settings', 'labgenz-community-management' ); ?></h3>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="font_family"><?php esc_html_e( 'Font Family', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<select id="font_family" name="font_family">
										<option value="system" <?php selected( $settings['font_family'], 'system' ); ?>>System Default</option>
										<option value="arial" <?php selected( $settings['font_family'], 'arial' ); ?>>Arial</option>
										<option value="helvetica" <?php selected( $settings['font_family'], 'helvetica' ); ?>>Helvetica</option>
										<option value="georgia" <?php selected( $settings['font_family'], 'georgia' ); ?>>Georgia</option>
										<option value="times" <?php selected( $settings['font_family'], 'times' ); ?>>Times New Roman</option>
										<option value="roboto" <?php selected( $settings['font_family'], 'roboto' ); ?>>Roboto (Google Fonts)</option>
										<option value="opensans" <?php selected( $settings['font_family'], 'opensans' ); ?>>Open Sans (Google Fonts)</option>
										<option value="lato" <?php selected( $settings['font_family'], 'lato' ); ?>>Lato (Google Fonts)</option>
									</select>
									<p class="description"><?php esc_html_e( 'Choose the font family for your plugin interface.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="font_size"><?php esc_html_e( 'Base Font Size', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="range" id="font_size" name="font_size" min="12" max="18" value="<?php echo esc_attr( $settings['font_size'] ); ?>" class="range-slider">
									<span class="range-value"><?php echo esc_html( $settings['font_size'] ); ?>px</span>
									<p class="description"><?php esc_html_e( 'Base font size for plugin interface.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Layout Tab -->
					<div id="layout" class="labgenz-tab-content">
						<h3><?php esc_html_e( 'Layout Settings', 'labgenz-community-management' ); ?></h3>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="border_radius"><?php esc_html_e( 'Border Radius', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<input type="range" id="border_radius" name="border_radius" min="0" max="20" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" class="range-slider">
									<span class="range-value"><?php echo esc_html( $settings['border_radius'] ); ?>px</span>
									<p class="description"><?php esc_html_e( 'Roundness of corners for buttons and cards.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Components Tab -->
					<div id="components" class="labgenz-tab-content">
						<h3><?php esc_html_e( 'Component Styles', 'labgenz-community-management' ); ?></h3>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="button_style"><?php esc_html_e( 'Button Style', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<select id="button_style" name="button_style">
										<option value="modern" <?php selected( $settings['button_style'], 'modern' ); ?>>Modern</option>
										<option value="classic" <?php selected( $settings['button_style'], 'classic' ); ?>>Classic</option>
										<option value="minimal" <?php selected( $settings['button_style'], 'minimal' ); ?>>Minimal</option>
										<option value="bold" <?php selected( $settings['button_style'], 'bold' ); ?>>Bold</option>
									</select>
									<p class="description"><?php esc_html_e( 'Visual style for buttons throughout the interface.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="table_style"><?php esc_html_e( 'Table Style', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<select id="table_style" name="table_style">
										<option value="modern" <?php selected( $settings['table_style'], 'modern' ); ?>>Modern</option>
										<option value="classic" <?php selected( $settings['table_style'], 'classic' ); ?>>Classic</option>
										<option value="minimal" <?php selected( $settings['table_style'], 'minimal' ); ?>>Minimal</option>
										<option value="striped" <?php selected( $settings['table_style'], 'striped' ); ?>>Striped</option>
									</select>
									<p class="description"><?php esc_html_e( 'Visual style for data tables.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="modal_style"><?php esc_html_e( 'Modal Style', 'labgenz-community-management' ); ?></label>
								</th>
								<td>
									<select id="modal_style" name="modal_style">
										<option value="modern" <?php selected( $settings['modal_style'], 'modern' ); ?>>Modern</option>
										<option value="classic" <?php selected( $settings['modal_style'], 'classic' ); ?>>Classic</option>
										<option value="minimal" <?php selected( $settings['modal_style'], 'minimal' ); ?>>Minimal</option>
									</select>
									<p class="description"><?php esc_html_e( 'Visual style for modal dialogs and popups.', 'labgenz-community-management' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="labgenz-form-actions">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Save Appearance Settings', 'labgenz-community-management' ); ?>
					</button>
					<button type="button" id="labgenz-reset-appearance" class="button button-secondary">
						<?php esc_html_e( 'Reset to Defaults', 'labgenz-community-management' ); ?>
					</button>
					<button type="button" id="labgenz-export-appearance" class="button">
						<?php esc_html_e( 'Export Settings', 'labgenz-community-management' ); ?>
					</button>
					<button type="button" id="labgenz-import-appearance" class="button">
						<?php esc_html_e( 'Import Settings', 'labgenz-community-management' ); ?>
					</button>
				</div>
			</form>
			
			<input type="file" id="labgenz-import-file" accept=".json" style="display: none;">
		</div>
	</div>

	<!-- Success/Error Messages -->
	<div id="labgenz-appearance-messages" class="labgenz-messages"></div>
</div>

<style>
.labgenz-appearance-wrap {
	max-width: 1200px;
}

.labgenz-appearance-container {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin-top: 20px;
}

.labgenz-preview-section, .labgenz-settings-section {
	background: #fff;
	padding: 20px;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.labgenz-preview-section {
	position: sticky;
	top: 32px;
	height: fit-content;
}

.labgenz-tab-content {
	display: none;
	padding: 20px 0;
}

.labgenz-tab-content.active {
	display: block;
}

.labgenz-preview-card {
	background: var(--labgenz-bg-color, #fff);
	border: 1px solid var(--labgenz-border-color, #e0e0e0);
	border-radius: var(--labgenz-border-radius, 4px);
	padding: 20px;
	margin-bottom: 20px;
	color: var(--labgenz-text-color, #2c3e50);
	font-family: var(--labgenz-font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif);
	font-size: var(--labgenz-font-size, 14px);
}

.labgenz-btn {
	padding: 8px 16px;
	border: none;
	border-radius: var(--labgenz-border-radius, 4px);
	cursor: pointer;
	margin-right: 10px;
	margin-bottom: 10px;
	font-size: var(--labgenz-font-size, 14px);
	font-family: var(--labgenz-font-family, inherit);
	text-decoration: none;
	display: inline-block;
	transition: all 0.2s ease;
}

.labgenz-btn-primary {
	background: var(--labgenz-primary-color, #3498db);
	color: white;
}

.labgenz-btn-secondary {
	background: var(--labgenz-secondary-color, #2c3e50);
	color: white;
}

.labgenz-btn-accent {
	background: var(--labgenz-accent-color, #e74c3c);
	color: white;
}

.labgenz-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
	font-family: var(--labgenz-font-family, inherit);
	font-size: var(--labgenz-font-size, 14px);
}

.labgenz-table th,
.labgenz-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid var(--labgenz-border-color, #e0e0e0);
}

.labgenz-table th {
	background: var(--labgenz-secondary-color, #2c3e50);
	color: white;
	font-weight: 600;
}

.labgenz-status-active {
	background: var(--labgenz-success-color, #27ae60);
	color: white;
	padding: 4px 8px;
	border-radius: var(--labgenz-border-radius, 4px);
	font-size: 12px;
}

.labgenz-status-pending {
	background: var(--labgenz-warning-color, #f39c12);
	color: white;
	padding: 4px 8px;
	border-radius: var(--labgenz-border-radius, 4px);
	font-size: 12px;
}

.labgenz-action-link {
	color: var(--labgenz-primary-color, #3498db);
	text-decoration: none;
}

.range-slider {
	width: 200px;
	margin-right: 10px;
}

.range-value {
	font-weight: bold;
	color: var(--labgenz-primary-color, #3498db);
}

.labgenz-form-actions {
	padding: 20px 0;
	border-top: 1px solid #e0e0e0;
	margin-top: 20px;
}

.labgenz-form-actions .button {
	margin-right: 10px;
}

.labgenz-messages {
	margin-top: 20px;
}

.labgenz-messages .notice {
	margin: 10px 0;
}

@media (max-width: 768px) {
	.labgenz-appearance-container {
		grid-template-columns: 1fr;
	}
	
	.labgenz-preview-section {
		position: static;
	}
}
</style>
