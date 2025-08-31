<?php

namespace LABGENZ_CM\Core\Authentication\Account;

use LABGENZ_CM\Account\Templates;

class AccountPageHandler {
	public function __construct() {
		// Add alias emails field to WooCommerce Edit Account form
		add_filter( 'woocommerce_edit_account_form_start', [ $this, 'add_alias_emails_field' ], 20 );
	}

	/**
	 * Output the alias emails field using the template
	 */
	public function add_alias_emails_field() {
		// Load the template file
		require_once LABGENZ_CM_TEMPLATES_DIR . '/account/alias-emails-field.php';

		// Call the function using its full namespace
		\LABGENZ_CM\Account\Templates\render_alias_emails_field();
	}
}
