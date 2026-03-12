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
	const CONFIG_FILE     = 'config.json';

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
		$prefix = array(
			'conference'      => 'ARS',
			'publication'     => 'PUB',
			'student-project' => 'PROJ',
			'grant'           => 'GRN',
		);
		$prefix = isset( $prefix[ $type ] ) ? $prefix[ $type ] : 'SUB';
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
					$errors[] = 'Extended abstract is required.';
				}
				if ( empty( trim( (string) ( $body['publicationType'] ?? '' ) ) ) ) {
					$errors[] = 'Publication type is required.';
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
				if ( empty( trim( (string) ( $body['abstract'] ?? '' ) ) ) ) {
					$errors[] = 'Project description is required.';
				}
				if ( empty( trim( (string) ( $body['projectType'] ?? '' ) ) ) ) {
					$errors[] = 'Project type is required.';
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
				if ( empty( trim( (string) ( $body['abstract'] ?? '' ) ) ) ) {
					$errors[] = 'Abstract is required.';
				}
				if ( empty( trim( (string) ( $body['fundingAgency'] ?? '' ) ) ) ) {
					$errors[] = 'Funding agency is required.';
				}
				break;
			default:
				$errors[] = 'Invalid submission type.';
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

	public static function read_config() {
		$path = RRP_DATA_DIR . self::CONFIG_FILE;
		$defaults = array(
			'stageRequirements'     => array(),
			'reviewerPools'         => array(),
			'poolCohorts'           => array(),
			'activeCohort'          => null,
			'defaultReviewersByStage' => array(),
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
		return array(
			'stageRequirements'      => isset( $data['stageRequirements'] ) && is_array( $data['stageRequirements'] ) ? $data['stageRequirements'] : array(),
			'reviewerPools'          => isset( $data['reviewerPools'] ) && is_array( $data['reviewerPools'] ) ? $data['reviewerPools'] : array(),
			'poolCohorts'            => isset( $data['poolCohorts'] ) && is_array( $data['poolCohorts'] ) ? $data['poolCohorts'] : array(),
			'activeCohort'           => isset( $data['activeCohort'] ) ? $data['activeCohort'] : null,
			'defaultReviewersByStage' => isset( $data['defaultReviewersByStage'] ) && is_array( $data['defaultReviewersByStage'] ) ? $data['defaultReviewersByStage'] : array(),
		);
	}

	public static function write_config( $config ) {
		self::ensure_data_dir();
		file_put_contents( RRP_DATA_DIR . self::CONFIG_FILE, wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );
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
			shuffle( $pool_ids );
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
		$type = isset( $submission['type'] ) ? strtolower( $submission['type'] ) : '';
		if ( ! in_array( $type, array( 'conference', 'publication', 'student-project', 'grant' ), true ) ) {
			return $submission;
		}
		$stages = self::WORKFLOW_STAGES[ $type ] ?? array();
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
		$has_needs_revision = false;
		$has_rejected = false;
		$all_approved = true;
		foreach ( $stages as $stage ) {
			$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
			foreach ( $decisions as $d ) {
				$d = strtolower( trim( (string) $d ) );
				if ( $d === 'rejected' ) {
					$has_rejected = true;
					$all_approved = false;
					break 2;
				}
				if ( $d === 'needs revision' ) {
					$has_needs_revision = true;
					$all_approved = false;
				}
				if ( $d !== 'approved' ) {
					$all_approved = false;
				}
			}
		}
		if ( $has_rejected ) {
			return 'Rejected';
		}
		if ( $has_needs_revision ) {
			return 'Revision Required';
		}
		if ( ! empty( $stages ) && $all_approved ) {
			$type = strtolower( $submission['type'] ?? '' );
			switch ( $type ) {
				case 'conference':
					return 'Confirmed for Presentation';
				case 'publication':
					return 'Published';
				case 'student-project':
					return 'Approved for Submission';
				case 'grant':
					return 'Approved';
				default:
					return 'Accepted';
			}
		}
		return $status;
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
			$type = $submission['type'] ?? 'unknown';

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
		$submissions = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();

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
			$type = $sub['type'] ?? 'unknown';
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
		$submissions = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();

		$metrics = array(
			'averageTimeToDecisionDays' => null,
			'finalizedCount' => 0,
			'inProgressCount' => 0,
			'pendingCount' => 0,
			'lateReviewAlerts' => 0,
		);

		$timeDiffs = array();
		$now = time();

		foreach ( $submissions as $sub ) {
			$status = $sub['status'] ?? '';
			$createdAt = isset( $sub['createdAt'] ) ? strtotime( $sub['createdAt'] ) : null;
			if ( $status === 'Confirmed for Presentation' || $status === 'Published' || $status === 'Approved for Submission' || $status === 'Approved' || $status === 'Accepted' ) {
				$metrics['finalizedCount']++;
				if ( $createdAt ) {
					$timeDiffs[] = ( $now - $createdAt ) / DAY_IN_SECONDS;
				}
			} elseif ( in_array( $status, array( 'Submitted', 'Under Review', 'Under Initial Review', 'Administrative Review', 'Revision Required', 'Revision Submitted' ), true ) ) {
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
