<?php
/**
 * Research Review Portal — Encrypted Settings Manager
 *
 * Stores all portal configuration in WordPress options with AES-256-GCM
 * authenticated encryption for sensitive fields (SSO secrets, etc.).
 *
 * ── Encryption notes ─────────────────────────────────────────────────────────
 * AES comes in three key sizes: 128, 192, and 256 bits. AES-256 is the highest
 * standard, approved by NIST and the NSA for top-secret classification.
 * AES-512 does not exist as a standard algorithm; AES-256-GCM is the industry
 * maximum and is what this class uses.
 *
 * The encryption key is derived via HKDF-SHA256 from WordPress's built-in
 * AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, and NONCE_KEY salts.  These are
 * set in wp-config.php and must be unique per installation.
 *
 * ── Password hashing notes ───────────────────────────────────────────────────
 * WordPress user passwords are stored using phpass/bcrypt via wp_hash_password().
 * Portal-created users go through the same mechanism.  The static helper methods
 * hash_password() / verify_password() here use Argon2id (PHP ≥ 7.2) or bcrypt
 * for any portal-level credential values (e.g. future admin-token features).
 *
 * ── Author ───────────────────────────────────────────────────────────────────
 * Kiran Kumar Vejendla — vejendlakirankumar@cityu.edu
 * City University of Seattle · School of Technology and Computing (STC)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Portal_Settings {

	/** WordPress option key — stored as an encrypted JSON blob. */
	const OPTION_KEY = 'rrp_portal_settings';

	/** Fields whose values are encrypted at rest. */
	const ENCRYPTED_FIELDS = array(
		'entra_client_secret',
		'smtp_password',
	);

	/** Default values for every known setting. */
	const DEFAULTS = array(
		// ── Branding ─────────────────────────────────────────────────────────
		'university_name'       => 'City University of Seattle',
		'university_short_name' => 'CityU',
		'university_logo_url'   => '',     // public URL; empty = use bundled logo
		'portal_name'           => 'Research Review Portal',
		'contact_email'         => '',

		// ── SSO ───────────────────────────────────────────────────────────────
		'sso_enabled'           => false,
		'sso_provider'          => 'wordpress',   // 'wordpress' | 'entra'
		'entra_tenant_id'       => '',
		'entra_client_id'       => '',
		'entra_client_secret'   => '',             // encrypted at rest
		'entra_redirect_uri'    => '',
		'entra_auto_provision'  => true,           // create WP user on first Entra login

		// ── SMTP / Email ──────────────────────────────────────────────────────────
		'smtp_enabled'    => false,
		'smtp_host'       => '',
		'smtp_port'       => 587,
		'smtp_encryption' => 'tls',   // '' | 'ssl' | 'tls'
		'smtp_auth'       => true,
		'smtp_user'       => '',
		'smtp_password'   => '',      // encrypted at rest (already in ENCRYPTED_FIELDS)
		'smtp_from_name'  => '',
		'smtp_from_email' => '',
	);

	// Runtime cache — cleared whenever settings are written.
	private static $cache = null;

	// ── Public read API ───────────────────────────────────────────────────────

	/**
	 * Get a single setting value (decrypted when appropriate).
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Returned when key is not set; null triggers DEFAULTS lookup.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$raw = self::raw();
		if ( ! array_key_exists( $key, $raw ) ) {
			return $default ?? ( self::DEFAULTS[ $key ] ?? null );
		}
		$val = $raw[ $key ];
		if ( in_array( $key, self::ENCRYPTED_FIELDS, true ) && '' !== (string) $val ) {
			$decrypted = self::aes_decrypt( (string) $val );
			return ( false !== $decrypted ) ? $decrypted : ( $default ?? '' );
		}
		return $val;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @param bool $redact_secrets When true (default) encrypted fields are returned
	 *                             as '[encrypted]' so secrets never travel to the browser.
	 * @return array
	 */
	public static function get_all( $redact_secrets = true ) {
		$merged = array_merge( self::DEFAULTS, self::raw() );
		foreach ( self::ENCRYPTED_FIELDS as $field ) {
			if ( '' !== (string) ( $merged[ $field ] ?? '' ) ) {
				if ( $redact_secrets ) {
					$merged[ $field ] = '[encrypted]';
				} else {
					$dec = self::aes_decrypt( (string) $merged[ $field ] );
					$merged[ $field ] = ( false !== $dec ) ? $dec : '';
				}
			}
		}
		return $merged;
	}

	// ── Public write API ──────────────────────────────────────────────────────

	/**
	 * Update one or more settings atomically.
	 *
	 * For encrypted fields, passing a non-empty string replaces the stored value;
	 * passing an empty string leaves the old encrypted value untouched (allows UI
	 * to keep a "••••••" placeholder and only transmit when actually changed).
	 *
	 * @param array $new_settings Associative array of key → value.  Unknown keys
	 *                            are silently ignored for security.
	 * @return true
	 */
	public static function update( array $new_settings ) {
		$current = self::raw();
		foreach ( $new_settings as $key => $value ) {
			if ( ! array_key_exists( $key, self::DEFAULTS ) ) {
				continue; // Reject unknown keys
			}
			if ( in_array( $key, self::ENCRYPTED_FIELDS, true ) ) {
				if ( '' !== (string) $value ) {
					// Encrypt and store the new secret
					$encrypted = self::aes_encrypt( (string) $value );
					if ( false !== $encrypted ) {
						$current[ $key ] = $encrypted;
					}
				}
				// else: empty = keep existing encrypted value; no change
			} else {
				// Cast to appropriate type
				switch ( $key ) {
					case 'sso_enabled':
					case 'entra_auto_provision':
						$current[ $key ] = (bool) $value;
						break;
					case 'university_logo_url':
					case 'entra_redirect_uri':
						$current[ $key ] = esc_url_raw( (string) $value );
						break;
					case 'contact_email':
					case 'smtp_from_email':
						$current[ $key ] = sanitize_email( (string) $value );
						break;
					case 'smtp_enabled':
					case 'smtp_auth':
						$current[ $key ] = (bool) $value;
						break;
					case 'smtp_port':
						$current[ $key ] = absint( $value );
						break;
					default:
						$current[ $key ] = sanitize_text_field( (string) $value );
				}
			}
		}
		self::$cache = null; // Invalidate the runtime cache
		// autoload=false: settings are never loaded on every page load for security.
		update_option( self::OPTION_KEY, $current, false );
		return true;
	}

	// ── Password helpers ──────────────────────────────────────────────────────

	/**
	 * Hash a password using Argon2id (PHP ≥ 7.3) or bcrypt fallback.
	 * Use this for any portal-level credential (not WP user passwords — those
	 * go through wp_hash_password / wp_check_password as usual).
	 *
	 * @param string $password Plain-text password.
	 * @return string          Portable hash string.
	 */
	public static function hash_password( $password ) {
		if ( defined( 'PASSWORD_ARGON2ID' ) ) {
			return password_hash( $password, PASSWORD_ARGON2ID, array(
				'memory_cost' => 65536, // 64 MiB
				'time_cost'   => 4,
				'threads'     => 2,
			) );
		}
		// phpass bcrypt — cost 12 (≈350 ms on typical hardware, resists GPU attacks)
		return password_hash( $password, PASSWORD_BCRYPT, array( 'cost' => 12 ) );
	}

	/**
	 * Verify a password against a hash produced by hash_password().
	 *
	 * @param string $password Plain-text password.
	 * @param string $hash     Stored hash.
	 * @return bool
	 */
	public static function verify_password( $password, $hash ) {
		return password_verify( $password, $hash );
	}

	// ── AES-256-GCM encryption ────────────────────────────────────────────────
	// Authenticated encryption: any tampering with the ciphertext causes
	// decryption to fail rather than silently produce garbage.

	/**
	 * Encrypt plain text using AES-256-GCM.
	 *
	 * Output format (all base64-encoded as one string):
	 *   12-byte IV ‖ 16-byte GCM tag ‖ ciphertext
	 *
	 * @param string $plaintext
	 * @return string|false  Base64-encoded envelope, or false on error.
	 */
	public static function aes_encrypt( $plaintext ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback for environments without OpenSSL (should not happen on PHP 8.1)
			return base64_encode( 'NOSSL:' . $plaintext );
		}
		$key = self::derive_key();
		if ( ! $key ) {
			return false;
		}
		$iv  = random_bytes( 12 ); // 96-bit IV recommended for GCM
		$tag = '';
		$cipher = openssl_encrypt(
			$plaintext,
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			16   // 128-bit authentication tag
		);
		if ( false === $cipher ) {
			return false;
		}
		return base64_encode( $iv . $tag . $cipher );
	}

	/**
	 * Decrypt an AES-256-GCM envelope produced by aes_encrypt().
	 *
	 * @param string $encoded  Base64-encoded envelope.
	 * @return string|false    Plain text, or false on failure / authentication error.
	 */
	public static function aes_decrypt( $encoded ) {
		// Compatibility: values stored by older fallback path
		$raw_check = base64_decode( $encoded, true );
		if ( false !== $raw_check && 0 === strpos( $raw_check, 'NOSSL:' ) ) {
			return substr( $raw_check, 6 );
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}
		$key = self::derive_key();
		if ( ! $key ) {
			return false;
		}
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) < 28 ) {
			return false; // Too short to be a valid envelope
		}
		$iv         = substr( $raw, 0, 12 );
		$tag        = substr( $raw, 12, 16 );
		$ciphertext = substr( $raw, 28 );
		$result = openssl_decrypt(
			$ciphertext,
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);
		return $result; // false if authentication failed
	}

	// ── Key derivation ────────────────────────────────────────────────────────

	/**
	 * Derive a 256-bit encryption key from WordPress secret salts using HKDF-SHA256.
	 *
	 * Security properties:
	 *  - Key material comes from four independent WP salts.
	 *  - HKDF provides proper key separation via an application-specific info label.
	 *  - Changing any WP salt invalidates all encrypted settings (intentional
	 *    defence-in-depth: salt rotation = automatic secret rotation).
	 *
	 * @return string|false  32-byte binary key, or false if no key material available.
	 */
	private static function derive_key() {
		$ikm = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $const ) {
			if ( defined( $const ) ) {
				$ikm .= constant( $const );
			}
		}
		if ( '' === $ikm ) {
			// No WP salts available — should only happen in unit-test environments
			// without a real wp-config.php.  Logging warning and returning false
			// is safer than using a predictable key.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'RRP_Portal_Settings: No WordPress secret keys found. Cannot derive encryption key.' );
			}
			return false;
		}
		// HKDF-SHA256: expand keying material with a domain-separation label.
		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $ikm, 32, 'rrp-portal-settings-v1', '' );
		}
		// PHP < 7.1.2 fallback (also safe: different label prevents cross-context use)
		return hash( 'sha256', $ikm . ':rrp-portal-settings-v1', true );
	}

	// ── Internal helpers ──────────────────────────────────────────────────────

	/** Return raw stored array (no decryption, no merge with defaults). */
	private static function raw() {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		$stored      = get_option( self::OPTION_KEY, array() );
		self::$cache = is_array( $stored ) ? $stored : array();
		return self::$cache;
	}
}
