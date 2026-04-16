<?php
/**
 * Research Review Portal — Workflow Engine (Phase 2)
 *
 * Provides a config-driven workflow layer that sits alongside the existing
 * hardcoded logic.  Submission types that carry a `workflow` block in their
 * config are processed here; all others fall back to the legacy code paths in
 * Portal_Data and Portal_REST so nothing breaks during the transition.
 *
 * Config shape expected in each submissionType entry (wp_options `rrp_config`):
 *
 *   "workflow": {
 *     "stages": [
 *       {
 *         "name":         "Chair Review",
 *         "role":         "gatekeeper",          // gatekeeper | committee | advisory
 *         "decisionRule": "single",              // unanimous | majority | single
 *         "decisions":    ["Approved","Needs Revision","Rejected"],
 *         "releaseChannel": true,                // this role releases to student
 *         "visibleTo":    ["gatekeeper","admin"] // viewer roles that see full data
 *       },
 *       { "name": "Committee Review", "role": "committee", "decisionRule": "unanimous", ... }
 *     ],
 *     "transitions": [
 *       { "trigger": "stage_all_decided", "stageRole": "committee", "next": "notify_gatekeeper" },
 *       { "trigger": "release_decision",  "decision": "Approved",          "status": "Approved" },
 *       { "trigger": "release_decision",  "decision": "Revision Required", "status": "Revision Required" },
 *       { "trigger": "release_decision",  "decision": "Rejected",          "status": "Rejected" }
 *     ],
 *     "finalStatusByDecision": {
 *       "Approved": "Confirmed for Presentation"
 *     },
 *     "studentVisibility": {
 *       "showStageNames":             true,
 *       "showReviewerNames":          true,
 *       "decisionVisibleAfterRelease": true
 *     }
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Workflow_Engine {

	// -------------------------------------------------------------------------
	// Config helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the workflow definition for the given submission type slug, or null
	 * if the type has no engine-managed workflow.
	 */
	public static function get_workflow( string $type ): ?array {
		$config = Portal_Data::read_config();
		foreach ( $config['submissionTypes'] ?? array() as $st ) {
			if ( ( $st['id'] ?? '' ) === $type ) {
				$wf = $st['workflow'] ?? null;
				return is_array( $wf ) && ! empty( $wf['stages'] ) ? $wf : null;
			}
		}
		return null;
	}

	/**
	 * Return the workflow for a submission (reads type from submission array).
	 */
	public static function get_workflow_for_submission( array $submission ): ?array {
		$type = $submission['submissionType'] ?? $submission['type'] ?? '';
		return $type ? self::get_workflow( $type ) : null;
	}

	// -------------------------------------------------------------------------
	// Stage introspection
	// -------------------------------------------------------------------------

	/**
	 * Return the role string for the given workflow stage index.
	 * Returns 'committee' as a safe default for unknown indices.
	 */
	public static function get_stage_role( array $wf, int $idx ): string {
		$stages = $wf['stages'] ?? array();
		return (string) ( $stages[ $idx ]['role'] ?? 'committee' );
	}

	/**
	 * Return true if the workflow stage at $idx is the gatekeeper stage.
	 */
	public static function is_gatekeeper_stage( array $wf, int $idx ): bool {
		return self::get_stage_role( $wf, $idx ) === 'gatekeeper';
	}

	/**
	 * Find the index of the first gatekeeper stage in the workflow.
	 * Returns -1 if none found.
	 */
	public static function gatekeeper_stage_index( array $wf ): int {
		foreach ( $wf['stages'] ?? array() as $i => $s ) {
			if ( ( $s['role'] ?? '' ) === 'gatekeeper' ) {
				return (int) $i;
			}
		}
		return -1;
	}

	/**
	 * Return the valid decision strings for a specific workflow stage index.
	 * Falls back to the standard set if not configured.
	 */
	public static function get_valid_decisions( array $wf, int $stage_idx ): array {
		$stages    = $wf['stages'] ?? array();
		$stage_cfg = $stages[ $stage_idx ] ?? array();
		$decs      = $stage_cfg['decisions'] ?? array();
		if ( ! empty( $decs ) && is_array( $decs ) ) {
			return array_values( $decs );
		}
		return array( 'Approved', 'Rejected', 'Pending', 'Needs Revision' );
	}

	/**
	 * Return true if the stage at $idx has a `releaseChannel` flag (i.e. this
	 * role is responsible for releasing a decision to the student).
	 */
	public static function stage_has_release_channel( array $wf, int $idx ): bool {
		$stages = $wf['stages'] ?? array();
		return (bool) ( $stages[ $idx ]['releaseChannel'] ?? false );
	}

	// -------------------------------------------------------------------------
	// Stage evaluation
	// -------------------------------------------------------------------------

	/**
	 * Evaluate whether a review stage has reached a terminal outcome.
	 *
	 * Returns one of: 'approved' | 'needs_revision' | 'rejected' | 'pending'
	 *
	 * Decision rules:
	 *   unanimous — every reviewer must have voted Approved for 'approved';
	 *               any Needs Revision vote → 'needs_revision' (takes priority over others);
	 *               any Rejected vote → 'rejected'.
	 *   majority  — strictly more than half of submitted votes are Approved → 'approved';
	 *               otherwise, if any Needs Revision → 'needs_revision'; else 'pending'.
	 *   single    — first non-empty vote determines outcome.
	 */
	public static function evaluate_stage( array $stage, string $rule = 'unanimous' ): string {
		$reviewers = $stage['reviewers'] ?? array();
		if ( empty( $reviewers ) ) {
			return 'pending';
		}
		$decisions = $stage['decisions'] ?? array();
		$votes     = array();
		foreach ( $reviewers as $r ) {
			$em  = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
			$dec = strtolower( trim( (string) ( $decisions[ $em ] ?? '' ) ) );
			if ( $em && $dec !== '' && $dec !== 'pending' ) {
				$votes[] = $dec;
			}
		}
		if ( empty( $votes ) ) {
			return 'pending';
		}
		$total    = count( $reviewers );
		$approved = count( array_filter( $votes, fn( $v ) => $v === 'approved' ) );
		$revision = count( array_filter( $votes, fn( $v ) => $v === 'needs revision' ) );
		$rejected = count( array_filter( $votes, fn( $v ) => $v === 'rejected' ) );

		switch ( $rule ) {
			case 'single':
				// Use the first recorded vote.
				$first = $votes[0];
				if ( $first === 'approved' )       return 'approved';
				if ( $first === 'needs revision' ) return 'needs_revision';
				if ( $first === 'rejected' )        return 'rejected';
				return 'pending';

			case 'majority':
				if ( count( $votes ) < $total ) {
					return 'pending'; // Not everyone has voted yet.
				}
				if ( $approved > $total / 2 ) return 'approved';
				if ( $revision > 0 )          return 'needs_revision';
				if ( $rejected > 0 )          return 'rejected';
				return 'pending';

			case 'unanimous':
			default:
				if ( $rejected > 0 )                  return 'rejected';
				if ( $revision > 0 )                  return 'needs_revision';
				if ( $approved === $total )            return 'approved';
				return 'pending'; // Not everyone voted Approved yet.
		}
	}

	/**
	 * Convenience wrapper: return true if the stage is fully approved per its
	 * configured decision rule.  Reads the rule from the workflow config.
	 *
	 * @param array      $stage     The reviewStages[] entry.
	 * @param array      $wf        The workflow definition.
	 * @param int        $stage_idx The index of this stage within the workflow.
	 */
	public static function is_stage_approved( array $stage, array $wf, int $stage_idx ): bool {
		$stages = $wf['stages'] ?? array();
		$rule   = (string) ( $stages[ $stage_idx ]['decisionRule'] ?? 'unanimous' );
		return self::evaluate_stage( $stage, $rule ) === 'approved';
	}

	// -------------------------------------------------------------------------
	// Status derivation
	// -------------------------------------------------------------------------

	/**
	 * Derive the public-facing submission status string using the workflow engine.
	 * Called from Portal_Data::derive_submission_status() when a workflow config exists.
	 *
	 * Returns a status string, or null to indicate the caller should fall back to
	 * legacy logic (should not happen in practice once config is fully populated).
	 */
	public static function derive_status( array $submission, array $wf ): ?string {
		$stages         = $submission['reviewStages'] ?? array();
		$active_stages  = array_values( array_filter( $stages, fn( $s ) => ! ( $s['skipped'] ?? false ) ) );
		$wf_stages      = $wf['stages'] ?? array();
		$gk_idx         = self::gatekeeper_stage_index( $wf );

		// ── Gated workflow: gatekeeper stage (usually index 0) is the only one that
		//    drives the status shown to the student / on the dashboard.
		if ( $gk_idx >= 0 ) {
			$gk_stage = $active_stages[ $gk_idx ] ?? array();

			// 1. Check gatekeeper stage for terminal decisions (Rejected, Needs Revision).
			$gk_wf_cfg  = $wf_stages[ $gk_idx ] ?? array();
			$gk_rule    = (string) ( $gk_wf_cfg['decisionRule'] ?? 'unanimous' );
			$gk_outcome = self::evaluate_stage( $gk_stage, $gk_rule );
			if ( $gk_outcome === 'rejected' )      return 'Rejected';
			if ( $gk_outcome === 'needs_revision' ) return 'Revision Required';

			// 2. Check for a current (non-superseded) gated release.
			$releases        = is_array( $submission['gatedReleases'] ?? null ) ? $submission['gatedReleases'] : array();
			$current_round   = (int) ( $submission['currentRound'] ?? 0 );
			$last_rel        = ! empty( $releases ) ? end( $releases ) : null;
			$rel_superseded  = false;
			if ( $last_rel ) {
				if ( isset( $last_rel['round'] ) ) {
					$rel_superseded = (int) $last_rel['round'] < $current_round;
				} else {
					// Legacy fallback: timestamp comparison.
					$rel_ts    = isset( $last_rel['releasedAt'] ) ? strtotime( (string) $last_rel['releasedAt'] ) : 0;
					$s0_rev_at = $gk_stage['revisionSubmittedAt'] ?? '';
					$s0_rev_ts = $s0_rev_at ? strtotime( (string) $s0_rev_at ) : 0;
					if ( $s0_rev_ts > 0 && $rel_ts > 0 && $s0_rev_ts > $rel_ts ) {
						$rel_superseded = true;
					}
				}
			}
			if ( $last_rel && ! $rel_superseded ) {
				$rd = (string) ( $last_rel['decision'] ?? '' );
				if ( $rd === 'Rejected' )               return 'Rejected';
				if ( $rd === 'Revision Required' )      return 'Revision Required';
				if ( $rd === 'Conditionally Approved' ) return 'Conditionally Approved';
				if ( $rd === 'Approved' ) {
					// Map to the type-specific final label if configured.
					$final_map = $wf['finalStatusByDecision'] ?? array();
					return $final_map['Approved'] ?? 'Approved';
				}
			}

			// 3. No release yet — derive intermediate status from higher-stage progress.
			$gk_stage_name = trim( (string) ( $gk_stage['stageName'] ?? ( $wf_stages[ $gk_idx ]['name'] ?? 'Primary Review' ) ) );
			if ( $gk_outcome === 'approved' ) {
				// Gatekeeper approved — look at higher stages.
				foreach ( array_slice( $active_stages, $gk_idx + 1 ) as $hs_idx => $hs ) {
					$real_hs_idx = $gk_idx + 1 + $hs_idx;
					$hs_wf_cfg   = $wf_stages[ $real_hs_idx ] ?? array();
					$hs_rule     = (string) ( $hs_wf_cfg['decisionRule'] ?? 'unanimous' );
					$hs_outcome  = self::evaluate_stage( $hs, $hs_rule );
					$hs_name     = trim( (string) ( $hs['stageName'] ?? ( $hs_wf_cfg['name'] ?? 'Higher Stage' ) ) );
					// All decided (any outcome) means the gatekeeper has been notified.
					$all_voted = self::all_reviewers_voted( $hs );
					if ( ! empty( $hs['gatekeeperNotifiedAt'] ) || $all_voted ) {
						return $gk_stage_name . ': Reviewing ' . $hs_name . ' Feedback';
					}
					if ( ! empty( $hs['reviewers'] ) ) {
						return $hs_name . ': Under Review';
					}
				}
				return $gk_stage_name . ': Awaiting Higher Stage Review';
			}

			// Gatekeeper stage has reviewers but not all approved yet.
			if ( ! empty( $gk_stage['reviewers'] ) ) {
				return $gk_stage_name . ': In Progress';
			}
			return $submission['status'] ?? 'Submitted';
		}

		// ── Non-gated: scan all stages sequentially.
		foreach ( $active_stages as $i => $stage ) {
			$rule    = (string) ( ( $wf_stages[ $i ] ?? array() )['decisionRule'] ?? 'unanimous' );
			$outcome = self::evaluate_stage( $stage, $rule );
			if ( $outcome === 'rejected' )      return 'Rejected';
			if ( $outcome === 'needs_revision' ) return 'Revision Required';
			if ( $outcome !== 'approved' && ! empty( $stage['reviewers'] ) ) {
				$name = trim( (string) ( $stage['stageName'] ?? '' ) );
				return $name ? $name . ': In Progress' : 'Under Review';
			}
		}
		// All stages approved.
		$final_map = $wf['finalStatusByDecision'] ?? array();
		return $final_map['Approved'] ?? 'Approved';
	}

	// -------------------------------------------------------------------------
	// Viewer role computation
	// -------------------------------------------------------------------------

	/**
	 * Determine the viewer role for a given user email against a submission.
	 *
	 * Returns one of: 'admin' | 'gatekeeper' | 'submitter' | 'committee' | 'advisory' | 'public'
	 */
	public static function compute_viewer_role( array $submission, string $email, bool $is_admin, array $wf = array() ): string {
		if ( $is_admin ) {
			return 'admin';
		}
		$email = strtolower( trim( $email ) );
		if ( strtolower( trim( (string) ( $submission['submitterEmail'] ?? '' ) ) ) === $email ) {
			return 'submitter';
		}
		// Determine role from reviewStages + workflow config.
		$stages    = $submission['reviewStages'] ?? array();
		$gk_idx    = ! empty( $wf ) ? self::gatekeeper_stage_index( $wf ) : 0;
		$wf_stages = $wf['stages'] ?? array();
		foreach ( $stages as $i => $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
					// Determine role for this stage from workflow config, default to stage index 0 = gatekeeper.
					if ( ! empty( $wf_stages ) ) {
						$role = (string) ( $wf_stages[ $i ]['role'] ?? 'committee' );
					} else {
						$role = ( $i === $gk_idx ) ? 'gatekeeper' : 'committee';
					}
					return $role;
				}
			}
		}
		// Legacy: check assignedReviewers (non-staged submissions).
		foreach ( $submission['assignedReviewers'] ?? array() as $r ) {
			if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
				return 'committee';
			}
		}
		return 'public';
	}

	/**
	 * Return true if the given user can release a gated decision for this submission.
	 */
	public static function can_release( array $submission, string $email, array $wf ): bool {
		$email  = strtolower( trim( $email ) );
		$stages = $submission['reviewStages'] ?? array();
		$gk_idx = self::gatekeeper_stage_index( $wf );
		if ( $gk_idx < 0 ) {
			$gk_idx = 0; // Fallback: treat stage 0 as gatekeeper.
		}
		foreach ( $stages[ $gk_idx ]['reviewers'] ?? array() as $r ) {
			if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// Visibility / data stripping
	// -------------------------------------------------------------------------

	/**
	 * Return a visibility-filtered copy of `reviewStages` appropriate for the
	 * given viewer role.  Strips decisions, feedback, and reviewer identities from
	 * stages the viewer should not see in full.
	 *
	 * This is the engine-driven replacement for the manual stripping logic in
	 * Portal_REST::submission_get().  Called only when a workflow config exists.
	 *
	 * @param array  $stages      The raw reviewStages array.
	 * @param string $viewer_role One of admin|gatekeeper|submitter|committee|advisory.
	 * @param array  $wf          The workflow definition.
	 * @param array  $submission  Full submission array (for context like gatedReleases).
	 * @return array Filtered stages suitable for the given viewer.
	 */
	public static function get_visible_stages( array $stages, string $viewer_role, array $wf, array $submission = array() ): array {
		$wf_stages     = $wf['stages'] ?? array();
		$student_vis   = $wf['studentVisibility'] ?? array();
		$show_names    = (bool) ( $student_vis['showReviewerNames'] ?? true );
		$gk_idx        = self::gatekeeper_stage_index( $wf );

		$result = array();
		foreach ( $stages as $i => $stage ) {
			$wf_stage_cfg = $wf_stages[ $i ] ?? array();
			$visible_to   = (array) ( $wf_stage_cfg['visibleTo'] ?? array( 'admin', 'gatekeeper', 'committee' ) );
			$stage_role   = (string) ( $wf_stage_cfg['role'] ?? 'committee' );

			$filtered = $stage; // Start with a full copy; strip selectively below.

			switch ( $viewer_role ) {
				case 'admin':
					// Admin sees everything — no changes.
					break;

				case 'gatekeeper':
					// Gatekeeper sees all stages with full detail.
					break;

				case 'submitter':
					// Students see stage names and (optionally) reviewer names, but never decisions or feedback.
					unset( $filtered['decisions'], $filtered['feedback'] );
					if ( ! $show_names && ! empty( $filtered['reviewers'] ) ) {
						$filtered['reviewers'] = array_map( function ( $r, $idx ) {
							return array( 'name' => 'Reviewer ' . ( $idx + 1 ), 'email' => '' );
						}, $filtered['reviewers'], array_keys( $filtered['reviewers'] ) );
					}
					// Mark stage 0 as completed when gatekeeper has approved.
					if ( $i === $gk_idx ) {
						$gk_rule = (string) ( $wf_stages[ $gk_idx ]['decisionRule'] ?? 'unanimous' );
						if ( self::evaluate_stage( $stage, $gk_rule ) === 'approved' ) {
							$filtered['stageCompleted'] = true;
						}
					}
					// Mark higher stages as notified when all reviewed (or flag already set).
					if ( $i > $gk_idx && empty( $filtered['gatekeeperNotifiedAt'] ) && self::all_reviewers_voted( $stage ) ) {
						$filtered['gatekeeperNotifiedAt'] = 'derived';
					}
					break;

				case 'committee':
				case 'advisory':
					// Reviewers see their own stage in full; other stages get stripped.
					if ( ! in_array( $viewer_role, $visible_to, true ) ) {
						unset( $filtered['decisions'], $filtered['feedback'] );
						$filtered['reviewers'] = array(); // Hide identities of other stages.
					}
					break;

				default:
					// Public: strip everything sensitive.
					unset( $filtered['decisions'], $filtered['feedback'] );
					$filtered['reviewers'] = array();
					break;
			}

			$result[ $i ] = $filtered;
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return true if every reviewer in the stage has submitted any non-empty decision.
	 */
	public static function all_reviewers_voted( array $stage ): bool {
		$reviewers = $stage['reviewers'] ?? array();
		if ( empty( $reviewers ) ) {
			return false;
		}
		$decisions = $stage['decisions'] ?? array();
		foreach ( $reviewers as $r ) {
			$em  = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
			$dec = trim( (string) ( $decisions[ $em ] ?? '' ) );
			if ( $em && $dec === '' ) {
				return false;
			}
		}
		return true;
	}
}
