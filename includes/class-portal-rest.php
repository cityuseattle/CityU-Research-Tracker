<?php
/**
 * Research Review Portal - REST API (WordPress).
 * Registers routes under wp-json/research-portal/v1/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Portal_REST {

	const NAMESPACE = 'research-portal/v1';

	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/health', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'health' ),
			'permission_callback' => '__return_true', // Public endpoint
		) );
		register_rest_route( self::NAMESPACE, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit' ),
			'permission_callback' => array( __CLASS__, 'can_submit_research' ),
			'args'                => array(),
		) );
		register_rest_route( self::NAMESPACE, '/submissions', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submissions_list' ),
			'permission_callback' => array( __CLASS__, 'can_view_submissions' ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/public', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submissions_public' ),
			'permission_callback' => '__return_true', // Public endpoint
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submission_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/preview', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submission_preview' ),
			'permission_callback' => array( __CLASS__, 'can_view_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'submission_patch' ),
			'permission_callback' => array( __CLASS__, 'can_edit_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/feedback', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'feedback' ),
			'permission_callback' => array( __CLASS__, 'can_provide_feedback' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/comments', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'comments' ),
			'permission_callback' => array( __CLASS__, 'can_add_comments' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/attachments', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'attachments_upload' ),
			'permission_callback' => array( __CLASS__, 'can_upload_attachments' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/attachments/(?P<filename>[^/]+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'attachments_download' ),
			'permission_callback' => array( __CLASS__, 'can_download_attachments' ),
			'args'                => array(
				'id'       => array( 'required' => true ),
				'filename' => array( 'required' => true ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/dashboard', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'dashboard' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/workflow', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_workflow' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/performance', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_performance' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/reviewer', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_reviewer' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/workload', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_workload' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/daily', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_daily' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/conflicts', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'conflicts_list' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/conflicts', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'declare_conflict' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/config/review-templates', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'config_review_templates_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/config/review-templates', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'config_review_templates_put' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/reviews/rate', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'reviews_rate' ),
			'permission_callback' => array( __CLASS__, 'can_provide_feedback' ),
		) );
		register_rest_route( self::NAMESPACE, '/reports/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'reports_export' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/notifications', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'notifications' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/timeline', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submission_timeline' ),
			'permission_callback' => array( __CLASS__, 'can_view_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/audit-log', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'audit_log_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/assignment-summary', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'assignment_summary' ),
			'permission_callback' => array( __CLASS__, 'can_view_assignments' ),
		) );
		register_rest_route( self::NAMESPACE, '/config/suggest-reviewers', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'config_suggest_reviewers' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/config/apply-pool-to-submissions', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'config_apply_pool' ),
			'permission_callback' => array( __CLASS__, 'can_assign_reviewers' ),
		) );
		register_rest_route( self::NAMESPACE, '/config', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'config_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/config', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'config_put' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );
		// Submission Types CRUD
		register_rest_route( self::NAMESPACE, '/submission-types', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submission_types_get' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NAMESPACE, '/submission-types', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'submission_types_put' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/submission-types/(?P<id>[a-zA-Z0-9_\-]+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'submission_type_patch' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submission-types/(?P<id>[a-zA-Z0-9_\-]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'submission_type_delete' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Workflow Stages library CRUD
		register_rest_route( self::NAMESPACE, '/workflow-stages', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'workflow_stages_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/workflow-stages/(?P<id>[a-zA-Z0-9_\-]+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'workflow_stage_patch' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/workflow-stages/(?P<id>[a-zA-Z0-9_\-]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'workflow_stage_delete' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/reviewers', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'reviewers' ),
			'permission_callback' => array( __CLASS__, 'can_view_reviewers' ),
		) );
		register_rest_route( self::NAMESPACE, '/reviews', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'reviews' ),
			'permission_callback' => array( __CLASS__, 'can_view_reviews' ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/skip-stage', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'skip_stage' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/deadlines', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submission_deadlines' ),
			'permission_callback' => array( __CLASS__, 'can_view_submission' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/analytics/overdue', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_overdue' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );
		// Portal user management (students & reviewers)
		// Self-profile: only authenticated users can read & update their own record
		register_rest_route( self::NAMESPACE, '/portal-users/me', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'portal_users_me' ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NAMESPACE, '/portal-users/me', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'portal_users_me_update' ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NAMESPACE, '/portal-users', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'portal_users_list' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/portal-users', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'portal_users_create' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/portal-users/(?P<id>\d+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'portal_users_update' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/portal-users/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'portal_users_delete' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Delete a legacy JSON-only reviewer (no WP account) by their pool ID (e.g. "r4")
		register_rest_route( self::NAMESPACE, '/portal-users/json/(?P<jsonId>[a-zA-Z0-9\-]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'portal_users_delete_json' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'jsonId' => array( 'required' => true ) ),
		) );
		// Delete a legacy student who only exists via a submission record (URL-encoded email)
		register_rest_route( self::NAMESPACE, '/portal-users/json-student/(?P<email>.+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'portal_users_delete_json_student' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'email' => array( 'required' => true ) ),
		) );
		// Admin-only password reset
		register_rest_route( self::NAMESPACE, '/portal-users/(?P<id>\d+)/reset-password', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'portal_users_reset_password' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Portal settings (university branding + SSO)
		register_rest_route( self::NAMESPACE, '/settings', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'portal_settings_get' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );
		register_rest_route( self::NAMESPACE, '/settings', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'portal_settings_update' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );
		// OAuth2 redirect callback for Microsoft Entra SSO (public — no auth required)
		register_rest_route( self::NAMESPACE, '/auth/callback', array(
			'methods'             => 'GET',
			'callback'            => array( 'RRP_Auth_Provider', 'handle_entra_callback' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function health( WP_REST_Request $request ) {
		return new WP_REST_Response( array( 'ok' => true, 'bootId' => (string) time() ), 200 );
	}

	/**
	 * Permission callback functions for role-based access control
	 */

	public static function can_submit_research( WP_REST_Request $request ) {
		// Require login and submission capability
		return is_user_logged_in() && current_user_can( 'rrp_submit_research' );
	}

	public static function can_view_submissions( WP_REST_Request $request ) {
		// Users can view their own submissions or reviewers/admins can view assigned ones
		return current_user_can( 'rrp_view_own_submissions' ) || current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_review_submissions' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_view_submission( WP_REST_Request $request ) {
		$submission_id = $request->get_param( 'id' );
		
		// Admins and coordinators can view any submission
		if ( current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_full_admin_access' ) ) {
			return true;
		}

		// Check if user is the submitter or assigned reviewer
		$submission = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$user_email   = strtolower( trim( (string) $current_user->user_email ) );

		// Check if user is the submitter
		if ( isset( $submission['submitterEmail'] ) && strtolower( trim( (string) $submission['submitterEmail'] ) ) === $user_email ) {
			return current_user_can( 'rrp_view_own_submissions' );
		}

		// Check if user is an assigned reviewer (top-level or per-stage)
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			foreach ( $submission['assignedReviewers'] ?? array() as $reviewer ) {
				if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
			foreach ( $submission['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $reviewer ) {
					if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function can_edit_submission( WP_REST_Request $request ) {
		$submission_id = $request->get_param( 'id' );
		
		// Admins and coordinators can edit any submission
		if ( current_user_can( 'rrp_edit_any_submission' ) || current_user_can( 'rrp_full_admin_access' ) ) {
			return true;
		}

		$submission = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$user_email   = strtolower( trim( (string) $current_user->user_email ) );

		// Reviewers assigned to this submission can PATCH it (to record decisions/feedback)
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			// Check top-level assignedReviewers
			foreach ( $submission['assignedReviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
			// Also check per-stage reviewers
			foreach ( $submission['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
						return true;
					}
				}
			}
		}

		// Check if user is the submitter and can edit their own submissions
		if ( current_user_can( 'rrp_edit_own_submissions' ) ) {
			return isset( $submission['submitterEmail'] ) && strtolower( trim( (string) $submission['submitterEmail'] ) ) === $user_email;
		}

		return false;
	}

	public static function can_provide_feedback( WP_REST_Request $request ) {
		// Reviewers can provide feedback on their assigned submissions
		if ( ! current_user_can( 'rrp_provide_feedback' ) ) {
			return false;
		}

		$submission_id = $request->get_param( 'id' );
		$submission = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$user_email   = strtolower( trim( (string) $current_user->user_email ) );

		// Check top-level assigned reviewers (case-insensitive)
		foreach ( $submission['assignedReviewers'] ?? array() as $reviewer ) {
			if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
				return true;
			}
		}
		// Also check per-stage reviewers
		foreach ( $submission['reviewStages'] ?? array() as $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $reviewer ) {
				if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function can_add_comments( WP_REST_Request $request ) {
		// Similar to feedback but also allow submitters to comment
		$submission_id = $request->get_param( 'id' );
		$submission = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$user_email   = strtolower( trim( (string) $current_user->user_email ) );

		// Admins can always comment
		if ( current_user_can( 'rrp_full_admin_access' ) ) {
			return true;
		}

		// Check if user is the submitter (case-insensitive)
		if ( isset( $submission['submitterEmail'] ) && strtolower( trim( (string) $submission['submitterEmail'] ) ) === $user_email ) {
			return true;
		}

		// Check if user is an assigned reviewer (top-level or per-stage, case-insensitive)
		if ( current_user_can( 'rrp_provide_feedback' ) ) {
			foreach ( $submission['assignedReviewers'] ?? array() as $reviewer ) {
				if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
			foreach ( $submission['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $reviewer ) {
					if ( strtolower( trim( (string) ( $reviewer['email'] ?? '' ) ) ) === $user_email ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function can_upload_attachments( WP_REST_Request $request ) {
		// Admins and coordinators can always upload.
		if ( current_user_can( 'rrp_edit_any_submission' ) || current_user_can( 'rrp_full_admin_access' ) ) {
			return true;
		}
		$submission_id = $request->get_param( 'id' );
		$submission    = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}
		$current_user = wp_get_current_user();
		$user_email   = strtolower( trim( (string) $current_user->user_email ) );
		// Submitter can upload (own submission).
		if ( current_user_can( 'rrp_edit_own_submissions' ) &&
			isset( $submission['submitterEmail'] ) &&
			strtolower( trim( (string) $submission['submitterEmail'] ) ) === $user_email ) {
			return true;
		}
		// Assigned reviewer can upload an annotated version.
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			$assigned = $submission['assignedReviewers'] ?? array();
			foreach ( $assigned as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function can_download_attachments( WP_REST_Request $request ) {
		return self::can_view_submission( $request );
	}

	public static function can_view_assignments( WP_REST_Request $request ) {
		return current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_assign_reviewers( WP_REST_Request $request ) {
		return current_user_can( 'rrp_assign_reviewers' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_manage_workflow( WP_REST_Request $request ) {
		return current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_view_config( WP_REST_Request $request ) {
		return current_user_can( 'rrp_view_all_submissions' )
			|| current_user_can( 'rrp_full_admin_access' )
			|| current_user_can( 'rrp_review_submissions' )
			|| current_user_can( 'rrp_submit_research' );
	}

	public static function can_manage_config( WP_REST_Request $request ) {
		return current_user_can( 'rrp_manage_system_config' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_view_reviewers( WP_REST_Request $request ) {
		return current_user_can( 'rrp_assign_reviewers' ) || current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_view_reviews( WP_REST_Request $request ) {
		return current_user_can( 'rrp_review_submissions' ) || current_user_can( 'rrp_view_review_dashboard' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_view_dashboard( WP_REST_Request $request ) {
		return current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_review_submissions' ) || current_user_can( 'rrp_view_own_submissions' ) || current_user_can( 'rrp_full_admin_access' );
	}

	public static function dashboard( WP_REST_Request $request ) {
		$user_email = '';
		$current_user = wp_get_current_user();
		if ( isset( $current_user->user_email ) ) {
			$user_email = strtolower( trim( (string) $current_user->user_email ) );
		}
		$req_email = $request->get_param( 'userEmail' );
		if ( $req_email ) {
			$user_email = strtolower( trim( (string) $req_email ) );
		}
		$data = Portal_Data::get_dashboard_data( array(
			'userEmail' => $user_email,
			'role' => isset( $current_user->roles ) && is_array( $current_user->roles ) ? implode( ',', $current_user->roles ) : '',
			'isAdmin' => current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_full_admin_access' ),
			'isReviewer' => current_user_can( 'rrp_review_submissions' ),
			'isSubmitter' => current_user_can( 'rrp_view_own_submissions' ),
		) );
		return new WP_REST_Response( $data, 200 );
	}

	public static function analytics_workflow( WP_REST_Request $request ) {
		$metrics = Portal_Data::get_workflow_metrics( self::analytics_user_filter() );
		return new WP_REST_Response( $metrics, 200 );
	}

	public static function analytics_performance( WP_REST_Request $request ) {
		$metrics = Portal_Data::get_performance_metrics( self::analytics_user_filter() );
		return new WP_REST_Response( $metrics, 200 );
	}

	/**
	 * Returns a filter params array scoped to the current user's role.
	 * - Coordinator / Admin : no filter (all submissions)
	 * - Reviewer            : { reviewerEmail: current email }
	 * - Student             : { submitterEmail: current email }
	 */
	private static function analytics_user_filter() {
		$user = wp_get_current_user();
		$email = strtolower( trim( (string) ( $user->user_email ?? '' ) ) );

		if ( current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_full_admin_access' ) || current_user_can( 'rrp_manage_workflow' ) ) {
			return array(); // coordinator / admin: no filter
		}
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			return array( 'reviewerEmail' => $email );
		}
		// student / everyone else
		return array( 'submitterEmail' => $email );
	}

	public static function analytics_reviewer( WP_REST_Request $request ) {
		$reviewer_email = $request->get_param( 'reviewerEmail' );
		if ( ! $reviewer_email ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail query parameter is required.' ), 400 );
		}
		$metrics = Portal_Data::get_reviewer_metrics( $reviewer_email );
		return new WP_REST_Response( $metrics, 200 );
	}

	public static function analytics_workload( WP_REST_Request $request ) {
		$reviewer_email = $request->get_param( 'reviewerEmail' );
		if ( ! $reviewer_email ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail query parameter is required.' ), 400 );
		}
		$workload = Portal_Data::get_reviewer_workload( $reviewer_email );
		return new WP_REST_Response( $workload, 200 );
	}

	public static function analytics_daily( WP_REST_Request $request ) {
		$filter = self::analytics_user_filter();
		$data   = Portal_Data::read_submissions();
		$all    = $data['submissions'] ?? array();

		// Apply the same role-scoping as workflow/performance
		if ( ! empty( $filter['submitterEmail'] ) ) {
			$fe   = strtolower( $filter['submitterEmail'] );
			$subs = array_filter( $all, function ( $s ) use ( $fe ) {
				return strtolower( trim( (string) ( $s['submitterEmail'] ?? '' ) ) ) === $fe;
			} );
		} elseif ( ! empty( $filter['reviewerEmail'] ) ) {
			$re   = strtolower( $filter['reviewerEmail'] );
			$subs = array_filter( $all, function ( $s ) use ( $re ) {
				foreach ( $s['assignedReviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $re ) return true;
				}
				return false;
			} );
		} else {
			$subs = $all; // admin/coordinator
		}

		// Determine date range: earliest submission to today
		$today = new DateTime( 'today' );
		$earliest = clone $today;
		foreach ( $subs as $sub ) {
			$ca = $sub['createdAt'] ?? '';
			if ( ! $ca ) continue;
			try {
				$dt = new DateTime( $ca );
				if ( $dt < $earliest ) $earliest = $dt;
			} catch ( Exception $e ) { /* skip */ }
		}
		// Clamp: at most 90 days back
		$min_start = ( clone $today )->modify( '-89 days' );
		if ( $earliest < $min_start ) $earliest = $min_start;

		// Build complete date list
		$dates = array();
		$cur   = clone $earliest;
		$cur->setTime( 0, 0, 0 );
		while ( $cur <= $today ) {
			$dates[] = $cur->format( 'Y-m-d' );
			$cur->modify( '+1 day' );
		}
		$date_index = array_flip( $dates );

		// Bucket by date + status
		$buckets = array(); // date => [ status => count ]
		foreach ( $subs as $sub ) {
			$ca = $sub['createdAt'] ?? '';
			if ( ! $ca ) continue;
			try { $dt = new DateTime( $ca ); } catch ( Exception $e ) { continue; }
			$d = $dt->format( 'Y-m-d' );
			if ( ! isset( $date_index[ $d ] ) ) continue;
			$status = $sub['status'] ?? 'Unknown';
			$buckets[ $d ][ $status ] = ( $buckets[ $d ][ $status ] ?? 0 ) + 1;
		}

		// Collect all statuses
		$all_statuses = array();
		foreach ( $buckets as $day_data ) {
			foreach ( array_keys( $day_data ) as $s ) $all_statuses[ $s ] = true;
		}

		$status_colors = array(
			'Submitted'                    => '#3b82f6',
			'Under Review'                 => '#f59e0b',
			'Under Initial Review'         => '#f59e0b',
			'Administrative Review'        => '#f59e0b',
			'Revision Required'            => '#8b5cf6',
			'Revision Submitted'           => '#a78bfa',
			'Approved'                     => '#22c55e',
			'Confirmed for Presentation'   => '#22c55e',
			'Published'                    => '#22c55e',
			'Approved for Submission'      => '#22c55e',
			'Rejected'                     => '#ef4444',
			'Draft'                        => '#94a3b8',
		);
		$default_color = '#64748b';

		$series = array();
		foreach ( array_keys( $all_statuses ) as $status ) {
			$values = array();
			foreach ( $dates as $d ) {
				$values[] = $buckets[ $d ][ $status ] ?? 0;
			}
			$series[] = array(
				'status' => $status,
				'color'  => $status_colors[ $status ] ?? $default_color,
				'values' => $values,
			);
		}

		return new WP_REST_Response( array( 'dates' => $dates, 'series' => $series ), 200 );
	}

	public static function conflicts_list( WP_REST_Request $request ) {
		return new WP_REST_Response( Portal_Data::get_conflict_of_interest_records(), 200 );
	}

	public static function declare_conflict( WP_REST_Request $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();
		$reviewer_email = isset( $body['reviewerEmail'] ) ? trim( (string) $body['reviewerEmail'] ) : '';
		$submission_id = isset( $body['submissionId'] ) ? trim( (string) $body['submissionId'] ) : '';
		$reason = isset( $body['reason'] ) ? trim( (string) $body['reason'] ) : '';
		if ( ! $reviewer_email || ! $submission_id || ! $reason ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail, submissionId, and reason are required.' ), 400 );
		}
		$record = Portal_Data::declare_conflict_of_interest( $reviewer_email, $submission_id, $reason );
		return new WP_REST_Response( $record, 201 );
	}

	public static function config_review_templates_get( WP_REST_Request $request ) {
		return new WP_REST_Response( Portal_Data::get_review_criteria_templates(), 200 );
	}

	public static function config_review_templates_put( WP_REST_Request $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();
		$templates = isset( $body['templates'] ) && is_array( $body['templates'] ) ? $body['templates'] : array();
		$updated = Portal_Data::set_review_criteria_templates( $templates );
		return new WP_REST_Response( array( 'templates' => $updated ), 200 );
	}

	public static function reviews_rate( WP_REST_Request $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();
		$submission_id = isset( $body['submissionId'] ) ? trim( (string) $body['submissionId'] ) : '';
		$reviewer_email = isset( $body['reviewerEmail'] ) ? strtolower( trim( (string) $body['reviewerEmail'] ) ) : '';
		$ratings = isset( $body['ratings'] ) && is_array( $body['ratings'] ) ? $body['ratings'] : array();
		$comments = isset( $body['comments'] ) ? trim( (string) $body['comments'] ) : '';
		if ( ! $submission_id || ! $reviewer_email || empty( $ratings ) ) {
			return new WP_REST_Response( array( 'error' => 'submissionId, reviewerEmail, and ratings are required.' ), 400 );
		}
		$data = Portal_Data::read_submissions();
		$found = false;
		foreach ( $data['submissions'] as &$sub ) {
			if ( $sub['id'] === $submission_id ) {
				$found = true;
				if ( ! isset( $sub['reviewScores'] ) || ! is_array( $sub['reviewScores'] ) ) {
					$sub['reviewScores'] = array();
				}
				$sub['reviewScores'][ $reviewer_email ] = array(
					'ratings' => $ratings,
					'comments' => $comments,
					'updatedAt' => gmdate( 'c' ),
				);
				Portal_Data::write_submissions( $data );
				break;
			}
		}
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found' ), 404 );
		}
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public static function reports_export( WP_REST_Request $request ) {
		$format = strtolower( trim( (string) $request->get_param( 'format' ) ) );
		if ( $format !== 'csv' && $format !== 'xlsx' ) {
			$format = 'csv';
		}

		$type = strtolower( trim( (string) $request->get_param( 'type' ) ) );
		if ( $type !== 'workflow' && $type !== 'performance' ) {
			$type = 'workflow';
		}

		$data = ( $type === 'performance' ) ? Portal_Data::get_performance_metrics() : Portal_Data::get_workflow_metrics();

		if ( $format === 'xlsx' ) {
			// Simplified xlsx as CSV with extra header for systems that can open as Excel.
			$content = Portal_Data::generate_report_csv( array( $data ), array_keys( $data ) );
			$filename = sprintf( 'rrp-%s-report-%s.xlsx', $type, date( 'Ymd' ) );
			return new WP_REST_Response( array( 'content' => base64_encode( $content ), 'filename' => $filename ), 200 );
		}

		$content = Portal_Data::generate_report_csv( array( $data ), array_keys( $data ) );
		$filename = sprintf( 'rrp-%s-report-%s.csv', $type, date( 'Ymd' ) );
		return new WP_REST_Response( array( 'content' => base64_encode( $content ), 'filename' => $filename ), 200 );
	}

	/**
	 * Helper function to get submission by ID
	 */
	private static function get_submission_by_id( $id ) {
		$data = Portal_Data::read_submissions();
		foreach ( $data['submissions'] as $submission ) {
			if ( $submission['id'] === $id ) {
				return $submission;
			}
		}
		return null;
	}

	public static function submit( WP_REST_Request $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();
		$type = isset( $body['type'] ) ? strtolower( trim( (string) $body['type'] ) ) : '';
		$allowed = array( 'conference', 'publication', 'student-project', 'grant' );
		if ( ! in_array( $type, $allowed, true ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'Invalid submission type.' ), 400 );
		}
		$is_draft = isset( $body['status'] ) && strtolower( trim( (string) $body['status'] ) ) === 'draft';
		$errors = $is_draft ? array() : Portal_Data::validate_submission( $type, $body );
		if ( ! empty( $errors ) ) {
			return new WP_REST_Response( array( 'success' => false, 'errors' => $errors ), 400 );
		}
		$submission_id = Portal_Data::next_id( $type );
		$status = $is_draft ? 'Draft' : ( $type === 'conference' ? 'Submitted - Awaiting Review' : 'Submitted' );
		$submission = array_merge( $body, array(
			'id'        => $submission_id,
			'type'      => $type,
			'status'    => $status,
			'createdAt' => gmdate( 'c' ),
		) );
		$config = Portal_Data::read_config();
		$has_pool = ( ! empty( $config['activeCohort'] ) && ! empty( $config['poolCohorts'][ $config['activeCohort'] ][ $type ] ) )
			|| ( ! empty( $config['reviewerPools'][ $type ]['reviewerIds'] ) );

		// Prefer the student's personal reviewer assignments (set during onboarding Step 3)
		$personal_assigned = false;
		$sub_type_key = isset( $body['submissionType'] ) ? sanitize_text_field( $body['submissionType'] ) : $type;
		if ( is_user_logged_in() ) {
			$meta = get_user_meta( get_current_user_id(), 'rrp_default_stage_reviewers', true );
			if ( is_array( $meta ) && ! empty( $meta[ $sub_type_key ] ) && is_array( $meta[ $sub_type_key ] ) ) {
				$review_stages      = array();
				$assigned_reviewers = array();
				$stage_index        = 1;
				foreach ( $meta[ $sub_type_key ] as $stage_name => $stage_reviewers ) {
					$stage_name = (string) $stage_name;
					$objects    = array();
					if ( is_array( $stage_reviewers ) ) {
						foreach ( $stage_reviewers as $r ) {
							if ( is_array( $r ) && ( ! empty( $r['email'] ) || ! empty( $r['id'] ) ) ) {
								$objects[] = array(
									'id'    => $r['id']    ?? null,
									'name'  => $r['name']  ?? '',
									'email' => $r['email'] ?? '',
								);
							}
						}
					}
					$review_stages[] = array(
						'stageName'           => $stage_name,
						'stageIndex'          => $stage_index++,
						'reviewers'           => $objects,
						'decisions'           => array(),
						'feedback'            => array(),
						'revisionSubmittedAt' => null,
					);
					foreach ( $objects as $r ) {
						$em = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
						$already = false;
						foreach ( $assigned_reviewers as $ar ) {
							if ( strtolower( trim( (string) ( $ar['email'] ?? '' ) ) ) === $em ) { $already = true; break; }
						}
						if ( $em && ! $already ) {
							$assigned_reviewers[] = $r;
						}
					}
				}
				if ( ! empty( $review_stages ) ) {
					$submission['reviewStages']      = $review_stages;
					$submission['assignedReviewers'] = $assigned_reviewers;
					$personal_assigned = true;
				}
			}
		}
		if ( ! $personal_assigned && $has_pool ) {
			$submission = Portal_Data::auto_assign_submission( $submission, $submission['submitterEmail'] ?? '' );
		}
		$data = Portal_Data::read_submissions();
		$data['submissions'][] = $submission;
		$new_idx = count( $data['submissions'] ) - 1;
		self::append_audit_log( $data, $new_idx, 'submission_created', $is_draft ? 'Draft saved.' : 'Submission created (type: ' . $type . ').' );
		Portal_Data::write_submissions( $data );

		// Send confirmation email for non-draft submissions
		if ( ! $is_draft ) {
			self::send_confirmation_email( $submission );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'id'      => $submission_id,
			'message' => $is_draft ? 'Draft saved.' : 'Submission received. You will receive a confirmation email shortly.',
		), 201 );
	}

	public static function send_confirmation_email( $submission ) {
		$to = trim( (string) ( $submission['submitterEmail'] ?? '' ) );
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}
		$subject = 'Research Review Portal: Submission Received (' . ( $submission['id'] ?? '' ) . ')';
		$message = sprintf(
			"Hello %s,\n\nYour submission (%s) has been received and is now in the review queue.\n\nTitle: %s\nType: %s\nStatus: %s\n\nThank you,\nResearch Review Portal",
			trim( (string) ( $submission['submitterName'] ?? $submission['submitterEmail'] ?? '' ) ),
			( $submission['id'] ?? '' ),
			( $submission['title'] ?? '–' ),
			( $submission['type'] ?? '–' ),
			( $submission['status'] ?? '–' )
		);
		if ( function_exists( 'wp_mail' ) ) {
			return (bool) wp_mail( $to, $subject, $message );
		}
		return true;
	}

	public static function scheduled_report() {
		$metrics = Portal_Data::get_performance_metrics();
		$workflow = Portal_Data::get_workflow_metrics();
		$now = date( 'Y-m-d H:i:s' );
		$subject = "Research Review Portal Daily Report ({$now})";
		$message = "Daily summary from Research Review Portal:\n\n";
		$message .= "Total submissions: " . ( $workflow['totalSubmissions'] ?? 0 ) . "\n";
		$message .= "Finalized: " . ( $metrics['finalizedCount'] ?? 0 ) . "\n";
		$message .= "In progress: " . ( $metrics['inProgressCount'] ?? 0 ) . "\n";
		$message .= "Average decision days: " . ( $metrics['averageTimeToDecisionDays'] ?? 0 ) . "\n";
		$message .= "Late review alerts: " . ( $metrics['lateReviewAlerts'] ?? 0 ) . "\n";

		$admin_email = get_option( 'admin_email' );
		if ( $admin_email && is_email( $admin_email ) && function_exists( 'wp_mail' ) ) {
			wp_mail( $admin_email, $subject, $message );
		}
		return true;
	}

	public static function submissions_list( WP_REST_Request $request ) {
		$data = Portal_Data::read_submissions();
		$list = array();
		foreach ( $data['submissions'] as $s ) {
			$list[] = array(
				'id'                 => $s['id'] ?? null,
				'type'               => $s['type'] ?? null,
				'submissionType'     => $s['submissionType'] ?? null,
				'status'             => $s['status'] ?? null,
				'title'              => $s['title'] ?? null,
				'submitterEmail'     => $s['submitterEmail'] ?? null,
				'submitterName'      => $s['submitterName'] ?? null,
				'createdAt'          => $s['createdAt'] ?? null,
				'assignedReviewers'  => $s['assignedReviewers'] ?? array(),
			);
		}
		return new WP_REST_Response( array( 'submissions' => $list ), 200 );
	}

	public static function submissions_public( WP_REST_Request $request ) {
		$data = Portal_Data::read_submissions();
		$list = array();
		foreach ( $data['submissions'] as $s ) {
			if ( ! in_array( $s['status'] ?? '', Portal_Data::PUBLIC_STATUSES, true ) ) {
				continue;
			}
			$list[] = array(
				'id'           => $s['id'] ?? null,
				'type'         => $s['type'] ?? null,
				'status'       => $s['status'] ?? null,
				'title'        => $s['title'] ?? null,
				'abstract'      => isset( $s['abstract'] ) ? substr( $s['abstract'], 0, 500 ) : '',
				'researchArea' => $s['researchArea'] ?? null,
				'keywords'     => $s['keywords'] ?? null,
				'createdAt'    => $s['createdAt'] ?? null,
			);
		}
		return new WP_REST_Response( array( 'submissions' => $list ), 200 );
	}

	public static function submission_get( WP_REST_Request $request ) {
		$id = $request['id'];
		$data = Portal_Data::read_submissions();
		$sub = null;
		foreach ( $data['submissions'] as $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$sub = $s;
				break;
			}
		}
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
			foreach ( $sub['reviewStages'] as $i => $rs ) {
				$decisions = $rs['decisions'] ?? array();
				$norm = array();
				foreach ( $decisions as $k => $v ) {
					$norm[ strtolower( trim( (string) $k ) ) ] = $v;
				}
				$sub['reviewStages'][ $i ]['decisions'] = $norm;
			}
		}
		return new WP_REST_Response( $sub, 200 );
	}

	public static function submission_preview( WP_REST_Request $request ) {
		// reuse submission_get for now
		return self::submission_get( $request );
	}

	public static function submission_timeline( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		$sub = self::get_submission_by_id( $id );
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$stages = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
		$timeline = array();
		foreach ( $stages as $stage ) {
			$reviewers = isset( $stage['reviewers'] ) && is_array( $stage['reviewers'] ) ? $stage['reviewers'] : array();
			$decisions = isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ? $stage['decisions'] : array();
			$timeline[] = array(
				'stageName' => $stage['stageName'] ?? '',
				'status'    => Portal_Data::is_stage_approved( $stage ) ? 'Approved' : ( in_array( 'needs revision', array_map( 'strtolower', array_values( $decisions ) ), true ) ? 'Revision Required' : 'In Progress' ),
				'reviewers' => array_map( function( $r ) use ( $decisions ) {
					$email = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
					return array(
						'name' => $r['name'] ?? '',
						'email' => $r['email'] ?? '',
						'decision' => isset( $decisions[ $email ] ) ? $decisions[ $email ] : 'Pending',
					);
				}, $reviewers ),
				'feedback' => $stage['feedback'] ?? array(),
				'revisionSubmittedAt' => $stage['revisionSubmittedAt'] ?? null,
			);
		}
		return new WP_REST_Response( array( 'id' => $id, 'timeline' => $timeline ), 200 );
	}

	public static function notifications( WP_REST_Request $request ) {
		$current_user = wp_get_current_user();
		$email = strtolower( trim( (string) ( $current_user->user_email ?? '' ) ) );
		$submissions = Portal_Data::read_submissions();
		$submissions = $submissions['submissions'] ?? array();
		$notify = array();
		foreach ( $submissions as $sub ) {
			$status = $sub['status'] ?? '';
			$submitter = strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) );
			$assigned = isset( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ? $sub['assignedReviewers'] : array();
			$is_reviewer = false;
			foreach ( $assigned as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
					$is_reviewer = true;
					break;
				}
			}
			if ( $submitter === $email ) {
				// update required states for submitter
				if ( in_array( $status, array( 'Revision Required', 'Rejected' ), true ) ) {
					$notify[] = array( 'id' => $sub['id'] ?? '', 'type' => 'revision', 'message' => 'Revision requested or rejected, please resubmit.', 'status' => $status );
				}
			}
			if ( $is_reviewer && in_array( $status, array( 'Submitted', 'Under Review', 'Submitted - Awaiting Review', 'Under Initial Review', 'Administrative Review', 'Revision Required', 'Revision Submitted' ), true ) ) {
				$notify[] = array( 'id' => $sub['id'] ?? '', 'type' => 'review', 'message' => 'Review pending for submission.', 'status' => $status );
			}
		}
		return new WP_REST_Response( array( 'notifications' => $notify ), 200 );
	}

	public static function submission_patch( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$idx = $i;
				break;
			}
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];

		if ( isset( $body['status'] ) && $body['status'] === 'Withdrawn' ) {
			if ( ! in_array( $sub['status'] ?? '', Portal_Data::WITHDRAWABLE, true ) ) {
				return new WP_REST_Response( array( 'error' => 'Submission can no longer be withdrawn at this stage.' ), 400 );
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'status' => 'Withdrawn' ) );
			self::append_audit_log( $data, $idx, 'status_changed', 'Submission withdrawn.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( ! empty( $body['status'] ) && is_string( $body['status'] ) && in_array( trim( $body['status'] ), Portal_Data::PUBLIC_STATUSES, true ) ) {
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'status' => trim( $body['status'] ) ) );
			self::append_audit_log( $data, $idx, 'status_changed', 'Status set to "' . trim( $body['status'] ) . '".' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( isset( $body['assignedReviewers'] ) && is_array( $body['assignedReviewers'] ) ) {
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'assignedReviewers' => $body['assignedReviewers'] ) );
			self::append_audit_log( $data, $idx, 'reviewers_assigned', 'Assigned reviewers updated.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( isset( $body['reviewStages'] ) && is_array( $body['reviewStages'] ) ) {
			$incoming = $body['reviewStages'];
			$existing = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
			$assigned = array();
			$merged = array();
			foreach ( $incoming as $rs ) {
				$name = $rs['stageName'] ?? '';
				$existing_rs = null;
				foreach ( $existing as $e ) {
					if ( strtolower( (string) ( $e['stageName'] ?? '' ) ) === strtolower( $name ) ) {
						$existing_rs = $e;
						break;
					}
				}
				$reviewers = isset( $rs['reviewers'] ) && is_array( $rs['reviewers'] ) ? $rs['reviewers'] : array();
				foreach ( $reviewers as $r ) {
					$em = isset( $r['email'] ) ? strtolower( trim( (string) $r['email'] ) ) : '';
					if ( $em && ! self::find_by_email( $assigned, $em ) ) {
						$assigned[] = array(
							'id'    => $r['id'] ?? null,
							'name'  => $r['name'] ?? '',
							'email' => $r['email'] ?? '',
						);
					}
				}
				$merged[] = array(
					'stageName'           => $name,
					'stageIndex'          => $rs['stageIndex'] ?? 0,
					'reviewers'           => $reviewers,
					'decisions'           => $existing_rs['decisions'] ?? array(),
					'feedback'            => $existing_rs['feedback'] ?? array(),
					'revisionSubmittedAt' => $existing_rs['revisionSubmittedAt'] ?? null,
					'requiredCount'       => $rs['requiredCount'] ?? $existing_rs['requiredCount'] ?? null,
				);
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $merged, 'assignedReviewers' => $assigned ) );
			self::append_audit_log( $data, $idx, 'reviewers_assigned', 'Review stages and reviewers configured.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// stageDecision: record reviewer decision (Approved|Rejected|Pending|Needs Revision)
		$valid_decisions = array( 'Approved', 'Rejected', 'Pending', 'Needs Revision' );
		if ( isset( $body['stageDecision'] ) && is_array( $body['stageDecision'] ) ) {
			$sd = $body['stageDecision'];
			$stage_name = isset( $sd['stageName'] ) ? trim( (string) $sd['stageName'] ) : '';
			$reviewer_email = isset( $sd['reviewerEmail'] ) ? trim( (string) $sd['reviewerEmail'] ) : '';
			$decision = isset( $sd['decision'] ) ? trim( (string) $sd['decision'] ) : '';
			if ( ! $stage_name || ! $reviewer_email || ! in_array( $decision, $valid_decisions, true ) ) {
				return new WP_REST_Response( array( 'error' => 'stageDecision must include stageName, reviewerEmail, and decision (Approved|Rejected|Pending|Needs Revision).' ), 400 );
			}
			$review_stages = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
			if ( empty( $review_stages ) && ! empty( $sub['assignedReviewers'] ) ) {
				$review_stages = array( array( 'stageName' => 'Review', 'stageIndex' => 1, 'reviewers' => $sub['assignedReviewers'], 'decisions' => array(), 'feedback' => array(), 'revisionSubmittedAt' => null ) );
			}
			$stage_index = -1;
			foreach ( $review_stages as $si => $rs ) {
				if ( strtolower( (string) ( $rs['stageName'] ?? '' ) ) === strtolower( $stage_name ) ) {
					$stage_index = $si;
					break;
				}
			}
			if ( $stage_index < 0 ) {
				return new WP_REST_Response( array( 'error' => 'Stage not found or no reviewers assigned for this stage.' ), 400 );
			}
			$stage = &$review_stages[ $stage_index ];
			$reviewer_in_stage = false;
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === strtolower( $reviewer_email ) ) {
					$reviewer_in_stage = true;
					break;
				}
			}
			if ( ! $reviewer_in_stage ) {
				return new WP_REST_Response( array( 'error' => 'Reviewer not assigned to this stage.' ), 403 );
			}
			$previous = array_slice( $review_stages, 0, $stage_index );
			$is_stage_approved = function ( $s ) {
				$revs = $s['reviewers'] ?? array();
				$decs = $s['decisions'] ?? array();
				if ( empty( $revs ) ) return false;
				foreach ( $revs as $r ) {
					$ek = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
					if ( strtolower( (string) ( $decs[ $ek ] ?? '' ) ) !== 'approved' ) return false;
				}
				return true;
			};
			$all_previous_approved = true;
			foreach ( $previous as $p ) {
				if ( ! $is_stage_approved( $p ) ) { $all_previous_approved = false; break; }
			}
			if ( ! $all_previous_approved && $decision !== 'Pending' ) {
				return new WP_REST_Response( array( 'error' => 'All previous stages must be approved (every reviewer must record Approved) before you can record a decision for this stage.' ), 400 );
			}
			$decisions = $stage['decisions'] ?? array();
			$email_key = strtolower( trim( $reviewer_email ) );
			if ( $email_key ) $decisions[ $email_key ] = $decision;
			$stage['decisions'] = $decisions;
			if ( ! isset( $stage['feedback'] ) || ! is_array( $stage['feedback'] ) ) $stage['feedback'] = array();
			if ( ! array_key_exists( 'revisionSubmittedAt', $stage ) ) $stage['revisionSubmittedAt'] = null;
			$new_status = $sub['status'];
			if ( $decision === 'Rejected' ) {
				$new_status = 'Rejected';
			} elseif ( $decision === 'Needs Revision' ) {
				$new_status = 'Revision Required';
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $review_stages, 'status' => $new_status ) );
			$data['submissions'][ $idx ]['status'] = Portal_Data::derive_submission_status( $data['submissions'][ $idx ] );
			self::append_audit_log( $data, $idx, 'decision_recorded', 'Decision "' . $decision . '" recorded for stage "' . $stage_name . '" by ' . $reviewer_email . '.' );
			Portal_Data::write_submissions( $data );
			$updated = $data['submissions'][ $idx ];
			// Auto-advance: notify next-stage reviewers when this stage is now fully approved
			if ( Portal_Data::is_stage_approved( $review_stages[ $stage_index ] ) ) {
				$next_index = $stage_index + 1;
				if ( isset( $review_stages[ $next_index ] ) ) {
					self::notify_stage_reviewers( $updated, $review_stages[ $next_index ] );
				}
			}
			if ( isset( $body['stageFeedback'] ) && is_array( $body['stageFeedback'] ) && ( $decision === 'Needs Revision' || $decision === 'Rejected' ) ) {
				$sf = $body['stageFeedback'];
				$fn = $sf['stageName'] ?? ''; $role = $sf['role'] ?? ''; $fe = $sf['email'] ?? ''; $name = $sf['name'] ?? ''; $message = isset( $sf['message'] ) ? trim( (string) $sf['message'] ) : '';
				if ( $fn && $role && $fe && $message !== '' ) {
					$stages = $updated['reviewStages'] ?? array();
					foreach ( $stages as $sti => $st ) {
						if ( strtolower( (string) ( $st['stageName'] ?? '' ) ) === strtolower( $fn ) ) {
							if ( ! is_array( $st['feedback'] ) ) $st['feedback'] = array();
							$st['feedback'][] = array(
								'role' => trim( $role ),
								'email' => trim( (string) $fe ),
								'name' => ( $name !== '' ? trim( (string) $name ) : 'Reviewer' ),
								'message' => $message,
								'createdAt' => gmdate( 'c' ),
							);
							$stages[ $sti ] = $st;
							$data['submissions'][ $idx ] = array_merge( $updated, array( 'reviewStages' => $stages ) );
							self::append_audit_log( $data, $idx, 'feedback_added', 'Reviewer feedback added for stage "' . $fn . '".' );
							Portal_Data::write_submissions( $data );
							$updated = $data['submissions'][ $idx ];
							break;
						}
					}
				}
			}
			return new WP_REST_Response( $updated, 200 );
		}

		// stageFeedback (standalone): reviewer or submitter message for a stage
		if ( isset( $body['stageFeedback'] ) && is_array( $body['stageFeedback'] ) ) {
			$sf = $body['stageFeedback'];
			$stage_name = $sf['stageName'] ?? ''; $role = $sf['role'] ?? ''; $fe = $sf['email'] ?? ''; $name = $sf['name'] ?? ''; $message = isset( $sf['message'] ) ? trim( (string) $sf['message'] ) : '';
			if ( ! $stage_name || ! $fe || ( $role !== 'reviewer' && $role !== 'submitter' ) || $message === '' ) {
				return new WP_REST_Response( array( 'error' => 'stageFeedback must include stageName, role (reviewer|submitter), email, and message.' ), 400 );
			}
			$review_stages = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
			if ( empty( $review_stages ) && ! empty( $sub['assignedReviewers'] ) ) {
				$review_stages = array( array( 'stageName' => 'Review', 'stageIndex' => 1, 'reviewers' => $sub['assignedReviewers'], 'decisions' => array(), 'feedback' => array(), 'revisionSubmittedAt' => null ) );
			}
			$found = false;
			foreach ( $review_stages as $si => $rs ) {
				if ( strtolower( (string) ( $rs['stageName'] ?? '' ) ) === strtolower( $stage_name ) ) {
					if ( ! is_array( $review_stages[ $si ]['feedback'] ) ) $review_stages[ $si ]['feedback'] = array();
					$review_stages[ $si ]['feedback'][] = array(
						'role' => trim( $role ),
						'email' => trim( (string) $fe ),
						'name' => $name !== '' ? trim( (string) $name ) : ( $role === 'submitter' ? 'Submitter' : 'Reviewer' ),
						'message' => $message,
						'createdAt' => gmdate( 'c' ),
					);
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				return new WP_REST_Response( array( 'error' => 'Stage not found.' ), 400 );
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $review_stages ) );
			self::append_audit_log( $data, $idx, 'feedback_added', ucfirst( (string) $role ) . ' feedback added for stage "' . $stage_name . '".' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// stageRevisionSubmitted: submitter marks revision submitted for a stage
		if ( isset( $body['stageRevisionSubmitted'] ) && is_array( $body['stageRevisionSubmitted'] ) ) {
			$srs = $body['stageRevisionSubmitted'];
			$stage_name = $srs['stageName'] ?? '';
			if ( ! $stage_name ) {
				return new WP_REST_Response( array( 'error' => 'stageRevisionSubmitted must include stageName.' ), 400 );
			}
			$review_stages = isset( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ? $sub['reviewStages'] : array();
			if ( empty( $review_stages ) && ! empty( $sub['assignedReviewers'] ) ) {
				$review_stages = array( array( 'stageName' => 'Review', 'stageIndex' => 1, 'reviewers' => $sub['assignedReviewers'], 'decisions' => array(), 'feedback' => array(), 'revisionSubmittedAt' => null ) );
			}
			// Clear all stage decisions and feedback so the workflow restarts from stage 0
			foreach ( $review_stages as $si => $rs ) {
				$review_stages[ $si ]['decisions']          = array();
				$review_stages[ $si ]['feedback']           = array();
				$review_stages[ $si ]['revisionSubmittedAt'] = null;
				if ( isset( $review_stages[ $si ]['skipped'] ) ) {
					$review_stages[ $si ]['skipped'] = false;
				}
			}
			// Stamp the revision time on the first stage (Chair Review) so it shows it was re-submitted
			if ( isset( $review_stages[0] ) ) {
				$review_stages[0]['revisionSubmittedAt'] = gmdate( 'c' );
			}
			$revision_count = (int) ( $sub['revisionCount'] ?? 0 ) + 1;
			$data['submissions'][ $idx ] = array_merge( $sub, array(
				'reviewStages'  => $review_stages,
				'status'        => 'Revision Submitted',
				'revisionCount' => $revision_count,
			) );
			self::append_audit_log( $data, $idx, 'revision_submitted', 'Revision submitted (round ' . $revision_count . ').' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// Submitter revision: update title, abstract, keywords, researchArea when revisable
		$revisable_statuses = array( 'Revision Required', 'Rejected', 'Revision Submitted' );
		$has_needs_revision = false;
		if ( ! empty( $sub['reviewStages'] ) ) {
			foreach ( $sub['reviewStages'] as $rs ) {
				foreach ( array_values( $rs['decisions'] ?? array() ) as $d ) {
					if ( strtolower( (string) $d ) === 'needs revision' ) { $has_needs_revision = true; break 2; }
				}
			}
		}
		$has_reviewer_feedback = false;
		if ( ! empty( $sub['reviewStages'] ) ) {
			foreach ( $sub['reviewStages'] as $rs ) {
				if ( ! empty( $rs['feedback'] ) ) { $has_reviewer_feedback = true; break; }
			}
		}
		$can_revise = in_array( $sub['status'] ?? '', $revisable_statuses, true ) || $has_needs_revision || $has_reviewer_feedback;
		if ( $can_revise && ( array_key_exists( 'title', $body ) || array_key_exists( 'abstract', $body ) || array_key_exists( 'keywords', $body ) || array_key_exists( 'researchArea', $body ) ) ) {
			$updated = $sub;
			if ( array_key_exists( 'title', $body ) ) $updated['title'] = trim( (string) $body['title'] ) ?: ( $sub['title'] ?? '' );
			if ( array_key_exists( 'abstract', $body ) ) $updated['abstract'] = trim( (string) $body['abstract'] ) ?: ( $sub['abstract'] ?? '' );
			if ( array_key_exists( 'keywords', $body ) ) $updated['keywords'] = trim( (string) $body['keywords'] ) !== '' ? trim( (string) $body['keywords'] ) : ( $sub['keywords'] ?? '' );
			if ( array_key_exists( 'researchArea', $body ) ) $updated['researchArea'] = trim( (string) $body['researchArea'] ) !== '' ? trim( (string) $body['researchArea'] ) : ( $sub['researchArea'] ?? '' );
			$data['submissions'][ $idx ] = $updated;
			self::append_audit_log( $data, $idx, 'content_updated', 'Submission content revised.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		return new WP_REST_Response( array( 'error' => 'Invalid update.' ), 400 );
	}

	private static function find_by_email( $arr, $email ) {
		$email = strtolower( trim( $email ) );
		foreach ( $arr as $a ) {
			if ( strtolower( trim( (string) ( $a['email'] ?? '' ) ) ) === $email ) {
				return true;
			}
		}
		return false;
	}

	public static function feedback( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: $request->get_body_params();
		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$idx = $i;
				break;
			}
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];
		if ( ! in_array( $sub['status'] ?? '', Portal_Data::PUBLIC_STATUSES, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Feedback is only accepted for published or accepted items.' ), 403 );
		}
		$name    = isset( $body['name'] ) ? trim( (string) $body['name'] ) : '';
		$email   = isset( $body['email'] ) ? trim( (string) $body['email'] ) : '';
		$comment = isset( $body['comment'] ) ? trim( (string) $body['comment'] ) : '';
		if ( ! $name || ! $email || ! $comment ) {
			return new WP_REST_Response( array( 'error' => 'Name, email, and comment are required.' ), 400 );
		}
		$feedback = array(
			'name'    => $name,
			'email'   => $email,
			'comment' => $comment,
			'at'      => gmdate( 'c' ),
		);
		$public_feedback = isset( $sub['publicFeedback'] ) && is_array( $sub['publicFeedback'] ) ? $sub['publicFeedback'] : array();
		$public_feedback[] = $feedback;
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'publicFeedback' => $public_feedback ) );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array( 'success' => true ), 201 );
	}

	public static function comments( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: $request->get_body_params();
		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$idx = $i;
				break;
			}
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub   = $data['submissions'][ $idx ];
		$stage = isset( $body['stage'] ) ? trim( (string) $body['stage'] ) : '';
		$text  = isset( $body['text'] ) ? trim( (string) $body['text'] ) : '';
		$by    = isset( $body['by'] ) ? trim( (string) $body['by'] ) : 'Internal';
		if ( ! $stage || ! $text ) {
			return new WP_REST_Response( array( 'error' => 'Stage and text are required.' ), 400 );
		}
		$internal = isset( $sub['internalComments'] ) && is_array( $sub['internalComments'] ) ? $sub['internalComments'] : array();
		$internal[] = array( 'stage' => $stage, 'text' => $text, 'by' => $by ?: 'Internal', 'at' => gmdate( 'c' ) );
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'internalComments' => $internal ) );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
	}

	/**
	 * Validate an uploaded temp file: confirms real MIME type matches extension and scans with ClamAV if available.
	 *
	 * @param string $tmp Temp path of the uploaded file.
	 * @param string $ext Lowercase extension ('pdf' or 'docx').
	 * @return bool True if file is safe and valid.
	 */
	private static function validate_upload_file( string $tmp, string $ext ): bool {
		$allowed_mimes = array(
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		// Verify actual MIME type matches declared extension
		if ( function_exists( 'finfo_open' ) && isset( $allowed_mimes[ $ext ] ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected = (string) finfo_file( $finfo, $tmp );
				finfo_close( $finfo );
				if ( $detected !== $allowed_mimes[ $ext ] ) {
					return false;
				}
			}
		}
		// ClamAV virus scan (only if clamscan binary is present)
		$clamscan = '';
		if ( function_exists( 'shell_exec' ) ) {
			$clamscan = trim( (string) ( shell_exec( 'which clamscan 2>/dev/null' ) ?? '' ) );
		}
		if ( $clamscan ) {
			$exit_code = 0;
			exec( escapeshellarg( $clamscan ) . ' --no-summary --stdout ' . escapeshellarg( $tmp ) . ' 2>&1', $scan_lines, $exit_code );
			if ( $exit_code !== 0 ) {
				return false;
			}
		}
		return true;
	}

	public static function attachments_upload( WP_REST_Request $request ) {
		$id = $request['id'];
		$data = Portal_Data::read_submissions();
		$idx = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$files = $request->get_file_params();
		if ( empty( $files ) || empty( $files['files'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No files uploaded. Use multipart/form-data with field "files".' ), 400 );
		}
		$upload = $files['files'];
		$max_size = 2 * 1024 * 1024; // 2MB
		$max_files = 5;
		$sub = $data['submissions'][ $idx ];
		$attachments = isset( $sub['attachments'] ) && is_array( $sub['attachments'] ) ? $sub['attachments'] : array();
		if ( count( $attachments ) >= $max_files ) {
			return new WP_REST_Response( array( 'error' => 'Max 5 files per submission.' ), 400 );
		}
		// Determine which revision round this upload belongs to.
		// Round 0 = original submission. Round N = Nth revision.
		$revision_statuses = array( 'Revision Required', 'Rejected', 'Revision Submitted' );
		$is_revision_upload = in_array( $sub['status'] ?? '', $revision_statuses, true );
		$revision_round = (int) ( $sub['revisionCount'] ?? 0 ) + ( $is_revision_upload ? 1 : 0 );
		$uploaded_at = gmdate( 'c' );
		$is_multi = isset( $upload['name'] ) && is_array( $upload['name'] );
		$new_attachments = array();
		if ( $is_multi ) {
			$n = count( $upload['name'] );
			for ( $i = 0; $i < $n && count( $attachments ) + count( $new_attachments ) < $max_files; $i++ ) {
				$tmp = $upload['tmp_name'][ $i ] ?? '';
				$err = $upload['error'][ $i ] ?? UPLOAD_ERR_NO_FILE;
				$size = (int) ( $upload['size'][ $i ] ?? 0 );
				$orig = $upload['name'][ $i ] ?? 'file';
				if ( $err !== UPLOAD_ERR_OK || ! is_uploaded_file( $tmp ) || $size > $max_size ) continue;
				$base = Portal_Data::sanitize_filename( pathinfo( $orig, PATHINFO_FILENAME ) );
				$ext = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
				$allowed_exts = array( 'pdf', 'docx' );
				if ( ! $ext || ! in_array( $ext, $allowed_exts, true ) ) continue;
				if ( ! self::validate_upload_file( $tmp, $ext ) ) continue;
				$ext = '.' . $ext;
				$filename = $base ? $base . $ext : 'file-' . time() . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$dest = $dir . $filename;
				if ( move_uploaded_file( $tmp, $dest ) ) {
					$cu   = wp_get_current_user();
					$is_rev = current_user_can( 'rrp_review_submissions' ) && ! current_user_can( 'rrp_edit_any_submission' ) && ! current_user_can( 'rrp_full_admin_access' );
					$entry = array( 'name' => $orig, 'filename' => $filename, 'size' => $size, 'uploadedAt' => $uploaded_at, 'revisionRound' => $revision_round );
					if ( $is_rev ) {
						$entry['uploadedByReviewer'] = true;
						$entry['reviewerEmail']      = strtolower( trim( (string) $cu->user_email ) );
						$entry['reviewerName']       = $cu->display_name ?: $cu->user_login;
					}
					$new_attachments[] = $entry;
				}
			}
		} else {
			$tmp = $upload['tmp_name'] ?? '';
			$err = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
			$size = (int) ( $upload['size'] ?? 0 );
			$orig = $upload['name'] ?? 'file';
			if ( $err === UPLOAD_ERR_OK && is_uploaded_file( $tmp ) && $size <= $max_size ) {
				$base = Portal_Data::sanitize_filename( pathinfo( $orig, PATHINFO_FILENAME ) );
				$ext = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
				$allowed_exts = array( 'pdf', 'docx' );
				if ( ! $ext || ! in_array( $ext, $allowed_exts, true ) || ! self::validate_upload_file( $tmp, $ext ) ) {
					return new WP_REST_Response( array( 'error' => 'Only PDF and DOCX files are accepted.' ), 400 );
				}
				$ext = '.' . $ext;
				$filename = $base ? $base . $ext : 'file-' . time() . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$dest = $dir . $filename;
				if ( move_uploaded_file( $tmp, $dest ) ) {
					$cu   = wp_get_current_user();
					$is_rev = current_user_can( 'rrp_review_submissions' ) && ! current_user_can( 'rrp_edit_any_submission' ) && ! current_user_can( 'rrp_full_admin_access' );
					$entry = array( 'name' => $orig, 'filename' => $filename, 'size' => $size, 'uploadedAt' => $uploaded_at, 'revisionRound' => $revision_round );
					if ( $is_rev ) {
						$entry['uploadedByReviewer'] = true;
						$entry['reviewerEmail']      = strtolower( trim( (string) $cu->user_email ) );
						$entry['reviewerName']       = $cu->display_name ?: $cu->user_login;
					}
					$new_attachments[] = $entry;
				}
			}
		}
		if ( empty( $new_attachments ) ) {
			return new WP_REST_Response( array( 'error' => 'Upload failed. Max 5 files, 2MB each.' ), 400 );
		}
		$attachments = array_merge( $attachments, $new_attachments );
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'attachments' => $attachments ) );
		foreach ( $new_attachments as $_na ) {
			$_fn    = (string) ( $_na['name'] ?? $_na['filename'] ?? 'file' );
			$_label = ! empty( $_na['uploadedByReviewer'] ) ? ' (reviewer annotation)' : '';
			self::append_audit_log( $data, $idx, 'file_uploaded', 'File uploaded: "' . $_fn . '"' . $_label . '.' );
		}
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
	}

	public static function attachments_download( WP_REST_Request $request ) {
		$id = $request['id'];
		$filename = $request['filename'];
		$data = Portal_Data::read_submissions();
		$sub = null;
		foreach ( $data['submissions'] as $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $sub = $s; break; }
		}
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$attachments = $sub['attachments'] ?? array();
		$att = null;
		foreach ( $attachments as $a ) {
			if ( ( $a['filename'] ?? '' ) === $filename || rawurlencode( $a['filename'] ?? '' ) === $filename ) {
				$att = $a;
				break;
			}
		}
		if ( ! $att ) {
			return new WP_REST_Response( array( 'error' => 'Attachment not found.' ), 404 );
		}
		$file_path = RRP_UPLOADS_DIR . $id . '/' . ( $att['filename'] ?? '' );
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_REST_Response( array( 'error' => 'File not found on server.' ), 404 );
		}
		$name = ( $att['name'] ?? $att['filename'] ?? 'download' );
		$name = str_replace( '"', '%22', $name );
		$ext      = strtolower( (string) pathinfo( $att['filename'] ?? '', PATHINFO_EXTENSION ) );
		$mime_map = array(
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		$mime   = $mime_map[ $ext ] ?? 'application/octet-stream';
		$inline = $request->get_param( 'inline' ) ? true : false;
		$disp   = $inline ? 'inline' : 'attachment';
		// Stream file bytes directly — bypasses WP REST JSON serialisation so binary content is served correctly.
		if ( ob_get_level() ) { ob_end_clean(); }
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . $disp . '; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}

	public static function assignment_summary( WP_REST_Request $request ) {
		$data = Portal_Data::read_submissions();
		$list = array();
		foreach ( $data['submissions'] as $s ) {
			$stages = array();
			foreach ( $s['reviewStages'] ?? array() as $rs ) {
				$reviewers = array();
				foreach ( $rs['reviewers'] ?? array() as $r ) {
					$reviewers[] = array( 'name' => $r['name'] ?? $r['email'] ?? '', 'email' => $r['email'] ?? '' );
				}
				$raw_decs = $rs['decisions'] ?? array();
				$stages[] = array(
					'stageName' => $rs['stageName'] ?? '',
					'reviewers' => $reviewers,
					'decisions' => ! empty( $raw_decs ) ? (object) $raw_decs : new \stdClass(),
					'skipped'   => ! empty( $rs['skipped'] ),
				);
			}
			$list[] = array(
				'id'           => $s['id'] ?? '',
				'type'         => $s['type'] ?? '',
				'title'        => $s['title'] ?? '—',
				'status'       => $s['status'] ?? '',
				'reviewStages' => $stages,
			);
		}
		return new WP_REST_Response( array( 'submissions' => $list ), 200 );
	}

	public static function config_suggest_reviewers( WP_REST_Request $request ) {
		$type = strtolower( trim( (string) ( $request->get_param( 'type' ) ?? '' ) ) );
		$count = (int) $request->get_param( 'count' );
		$count = max( 0, min( 20, $count ) );
		$mode = strtolower( trim( (string) ( $request->get_param( 'mode' ) ?? '' ) ) );
		$exclude_ids = array_filter( array_map( 'trim', explode( ',', (string) ( $request->get_param( 'excludeIds' ) ?? '' ) ) ) );
		$advance = in_array( $request->get_param( 'advanceRoundRobin' ), array( '1', 'true' ), true );
		$allowed = array( 'conference', 'publication', 'student-project', 'grant' );
		if ( ! $type || ! in_array( $type, $allowed, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid type.' ), 400 );
		}
		$config = Portal_Data::read_config();
		if ( $mode === 'random' || $mode === 'round_robin' ) {
			if ( ! isset( $config['reviewerPools'][ $type ] ) ) {
				$config['reviewerPools'][ $type ] = array( 'reviewerIds' => array(), 'assignmentMode' => 'random' );
			}
			$config['reviewerPools'][ $type ]['assignmentMode'] = $mode;
		}
		$selected = Portal_Data::select_from_pool( $config, $type, $count, $exclude_ids, $advance );
		if ( empty( $selected ) ) {
			return new WP_REST_Response( array( 'reviewerIds' => array(), 'message' => 'No reviewers in pool or all excluded.' ), 200 );
		}
		return new WP_REST_Response( array( 'reviewerIds' => $selected ), 200 );
	}

	public static function config_apply_pool( WP_REST_Request $request ) {
		$data = Portal_Data::read_submissions();
		$stage_aliases = array(
			'conference'      => array( 'peer review' => array( 'review' ) ),
			'publication'     => array( 'expert review' => array( 'review' ) ),
			'student-project' => array( 'advisor consultation' => array( 'review' ) ),
			'grant'           => array( 'multi-criteria review' => array( 'review' ) ),
		);
		$applied = 0;
		$skipped = 0;
		foreach ( $data['submissions'] as $i => $s ) {
			$type = strtolower( (string) ( $s['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'conference', 'publication', 'student-project', 'grant' ), true ) ) continue;
			$fresh = Portal_Data::auto_assign_submission( $s, $s['submitterEmail'] ?? '' );
			$existing = isset( $s['reviewStages'] ) && is_array( $s['reviewStages'] ) ? $s['reviewStages'] : array();
			$existing_by_stage = array();
			foreach ( $existing as $rs ) {
				$name = trim( (string) ( $rs['stageName'] ?? '' ) );
				if ( $name && ! empty( $rs['reviewers'] ) ) {
					$existing_by_stage[ strtolower( $name ) ] = $rs;
				}
			}
			$aliases = isset( $stage_aliases[ $type ] ) ? $stage_aliases[ $type ] : array();
			$merged = array();
			foreach ( $fresh['reviewStages'] ?? array() as $rs ) {
				$name = trim( (string) ( $rs['stageName'] ?? '' ) );
				$key = strtolower( $name );
				$existing_rs = isset( $existing_by_stage[ $key ] ) ? $existing_by_stage[ $key ] : null;
				if ( ! $existing_rs && ! empty( $aliases[ $key ] ) ) {
					foreach ( $aliases[ $key ] as $alt ) {
						if ( ! empty( $existing_by_stage[ $alt ] ) ) {
							$existing_rs = $existing_by_stage[ $alt ];
							break;
						}
					}
				}
				$merged[] = $existing_rs ? $existing_rs : $rs;
			}
			$assigned = array();
			foreach ( $merged as $rs ) {
				foreach ( $rs['reviewers'] ?? array() as $r ) {
					$em = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
					if ( $em && ! self::find_by_email( $assigned, $em ) ) {
						$assigned[] = array( 'id' => $r['id'] ?? null, 'name' => $r['name'] ?? '', 'email' => $r['email'] ?? '' );
					}
				}
			}
			$had_no_stages = empty( $existing ) || empty( array_filter( $existing, function ( $rs ) { return ! empty( $rs['reviewers'] ); } ) );
			$now_has_all = ! empty( $merged );
			if ( $now_has_all ) $applied++;
			if ( $had_no_stages && ! $now_has_all ) $skipped++;
			$data['submissions'][ $i ] = array_merge( $s, array( 'reviewStages' => $merged, 'assignedReviewers' => $assigned ) );
		}
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array( 'success' => true, 'applied' => $applied, 'skipped' => $skipped, 'total' => count( $data['submissions'] ) ), 200 );
	}

	public static function config_get( WP_REST_Request $request ) {
		return new WP_REST_Response( Portal_Data::read_config(), 200 );
	}

	public static function config_put( WP_REST_Request $request ) {
		$body   = $request->get_json_params() ?: array();
		$config = Portal_Data::read_config();
		if ( isset( $body['stageRequirements'] ) && is_array( $body['stageRequirements'] ) ) {
			$config['stageRequirements'] = $body['stageRequirements'];
		}
		if ( isset( $body['reviewerPools'] ) && is_array( $body['reviewerPools'] ) ) {
			$config['reviewerPools'] = $body['reviewerPools'];
		}
		if ( isset( $body['poolCohorts'] ) && is_array( $body['poolCohorts'] ) ) {
			$config['poolCohorts'] = $body['poolCohorts'];
		}
		if ( array_key_exists( 'activeCohort', $body ) ) {
			$config['activeCohort'] = $body['activeCohort'];
		}
		if ( isset( $body['defaultReviewersByStage'] ) && is_array( $body['defaultReviewersByStage'] ) ) {
			$config['defaultReviewersByStage'] = $body['defaultReviewersByStage'];
		}
		if ( isset( $body['programs'] ) && is_array( $body['programs'] ) ) {
			$config['programs'] = $body['programs'];
		}
		if ( array_key_exists( 'defaultProgramDirectorId', $body ) ) {
			$config['defaultProgramDirectorId'] = sanitize_text_field( (string) $body['defaultProgramDirectorId'] );
		}
		if ( array_key_exists( 'dissertationDirectorId', $body ) ) {
			$config['dissertationDirectorId'] = sanitize_text_field( (string) $body['dissertationDirectorId'] );
		}
		if ( isset( $body['departments'] ) && is_array( $body['departments'] ) ) {
			$config['departments'] = $body['departments'];
		}
		if ( isset( $body['submissionTypes'] ) && is_array( $body['submissionTypes'] ) ) {
			$config['submissionTypes'] = $body['submissionTypes'];
		}
		Portal_Data::write_config( $config );
		return new WP_REST_Response( $config, 200 );
	}

	public static function submission_types_get( WP_REST_Request $request ) {
		$config = Portal_Data::read_config();
		return new WP_REST_Response( array( 'submissionTypes' => $config['submissionTypes'] ?? array() ), 200 );
	}

	public static function submission_types_put( WP_REST_Request $request ) {
		$body  = $request->get_json_params() ?: array();
		$types = isset( $body['submissionTypes'] ) && is_array( $body['submissionTypes'] ) ? $body['submissionTypes'] : array();
		$sanitized = array();
		foreach ( $types as $t ) {
			if ( empty( $t['id'] ) || empty( $t['label'] ) ) continue;
			$sanitized[] = array(
				'id'          => sanitize_key( (string) $t['id'] ),
				'label'       => sanitize_text_field( (string) $t['label'] ),
				'description' => sanitize_text_field( (string) ( $t['description'] ?? '' ) ),
				'stages'      => array_values( array_map( 'sanitize_text_field', (array) ( $t['stages'] ?? array() ) ) ),
			);
		}
		$config = Portal_Data::read_config();
		$config['submissionTypes'] = $sanitized;
		Portal_Data::write_config( $config );
		return new WP_REST_Response( array( 'submissionTypes' => $sanitized ), 200 );
	}

	public static function submission_type_patch( WP_REST_Request $request ) {
		$id   = sanitize_key( (string) $request->get_param( 'id' ) );
		$body = $request->get_json_params() ?: array();
		$config = Portal_Data::read_config();
		$types  = $config['submissionTypes'] ?? array();
		$found  = false;
		foreach ( $types as &$t ) {
			if ( $t['id'] === $id ) {
				$found = true;
				if ( isset( $body['label'] ) )       $t['label']       = sanitize_text_field( (string) $body['label'] );
				if ( isset( $body['description'] ) ) $t['description'] = sanitize_text_field( (string) $body['description'] );
				if ( isset( $body['stages'] ) && is_array( $body['stages'] ) ) {
					$t['stages'] = array_values( array_map( 'sanitize_text_field', $body['stages'] ) );
				}
				break;
			}
		}
		unset( $t );
		if ( ! $found ) {
			// Create new type if it doesn't exist
			$types[] = array(
				'id'          => $id,
				'label'       => sanitize_text_field( (string) ( $body['label'] ?? $id ) ),
				'description' => sanitize_text_field( (string) ( $body['description'] ?? '' ) ),
				'stages'      => array_values( array_map( 'sanitize_text_field', (array) ( $body['stages'] ?? array() ) ) ),
			);
		}
		$config['submissionTypes'] = $types;
		Portal_Data::write_config( $config );
		return new WP_REST_Response( array( 'submissionTypes' => $types ), 200 );
	}

	public static function submission_type_delete( WP_REST_Request $request ) {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$config = Portal_Data::read_config();
		$types  = array_values( array_filter( $config['submissionTypes'] ?? array(), function( $t ) use ( $id ) {
			return $t['id'] !== $id;
		} ) );
		$config['submissionTypes'] = $types;
		Portal_Data::write_config( $config );
		return new WP_REST_Response( array( 'submissionTypes' => $types ), 200 );
	}

	// ── Workflow Stages library ───────────────────────────────────────────────

	public static function workflow_stages_get( WP_REST_Request $request ) {
		$config = Portal_Data::read_config();
		$stages = $config['workflowStages'] ?? array();
		// Auto-seed from existing submission type stage names if library is empty
		if ( empty( $stages ) ) {
			$seen      = array();
			$req_all   = $config['stageRequirements'] ?? array();
			foreach ( ( $config['submissionTypes'] ?? array() ) as $t ) {
				foreach ( ( $t['stages'] ?? array() ) as $sname ) {
					if ( isset( $seen[ $sname ] ) ) continue;
					$seen[ $sname ] = true;
					$multi = false;
					foreach ( $req_all as $req_map ) {
						if ( isset( $req_map[ $sname ] ) && ( $req_map[ $sname ]['requiredCount'] ?? 1 ) > 1 ) {
							$multi = true;
							break;
						}
					}
					$stages[] = array(
						'id'         => sanitize_key( $sname ),
						'name'       => $sname,
						'singleUser' => ! $multi,
						'multiUser'  => $multi,
					);
				}
			}
			if ( ! empty( $stages ) ) {
				$config['workflowStages'] = $stages;
				Portal_Data::write_config( $config );
			}
		}
		return new WP_REST_Response( array( 'workflowStages' => $stages ), 200 );
	}

	public static function workflow_stage_patch( WP_REST_Request $request ) {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$body   = $request->get_json_params() ?: array();
		$config = Portal_Data::read_config();
		$stages = $config['workflowStages'] ?? array();
		$found  = false;
		foreach ( $stages as &$s ) {
			if ( $s['id'] === $id ) {
				$found = true;
				if ( isset( $body['name'] ) )       $s['name']       = sanitize_text_field( (string) $body['name'] );
				if ( isset( $body['singleUser'] ) )  $s['singleUser'] = (bool) $body['singleUser'];
				if ( isset( $body['multiUser'] ) )   $s['multiUser']  = (bool) $body['multiUser'];
				break;
			}
		}
		unset( $s );
		if ( ! $found ) {
			$stages[] = array(
				'id'         => $id,
				'name'       => sanitize_text_field( (string) ( $body['name'] ?? $id ) ),
				'singleUser' => (bool) ( $body['singleUser'] ?? true ),
				'multiUser'  => (bool) ( $body['multiUser']  ?? false ),
			);
		}
		$config['workflowStages'] = $stages;
		Portal_Data::write_config( $config );
		return new WP_REST_Response( array( 'workflowStages' => $stages ), 200 );
	}

	public static function workflow_stage_delete( WP_REST_Request $request ) {
		$id     = sanitize_key( (string) $request->get_param( 'id' ) );
		$config = Portal_Data::read_config();
		$stages = array_values( array_filter( $config['workflowStages'] ?? array(), function( $s ) use ( $id ) {
			return $s['id'] !== $id;
		} ) );
		$config['workflowStages'] = $stages;
		Portal_Data::write_config( $config );
		return new WP_REST_Response( array( 'workflowStages' => $stages ), 200 );
	}

	public static function reviewers( WP_REST_Request $request ) {
		$submission_type = $request->get_param( 'submissionType' ) ? strtolower( trim( (string) $request->get_param( 'submissionType' ) ) ) : '';
		$list = Portal_Data::read_reviewers();
		if ( $submission_type ) {
			$list = array_filter( $list, function ( $r ) use ( $submission_type ) {
				$types = $r['submissionTypes'] ?? array();
				if ( empty( $types ) || ! is_array( $types ) ) {
					return true;
				}
				foreach ( $types as $t ) {
					if ( strtolower( (string) $t ) === $submission_type ) {
						return true;
					}
				}
				return false;
			} );
			$list = array_values( $list );
		}
		return new WP_REST_Response( array( 'reviewers' => $list ), 200 );
	}

	public static function reviews( WP_REST_Request $request ) {
		$email = $request->get_param( 'reviewerEmail' ) ? strtolower( trim( (string) $request->get_param( 'reviewerEmail' ) ) ) : '';
		if ( ! $email ) {
			return new WP_REST_Response( array( 'submissions' => array() ), 200 );
		}
		$data = Portal_Data::read_submissions();
		$list = array();
		foreach ( $data['submissions'] as $s ) {
			$in_assigned = false;
			foreach ( $s['assignedReviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
					$in_assigned = true;
					break;
				}
			}
			if ( ! $in_assigned && ! empty( $s['reviewStages'] ) ) {
				foreach ( $s['reviewStages'] as $rs ) {
					foreach ( $rs['reviewers'] ?? array() as $r ) {
						if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
							$in_assigned = true;
							break 2;
						}
					}
				}
			}
			if ( $in_assigned ) {
				$deadline = null;
				foreach ( $s['assignedReviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$deadline = $r['deadline'] ?? null;
						break;
					}
				}
				$list[] = array(
					'id'             => $s['id'] ?? null,
					'type'           => $s['type'] ?? null,
					'submissionType' => $s['submissionType'] ?? null,
					'status'         => $s['status'] ?? null,
					'title'          => $s['title'] ?? null,
					'createdAt'      => $s['createdAt'] ?? null,
					'deadline'       => $deadline,
				);
			}
		}
		return new WP_REST_Response( array( 'submissions' => $list ), 200 );
	}

	public static function skip_stage( WP_REST_Request $request ) {
		$id   = $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		$stage_name = isset( $body['stageName'] ) ? strtolower( trim( (string) $body['stageName'] ) ) : '';

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}

		$sub    = $data['submissions'][ $idx ];
		$stages = $sub['reviewStages'] ?? array();
		$found  = false;
		foreach ( $stages as $si => $stage ) {
			$match = $stage_name === '' || strtolower( (string) ( $stage['stageName'] ?? '' ) ) === $stage_name;
			if ( $match && ! Portal_Data::is_stage_approved( $stage ) && ! ( $stage['skipped'] ?? false ) ) {
				$stages[ $si ]['skipped']   = true;
				$stages[ $si ]['skippedAt'] = gmdate( 'c' );
				$stages[ $si ]['skippedBy'] = wp_get_current_user()->user_email ?? '';
				// Mark all reviewers Approved so derived status can progress
				foreach ( $stages[ $si ]['reviewers'] ?? array() as $r ) {
					$em = strtolower( trim( (string) ( $r['email'] ?? '' ) ) );
					if ( $em ) $stages[ $si ]['decisions'][ $em ] = 'Approved';
				}
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'No pending stage found to skip.' ), 400 );
		}

		$sub['reviewStages'] = $stages;
		$sub['status']       = Portal_Data::derive_submission_status( $sub );
		$data['submissions'][ $idx ] = $sub;
		self::append_audit_log( $data, $idx, 'stage_skipped', 'Stage "' . ( $stages[ $si ]['stageName'] ?? $stage_name ) . '" skipped.' );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( $sub, 200 );
	}

	public static function submission_deadlines( WP_REST_Request $request ) {
		$id  = $request->get_param( 'id' );
		$sub = self::get_submission_by_id( $id );
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$stages    = $sub['reviewStages'] ?? array();
		$deadlines = array();
		foreach ( $stages as $i => $stage ) {
			$deadlines[] = array(
				'stageName'  => $stage['stageName'] ?? '',
				'stageIndex' => $i,
				'deadline'   => Portal_Data::calculate_stage_deadline( $sub, $i ),
				'approved'   => Portal_Data::is_stage_approved( $stage ),
				'skipped'    => $stage['skipped'] ?? false,
			);
		}
		return new WP_REST_Response( array( 'id' => $id, 'deadlines' => $deadlines ), 200 );
	}

	public static function analytics_overdue( WP_REST_Request $request ) {
		return new WP_REST_Response( array( 'overdue' => Portal_Data::get_overdue_submissions() ), 200 );
	}

	/**
	 * Send email notification to all reviewers assigned to a stage.
	 */
	private static function notify_stage_reviewers( $submission, $stage ) {
		$reviewers = $stage['reviewers'] ?? array();
		foreach ( $reviewers as $reviewer ) {
			$email = trim( (string) ( $reviewer['email'] ?? '' ) );
			if ( ! $email || ! is_email( $email ) ) {
				continue;
			}
			$subject = 'Research Review Portal: Review Requested (' . ( $submission['id'] ?? '' ) . ')';
			$message = sprintf(
				"Hello %s,\n\nYou have been assigned to review a submission for stage: %s\n\nTitle: %s\nType: %s\nID: %s\n\nPlease log in to the portal to review the submission.\n\nThank you,\nResearch Review Portal",
				trim( (string) ( $reviewer['name'] ?? $reviewer['email'] ?? '' ) ),
				$stage['stageName'] ?? '',
				$submission['title'] ?? '–',
				$submission['type'] ?? '–',
				$submission['id'] ?? ''
			);
			if ( function_exists( 'wp_mail' ) ) {
				wp_mail( $email, $subject, $message );
			}
		}
	}

	// ─── Portal User Management ───────────────────────────────────────────────

	private static function format_portal_user( $u ) {
		$roles = (array) $u->roles;
		if ( in_array( 'rrp_reviewer', $roles, true ) ) {
			$portal_role = 'Reviewer';
		} elseif ( in_array( 'rrp_faculty', $roles, true ) ) {
			$portal_role = 'Faculty';
		} elseif ( in_array( 'rrp_coordinator', $roles, true ) ) {
			$portal_role = 'Coordinator';
		} elseif ( in_array( 'rrp_admin', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
			$portal_role = 'Admin';
		} else {
			$portal_role = 'Student';
		}
		$wp_role = 'rrp_student';
		foreach ( array( 'rrp_admin', 'administrator', 'rrp_coordinator', 'rrp_faculty', 'rrp_reviewer', 'rrp_student' ) as $r ) {
			if ( in_array( $r, $roles, true ) ) { $wp_role = $r; break; }
		}
		$all_portal_roles = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		$wp_roles = array_values( array_filter( $roles, function( $r ) use ( $all_portal_roles ) {
			return in_array( $r, $all_portal_roles, true );
		} ) );
		if ( in_array( 'administrator', $roles, true ) && ! in_array( 'rrp_admin', $wp_roles, true ) ) {
			$wp_roles[] = 'rrp_admin';
		}

		return array(
			'id'                    => $u->ID,
			'name'                  => $u->display_name,
			'email'                 => $u->user_email,
			'portalRole'            => $portal_role,
			'wpRole'                => $wp_role,
			'wpRoles'               => $wp_roles,
			'degree'                => (string) ( get_user_meta( $u->ID, 'rrp_degree', true ) ?: '' ),
			'allowedTypes'          => (array) ( get_user_meta( $u->ID, 'rrp_allowed_submission_types', true ) ?: array() ),
			'defaultStageReviewers' => (array) ( get_user_meta( $u->ID, 'rrp_default_stage_reviewers', true ) ?: array() ),
			'submissionTypes'       => (array) ( get_user_meta( $u->ID, 'rrp_submission_types', true ) ?: array() ),
			'department'            => (string) ( get_user_meta( $u->ID, 'rrp_department', true ) ?: '' ),
			'expertise'             => (string) ( get_user_meta( $u->ID, 'rrp_expertise', true ) ?: '' ),
			'programIds'            => (array) ( get_user_meta( $u->ID, 'rrp_program_ids', true ) ?: array() ),
			'programId'             => (string) ( get_user_meta( $u->ID, 'rrp_program_id', true ) ?: '' ),
			'registeredAt'          => $u->user_registered,
		);
	}

	public static function portal_users_me( WP_REST_Request $request ) {
		$current = wp_get_current_user();
		if ( ! $current->exists() ) {
			return new WP_REST_Response( array( 'error' => 'Not logged in.' ), 401 );
		}
		return new WP_REST_Response( array( 'user' => self::format_portal_user( $current ) ), 200 );
	}

	public static function portal_users_me_update( WP_REST_Request $request ) {
		$current = wp_get_current_user();
		if ( ! $current->exists() ) {
			return new WP_REST_Response( array( 'error' => 'Not logged in.' ), 401 );
		}
		$id   = (int) $current->ID;
		$body = $request->get_json_params() ?: array();

		$update = array( 'ID' => $id );
		if ( isset( $body['firstName'] ) ) $update['first_name']  = sanitize_text_field( $body['firstName'] );
		if ( isset( $body['lastName'] ) )  $update['last_name']   = sanitize_text_field( $body['lastName'] );
		if ( isset( $body['firstName'] ) || isset( $body['lastName'] ) ) {
			$fn = sanitize_text_field( $body['firstName'] ?? $current->first_name );
			$ln = sanitize_text_field( $body['lastName']  ?? $current->last_name );
			$update['display_name'] = trim( $fn . ' ' . $ln ) ?: $current->display_name;
		}
		if ( ! empty( $body['password'] ) && strlen( (string) $body['password'] ) >= 8 ) {
			$update['user_pass'] = (string) $body['password'];
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_user( $update );
			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
			}
		}
		if ( array_key_exists( 'degree', $body ) )     update_user_meta( $id, 'rrp_degree',     sanitize_text_field( $body['degree'] ) );
		if ( array_key_exists( 'department', $body ) )  update_user_meta( $id, 'rrp_department', sanitize_text_field( $body['department'] ) );
		if ( array_key_exists( 'expertise', $body ) )   update_user_meta( $id, 'rrp_expertise',  sanitize_textarea_field( $body['expertise'] ) );

		$updated = get_userdata( $id );
		return new WP_REST_Response( array( 'user' => self::format_portal_user( $updated ) ), 200 );
	}

	public static function portal_users_list( WP_REST_Request $request ) {
		$role_filter = $request->get_param( 'role' ) ? sanitize_key( (string) $request->get_param( 'role' ) ) : '';
		$roles_to_query = array();
		if ( $role_filter === 'student' ) {
			$roles_to_query = array( 'rrp_student' );
		} elseif ( $role_filter === 'reviewer' ) {
			$roles_to_query = array( 'rrp_reviewer' );
		} elseif ( $role_filter === 'coordinator' ) {
			$roles_to_query = array( 'rrp_coordinator' );
		} elseif ( $role_filter === 'admin' ) {
			$roles_to_query = array( 'rrp_admin' );
		} elseif ( $role_filter === 'faculty' ) {
			$roles_to_query = array( 'rrp_faculty' );
		} else {
			$roles_to_query = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		}
		$seen_ids = array();
		$users    = array();
		foreach ( $roles_to_query as $role ) {
			$wp_users = get_users( array( 'role' => $role, 'number' => -1, 'orderby' => 'display_name' ) );
			foreach ( $wp_users as $u ) {
				if ( ! isset( $seen_ids[ $u->ID ] ) ) {
					$seen_ids[ $u->ID ] = true;
					$users[] = self::format_portal_user( $u );
				}
			}
		}
		// Also include users with no portal role so admins can find and assign them.
		if ( $role_filter === '' ) {
			$no_role_users = get_users( array(
				'role__not_in' => array_merge(
					$roles_to_query,
					array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' )
				),
				'number'  => -1,
				'orderby' => 'display_name',
			) );
			foreach ( $no_role_users as $u ) {
				if ( ! isset( $seen_ids[ $u->ID ] ) ) {
					$seen_ids[ $u->ID ] = true;
					$formatted          = self::format_portal_user( $u );
					$formatted['portalRole'] = 'Pending';
					$users[] = $formatted;
				}
			}
		}
		// Merge legacy students: submitters from submissions.json who don't yet have a WP account.
		if ( $role_filter === 'student' || $role_filter === '' ) {
			$wp_emails  = array_map( function( $u ) { return strtolower( $u['email'] ); }, $users );
			$data       = Portal_Data::read_submissions();
			$subs       = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();
			$seen       = array();
			foreach ( $subs as $sub ) {
				$email = strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) );
				$name  = trim( (string) ( $sub['submitterName'] ?? '' ) );
				if ( ! $email || in_array( $email, $wp_emails, true ) || isset( $seen[ $email ] ) ) {
					continue;
				}
				$seen[ $email ] = true;
				// Collect all submission types for this submitter
				$sub_types = array();
				foreach ( $subs as $s ) {
					if ( strtolower( trim( (string) ( $s['submitterEmail'] ?? '' ) ) ) === $email && ! empty( $s['type'] ) ) {
						$sub_types[] = $s['type'];
					}
				}
				$users[] = array(
					'id'                    => 'legacy-' . md5( $email ),
					'name'                  => $name ?: $email,
					'email'                 => $sub['submitterEmail'],
					'portalRole'            => 'Student',
					'wpRole'                => 'rrp_student',
					'degree'                => '',
					'allowedTypes'          => array_values( array_unique( $sub_types ) ),
					'defaultStageReviewers' => array(),
					'submissionTypes'       => array(),
					'department'            => '',
					'expertise'             => '',
					'registeredAt'          => '',
					'jsonOnly'              => true,
					'jsonStudentEmail'      => $sub['submitterEmail'],
				);
			}
		}
		return new WP_REST_Response( array( 'users' => $users ), 200 );
	}

	public static function portal_users_create( WP_REST_Request $request ) {
		$body       = $request->get_json_params() ?: array();
		$first_name = sanitize_text_field( $body['firstName'] ?? '' );
		$last_name  = sanitize_text_field( $body['lastName'] ?? '' );
		$email      = sanitize_email( $body['email'] ?? '' );
		$password   = isset( $body['password'] ) ? (string) $body['password'] : '';

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'error' => 'A valid email address is required.' ), 400 );
		}
		$allowed_roles = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );

		// Support both 'role' (single string) and 'roles' (array) — 'roles' takes precedence
		if ( isset( $body['roles'] ) && is_array( $body['roles'] ) ) {
			$roles = array_values( array_unique( array_filter(
				array_map( 'sanitize_key', $body['roles'] ),
				function( $r ) use ( $allowed_roles ) { return in_array( $r, $allowed_roles, true ); }
			) ) );
			$role = ! empty( $roles ) ? $roles[0] : 'rrp_student';
		} else {
			$role  = sanitize_key( $body['role'] ?? 'rrp_student' );
			$roles = array( $role );
		}

		if ( empty( $roles ) ) {
			return new WP_REST_Response( array( 'error' => 'At least one valid role is required.' ), 400 );
		}
		// Only admins can create coordinator or admin accounts
		if ( array_intersect( array( 'rrp_coordinator', 'rrp_admin' ), $roles ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'Only admins can create coordinator or admin accounts.' ), 403 );
		}
		if ( email_exists( $email ) ) {
			$existing     = get_user_by( 'email', $email );
			$portal_roles = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin' );
			if ( array_intersect( $portal_roles, (array) $existing->roles ) ) {
				return new WP_REST_Response( array( 'error' => 'A user with this email already has an active portal account.' ), 409 );
			}
			// Re-onboard: user was previously removed from the portal — restore their access.
			$user_id    = $existing->ID;
			$reactivate = array( 'ID' => $user_id, 'role' => $role );
			if ( $first_name ) $reactivate['first_name']    = $first_name;
			if ( $last_name )  $reactivate['last_name']     = $last_name;
			$dn = trim( "$first_name $last_name" );
			if ( $dn )         $reactivate['display_name'] = $dn;
			if ( $password )   $reactivate['user_pass']    = $password;
			$result = wp_update_user( $reactivate );
			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
			}
		} else {
			$display_name = trim( $first_name . ' ' . $last_name ) ?: $email;
			$base_login   = sanitize_user( strtolower( str_replace( ' ', '.', $display_name ) ), true );
			$base_login   = $base_login ?: sanitize_user( explode( '@', $email )[0], true );
			$login        = $base_login;
			$i            = 1;
			while ( username_exists( $login ) ) {
				$login = $base_login . $i++;
			}
			$user_data = array(
				'user_login'   => $login,
				'user_email'   => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name,
				'user_pass'    => $password ?: wp_generate_password( 12, true, false ),
				'role'         => $role,
			);
			$user_id = wp_insert_user( $user_data );
			if ( is_wp_error( $user_id ) ) {
				return new WP_REST_Response( array( 'error' => $user_id->get_error_message() ), 400 );
			}
		}

		// Apply additional roles beyond the primary one
		if ( count( $roles ) > 1 ) {
			$u_obj = get_userdata( $user_id );
			foreach ( array_slice( $roles, 1 ) as $extra_role ) {
				if ( in_array( $extra_role, $allowed_roles, true ) ) {
					$u_obj->add_role( $extra_role );
				}
			}
		}

		if ( ! empty( $body['degree'] ) ) {
			update_user_meta( $user_id, 'rrp_degree', sanitize_text_field( $body['degree'] ) );
		}
		if ( ! empty( $body['allowedTypes'] ) && is_array( $body['allowedTypes'] ) ) {
			update_user_meta( $user_id, 'rrp_allowed_submission_types', array_map( 'sanitize_text_field', $body['allowedTypes'] ) );
		}
		if ( ! empty( $body['defaultStageReviewers'] ) && is_array( $body['defaultStageReviewers'] ) ) {
			update_user_meta( $user_id, 'rrp_default_stage_reviewers', $body['defaultStageReviewers'] );
		}
		if ( ! empty( $body['submissionTypes'] ) && is_array( $body['submissionTypes'] ) ) {
			update_user_meta( $user_id, 'rrp_submission_types', array_map( 'sanitize_text_field', $body['submissionTypes'] ) );
		}
		if ( ! empty( $body['department'] ) ) {
			update_user_meta( $user_id, 'rrp_department', sanitize_text_field( $body['department'] ) );
		}
		if ( ! empty( $body['expertise'] ) ) {
			update_user_meta( $user_id, 'rrp_expertise', sanitize_textarea_field( $body['expertise'] ) );
		}

		// Sync to reviewers.json so the assignment panel sees this reviewer
		if ( in_array( 'rrp_reviewer', $roles, true ) || in_array( 'rrp_faculty', $roles, true ) ) {
			Portal_Data::sync_reviewer_to_json( $user_id );
		}
		if ( ! empty( $body['programIds'] ) && is_array( $body['programIds'] ) ) {
			update_user_meta( $user_id, 'rrp_program_ids', array_map( 'sanitize_text_field', $body['programIds'] ) );
		}
		if ( ! empty( $body['programId'] ) ) {
			update_user_meta( $user_id, 'rrp_program_id', sanitize_text_field( $body['programId'] ) );
		}

		return new WP_REST_Response( array( 'user' => self::format_portal_user( get_userdata( $user_id ) ), 'created' => true ), 201 );
	}

	public static function portal_users_update( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}

		$update = array( 'ID' => $id );
		if ( isset( $body['firstName'] ) ) $update['first_name']   = sanitize_text_field( $body['firstName'] );
		if ( isset( $body['lastName'] ) )  $update['last_name']    = sanitize_text_field( $body['lastName'] );
		if ( isset( $body['firstName'] ) || isset( $body['lastName'] ) ) {
			$fn = sanitize_text_field( $body['firstName'] ?? $user->first_name );
			$ln = sanitize_text_field( $body['lastName']  ?? $user->last_name );
			$update['display_name'] = trim( $fn . ' ' . $ln ) ?: $user->display_name;
		}
		if ( ! empty( $body['password'] ) ) {
			$update['user_pass'] = (string) $body['password'];
		}
		if ( count( $update ) > 1 ) {
			wp_update_user( $update );
		}

		if ( array_key_exists( 'degree', $body ) ) {
			update_user_meta( $id, 'rrp_degree', sanitize_text_field( $body['degree'] ) );
		}
		if ( array_key_exists( 'allowedTypes', $body ) && is_array( $body['allowedTypes'] ) ) {
			update_user_meta( $id, 'rrp_allowed_submission_types', array_map( 'sanitize_text_field', $body['allowedTypes'] ) );
		}
		if ( array_key_exists( 'defaultStageReviewers', $body ) && is_array( $body['defaultStageReviewers'] ) ) {
			update_user_meta( $id, 'rrp_default_stage_reviewers', $body['defaultStageReviewers'] );
		}
		if ( array_key_exists( 'submissionTypes', $body ) && is_array( $body['submissionTypes'] ) ) {
			update_user_meta( $id, 'rrp_submission_types', array_map( 'sanitize_text_field', $body['submissionTypes'] ) );
		}
		if ( array_key_exists( 'department', $body ) ) {
			update_user_meta( $id, 'rrp_department', sanitize_text_field( $body['department'] ) );
		}
		if ( array_key_exists( 'expertise', $body ) ) {
			update_user_meta( $id, 'rrp_expertise', sanitize_textarea_field( $body['expertise'] ) );
		}
		if ( array_key_exists( 'programIds', $body ) && is_array( $body['programIds'] ) ) {
			update_user_meta( $id, 'rrp_program_ids', array_map( 'sanitize_text_field', $body['programIds'] ) );
		}
		if ( array_key_exists( 'programId', $body ) ) {
			update_user_meta( $id, 'rrp_program_id', sanitize_text_field( (string) $body['programId'] ) );
		}

		// Allow admin to change user role(s)
		$allowed_roles = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		if ( current_user_can( 'rrp_full_admin_access' ) ) {
			if ( isset( $body['roles'] ) && is_array( $body['roles'] ) ) {
				// Multi-role assignment
				$new_roles = array_values( array_unique( array_filter(
					array_map( 'sanitize_key', $body['roles'] ),
					function( $r ) use ( $allowed_roles ) { return in_array( $r, $allowed_roles, true ); }
				) ) );
				if ( ! empty( $new_roles ) ) {
					// Remove all rrp_* portal roles first
					foreach ( $allowed_roles as $r ) { $user->remove_role( $r ); }
					// Set primary role, then add additional roles
					$user->set_role( $new_roles[0] );
					foreach ( array_slice( $new_roles, 1 ) as $r ) { $user->add_role( $r ); }
				}
			} elseif ( ! empty( $body['role'] ) ) {
				// Single role change (backward compat)
				$new_role = sanitize_key( (string) $body['role'] );
				if ( in_array( $new_role, $allowed_roles, true ) ) {
					$user->set_role( $new_role );
				}
			}
		}

		// Sync to reviewers.json: add/update if still a reviewer, remove if no longer one
		$updated_roles = (array) get_userdata( $id )->roles;
		if ( in_array( 'rrp_reviewer', $updated_roles, true ) || in_array( 'rrp_faculty', $updated_roles, true ) ) {
			Portal_Data::sync_reviewer_to_json( $id );
		} else {
			// User was possibly demoted away from reviewer — remove from pool
			Portal_Data::remove_reviewer_from_json( $user->user_email );
		}

		return new WP_REST_Response( array( 'user' => self::format_portal_user( get_userdata( $id ) ) ), 200 );
	}

	public static function portal_users_delete( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( $id === get_current_user_id() ) {
			return new WP_REST_Response( array( 'error' => 'You cannot remove your own portal access.' ), 400 );
		}
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}
		if ( user_can( $id, 'rrp_full_admin_access' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'You cannot remove an administrator.' ), 403 );
		}
		$was_reviewer = in_array( 'rrp_reviewer', (array) $user->roles, true ) || in_array( 'rrp_faculty', (array) $user->roles, true );
		foreach ( array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' ) as $r ) {
			$user->remove_role( $r );
		}
		if ( empty( $user->roles ) ) {
			$user->set_role( 'subscriber' );
		}
		// Remove from reviewers.json if they were a reviewer
		if ( $was_reviewer ) {
			Portal_Data::remove_reviewer_from_json( $user->user_email );
		}
		return new WP_REST_Response( array( 'removed' => true, 'userId' => $id ), 200 );
	}

	public static function portal_users_delete_json_student( WP_REST_Request $request ) {
		// Legacy students aren't stored anywhere — they're derived from submissions.
		// "Deleting" one just means creating a WP user with no role (subscriber)
		// so they no longer appear as legacy. Here we simply acknowledge the intent;
		// coordinators should use Import to give them a proper account instead.
		// For a hard removal, the submission's submitterEmail would need to be cleared,
		// which we intentionally don't do to preserve audit history.
		$email = sanitize_email( urldecode( $request->get_param( 'email' ) ) );
		if ( ! $email ) {
			return new WP_REST_Response( array( 'error' => 'Invalid email.' ), 400 );
		}
		// Check the submitter actually exists in submissions
		$data  = Portal_Data::read_submissions();
		$subs  = isset( $data['submissions'] ) && is_array( $data['submissions'] ) ? $data['submissions'] : array();
		$found = false;
		foreach ( $subs as $sub ) {
			if ( strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) ) === strtolower( $email ) ) {
				$found = true; break;
			}
		}
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Submitter not found in submissions.' ), 404 );
		}
		return new WP_REST_Response( array(
			'dismissed'  => true,
			'email'      => $email,
			'note'       => 'Legacy entry dismissed. Submission history is preserved. Use Import to create a portal account.',
		), 200 );
	}

	public static function portal_users_delete_json( WP_REST_Request $request ) {
		$json_id   = sanitize_key( $request->get_param( 'jsonId' ) );
		$reviewers = Portal_Data::read_reviewers();
		$found     = false;
		$filtered  = array_values( array_filter( $reviewers, function( $r ) use ( $json_id, &$found ) {
			if ( ( $r['id'] ?? '' ) === $json_id ) { $found = true; return false; }
			return true;
		} ) );
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Reviewer not found in pool.' ), 404 );
		}
		Portal_Data::write_reviewers( $filtered );
		return new WP_REST_Response( array( 'removed' => true, 'jsonId' => $json_id ), 200 );
	}

	public static function portal_users_reset_password( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}
		// Never reset another admin's password unless you are also admin
		if ( user_can( $id, 'rrp_full_admin_access' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot reset an administrator password.' ), 403 );
		}
		$new_password = isset( $body['password'] ) && (string) $body['password'] !== '' ? (string) $body['password'] : wp_generate_password( 12, true, false );
		wp_set_password( $new_password, $id );
		return new WP_REST_Response( array( 'reset' => true, 'userId' => $id, 'newPassword' => $new_password ), 200 );
	}

	// ─── Audit Log ────────────────────────────────────────────────────────────

	/**
	 * Append an audit log entry to the in-memory submission record.
	 * Caller is responsible for calling Portal_Data::write_submissions() afterwards.
	 */
	private static function append_audit_log( array &$data, int $idx, string $action, string $detail ): void {
		$user  = wp_get_current_user();
		$entry = array(
			'at'     => gmdate( 'c' ),
			'action' => $action,
			'actor'  => array(
				'name'  => $user->display_name ?: ( $user->user_login ?: 'System' ),
				'email' => strtolower( trim( (string) ( $user->user_email ?? '' ) ) ),
			),
			'detail' => $detail,
		);
		if ( ! isset( $data['submissions'][ $idx ]['auditLog'] ) || ! is_array( $data['submissions'][ $idx ]['auditLog'] ) ) {
			$data['submissions'][ $idx ]['auditLog'] = array();
		}
		$data['submissions'][ $idx ]['auditLog'][] = $entry;
	}

	public static function audit_log_get( WP_REST_Request $request ): WP_REST_Response {
		$id  = $request->get_param( 'id' );
		$sub = self::get_submission_by_id( $id );
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$log = isset( $sub['auditLog'] ) && is_array( $sub['auditLog'] ) ? $sub['auditLog'] : array();
		return new WP_REST_Response( array( 'id' => $id, 'auditLog' => array_reverse( $log ) ), 200 );
	}

	// ─── Portal Settings ──────────────────────────────────────────────────────

	public static function portal_settings_get( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( RRP_Portal_Settings::get_all( true ), 200 );
	}

	public static function portal_settings_update( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) || empty( $body ) ) {
			return new WP_REST_Response( array( 'error' => 'Request body must be a JSON object.' ), 400 );
		}
		$updated = RRP_Portal_Settings::update( $body );
		if ( is_wp_error( $updated ) ) {
			return new WP_REST_Response( array( 'error' => $updated->get_error_message() ), 422 );
		}
		return new WP_REST_Response( RRP_Portal_Settings::get_all( true ), 200 );
	}
}
