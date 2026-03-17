<?php
/**
 * Plugin Name: RRP – Argon2id Password Hashing
 * Description: Replaces WordPress phpass/bcrypt with Argon2id. Manages default portal admin.
 * Version:     1.0.0
 *
 * Must-use plugin — placed in wp-content/mu-plugins/ so it loads before
 * pluggable.php, allowing the function-existence guards to honour our overrides.
 */

// ─────────────────────────────────────────────────────────────────────────────
// 0.  Argon2id parameters (shared by hash and verify)
// ─────────────────────────────────────────────────────────────────────────────

define( 'RRP_ARGON2ID_OPTIONS', array(
	'memory_cost' => 65536, // 64 MiB
	'time_cost'   => 4,
	'threads'     => 2,
) );

// ─────────────────────────────────────────────────────────────────────────────
// 1.  Override wp_hash_password  →  Argon2id
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_hash_password' ) ) {
	/**
	 * Hash a password using Argon2id.
	 *
	 * @param  string $password Plain-text password.
	 * @return string           Argon2id hash string.
	 */
	function wp_hash_password( $password ) {
		return password_hash( $password, PASSWORD_ARGON2ID, RRP_ARGON2ID_OPTIONS );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 2.  Override wp_check_password  →  Argon2id + legacy fallback + auto-migrate
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_check_password' ) ) {
	/**
	 * Verify a password against its stored hash.
	 *
	 * Handles three formats in priority order:
	 *   1. Argon2id   – our new format ($argon2id$…)
	 *   2. WP bcrypt  – WordPress 6.4+ format  ($wp$2y$…)
	 *   3. phpass     – legacy WP format       ($P$…) and bare MD5
	 *
	 * On a successful legacy match the hash is silently migrated to Argon2id
	 * so the next login is native.
	 *
	 * @param  string     $password Plain-text password.
	 * @param  string     $hash     Stored hash from wp_users.user_pass.
	 * @param  int|string $user_id  Optional user ID used for migration.
	 * @return bool
	 */
	function wp_check_password( $password, $hash, $user_id = '' ) {

		// ── 1. Argon2id ──────────────────────────────────────────────────────
		if ( str_starts_with( $hash, '$argon2id$' ) ) {
			$ok = password_verify( $password, $hash );

			// Upgrade cost parameters if they have changed since the hash was stored.
			if ( $ok && $user_id && password_needs_rehash( $hash, PASSWORD_ARGON2ID, RRP_ARGON2ID_OPTIONS ) ) {
				rrp_update_user_hash( (int) $user_id, wp_hash_password( $password ) );
			}

			return apply_filters( 'check_password', $ok, $password, $hash, $user_id );
		}

		// ── 2. WordPress 6.4+ bcrypt  ($wp$2y$…) ────────────────────────────
		if ( str_starts_with( $hash, '$wp$' ) ) {
			// WP strips its "$wp" prefix before calling password_verify.
			$ok = password_verify( $password, substr( $hash, 3 ) );

			if ( $ok && $user_id ) {
				rrp_update_user_hash( (int) $user_id, wp_hash_password( $password ) );
			}

			return apply_filters( 'check_password', $ok, $password, $hash, $user_id );
		}

		// ── 3. Legacy phpass / bare MD5 ──────────────────────────────────────
		if ( ! class_exists( 'PasswordHash' ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
		}
		$wp_hasher = new PasswordHash( 8, true );
		$ok        = $wp_hasher->CheckPassword( $password, $hash );

		if ( $ok && $user_id ) {
			rrp_update_user_hash( (int) $user_id, wp_hash_password( $password ) );
		}

		return apply_filters( 'check_password', $ok, $password, $hash, $user_id );
	}

	/**
	 * Raw DB update of user_pass — avoids the session-destruction side-effect
	 * of wp_set_password() when migrating during normal login.
	 *
	 * @param  int    $user_id  WordPress user ID.
	 * @param  string $new_hash The new Argon2id hash.
	 */
	function rrp_update_user_hash( $user_id, $new_hash ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->users,
			array( 'user_pass' => $new_hash ),
			array( 'ID'        => $user_id ),
			array( '%s' ),
			array( '%d' )
		);
		wp_cache_delete( $user_id, 'users' );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 3.  Default portal admin management
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'rrp_manage_default_admin' );

/**
 * Ensure a fallback "admin / admin123" portal admin always exists.
 *
 * Rules:
 *  - If NO user-defined rrp_admin users exist  → create/unlock the default admin.
 *  - If user-defined rrp_admin users are present → lock the default admin so
 *    admin123 cannot be used to log in.
 *
 * Result is cached in a 5-minute transient to avoid per-request DB overhead.
 */
function rrp_manage_default_admin() {
	if ( false !== get_transient( 'rrp_admin_check_v1' ) ) {
		return;
	}

	$all_admin_ids = get_users( array(
		'role'   => 'rrp_admin',
		'number' => -1,
		'fields' => 'ID',
	) );

	$default_id        = null;
	$userdefined_count = 0;

	foreach ( $all_admin_ids as $uid ) {
		if ( '1' === get_user_meta( $uid, 'rrp_is_default_admin', true ) ) {
			$default_id = (int) $uid;
		} else {
			$userdefined_count++;
		}
	}

	if ( $userdefined_count > 0 ) {
		// Lock the default admin — admin123 must no longer work.
		if ( null !== $default_id ) {
			update_user_meta( $default_id, 'rrp_admin_locked', '1' );
		}
	} else {
		// No user-defined admin — ensure the fallback account exists and is usable.
		if ( null === $default_id ) {
			$existing = get_user_by( 'login', 'admin' );

			if ( $existing ) {
				// Adopt the existing 'admin' WordPress account as the portal default.
				$wp_user = new WP_User( $existing->ID );
				$wp_user->set_role( 'rrp_admin' );
				update_user_meta( $existing->ID, 'rrp_is_default_admin', '1' );
				delete_user_meta( $existing->ID, 'rrp_admin_locked' );
			} else {
				// Create a fresh default admin.
				$uid = wp_insert_user( array(
					'user_login' => 'admin',
					'user_pass'  => 'admin123',
					'user_email' => 'admin@portal.local',
					'role'       => 'rrp_admin',
				) );

				if ( ! is_wp_error( $uid ) ) {
					update_user_meta( $uid, 'rrp_is_default_admin', '1' );
				}
			}
		} else {
			// Unlock the existing default admin.
			delete_user_meta( $default_id, 'rrp_admin_locked' );
		}
	}

	set_transient( 'rrp_admin_check_v1', 1, 5 * MINUTE_IN_SECONDS );
}

// ─────────────────────────────────────────────────────────────────────────────
// 4.  Block login for a locked default admin
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'authenticate', 'rrp_block_locked_default_admin', 100, 3 );

/**
 * Return a WP_Error if the authenticating user is the locked default admin.
 * Priority 100 ensures this runs after WP's own credential check (priority 20).
 *
 * @param  WP_User|WP_Error|null $user
 * @param  string                $username
 * @param  string                $password
 * @return WP_User|WP_Error|null
 */
function rrp_block_locked_default_admin( $user, $username, $password ) {
	if ( $user instanceof WP_User &&
		'1' === get_user_meta( $user->ID, 'rrp_admin_locked', true ) ) {
		return new WP_Error(
			'rrp_default_admin_locked',
			'This account is disabled. Sign in with your organisational credentials.'
		);
	}
	return $user;
}
