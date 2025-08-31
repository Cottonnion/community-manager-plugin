<?php

namespace LABGENZ_CM\Core\Authentication;

/**
 * EmailAuthenticator - With Custom Error Messages
 *
 * Fixed to show proper error messages for alias authentication
 */
class EmailAuthenticator {

	private $multi_email_manager;
	private $login_attempts_table;
	private $max_login_attempts = 5;
	private $lockout_duration   = 900; // 15 minutes

	public function __construct() {
		$this->multi_email_manager = new MultiEmailManager();
		global $wpdb;
		$this->login_attempts_table = $wpdb->prefix . 'user_login_attempts';
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress authentication hooks
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'maybe_create_login_attempts_table' ] );

		// Hook MUCH earlier - before WordPress authentication (priority 20)
		add_filter( 'authenticate', [ $this, 'authenticate_with_alias' ], 10, 3 );

		// Remove ALL WordPress default authentication filters to prevent conflicts
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );

		// Add custom error message filters
		add_filter( 'wp_login_errors', [ $this, 'customize_login_errors' ], 10, 2 );

		add_filter( 'wp_authenticate_user', [ $this, 'check_account_lockout' ], 10, 2 );

		// Track failed login attempts
		add_action( 'wp_login_failed', [ $this, 'record_failed_attempt' ] );
		add_action( 'wp_login', [ $this, 'clear_failed_attempts' ], 10, 2 );
		add_action( 'wp_login', [ $this, 'update_last_login_timestamp' ], 10, 2 );

		// Security logging
		add_action( 'wp_login', [ $this, 'log_successful_login' ], 10, 2 );

		// Clean old login attempts
		add_action( 'wp_scheduled_delete', [ $this, 'cleanup_old_attempts' ] );

		// Add custom login form fields
		add_action( 'login_form', [ $this, 'add_login_form_scripts' ] );
	}

	/**
	 * Create table for tracking login attempts - FIXED
	 */
	public function maybe_create_login_attempts_table() {
		global $wpdb;

		if ( get_option( 'user_login_attempts_table_version' ) !== '1.0' ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->login_attempts_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text DEFAULT NULL,
                attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
                status enum('failed', 'success', 'blocked') DEFAULT 'failed',
                user_id bigint(20) unsigned DEFAULT NULL,
                PRIMARY KEY (id),
                KEY email (email),
                KEY ip_address (ip_address),
                KEY attempt_time (attempt_time),
                KEY user_id (user_id)
            ) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( 'user_login_attempts_table_version', '1.0' );
		}
		// REMOVED: Misplaced logging code that was here
	}


	/**
	 * Customize login error messages
	 */
	public function customize_login_errors( $errors, $redirect_to ) {
		// If there are authentication errors, customize them
		if ( $errors->has_errors() ) {
			$error_codes = $errors->get_error_codes();

			foreach ( $error_codes as $code ) {
				$messages = $errors->get_error_messages( $code );

				// Customize specific error messages
				switch ( $code ) {
					case 'invalid_email':
						$errors->remove( $code );
						$email = isset( $_POST['log'] ) ? sanitize_email( $_POST['log'] ) : '';
						$errors->add(
							'invalid_email',
							sprintf(
								'<strong>Error:</strong> The email address <strong>%s</strong> is not registered on this site. Please check your spelling or contact support.',
								esc_html( $email )
							)
						);
						break;

					case 'incorrect_password':
						$errors->remove( $code );
						$username = isset( $_POST['log'] ) ? sanitize_text_field( $_POST['log'] ) : '';
						if ( is_email( $username ) ) {
							$errors->add(
								'incorrect_password',
								'<strong>Error:</strong> The password you entered is incorrect. Please try again or reset your password.'
							);
						} else {
							$errors->add(
								'incorrect_password',
								'<strong>Error:</strong> The password you entered for the username is incorrect.'
							);
						}
						break;

					case 'account_locked':
						$errors->remove( $code );
						$lockout_info = $this->get_lockout_info( isset( $_POST['log'] ) ? sanitize_text_field( $_POST['log'] ) : '' );
						if ( $lockout_info && $lockout_info['minutes_remaining'] > 0 ) {
							$errors->add(
								'account_locked',
								sprintf(
									'<strong>Account Temporarily Locked:</strong> Too many failed login attempts. Please try again in %d minutes.',
									$lockout_info['minutes_remaining']
								)
							);
						} else {
							$errors->add(
								'account_locked',
								'<strong>Account Temporarily Locked:</strong> Too many failed login attempts. Please try again later.'
							);
						}
						break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Main authentication handler - COMPLETELY REPLACES WordPress auth
	 */
	public function authenticate_with_alias( $user, $username, $password ) {
		// Skip if already authenticated or missing credentials
		if ( is_wp_error( $user ) || empty( $username ) || empty( $password ) ) {
			return $user;
		}

		// If already authenticated, allow it
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		$this->log_auth_activity( 0, 'auth_attempt', $username );

		// Handle email-based login (including aliases)
		if ( is_email( $username ) ) {
			// First, check if it's a direct email match
			$direct_user = get_user_by( 'email', $username );
			if ( $direct_user ) {
				$this->log_auth_activity( $direct_user->ID, 'direct_email_found', $username );
				if ( wp_check_password( $password, $direct_user->user_pass, $direct_user->ID ) ) {
					$this->log_auth_activity( $direct_user->ID, 'direct_email_success', $username );
					return $direct_user;
				} else {
					$this->log_auth_activity( $direct_user->ID, 'direct_email_failed', $username );
					return new \WP_Error(
						'incorrect_password',
						'The password you entered is incorrect.'
					);
				}
			}

			// Check if this email is a verified alias
			$alias_user = $this->multi_email_manager->get_user_by_alias( $username );
			if ( $alias_user ) {
				$this->log_auth_activity( $alias_user->ID, 'alias_found', $username );

				// Check if account is locked
				if ( $this->is_account_locked( $username ) ) {
					$this->log_auth_activity( $alias_user->ID, 'alias_locked', $username );
					return new \WP_Error(
						'account_locked',
						'Account temporarily locked due to multiple failed login attempts.'
					);
				}

				// Verify password against the main account
				if ( wp_check_password( $password, $alias_user->user_pass, $alias_user->ID ) ) {
					$this->log_auth_activity( $alias_user->ID, 'alias_login_success', $username );
					$this->log_login_attempt( $username, 'success', $alias_user->ID );
					return $alias_user;
				} else {
					$this->log_auth_activity( $alias_user->ID, 'alias_login_failed', $username );
					$this->log_login_attempt( $username, 'failed', $alias_user->ID );
					return new \WP_Error(
						'incorrect_password',
						'The password you entered is incorrect.'
					);
				}
			}

			// Email not found anywhere
			$this->log_auth_activity( 0, 'unknown_email_attempt', $username );
			return new \WP_Error(
				'invalid_email',
				'Unknown email address. Check your spelling or contact support.'
			);
		}

		// Handle username-based login (replaces wp_authenticate_username_password)
		$user_by_login = get_user_by( 'login', $username );
		if ( $user_by_login ) {
			$this->log_auth_activity( $user_by_login->ID, 'username_found', $username );
			if ( wp_check_password( $password, $user_by_login->user_pass, $user_by_login->ID ) ) {
				$this->log_auth_activity( $user_by_login->ID, 'username_success', $username );
				return $user_by_login;
			} else {
				$this->log_auth_activity( $user_by_login->ID, 'username_failed', $username );
				return new \WP_Error(
					'incorrect_password',
					'The password you entered for the username is incorrect.'
				);
			}
		}

		// Username not found
		$this->log_auth_activity( 0, 'unknown_username_attempt', $username );
		return new \WP_Error(
			'invalid_username',
			sprintf( 'The username <strong>%s</strong> is not registered on this site.', esc_html( $username ) )
		);
	}

	/**
	 * Check if account is locked due to failed attempts
	 */
	public function check_account_lockout( $user, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$email = $user->user_email;

		// Check if primary email is locked
		if ( $this->is_account_locked( $email ) ) {
			return new \WP_Error(
				'account_locked',
				'Account temporarily locked due to multiple failed login attempts. Please try again later.'
			);
		}

		// Check if any of user's aliases are locked
		$aliases = $this->multi_email_manager->get_user_aliases( $user->ID );
		foreach ( $aliases as $alias ) {
			if ( $this->is_account_locked( $alias->alias_email ) ) {
				return new \WP_Error(
					'account_locked',
					'Account temporarily locked due to multiple failed login attempts. Please try again later.'
				);
			}
		}

		return $user;
	}

	/**
	 * Check if email/IP is locked due to failed attempts
	 */
	private function is_account_locked( $email ) {
		global $wpdb;

		$ip_address   = $this->get_client_ip();
		$lockout_time = gmdate( 'Y-m-d H:i:s', time() - $this->lockout_duration );

		// Check failed attempts for this email in the lockout period
		$email_attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->login_attempts_table} 
             WHERE email = %s 
             AND status = 'failed' 
             AND attempt_time > %s",
				$email,
				$lockout_time
			)
		);

		// Check failed attempts from this IP in the lockout period
		$ip_attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->login_attempts_table} 
             WHERE ip_address = %s 
             AND status = 'failed' 
             AND attempt_time > %s",
				$ip_address,
				$lockout_time
			)
		);

		return ( $email_attempts >= $this->max_login_attempts ) || ( $ip_attempts >= ( $this->max_login_attempts * 2 ) );
	}

	/**
	 * Record failed login attempt - FIXED
	 */
	public function record_failed_attempt( $username ) {
		$this->log_auth_activity( 0, 'wp_login_failed_hook', $username );

		// Track both email and username attempts
		$user    = null;
		$user_id = null;

		if ( is_email( $username ) ) {
			$user = get_user_by( 'email', $username );
			if ( ! $user ) {
				$user = $this->multi_email_manager->get_user_by_alias( $username );
			}
		} else {
			$user = get_user_by( 'login', $username );
		}

		if ( $user ) {
			$user_id = $user->ID;
		}

		$this->log_login_attempt( $username, 'failed', $user_id );
	}
	/**
	 * Clear failed attempts on successful login
	 */
	public function clear_failed_attempts( $user_login, $user ) {
		global $wpdb;

		$ip_address = $this->get_client_ip();

		// Clear attempts for user's primary email
		$wpdb->delete(
			$this->login_attempts_table,
			[
				'email'  => $user->user_email,
				'status' => 'failed',
			],
			[ '%s', '%s' ]
		);

		// Clear attempts for user's aliases
		$aliases = $this->multi_email_manager->get_user_aliases( $user->ID );
		foreach ( $aliases as $alias ) {
			$wpdb->delete(
				$this->login_attempts_table,
				[
					'email'  => $alias->alias_email,
					'status' => 'failed',
				],
				[ '%s', '%s' ]
			);
		}

		// Clear IP-based attempts (partial clear to prevent abuse)
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->login_attempts_table} 
             WHERE ip_address = %s 
             AND status = 'failed' 
             AND attempt_time > %s 
             LIMIT 3",
				$ip_address,
				gmdate( 'Y-m-d H:i:s', time() - 3600 ) // Last hour only
			)
		);
	}

	/**
	 * Log login attempt
	 */
	private function log_login_attempt( $email, $status, $user_id = null ) {
		global $wpdb;

		$wpdb->insert(
			$this->login_attempts_table,
			[
				'email'        => $email,
				'ip_address'   => $this->get_client_ip(),
				'user_agent'   => $this->get_user_agent(),
				'status'       => $status,
				'user_id'      => $user_id,
				'attempt_time' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
	}

	/**
	 * Log successful login with additional context
	 */
	public function log_successful_login( $user_login, $user ) {
		return;
		$login_email = '';

		// Determine which email was used to login
		if ( isset( $_POST['log'] ) && is_email( $_POST['log'] ) ) {
			$login_email = sanitize_email( $_POST['log'] );

			// Check if it was an alias
			if ( $login_email !== $user->user_email ) {
				if ( $this->multi_email_manager->is_alias_email( $login_email ) ) {
					error_log( "EmailAuthenticator: User {$user->ID} ({$user->user_login}) logged in using alias: {$login_email}" );
				}
			}
		}

		// Additional security logging
		$this->log_auth_activity( $user->ID, 'successful_login', $login_email );
	}

	/**
	 * Get user's verified emails (primary + aliases)
	 */
	public function get_user_login_emails( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return [];
		}

		$emails = [ $user->user_email ]; // Primary email

		// Add verified aliases
		$aliases = $this->multi_email_manager->get_user_aliases( $user_id );
		foreach ( $aliases as $alias ) {
			if ( $alias->is_verified ) {
				$emails[] = $alias->alias_email;
			}
		}

		return $emails;
	}

	/**
	 * Get login statistics for user
	 */
	public function get_user_login_stats( $user_id, $days = 30 ) {
		global $wpdb;

		$user_emails = $this->get_user_login_emails( $user_id );
		if ( empty( $user_emails ) ) {
			return [];
		}

		$email_placeholders = implode( ',', array_fill( 0, count( $user_emails ), '%s' ) );
		$since_date         = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$params = array_merge( $user_emails, [ $since_date ] );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT email, status, COUNT(*) as count, 
                    MAX(attempt_time) as last_attempt
             FROM {$this->login_attempts_table} 
             WHERE email IN ({$email_placeholders}) 
             AND attempt_time > %s
             GROUP BY email, status 
             ORDER BY last_attempt DESC",
				...$params
			)
		);
	}

	/**
	 * Check for suspicious login patterns
	 */
	public function detect_suspicious_activity( $user_id ) {
		global $wpdb;

		$user_emails = $this->get_user_login_emails( $user_id );
		if ( empty( $user_emails ) ) {
			return false;
		}

		$email_placeholders = implode( ',', array_fill( 0, count( $user_emails ), '%s' ) );
		$recent_time        = gmdate( 'Y-m-d H:i:s', time() - 3600 ); // Last hour

		$params = array_merge( $user_emails, [ $recent_time ] );

		// Check for multiple IPs
		$ip_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_address) 
             FROM {$this->login_attempts_table} 
             WHERE email IN ({$email_placeholders}) 
             AND status = 'success' 
             AND attempt_time > %s",
				...$params
			)
		);

		return $ip_count > 3; // More than 3 different IPs in last hour
	}

	/**
	 * Add login form scripts for enhanced UX
	 */
	public function add_login_form_scripts() {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			setTimeout(() => {
				const usernameField = document.getElementById('user_login');
				if (usernameField) {
					usernameField.setAttribute('placeholder', 'Email Address (including aliases)');
				}
			}, 1000);
		});
		</script>
		<?php
	}

	/**
	 * Clean old login attempts (older than 30 days)
	 */
	public function cleanup_old_attempts() {
		global $wpdb;

		$cleanup_date = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

		$wpdb->delete(
			$this->login_attempts_table,
			[ 'attempt_time <' => $cleanup_date ],
			[ '%s' ]
		);
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', $_SERVER[ $key ] );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get sanitized user agent
	 *
	 * @return string
	 */
	private function get_user_agent() {
		return substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ), 0, 500 );
	}

	/**
	 * Force logout all sessions for security
	 *
	 * @param int $user_id
	 */
	public function force_logout_all_sessions( $user_id ) {
		$sessions = \WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();

		error_log( "EmailAuthenticator: Forced logout all sessions for user {$user_id}" );
	}

	/**
	 * Log authentication activities to custom directory
	 *
	 * @param int    $user_id
	 * @param string $action
	 * @param string $email
	 */
	private function log_auth_activity( $user_id, $action, $email ) {
		return;
		$log_file   = LABGENZ_LOGS_DIR . '/email_auth.log';
		$timestamp  = current_time( 'Y-m-d H:i:s' );
		$ip         = $this->get_client_ip();
		$user_agent = substr( $this->get_user_agent(), 0, 100 );

		$message = "[{$timestamp}] User: {$user_id} | Action: {$action} | Email: {$email} | IP: {$ip} | UA: {$user_agent}" . PHP_EOL;

		if ( ! file_exists( dirname( $log_file ) ) ) {
			wp_mkdir_p( dirname( $log_file ) );
		}

		file_put_contents( $log_file, $message, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get lockout status for email
	 *
	 * @param string $email
	 * @return array|false  Returns array with lockout info or false if not locked
	 */
	public function get_lockout_info( $email ) {
		if ( ! $this->is_account_locked( $email ) ) {
			return false;
		}

		global $wpdb;

		$lockout_time = gmdate( 'Y-m-d H:i:s', time() - $this->lockout_duration );

		$last_attempt = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(attempt_time) FROM {$this->login_attempts_table} 
             WHERE email = %s AND status = 'failed' AND attempt_time > %s",
				$email,
				$lockout_time
			)
		);

		if ( $last_attempt ) {
			$unlock_time = strtotime( $last_attempt ) + $this->lockout_duration;
			return [
				'locked'            => true,
				'unlock_time'       => $unlock_time,
				'minutes_remaining' => max( 0, ceil( ( $unlock_time - time() ) / 60 ) ),
			];
		}

		return false;
	}

	/**
	 * Update user's last login timestamp
	 *
	 * @param string   $user_login
	 * @param \WP_User $user
	 */
	public function update_last_login_timestamp( $user_login, $user ) {
		$timestamp = current_time( 'mysql' ); // MySQL datetime format
		update_user_meta( $user->ID, '_lab_last_login_at', $timestamp );

		// Also store human-readable format
		update_user_meta( $user->ID, '_lab_last_login_readable', current_time( 'Y-m-d H:i:s' ) );
	}
}