<?php
/**
 * Research Review Portal - Data layer (submissions, reviewers, config).
 * Mirrors the Node server.js logic for WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Portal_Data {

	const SUBMISSIONS_FILE = 'submissions.json';

	const CONFIG_FILE      = 'config.json';
	const WEBHOOKS_FILE    = 'webhooks.json';

	const WITHDRAWABLE = array(
		'Submitted - Awaiting Review',
		'Submitted',
		'Under Initial Review',
		'Administrative Review',
		'Revision Required',
		'Revision Submitted',
	);

	const PUBLIC_STATUSES = array(
		'Accepted',
		'Conditionally Accepted',
		'Waitlisted',
		'Confirmed for Presentation',
		'Approved for Submission',
		'Published',
	);

	const WORKFLOW_STAGES = array(
		'conference'       => array( 'Initial Screening', 'Reviewer Assignment', 'Peer Review', 'Review Consolidation', 'Final Decision', 'Confirmation' ),
		'publication'      => array( 'Administrative Check', 'Reviewer Matching', 'Expert Review', 'Director Assessment', 'Final Decision', 'Tracking' ),
		'student-project'  => array( 'Advisor Matching', 'Advisor Consultation', 'Feasibility Check', 'Director Approval', 'Project Setup', 'Milestone Tracking' ),
		'grant'            => array( 'Compliance Check', 'Review Assignment', 'Multi-Criteria Review', 'Committee Meeting', 'Final Decision', 'Development Support', 'Submission Tracking' ),
	);

	/**
	 * Get workflow stages for a type, preferring data/config.json submissionTypes over the static constant.
	 * Falls back to static constant then to a generic 2-stage default.
	 */
	public static function get_workflow_stages( string $type ): array {
		$config = self::read_config();
		foreach ( $config['submissionTypes'] ?? array() as $st ) {
			if ( ( $st['id'] ?? '' ) === $type && ! empty( $st['stages'] ) ) {
				return array_values( (array) $st['stages'] );
			}
		}
		// Fall back to static constant
		if ( isset( self::WORKFLOW_STAGES[ $type ] ) ) {
			return self::WORKFLOW_STAGES[ $type ];
		}
		return array( 'Initial Review', 'Final Approval' );
	}

	/**
	 * Return all submission types from config, falling back to the static set.
	 */
	public static function get_submission_types(): array {
		$config = self::read_config();
		if ( ! empty( $config['submissionTypes'] ) ) {
			return (array) $config['submissionTypes'];
		}
		// Bootstrap from the static constant
		$types = array();
		foreach ( self::WORKFLOW_STAGES as $id => $stages ) {
			$types[] = array( 'id' => $id, 'label' => ucwords( str_replace( '-', ' ', $id ) ), 'description' => '', 'stages' => $stages );
		}
		return $types;
	}

	const DEFAULT_REVIEWERS = array(
		// Seed data uses placeholder emails. Replace with real addresses via the
		// admin Users panel — never hardcode institutional email addresses in source.
		array( 'id' => 'r1', 'name' => 'Reviewer One',   'email' => 'reviewer1@example.com', 'submissionTypes' => array( 'conference', 'publication', 'grant' ) ),
		array( 'id' => 'r2', 'name' => 'Reviewer Two',   'email' => 'reviewer2@example.com', 'submissionTypes' => array( 'conference', 'publication' ) ),
		array( 'id' => 'r3', 'name' => 'Reviewer Three', 'email' => 'reviewer3@example.com', 'submissionTypes' => array( 'conference', 'publication', 'student-project' ) ),
		array( 'id' => 'r4', 'name' => 'Reviewer Four',  'email' => 'reviewer4@example.com', 'submissionTypes' => array( 'conference', 'publication', 'student-project', 'grant' ) ),
		array( 'id' => 'r5', 'name' => 'Reviewer Five',  'email' => 'reviewer5@example.com', 'submissionTypes' => array( 'conference', 'publication', 'student-project' ) ),
		array( 'id' => 'r6', 'name' => 'Reviewer Six',   'email' => 'reviewer6@example.com', 'submissionTypes' => array( 'conference', 'publication', 'grant' ) ),
		array( 'id' => 'r7', 'name' => 'Reviewer Seven', 'email' => 'reviewer7@example.com', 'submissionTypes' => array( 'conference', 'publication', 'student-project', 'grant' ) ),
	);

	public static function ensure_data_dir() {
		if ( ! file_exists( RRP_DATA_DIR ) ) {
			wp_mkdir_p( RRP_DATA_DIR );
		}
		if ( ! file_exists( RRP_UPLOADS_DIR ) ) {
			wp_mkdir_p( RRP_UPLOADS_DIR );
		}
		// Block direct HTTP access to both directories.
		self::write_dir_protection( RRP_DATA_DIR );
		self::write_dir_protection( RRP_UPLOADS_DIR );
	}

	/**
	 * Write .htaccess and index.php into a directory to prevent direct web access.
	 * .htaccess covers Apache with AllowOverride enabled.
	 * index.php (silence is golden) prevents directory listing as a fallback.
	 */
	private static function write_dir_protection( string $dir ) {
		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules  = "# Block all direct HTTP access to this directory.\n";
			$rules .= "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
			$rules .= "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
			file_put_contents( $htaccess, $rules, LOCK_EX );
		}
		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}
	}

	/** Sanitize filename for storage (match Node sanitizeFilename). */
	public static function sanitize_filename( $name ) {
		$name = $name ? preg_replace( '/[^a-zA-Z0-9._-]/', '_', $name ) : 'file';
		// Strip consecutive dots to prevent path traversal sequences (e.g. ../ )
		$name = preg_replace( '/\.{2,}/', '_', $name );
		// Strip leading dots (hidden file prevention)
		$name = ltrim( $name, '.' );
		if ( $name === '' || $name === '_' ) {
			$name = 'file';
		}
		return substr( $name, 0, 100 );
	}

	/**
	 * Read all submissions from the database.
	 * Returns the same { submissions: [], nextIds: {} } envelope that all callers expect.
	 */
	public static function read_submissions() {
		global $wpdb;
		self::ensure_data_dir(); // Keeps the uploads/ directory protected.
		$table = $wpdb->prefix . 'rrp_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows  = $wpdb->get_col( "SELECT `data` FROM `{$table}` ORDER BY `created_at` ASC, `submission_id` ASC" );
		$submissions = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $json ) {
				$sub = json_decode( (string) $json, true );
				if ( is_array( $sub ) ) {
					$submissions[] = $sub;
				}
			}
		}
		// Re-derive status for non-terminal submissions so stale DB values never surface.
		$_preserved_statuses = array( 'Draft', 'Withdrawn', 'Cancelled', 'Full Paper Invited', 'Appeal Pending', 'Appeal Under Review' );
		foreach ( $submissions as &$_sub ) {
			if ( ! in_array( $_sub['status'] ?? '', $_preserved_statuses, true ) ) {
				$_sub['status'] = self::derive_submission_status( $_sub );
			}
		}
		unset( $_sub );
		$nextIds = get_option( 'rrp_next_ids', array() );
		return array(
			'submissions' => $submissions,
			'nextIds'     => is_array( $nextIds ) ? $nextIds : array(),
		);
	}

	/**
	 * Persist the full submissions data set to the database.
	 * Upserts every submission present in $data and removes any rows no longer
	 * in the array — exactly mirroring the previous whole-file-write behaviour.
	 * The entire operation runs inside a transaction for atomicity.
	 */
	public static function write_submissions( $data ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'rrp_submissions';
		$submissions = isset( $data['submissions'] ) ? (array) $data['submissions'] : array();
		$nextIds     = isset( $data['nextIds'] )     ? (array) $data['nextIds']     : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'START TRANSACTION' );

		$ids_in_data = array();
		foreach ( $submissions as $sub ) {
			$id = $sub['id'] ?? null;
			if ( ! $id || ! is_string( $id ) ) {
				continue;
			}
			$ids_in_data[]  = (string) $id;
			$created_raw    = $sub['createdAt'] ?? null;
			$created_ts     = $created_raw ? strtotime( $created_raw ) : false;
			$created_at_db  = ( false !== $created_ts ) ? gmdate( 'Y-m-d H:i:s', $created_ts ) : current_time( 'mysql', true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->replace(
				$table,
				array(
					'submission_id'   => (string) $id,
					'status'          => (string) ( $sub['status'] ?? '' ),
					'submitter_email' => strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) ),
					'submission_type' => (string) ( $sub['submissionType'] ?? $sub['type'] ?? '' ),
					'created_at'      => $created_at_db,
					'data'            => (string) wp_json_encode( $sub, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Purge rows no longer present in the caller's data set.
		if ( ! empty( $ids_in_data ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids_in_data ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `submission_id` NOT IN ({$placeholders})", ...$ids_in_data ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "TRUNCATE TABLE `{$table}`" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'COMMIT' );
		update_option( 'rrp_next_ids', $nextIds, false );
	}

	/**
	 * Generate the next sequential submission ID for the given type + year.
	 * Reads and writes only the rrp_next_ids option — never touches the
	 * submissions table — making it both fast and free of race conditions
	 * with concurrent submission writes.
	 */
	public static function next_id( $type ) {
		$year    = (int) gmdate( 'Y' );
		$key     = $type . '-' . $year;
		$nextIds = (array) ( get_option( 'rrp_next_ids', array() ) ?: array() );
		$num     = ( (int) ( $nextIds[ $key ] ?? 0 ) ) + 1;
		$nextIds[ $key ] = $num;
		update_option( 'rrp_next_ids', $nextIds, false );
		return $type . '-' . $year . '-' . str_pad( (string) $num, 3, '0', STR_PAD_LEFT );
	}

	public static function word_count( $text ) {
		if ( ! is_string( $text ) || trim( $text ) === '' ) {
			return 0;
		}
		$words = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return $words ? count( $words ) : 0;
	}

	public static function validate_submission( $type, $body, $is_draft = false ) {
		$body = is_array( $body ) ? $body : array();
		if ( $is_draft ) {
			return array();
		}
		// Config-driven validation: if the submission type defines requiredFields,
		// use those instead of the hardcoded switch block below.
		$config = self::read_config();
		foreach ( $config['submissionTypes'] ?? array() as $st ) {
			if ( ( $st['id'] ?? '' ) === $type && isset( $st['requiredFields'] ) && is_array( $st['requiredFields'] ) ) {
				$errors      = array();
				$field_labels = array(
					'title'                   => 'Title',
					'submitterName'           => 'Submitter name',
					'submitterEmail'          => 'Email',
					'affiliation'             => 'Affiliation',
					'abstract'                => 'Abstract',
					'keywords'                => 'Keywords',
					'researchArea'            => 'Research area',
					'presentationPreference'  => 'Presentation preference',
				);
				foreach ( $st['requiredFields'] as $field ) {
					if ( empty( trim( (string) ( $body[ $field ] ?? '' ) ) ) ) {
						$label     = $field_labels[ $field ] ?? ucwords( str_replace( array( '_', '-' ), ' ', (string) $field ) );
						$errors[]  = $label . ' is required.';
					}
				}
				// Universal title-length cap regardless of field list.
				if ( in_array( 'title', $st['requiredFields'], true ) && strlen( (string) ( $body['title'] ?? '' ) ) > 200 ) {
					$errors[] = 'Title must be 200 characters or less.';
				}
				return $errors;
			}
		}
		// Legacy hardcoded per-type validation (used when no requiredFields config exists).
		$errors = array();
		switch ( $type ) {
			case 'conference':
				if ( empty( trim( (string) ( $body['submitterName'] ?? '' ) ) ) ) {
					$errors[] = 'Submitter name is required.';
				}
				if ( empty( trim( (string) ( $body['submitterEmail'] ?? '' ) ) ) ) {
					$errors[] = 'Submitter email is required.';
				}
				if ( empty( trim( (string) ( $body['affiliation'] ?? '' ) ) ) ) {
					$errors[] = 'Affiliation is required.';
				}
				if ( empty( trim( (string) ( $body['title'] ?? '' ) ) ) ) {
					$errors[] = 'Title is required.';
				}
				if ( strlen( (string) ( $body['title'] ?? '' ) ) > 200 ) {
					$errors[] = 'Title must be 200 characters or less.';
				}
				if ( empty( trim( (string) ( $body['abstract'] ?? '' ) ) ) ) {
					$errors[] = 'Abstract is required.';
				} else {
					$wc = self::word_count( (string) ( $body['abstract'] ?? '' ) );
					if ( $wc < 250 || $wc > 500 ) {
						$errors[] = sprintf( 'Abstract must be 250–500 words (current: %d).', $wc );
					}
				}
				if ( empty( trim( (string) ( $body['keywords'] ?? '' ) ) ) ) {
					$errors[] = 'Keywords are required (3–5).';
				} else {
					$kw = array_filter( array_map( 'trim', preg_split( '/[,;]/', (string) ( $body['keywords'] ?? '' ) ) ) );
					if ( count( $kw ) < 3 || count( $kw ) > 5 ) {
						$errors[] = 'Provide 3–5 keywords.';
					}
				}
				if ( empty( trim( (string) ( $body['researchArea'] ?? '' ) ) ) ) {
					$errors[] = 'Research area/category is required.';
				}
				$pref = strtolower( (string) ( $body['presentationPreference'] ?? '' ) );
				if ( ! in_array( $pref, array( 'oral', 'poster' ), true ) ) {
					$errors[] = 'Select presentation preference: Oral or Poster.';
				}
				break;
			case 'publication':
				if ( empty( trim( (string) ( $body['submitterName'] ?? '' ) ) ) ) {
					$errors[] = 'Submitter name is required.';
				}
				if ( empty( trim( (string) ( $body['submitterEmail'] ?? '' ) ) ) ) {
					$errors[] = 'Submitter email is required.';
				}
				if ( empty( trim( (string) ( $body['title'] ?? '' ) ) ) ) {
					$errors[] = 'Title is required.';
				}
				if ( strlen( (string) ( $body['title'] ?? '' ) ) > 200 ) {
					$errors[] = 'Title must be 200 characters or less.';
				}
				if ( empty( trim( (string) ( $body['researchArea'] ?? '' ) ) ) ) {
					$errors[] = 'Research area/category is required.';
				}
				break;
			case 'student-project':
				if ( empty( trim( (string) ( $body['submitterName'] ?? '' ) ) ) ) {
					$errors[] = 'Student name is required.';
				}
				if ( empty( trim( (string) ( $body['submitterEmail'] ?? '' ) ) ) ) {
					$errors[] = 'Email is required.';
				}
				if ( empty( trim( (string) ( $body['title'] ?? '' ) ) ) ) {
					$errors[] = 'Project title is required.';
				}
				if ( empty( trim( (string) ( $body['researchArea'] ?? '' ) ) ) ) {
					$errors[] = 'Research area is required.';
				}
				break;
			case 'grant':
				if ( empty( trim( (string) ( $body['submitterName'] ?? '' ) ) ) ) {
					$errors[] = 'PI name is required.';
				}
				if ( empty( trim( (string) ( $body['submitterEmail'] ?? '' ) ) ) ) {
					$errors[] = 'Email is required.';
				}
				if ( empty( trim( (string) ( $body['title'] ?? '' ) ) ) ) {
					$errors[] = 'Proposal title is required.';
				}
				if ( empty( trim( (string) ( $body['researchArea'] ?? '' ) ) ) ) {
					$errors[] = 'Research area is required.';
				}
				break;
			default:
				// Dynamic type defined in config — validate the universal required fields.
				if ( empty( trim( (string) ( $body['title'] ?? '' ) ) ) ) {
					$errors[] = 'Title is required.';
				}
				if ( strlen( (string) ( $body['title'] ?? '' ) ) > 200 ) {
					$errors[] = 'Title must be 200 characters or less.';
				}
				if ( empty( trim( (string) ( $body['submitterName'] ?? '' ) ) ) ) {
					$errors[] = 'Submitter name is required.';
				}
				if ( empty( trim( (string) ( $body['submitterEmail'] ?? '' ) ) ) ) {
					$errors[] = 'Email is required.';
				}
				if ( empty( trim( (string) ( $body['researchArea'] ?? '' ) ) ) ) {
					$errors[] = 'Research area is required.';
				}
				break;
		}
		return $errors;
	}

	/**
	 * Return all reviewers from the WordPress database (single source of truth).
	 * Only users with the rrp_reviewer role are included — rrp_faculty is a
	 * separate advisor role and is not part of the reviewer assignment pool.
	 * Returns the same [{id, name, email, submissionTypes}] shape used everywhere
	 * in the portal so all callers work without modification.
	 */
	public static function read_reviewers() {
		$users = get_users( array(
			'role__in' => array( 'rrp_reviewer' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
		$list = array();
		foreach ( $users as $user ) {
			// Prefer explicit per-reviewer submission types; fall back to allowed types.
			$types = (array) ( get_user_meta( $user->ID, 'rrp_submission_types', true ) ?: array() );
			if ( empty( $types ) ) {
				$types = (array) ( get_user_meta( $user->ID, 'rrp_allowed_submission_types', true ) ?: array() );
			}
			$rid    = (string) ( get_user_meta( $user->ID, 'rrp_reviewer_id', true ) ?: 'wp_' . $user->ID );
			$list[] = array(
				'id'              => $rid,
				'name'            => $user->display_name,
				'email'           => $user->user_email,
				'submissionTypes' => array_values( array_filter( array_map( 'strval', $types ) ) ),
			);
		}
		return $list;
	}

	/**
	 * No-op: reviewers now live in the WordPress database only.
	 * Kept for backward compatibility; callers may safely be removed over time.
	 */
	public static function write_reviewers( $list ) {
		// DB is the single source of truth — nothing to write to disk.
	}

	/**
	 * No-op: reviewer data is read directly from the WordPress database via
	 * read_reviewers(). No JSON file needs to be kept in sync.
	 */
	public static function sync_reviewer_to_json( $user_id ) {
		// DB is the single source of truth — nothing to sync.
	}

	/**
	 * No-op: reviewer removal is handled by WordPress role management.
	 * When a user loses the rrp_reviewer/rrp_faculty role or is deleted,
	 * read_reviewers() will no longer return them automatically.
	 */
	public static function remove_reviewer_from_json( $email ) {
		// DB is the single source of truth — nothing to remove from disk.
	}

	/**
	 * Read portal configuration from wp_options (replaces config.json).
	 */
	public static function read_config() {
		$defaults = array(
			'stageRequirements'       => array(),
			'reviewerPools'           => array(),
			'poolCohorts'             => array(),
			'activeCohort'            => null,
			'defaultReviewersByStage' => array(),
			'programs'                => array(),
		);
		$stored = get_option( 'rrp_config', null );
		if ( is_array( $stored ) ) {
			// Merge persisted data over defaults so all keys are always present.
			return array_merge( $defaults, $stored );
		}
		return $defaults;
	}

	/**
	 * Persist portal configuration to wp_options (replaces config.json).
	 */
	public static function write_config( $config ) {
		update_option( 'rrp_config', $config, false );
	}

	/**
	 * Read webhook registrations from wp_options (replaces webhooks.json).
	 */
	public static function read_webhooks(): array {
		$stored = get_option( 'rrp_webhooks', array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Persist webhook registrations to wp_options (replaces webhooks.json).
	 */
	public static function write_webhooks( array $webhooks ): void {
		update_option( 'rrp_webhooks', $webhooks, false );
	}

	public static function select_from_pool( $config, $type, $count, $exclude_ids, $advance_round_robin = false ) {
		$pools   = $config['reviewerPools'] ?? array();
		$cohorts = $config['poolCohorts'] ?? array();
		$active  = $config['activeCohort'] ?? null;
		$pool_ids = array();
		if ( $active && ! empty( $cohorts[ $active ][ $type ] ) && is_array( $cohorts[ $active ][ $type ] ) ) {
			$pool_ids = $cohorts[ $active ][ $type ];
		} elseif ( ! empty( $pools[ $type ]['reviewerIds'] ) && is_array( $pools[ $type ]['reviewerIds'] ) ) {
			$pool_ids = $pools[ $type ]['reviewerIds'];
		}
		$pool_ids = array_values( array_diff( $pool_ids, $exclude_ids ) );
		if ( empty( $pool_ids ) || $count <= 0 ) {
			return array();
		}
		$type_pool = $pools[ $type ] ?? array();
		$mode      = isset( $type_pool['assignmentMode'] ) ? $type_pool['assignmentMode'] : 'random';
		if ( $mode !== 'fixed' && $mode !== 'random' ) {
			$mode = 'round_robin';
		}
		$selected = array();
		if ( $mode === 'fixed' ) {
			$selected = array_slice( $pool_ids, 0, $count );
		} elseif ( $mode === 'random' ) {
			// F16: Use cryptographically secure shuffle (Fisher-Yates with random_int)
			for ( $i = count( $pool_ids ) - 1; $i > 0; $i-- ) {
				$j              = random_int( 0, $i );
				$tmp            = $pool_ids[ $i ];
				$pool_ids[ $i ] = $pool_ids[ $j ];
				$pool_ids[ $j ] = $tmp;
			}
			$selected = array_slice( $pool_ids, 0, $count );
		} else {
			$len   = count( $pool_ids );
			$start = isset( $type_pool['lastRoundRobinIndex'] ) ? ( (int) $type_pool['lastRoundRobinIndex'] % max( 1, $len ) ) : 0;
			for ( $i = 0; count( $selected ) < $count && $i < $len; $i++ ) {
				$id = $pool_ids[ ( $start + $i ) % $len ];
				if ( ! in_array( $id, $selected, true ) ) {
					$selected[] = $id;
				}
			}
			if ( $advance_round_robin && ! empty( $selected ) ) {
				$next = ( $start + count( $selected ) ) % $len;
				if ( empty( $config['reviewerPools'][ $type ] ) ) {
					$config['reviewerPools'][ $type ] = array( 'reviewerIds' => $pool_ids, 'assignmentMode' => 'round_robin' );
				}
				$config['reviewerPools'][ $type ]['lastRoundRobinIndex'] = $next;
				self::write_config( $config );
			}
		}
		return array_slice( $selected, 0, $count );
	}

	public static function auto_assign_submission( $submission, $submitter_email ) {
		$type   = isset( $submission['type'] ) ? strtolower( $submission['type'] ) : '';
		$stages = self::get_workflow_stages( $type );
		if ( empty( $stages ) ) {
			return $submission;
		}
		$config    = self::read_config();
		$req_type  = $type === 'student-project' ? 'student' : $type;
		$req       = isset( $config['stageRequirements'][ $req_type ] ) && is_array( $config['stageRequirements'][ $req_type ] ) ? $config['stageRequirements'][ $req_type ] : array();
		$defaults  = isset( $config['defaultReviewersByStage'][ $type ] ) && is_array( $config['defaultReviewersByStage'][ $type ] ) ? $config['defaultReviewersByStage'][ $type ] : array();
		$reviewers = self::read_reviewers();
		$by_id     = array();
		foreach ( $reviewers as $r ) {
			$id = $r['id'] ?? $r['email'] ?? null;
			if ( $id ) {
				$by_id[ $id ] = $r;
			}
		}
		$submitter = strtolower( trim( (string) ( $submitter_email ?? $submission['submitterEmail'] ?? '' ) ) );
		$review_stages   = array();
		$assigned_reviewers = array();
		foreach ( $stages as $i => $stage_name ) {
			$objects = array();
			$default_list = isset( $defaults[ $stage_name ] ) && is_array( $defaults[ $stage_name ] ) ? $defaults[ $stage_name ] : array();
			if ( ! empty( $default_list ) ) {
				foreach ( $default_list as $r ) {
					if ( is_array( $r ) && ( ! empty( $r['id'] ) || ! empty( $r['email'] ) ) ) {
						$obj = array(
							'id'    => $r['id'] ?? null,
							'name'  => $r['name'] ?? '',
							'email' => $r['email'] ?? '',
						);
						if ( strtolower( trim( (string) ( $obj['email'] ?? '' ) ) ) !== $submitter ) {
							$objects[] = $obj;
						}
					} elseif ( isset( $by_id[ $r ] ) ) {
						$rev = $by_id[ $r ];
						$em = strtolower( trim( (string) ( $rev['email'] ?? '' ) ) );
						if ( $em && $em !== $submitter ) {
							$objects[] = array(
								'id'    => $rev['id'] ?? null,
								'name'  => $rev['name'] ?? '',
								'email' => $rev['email'] ?? '',
							);
						}
					}
				}
			}
			if ( empty( $objects ) ) {
				$req_count = isset( $req[ $stage_name ]['requiredCount'] ) ? (int) $req[ $stage_name ]['requiredCount'] : 1;
				$count     = max( 1, min( 20, $req_count ) );
				$ids       = self::select_from_pool( $config, $type, $count, array(), true );
				foreach ( $ids as $id ) {
					if ( isset( $by_id[ $id ] ) ) {
						$rev = $by_id[ $id ];
						$em = strtolower( trim( (string) ( $rev['email'] ?? '' ) ) );
						if ( $em && $em !== $submitter ) {
							$objects[] = array(
								'id'    => $rev['id'] ?? null,
								'name'  => $rev['name'] ?? '',
								'email' => $rev['email'] ?? '',
							);
						}
					}
				}
			}
			$review_stages[] = array(
				'stageName'           => $stage_name,
				'stageIndex'          => $i + 1,
				'reviewers'           => $objects,
				'decisions'           => array(),
				'feedback'            => array(),
				'revisionSubmittedAt' => null,
			);
			foreach ( $objects as $r ) {
				$em = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
				if ( $em && ! self::array_find_by_email( $assigned_reviewers, $em ) ) {
					$assigned_reviewers[] = array(
						'id'    => $r['id'] ?? null,
						'name'  => $r['name'] ?? '',
						'email' => $r['email'] ?? '',
					);
				}
			}
		}
		$submission['reviewStages']       = $review_stages;
		$submission['assignedReviewers']  = $assigned_reviewers;
		return $submission;
	}

	public static function is_stage_approved( $stage, $submission = null, $stage_idx = null ) {
		if ( empty( $stage['reviewers'] ) || ! is_array( $stage['reviewers'] ) ) {
			return false;
		}
		// Use the workflow engine when a submission + stage index are provided and a workflow config exists.
		if ( $submission !== null && $stage_idx !== null ) {
			$_wf = Workflow_Engine::get_workflow_for_submission( $submission );
			if ( $_wf !== null ) {
				return Workflow_Engine::is_stage_approved( $stage, $_wf, $stage_idx );
			}
		}
		// Legacy path: unanimous approval only.
		$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
		foreach ( $stage['reviewers'] as $r ) {
			$em = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
			if ( ! $em ) {
				continue;
			}
			$decision = strtolower( trim( (string) ( $decisions[ $em ] ?? '' ) ) );
			if ( $decision !== 'approved' ) {
				return false;
			}
		}
		return true;
	}

	public static function derive_submission_status( $submission ) {
		$status = $submission['status'] ?? 'Submitted';
		$stages = isset( $submission['reviewStages'] ) && is_array( $submission['reviewStages'] ) ? $submission['reviewStages'] : array();

		// Preserved terminal statuses — do not override.
		$preserved = array( 'Draft', 'Withdrawn', 'Cancelled', 'Full Paper Invited', 'Appeal Pending', 'Appeal Under Review' );
		if ( in_array( $status, $preserved, true ) ) {
			return $status;
		}

		// ── Workflow Engine path (Phase 2+) ─────────────────────────────────────
		// If the submission type has a config-defined workflow, delegate entirely.
		$_wf = Workflow_Engine::get_workflow_for_submission( $submission );
		if ( $_wf !== null ) {
			$_engine_status = Workflow_Engine::derive_status( $submission, $_wf );
			if ( $_engine_status !== null ) {
				return $_engine_status;
			}
		}
		// ── End Workflow Engine path ─────────────────────────────────────────────

		$active_stages = array_filter( $stages, function ( $s ) { return ! ( $s['skipped'] ?? false ); } );
		if ( empty( $active_stages ) ) {
			return $status;
		}

		// For gated review types, only stage 0 (gatekeeper stage) determines terminal status.
		// Higher stages are internal deliberation; their decisions do not flow to the submitter.
		$_sub_type = $submission['submissionType'] ?? $submission['type'] ?? '';
		$_is_gated = false;
		if ( $_sub_type ) {
			$_cfg = self::read_config();
			foreach ( $_cfg['submissionTypes'] ?? array() as $_st ) {
				if ( ( $_st['id'] ?? '' ) === $_sub_type && ! empty( $_st['gatedReview'] ) ) {
					$_is_gated = true; break;
				}
			}
		}
		$_stages_to_scan = $_is_gated
			? array_slice( array_values( $active_stages ), 0, 1 )  // Only gatekeeper stage
			: array_values( $active_stages );

		$has_needs_revision = false;
		$has_rejected = false;

		// Scan stages for terminal decisions.
		foreach ( $_stages_to_scan as $stage ) {
			$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
			foreach ( $decisions as $d ) {
				$d = strtolower( trim( (string) $d ) );
				if ( $d === 'rejected' ) {
					$has_rejected = true;
					break 2;
				}
				if ( $d === 'needs revision' ) {
					$has_needs_revision = true;
				}
			}
		}
		if ( $has_rejected ) {
			return 'Rejected';
		}
		if ( $has_needs_revision ) {
			return 'Revision Required';
		}

		// Partial progress: find the first active stage that has reviewers but isn't fully approved.
		// For gated reviews, only the gatekeeper stage (index 0) drives the submission status label.
		// Stages with NO reviewers are simply unassigned — they don't count as "In Progress".
		$_progress_stages = $_is_gated ? array_slice( array_values( $active_stages ), 0, 1 ) : array_values( $active_stages );
		foreach ( $_progress_stages as $stage ) {
			if ( ! empty( $stage['reviewers'] ) && ! self::is_stage_approved( $stage ) ) {
				$stage_name = trim( (string) ( $stage['stageName'] ?? '' ) );
				return $stage_name ? $stage_name . ': In Progress' : 'Under Review';
			}
		}

		// Reach here when every active stage either has no reviewers or is fully approved → final label.
		// For gated reviews, stage 0 being approved does NOT mean the submission is done;
		// the gatekeeper still needs to review higher-stage feedback and release a decision.
		if ( $_is_gated ) {
			$_gk_stage      = array_values( $active_stages )[0] ?? array();
			$_gk_stage_name = trim( (string) ( $_gk_stage['stageName'] ?? 'Primary Review' ) );
			$_higher_stages = array_slice( array_values( $active_stages ), 1 );

			// If a final gated release exists, use its decision for a terminal status.
			// Exception: if the student submitted a revision AFTER the release (new round),
			// the old release is superseded — skip it and derive from the new round's state.
			$_releases = isset( $submission['gatedReleases'] ) && is_array( $submission['gatedReleases'] )
				? $submission['gatedReleases'] : array();
			$_release_superseded = false;
			if ( ! empty( $_releases ) ) {
				$_last_rel      = end( $_releases );
				$_current_round = (int) ( $submission['currentRound'] ?? 0 );
				// If the release carries a round stamp, compare integers — fast and unambiguous.
				// Fall back to timestamp comparison only for older releases that predate Phase 1.
				if ( isset( $_last_rel['round'] ) ) {
					$_release_superseded = (int) $_last_rel['round'] < $_current_round;
				} else {
					// Legacy path: compare releasedAt vs stage-0 revisionSubmittedAt timestamps.
					$_rel_ts    = isset( $_last_rel['releasedAt'] ) ? strtotime( (string) $_last_rel['releasedAt'] ) : 0;
					$_s0_rev_at = $_gk_stage['revisionSubmittedAt'] ?? '';
					$_s0_rev_ts = $_s0_rev_at ? strtotime( (string) $_s0_rev_at ) : 0;
					if ( $_s0_rev_ts > 0 && $_rel_ts > 0 && $_s0_rev_ts > $_rel_ts ) {
						$_release_superseded = true;
					}
				}
			}
			if ( ! empty( $_releases ) && ! $_release_superseded ) {
				$_last_rel = end( $_releases );
				$_rd       = (string) ( $_last_rel['decision'] ?? '' );
				if ( $_rd === 'Rejected' )               return 'Rejected';
				if ( $_rd === 'Revision Required' )      return 'Revision Required';
				if ( $_rd === 'Conditionally Approved' ) return 'Conditionally Approved';
				// Approved → fall through to type-based final label
			} else {
				// No release yet — check higher-stage progress.
				$_any_gk_notified    = false;
				$_notified_hs_name   = '';
				$_any_hs_in_progress = false;
				$_first_hs_name      = '';
				foreach ( $_higher_stages as $_hs ) {
					$_hs_reviewers  = $_hs['reviewers'] ?? array();
					$_hs_decisions  = is_array( $_hs['decisions'] ?? null ) ? $_hs['decisions'] : array();
					// "All decided" = every reviewer has submitted any non-empty decision,
					// regardless of whether gatekeeperNotifiedAt was written (handles legacy data).
					$_hs_all_decided = false;
					if ( ! empty( $_hs_reviewers ) ) {
						$_hs_all_decided = true;
						foreach ( $_hs_reviewers as $_hr ) {
							$_hek = strtolower( trim( (string) ( $_hr['email'] ?? '' ) ) );
							if ( empty( $_hs_decisions[ $_hek ] ) ) { $_hs_all_decided = false; break; }
						}
					}
					if ( ! empty( $_hs['gatekeeperNotifiedAt'] ) || $_hs_all_decided ) {
						$_any_gk_notified  = true;
						$_notified_hs_name = trim( (string) ( $_hs['stageName'] ?? '' ) );
						break;
					}
					if ( ! empty( $_hs_reviewers ) ) {
						$_any_hs_in_progress = true;
						if ( ! $_first_hs_name ) $_first_hs_name = trim( (string) ( $_hs['stageName'] ?? '' ) );
					}
				}
				if ( $_any_gk_notified ) {
					$_suffix = $_notified_hs_name ? ': Reviewing ' . $_notified_hs_name . ' Feedback' : ': Reviewing Higher Stage Feedback';
					return $_gk_stage_name . $_suffix;
				}
				if ( $_any_hs_in_progress ) {
					$_label = $_first_hs_name ?: 'Higher Stage';
					return $_label . ': Under Review';
				}
				if ( ! empty( $_gk_stage['reviewers'] ) && self::is_stage_approved( $_gk_stage ) ) {
					return $_gk_stage_name . ': Awaiting Higher Stage Review';
				}
				// Stage 0 has no reviewers yet or is in early state — preserve current status.
				return $status;
			}
		}

		$type = strtolower( $submission['type'] ?? '' );
		switch ( $type ) {
			case 'conference':
				return 'Confirmed for Presentation';
			case 'publication':
				return 'Published';
			case 'grant':
				return 'Approved';
			default:
				return 'Approved';
		}
	}

	public static function get_dashboard_data( $params = array() ) {
		$params = is_array( $params ) ? $params : array();
		$current_user_email = isset( $params['userEmail'] ) ? strtolower( trim( (string) $params['userEmail'] ) ) : '';
		$current_user_role = isset( $params['role'] ) ? $params['role'] : '';
		$current_user_is_admin = isset( $params['isAdmin'] ) ? (bool) $params['isAdmin'] : false;
		$current_user_is_reviewer = isset( $params['isReviewer'] ) ? (bool) $params['isReviewer'] : false;
		$current_user_is_submitter = isset( $params['isSubmitter'] ) ? (bool) $params['isSubmitter'] : false;

		$data = self::read_submissions();
		$submissions = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();

		$overview = array(
			'totalSubmissions' => 0,
			'statusCounts' => array(),
			'typeCounts' => array(),
			'progressSummary' => array(),
		);

		$mySubmissions = array();
		$assignedSubmissions = array();
		$pendingReview = array();

		foreach ( $submissions as $submission ) {
			$overview['totalSubmissions']++;
			$status = $submission['status'] ?? 'Unknown';
			$type = ( ! empty( $submission['submissionType'] ) ? $submission['submissionType'] : ( $submission['type'] ?? 'unknown' ) );

			if ( ! isset( $overview['statusCounts'][ $status ] ) ) {
				$overview['statusCounts'][ $status ] = 0;
			}
			$overview['statusCounts'][ $status ]++;

			if ( ! isset( $overview['typeCounts'][ $type ] ) ) {
				$overview['typeCounts'][ $type ] = 0;
			}
			$overview['typeCounts'][ $type ]++;

			$stages = isset( $submission['reviewStages'] ) && is_array( $submission['reviewStages'] ) ? $submission['reviewStages'] : array();
			$total_stages = count( $stages );
			$approved = 0;
			foreach ( $stages as $stage ) {
				if ( self::is_stage_approved( $stage ) ) {
					$approved++;
				}
			}
			$progress_percent = $total_stages > 0 ? (int) round( ( $approved / $total_stages ) * 100 ) : ( $status === 'Draft' ? 0 : 10 );

			$overview['progressSummary'][] = array(
				'id' => $submission['id'] ?? null,
				'title' => $submission['title'] ?? '',
				'status' => $status,
				'type' => $type,
				'progress' => $progress_percent,
			);

			$submitter_email = strtolower( trim( (string) ( $submission['submitterEmail'] ?? '' ) ) );
			if ( $current_user_email && $submitter_email === $current_user_email ) {
				$mySubmissions[] = $submission;
			}

			$assigned_reviewers = isset( $submission['assignedReviewers'] ) && is_array( $submission['assignedReviewers'] ) ? $submission['assignedReviewers'] : array();
			foreach ( $assigned_reviewers as $reviewer ) {
				$rev_email = strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) );
				if ( $current_user_email && $rev_email === $current_user_email ) {
					$assignedSubmissions[] = $submission;
					if ( in_array( $status, array( 'Submitted', 'Under Review', 'Submitted - Awaiting Review', 'Under Initial Review', 'Administrative Review', 'Revision Required' ), true ) ) {
						$pendingReview[] = $submission;
					}
				}
				if ( $rev_email ) {
					$pendingReview[] = $submission;
				}
			}
		}

		$return = array(
			'overview' => $overview,
			'user' => array(
				'email' => $current_user_email,
				'role' => $current_user_role,
				'isAdmin' => $current_user_is_admin,
				'isReviewer' => $current_user_is_reviewer,
				'isSubmitter' => $current_user_is_submitter,
				'mySubmissionsCount' => count( array_values( $mySubmissions ) ),
				'assignedSubmissionsCount' => count( array_values( $assignedSubmissions ) ),
				'pendingReviewCount' => count( array_values( $pendingReview ) ),
			),
		);

		if ( $current_user_is_admin ) {
			$return['overall'] = $overview;
		}

		return $return;
	}

	public static function get_workflow_metrics( $params = array() ) {
		$data = self::read_submissions();
		$all  = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();
		$submissions = self::filter_submissions_for_analytics( $all, $params );

		$metrics = array(
			'totalSubmissions' => count( $submissions ),
			'totalByType' => array(),
			'totalByStatus' => array(),
			'averageStages' => 0,
			'meanReviewerLoad' => 0,
		);

		$stageCounts = 0;
		$reviewerCounts = 0;

		foreach ( $submissions as $sub ) {
			$type = ( ! empty( $sub['submissionType'] ) ? $sub['submissionType'] : ( $sub['type'] ?? 'unknown' ) );
			$status = $sub['status'] ?? 'unknown';
			$stages = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
			$reviewers = isset( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ? $sub['assignedReviewers'] : array();

			$metrics['totalByType'][ $type ] = ( isset( $metrics['totalByType'][ $type ] ) ? $metrics['totalByType'][ $type ] : 0 ) + 1;
			$metrics['totalByStatus'][ $status ] = ( isset( $metrics['totalByStatus'][ $status ] ) ? $metrics['totalByStatus'][ $status ] : 0 ) + 1;
			$stageCounts += count( $stages );
			$reviewerCounts += count( $reviewers );
		}

		if ( $metrics['totalSubmissions'] > 0 ) {
			$metrics['averageStages'] = round( $stageCounts / $metrics['totalSubmissions'], 2 );
			$metrics['meanReviewerLoad'] = round( $reviewerCounts / $metrics['totalSubmissions'], 2 );
		}

		return $metrics;
	}

	public static function get_performance_metrics( $params = array() ) {
		$data = self::read_submissions();
		$all  = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();
		$submissions = self::filter_submissions_for_analytics( $all, $params );

		$metrics = array(
			'averageTimeToDecisionDays' => null,
			'finalizedCount' => 0,
			'inProgressCount' => 0,
			'withdrawnCancelledCount' => 0,
			'pendingCount' => 0,
			'lateReviewAlerts' => 0,
		);

		$timeDiffs = array();
		$now = time();

		foreach ( $submissions as $sub ) {
			$status = $sub['status'] ?? '';
			$createdAt = isset( $sub['createdAt'] ) ? strtotime( $sub['createdAt'] ) : null;
			$finalized_statuses = array( 'Confirmed for Presentation', 'Published', 'Approved for Submission', 'Approved', 'Accepted' );
			$in_progress_statuses = array( 'Submitted', 'Under Review', 'Under Initial Review', 'Administrative Review', 'Revision Required', 'Revision Submitted' );
			$is_finalized   = in_array( $status, $finalized_statuses, true );
			$is_withdrawn_cancelled = in_array( $status, array( 'Withdrawn', 'Cancelled' ), true );
			$is_in_progress = ! $is_finalized && ! $is_withdrawn_cancelled && ( in_array( $status, $in_progress_statuses, true ) || strpos( $status, ': In Progress' ) !== false );
			if ( $is_finalized ) {
				$metrics['finalizedCount']++;
				if ( $createdAt ) {
					$timeDiffs[] = ( $now - $createdAt ) / DAY_IN_SECONDS;
				}
			} elseif ( $is_withdrawn_cancelled ) {
				$metrics['withdrawnCancelledCount']++;
			} elseif ( $is_in_progress ) {
				$metrics['inProgressCount']++;
				if ( $createdAt && ( $now - $createdAt ) > ( 14 * DAY_IN_SECONDS ) ) {
					$metrics['lateReviewAlerts']++;
				}
			} else {
				$metrics['pendingCount']++;
			}
		}

		if ( ! empty( $timeDiffs ) ) {
			$metrics['averageTimeToDecisionDays'] = round( array_sum( $timeDiffs ) / count( $timeDiffs ), 2 );
		}

		return $metrics;
	}

	public static function get_reviewer_workload( $reviewer_email ) {
		$reviewer_email = strtolower( trim( (string) $reviewer_email ) );
		$data = self::read_submissions();
		$submissions = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();

		$workload = array(
			'reviewerEmail' => $reviewer_email,
			'totalAssigned' => 0,
			'pending' => 0,
			'approved' => 0,
			'rejected' => 0,
			'revisionRequired' => 0,
			'activeSubmissions' => array(),
		);

		foreach ( $submissions as $sub ) {
			$assignedReviewer = false;
			foreach ( $sub['assignedReviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $reviewer_email ) {
					$assignedReviewer = true;
					break;
				}
			}
			if ( ! $assignedReviewer ) {
				continue;
			}

			$workload['totalAssigned']++;
			$decision = 'Pending';
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
				if ( isset( $decisions[ $reviewer_email ] ) ) {
					$decision = strtolower( trim( (string) $decisions[ $reviewer_email ] ) );
					break;
				}
			}

			if ( $decision === 'approved' ) {
				$workload['approved']++;
			} elseif ( $decision === 'rejected' ) {
				$workload['rejected']++;
			} elseif ( in_array( $decision, array( 'needs revision', 'revision required' ), true ) ) {
				$workload['revisionRequired']++;
			} else {
				$workload['pending']++;
			}

			$workload['activeSubmissions'][] = array(
				'id' => $sub['id'] ?? '',
				'title' => $sub['title'] ?? '',
				'status' => $sub['status'] ?? '',
				'decision' => $decision,
			);
		}

		return $workload;
	}

	/**
	 * Filter a submissions array for analytics based on role params.
	 * - $params empty          → all submissions (coordinator / admin)
	 * - $params['submitterEmail'] → only that submitter's submissions (student)
	 * - $params['reviewerEmail']  → only submissions assigned to that reviewer
	 */
	private static function filter_submissions_for_analytics( array $subs, array $params ) {
		if ( ! empty( $params['submitterEmail'] ) ) {
			$fe = strtolower( trim( (string) $params['submitterEmail'] ) );
			return array_values( array_filter( $subs, function ( $s ) use ( $fe ) {
				return strtolower( trim( (string) ( $s['submitterEmail'] ?? '' ) ) ) === $fe;
			} ) );
		}
		if ( ! empty( $params['reviewerEmail'] ) ) {
			$re = strtolower( trim( (string) $params['reviewerEmail'] ) );
			return array_values( array_filter( $subs, function ( $s ) use ( $re ) {
				foreach ( $s['assignedReviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $re ) return true;
				}
				return false;
			} ) );
		}
		// Coordinator scoped to specific submission types (from user meta via DB)
		if ( ! empty( $params['submissionTypes'] ) && is_array( $params['submissionTypes'] ) ) {
			$types = array_map( 'strtolower', array_map( 'trim', $params['submissionTypes'] ) );
			return array_values( array_filter( $subs, function ( $s ) use ( $types ) {
				return in_array( strtolower( trim( (string) ( $s['type'] ?? '' ) ) ), $types, true );
			} ) );
		}
		return $subs; // no filter = all (admin path)
	}

	public static function get_review_criteria_templates() {
		$config = self::read_config();
		return isset( $config['reviewCriteriaTemplates'] ) && is_array( $config['reviewCriteriaTemplates'] ) ? $config['reviewCriteriaTemplates'] : array(
			array(
				'name' => 'Standard Evaluation',
				'criteria' => array(
					array( 'label' => 'Originality', 'weight' => 25 ),
					array( 'label' => 'Methodology', 'weight' => 25 ),
					array( 'label' => 'Impact', 'weight' => 25 ),
					array( 'label' => 'Clarity', 'weight' => 25 ),
				),
			),
		);
	}

	public static function set_review_criteria_templates( $templates ) {
		$config = self::read_config();
		$config['reviewCriteriaTemplates'] = is_array( $templates ) ? $templates : array();
		self::write_config( $config );
		return $config['reviewCriteriaTemplates'];
	}

	public static function get_conflict_of_interest_records() {
		$config = self::read_config();
		return isset( $config['conflictOfInterest'] ) && is_array( $config['conflictOfInterest'] ) ? $config['conflictOfInterest'] : array();
	}

	public static function declare_conflict_of_interest( $reviewer_email, $submission_id, $reason ) {
		$reviewer_email = strtolower( trim( (string) $reviewer_email ) );
		$submission_id = trim( (string) $submission_id );
		$reason = trim( (string) $reason );
		$config = self::read_config();
		if ( ! isset( $config['conflictOfInterest'] ) || ! is_array( $config['conflictOfInterest'] ) ) {
			$config['conflictOfInterest'] = array();
		}
		if ( ! isset( $config['conflictOfInterest'][ $submission_id ] ) ) {
			$config['conflictOfInterest'][ $submission_id ] = array();
		}
		$config['conflictOfInterest'][ $submission_id ][ $reviewer_email ] = array(
			'reviewerEmail' => $reviewer_email,
			'submissionId' => $submission_id,
			'reason' => $reason,
			'timestamp' => gmdate( 'c' ),
		);
		self::write_config( $config );
		return $config['conflictOfInterest'][ $submission_id ][ $reviewer_email ];
	}

	public static function get_reviewer_metrics( $reviewer_email ) {
		$reviewer_email = strtolower( trim( (string) $reviewer_email ) );
		$data = self::read_submissions();
		$submissions = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();

		$metrics = array(
			'reviewerEmail' => $reviewer_email,
			'totalAssigned' => 0,
			'pending' => 0,
			'approved' => 0,
			'rejected' => 0,
			'needsRevision' => 0,
			'overdue' => 0,
			'reviewHistory' => array(),
		);

		$now = time();
		foreach ( $submissions as $sub ) {
			$assigned = false;
			$status = $sub['status'] ?? '';
			$deadline = null;
			foreach ( $sub['assignedReviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $reviewer_email ) {
					$assigned = true;
					$deadline = isset( $r['deadline'] ) ? strtotime( $r['deadline'] ) : null;
					break;
				}
			}
			if ( ! $assigned ) {
				continue;
			}

			$metrics['totalAssigned']++;
			$decision = 'Pending';
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
				if ( isset( $decisions[ $reviewer_email ] ) ) {
					$decision = $decisions[ $reviewer_email ];
					break;
				}
			}

			$metrics['reviewHistory'][] = array(
				'id' => $sub['id'] ?? '',
				'title' => $sub['title'] ?? '',
				'status' => $status,
				'decision' => $decision,
				'deadline' => $deadline ? date( 'Y-m-d', $deadline ) : null,
			);

			switch ( strtolower( trim( (string) $decision ) ) ) {
				case 'approved':
					$metrics['approved']++;
					break;
				case 'rejected':
					$metrics['rejected']++;
					break;
				case 'needs revision':
				case 'revision required':
					$metrics['needsRevision']++;
					break;
				default:
					$metrics['pending']++;
					break;
			}

			if ( $deadline && $now > $deadline && ! in_array( strtolower( $decision ), array( 'approved', 'rejected' ), true ) ) {
				$metrics['overdue']++;
			}
		}

		return $metrics;
	}

	public static function generate_report_csv( $records, $columns = array() ) {
		$fp = fopen( 'php://temp', 'r+' );
		if ( ! $fp ) {
			return '';
		}

		if ( empty( $columns ) && ! empty( $records ) ) {
			$columns = array_keys( (array) reset( $records ) );
		}

		fputcsv( $fp, $columns );
		foreach ( $records as $record ) {
			$row = array();
			foreach ( $columns as $col ) {
				$row[] = isset( $record[ $col ] ) ? $record[ $col ] : '';
			}
			fputcsv( $fp, $row );
		}

		rewind( $fp );
		$output = stream_get_contents( $fp );
		fclose( $fp );
		return $output;
	}

	/**
	 * Return the index of the first stage that is neither fully approved nor skipped.
	 * Returns the last stage index if all are done.
	 */
	public static function get_active_stage_index( $submission ) {
		$stages = isset( $submission['reviewStages'] ) && is_array( $submission['reviewStages'] ) ? $submission['reviewStages'] : array();
		foreach ( $stages as $i => $stage ) {
			if ( ! ( $stage['skipped'] ?? false ) && ! self::is_stage_approved( $stage ) ) {
				return $i;
			}
		}
		return max( 0, count( $stages ) - 1 );
	}

	/**
	 * Calculate the cumulative due-date for a stage.
	 *
	 * Config key: stageDueDays[type][stageName] = integer days (new per-stage format).
	 * Falls back to stageDueDays[type] as a flat integer (legacy) or 7 days (default).
	 *
	 * The deadline is CUMULATIVE: stage N deadline = createdAt + sum(days for stages 0..N).
	 */
	public static function calculate_stage_deadline( $submission, $stage_index ) {
		$created_ts = isset( $submission['createdAt'] ) ? strtotime( $submission['createdAt'] ) : time();
		// Prefer submissionType (the true config-defined type) over type (legacy remapped field).
		$type       = $submission['submissionType'] ?? $submission['type'] ?? 'conference';
		$config     = self::read_config();

		// Per-stage map: stageDueDays[type] is an array keyed by stageName.
		$stage_map  = ( isset( $config['stageDueDays'][ $type ] ) && is_array( $config['stageDueDays'][ $type ] ) )
		              ? (array) $config['stageDueDays'][ $type ]
		              : array();

		// Legacy flat integer fallback.
		$flat_days  = ( isset( $config['stageDueDays'][ $type ] ) && ! is_array( $config['stageDueDays'][ $type ] ) )
		              ? max( 1, (int) $config['stageDueDays'][ $type ] )
		              : 7;

		// Resolve stage names from reviewStages on the submission, or fall back to config type definition.
		$stage_names = array();
		if ( ! empty( $submission['reviewStages'] ) && is_array( $submission['reviewStages'] ) ) {
			foreach ( $submission['reviewStages'] as $rs ) {
				$stage_names[] = $rs['stageName'] ?? '';
			}
		}
		if ( empty( $stage_names ) ) {
			foreach ( ( $config['submissionTypes'] ?? array() ) as $st ) {
				if ( ( $st['id'] ?? '' ) === $type ) {
					$stage_names = $st['stages'] ?? array();
					break;
				}
			}
		}

		// Sum days for stages 0 … stage_index (cumulative).
		$total_days = 0;
		for ( $i = 0; $i <= $stage_index; $i++ ) {
			$stage_name = $stage_names[ $i ] ?? '';
			if ( $stage_name && isset( $stage_map[ $stage_name ] ) ) {
				$total_days += max( 1, (int) $stage_map[ $stage_name ] );
			} else {
				$total_days += $flat_days;
			}
		}

		// Weekend / holiday skipping.
		$opts         = $config['deadlineOptions'] ?? array();
		$skip_wkends  = ! empty( $opts['skipWeekends'] );
		$holidays     = isset( $opts['publicHolidays'] ) && is_array( $opts['publicHolidays'] )
		                ? array_filter( $opts['publicHolidays'] ) : array();

		$deadline_ts  = $created_ts;
		$days_added   = 0;
		while ( $days_added < $total_days ) {
			$deadline_ts += DAY_IN_SECONDS;
			$dow = (int) gmdate( 'N', $deadline_ts ); // 1=Mon … 7=Sun
			if ( $skip_wkends && ( $dow === 6 || $dow === 7 ) ) {
				continue; // skip Saturday / Sunday
			}
			$date_str = gmdate( 'Y-m-d', $deadline_ts );
			if ( $holidays && in_array( $date_str, $holidays, true ) ) {
				continue; // skip public holiday
			}
			$days_added++;
		}

		// Advance past any trailing weekend / holiday days so the deadline itself is a working day.
		if ( $skip_wkends || $holidays ) {
			$dow = (int) gmdate( 'N', $deadline_ts );
			while ( ( $skip_wkends && ( $dow === 6 || $dow === 7 ) )
			         || ( $holidays && in_array( gmdate( 'Y-m-d', $deadline_ts ), $holidays, true ) ) ) {
				$deadline_ts += DAY_IN_SECONDS;
				$dow = (int) gmdate( 'N', $deadline_ts );
			}
		}

		return gmdate( 'c', $deadline_ts );
	}

	/**
	 * Return the effective deadline for a stage, honouring any approved extension.
	 * An approved extension stores `extensionDeadline` (ISO date) on the stage object.
	 */
	public static function get_effective_deadline( $submission, $stage_index ) {
		$stage = $submission['reviewStages'][ $stage_index ] ?? null;
		if ( $stage && ! empty( $stage['extensionDeadline'] ) && ! empty( $stage['extensionApproved'] ) ) {
			return $stage['extensionDeadline'];
		}
		return self::calculate_stage_deadline( $submission, $stage_index );
	}

	/**
	 * Return the grace period in seconds from config (default 2 days).
	 */
	public static function get_grace_period_seconds() {
		$config = self::read_config();
		$days   = (int) ( $config['deadlineOptions']['gracePeriodDays'] ?? 2 );
		return max( 0, $days ) * DAY_IN_SECONDS;
	}


	/**
	 * Return an array of all submissions / stages that are overdue.
	 */
	public static function get_overdue_submissions() {
		$data    = self::read_submissions();
		$subs    = $data['submissions'] ?? array();
		$now     = time();
		$grace   = self::get_grace_period_seconds();
		$overdue = array();
		foreach ( $subs as $sub ) {
			$stages = $sub['reviewStages'] ?? array();
			foreach ( $stages as $i => $stage ) {
				if ( self::is_stage_approved( $stage ) || ( $stage['skipped'] ?? false ) ) {
					continue;
				}
				$deadline_ts = strtotime( self::get_effective_deadline( $sub, $i ) );
				if ( $deadline_ts && $now > ( $deadline_ts + $grace ) ) {
					$overdue[] = array(
						'submissionId' => $sub['id'] ?? '',
						'title'        => $sub['title'] ?? '',
						'stageName'    => $stage['stageName'] ?? '',
						'stageIndex'   => $i,
						'deadline'     => gmdate( 'c', $deadline_ts ),
						'reviewers'    => array_map( function ( $r ) { return $r['email'] ?? ''; }, $stage['reviewers'] ?? array() ),
					);
				}
			}
		}
		return $overdue;
	}

	private static function array_find_by_email( $arr, $email ) {
		$email = strtolower( trim( $email ) );
		foreach ( $arr as $a ) {
			if ( strtolower( trim( (string) ( $a['email'] ?? '' ) ) ) === $email ) {
				return true;
			}
		}
		return false;
	}

	// ── Database setup & migration ─────────────────────────────────────────────

	/**
	 * Scan all in-progress submissions and set / clear the `reviewerRemoved` flag
	 * based on whether their assigned reviewers still hold the rrp_reviewer role
	 * in WordPress.
	 *
	 * This catches reviewers who were removed before the flag mechanism existed,
	 * as well as any edge-case where the delete/role-change hook was bypassed.
	 *
	 * Rate-limited to once per 5 minutes via a transient so it does not add
	 * overhead to every single coordinator page-load.
	 *
	 * @param bool $force Skip the rate-limit and always scan.
	 */
	public static function flag_orphaned_reviewer_assignments( bool $force = false ): void {
		if ( ! $force && get_transient( 'rrp_reviewer_scan' ) ) {
			return;
		}
		set_transient( 'rrp_reviewer_scan', '1', 5 * MINUTE_IN_SECONDS );

		global $wpdb;
		$table = $wpdb->prefix . 'rrp_submissions';

		// 'fields' => 'user_email' (string) returns a plain array of email strings.
		$active_emails = array();
		$raw_emails    = get_users( array(
			'role__in' => array( 'rrp_reviewer' ),
			'fields'   => 'user_email',
		) );
		foreach ( (array) $raw_emails as $e ) {
			$active_emails[] = strtolower( trim( (string) $e ) );
		}

		// Terminal statuses — no reassignment needed.
		$terminal = array(
			'Withdrawn', 'Cancelled', 'Approved', 'Rejected', 'Published',
			'Confirmed for Presentation', 'Approved for Submission',
		);

		// Read directly from DB; update each changed row individually to avoid
		// the risk of the full write_submissions() DELETE-NOT-IN pass.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT `submission_id`, `status`, `data` FROM `{$table}`",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$sub = json_decode( (string) $row['data'], true );
			if ( ! is_array( $sub ) ) {
				continue;
			}

			$is_terminal  = in_array( $row['status'], $terminal, true );
			$current_flag = ! empty( $sub['reviewerRemoved'] );
			$desired_flag = false;

			if ( ! $is_terminal ) {
				// Check top-level assignedReviewers.
				foreach ( $sub['assignedReviewers'] ?? array() as $r ) {
					$email = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
					if ( $email !== '' && ! in_array( $email, $active_emails, true ) ) {
						$desired_flag = true;
						break;
					}
				}
				// Also check per-stage reviewers.
				if ( ! $desired_flag ) {
					foreach ( $sub['reviewStages'] ?? array() as $stage ) {
						foreach ( $stage['reviewers'] ?? array() as $r ) {
							$email = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
							if ( $email !== '' && ! in_array( $email, $active_emails, true ) ) {
								$desired_flag = true;
								break 2;
							}
						}
					}
				}
			}

			if ( $desired_flag === $current_flag ) {
				continue; // Nothing to change for this row.
			}

			$sub['reviewerRemoved'] = $desired_flag;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table,
				array( 'data' => wp_json_encode( $sub, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
				array( 'submission_id' => (string) $row['submission_id'] ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}

	/**
	 * Create the rrp_submissions table if it does not already exist.
	 * Uses dbDelta() so it is safe to call on every plugin load.
	 *
	 * Table design:
	 *   - submission_id  VARCHAR(50)  — canonical ID (e.g. PROJ-2026-003)
	 *   - status         VARCHAR(100) — denormalised for fast coordinator queries
	 *   - submitter_email VARCHAR(191) — denormalised for fast reviewer filtering
	 *   - submission_type VARCHAR(100) — denormalised for analytics
	 *   - created_at     DATETIME     — denormalised for date-range ordering
	 *   - data           LONGTEXT     — full submission JSON (single source of truth)
	 */
	public static function create_tables(): void {
		global $wpdb;
		$table           = $wpdb->prefix . 'rrp_submissions';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  submission_id VARCHAR(50) NOT NULL,
  status VARCHAR(100) NOT NULL DEFAULT '',
  submitter_email VARCHAR(191) NOT NULL DEFAULT '',
  submission_type VARCHAR(100) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  data LONGTEXT NOT NULL,
  PRIMARY KEY  (submission_id),
  KEY idx_status (status),
  KEY idx_submitter_email (submitter_email),
  KEY idx_submission_type (submission_type),
  KEY idx_created_at (created_at)
) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run the one-time migration from JSON files to the database.
	 * Safe to call on every plugin load — the rrp_db_version option guards
	 * against repeated execution.
	 */
	public static function maybe_migrate(): void {
		if ( get_option( 'rrp_db_version' ) === '1.0' ) {
			return; // Already migrated.
		}
		self::create_tables();
		self::_migrate_json_to_db();
		update_option( 'rrp_db_version', '1.0', false );
	}

	/**
	 * Copy all data from the three legacy JSON files into the database.
	 * Each JSON file is renamed to *.bak-TIMESTAMP after a successful import
	 * so the originals are preserved for manual review.
	 * This method is idempotent: running it again on already-imported data
	 * simply upserts the same rows/options without duplication.
	 */
	private static function _migrate_json_to_db(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'rrp_submissions';
		$ts    = gmdate( 'Ymd-His' );

		// ── submissions.json → rrp_submissions table + rrp_next_ids option ──
		$sub_path = RRP_DATA_DIR . self::SUBMISSIONS_FILE;
		if ( file_exists( $sub_path ) ) {
			$raw = file_get_contents( $sub_path );
			if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
				$raw = substr( $raw, 3 );
			}
			$file_data = json_decode( $raw, true );
			if ( is_array( $file_data ) ) {
				$submissions = isset( $file_data['submissions'] ) && is_array( $file_data['submissions'] ) ? $file_data['submissions'] : array();
				$nextIds     = isset( $file_data['nextIds'] )     && is_array( $file_data['nextIds'] )     ? $file_data['nextIds']     : array();
				foreach ( $submissions as $sub ) {
					$id = $sub['id'] ?? null;
					if ( ! $id || ! is_string( $id ) ) {
						continue;
					}
					$created_raw   = $sub['createdAt'] ?? null;
					$created_ts    = $created_raw ? strtotime( $created_raw ) : false;
					$created_at_db = ( false !== $created_ts ) ? gmdate( 'Y-m-d H:i:s', $created_ts ) : current_time( 'mysql', true );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->replace(
						$table,
						array(
							'submission_id'   => (string) $id,
							'status'          => (string) ( $sub['status'] ?? '' ),
							'submitter_email' => strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) ),
							'submission_type' => (string) ( $sub['submissionType'] ?? $sub['type'] ?? '' ),
							'created_at'      => $created_at_db,
							'data'            => (string) wp_json_encode( $sub, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
						),
						array( '%s', '%s', '%s', '%s', '%s', '%s' )
					);
				}
				update_option( 'rrp_next_ids', $nextIds, false );
				// Rename original as read-only backup; new code reads from DB.
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@rename( $sub_path, $sub_path . '.bak-' . $ts );
			}
		}

		// ── config.json → rrp_config option ──────────────────────────────────
		$cfg_path = RRP_DATA_DIR . self::CONFIG_FILE;
		if ( file_exists( $cfg_path ) ) {
			$raw = file_get_contents( $cfg_path );
			if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
				$raw = substr( $raw, 3 );
			}
			$config = json_decode( $raw, true );
			if ( is_array( $config ) ) {
				update_option( 'rrp_config', $config, false );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@rename( $cfg_path, $cfg_path . '.bak-' . $ts );
			}
		}

		// ── webhooks.json → rrp_webhooks option ──────────────────────────────
		$wh_path = RRP_DATA_DIR . self::WEBHOOKS_FILE;
		if ( file_exists( $wh_path ) ) {
			$raw      = file_get_contents( $wh_path );
			$webhooks = json_decode( $raw, true );
			if ( is_array( $webhooks ) ) {
				update_option( 'rrp_webhooks', $webhooks, false );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@rename( $wh_path, $wh_path . '.bak-' . $ts );
			}
		}
	}
}
