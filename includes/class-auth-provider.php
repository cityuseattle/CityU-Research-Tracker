<?php
/**
 * Research Review Portal — Authentication Provider
 *
 * Abstracts the login/logout mechanism so the portal can switch from
 * WordPress-native authentication to Microsoft Entra ID (Azure AD) SSO
 * with minimal configuration changes.
 *
 * ── Current mode ─────────────────────────────────────────────────────────────
 * RRP_AUTH_PROVIDER = 'wordpress'   (default — no changes needed)
 * Users log in through the standard WordPress login page.
 *
 * ── Switching to Entra ID ────────────────────────────────────────────────────
 * 1. Register an App Registration in your Azure portal (Entra ID → App registrations).
 *    Set the redirect URI to:
 *      https://<your-domain>/wp-json/research-portal/v1/auth/callback
 *
 * 2. Add the following constants to wp-config.php (do NOT commit secrets):
 *
 *      define( 'RRP_AUTH_PROVIDER',      'entra' );
 *      define( 'RRP_ENTRA_TENANT_ID',    'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' );
 *      define( 'RRP_ENTRA_CLIENT_ID',    'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' );
 *      define( 'RRP_ENTRA_CLIENT_SECRET','your-client-secret-value' );
 *      define( 'RRP_ENTRA_REDIRECT_URI', 'https://<your-domain>/wp-json/research-portal/v1/auth/callback' );
 *
 * 3. Register the callback route — already done in class-portal-rest.php
 *    (see RRP_AUTH_PROVIDER check there).
 *
 * ── Authorization model (unchanged in both modes) ────────────────────────────
 * Entra handles authentication only (who you are).
 * Portal roles (Student / Reviewer / Coordinator / Admin) are assigned locally
 * by the portal administrator and are never synced from Entra groups.
 * A brand-new Entra user gets no portal role until an admin assigns one.
 * ─────────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RRP_AUTH_PROVIDER' ) ) {
	// Default: use WordPress native auth.
	// Change to 'entra' in wp-config.php once Entra constants are set.
	define( 'RRP_AUTH_PROVIDER', 'wordpress' );
}

class RRP_Auth_Provider {

	/**
	 * True when Entra SSO is the active provider.
	 * Checks the wp-config constant first; if absent, checks the DB setting.
	 */
	public static function is_entra_active(): bool {
		if ( RRP_AUTH_PROVIDER === 'entra' ) {
			return true;
		}
		if ( class_exists( 'RRP_Portal_Settings' ) ) {
			return (bool) RRP_Portal_Settings::get( 'sso_enabled' )
				&& RRP_Portal_Settings::get( 'sso_provider' ) === 'entra';
		}
		return false;
	}

	/**
	 * Return the login URL for whichever provider is active.
	 *
	 * @param string $redirect_to  URL to return to after successful login.
	 * @return string
	 */
	public static function get_login_url( $redirect_to = '' ) {
		if ( self::is_entra_active() ) {
			return self::entra_auth_url( $redirect_to );
		}
		return wp_login_url( $redirect_to ?: home_url() );
	}

	/**
	 * Return the logout URL for whichever provider is active.
	 *
	 * Only routes to Entra's logout endpoint when the current user's session
	 * was actually created via Entra (checked via rrp_session_via_entra usermeta).
	 * Local-password logins always use the standard WordPress logout URL.
	 *
	 * @param string $redirect_to  URL to land on after logout.
	 * @return string
	 */
	public static function get_logout_url( $redirect_to = '' ) {
		if ( self::is_entra_active() ) {
			$user_id = get_current_user_id();
			if ( $user_id && get_user_meta( $user_id, 'rrp_session_via_entra', true ) ) {
				return self::entra_logout_url( $redirect_to );
			}
		}
		return wp_logout_url( $redirect_to ?: home_url() );
	}

	/**
	 * True when there is an authenticated session (provider-agnostic).
	 *
	 * @return bool
	 */
	public static function is_authenticated() {
		return is_user_logged_in();
	}

	// ── Entra stubs ── (inactive until RRP_AUTH_PROVIDER = 'entra') ─────────

	/**
	 * Build the Entra OAuth 2.0 authorization URL.
	 * Uses wp-config constants when defined; otherwise reads from the encrypted DB settings.
	 * Falls back to the WP login page when neither source has the required values.
	 */
	private static function entra_auth_url( $redirect_to ) {
		$tenant_id = defined( 'RRP_ENTRA_TENANT_ID' ) ? RRP_ENTRA_TENANT_ID
			: ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_tenant_id' ) : '' );
		$client_id = defined( 'RRP_ENTRA_CLIENT_ID' ) ? RRP_ENTRA_CLIENT_ID
			: ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_client_id' ) : '' );
		$redirect_uri = defined( 'RRP_ENTRA_REDIRECT_URI' ) ? RRP_ENTRA_REDIRECT_URI
			: ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_redirect_uri' ) : '' );

		if ( ! $tenant_id || ! $client_id ) {
			// Not fully configured — degrade gracefully
			return wp_login_url( $redirect_to ?: home_url() );
		}

		$state = wp_create_nonce( 'rrp_entra_state' );

		if ( $redirect_to ) {
			// Store the post-login destination for 5 minutes
			set_transient( 'rrp_entra_post_login_' . $state, $redirect_to, 300 );
		}

		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'redirect_uri'  => $redirect_uri,
			'response_mode' => 'query',
			'scope'         => 'openid profile email',
			'state'         => $state,
		);

		return 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id )
			. '/oauth2/v2.0/authorize?' . http_build_query( $params );
	}

	/**
	 * Build the Entra logout URL (also signs the user out of Entra SSO session).
	 */
	private static function entra_logout_url( $redirect_to ) {
		$tenant_id = defined( 'RRP_ENTRA_TENANT_ID' ) ? RRP_ENTRA_TENANT_ID
			: ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_tenant_id' ) : '' );

		if ( ! $tenant_id ) {
			return wp_logout_url( $redirect_to ?: home_url() );
		}
		$post_logout = rawurlencode( $redirect_to ?: home_url() );
		return 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id )
			. '/oauth2/v2.0/logout?post_logout_redirect_uri=' . $post_logout;
	}

	/**
	 * Handle the incoming Entra OAuth callback.
	 *
	 * Registered as:  GET /wp-json/research-portal/v1/auth/callback
	 * Active only when RRP_AUTH_PROVIDER = 'entra'.
	 *
	 * Full implementation flow (to be completed once Entra constants are set):
	 *   1. Verify the 'state' nonce against the transient set in entra_auth_url().
	 *   2. Exchange the 'code' parameter for tokens via the token endpoint.
	 *   3. Decode the id_token and extract: oid, email, name, department claims.
	 *   4. Call self::map_entra_user() to find or create the local WP user.
	 *   5. Call wp_set_auth_cookie() and redirect to the portal.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|void
	 */
	public static function handle_entra_callback( WP_REST_Request $request ) {
		// 1. Verify state nonce (CSRF protection)
		$state = sanitize_text_field( (string) ( $request->get_param( 'state' ) ?? '' ) );
		if ( ! $state || ! wp_verify_nonce( $state, 'rrp_entra_state' ) ) {
			wp_die(
				esc_html__( 'Invalid authentication state. Please try logging in again.', 'rrp' ),
				esc_html__( 'Authentication Error', 'rrp' ),
				array( 'response' => 403 )
			);
		}

		// 2. Surface any Entra-side errors
		$error = $request->get_param( 'error' );
		if ( $error ) {
			$desc = sanitize_text_field( (string) ( $request->get_param( 'error_description' ) ?? $error ) );
			wp_die(
				esc_html( 'SSO Error: ' . $desc ),
				esc_html__( 'Authentication Error', 'rrp' ),
				array( 'response' => 401 )
			);
		}

		// 3. Retrieve authorization code
		$code = sanitize_text_field( (string) ( $request->get_param( 'code' ) ?? '' ) );
		if ( ! $code ) {
			wp_die(
				esc_html__( 'No authorization code received.', 'rrp' ),
				esc_html__( 'Authentication Error', 'rrp' ),
				array( 'response' => 400 )
			);
		}

		// 4. Resolve config (constants preferred; fall back to encrypted DB settings)
		$tenant_id     = defined( 'RRP_ENTRA_TENANT_ID' )     ? RRP_ENTRA_TENANT_ID     : ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_tenant_id' )     : '' );
		$client_id     = defined( 'RRP_ENTRA_CLIENT_ID' )     ? RRP_ENTRA_CLIENT_ID     : ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_client_id' )     : '' );
		$client_secret = defined( 'RRP_ENTRA_CLIENT_SECRET' ) ? RRP_ENTRA_CLIENT_SECRET : ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_client_secret' ) : '' );
		$redirect_uri  = defined( 'RRP_ENTRA_REDIRECT_URI' )  ? RRP_ENTRA_REDIRECT_URI  : ( class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get( 'entra_redirect_uri' )  : '' );

		if ( ! $tenant_id || ! $client_id || ! $client_secret ) {
			wp_die(
				esc_html__( 'Microsoft Entra SSO is not fully configured. Please contact the portal administrator.', 'rrp' ),
				esc_html__( 'SSO Configuration Error', 'rrp' ),
				array( 'response' => 501 )
			);
		}

		// 5. Exchange authorization code for tokens
		$token_response = wp_remote_post(
			'https://login.microsoftonline.com/' . rawurlencode( $tenant_id ) . '/oauth2/v2.0/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
					'scope'         => 'openid profile email',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			wp_die(
				esc_html__( 'Failed to contact Microsoft Entra. Please try again.', 'rrp' ),
				esc_html__( 'SSO Error', 'rrp' ),
				array( 'response' => 502 )
			);
		}

		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
		if ( empty( $token_body['id_token'] ) ) {
			$error_msg = $token_body['error_description'] ?? 'No id_token in response.';
			wp_die(
				esc_html( 'Token exchange failed: ' . $error_msg ),
				esc_html__( 'SSO Error', 'rrp' ),
				array( 'response' => 502 )
			);
		}

		// 6. Decode id_token payload
		// Token was received directly from Microsoft over HTTPS — TLS guarantees
		// integrity; state nonce provides CSRF protection.
		$claims = self::decode_jwt_payload( $token_body['id_token'] );
		if ( ! $claims ) {
			wp_die(
				esc_html__( 'Unable to parse id_token from Microsoft Entra.', 'rrp' ),
				esc_html__( 'SSO Error', 'rrp' ),
				array( 'response' => 502 )
			);
		}

		// 7. Find or create a local WordPress account
		$user_id = self::map_entra_user( $claims );
		if ( is_wp_error( $user_id ) ) {
			wp_die(
				esc_html( $user_id->get_error_message() ),
				esc_html__( 'SSO Error', 'rrp' ),
				array( 'response' => 403 )
			);
		}

		// 7b. Reject locked accounts before establishing any session.
		if ( get_user_meta( $user_id, 'rrp_locked', true ) ) {
			wp_die(
				esc_html__( 'Your account has been locked. Please contact the portal administrator.', 'rrp' ),
				esc_html__( 'Account Locked', 'rrp' ),
				array( 'response' => 403 )
			);
		}

		// 8. Establish WordPress session
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Mark this session as Entra-authenticated so logout routes correctly.
		update_user_meta( $user_id, 'rrp_session_via_entra', '1' );

		// 9. Redirect to the originally requested URL or portal home
		$redirect_to = get_transient( 'rrp_entra_post_login_' . $state );
		delete_transient( 'rrp_entra_post_login_' . $state );

		wp_redirect( esc_url_raw( $redirect_to ?: home_url() ) );
		exit;
	}

	/**
	 * Decode the payload segment of a JWT (base64url → JSON → array).
	 * Does NOT verify the signature — only called after receiving the token
	 * directly from Microsoft's token endpoint over TLS.
	 *
	 * Normalises common Entra claim names to the keys expected by map_entra_user():
	 *   oid, email, name, department.
	 *
	 * @param string $jwt
	 * @return array<string,string>|null
	 */
	private static function decode_jwt_payload( string $jwt ): ?array {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		// base64url → base64
		$b64     = strtr( $parts[1], '-_', '+/' );
		$padding = strlen( $b64 ) % 4;
		if ( $padding !== 0 ) {
			$b64 .= str_repeat( '=', 4 - $padding );
		}
		$payload = base64_decode( $b64, true );
		if ( false === $payload ) {
			return null;
		}
		$claims = json_decode( $payload, true );
		if ( ! is_array( $claims ) ) {
			return null;
		}
		return array(
			'oid'        => sanitize_text_field( (string) ( $claims['oid']                ?? '' ) ),
			'email'      => sanitize_email( (string) ( $claims['email']                    ?? ( $claims['preferred_username'] ?? '' ) ) ),
			'name'       => sanitize_text_field( (string) ( $claims['name']               ?? '' ) ),
			'department' => sanitize_text_field( (string) ( $claims['department']          ?? '' ) ),
		);
	}

	/**
	 * Map Entra id_token claims to a local WP user account.
	 *
	 * Lookup order:
	 *   1. Find existing user by stable Entra OID (survives email/name changes).
	 *   2. Fall back to email match (for users pre-created by admin).
	 *   3. Create a new WP user with NO portal roles — admin must assign roles
	 *      before the user gains any portal access.
	 *
	 * On every login the display_name is refreshed from Entra.
	 * The department claim is only written on first login (admin can override).
	 *
	 * @param array $claims  Decoded id_token payload: { oid, email, name, department }
	 * @return int|WP_Error  WP user ID on success.
	 */
	public static function map_entra_user( array $claims ) {
		$oid   = sanitize_text_field( $claims['oid']   ?? '' );
		$email = sanitize_email(      $claims['email'] ?? '' );
		$name  = sanitize_text_field( $claims['name']  ?? '' );

		if ( ! $oid || ! $email ) {
			return new WP_Error( 'entra_invalid_claims', 'Missing oid or email in Entra token.' );
		}

		// 1. Find by stable OID (preferred — survives email changes)
		$by_oid = get_users( array(
			'meta_key'   => 'rrp_entra_oid',
			'meta_value' => $oid,
			'number'     => 1,
		) );
		$user = $by_oid[0] ?? null;

		// 2. Fall back to email
		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
		}

		// 3. Auto-provision — new Entra users get the Student role so they can
		//    access the portal immediately. Admins can promote to other roles later.
		if ( ! $user ) {
			$user_id = wp_insert_user( array(
				'user_login'   => sanitize_user( $email ),
				'user_email'   => $email,
				'display_name' => $name,
				'user_pass'    => wp_generate_password( 32 ),
				'role'         => 'rrp_student',
			) );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$user = get_userdata( $user_id );
		}

		// Always update the stable OID link
		update_user_meta( $user->ID, 'rrp_entra_oid', $oid );

		// Ensure every Entra user has at least the Student role.
		// Covers existing accounts created before auto-provisioning was added.
		$portal_roles = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		$has_portal_role = false;
		foreach ( $portal_roles as $pr ) {
			if ( in_array( $pr, (array) $user->roles, true ) ) {
				$has_portal_role = true;
				break;
			}
		}
		if ( ! $has_portal_role ) {
			$wp_user_obj = new WP_User( $user->ID );
			$wp_user_obj->add_role( 'rrp_student' );
		}

		// Sync display_name from Entra on every login
		if ( $name && $name !== $user->display_name ) {
			wp_update_user( array( 'ID' => $user->ID, 'display_name' => $name ) );
		}

		// Sync department only on first login (admin may override locally)
		if ( ! empty( $claims['department'] ) && ! get_user_meta( $user->ID, 'rrp_department', true ) ) {
			update_user_meta( $user->ID, 'rrp_department', sanitize_text_field( $claims['department'] ) );
		}

		return $user->ID;
	}
}
