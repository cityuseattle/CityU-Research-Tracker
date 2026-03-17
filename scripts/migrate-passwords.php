<?php
/**
 * Password Migration Script — Argon2id
 *
 * Resets ALL WordPress user passwords to Argon2id hashes.
 *
 *   Default portal admin  (rrp_is_default_admin = 1)  →  admin123
 *   All other users                                    →  test123
 *
 * Usage (via WP-CLI on the server):
 *   wp --path=/var/www/html eval-file /tmp/migrate-passwords.php
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	die( 'Must be run via: wp eval-file /tmp/migrate-passwords.php' . PHP_EOL );
}

$argon2id_opts = array(
	'memory_cost' => 65536,
	'time_cost'   => 4,
	'threads'     => 2,
);

if ( ! defined( 'PASSWORD_ARGON2ID' ) ) {
	WP_CLI::error( 'PASSWORD_ARGON2ID is not defined. PHP must be compiled with Argon2 support (PHP 7.3+).' );
	return;
}

global $wpdb;

// Get all users.
$users = get_users( array( 'number' => -1, 'fields' => array( 'ID', 'user_login' ) ) );

if ( empty( $users ) ) {
	WP_CLI::warning( 'No users found.' );
	return;
}

WP_CLI::log( sprintf( 'Migrating %d user(s) to Argon2id...', count( $users ) ) );

$count = 0;

foreach ( $users as $u ) {
	$is_default_admin = ( '1' === get_user_meta( $u->ID, 'rrp_is_default_admin', true ) );
	$plain_password   = $is_default_admin ? 'admin123' : 'test123';

	$new_hash = password_hash( $plain_password, PASSWORD_ARGON2ID, $argon2id_opts );

	$updated = $wpdb->update(
		$wpdb->users,
		array(
			'user_pass'           => $new_hash,
			'user_activation_key' => '',    // invalidate any pending password-reset links
		),
		array( 'ID' => $u->ID ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	wp_cache_delete( $u->ID, 'users' );

	if ( false === $updated ) {
		WP_CLI::warning( "  FAILED  #{$u->ID} ({$u->user_login})" );
	} else {
		$label = $is_default_admin ? 'admin123  [default admin]' : 'test123';
		WP_CLI::log( "  OK  #{$u->ID} ({$u->user_login})  →  {$label}  (Argon2id)" );
		$count++;
	}
}

// Delete the admin-check transient so the mu-plugin re-evaluates on next request.
delete_transient( 'rrp_admin_check_v1' );

WP_CLI::success( "Migrated {$count} of " . count( $users ) . ' user(s) to Argon2id.' );
WP_CLI::log( '' );
WP_CLI::log( 'Login credentials after migration:' );
WP_CLI::log( '  Default portal admin:  admin  /  admin123  (disabled once a real admin exists)' );
WP_CLI::log( '  All other users:       <username>  /  test123' );
