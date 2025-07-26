<?php

declare(strict_types=1);

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\OrganizationAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template renderer for organization access admin pages
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Admin
 */
class OrganizationAccessRenderer {

	/**
	 * Data handler instance
	 *
	 * @var OrganizationAccessDataHandler
	 */
	private OrganizationAccessDataHandler $data_handler;

	/**
	 * Constructor
	 *
	 * @param OrganizationAccessDataHandler $data_handler Data handler instance
	 */
	public function __construct( OrganizationAccessDataHandler $data_handler ) {
		$this->data_handler = $data_handler;
	}

	/**
	 * Render the main admin page
	 *
	 * @param string $current_tab Current tab
	 * @param array  $valid_tabs Valid tabs
	 * @return void
	 */
	public function render_admin_page( string $current_tab, array $valid_tabs ): void {
		$requests = $this->data_handler->get_requests_by_tab( $current_tab );

		// Load the main template
		$this->load_template(
			'admin/organization-requests-page',
			[
				'current_tab'      => $current_tab,
				'valid_tabs'       => $valid_tabs,
				'requests'         => $requests,
				'data_handler'     => $this->data_handler,
				'status_constants' => $this->data_handler->get_status_constants(),
			]
		);
	}

	/**
	 * Load a template file
	 *
	 * @param string $template_name Template name (without .php extension)
	 * @param array  $vars Variables to pass to template
	 * @return void
	 */
	private function load_template( string $template_name, array $vars = [] ): void {
		// Extract variables to make them available in template
		extract( $vars );

		// Build template path
		$template_path = LABGENZ_CM_PATH . 'templates/' . $template_name . '.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			error_log( "Template not found: {$template_path}" );
			echo '<div class="notice notice-error"><p>' .
				sprintf( __( 'Template not found: %s', 'labgenz-community-management' ), $template_name ) .
				'</p></div>';
		}
	}

	/**
	 * Get template part
	 *
	 * @param string $template_name Template name
	 * @param array  $vars Variables to pass to template
	 * @return string
	 */
	public function get_template_part( string $template_name, array $vars = [] ): string {
		ob_start();
		$this->load_template( $template_name, $vars );
		return ob_get_clean();
	}
}
