<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for Labgenz Community Management plugin.
 */
class AjaxHandler {
	/**
	 * Registers AJAX actions for the plugin.
	 *
	 * @param array<string, callable> $actions
	 */
	public function register_ajax_actions( array $actions ): void {
		foreach ( $actions as $action => $callback ) {
			add_action(
				"wp_ajax_{$action}",
				function () use ( $action, $callback ) {
					try {
						call_user_func( $callback );
					} catch ( \Throwable $e ) {
						wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
					}
				}
			);
			add_action( "wp_ajax_nopriv_{$action}", array( $this, 'handle_non_logged_user_requests' ) );
		}
	}

	/**
	 * Handles requests from non-authenticated users.
	 */
	public function handle_non_logged_user_requests(): void {
		wp_send_json_error( array( 'message' => "You're not allowed to do that" ), 401 );
	}

	/**
	 * Processes an AJAX request with security checks.
	 *
	 * @param callable $callback
	 * @param string   $nonce_action
	 */
	public function handle_request( callable $callback, string $nonce_action ): void {
		try {
			$this->validate_nonce( $nonce_action );
			$request_data = $this->sanitize_request_data( $_POST );
			$response     = $this->execute_callback( $callback, $request_data );
			wp_send_json_success( $response );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				),
				403
			);
		}
	}

	private function validate_nonce( string $nonce_action ): void {
		$token = $_POST['_nonce'] ?? $_POST['security'] ?? $_POST['nonce'] ?? null;
		if ( ! $token ) {
			throw new \Exception( 'Security token is missing', 403 );
		}
		$security_token = sanitize_text_field( wp_unslash( $token ) );
		if ( ! wp_verify_nonce( $security_token, $nonce_action ) ) {
			throw new \Exception( 'Invalid security token', 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'Insufficient permissions', 403 );
		}
	}

	private function sanitize_request_data( array $data ): array {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_request_data( $value );
			} else {
				$sanitized[ $key ] = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : $value;
			}
		}
		return $sanitized;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	private function execute_callback( callable $callback, array $data ) {
		$response = call_user_func( $callback, $data );
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$error_code = $error_code ? (int) $error_code : 400; // Fix short ternary
			throw new \Exception( $response->get_error_message(), $error_code );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal method return, not direct output
		return $response;
	}
}
