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
	}

	public static function health( WP_REST_Request $request ) {
		return new WP_REST_Response( array( 'ok' => true, 'bootId' => (string) time() ), 200 );
	}

	/**
	 * Permission callback functions for role-based access control
	 */

	public static function can_submit_research( WP_REST_Request $request ) {
		// Allow logged-in users with submission capability or non-logged users for now
		return is_user_logged_in() ? current_user_can( 'rrp_submit_research' ) || current_user_can( 'read' ) : true;
	}

	public static function can_view_submissions( WP_REST_Request $request ) {
		// Users can view their own submissions or reviewers/admins can view assigned ones
		return current_user_can( 'rrp_view_own_submissions' ) || current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_review_submissions' );
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
		$user_email = $current_user->user_email;

		// Check if user is the submitter
		if ( isset( $submission['submitterEmail'] ) && $submission['submitterEmail'] === $user_email ) {
			return current_user_can( 'rrp_view_own_submissions' );
		}

		// Check if user is an assigned reviewer
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			$assigned_reviewers = $submission['assignedReviewers'] ?? array();
			foreach ( $assigned_reviewers as $reviewer ) {
				if ( isset( $reviewer['email'] ) && $reviewer['email'] === $user_email ) {
					return true;
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

		// Check if user is the submitter and can edit their own submissions
		if ( current_user_can( 'rrp_edit_own_submissions' ) ) {
			$submission = self::get_submission_by_id( $submission_id );
			if ( $submission ) {
				$current_user = wp_get_current_user();
				return isset( $submission['submitterEmail'] ) && $submission['submitterEmail'] === $current_user->user_email;
			}
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
		$user_email = $current_user->user_email;

		// Check if user is an assigned reviewer
		$assigned_reviewers = $submission['assignedReviewers'] ?? array();
		foreach ( $assigned_reviewers as $reviewer ) {
			if ( isset( $reviewer['email'] ) && $reviewer['email'] === $user_email ) {
				return true;
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
		$user_email = $current_user->user_email;

		// Check if user is the submitter
		if ( isset( $submission['submitterEmail'] ) && $submission['submitterEmail'] === $user_email ) {
			return true;
		}

		// Check if user is an assigned reviewer
		if ( current_user_can( 'rrp_provide_feedback' ) ) {
			$assigned_reviewers = $submission['assignedReviewers'] ?? array();
			foreach ( $assigned_reviewers as $reviewer ) {
				if ( isset( $reviewer['email'] ) && $reviewer['email'] === $user_email ) {
					return true;
				}
			}
		}

		// Admins can always comment
		return current_user_can( 'rrp_full_admin_access' );
	}

	public static function can_upload_attachments( WP_REST_Request $request ) {
		return self::can_edit_submission( $request );
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
		return current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_full_admin_access' );
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
		$metrics = Portal_Data::get_workflow_metrics();
		return new WP_REST_Response( $metrics, 200 );
	}

	public static function analytics_performance( WP_REST_Request $request ) {
		$metrics = Portal_Data::get_performance_metrics();
		return new WP_REST_Response( $metrics, 200 );
	}

	public static function analytics_reviewer( WP_REST_Request $request ) {
		$reviewer_email = $request->get_param( 'reviewerEmail' );
		if ( ! $reviewer_email ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail query parameter is required.' ), 400 );
		}
		$metrics = Portal_Data::get_reviewer_metrics( $reviewer_email );
		return new WP_REST_Response( $metrics, 200 );
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
		if ( $has_pool ) {
			$submission = Portal_Data::auto_assign_submission( $submission, $submission['submitterEmail'] ?? '' );
		}
		$data = Portal_Data::read_submissions();
		$data['submissions'][] = $submission;
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
			if ( $is_reviewer && in_array( $status, array( 'Submitted', 'Under Review', 'Submitted - Awaiting Review', 'Under Initial Review', 'Administrative Review', 'Revision Required' ), true ) ) {
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
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( ! empty( $body['status'] ) && is_string( $body['status'] ) && in_array( trim( $body['status'] ), Portal_Data::PUBLIC_STATUSES, true ) ) {
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'status' => trim( $body['status'] ) ) );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( isset( $body['assignedReviewers'] ) && is_array( $body['assignedReviewers'] ) ) {
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'assignedReviewers' => $body['assignedReviewers'] ) );
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
			Portal_Data::write_submissions( $data );
			$updated = $data['submissions'][ $idx ];
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
			$found = false;
			foreach ( $review_stages as $si => $rs ) {
				if ( strtolower( (string) ( $rs['stageName'] ?? '' ) ) === strtolower( $stage_name ) ) {
					$review_stages[ $si ]['revisionSubmittedAt'] = gmdate( 'c' );
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				return new WP_REST_Response( array( 'error' => 'Stage not found.' ), 400 );
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $review_stages, 'status' => 'Revision Submitted' ) );
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
				$allowed_exts = array( 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt' );
				if ( $ext && ! in_array( $ext, $allowed_exts, true ) ) continue;
				$ext = $ext ? '.' . $ext : '';
				$filename = $base ? $base . $ext : 'file-' . time() . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$dest = $dir . $filename;
				if ( move_uploaded_file( $tmp, $dest ) ) {
					$new_attachments[] = array( 'name' => $orig, 'filename' => $filename, 'size' => $size );
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
				$allowed_exts = array( 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt' );
				if ( $ext && ! in_array( $ext, $allowed_exts, true ) ) {
					$ext = '';
				}
				$ext = $ext ? '.' . $ext : '';
				$filename = $base ? $base . $ext : 'file-' . time() . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$dest = $dir . $filename;
				if ( move_uploaded_file( $tmp, $dest ) ) {
					$new_attachments[] = array( 'name' => $orig, 'filename' => $filename, 'size' => $size );
				}
			}
		}
		if ( empty( $new_attachments ) ) {
			return new WP_REST_Response( array( 'error' => 'Upload failed. Max 5 files, 2MB each.' ), 400 );
		}
		$attachments = array_merge( $attachments, $new_attachments );
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'attachments' => $attachments ) );
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
		$content = file_get_contents( $file_path );
		$response = new WP_REST_Response( $content, 200 );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $name . '"' );
		$response->header( 'Content-Type', 'application/octet-stream' );
		return $response;
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
				$stages[] = array( 'stageName' => $rs['stageName'] ?? '', 'reviewers' => $reviewers );
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
		Portal_Data::write_config( $config );
		return new WP_REST_Response( $config, 200 );
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
					'id'        => $s['id'] ?? null,
					'type'      => $s['type'] ?? null,
					'status'    => $s['status'] ?? null,
					'title'     => $s['title'] ?? null,
					'createdAt' => $s['createdAt'] ?? null,
					'deadline'  => $deadline,
				);
			}
		}
		return new WP_REST_Response( array( 'submissions' => $list ), 200 );
	}
}
