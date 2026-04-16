<?php
/**
 * Plugin Name: Research Review Portal
 * Description: Research submission and review workflow for conferences, publications, student projects, and grants. Port of the CityU Research Review Portal for WordPress.
 * Version: 1.0.0
 * Author: Kiran Kumar Vejendla
 * Author URI: mailto:vejendlakirankumar@cityu.edu
 * License: MIT
 * Text Domain: research-review-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RRP_DATA_DIR', RRP_PLUGIN_DIR . 'data/' );
define( 'RRP_UPLOADS_DIR', RRP_DATA_DIR . 'uploads/' );

require_once RRP_PLUGIN_DIR . 'includes/class-portal-data.php';
require_once RRP_PLUGIN_DIR . 'includes/class-workflow-engine.php';
require_once RRP_PLUGIN_DIR . 'includes/class-portal-rest.php';
require_once RRP_PLUGIN_DIR . 'includes/class-portal-settings.php';
require_once RRP_PLUGIN_DIR . 'includes/class-user-management.php';
require_once RRP_PLUGIN_DIR . 'includes/class-process-documentation.php';
require_once RRP_PLUGIN_DIR . 'includes/class-auth-provider.php';

class Research_Review_Portal {

	public static function init() {
		add_shortcode( 'research_review_portal', array( __CLASS__, 'shortcode_portal' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render_site_banner' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_site_banner_fallback' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_homepage' ) );
		// Login page branding
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_styles' ) );
		add_action( 'login_head',            array( __CLASS__, 'login_favicon' ) );
		add_filter( 'login_title',           array( __CLASS__, 'login_page_title' ) );
		add_filter( 'login_headerurl',       array( __CLASS__, 'login_header_url' ) );
		add_filter( 'login_headertext',      array( __CLASS__, 'login_header_text' ) );
		// Show login choice: Entra SSO button + local form.
		add_filter( 'login_message',         array( __CLASS__, 'login_choice_message' ) );
		// Clear the Entra session flag when a user logs in via the standard WP form.
		add_action( 'wp_login', array( __CLASS__, 'clear_entra_session_flag' ), 10, 2 );
		// Clear the Entra session flag on any logout (before session is destroyed).
		add_action( 'wp_logout', array( __CLASS__, 'clear_entra_session_flag_on_logout' ) );
		// Block locked accounts from logging in via username+password.
		add_filter( 'authenticate', array( __CLASS__, 'block_locked_users' ), 30, 3 );
		// Block SSO-provisioned accounts from logging in via username+password.
		add_filter( 'authenticate', array( __CLASS__, 'block_sso_users_from_password_login' ), 31, 3 );
		// Replace verbose WP core auth errors with a generic message to prevent
		// username enumeration (OWASP A07). Runs last (priority 99) so our own
		// meaningful errors (account_locked, sso_account_no_password) are seen first.
		add_filter( 'authenticate', array( __CLASS__, 'normalize_auth_errors' ), 99, 3 );
		// Track successive failed login attempts; auto-lock after 3 failures.
		add_action( 'wp_login_failed', array( __CLASS__, 'track_failed_login' ) );
		// Reset the failure counter whenever a user logs in successfully.
		add_action( 'wp_login', array( __CLASS__, 'reset_login_counter' ), 9, 2 );
	}

	/**
	 * Inject a "Sign in with Microsoft" button above the standard WP login form
	 * whenever Entra SSO is configured and enabled.
	 *
	 * The local username/password form is always shown below — no auto-redirect.
	 * This gives users and administrators a clear choice and avoids lock-outs.
	 */
	public static function login_choice_message( $message ) {
		$action = sanitize_key( $_GET['action'] ?? 'login' );
		if ( $action !== 'login' ) {
			return $message;
		}
		if ( ! class_exists( 'RRP_Auth_Provider' ) || ! RRP_Auth_Provider::is_entra_active() ) {
			return $message;
		}
		$redirect_to = esc_url_raw( $_GET['redirect_to'] ?? home_url( '/' ) );
		$entra_url   = RRP_Auth_Provider::get_login_url( $redirect_to );
		$btn = '<div id="rrp-sso-choice">'
			. '<a href="' . esc_url( $entra_url ) . '" class="button button-hero rrp-entra-btn">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 21 21" style="vertical-align:middle;margin-right:8px">'
			. '<path d="M10 0H0v10h10V0z" fill="#F25022"/>'
			. '<path d="M21 0H11v10h10V0z" fill="#7FBA00"/>'
			. '<path d="M10 11H0v10h10V11z" fill="#00A4EF"/>'
			. '<path d="M21 11H11v10h10V11z" fill="#FFB900"/>'
			. '</svg>'
			. 'Sign in with Microsoft'
			. '</a>'
			. '<div class="rrp-divider"><span>or sign in with username &amp; password</span></div>'
			. '</div>';
		return $message . $btn;
	}

	/**
	 * Replace all WP core authentication error messages with a single generic
	 * "Invalid username or password." response to prevent username enumeration
	 * (OWASP A07 — Identification and Authentication Failures).
	 *
	 * Portal-specific error codes (account_locked, sso_account_no_password) are
	 * intentionally preserved — they carry actionable guidance for the user.
	 * ALL other auth errors (invalid_username, invalid_email, incorrect_password,
	 * empty_username, empty_password, and any future WP core codes) are collapsed
	 * into the single generic message so attackers cannot enumerate valid usernames.
	 */
	public static function normalize_auth_errors( $user, $username, $password ) {
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}
		$preserve = array( 'account_locked', 'sso_account_no_password' );
		if ( in_array( $user->get_error_code(), $preserve, true ) ) {
			return $user;
		}
		return new WP_Error(
			'authentication_failed',
			__( '<strong>Error:</strong> Invalid username or password.', 'rrp' )
		);
	}

	/**
	 * Track successive failed password-based login attempts per user account.
	 * After 3 failures the account is automatically locked (rrp_locked = 1) and
	 * all active sessions are destroyed immediately.
	 *
	 * SSO accounts are excluded — they cannot authenticate via password anyway.
	 * Already-locked accounts are skipped to avoid unnecessary DB writes.
	 *
	 * @param string $username Username or email address that failed to log in.
	 */
	public static function track_failed_login( $username ) {
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}
		if ( ! $user ) {
			return; // Unknown username — no account record to update.
		}
		// SSO-managed accounts have password login blocked separately.
		if ( get_user_meta( $user->ID, 'rrp_entra_oid', true ) ) {
			return;
		}
		// Account already locked — no need to increment further.
		if ( get_user_meta( $user->ID, 'rrp_locked', true ) ) {
			return;
		}
		$attempts = (int) get_user_meta( $user->ID, 'rrp_failed_login_count', true ) + 1;
		update_user_meta( $user->ID, 'rrp_failed_login_count', $attempts );
		if ( $attempts >= 3 ) {
			update_user_meta( $user->ID, 'rrp_locked', '1' );
			// Destroy all active sessions so any hijacked session is terminated.
			$sessions = WP_Session_Tokens::get_instance( $user->ID );
			$sessions->destroy_all();
		}
	}

	/**
	 * Clear the failed-login counter when a user authenticates successfully.
	 * Registered at priority 9 to run before other wp_login handlers.
	 */
	public static function reset_login_counter( $user_login, $user ) {
		delete_user_meta( $user->ID, 'rrp_failed_login_count' );
	}

	/**
	 * Prevent SSO-provisioned accounts from completing a username+password login.
	 *
	 * Users whose account was created or linked to Entra ID (they have the
	 * rrp_entra_oid usermeta) must authenticate exclusively through the SSO
	 * flow.  Password-based login is blocked so that a guessed / reset password
	 * cannot be used as an alternative entry point into an Entra-managed account.
	 *
	 * Only enforced when Entra SSO is actually configured; if SSO is disabled the
	 * restriction is lifted so administrators can still access their accounts.
	 */
	public static function block_sso_users_from_password_login( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}
		// Only enforce while Entra SSO is active — avoids admin lockout if SSO is disabled.
		if ( ! class_exists( 'RRP_Auth_Provider' ) || ! RRP_Auth_Provider::is_entra_active() ) {
			return $user;
		}
		if ( get_user_meta( $user->ID, 'rrp_entra_oid', true ) ) {
			$sso_url = esc_url( RRP_Auth_Provider::get_login_url() );
			return new WP_Error(
				'sso_account_no_password',
				sprintf(
					/* translators: %s = SSO login URL */
					__( 'This account is managed by your institution. Please <a href="%s">sign in with Microsoft</a> instead.', 'rrp' ),
					$sso_url
				)
			);
		}
		return $user;
	}

	/**
	 * Clear the Entra session flag when a user logs in via the standard WP password form.
	 * Hooked to wp_login (fires after successful credential authentication).
	 */
	public static function clear_entra_session_flag( $user_login, $user ) {
		delete_user_meta( $user->ID, 'rrp_session_via_entra' );
	}

	/**
	 * Prevent locked accounts from completing login (password or any auth method
	 * that goes through the authenticate filter).
	 */
	public static function block_locked_users( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}
		if ( get_user_meta( $user->ID, 'rrp_locked', true ) ) {
			return new WP_Error(
				'account_locked',
				__( 'Your account has been locked. Please contact the portal administrator.', 'rrp' )
			);
		}
		return $user;
	}

	/**
	 * Clear the Entra session flag when a user logs out (any provider).
	 * Hooked to wp_logout which fires before the session is destroyed,
	 * so get_current_user_id() is still valid.
	 */
	public static function clear_entra_session_flag_on_logout() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, 'rrp_session_via_entra' );
		}
	}

	public static function login_favicon() {
		$logo_raw = RRP_Portal_Settings::get( 'university_logo_url' );
		$logo_url = esc_url( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' );
		echo '<link rel="icon" href="' . $logo_url . '" type="image/svg+xml">' . "\n";
		echo '<link rel="shortcut icon" href="' . $logo_url . '">' . "\n";
	}

	public static function login_page_title() {
		$portal_name = RRP_Portal_Settings::get( 'portal_name' ) ?: 'CityU Research Portal';
		return esc_html( $portal_name ) . ' &#8212; Login';
	}

	public static function login_styles() {
		$logo_raw = RRP_Portal_Settings::get( 'university_logo_url' );
		$logo_url = esc_url( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' );
		$portal_name = RRP_Portal_Settings::get( 'portal_name' ) ?: 'Research Review Portal';
		$css = '
#login h1 a, .login h1 a {
	background-image: url(' . $logo_url . ') !important;
	background-size: contain !important;
	background-repeat: no-repeat !important;
	background-position: center !important;
	width: 240px !important;
	height: 70px !important;
	display: block !important;
	filter: none !important;
}
body.login { background: #f4f6f9 !important; }
body.login #login { padding-top: 40px; }
body.login #wp-auth-check-wrap #wp-auth-check { border-top: 4px solid #002f52; }
body.login .button-primary { background: #002f52 !important; border-color: #002f52 !important; }
body.login .button-primary:hover, body.login .button-primary:focus { background: #005a99 !important; border-color: #005a99 !important; }
/* SSO choice UI */
#rrp-sso-choice { margin-bottom: 16px; text-align: center; }
.rrp-entra-btn {
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	width: 100% !important;
	padding: 10px 16px !important;
	background: #fff !important;
	border: 1px solid #ccc !important;
	border-radius: 4px !important;
	color: #333 !important;
	font-size: 15px !important;
	font-weight: 500 !important;
	text-decoration: none !important;
	box-shadow: 0 1px 3px rgba(0,0,0,.12);
	transition: box-shadow .15s;
}
.rrp-entra-btn:hover { box-shadow: 0 2px 6px rgba(0,0,0,.2) !important; border-color: #0078d4 !important; }
.rrp-divider {
	display: flex;
	align-items: center;
	margin: 20px 0 8px;
	color: #888;
	font-size: 13px;
}
.rrp-divider::before, .rrp-divider::after {
	content: "";
	flex: 1;
	height: 1px;
	background: #ddd;
}
.rrp-divider span { padding: 0 10px; white-space: nowrap; }
';
		// Attach inline CSS to the built-in login stylesheet so it is printed
		// AFTER login.css — this ensures our logo overrides the WP default.
		wp_add_inline_style( 'login', $css );
	}

	public static function login_header_url() {
		return home_url( '/' );
	}

	public static function login_header_text() {
		return 'City University of Seattle — Research Review Portal';
	}

	/**
	 * Replace the stored site hostname with the actual request hostname.
	 * Hooked to option_siteurl / option_home so it fires before redirect_canonical.
	 * Receives the raw option value as $url — must NOT call get_option() to avoid recursion.
	 */
	public static function fix_url_host( $url ) {
		if ( ! isset( $_SERVER['HTTP_HOST'] ) || '' === $_SERVER['HTTP_HOST'] ) {
			return $url;
		}
		$stored_host   = (string) ( parse_url( $url, PHP_URL_HOST ) ?: '' );
		if ( '' === $stored_host ) {
			return $url;
		}
		$stored_scheme = (string) ( parse_url( $url, PHP_URL_SCHEME ) ?: 'http' );
		$stored_port   = parse_url( $url, PHP_URL_PORT );
		$stored_origin = $stored_scheme . '://' . $stored_host . ( $stored_port ? ':' . (string) $stored_port : '' );
		$scheme         = is_ssl() ? 'https' : 'http';
		$request_origin = $scheme . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		if ( $stored_origin !== $request_origin ) {
			$url = str_replace( $stored_origin, $request_origin, $url );
		}
		return $url;
	}

	/**
	 * Prevent redirect_canonical from sending browsers to the stored (localhost) URL
	 * when accessed from an external hostname.
	 */
	public static function no_localhost_redirect( $redirect_url, $requested_url ) {
		if ( ! isset( $_SERVER['HTTP_HOST'] ) || '' === $_SERVER['HTTP_HOST'] ) {
			return $redirect_url;
		}
		$request_host  = (string) ( parse_url( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ), PHP_URL_HOST ) ?: '' );
		$redirect_host = (string) ( parse_url( $redirect_url, PHP_URL_HOST ) ?: '' );
		if ( '' !== $redirect_host && $redirect_host !== $request_host ) {
			return false; // suppress redirect to wrong host
		}
		return $redirect_url;
	}

	public static function render_site_banner() {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return;
		}

		$logged_in = is_user_logged_in();
		$login_url  = RRP_Auth_Provider::get_login_url( home_url() );
		$logout_url = RRP_Auth_Provider::get_logout_url( home_url() );
		$current_user = wp_get_current_user();
		$user_name  = $current_user->exists() ? ( $current_user->display_name ?: $current_user->user_login ) : '';
		$user_roles = $current_user->exists() ? $current_user->roles : array();
		$role_labels = RRP_User_Management::get_role_labels( $user_roles );
		$role_label  = implode( ' · ', $role_labels );

		$logo_url = esc_url( RRP_PLUGIN_URL . 'assets/city-university-logo.svg' );
		echo '<div class="rrp-site-banner ' . ( $logged_in ? 'rrp-site-banner-loggedin' : 'rrp-site-banner-guest' ) . '">' .
			'<div class="rrp-site-banner_left">' .
			'<img src="' . $logo_url . '" alt="City University of Seattle" class="rrp-site-logo">' .
			'<span class="rrp-site-banner-brand">Research Review Portal</span>' .
			'</div>';

		echo '<div class="rrp-site-banner_content">';
		if ( $logged_in ) {
			echo '<span>Logged in as <strong>' . esc_html( $user_name ) . '</strong> (' . esc_html( $role_label ?: 'User' ) . ')</span>';
		}
		echo '</div>';

		echo '<div class="rrp-site-banner_actions">';
		if ( $logged_in ) {
			echo '<a class="rrp-btn secondary" href="' . esc_url( add_query_arg( 'portal', '1', home_url( '/' ) ) ) . '">My Portal</a>';
			echo '<a class="rrp-btn" href="' . esc_url( $logout_url ) . '">Logout</a>';
		} else {
			echo '<a class="rrp-btn" href="' . esc_url( $login_url ) . '">Login</a>';
		}
		echo '</div></div>';
	}

	public static function render_site_banner_fallback() {
		// If theme doesn't support wp_body_open, render in head and use JS to move into body start.
		?>
		<style>
		.rrp-site-banner { box-sizing: border-box; width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 1.5rem; z-index: 99999; font-size: 0.9rem; position: sticky; top: 0; }
		.rrp-site-banner-guest { background: #003d66; border-bottom: 2px solid #002a4a; color: #fff; }
		.rrp-site-banner-loggedin { background: #fff; border-bottom: 2px solid #e0e0e0; color: #1a1a1a; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
		.rrp-site-banner_left { display: flex; align-items: center; gap: 0.75rem; }
		.rrp-site-logo { height: 28px; width: auto; display: block; filter: brightness(0) invert(1); }
		.rrp-site-banner-loggedin .rrp-site-logo { filter: none; }
		.rrp-site-banner-brand { font-weight: 700; letter-spacing: 0.01em; font-size: 0.9rem; color: rgba(255,255,255,0.88); border-left: 1px solid rgba(255,255,255,0.3); padding-left: 0.75rem; }
		.rrp-site-banner-loggedin .rrp-site-banner-brand { color: #003d66; border-left-color: #ccc; }
		.rrp-site-banner_content { flex: 1; padding: 0 1rem; font-size: 0.85rem; }
		.rrp-site-banner .rrp-site-banner_actions a { margin-left: 0.5rem; }
		.rrp-site-banner .rrp-btn { padding: 0.38rem 1rem; border-radius: 6px; border: none; font-size: 0.82rem; font-weight: 600; cursor: pointer; background: #fff; color: #003d66; text-decoration: none; display: inline-block; }
		.rrp-site-banner-loggedin .rrp-btn { background: #003d66; color: #fff; }
		.rrp-site-banner .rrp-btn.secondary { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.35); }
		.rrp-site-banner-loggedin .rrp-btn.secondary { background: transparent; color: #003d66; border: 1px solid #003d66; }
		</style>
		<script>
		(function() {
			var banner = document.querySelector('.rrp-site-banner');
			if (!banner) return;
			var body = document.body;
			if (body.firstChild && body.firstChild.nodeName !== 'STYLE') {
				body.insertBefore(banner, body.firstChild);
			}
		})();
		</script>
		<?php
	}


	public static function shortcode_portal( $atts ) {
		do_action( 'rrp_portal_enqueue' );
		wp_enqueue_style(
			'research-review-portal',
			RRP_PLUGIN_URL . 'assets/portal.css',
			array(),
			(string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.css' )
		);
		wp_enqueue_script(
			'mammoth-js',
			RRP_PLUGIN_URL . 'assets/mammoth.browser.min.js',
			array(),
			'1.7.0',
			true
		);
		wp_enqueue_script(
			'jszip-js',
			RRP_PLUGIN_URL . 'assets/jszip.min.js',
			array(),
			'3.10.1',
			true
		);
		wp_enqueue_script(
			'docx-preview-js',
			RRP_PLUGIN_URL . 'assets/docx-preview.min.js',
			array( 'jszip-js' ),
			'0.3.6',
			true
		);
		wp_enqueue_script(
			'research-review-portal',
			RRP_PLUGIN_URL . 'assets/portal.js',
			array( 'mammoth-js', 'docx-preview-js' ),
			(string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.js' ),
			true
		);

		$logged_in = is_user_logged_in();
		$login_url  = RRP_Auth_Provider::get_login_url( get_permalink() );
		$logout_url = RRP_Auth_Provider::get_logout_url( get_permalink() );
		$current_user = wp_get_current_user();
		$user_name  = $current_user->exists() ? ( $current_user->display_name ?: $current_user->user_login ) : '';
		$user_roles = $current_user->exists() ? $current_user->roles : array();
		$role_labels = RRP_User_Management::get_role_labels( $user_roles );
		$role_label  = implode( ' · ', $role_labels );

		// Public submissions config — needed for guest registration option
		$_pub_cfg            = Portal_Data::read_config()['publicSubmissions'] ?? array();
		$_pub_enabled        = ! empty( $_pub_cfg['enabled'] );
		$_pub_allowed_types  = is_array( $_pub_cfg['allowedTypes'] ?? null ) ? array_values( $_pub_cfg['allowedTypes'] ) : array();

		// F12: PII fields (firstName, lastName, degree, department, expertise) are intentionally
		// omitted from window.RRP. They are fetched on-demand via GET /portal-users/me and
		// never broadcast to the global JS scope.
		wp_add_inline_script( 'research-review-portal', sprintf(
			'window.RRP = { restBase: %s, nonce: %s, isLoggedIn: %s, loginUrl: %s, logoutUrl: %s, userName: %s, userRole: %s, userRoles: %s, userEmail: %s, userId: %s, universityName: %s, universityShortName: %s, universityLogo: %s, portalName: %s, ssoEnabled: %s, ssoProvider: %s, publicSubmissionsEnabled: %s, publicAllowedTypes: %s };',
			wp_json_encode( rest_url( 'research-portal/v1' ) ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) ),
			wp_json_encode( $logged_in ),
			wp_json_encode( $login_url ),
			wp_json_encode( $logout_url ),
			wp_json_encode( $user_name ),
			wp_json_encode( $role_labels[0] ?? '' ),
			wp_json_encode( $role_labels ),
			wp_json_encode( $current_user->exists() ? $current_user->user_email : '' ),
			wp_json_encode( $current_user->exists() ? (int) $current_user->ID : 0 ),
			wp_json_encode( RRP_Portal_Settings::get( 'university_name' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'university_short_name' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'university_logo_url' ) ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' ),
			wp_json_encode( RRP_Portal_Settings::get( 'portal_name' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_enabled' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_provider' ) ),
			wp_json_encode( $_pub_enabled ),
			wp_json_encode( $_pub_allowed_types )
		), 'before' );

		ob_start();

		$show_portal = isset( $_GET['portal'] ) && '1' === $_GET['portal'];

		if ( ! $show_portal || ! $logged_in ) {
			?>
			<div id="research-review-portal" class="rrp-portal rrp-homepage">

				<!-- ── Hero ── -->
				<div class="rrp-hero">
					<div class="rrp-hero-inner">
						<div class="rrp-hero-badge">City University of Seattle &middot; School of Technology and Computing</div>
						<h1 class="rrp-hero-title">CityU Research<br>Review Portal</h1>
						<p class="rrp-hero-sub">A unified platform for submitting and tracking Doctoral Dissertations, Capstone Projects, Research Papers, and Grant Proposals&mdash;each with its own structured multi-stage review and approval workflow.</p>
						<?php if ( $logged_in ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'portal', '1', get_permalink() ) ); ?>" class="rrp-btn rrp-btn-hero">Go to My Dashboard &rarr;</a>
						<?php else : ?>
							<a href="<?php echo esc_url( $login_url ); ?>" class="rrp-btn rrp-btn-hero">Login to Submit &rarr;</a>
						<?php endif; ?>
					</div>
				</div>

				<!-- ── About ── -->
				<section class="rrp-home-section rrp-about-section">
					<h2 class="rrp-home-section-title">About This Portal</h2>
					<div class="rrp-about-body">
						<p>The CityU Research Review Portal is a centralised digital platform developed by CityU School of Technology and Computing (STC). It provides faculty, students, and research coordinators at <strong>City University of Seattle</strong> with a single, transparent system for submitting and tracking all academic research work through structured multi-stage review and approval workflows.</p>
						<p>The portal ensures that every submission&mdash;whether a doctoral dissertation, capstone project, research paper, or grant proposal&mdash;receives consistent, accountable review by the right people at the right stage. All participants can monitor progress in real time, and reviewers are notified automatically when a submission enters their stage.</p>
						<div class="rrp-about-highlights">
							<div class="rrp-about-highlight">
								<span class="rrp-about-hl-icon">&#128101;</span>
								<div><strong>Role-Based Access</strong><br>Students, advisors, committee members, program directors, and dissertation directors each have a dedicated view and action set.</div>
							</div>
							<div class="rrp-about-highlight">
								<span class="rrp-about-hl-icon">&#128203;</span>
								<div><strong>Multi-Stage Workflows</strong><br>Every document type has its own approval path. Submissions move forward only after all required approvals are collected at each stage.</div>
							</div>
							<div class="rrp-about-highlight">
								<span class="rrp-about-hl-icon">&#128276;</span>
								<div><strong>Real-Time Notifications</strong><br>Reviewers are automatically notified when a submission reaches their stage. Students receive updates as decisions are made.</div>
							</div>
							<div class="rrp-about-highlight">
								<span class="rrp-about-hl-icon">&#128196;</span>
								<div><strong>Document Management</strong><br>Supporting files can be attached to each submission. Drafts are saved automatically, and the full revision history is preserved.</div>
							</div>
						</div>
					</div>
				</section>

				<!-- ── Submission Type Tabs ── -->
				<section class="rrp-home-section rrp-tabs-section">
					<h2 class="rrp-home-section-title">Submission Types &amp; Approval Processes</h2>
					<p class="rrp-home-section-sub">Select a document type to see its full approval workflow and the types of documents accepted under each category.</p>

					<div class="rrp-tabs" role="tablist">
						<button class="rrp-tab rrp-tab-active" role="tab" aria-selected="true"  data-tab="dissertation">&#127979; Doctoral Dissertation</button>
						<button class="rrp-tab" role="tab" aria-selected="false" data-tab="capstone">&#127891; Capstone Project</button>
						<button class="rrp-tab" role="tab" aria-selected="false" data-tab="research">&#128196; Research Paper</button>
						<button class="rrp-tab" role="tab" aria-selected="false" data-tab="grant">&#128181; Grant Proposal</button>
					</div>

					<!-- Doctoral Dissertation -->
					<div class="rrp-tab-panel rrp-tab-panel-active" data-panel="dissertation">
						<div class="rrp-tab-intro">
							<h3>Doctoral Dissertation</h3>
							<p>Doctoral students submit their dissertation through a structured multi-member approval chain. The Chair reviews first, followed by mandatory approval from all committee members, then the Program Director, and finally the Dissertation Director signs off on the completed work.</p>
							<p><strong>Accepted documents:</strong> Full dissertation manuscripts, proposal drafts, prospectus documents, and final defence submissions.</p>
						</div>
						<div class="rrp-flow">
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128100;</div>
								<div class="rrp-flow-label">Student Submits</div>
								<div class="rrp-flow-desc">Student uploads the dissertation document and supporting materials through the portal.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128081;</div>
								<div class="rrp-flow-label">Chair Review &amp; Approval</div>
								<div class="rrp-flow-desc">The dissertation chair reviews the submission. The chair may request revisions or approve it to advance to the committee.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step rrp-flow-step-multi">
								<div class="rrp-flow-icon">&#128101;</div>
								<div class="rrp-flow-label">Committee Member Review <span class="rrp-flow-tag">All members must approve</span></div>
								<div class="rrp-flow-desc">All assigned committee members independently review the dissertation. The submission advances only when every committee member has provided their approval.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#127970;</div>
								<div class="rrp-flow-label">Program Director Approval</div>
								<div class="rrp-flow-desc">The Program Director conducts a final academic review and confirms the dissertation meets all program requirements.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step rrp-flow-step-final">
								<div class="rrp-flow-icon">&#9989;</div>
								<div class="rrp-flow-label">Dissertation Director Sign-Off</div>
								<div class="rrp-flow-desc">The Dissertation Director provides the final institutional approval. Once signed off, the dissertation is officially approved and the student is notified.</div>
							</div>
						</div>
					</div>

					<!-- Capstone Project -->
					<div class="rrp-tab-panel" data-panel="capstone">
						<div class="rrp-tab-intro">
							<h3>Capstone Project</h3>
							<p>Undergraduate and graduate students submit their capstone project proposals through a two-stage review path. An assigned academic advisor reviews and approves the work first, followed by the Program Director who provides the final approval to complete the process.</p>
							<p><strong>Accepted documents:</strong> Capstone proposals, project plans, feasibility studies, interim reports, and final project deliverables.</p>
						</div>
						<div class="rrp-flow">
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128100;</div>
								<div class="rrp-flow-label">Student Submits</div>
								<div class="rrp-flow-desc">Student uploads their capstone project document and any required supporting materials.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128203;</div>
								<div class="rrp-flow-label">Advisor Review &amp; Approval</div>
								<div class="rrp-flow-desc">The assigned academic advisor reviews the project for academic merit, scope, and feasibility. The advisor may request revisions before approving.</div>
							</div>
							<div class="rrp-flow-arrow">&#8595;</div>
							<div class="rrp-flow-step rrp-flow-step-final">
								<div class="rrp-flow-icon">&#9989;</div>
								<div class="rrp-flow-label">Program Director Approval</div>
								<div class="rrp-flow-desc">The Program Director provides the final approval. Once approved, the capstone project is officially accepted and the student is notified of completion.</div>
							</div>
						</div>
					</div>

					<!-- Research Paper -->
					<div class="rrp-tab-panel" data-panel="research">
						<div class="rrp-tab-intro">
							<h3>Research Paper</h3>
							<p>Research papers submitted for the <strong>Applied Research Symposium (ARS)</strong>, the <strong>Doctor of IT Forum (DIT)</strong>, or for journal and conference publication go through a rigorous peer review process. Papers are screened for completeness, reviewed by matched expert reviewers, consolidated, and given a final editorial decision.</p>
							<p><strong>Accepted documents:</strong> Full research papers, extended abstracts, journal manuscripts, book chapters, poster submissions, and technical reports.</p>
						</div>
						<div class="rrp-flow rrp-flow-horizontal">
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128100;</div>
								<div class="rrp-flow-label">Student / Author Submits</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128270;</div>
								<div class="rrp-flow-label">Initial Screening</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128101;</div>
								<div class="rrp-flow-label">Reviewer Assignment</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128064;</div>
								<div class="rrp-flow-label">Peer Review</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128221;</div>
								<div class="rrp-flow-label">Review Consolidation</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step rrp-flow-step-final">
								<div class="rrp-flow-icon">&#9989;</div>
								<div class="rrp-flow-label">Final Decision &amp; Confirmation</div>
							</div>
						</div>
						<div class="rrp-tab-note">&#8505; Each stage is handled by specialist reviewers matched to the paper&rsquo;s subject area. Authors may be invited to submit revisions before the final decision is issued.</div>
					</div>

					<!-- Grant Proposal -->
					<div class="rrp-tab-panel" data-panel="grant">
						<div class="rrp-tab-intro">
							<h3>Grant Proposal</h3>
							<p>Internal and external funding applications go through an extensive seven-stage review. Proposals are checked for compliance, assigned to reviewers, assessed across multiple criteria by a committee, presented in a committee meeting, and then given a final funding decision before the institution provides submission tracking support.</p>
							<p><strong>Accepted documents:</strong> Internal research grant applications, external government funding proposals, industry partnership applications, and seed-funding requests.</p>
						</div>
						<div class="rrp-flow rrp-flow-horizontal">
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128100;</div>
								<div class="rrp-flow-label">Applicant Submits</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128203;</div>
								<div class="rrp-flow-label">Compliance Check</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128101;</div>
								<div class="rrp-flow-label">Review Assignment</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step rrp-flow-step-multi">
								<div class="rrp-flow-icon">&#128211;</div>
								<div class="rrp-flow-label">Multi-Criteria Review <span class="rrp-flow-tag">Committee</span></div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#128106;</div>
								<div class="rrp-flow-label">Committee Meeting</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step">
								<div class="rrp-flow-icon">&#9878;&#65039;</div>
								<div class="rrp-flow-label">Final Decision</div>
							</div>
							<div class="rrp-flow-arrow rrp-flow-arrow-h">&#8594;</div>
							<div class="rrp-flow-step rrp-flow-step-final">
								<div class="rrp-flow-icon">&#9989;</div>
								<div class="rrp-flow-label">Development Support &amp; Tracking</div>
							</div>
						</div>
						<div class="rrp-tab-note">&#8505; Once a grant is approved, the STC research office provides ongoing development support and monitors submission milestones until the grant application is lodged with the funding body.</div>
					</div>

				</section>

				<!-- ── How It Works ── -->
				<section class="rrp-home-section">
					<h2 class="rrp-home-section-title">How It Works</h2>
					<div class="rrp-how-it-works">
						<div class="rrp-how-step">
							<div class="rrp-how-icon">1</div>
							<h4>Create an Account</h4>
							<p>Sign in with your CityU credentials. Your assigned role&mdash;student, advisor, committee member, or director&mdash;determines which dashboards and actions are available to you.</p>
						</div>
						<div class="rrp-how-step">
							<div class="rrp-how-icon">2</div>
							<h4>Submit Your Document</h4>
							<p>Choose your submission type, complete the form, and attach supporting files. Drafts are saved automatically as you work, so you can return and continue at any time.</p>
						</div>
						<div class="rrp-how-step">
							<div class="rrp-how-icon">3</div>
							<h4>Track Review Progress</h4>
							<p>Monitor your submission through each approval stage in real time. Receive notifications when reviewer feedback arrives and respond with revisions when required.</p>
						</div>
					</div>
				</section>

				<!-- ── CTA Footer ── -->
				<div class="rrp-home-cta">
					<h3>Ready to submit your research?</h3>
					<p>Log in with your City University of Seattle credentials to access your personal dashboard.</p>
					<?php if ( $logged_in ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'portal', '1', get_permalink() ) ); ?>" class="rrp-btn rrp-btn-hero">Go to My Dashboard &rarr;</a>
					<?php else : ?>
						<a href="<?php echo esc_url( $login_url ); ?>" class="rrp-btn rrp-btn-hero">Login Now &rarr;</a>
					<?php endif; ?>
				</div>

			</div>
			<script>
			(function(){
				var tabs = document.querySelectorAll('.rrp-tab');
				var panels = document.querySelectorAll('.rrp-tab-panel');
				tabs.forEach(function(tab){
					tab.addEventListener('click', function(){
						tabs.forEach(function(t){ t.classList.remove('rrp-tab-active'); t.setAttribute('aria-selected','false'); });
						panels.forEach(function(p){ p.classList.remove('rrp-tab-panel-active'); });
						tab.classList.add('rrp-tab-active');
						tab.setAttribute('aria-selected','true');
						var target = tab.getAttribute('data-tab');
						var panel = document.querySelector('.rrp-tab-panel[data-panel="' + target + '"]');
						if(panel) panel.classList.add('rrp-tab-panel-active');
					});
				});
			})();
			</script>
			<?php
			return ob_get_clean();
		}

		?>
		<div id="research-review-portal" class="rrp-portal" data-rest-base="<?php echo esc_attr( rest_url( 'research-portal/v1' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<div class="rrp-loading">Loading portal…</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── Full-page intercept via template_redirect ──────────────────────────────

	public static function maybe_render_homepage() {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_front_page() && ! is_home() ) {
			return;
		}
		$show_portal = isset( $_GET['portal'] ) && '1' === $_GET['portal'];
		if ( $show_portal && is_user_logged_in() ) {
			self::output_portal_page();
			exit;
		}
		if ( is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'portal', '1', home_url( '/' ) ) );
			exit;
		}
		// Non-logged-in: if ?portal=1 and public submissions enabled, serve portal JS page
		// so renderSelection() / renderPublicRegistration() in JS can handle the form.
		if ( $show_portal ) {
			$_pr_cfg = Portal_Data::read_config()['publicSubmissions'] ?? array();
			if ( ! empty( $_pr_cfg['enabled'] ) ) {
				self::output_public_portal_page();
				exit;
			}
		}
		self::output_guest_homepage();
		exit;
	}

	private static function output_portal_page() {
		show_admin_bar( false );
		$current_user = wp_get_current_user();
		$user_name    = $current_user->exists() ? ( $current_user->display_name ?: $current_user->user_login ) : '';
		$user_roles   = $current_user->exists() ? $current_user->roles : array();
		$role_labels  = RRP_User_Management::get_role_labels( $user_roles );
		$role_label   = $role_labels[0] ?? '';
		$logout_url   = esc_url( RRP_Auth_Provider::get_logout_url( home_url( '/' ) ) );

		// Load configurable branding
		$univ_name   = RRP_Portal_Settings::get( 'university_name' );
		$univ_short  = RRP_Portal_Settings::get( 'university_short_name' );
		$portal_name = RRP_Portal_Settings::get( 'portal_name' );
		$logo_raw    = RRP_Portal_Settings::get( 'university_logo_url' );
		$logo_url    = esc_url( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' );

		wp_enqueue_style( 'research-review-portal', RRP_PLUGIN_URL . 'assets/portal.css', array(), (string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.css' ) );
		wp_enqueue_script( 'mammoth-js', RRP_PLUGIN_URL . 'assets/mammoth.browser.min.js', array(), '1.7.0', true );
		wp_enqueue_script( 'jszip-js', RRP_PLUGIN_URL . 'assets/jszip.min.js', array(), '3.10.1', true );
		wp_enqueue_script( 'docx-preview-js', RRP_PLUGIN_URL . 'assets/docx-preview.min.js', array( 'jszip-js' ), '0.3.6', true );
		wp_enqueue_script( 'research-review-portal', RRP_PLUGIN_URL . 'assets/portal.js', array( 'mammoth-js', 'docx-preview-js' ), (string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.js' ), true );
		// Public submissions config
		$_pub_cfg2           = Portal_Data::read_config()['publicSubmissions'] ?? array();
		$_pub_enabled2       = ! empty( $_pub_cfg2['enabled'] );
		$_pub_allowed_types2 = is_array( $_pub_cfg2['allowedTypes'] ?? null ) ? array_values( $_pub_cfg2['allowedTypes'] ) : array();

		$rrp_allowed_types = (array) ( get_user_meta( get_current_user_id(), 'rrp_allowed_submission_types', true ) ?: [] );
		// Public users get submission types from the public submissions config
		if ( in_array( 'rrp_public', $user_roles, true ) ) {
			$rrp_allowed_types = $_pub_allowed_types2;
		}
		$rrp_program_ids   = (array) ( get_user_meta( get_current_user_id(), 'rrp_program_ids', true ) ?: [] );
		// F12: PII fields (firstName, lastName, degree, department, expertise) are intentionally
		// omitted from window.RRP. They are fetched on-demand via GET /portal-users/me.
		wp_add_inline_script( 'research-review-portal', sprintf(
			'window.RRP = { restBase: %s, nonce: %s, isLoggedIn: true, loginUrl: %s, logoutUrl: %s, userName: %s, userRole: %s, userEmail: %s, allowedTypes: %s, userId: %s, programIds: %s, userRoles: %s, universityName: %s, universityShortName: %s, universityLogo: %s, portalName: %s, ssoEnabled: %s, ssoProvider: %s, publicSubmissionsEnabled: %s, publicAllowedTypes: %s };',
			wp_json_encode( rest_url( 'research-portal/v1' ) ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) ),
			wp_json_encode( '' ),
			wp_json_encode( RRP_Auth_Provider::get_logout_url( home_url( '/' ) ) ),
			wp_json_encode( $user_name ),
			wp_json_encode( $role_label ),
			wp_json_encode( $current_user->user_email ),
			wp_json_encode( $rrp_allowed_types ),
			wp_json_encode( (int) get_current_user_id() ),
			wp_json_encode( $rrp_program_ids ),
			wp_json_encode( $role_labels ),
			wp_json_encode( $univ_name ),
			wp_json_encode( $univ_short ),
			wp_json_encode( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' ),
			wp_json_encode( $portal_name ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_enabled' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_provider' ) ),
			wp_json_encode( $_pub_enabled2 ),
			wp_json_encode( $_pub_allowed_types2 )
		), 'before' );
		?>
<!DOCTYPE html><html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $portal_name ?: get_bloginfo( 'name' ) ); ?> &mdash; Research Portal</title>
<?php wp_head(); ?>
<link rel="icon" href="<?php echo $logo_url; ?>" type="image/svg+xml">
<style>
body{margin:0;padding:0;background:#f4f6f9}
#wpadminbar{display:none!important}
.rrp-ptopbar{background:#002f52;color:#fff;display:flex;justify-content:space-between;align-items:center;padding:0 1.5rem;height:56px;position:sticky;top:0;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.rrp-ptopbar-left{display:flex;align-items:center;gap:.75rem}
.rrp-ptopbar-logo{height:30px;width:auto;display:block;filter:brightness(0) invert(1)}
.rrp-ptopbar-brand{font-size:.95rem;font-weight:700;letter-spacing:.02em;color:rgba(255,255,255,.9);border-left:1px solid rgba(255,255,255,.25);padding-left:.75rem}
.rrp-ptopbar-right{display:flex;align-items:center;gap:.6rem;font-size:.85rem}
.rrp-ptopbar-right span{color:rgba(255,255,255,.82)}
.rrp-ptopbar-right a{color:#fff;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);padding:.3rem .9rem;border-radius:5px;font-size:.8rem;font-weight:600;text-decoration:none;transition:background .15s}
.rrp-ptopbar-right a:hover{background:rgba(255,255,255,.22)}
.rrp-ptopbar-right a.rrp-logout{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.2)}
.rrp-portal-wrap{max-width:980px;margin:1.5rem auto;padding:0 1.25rem}
</style>
</head>
<body>
<div class="rrp-ptopbar">
  <div class="rrp-ptopbar-left">
    <img src="<?php echo $logo_url; ?>" alt="<?php echo esc_attr( $univ_name ); ?>" class="rrp-ptopbar-logo">
    <span class="rrp-ptopbar-brand"><?php echo esc_html( $portal_name ); ?></span>
  </div>
  <div class="rrp-ptopbar-right">
    <span>Logged in as <strong><?php echo esc_html( $user_name ); ?></strong></span>
    <a class="rrp-logout" href="<?php echo $logout_url; ?>">Logout</a>
  </div>
</div>
<div class="rrp-portal-wrap">
  <div id="research-review-portal" class="rrp-portal" data-rest-base="<?php echo esc_attr( rest_url( 'research-portal/v1' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
    <div class="rrp-loading">Loading portal&hellip;</div>
  </div>
</div>
<footer style="text-align:center;padding:.9rem 1rem;font-size:.78rem;color:#9ca3af;border-top:1px solid #e5e7eb;margin-top:2rem;background:#f8fafc">
  &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $univ_name ); ?> &middot; <?php echo esc_html( $portal_name ); ?> &middot; School of Technology and Computing (STC)<br>
  <span style="font-size:.73rem;">Designed &amp; developed by <a href="mailto:vejendlakirankumar@cityu.edu" style="color:#4b83bc;text-decoration:none;">Kiran Kumar Vejendla</a> &middot; <a href="mailto:vejendlakirankumar@cityu.edu" style="color:#4b83bc;text-decoration:none;">vejendlakirankumar@cityu.edu</a> and <a href="mailto:garrisjemell@cityu.edu" style="color:#4b83bc;text-decoration:none;">Jemell Garris</a> &middot; <a href="mailto:garrisjemell@cityu.edu" style="color:#4b83bc;text-decoration:none;">garrisjemell@cityu.edu</a></span>
</footer>
<?php wp_footer(); ?>
</body></html>
		<?php
	}

	/**
	 * Standalone portal page for non-logged-in users when public submissions are enabled.
	 * Loads portal.js with isLoggedIn:false so renderSelection() shows the registration card.
	 */
	private static function output_public_portal_page() {
		show_admin_bar( false );
		// Use the standard WP login page, not the SSO endpoint, so public users
		// can authenticate with the username+password they just created.
		// After login WordPress redirects them back to /?portal=1 (the portal).
		$login_url    = wp_login_url( home_url( '/?portal=1' ) );
		$pub_cfg      = Portal_Data::read_config();
		$pub_settings = $pub_cfg['publicSubmissions'] ?? array();
		$pub_types    = is_array( $pub_settings['allowedTypes'] ?? null ) ? array_values( $pub_settings['allowedTypes'] ) : array();
		$univ_name    = RRP_Portal_Settings::get( 'university_name' );
		$univ_short   = RRP_Portal_Settings::get( 'university_short_name' );
		$portal_name  = RRP_Portal_Settings::get( 'portal_name' ) ?: 'Research Review Portal';
		$logo_raw     = RRP_Portal_Settings::get( 'university_logo_url' );
		$logo_url     = esc_url( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' );

		wp_enqueue_style( 'research-review-portal', RRP_PLUGIN_URL . 'assets/portal.css', array(), (string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.css' ) );
		wp_enqueue_script( 'mammoth-js', RRP_PLUGIN_URL . 'assets/mammoth.browser.min.js', array(), '1.7.0', true );
		wp_enqueue_script( 'jszip-js', RRP_PLUGIN_URL . 'assets/jszip.min.js', array(), '3.10.1', true );
		wp_enqueue_script( 'docx-preview-js', RRP_PLUGIN_URL . 'assets/docx-preview.min.js', array( 'jszip-js' ), '0.3.6', true );
		wp_enqueue_script( 'research-review-portal', RRP_PLUGIN_URL . 'assets/portal.js', array( 'mammoth-js', 'docx-preview-js' ), (string) filemtime( RRP_PLUGIN_DIR . 'assets/portal.js' ), true );

		// F12: PII fields omitted — unauthenticated page has no user PII to expose.
		wp_add_inline_script( 'research-review-portal', sprintf(
			'window.RRP = { restBase: %s, nonce: %s, isLoggedIn: false, loginUrl: %s, logoutUrl: %s, userName: %s, userRole: %s, userRoles: %s, userEmail: %s, userId: %s, universityName: %s, universityShortName: %s, universityLogo: %s, portalName: %s, ssoEnabled: %s, ssoProvider: %s, publicSubmissionsEnabled: true, publicAllowedTypes: %s };',
			wp_json_encode( rest_url( 'research-portal/v1' ) ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) ),
			wp_json_encode( esc_url( $login_url ) ),
			wp_json_encode( esc_url( $login_url ) ),
			wp_json_encode( '' ),
			wp_json_encode( '' ),
			wp_json_encode( array() ),
			wp_json_encode( '' ),
			wp_json_encode( 0 ),
			wp_json_encode( $univ_name ),
			wp_json_encode( $univ_short ),
			wp_json_encode( $logo_raw ?: RRP_PLUGIN_URL . 'assets/city-university-logo.svg' ),
			wp_json_encode( $portal_name ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_enabled' ) ),
			wp_json_encode( RRP_Portal_Settings::get( 'sso_provider' ) ),
			wp_json_encode( $pub_types )
		), 'before' );
		?>
<!DOCTYPE html><html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $portal_name ); ?> &mdash; Register to Submit</title>
<?php wp_head(); ?>
<link rel="icon" href="<?php echo $logo_url; ?>" type="image/svg+xml">
<style>
body{margin:0;padding:0;background:#f4f6f9}
#wpadminbar{display:none!important}
.rrp-ptopbar{background:#002f52;color:#fff;display:flex;justify-content:space-between;align-items:center;padding:0 1.5rem;height:56px;position:sticky;top:0;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.rrp-ptopbar-left{display:flex;align-items:center;gap:.75rem}
.rrp-ptopbar-logo{height:30px;width:auto;display:block;filter:brightness(0) invert(1)}
.rrp-ptopbar-brand{font-size:.95rem;font-weight:700;letter-spacing:.02em;color:rgba(255,255,255,.9);border-left:1px solid rgba(255,255,255,.25);padding-left:.75rem}
.rrp-ptopbar-right{display:flex;align-items:center;gap:.6rem;font-size:.85rem}
.rrp-ptopbar-right a{color:#fff;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);padding:.3rem .9rem;border-radius:5px;font-size:.8rem;font-weight:600;text-decoration:none;transition:background .15s}
.rrp-ptopbar-right a:hover{background:rgba(255,255,255,.22)}
.rrp-portal-wrap{max-width:980px;margin:1.5rem auto;padding:0 1.25rem}
</style>
</head>
<body>
<div class="rrp-ptopbar">
  <div class="rrp-ptopbar-left">
    <img src="<?php echo $logo_url; ?>" alt="<?php echo esc_attr( $univ_name ); ?>" class="rrp-ptopbar-logo">
    <span class="rrp-ptopbar-brand"><?php echo esc_html( $portal_name ); ?></span>
  </div>
  <div class="rrp-ptopbar-right">
    <a href="<?php echo esc_url( $login_url ); ?>">Already have an account? Login &rarr;</a>
  </div>
</div>
<div class="rrp-portal-wrap">
  <div id="research-review-portal" class="rrp-portal">
    <div class="rrp-loading">Loading&hellip;</div>
  </div>
</div>
<?php wp_footer(); ?>
</body></html>
		<?php
	}

	private static function output_guest_homepage() {
		$login_url = esc_url( wp_login_url( home_url( '/' ) ) );
		$year      = gmdate( 'Y' );
		// Check if public submissions are enabled for the register option
		$_gh_pub_cfg     = Portal_Data::read_config()['publicSubmissions'] ?? array();
		$_gh_pub_enabled = ! empty( $_gh_pub_cfg['enabled'] );
		$register_url    = esc_url( add_query_arg( 'portal', '1', home_url( '/' ) ) );
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Centralised submission and multi-stage peer review for dissertations, capstone projects, research papers, publications and grant proposals at CityU.">
<title><?php echo esc_html( RRP_Portal_Settings::get( 'portal_name' ) ?: 'CityU Research Review Portal' ); ?></title>
<link rel="icon" href="<?php echo esc_url( RRP_PLUGIN_URL . 'assets/city-university-logo.svg' ); ?>" type="image/svg+xml">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;color:#1a1a1a;background:#f4f6f9;line-height:1.65}
a{text-decoration:none;color:inherit}

/* ── Topbar ── */
.t-bar{position:sticky;top:0;z-index:1000;background:#002f52;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 2rem;height:58px;box-shadow:0 2px 10px rgba(0,0,0,.28)}
.t-brand{font-size:1.05rem;font-weight:700;letter-spacing:.02em;display:flex;align-items:center;gap:.55rem}
.t-nav{display:flex;align-items:center;gap:1.5rem;font-size:.87rem}
.t-nav a{color:rgba(255,255,255,.8);transition:color .15s}
.t-nav a:hover{color:#fff}
.t-login{background:#fff;color:#002f52!important;font-weight:700;border-radius:6px;padding:.4rem 1.15rem;transition:background .18s!important;white-space:nowrap}
.t-login:hover{background:#dbeeff!important}
.t-register{background:#0074c2!important;color:#fff!important;border:2px solid rgba(255,255,255,.35);margin-left:.3rem}
.t-register:hover{background:#005fa3!important}

/* ── Hero ── */
.hero{background:linear-gradient(140deg,#002f52 0%,#00509e 55%,#0074c2 100%);color:#fff;padding:5rem 2rem 4.5rem;text-align:center}
.hero-inner{max-width:700px;margin:0 auto}
.hero-eyebrow{display:inline-block;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.28);border-radius:20px;padding:.28rem .95rem;font-size:.74rem;letter-spacing:.08em;text-transform:uppercase;margin-bottom:1.3rem}
.hero h1{font-size:2.9rem;font-weight:900;line-height:1.13;margin-bottom:1.1rem}
.hero h1 em{font-style:normal;color:#7ecfff}
.hero p{font-size:1.08rem;color:rgba(255,255,255,.86);max-width:560px;margin:0 auto 2.1rem;line-height:1.72}
.btn-cta{display:inline-block;background:#fff;color:#002f52;font-weight:800;font-size:.97rem;border-radius:8px;padding:.75rem 2rem;box-shadow:0 4px 18px rgba(0,0,0,.18);transition:background .18s,transform .14s}
.btn-cta:hover{background:#dbeeff;transform:translateY(-2px)}
.btn-cta-outline{background:rgba(255,255,255,.13);color:#fff!important;border:2px solid rgba(255,255,255,.55);box-shadow:none}
.btn-cta-outline:hover{background:rgba(255,255,255,.22);transform:translateY(-2px)}

/* ── Shared section layout ── */
.section{padding:3.5rem 2rem}
.section-inner{max-width:1000px;margin:0 auto}
.section-label{font-size:.73rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#0074c2;margin-bottom:.45rem}
.section-title{font-size:1.85rem;font-weight:800;color:#002f52;margin-bottom:.7rem;line-height:1.2}
.section-sub{font-size:.96rem;color:#4a4a5a;max-width:680px;line-height:1.72;margin-bottom:2rem}
.section-alt{background:#fff}

/* ── About feature cards ── */
.features{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.1rem}
.feat{border:1px solid #dde5f2;border-radius:10px;padding:1.3rem 1.15rem;background:#f8fbff;transition:border-color .2s,box-shadow .2s}
.feat:hover{border-color:#0074c2;box-shadow:0 4px 16px rgba(0,116,194,.1)}
.feat-icon{font-size:1.65rem;margin-bottom:.55rem}
.feat h4{font-size:.95rem;font-weight:700;color:#002f52;margin-bottom:.3rem}
.feat p{font-size:.83rem;color:#555;line-height:1.55}

/* ── Who cards ── */
.who-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.9rem;margin-top:1rem}
.who-card{background:#fff;border:1px solid #dde5f2;border-radius:9px;padding:1.1rem 1rem;text-align:center}
.who-icon{font-size:1.55rem;margin-bottom:.4rem}
.who-card h4{font-size:.9rem;font-weight:700;color:#002f52;margin-bottom:.2rem}
.who-card p{font-size:.78rem;color:#555;line-height:1.5}

/* ── Tabs ── */
.tab-bar{display:flex;flex-wrap:wrap;gap:0;border-bottom:2px solid #dde5f2;margin-top:1.4rem}
.tab-btn{background:none;border:none;cursor:pointer;padding:.62rem 1.1rem .72rem;font-size:.86rem;font-weight:600;color:#5a5a6a;border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .18s,border-color .18s;display:flex;align-items:center;gap:.35rem;white-space:nowrap}
.tab-btn:hover{color:#0074c2}
.tab-btn.active{color:#002f52;border-bottom-color:#0074c2;background:rgba(0,116,194,.04)}
.tab-panels{margin-top:0}
.tab-panel{display:none;padding:2rem 0 .5rem}
.tab-panel.active{display:block}

/* ── Tab panel header ── */
.tp-header{display:flex;gap:1.2rem;align-items:flex-start;margin-bottom:1.75rem;padding-bottom:1.5rem;border-bottom:1px solid #edf0f7;flex-wrap:wrap}
.tp-icon{font-size:2.75rem;line-height:1;flex-shrink:0}
.tp-meta h3{font-size:1.25rem;font-weight:800;color:#002f52;margin-bottom:.35rem}
.tp-meta p{font-size:.9rem;color:#4a4a5a;line-height:1.65;max-width:610px}
.tp-eligible{display:inline-block;margin-top:.55rem;font-size:.76rem;font-weight:600;background:#ebf4ff;color:#0074c2;border-radius:20px;padding:.22rem .8rem;border:1px solid #c0d9f7}

/* ── Stage vertical timeline ── */
.stages{display:flex;flex-direction:column}
.stage{display:flex;gap:1rem;align-items:flex-start;position:relative}
.stage:not(:last-child)::before{content:"";position:absolute;left:16px;top:36px;width:2px;height:calc(100% - 8px);background:#dde5f2;z-index:0}
.s-num{width:32px;height:32px;border-radius:50%;background:#0074c2;color:#fff;font-size:.8rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;margin-top:.18rem}
.stage:last-child .s-num{background:#2d6b4f}
.s-body{padding:.05rem 0 1.4rem}
.s-name{font-size:.95rem;font-weight:700;color:#002f52;margin-bottom:.22rem}
.s-desc{font-size:.84rem;color:#555;line-height:1.55}

/* ── CTA ── */
.cta{background:linear-gradient(135deg,#002f52 0%,#00509e 55%,#0074c2 100%);color:#fff;padding:4rem 2rem;text-align:center}
.cta h2{font-size:1.85rem;font-weight:900;margin-bottom:.55rem}
.cta p{color:rgba(255,255,255,.83);margin-bottom:1.75rem;font-size:1rem;max-width:500px;margin-left:auto;margin-right:auto}

/* ── Footer ── */
.site-footer{background:#111827;color:#6b7280;text-align:center;padding:1.35rem 1rem;font-size:.81rem}

/* ── Responsive ── */
@media(max-width:640px){
  .t-bar{padding:0 1rem}
  .t-hide{display:none}
  .hero h1{font-size:2rem}
  .hero{padding:3.5rem 1.25rem}
  .section{padding:2.5rem 1.25rem}
  .section-title{font-size:1.45rem}
  .tab-btn{padding:.5rem .7rem .6rem;font-size:.8rem}
  .tp-icon{font-size:2rem}
}
</style>
</head>
<body>

<header class="t-bar">
  <div class="t-brand">
    <img src="<?php echo esc_url( RRP_PLUGIN_URL . 'assets/city-university-logo.svg' ); ?>" alt="City University of Seattle" style="height:22px;width:auto;filter:brightness(0) invert(1);display:block;">
    <span style="border-left:1px solid rgba(255,255,255,0.3);padding-left:0.65rem;margin-left:0.1rem;">Research Review Portal</span>
  </div>
  <nav class="t-nav" aria-label="Site navigation">
    <a href="#about" class="t-hide">About</a>
    <a href="#process" class="t-hide">Submission Types</a>
    <a href="<?php echo $login_url; ?>" class="t-login">Login</a>
    <?php if ( $_gh_pub_enabled ) : ?>
    <a href="<?php echo $register_url; ?>" class="t-login t-register t-hide">Register to Submit</a>
    <?php endif; ?>
  </nav>
</header>

<section class="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">City University of Seattle &middot; School of Technology and Computing</div>
    <h1>Streamlined Research<br><em>Submission &amp; Review</em></h1>
    <p>The CityU Research Review Portal is a centralised platform for submitting and tracking doctoral dissertations, capstone projects, research papers, publications, and grant proposals through structured multi-stage expert review.</p>
    <a href="<?php echo $login_url; ?>" class="btn-cta">Login to Submit Your Work &rarr;</a>
    <?php if ( $_gh_pub_enabled ) : ?>
    <div style="margin-top:.9rem;">
      <a href="<?php echo $register_url; ?>" class="btn-cta btn-cta-outline">No CityU account? Register to Submit &rarr;</a>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-alt" id="about">
  <div class="section-inner">
    <div class="section-label">About This Portal</div>
    <h2 class="section-title">What is the Research Review Portal?</h2>
    <p class="section-sub">The CityU Research Review Portal is a centralised digital platform developed by CityU School of Technology and Computing (STC). It manages the full lifecycle of academic research submissions at <strong>City University of Seattle</strong>&mdash;from initial upload through expert peer review to final decision&mdash;in a single transparent, trackable system.</p>
    <div class="features">
      <div class="feat"><div class="feat-icon">&#128203;</div><h4>Structured Multi-Stage Review</h4><p>Every submission follows a workflow defined for its type, ensuring rigorous and consistent evaluation at each stage.</p></div>
      <div class="feat"><div class="feat-icon">&#128065;&#65039;</div><h4>Full Transparency</h4><p>Submitters can track exactly which stage their work is at, who is reviewing it, and what feedback has been provided.</p></div>
      <div class="feat"><div class="feat-icon">&#128101;</div><h4>Expert Reviewer Coordination</h4><p>Coordinators assign qualified reviewers from a managed pool, ensuring domain expertise and conflict-of-interest checks.</p></div>
      <div class="feat"><div class="feat-icon">&#128196;</div><h4>Document Management</h4><p>Upload supporting materials, save drafts, and attach revisions&mdash;all version-controlled and linked to your submission.</p></div>
      <div class="feat"><div class="feat-icon">&#128276;</div><h4>Automated Notifications</h4><p>Reviewers and submitters receive email alerts when action is required, keeping the process moving without manual chasing.</p></div>
      <div class="feat"><div class="feat-icon">&#128202;</div><h4>Coordinator Dashboard</h4><p>Admins and coordinators get a bird&rsquo;s-eye view of all active submissions, deadlines, and overdue reviews.</p></div>
    </div>
  </div>
</section>

<section class="section" id="who">
  <div class="section-inner">
    <div class="section-label">Who Can Use This Portal?</div>
    <h2 class="section-title">User Roles</h2>
    <div class="who-grid">
      <div class="who-card"><div class="who-icon">&#127891;</div><h4>Students</h4><p>Submit dissertations and capstone projects, track review progress, and respond to advisor feedback.</p></div>
      <div class="who-card"><div class="who-icon">&#128221;</div><h4>Researchers &amp; Faculty</h4><p>Submit conference papers, journal manuscripts, and grant proposals for expert peer review.</p></div>
      <div class="who-card"><div class="who-icon">&#128269;</div><h4>Reviewers</h4><p>Receive assignments, provide structured feedback, and submit decisions within the review workflow.</p></div>
      <div class="who-card"><div class="who-icon">&#128450;&#65039;</div><h4>Coordinators</h4><p>Manage the reviewer pool, assign reviewers, advance stages, and generate progress reports.</p></div>
      <div class="who-card"><div class="who-icon">&#9881;&#65039;</div><h4>Administrators</h4><p>Configure submission types, manage user roles, oversee all submissions, and access analytics.</p></div>
    </div>
  </div>
</section>

<section class="section section-alt" id="process">
  <div class="section-inner">
    <div class="section-label">Submission Types</div>
    <h2 class="section-title">Review Process by Submission Type</h2>
    <p class="section-sub">Select a submission type to see the complete staged review workflow. Each stage must be approved before the submission advances to the next.</p>

    <div class="tab-bar" role="tablist" aria-label="Submission types">
      <button class="tab-btn active" role="tab" aria-selected="true"  aria-controls="tab-diss"  data-tab="diss"> &#127891; Doctoral Dissertation</button>
      <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-cap"   data-tab="cap">  &#128208; Capstone Project</button>
      <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-paper" data-tab="paper">&#128196; Research Paper</button>
      <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-pub"   data-tab="pub">  &#128218; Journal Publication</button>
      <button class="tab-btn"        role="tab" aria-selected="false" aria-controls="tab-grant" data-tab="grant">&#128181; Grant Proposal</button>
    </div>

    <div class="tab-panels">

      <!-- DOCTORAL DISSERTATION -->
      <div class="tab-panel active" id="tab-diss" role="tabpanel">
        <div class="tp-header">
          <div class="tp-icon">&#127891;</div>
          <div class="tp-meta">
            <h3>Doctoral Dissertation</h3>
            <p>A doctoral dissertation represents original scholarly research contributing new knowledge to your field. The portal supports PhD and research-doctoral candidates by coordinating advisor matching, consultation, feasibility assessment, and milestone monitoring throughout the doctoral journey.</p>
            <span class="tp-eligible">Eligible: PhD &amp; research doctoral candidates at CityU</span>
          </div>
        </div>
        <div class="stages">
          <div class="stage"><div class="s-num">1</div><div class="s-body"><div class="s-name">Student Submits</div><div class="s-desc">The doctoral student uploads the dissertation document and all required supporting materials through the portal to begin the approval chain.</div></div></div>
          <div class="stage"><div class="s-num">2</div><div class="s-body"><div class="s-name">Chair Review &amp; Approval</div><div class="s-desc">The dissertation chair reviews the submission for academic quality and completeness. The chair may request revisions before approving the work to advance to the committee.</div></div></div>
          <div class="stage"><div class="s-num">3</div><div class="s-body"><div class="s-name">Committee Member Review <span style="display:inline-block;font-size:.7rem;font-weight:600;background:#003d66;color:#fff;border-radius:20px;padding:.15rem .55rem;margin-left:.3rem;">All members must approve</span></div><div class="s-desc">All assigned committee members independently review the dissertation. The submission advances only when every committee member has provided their individual approval&mdash;a single pending approval holds the process.</div></div></div>
          <div class="stage"><div class="s-num">4</div><div class="s-body"><div class="s-name">Program Director Approval</div><div class="s-desc">The Program Director conducts a final academic review and confirms the dissertation meets all program requirements and institutional standards before it progresses to the final authority.</div></div></div>
          <div class="stage"><div class="s-num">5</div><div class="s-body"><div class="s-name">Dissertation Director Sign-Off</div><div class="s-desc">The Dissertation Director provides the final institutional approval and formally signs off on the completed work. Once signed off, the dissertation is officially approved and the student is notified.</div></div></div>
        </div>
      </div>

      <!-- CAPSTONE PROJECT -->
      <div class="tab-panel" id="tab-cap" role="tabpanel">
        <div class="tp-header">
          <div class="tp-icon">&#128208;</div>
          <div class="tp-meta">
            <h3>Capstone Project</h3>
            <p>Capstone projects enable final-year students to apply academic knowledge to real-world problems under faculty supervision. The portal manages supervisor matching, project scoping, feasibility evaluation, and progress tracking through structured milestones.</p>
            <span class="tp-eligible">Eligible: Final-year undergraduate &amp; postgraduate students at CityU</span>
          </div>
        </div>
        <div class="stages">
          <div class="stage"><div class="s-num">1</div><div class="s-body"><div class="s-name">Student Submits</div><div class="s-desc">The student uploads their capstone project document and any required supporting materials through the portal.</div></div></div>
          <div class="stage"><div class="s-num">2</div><div class="s-body"><div class="s-name">Advisor Review &amp; Approval</div><div class="s-desc">The assigned academic advisor reviews the project for academic merit, scope, and feasibility. The advisor may request revisions before approving the work to advance to the Program Director.</div></div></div>
          <div class="stage"><div class="s-num">3</div><div class="s-body"><div class="s-name">Program Director Approval</div><div class="s-desc">The Program Director provides the final approval. Once approved, the capstone project is officially accepted and the student is notified of successful completion.</div></div></div>
        </div>
      </div>

      <!-- RESEARCH PAPER -->
      <div class="tab-panel" id="tab-paper" role="tabpanel">
        <div class="tp-header">
          <div class="tp-icon">&#128196;</div>
          <div class="tp-meta">
            <h3>Research Paper / Conference Submission</h3>
            <p>Submit research papers to the <strong>Applied Research Symposium (ARS)</strong> or the <strong>Doctor of IT Forum (DIT)</strong>. Papers undergo double-blind peer review to ensure scholarly rigour before acceptance for presentation.</p>
            <span class="tp-eligible">Eligible: Faculty, researchers &amp; doctoral students at CityU</span>
          </div>
        </div>
        <div class="stages">
          <div class="stage"><div class="s-num">1</div><div class="s-body"><div class="s-name">Initial Screening</div><div class="s-desc">The submitted paper is checked against the call-for-papers scope, formatting guidelines, and mandatory submission requirements.</div></div></div>
          <div class="stage"><div class="s-num">2</div><div class="s-body"><div class="s-name">Reviewer Assignment</div><div class="s-desc">Two or more qualified domain experts are selected from the reviewer pool and invited for double-blind peer review.</div></div></div>
          <div class="stage"><div class="s-num">3</div><div class="s-body"><div class="s-name">Peer Review</div><div class="s-desc">Reviewers independently assess originality, research methodology, contribution to the field, and writing quality.</div></div></div>
          <div class="stage"><div class="s-num">4</div><div class="s-body"><div class="s-name">Review Consolidation</div><div class="s-desc">All reviewer reports are compiled, discrepancies resolved, and revision requirements summarised for the author.</div></div></div>
          <div class="stage"><div class="s-num">5</div><div class="s-body"><div class="s-name">Final Decision</div><div class="s-desc">Editorial decision issued: Accept, Minor Revision, Major Revision &amp; Resubmit, or Reject&mdash;with written justification.</div></div></div>
          <div class="stage"><div class="s-num">6</div><div class="s-body"><div class="s-name">Confirmation</div><div class="s-desc">Accepted authors receive their presentation slot, camera-ready instructions, and conference registration details.</div></div></div>
        </div>
      </div>

      <!-- JOURNAL PUBLICATION -->
      <div class="tab-panel" id="tab-pub" role="tabpanel">
        <div class="tp-header">
          <div class="tp-icon">&#128218;</div>
          <div class="tp-meta">
            <h3>Journal Publication</h3>
            <p>For faculty and researchers submitting manuscripts to peer-reviewed academic journals or as chapters in edited volumes. Expert reviewers provide detailed scholarly feedback before the Director of Research makes a final publication recommendation.</p>
            <span class="tp-eligible">Eligible: Faculty members &amp; research staff at CityU</span>
          </div>
        </div>
        <div class="stages">
          <div class="stage"><div class="s-num">1</div><div class="s-body"><div class="s-name">Administrative Check</div><div class="s-desc">Manuscript verified for author eligibility, conflict-of-interest disclosures, target journal formatting, and copyright agreements.</div></div></div>
          <div class="stage"><div class="s-num">2</div><div class="s-body"><div class="s-name">Reviewer Matching</div><div class="s-desc">Domain experts identified from the reviewer pool, checked for conflicts of interest, and formally invited to review.</div></div></div>
          <div class="stage"><div class="s-num">3</div><div class="s-body"><div class="s-name">Expert Review</div><div class="s-desc">In-depth scholarly evaluation covering literature engagement, research design, statistical methods, and significance of contribution.</div></div></div>
          <div class="stage"><div class="s-num">4</div><div class="s-body"><div class="s-name">Director Assessment</div><div class="s-desc">The Research Director reviews consolidated reviewer feedback and provides a final academic judgement and publication recommendation.</div></div></div>
          <div class="stage"><div class="s-num">5</div><div class="s-body"><div class="s-name">Final Decision</div><div class="s-desc">Decision issued: Approved for external submission, Major Revision, Minor Revision, or Rejection with written feedback.</div></div></div>
          <div class="stage"><div class="s-num">6</div><div class="s-body"><div class="s-name">Tracking</div><div class="s-desc">Submission to the target journal is registered and progress monitored from submission through to publication or rejection.</div></div></div>
        </div>
      </div>

      <!-- GRANT PROPOSAL -->
      <div class="tab-panel" id="tab-grant" role="tabpanel">
        <div class="tp-header">
          <div class="tp-icon">&#128181;</div>
          <div class="tp-meta">
            <h3>Grant Proposal</h3>
            <p>Apply for CityU internal seed funding or prepare competitive external applications for government and industry funders. Proposals are evaluated across multiple criteria by a dedicated research committee before a funding decision is made.</p>
            <span class="tp-eligible">Eligible: Faculty, research centres &amp; approved research teams at CityU</span>
          </div>
        </div>
        <div class="stages">
          <div class="stage"><div class="s-num">1</div><div class="s-body"><div class="s-name">Compliance Check</div><div class="s-desc">Proposal verified against funder eligibility criteria, budget ceilings, mandatory documentation requirements, and institutional policies.</div></div></div>
          <div class="stage"><div class="s-num">2</div><div class="s-body"><div class="s-name">Review Assignment</div><div class="s-desc">Research committee members with relevant domain expertise are designated and conflicts of interest identified and managed.</div></div></div>
          <div class="stage"><div class="s-num">3</div><div class="s-body"><div class="s-name">Multi-Criteria Review</div><div class="s-desc">Proposals scored across research impact, innovation, feasibility, team capability, and value for money.</div></div></div>
          <div class="stage"><div class="s-num">4</div><div class="s-body"><div class="s-name">Committee Meeting</div><div class="s-desc">Full committee convenes to deliberate on scores, discuss shortlisted proposals, and produce a ranked recommendation.</div></div></div>
          <div class="stage"><div class="s-num">5</div><div class="s-body"><div class="s-name">Final Decision</div><div class="s-desc">Funding awarded with conditions, deferred to the next cycle with feedback, or declined with written justification.</div></div></div>
          <div class="stage"><div class="s-num">6</div><div class="s-body"><div class="s-name">Development Support</div><div class="s-desc">Approved teams receive writing support, budget-refinement assistance, and compliance guidance for the external submission.</div></div></div>
          <div class="stage"><div class="s-num">7</div><div class="s-body"><div class="s-name">Submission Tracking</div><div class="s-desc">Formal submission to the funding body is recorded and tracked until acknowledgement and final grant outcome are confirmed.</div></div></div>
        </div>
      </div>

    </div><!-- .tab-panels -->
  </div><!-- .section-inner -->
</section>

<section class="cta">
  <h2>Ready to submit your research?</h2>
  <p>Log in with your CityU credentials to access your personal dashboard and begin a new submission.</p>
  <a href="<?php echo $login_url; ?>" class="btn-cta">Login Now &rarr;</a>
  <?php if ( $_gh_pub_enabled ) : ?>
  <div style="margin-top:1.25rem;font-size:.9rem;color:rgba(255,255,255,.7);">- or -</div>
  <p style="margin-top:.75rem;color:rgba(255,255,255,.8);font-size:.92rem;">No CityU account? Public submissions are open.</p>
  <a href="<?php echo $register_url; ?>" class="btn-cta" style="background:#0074c2;color:#fff;margin-top:.5rem;border:2px solid rgba(255,255,255,.45);">Register &amp; Submit &rarr;</a>
  <?php endif; ?>
</section>

<footer class="site-footer">
  <p>&copy; <?php echo esc_html( $year ); ?> City University of Seattle &middot; Research Review Portal &middot; School of Technology and Computing (STC)</p>
  <p style="margin-top:.35rem;font-size:.76rem;color:#4b5563;">Designed &amp; developed by <a href="mailto:vejendlakirankumar@cityu.edu" style="color:#60a5fa;text-decoration:none;">Kiran Kumar Vejendla</a> &middot; <a href="mailto:vejendlakirankumar@cityu.edu" style="color:#60a5fa;text-decoration:none;">vejendlakirankumar@cityu.edu</a> and <a href="mailto:garrisjemell@cityu.edu" style="color:#60a5fa;text-decoration:none;">Jemell Garris</a> &middot; <a href="mailto:garrisjemell@cityu.edu" style="color:#60a5fa;text-decoration:none;">garrisjemell@cityu.edu</a></p>
</footer>

<script>
(function(){
  var btns   = document.querySelectorAll('.tab-btn');
  var panels = document.querySelectorAll('.tab-panel');
  btns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var target = btn.getAttribute('data-tab');
      btns.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
      panels.forEach(function(p){ p.classList.remove('active'); });
      btn.classList.add('active');
      btn.setAttribute('aria-selected','true');
      var panel = document.getElementById('tab-' + target);
      if (panel) panel.classList.add('active');
    });
  });
})();
</script>
</body>
</html>
		<?php
	}
}

	add_action( 'init', array( 'Research_Review_Portal', 'init' ) );
	add_action( 'rest_api_init', array( 'Portal_REST', 'register_routes' ) );

	// Run the one-time JSON-to-DB migration on every load; the method is
	// guarded by the rrp_db_version option so it is a fast no-op after the
	// first successful migration.
	add_action( 'plugins_loaded', array( 'Portal_Data', 'maybe_migrate' ) );

	// ── Dynamic-analysis security hardening hooks ─────────────────────────────

	// D-Headers: Add security headers to every WordPress response.
	add_action( 'send_headers', function () {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()' );
		// Content-Security-Policy: self-hosted only (mammoth.js now served locally).
		header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://secure.gravatar.com; connect-src 'self'; frame-ancestors 'none';" );
		// Remove PHP version exposure.
		header( 'X-Powered-By:' );
	}, 1 );

	// D-CORS: Override WordPress REST API's default of reflecting any Origin
	// back with Allow-Credentials:true (OWASP A05 – Security Misconfiguration).
	// Priority 100 ensures this runs AFTER WP core's send_cors_headers (priority 10).
	// Only the same host (and optionally RRP_CORS_ORIGIN) is allowed.
	add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
		// Remove all CORS headers set by WordPress core before we set our own.
		header_remove( 'Access-Control-Allow-Origin' );
		header_remove( 'Access-Control-Allow-Credentials' );
		header_remove( 'Access-Control-Expose-Headers' );
		header_remove( 'Access-Control-Allow-Headers' );
		header_remove( 'Access-Control-Allow-Methods' );
		$request_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
		if ( $request_origin ) {
			$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
			$req_host  = wp_parse_url( $request_origin, PHP_URL_HOST );
			$explicit  = defined( 'RRP_CORS_ORIGIN' ) ? (string) RRP_CORS_ORIGIN : '';
			if ( $site_host && $req_host && strtolower( $site_host ) === strtolower( $req_host ) ) {
				header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $request_origin ) );
				header( 'Access-Control-Allow-Credentials: true' );
				header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE' );
				header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, Content-Disposition' );
				header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages' );
			} elseif ( $explicit && $request_origin === $explicit ) {
				// Explicitly configured trusted third-party origin (no credentials).
				header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $request_origin ) );
				header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST' );
				header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type' );
			}
			// Any other origin gets no CORS headers — the browser will block it.
			header( 'Vary: Origin' );
		}
		return $served;
	}, 100, 3 ); // Priority 100: must run AFTER WP core's send_cors_headers (priority 10)

	// D-XMLRPC: Disable XML-RPC entirely — it is not used by the portal and
	// system.multicall enables large-scale credential brute-forcing.
	// Belt-and-suspenders: three layers to block all XML-RPC paths, including
	// IXR built-ins (system.listMethods, system.multicall) that bypass the
	// xmlrpc_enabled filter.
	add_filter( 'xmlrpc_enabled', '__return_false' );
	// Layer 2: kill the request at init before wp_xmlrpc_server is instantiated.
	add_action( 'init', function () {
		if ( isset( $_SERVER['SCRIPT_FILENAME'] ) &&
			'xmlrpc.php' === basename( (string) $_SERVER['SCRIPT_FILENAME'] ) ) {
			status_header( 403 );
			die( 'XML-RPC services are disabled on this server.' );
		}
	}, 1 );
	// Remove the XML-RPC link advertised in page <head>.
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );

	// D-UserEnum-REST: Require authentication to list WP users via REST API.
	add_filter( 'rest_endpoints', function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'] );
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	} );

	// D-UserEnum-Author: Block ?author=N username enumeration via redirect.
	add_action( 'template_redirect', function () {
		if ( isset( $_GET['author'] ) && ! current_user_can( 'list_users' ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}, 1 );

	// ── URL host fix ─────────────────────────────────────────────────────────
	// Must be registered at plugin-load time, NOT inside 'init', so these fire
	// before WordPress reads options and before redirect_canonical runs.
	// option_siteurl / option_home are the root source that all URL functions
	// (home_url, site_url, rest_url, redirect_canonical) derive from.
	if ( isset( $_SERVER['HTTP_HOST'] ) && '' !== $_SERVER['HTTP_HOST'] ) {
		add_filter( 'option_siteurl',     array( 'Research_Review_Portal', 'fix_url_host' ), 1, 1 );
		add_filter( 'option_home',         array( 'Research_Review_Portal', 'fix_url_host' ), 1, 1 );
		add_filter( 'redirect_canonical',  array( 'Research_Review_Portal', 'no_localhost_redirect' ), 1, 2 );
	}

	add_action( 'rrp_daily_report',       array( 'Portal_REST', 'scheduled_report' ) );
	add_action( 'rrp_deadline_reminders', array( 'Portal_REST', 'send_deadline_reminders' ) );
	add_action( 'rrp_escalation_check',   array( 'Portal_REST', 'send_escalation_emails' ) );
	add_action( 'rrp_auto_backup',        array( 'Portal_REST', 'run_auto_backup' ) );
	add_action( 'phpmailer_init',         array( 'Portal_REST', 'configure_phpmailer' ) );
	// Route all wp_mail() calls through ACS when ACS email is enabled (bypasses PHPMailer/sendmail)
	add_filter( 'pre_wp_mail',            array( 'Portal_REST', 'pre_wp_mail_acs' ), 10, 2 );

	// Register a custom weekly recurrence for auto-backup.
	add_filter( 'cron_schedules', function ( $schedules ) {
		if ( ! isset( $schedules['rrp_weekly'] ) ) {
			$schedules['rrp_weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => 'Once Weekly (RRP)',
			);
		}
		return $schedules;
	} );

	register_activation_hook( __FILE__, function() {
		if ( ! wp_next_scheduled( 'rrp_daily_report' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_daily_report' );
		}
		if ( ! wp_next_scheduled( 'rrp_deadline_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_deadline_reminders' );
		}
		if ( ! wp_next_scheduled( 'rrp_escalation_check' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_escalation_check' );
		}
	} );

	register_deactivation_hook( __FILE__, function() {
		wp_clear_scheduled_hook( 'rrp_daily_report' );
		wp_clear_scheduled_hook( 'rrp_deadline_reminders' );
		wp_clear_scheduled_hook( 'rrp_escalation_check' );
		wp_clear_scheduled_hook( 'rrp_auto_backup' );
	} );

	// Auto-schedule new cron jobs on every load if not already registered
	// (handles upgrades without requiring plugin re-activation).
	add_action( 'init', function () {
		if ( ! wp_next_scheduled( 'rrp_deadline_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_deadline_reminders' );
		}
		if ( ! wp_next_scheduled( 'rrp_escalation_check' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_escalation_check' );
		}
		// Auto backup: schedule/reschedule/deschedule based on live settings.
		if ( class_exists( 'RRP_Portal_Settings' ) ) {
			$ab_on   = (bool) RRP_Portal_Settings::get( 'auto_backup_enabled' );
			$ab_freq = RRP_Portal_Settings::get( 'auto_backup_schedule' ) === 'weekly' ? 'rrp_weekly' : 'daily';
			$next    = wp_next_scheduled( 'rrp_auto_backup' );
			if ( $ab_on ) {
				if ( ! $next || wp_get_schedule( 'rrp_auto_backup' ) !== $ab_freq ) {
					wp_clear_scheduled_hook( 'rrp_auto_backup' );
					wp_schedule_event( time(), $ab_freq, 'rrp_auto_backup' );
				}
			} elseif ( $next ) {
				wp_clear_scheduled_hook( 'rrp_auto_backup' );
			}
		}
	} );
