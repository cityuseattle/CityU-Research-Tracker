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
	const REVIEWERS_FILE   = 'reviewers.json';
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
		array( 'id' => 'r1', 'name' => 'Cris Ewell', 'email' => 'cewell@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'grant' ) ),
		array( 'id' => 'r2', 'name' => 'George Bragg', 'email' => 'gbragg@cityu.edu', 'submissionTypes' => array( 'conference', 'publication' ) ),
		array( 'id' => 'r3', 'name' => 'Greg Surber', 'email' => 'gsurber@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'student-project' ) ),
		array( 'id' => 'r4', 'name' => 'Jemell Garris', 'email' => 'garrisjemell@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'student-project', 'grant' ) ),
		array( 'id' => 'r5', 'name' => 'Jenny Ju', 'email' => 'jju@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'student-project' ) ),
		array( 'id' => 'r6', 'name' => 'Patrick Offor', 'email' => 'poffor@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'grant' ) ),
		array( 'id' => 'r7', 'name' => 'Morgan Zantua', 'email' => 'mzantua@cityu.edu', 'submissionTypes' => array( 'conference', 'publication', 'student-project', 'grant' ) ),
	);

	public static function ensure_data_dir() {
		if ( ! file_exists( RRP_DATA_DIR ) ) {
			wp_mkdir_p( RRP_DATA_DIR );
		}
		if ( ! file_exists( RRP_UPLOADS_DIR ) ) {
			wp_mkdir_p( RRP_UPLOADS_DIR );
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

	public static function read_submissions() {
		self::ensure_data_dir();
		$path = RRP_DATA_DIR . self::SUBMISSIONS_FILE;
		if ( ! file_exists( $path ) ) {
			return array( 'submissions' => array(), 'nextIds' => array() );
		}
		$raw = file_get_contents( $path );
		if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$raw = substr( $raw, 3 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array( 'submissions' => array(), 'nextIds' => array() );
		}
		if ( empty( $data['submissions'] ) || ! is_array( $data['submissions'] ) ) {
			$data['submissions'] = array();
		}
		if ( empty( $data['nextIds'] ) || ! is_array( $data['nextIds'] ) ) {
			$data['nextIds'] = array();
		}
		return $data;
	}

	public static function write_submissions( $data ) {
		self::ensure_data_dir();
		file_put_contents( RRP_DATA_DIR . self::SUBMISSIONS_FILE, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );
	}

	public static function next_id( $type ) {
		$data   = self::read_submissions();
		$year   = (int) gmdate( 'Y' );
		$key    = $type . '-' . $year;
		$num    = ( isset( $data['nextIds'][ $key ] ) ? (int) $data['nextIds'][ $key ] : 0 ) + 1;
		$data['nextIds'][ $key ] = $num;
		$prefix_map = array(
			'conference'      => 'ARS',
			'publication'     => 'PUB',
			'student-project' => 'PROJ',
			'grant'           => 'GRN',
			'dissertation'    => 'DIS',
			'capstone'        => 'CAP',
			'research-paper'  => 'RES',
			'grant-proposal'  => 'GRP',
		);
		if ( isset( $prefix_map[ $type ] ) ) {
			$prefix = $prefix_map[ $type ];
		} else {
			// Derive a 3-letter prefix from the type slug for any future dynamic type.
			$slug   = strtoupper( preg_replace( '/[^a-zA-Z]/', '', $type ) );
			$prefix = substr( $slug, 0, 3 ) ?: 'SUB';
		}
		self::write_submissions( $data );
		return $prefix . '-' . $year . '-' . str_pad( (string) $num, 3, '0', STR_PAD_LEFT );
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

	public static function read_reviewers() {
		$path = RRP_DATA_DIR . self::REVIEWERS_FILE;
		if ( ! file_exists( $path ) ) {
			return self::DEFAULT_REVIEWERS;
		}
		$raw = file_get_contents( $path );
		$list = json_decode( $raw, true );
		return is_array( $list ) ? $list : self::DEFAULT_REVIEWERS;
	}

	public static function write_reviewers( $list ) {
		self::ensure_data_dir();
		file_put_contents( RRP_DATA_DIR . self::REVIEWERS_FILE, wp_json_encode( array_values( $list ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );
	}

	/**
	 * Sync a WordPress reviewer user to reviewers.json (create or update the entry).
	 */
	public static function sync_reviewer_to_json( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;
		$types = (array) ( get_user_meta( $user_id, 'rrp_submission_types', true ) ?: array() );
		$rid   = (string) ( get_user_meta( $user_id, 'rrp_reviewer_id', true ) ?: 'wp_' . $user_id );
		$list  = self::read_reviewers();
		$found = false;
		foreach ( $list as &$r ) {
			if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === strtolower( trim( $user->user_email ) ) ) {
				$r['id']              = $rid;
				$r['name']            = $user->display_name;
				$r['submissionTypes'] = $types;
				$found = true;
				break;
			}
		}
		unset( $r );
		if ( ! $found ) {
			$list[] = array( 'id' => $rid, 'name' => $user->display_name, 'email' => $user->user_email, 'submissionTypes' => $types );
		}
		self::write_reviewers( $list );
	}

	/**
	 * Remove a reviewer entry from reviewers.json by email.
	 */
	public static function remove_reviewer_from_json( $email ) {
		$list    = self::read_reviewers();
		$email_l = strtolower( trim( (string) $email ) );
		$list    = array_values( array_filter( $list, function ( $r ) use ( $email_l ) {
			return strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) !== $email_l;
		} ) );
		self::write_reviewers( $list );
	}

	public static function read_config() {
		$path = RRP_DATA_DIR . self::CONFIG_FILE;
		$defaults = array(
			'stageRequirements'      => array(),
			'reviewerPools'          => array(),
			'poolCohorts'            => array(),
			'activeCohort'           => null,
			'defaultReviewersByStage' => array(),
			'programs'               => array(),
		);
		if ( ! file_exists( $path ) ) {
			return $defaults;
		}
		$raw = file_get_contents( $path );
		if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$raw = substr( $raw, 3 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $defaults;
		}
		// Merge persisted data over defaults so all keys (programs, templates, COI, etc.) are preserved.
		return array_merge( $defaults, $data );
	}

	public static function write_config( $config ) {
		self::ensure_data_dir();
		file_put_contents( RRP_DATA_DIR . self::CONFIG_FILE, wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );
	}

	public static function read_webhooks(): array {
		$path = RRP_DATA_DIR . self::WEBHOOKS_FILE;
		if ( ! file_exists( $path ) ) {
			return array();
		}
		$raw  = file_get_contents( $path );
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	public static function write_webhooks( array $webhooks ): void {
		self::ensure_data_dir();
		file_put_contents( RRP_DATA_DIR . self::WEBHOOKS_FILE, wp_json_encode( $webhooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );
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

	public static function is_stage_approved( $stage ) {
		if ( empty( $stage['reviewers'] ) || ! is_array( $stage['reviewers'] ) ) {
			return false;
		}
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

		$active_stages = array_filter( $stages, function ( $s ) { return ! ( $s['skipped'] ?? false ); } );
		if ( empty( $active_stages ) ) {
			return $status;
		}

		$has_needs_revision = false;
		$has_rejected = false;

		// Scan all active stages for terminal decisions first.
		foreach ( $active_stages as $stage ) {
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
		// Stages with NO reviewers are simply unassigned — they don't count as "In Progress".
		foreach ( array_values( $active_stages ) as $stage ) {
			if ( ! empty( $stage['reviewers'] ) && ! self::is_stage_approved( $stage ) ) {
				$stage_name = trim( (string) ( $stage['stageName'] ?? '' ) );
				return $stage_name ? $stage_name . ': In Progress' : 'Under Review';
			}
		}

		// Reach here when every active stage either has no reviewers or is fully approved → final label.
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
		return $subs; // no filter = all
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
}
