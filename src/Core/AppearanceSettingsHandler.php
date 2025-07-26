<?php
namespace LABGENZ_CM\Core;

/**
 * Class for handling Labgenz Community Management Appearance Settings
 */
class AppearanceSettingsHandler {
	/**
	 * Initialize hooks for AJAX and appearance settings
	 */
	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'add_google_fonts' ] );
		add_action( 'admin_head', [ __CLASS__, 'add_google_fonts' ] );
	}

	/**
	 * AJAX handler for saving appearance settings
	 */
	public static function save_appearance_settings_ajax() {
		$ajax = new \LABGENZ_CM\Core\AjaxHandler();
		try {
			$ajax->handle_request( [ __CLASS__, 'process_save_appearance_settings' ], 'labgenz_appearance_nonce' );
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				],
				500
			);
		}
	}

	/**
	 * Actual logic for saving appearance settings (used by AjaxHandler)
	 */
	public static function process_save_appearance_settings( $request ) {
		// Check user capabilities (already checked in AjaxHandler, but double-check if needed)
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to modify these settings.', 403 );
		}

		$allowed_settings = [
			'primary_color',
			'secondary_color',
			'accent_color',
			'success_color',
			'warning_color',
			'background_color',
			'text_color',
			'border_color',
			'font_family',
			'font_size',
			'border_radius',
			'button_style',
			'table_style',
			'modal_style',
		];
		$settings         = [];
		if ( ! isset( $request['settings'] ) || ! is_array( $request['settings'] ) ) {
			return new \WP_Error( 'invalid', 'Invalid settings data.', 400 );
		}
		foreach ( $request['settings'] as $key => $value ) {
			if ( ! in_array( $key, $allowed_settings, true ) ) {
				continue;
			}
			switch ( $key ) {
				case 'primary_color':
				case 'secondary_color':
				case 'accent_color':
				case 'success_color':
				case 'warning_color':
				case 'background_color':
				case 'text_color':
				case 'border_color':
					$value = sanitize_hex_color( $value );
					if ( $value ) {
						$settings[ $key ] = $value;
					}
					break;
				case 'font_family':
					$allowed_fonts = [ 'system', 'arial', 'helvetica', 'georgia', 'times', 'roboto', 'opensans', 'lato' ];
					if ( in_array( $value, $allowed_fonts, true ) ) {
						$settings[ $key ] = sanitize_text_field( $value );
					}
					break;
				case 'font_size':
					$font_size = intval( $value );
					if ( $font_size >= 12 && $font_size <= 18 ) {
						$settings[ $key ] = $font_size;
					}
					break;
				case 'border_radius':
					$border_radius = intval( $value );
					if ( $border_radius >= 0 && $border_radius <= 20 ) {
						$settings[ $key ] = $border_radius;
					}
					break;
				case 'button_style':
				case 'table_style':
					$allowed_styles = [ 'modern', 'classic', 'minimal', 'bold', 'striped' ];
					if ( in_array( $value, $allowed_styles, true ) ) {
						$settings[ $key ] = sanitize_text_field( $value );
					}
					break;
				case 'modal_style':
					$allowed_modal_styles = [ 'modern', 'classic', 'minimal' ];
					if ( in_array( $value, $allowed_modal_styles, true ) ) {
						$settings[ $key ] = sanitize_text_field( $value );
					}
					break;
				default:
					$settings[ $key ] = sanitize_text_field( $value );
					break;
			}
		}
		$result = update_option( 'labgenz_cm_appearance', $settings );
		if ( $result ) {
			self::generate_appearance_css( $settings );
			return [
				'message'  => 'Appearance settings saved successfully!',
				'settings' => $settings,
			];
		} else {
			return new \WP_Error( 'save_failed', 'Failed to save settings. Please try again.', 500 );
		}
	}

	/**
	 * Generate CSS file from appearance settings (optional for better performance)
	 */
	public static function generate_appearance_css( $settings ) {
		$css_dir  = LABGENZ_CM_PATH . 'src/Admin/assets/';
		$css_file = $css_dir . 'appearance.css';

		// Create directory if it doesn't exist
		if ( ! file_exists( $css_dir ) ) {
			wp_mkdir_p( $css_dir );
		}

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

		$settings = array_merge( $defaults, $settings );

		// Font family mappings
		$font_families = [
			'system'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
			'arial'     => 'Arial, sans-serif',
			'helvetica' => 'Helvetica, Arial, sans-serif',
			'georgia'   => 'Georgia, serif',
			'times'     => '"Times New Roman", Times, serif',
			'roboto'    => '"Roboto", sans-serif',
			'opensans'  => '"Open Sans", sans-serif',
			'lato'      => '"Lato", sans-serif',
		];

		$font_family = isset( $font_families[ $settings['font_family'] ] ) ? $font_families[ $settings['font_family'] ] : $font_families['system'];

		// Generate CSS content
		$css_content  = ":root {\n";
		$css_content .= "  --labgenz-primary-color: {$settings['primary_color']};\n";
		$css_content .= "  --labgenz-secondary-color: {$settings['secondary_color']};\n";
		$css_content .= "  --labgenz-accent-color: {$settings['accent_color']};\n";
		$css_content .= "  --labgenz-success-color: {$settings['success_color']};\n";
		$css_content .= "  --labgenz-warning-color: {$settings['warning_color']};\n";
		$css_content .= "  --labgenz-bg-color: {$settings['background_color']};\n";
		$css_content .= "  --labgenz-text-color: {$settings['text_color']};\n";
		$css_content .= "  --labgenz-border-color: {$settings['border_color']};\n";
		$css_content .= "  --labgenz-font-family: {$font_family};\n";
		$css_content .= "  --labgenz-font-size: {$settings['font_size']}px;\n";
		$css_content .= "  --labgenz-border-radius: {$settings['border_radius']}px;\n";
		$css_content .= "}\n\n";

		// Base plugin styles
		$css_content .= ".labgenz-community-management {\n";
		$css_content .= "  font-family: var(--labgenz-font-family);\n";
		$css_content .= "  font-size: var(--labgenz-font-size);\n";
		$css_content .= "  color: var(--labgenz-text-color);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .labgenz-modal {\n";
		$css_content .= "  background: var(--labgenz-bg-color);\n";
		$css_content .= "  border: 1px solid var(--labgenz-border-color);\n";
		$css_content .= "  border-radius: var(--labgenz-border-radius);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .members-table {\n";
		$css_content .= "  font-family: var(--labgenz-font-family);\n";
		$css_content .= "  font-size: var(--labgenz-font-size);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .members-table th {\n";
		$css_content .= "  background: var(--labgenz-secondary-color);\n";
		$css_content .= "  color: white;\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .members-table td {\n";
		$css_content .= "  border-bottom: 1px solid var(--labgenz-border-color);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .status-badge.status-active {\n";
		$css_content .= "  background: var(--labgenz-success-color);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .status-badge.status-pending {\n";
		$css_content .= "  background: var(--labgenz-warning-color);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management .remove-link,\n";
		$css_content .= ".labgenz-community-management .cancel-link {\n";
		$css_content .= "  color: var(--labgenz-accent-color);\n";
		$css_content .= "  border-color: var(--labgenz-accent-color);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management button,\n";
		$css_content .= ".labgenz-community-management .button {\n";
		$css_content .= "  border-radius: var(--labgenz-border-radius);\n";
		$css_content .= "  font-family: var(--labgenz-font-family);\n";
		$css_content .= "}\n\n";

		$css_content .= ".labgenz-community-management #labgenz-show-invite-popup {\n";
		$css_content .= "  background: var(--labgenz-primary-color);\n";
		$css_content .= "  color: white;\n";
		$css_content .= "  border: none;\n";
		$css_content .= "  padding: 8px 16px;\n";
		$css_content .= "  border-radius: var(--labgenz-border-radius);\n";
		$css_content .= "  cursor: pointer;\n";
		$css_content .= "}\n\n";

		// Button styles
		if ( 'classic' === $settings['button_style'] ) {
			$css_content .= ".labgenz-community-management button,\n";
			$css_content .= ".labgenz-community-management .button {\n";
			$css_content .= "  border: 2px solid;\n";
			$css_content .= "  background: transparent !important;\n";
			$css_content .= "  font-weight: bold;\n";
			$css_content .= "}\n\n";

			$css_content .= ".labgenz-community-management #labgenz-show-invite-popup {\n";
			$css_content .= "  color: var(--labgenz-primary-color) !important;\n";
			$css_content .= "  border-color: var(--labgenz-primary-color);\n";
			$css_content .= "}\n\n";
		} elseif ( 'minimal' === $settings['button_style'] ) {
			$css_content .= ".labgenz-community-management button,\n";
			$css_content .= ".labgenz-community-management .button {\n";
			$css_content .= "  background: transparent !important;\n";
			$css_content .= "  border: none;\n";
			$css_content .= "  text-decoration: underline;\n";
			$css_content .= "  padding: 4px 8px;\n";
			$css_content .= "}\n\n";
		} elseif ( 'bold' === $settings['button_style'] ) {
			$css_content .= ".labgenz-community-management button,\n";
			$css_content .= ".labgenz-community-management .button {\n";
			$css_content .= "  font-weight: 900;\n";
			$css_content .= "  text-transform: uppercase;\n";
			$css_content .= "  letter-spacing: 1px;\n";
			$css_content .= "  padding: 12px 24px;\n";
			$css_content .= "  box-shadow: 0 4px 8px rgba(0,0,0,0.2);\n";
			$css_content .= "}\n\n";
		}

		// Table styles
		if ( 'classic' === $settings['table_style'] ) {
			$css_content .= ".labgenz-community-management .members-table {\n";
			$css_content .= "  border: 2px solid var(--labgenz-border-color);\n";
			$css_content .= "}\n\n";

			$css_content .= ".labgenz-community-management .members-table th,\n";
			$css_content .= ".labgenz-community-management .members-table td {\n";
			$css_content .= "  border: 1px solid var(--labgenz-border-color);\n";
			$css_content .= "}\n\n";

			$css_content .= ".labgenz-community-management .members-table th {\n";
			$css_content .= "  background: var(--labgenz-bg-color) !important;\n";
			$css_content .= "  color: var(--labgenz-text-color) !important;\n";
			$css_content .= "  border-bottom: 2px solid var(--labgenz-secondary-color);\n";
			$css_content .= "}\n\n";
		} elseif ( 'minimal' === $settings['table_style'] ) {
			$css_content .= ".labgenz-community-management .members-table {\n";
			$css_content .= "  border: none;\n";
			$css_content .= "}\n\n";

			$css_content .= ".labgenz-community-management .members-table th {\n";
			$css_content .= "  background: transparent !important;\n";
			$css_content .= "  color: var(--labgenz-text-color) !important;\n";
			$css_content .= "  border-bottom: 2px solid var(--labgenz-primary-color);\n";
			$css_content .= "  border-top: none;\n";
			$css_content .= "  border-left: none;\n";
			$css_content .= "  border-right: none;\n";
			$css_content .= "}\n\n";

			$css_content .= ".labgenz-community-management .members-table td {\n";
			$css_content .= "  border-left: none;\n";
			$css_content .= "  border-right: none;\n";
			$css_content .= "  border-top: none;\n";
			$css_content .= "}\n\n";
		} elseif ( 'striped' === $settings['table_style'] ) {
			$css_content .= ".labgenz-community-management .members-table tbody tr:nth-child(even) {\n";
			$css_content .= "  background: rgba(0,0,0,0.05);\n";
			$css_content .= "}\n\n";
		}

		// Write CSS file using WP Filesystem API
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $css_file, $css_content, FS_CHMOD_FILE );
	}

	/**
	 * Enqueue the generated appearance CSS file
	 * Add this to your main plugin file where you enqueue other styles
	 */
	public static function enqueue_appearance_styles() {
		$css_file = LABGENZ_CM_PATH . 'src/Admin/assets/appearance.css';
		$css_url  = LABGENZ_CM_URL . 'src/Admin/assets/appearance.css';

		// Only enqueue if file exists
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'labgenz-appearance',
				$css_url,
				[],
				filemtime( $css_file ) // Use file modification time as version for cache busting
			);
		}
	}

	/**
	 * AJAX handler for resetting appearance settings
	 */
	public static function reset_appearance_settings_ajax() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'labgenz_appearance_nonce' ) ) {
			wp_send_json_error( 'Security check failed.' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You do not have permission to modify these settings.' );
			return;
		}

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

		// Save default settings
		$result = update_option( 'labgenz_cm_appearance', $defaults );

		if ( $result ) {
			// Regenerate CSS file
			self::generate_appearance_css( $defaults );

			wp_send_json_success(
				[
					'message'  => 'Appearance settings reset to defaults successfully!',
					'settings' => $defaults,
				]
			);
		} else {
			wp_send_json_error( 'Failed to reset settings. Please try again.' );
		}
	}

	/**
	 * Add Google Fonts to head when needed
	 */
	public static function add_google_fonts() {
		$settings = get_option( 'labgenz_cm_appearance', [] );

		if ( isset( $settings['font_family'] ) ) {
			$google_fonts = [ 'roboto', 'opensans', 'lato' ];

			if ( in_array( $settings['font_family'], $google_fonts, true ) ) {
				$font_name = ucfirst( $settings['font_family'] );
				if ( 'opensans' === $settings['font_family'] ) {
					$font_name = 'Open+Sans';
				}

				$font_url = "https://fonts.googleapis.com/css2?family={$font_name}:wght@300;400;500;600;700&display=swap";
                // phpcs:ignore
				echo '<link rel="stylesheet" href="' . esc_url( $font_url ) . '" type="text/css" media="all" />' . "\n";
			}
		}
	}

	/**
	 * Initialize appearance settings on plugin activation
	 */
	public static function init_appearance_settings() {
		$existing_settings = get_option( 'labgenz_cm_appearance' );

		if ( false === $existing_settings ) {
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

			add_option( 'labgenz_cm_appearance', $defaults );
			self::generate_appearance_css( $defaults );
		}
	}

	/**
	 * Clean up generated CSS file on plugin deactivation
	 */
	public static function cleanup_appearance_files() {
		$css_file = LABGENZ_CM_PATH . 'src/Admin/assets/appearance.css';

		if ( file_exists( $css_file ) ) {
			wp_delete_file( $css_file );
		}

		// Remove directory if empty
		$css_dir = LABGENZ_CM_PATH . 'src/Admin/assets/';
        // phpcs:ignore
		if ( is_dir( $css_dir ) && count( scandir( $css_dir ) ) == 2 ) { // Only . and ..
			// phpcs:ignore
            rmdir( $css_dir );
		}
	}
}
