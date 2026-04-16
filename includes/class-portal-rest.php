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
		// ── Public-facing config (no auth — tells guests whether registration is open) ──
		register_rest_route( self::NAMESPACE, '/public-config', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'public_config_get' ),
			'permission_callback' => '__return_true',
		) );
		// ── Self-registration for public submitters (no auth required) ────────────────
		register_rest_route( self::NAMESPACE, '/auth/register', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'auth_register' ),
			'permission_callback' => '__return_true',
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
		register_rest_route( self::NAMESPACE, '/submissions/inactive', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'submissions_inactive' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/bulk-cancel', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submissions_bulk_cancel' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/calendar-events', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'calendar_events' ),
			'permission_callback' => array( __CLASS__, 'can_view_submissions' ),
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
			'permission_callback' => 'is_user_logged_in',
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
			'permission_callback' => array( __CLASS__, 'can_rate_review' ),
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
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/meetings', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'portal_meeting_create' ),
			'permission_callback' => array( __CLASS__, 'can_schedule_meeting' ),
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
		// Workflow schema — static options for the workflow engine editor
		register_rest_route( self::NAMESPACE, '/config/workflow-schema', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'workflow_schema_get' ),
			'permission_callback' => array( __CLASS__, 'can_view_config' ),
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
		// Extension requests (reviewer submits → coordinator approves/denies)
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/request-extension', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'extension_request' ),
			'permission_callback' => array( __CLASS__, 'can_provide_feedback' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/extension-requests/(?P<reqId>[a-zA-Z0-9\-]+)/approve', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'extension_approve' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ), 'reqId' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/extension-requests/(?P<reqId>[a-zA-Z0-9\-]+)/deny', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'extension_deny' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ), 'reqId' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/extension-requests', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'extension_requests_list' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		// Decision appeals queue
		register_rest_route( self::NAMESPACE, '/appeals', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'appeals_list' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		// Decision appeal
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/appeal', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'appeal_post' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/appeal', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'appeal_patch' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
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
		// Lock / unlock a user account
		register_rest_route( self::NAMESPACE, '/portal-users/(?P<id>\d+)/lock', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'portal_users_toggle_lock' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
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
		register_rest_route( self::NAMESPACE, '/settings/test-email', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'portal_settings_test_email' ),
			'permission_callback' => array( __CLASS__, 'can_manage_config' ),
		) );

		// ── Administration: Backup / Restore / Archive ───────────────────────
		register_rest_route( self::NAMESPACE, '/admin/backup', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'admin_backup_download' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/restore', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'admin_restore' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/archives', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'admin_archives_list' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/archive-submissions', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'admin_archive_submissions' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/archives/(?P<name>[\w\-\.]+)/download', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'admin_archive_download' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
			'args'                => array( 'name' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/archives/(?P<name>[\w\-\.]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'admin_archive_delete' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
			'args'                => array( 'name' => array( 'required' => true ) ),
		) );

		// ── Administration: Auto Backup ────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/admin/auto-backup/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'admin_auto_backup_status' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/auto-backup/trigger', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'admin_auto_backup_trigger' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		// ── Collaborative stage notes ───────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/collab', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'collab_get' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/collab', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'collab_put' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// ── Administration: Role Management ──────────────────────────────────
		register_rest_route( self::NAMESPACE, '/admin/roles', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'admin_roles_list' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/roles', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'admin_role_create' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );
		register_rest_route( self::NAMESPACE, '/admin/roles/(?P<slug>[a-z0-9_]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'admin_role_delete' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
			'args'                => array( 'slug' => array( 'required' => true ) ),
		) );
		// Phase 6 migration: one-time stamp of currentRound on legacy submissions
		register_rest_route( self::NAMESPACE, '/admin/migrate/stamp-rounds', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'migration_stamp_rounds' ),
			'permission_callback' => array( __CLASS__, 'is_full_admin' ),
		) );

		// Notification preferences (self-service: each user manages their own)
		register_rest_route( self::NAMESPACE, '/notif-prefs', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'notif_prefs_get' ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NAMESPACE, '/notif-prefs', array(
			'methods'             => 'PUT',
			'callback'            => array( __CLASS__, 'notif_prefs_put' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		// OAuth2 redirect callback for Microsoft Entra SSO (public — no auth required)
		register_rest_route( self::NAMESPACE, '/auth/callback', array(
			'methods'             => 'GET',
			'callback'            => array( 'RRP_Auth_Provider', 'handle_entra_callback' ),
			'permission_callback' => '__return_true',
		) );

		// ── Webhooks ──────────────────────────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/webhooks', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'webhooks_list' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/webhooks', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'webhook_create' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );
		register_rest_route( self::NAMESPACE, '/webhooks/(?P<id>[a-zA-Z0-9\-]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'webhook_delete' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		// ── Reviewer performance metrics ─────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/analytics/reviewer-performance', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_reviewer_performance' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ), // reviewers get own row only
		) );

		// ── Reviewer load (submissions per reviewer) ──────────────────────────────
		register_rest_route( self::NAMESPACE, '/analytics/reviewer-load', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'analytics_reviewer_load' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		// ── Plagiarism / similarity check ─────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/similarity-check', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'similarity_check' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ), // all roles — scoped inside handler
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		// ── Bulk announcement broadcast ───────────────────────────────────────────
		register_rest_route( self::NAMESPACE, '/announcements', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'announcements_send' ),
			'permission_callback' => array( __CLASS__, 'can_manage_workflow' ),
		) );

		// ── Gated Review: primary-reviewer-as-conduit workflows ───────────────────
		// Release a consolidated decision to the student
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/release', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'gated_release' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Request a re-check from a higher-stage reviewer
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/request-recheck', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'gated_request_recheck' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Coordination messages between Stage 1 and higher-stage reviewers
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/messages', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'gated_messages_get' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/messages', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'gated_messages_post' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		// Meeting requests between Stage 1 and higher-stage reviewers
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/meeting-requests', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'gated_meetings_get' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/meeting-requests', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'gated_meetings_post' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[a-zA-Z0-9\-]+)/gated/meeting-requests/(?P<reqId>[a-zA-Z0-9\-]+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'gated_meetings_patch' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'id'    => array( 'required' => true ),
				'reqId' => array( 'required' => true ),
			),
		) );
	}

	public static function health( WP_REST_Request $request ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// ── Rate limiting (IP-based, WP transients) ───────────────────────────────
	private static function check_rate_limit( string $action, int $max = 5, int $window_secs = 900 ): bool {
		// F15: Use user ID as the rate-limit key for authenticated users so proxy
		// IP spoofing (X-Forwarded-For etc.) cannot be used to bypass the limit.
		if ( is_user_logged_in() ) {
			$key = 'rrp_rl_user_' . get_current_user_id() . '_' . md5( $action );
		} else {
			$ip  = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
			$key = 'rrp_rl_' . md5( $action . '_' . $ip );
		}
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window_secs );
		return true;
	}

	// ── SSRF guard: blocks private/loopback ranges and enforces HTTPS ────────
	private static function is_safe_external_url( string $url ): bool {
		$parsed = wp_parse_url( $url );
		if ( ( $parsed['scheme'] ?? '' ) !== 'https' ) {
			return false;
		}
		$host = strtolower( (string) ( $parsed['host'] ?? '' ) );
		if ( ! $host ) {
			return false;
		}
		// Resolve hostname to IP and reject private/reserved ranges
		$ip = gethostbyname( $host );
		if ( $ip === $host && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			// gethostbyname returned the hostname unchanged — unresolvable
			return false;
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}
		return true;
	}

	// ── Webhook delivery (non-blocking, HMAC-signed) ──────────────────────────
	private static function fire_webhooks( string $event, array $payload ): void {
		$webhooks = Portal_Data::read_webhooks();
		if ( empty( $webhooks ) ) {
			return;
		}
		$body = (string) wp_json_encode( array(
			'event'       => $event,
			'data'        => $payload,
			'deliveredAt' => gmdate( 'c' ),
		) );
		foreach ( $webhooks as $wh ) {
			if ( empty( $wh['url'] ) ) {
				continue;
			}
			// SSRF guard: only deliver to safe external HTTPS endpoints
			if ( ! self::is_safe_external_url( (string) $wh['url'] ) ) {
				continue;
			}
			$secret  = (string) ( $wh['secret'] ?? '' );
			$headers = array(
				'Content-Type' => 'application/json',
				'X-RRP-Event'  => $event,
				'X-RRP-Hook-ID' => (string) ( $wh['id'] ?? '' ),
			);
			if ( $secret ) {
				$headers['X-RRP-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
			}
			wp_remote_post( esc_url_raw( $wh['url'] ), array(
				'timeout'  => 5,
				'body'     => $body,
				'headers'  => $headers,
				'blocking' => false,
			) );
		}
	}

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

	/**
	 * Permission callback for POST /reviews/rate.
	 * Reads submissionId from the request body (the route has no {id} path segment).
	 */
	public static function can_rate_review( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$body          = $request->get_json_params() ?: $request->get_body_params();
		$submission_id = isset( $body['submissionId'] ) ? trim( (string) $body['submissionId'] ) : '';
		if ( ! $submission_id ) {
			// No submission ID yet; gate on the base review capability.
			return current_user_can( 'rrp_review_submissions' ) || current_user_can( 'rrp_provide_feedback' );
		}
		$submission = self::get_submission_by_id( $submission_id );
		if ( ! $submission ) {
			return false;
		}
		if ( current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' ) ) {
			return true;
		}
		$email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
		foreach ( $submission['assignedReviewers'] ?? array() as $r ) {
			if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
				return true;
			}
		}
		foreach ( $submission['reviewStages'] ?? array() as $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function can_provide_feedback( WP_REST_Request $request ) {
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
		// Students (rrp_submit_research) must NOT see full portal config — it
		// reveals reviewer pools, stage requirements, and blind-review settings.
		// Only coordinators, reviewers, and admins need this data.
		return current_user_can( 'rrp_view_all_submissions' )
			|| current_user_can( 'rrp_full_admin_access' )
			|| current_user_can( 'rrp_review_submissions' );
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
		// Only coordinators / admins may request dashboard data for a different user
		if ( $req_email && ( current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' ) ) ) {
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
	 *
	 * Returned array always contains a 'role' key; additional keys:
	 *   admin                → role=admin  (no submission filter)
	 *   coordinator          → role=coordinator, coordinatorEmail, optional submissionTypes
	 *   reviewer/faculty     → role=reviewer, reviewerEmail
	 *   student/public       → role=student, submitterEmail
	 *
	 * filter_submissions_for_analytics() in Portal_Data only inspects
	 * submitterEmail, reviewerEmail, and submissionTypes — the extra
	 * keys are ignored there, ensuring no breakage.
	 */
	private static function analytics_user_filter() {
		$user  = wp_get_current_user();
		$email = strtolower( trim( (string) ( $user->user_email ?? '' ) ) );

		if ( current_user_can( 'rrp_full_admin_access' ) ) {
			return array( 'role' => 'admin' );
		}
		if ( current_user_can( 'rrp_view_all_submissions' ) || current_user_can( 'rrp_manage_workflow' ) ) {
			// Check if this coordinator is scoped to specific submission types (stored in user meta via DB)
			$reviewer_data = Portal_Data::read_reviewers();
			$coord_types   = null;
			foreach ( $reviewer_data as $rv ) {
				if ( strtolower( trim( (string) ( $rv['email'] ?? '' ) ) ) === $email ) {
					$types = $rv['submissionTypes'] ?? null;
					if ( is_array( $types ) && ! empty( $types ) ) {
						$coord_types = $types;
					}
					break;
				}
			}
			if ( $coord_types ) {
				return array( 'role' => 'coordinator', 'coordinatorEmail' => $email, 'submissionTypes' => $coord_types );
			}
			return array( 'role' => 'coordinator', 'coordinatorEmail' => $email );
		}
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			return array( 'role' => 'reviewer', 'reviewerEmail' => $email );
		}
		// student / public / everyone else
		return array( 'role' => 'student', 'submitterEmail' => $email );
	}

	public static function analytics_reviewer( WP_REST_Request $request ) {
		$reviewer_email = sanitize_email( (string) ( $request->get_param( 'reviewerEmail' ) ?? '' ) );
		if ( ! $reviewer_email ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail query parameter is required.' ), 400 );
		}
		// Non-admin/coordinator callers may only retrieve their own reviewer data.
		if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			$current_email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
			if ( strtolower( trim( $reviewer_email ) ) !== $current_email ) {
				return new WP_REST_Response( array( 'error' => 'Access denied.' ), 403 );
			}
		}
		$metrics = Portal_Data::get_reviewer_metrics( $reviewer_email );
		return new WP_REST_Response( $metrics, 200 );
	}

	public static function analytics_workload( WP_REST_Request $request ) {
		$reviewer_email = sanitize_email( (string) ( $request->get_param( 'reviewerEmail' ) ?? '' ) );
		if ( ! $reviewer_email ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail query parameter is required.' ), 400 );
		}
		// Non-admin/coordinator callers may only retrieve their own workload data.
		if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			$current_email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
			if ( strtolower( trim( $reviewer_email ) ) !== $current_email ) {
				return new WP_REST_Response( array( 'error' => 'Access denied.' ), 403 );
			}
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
		$raw  = Portal_Data::get_conflict_of_interest_records();
		$flat = array();
		// Stored as conflictOfInterest[submissionId][reviewerEmail] — flatten for the UI.
		foreach ( (array) $raw as $sub_id => $by_email ) {
			foreach ( (array) $by_email as $email => $record ) {
				$flat[] = array(
					'submissionId'  => $record['submissionId']  ?? $sub_id,
					'reviewerEmail' => $record['reviewerEmail'] ?? $email,
					'reason'        => $record['reason']        ?? '',
					'declaredAt'    => $record['timestamp']     ?? null,
				);
			}
		}
		return new WP_REST_Response( array( 'conflicts' => $flat ), 200 );
	}

	public static function declare_conflict( WP_REST_Request $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();
		$reviewer_email = isset( $body['reviewerEmail'] ) ? strtolower( trim( (string) $body['reviewerEmail'] ) ) : '';
		$submission_id  = isset( $body['submissionId'] )  ? sanitize_text_field( trim( (string) $body['submissionId'] ) )  : '';
		$reason         = isset( $body['reason'] )        ? sanitize_textarea_field( trim( (string) $body['reason'] ) )    : '';

		if ( ! $reviewer_email || ! $submission_id || ! $reason ) {
			return new WP_REST_Response( array( 'error' => 'reviewerEmail, submissionId, and reason are required.' ), 400 );
		}

		// Non-coordinators / non-admins can only declare on their own behalf.
		$current_user = wp_get_current_user();
		$is_privileged = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		if ( ! $is_privileged ) {
			$my_email = strtolower( trim( $current_user->user_email ) );
			if ( $my_email !== $reviewer_email ) {
				return new WP_REST_Response( array( 'error' => 'You may only declare a conflict for your own account.' ), 403 );
			}
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
		// Prevent caller from attributing ratings to a different reviewer (IDOR / identity spoofing).
		if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			$current_email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
			if ( $reviewer_email !== $current_email ) {
				return new WP_REST_Response( array( 'error' => 'You may only submit ratings as yourself.' ), 403 );
			}
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
		// Rate limit public (rrp_public role) submissions: 10 per IP per 15 min
		if ( is_user_logged_in() ) {
			$_roles = (array) wp_get_current_user()->roles;
			if ( in_array( 'rrp_public', $_roles, true ) && ! array_intersect( array( 'rrp_student', 'rrp_faculty', 'rrp_coordinator', 'rrp_admin' ), $_roles ) ) {
				if ( ! self::check_rate_limit( 'submit', 10, 900 ) ) {
					return new WP_REST_Response( array( 'success' => false, 'error' => 'Too many submission attempts. Please try again in 15 minutes.' ), 429 );
				}
			}
		}
		$body = $request->get_json_params() ?: $request->get_body_params();
		// Accept the authoritative type from submissionType (set by the form) or type.
		// submissionType is the canonical config-defined id; type is kept in sync.
		$sub_type_field = isset( $body['submissionType'] ) ? strtolower( trim( (string) $body['submissionType'] ) ) : '';
		$type_field     = isset( $body['type'] )           ? strtolower( trim( (string) $body['type'] ) )           : '';
		$type = $sub_type_field ?: $type_field;
		if ( empty( $type ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'Submission type is required.' ), 400 );
		}
		// Validate against dynamically configured types (config.json) plus the legacy hardcoded set.
		$config_types  = array_column( Portal_Data::get_submission_types(), 'id' );
		$legacy_types  = array( 'conference', 'publication', 'student-project', 'grant' );
		$allowed_types = array_unique( array_merge( $config_types, $legacy_types ) );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'Invalid submission type.' ), 400 );
		}
		// If submitter has only the public role, restrict to the admin-configured allowed types.
		if ( is_user_logged_in() && current_user_can( 'rrp_submit_research' ) && ! current_user_can( 'rrp_view_all_submissions' ) ) {
			$current_roles = (array) wp_get_current_user()->roles;
			if ( in_array( 'rrp_public', $current_roles, true ) && ! array_intersect( array( 'rrp_student', 'rrp_faculty', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin' ), $current_roles ) ) {
				$pub_cfg     = Portal_Data::read_config()['publicSubmissions'] ?? array();
				$pub_allowed = is_array( $pub_cfg['allowedTypes'] ?? null ) ? $pub_cfg['allowedTypes'] : array();
				if ( ! empty( $pub_allowed ) && ! in_array( $type, $pub_allowed, true ) ) {
					return new WP_REST_Response( array( 'success' => false, 'error' => 'This submission type is not available for public submitters.' ), 403 );
				}
			}
		}
		// Always store type == submissionType so both fields agree.
		$body['type']           = $type;
		$body['submissionType'] = $type;
		$is_draft = isset( $body['status'] ) && strtolower( trim( (string) $body['status'] ) ) === 'draft';
		$errors = $is_draft ? array() : Portal_Data::validate_submission( $type, $body );
		if ( ! empty( $errors ) ) {
			return new WP_REST_Response( array( 'success' => false, 'errors' => $errors ), 400 );
		}
		$submission_id = Portal_Data::next_id( $type );
		$status = $is_draft ? 'Draft' : ( $type === 'conference' ? 'Submitted - Awaiting Review' : 'Submitted' );
		// Allowlist: only copy safe, submitter-controlled fields from $body to prevent mass assignment
		// (e.g. prevent injecting reviewStages, decisions, similarityScore, auditLog, etc.)
		$allowed_submit_fields = array(
			'title', 'abstract', 'keywords', 'researchArea', 'submitterName', 'submitterEmail',
			'coAuthors', 'affiliation', 'degreeProgram', 'department', 'supervisor',
			'fundingAgency', 'projectType', 'publicationType', 'presentationPreference',
			'fundingSource', 'conflictOfInterest', 'customFields', 'notes',
		);
		$body_clean = array_intersect_key( $body, array_flip( $allowed_submit_fields ) );
		$submission = array_merge( $body_clean, array(
			'id'        => $submission_id,
			'type'      => $type,
			'submissionType' => $type,
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
		if ( ! self::user_wants_notif( $to, 'submission_received' ) ) {
			return true; // user opted out
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

	// ─── Deadline reminder emails (2 days before due date) ────────────────────

	public static function send_deadline_reminders() {
		$data    = Portal_Data::read_submissions();
		$now     = time();
		$window  = 2 * DAY_IN_SECONDS;
		$changed = false;

		foreach ( $data['submissions'] as &$sub ) {
			$status = strtolower( $sub['status'] ?? '' );
			if ( in_array( $status, array( 'approved', 'published', 'rejected', 'withdrawn' ), true ) ) {
				continue;
			}
			foreach ( ( $sub['reviewStages'] ?? array() ) as $i => &$stage ) {
				if ( Portal_Data::is_stage_approved( $stage ) || ( $stage['skipped'] ?? false ) ) {
					continue;
				}
				if ( ! empty( $stage['reminderSentAt'] ) ) {
					continue; // already reminded this round
				}
				$deadline_ts = strtotime( Portal_Data::get_effective_deadline( $sub, $i ) );
				if ( ! $deadline_ts ) continue;
				$time_left = $deadline_ts - $now;
				if ( $time_left <= 0 || $time_left > $window ) continue;

				$stage_name = $stage['stageName'] ?? ( 'Stage ' . ( $i + 1 ) );
				$due_str    = gmdate( 'Y-m-d', $deadline_ts );
				$days_left  = (int) ceil( $time_left / DAY_IN_SECONDS );
				$subject    = '[Research Portal] Reminder: Review due in ' . $days_left . ' day(s) — ' . ( $sub['id'] ?? '' );
				$body       = "This is a reminder that your review is due soon.\n\n"
				            . 'Submission : ' . ( $sub['title'] ?? $sub['id'] ?? '' ) . "\n"
				            . 'ID         : ' . ( $sub['id'] ?? '' ) . "\n"
				            . 'Stage      : ' . $stage_name . "\n"
				            . 'Due Date   : ' . $due_str . "\n\n"
				            . 'Please log in to the Research Review Portal to complete your review.';

				foreach ( ( $stage['reviewers'] ?? array() ) as $r ) {
					$email = trim( (string) ( $r['email'] ?? '' ) );
					if ( $email && is_email( $email ) && self::user_wants_notif( $email, 'deadline_reminder' ) ) {
						wp_mail( $email, $subject, $body );
					}
				}
				$stage['reminderSentAt'] = gmdate( 'c' );
				$changed = true;
			}
			unset( $stage );
		}
		unset( $sub );
		if ( $changed ) {
			Portal_Data::write_submissions( $data );
		}
	}

	// ─── Escalation emails (after due date passes) ────────────────────────────

	public static function send_escalation_emails() {
		$data    = Portal_Data::read_submissions();
		$now     = time();
		$changed = false;

		// Coordinator + admin emails.
		$coord_users  = get_users( array( 'role__in' => array( 'rrp_coordinator', 'rrp_admin' ), 'number' => -1, 'fields' => array( 'user_email' ) ) );
		$coord_emails = array_values( array_filter( array_column( $coord_users, 'user_email' ), 'is_email' ) );

		// Faculty / program director emails.
		$dir_users    = get_users( array( 'role' => 'rrp_faculty', 'number' => -1, 'fields' => array( 'user_email' ) ) );
		$dir_emails   = array_values( array_filter( array_column( $dir_users, 'user_email' ), 'is_email' ) );

		foreach ( $data['submissions'] as &$sub ) {
			$status = strtolower( $sub['status'] ?? '' );
			if ( in_array( $status, array( 'approved', 'published', 'rejected', 'withdrawn' ), true ) ) {
				continue;
			}
			$submitter_email = trim( (string) ( $sub['submitterEmail'] ?? '' ) );

			foreach ( ( $sub['reviewStages'] ?? array() ) as $i => &$stage ) {
				if ( Portal_Data::is_stage_approved( $stage ) || ( $stage['skipped'] ?? false ) ) {
					continue;
				}
				$deadline_ts  = strtotime( Portal_Data::get_effective_deadline( $sub, $i ) );
				$grace        = Portal_Data::get_grace_period_seconds();
				if ( ! $deadline_ts || $now <= ( $deadline_ts + $grace ) ) continue;

				// Re-escalate every 3 days to avoid daily spam.
				if ( ! empty( $stage['escalationSentAt'] ) ) {
					$last = strtotime( $stage['escalationSentAt'] );
					if ( $last && ( $now - $last ) < 3 * DAY_IN_SECONDS ) continue;
				}

				$stage_name    = $stage['stageName'] ?? ( 'Stage ' . ( $i + 1 ) );
				$overdue_days  = (int) ceil( ( $now - $deadline_ts ) / DAY_IN_SECONDS );
				$due_str       = gmdate( 'Y-m-d', $deadline_ts );
				$reviewer_emails = array_values( array_filter(
					array_column( $stage['reviewers'] ?? array(), 'email' ), 'is_email'
				) );

				$to_emails = array_values( array_unique( array_merge(
					$reviewer_emails, $coord_emails, $dir_emails
				) ) );
				$headers = array();
				if ( $submitter_email && is_email( $submitter_email ) ) {
					$headers[] = 'Cc: ' . $submitter_email;
				}

				$subject = '[Research Portal] ESCALATION: Overdue ' . $overdue_days . 'd — ' . ( $sub['id'] ?? '' );
				$body    = "ESCALATION NOTICE\n\n"
				         . "The review stage below is overdue and requires immediate attention.\n\n"
				         . 'Submission  : ' . ( $sub['title'] ?? $sub['id'] ?? '' ) . "\n"
				         . 'ID          : ' . ( $sub['id'] ?? '' ) . "\n"
				         . 'Stage       : ' . $stage_name . "\n"
				         . 'Due Date    : ' . $due_str . "\n"
				         . 'Overdue by  : ' . $overdue_days . " day(s)\n"
				         . 'Reviewers   : ' . ( $reviewer_emails ? implode( ', ', $reviewer_emails ) : 'None assigned' ) . "\n\n"
				         . 'Please log in to the Research Review Portal to address this immediately.';

				foreach ( $to_emails as $email ) {
					if ( is_email( $email ) && self::user_wants_notif( $email, 'escalation' ) ) {
						wp_mail( $email, $subject, $body, $headers );
					}
				}
				$stage['escalationSentAt'] = gmdate( 'c' );
				$changed = true;
			}
			unset( $stage );
		}
		unset( $sub );
		if ( $changed ) {
			Portal_Data::write_submissions( $data );
		}
	}

	public static function submissions_list( WP_REST_Request $request ) {
		// Auto-scan for orphaned reviewer assignments when a coordinator or admin
		// loads the submissions list. Rate-limited to once per 5 min (transient).
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		if ( array_intersect( $roles, array( 'rrp_admin', 'rrp_coordinator', 'administrator' ) ) ) {
			Portal_Data::flag_orphaned_reviewer_assignments();
		}
		$data = Portal_Data::read_submissions();
		$list = array();
		foreach ( $data['submissions'] as $s ) {
			$list[] = array(
				'id'                 => $s['id'] ?? null,
				'type'               => $s['type'] ?? null,
				'submissionType'     => $s['submissionType'] ?? null,
				'status'             => $s['status'] ?? null,
				'workflowStatusClass' => self::status_to_badge_class( (string) ( $s['status'] ?? '' ) ),
				'title'              => $s['title'] ?? null,
				'submitterEmail'     => $s['submitterEmail'] ?? null,
				'submitterName'      => $s['submitterName'] ?? null,
				'createdAt'          => $s['createdAt'] ?? null,
				'assignedReviewers'  => $s['assignedReviewers'] ?? array(),
				'reviewerRemoved'    => !empty( $s['reviewerRemoved'] ),
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
		// Attach submission-type flags (allowMeetings) to response
		$_sub_config = Portal_Data::read_config();
		foreach ( $_sub_config['submissionTypes'] ?? array() as $_st ) {
			if ( $_st['id'] === ( $sub['submissionType'] ?? $sub['type'] ?? '' ) ) {
				$sub['allowMeetings'] = (bool) ( $_st['allowMeetings'] ?? false );
				break;
			}
		}
		// ── Enrich reviewer display names from WordPress user records ────────────
		// Names stored in reviewStages/assignedReviewers may be user_login at assignment
		// time; replace with the current WP display_name so all roles see real names.
		if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
			foreach ( $sub['reviewStages'] as &$_estage ) {
				if ( empty( $_estage['reviewers'] ) || ! is_array( $_estage['reviewers'] ) ) {
					continue;
				}
				foreach ( $_estage['reviewers'] as &$_erev ) {
					if ( ! empty( $_erev['email'] ) ) {
						$_ewp = get_user_by( 'email', strtolower( trim( (string) $_erev['email'] ) ) );
						if ( $_ewp && ! empty( $_ewp->display_name ) ) {
							$_erev['name'] = $_ewp->display_name;
						}
					}
				}
				unset( $_erev );
			}
			unset( $_estage );
		}
		if ( ! empty( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ) {
			foreach ( $sub['assignedReviewers'] as &$_erev ) {
				if ( ! empty( $_erev['email'] ) ) {
					$_ewp = get_user_by( 'email', strtolower( trim( (string) $_erev['email'] ) ) );
					if ( $_ewp && ! empty( $_ewp->display_name ) ) {
						$_erev['name'] = $_ewp->display_name;
					}
				}
			}
			unset( $_erev );
		}

		// Double-blind redaction
		$sub = self::apply_blind_review_redaction( $sub );

		// ── Gated Review visibility filtering ────────────────────────────────────
		// When gatedReview is enabled for the submission type:
		//  - Admin/Coordinator: see everything, plus a flag noting this is a gated review
		//  - Stage 0 reviewer (gatekeeper): sees all stages and the full coordination log
		//  - Higher-stage reviewers: see only their own stage; targeted coordination messages only
		//  - Student/submitter: sees only what Stage 1 has released; raw stage data is hidden
		$_gr_type = $sub['submissionType'] ?? $sub['type'] ?? '';
		$_gr_config = Portal_Data::read_config();
		$_gr_is_gated = false;
		foreach ( $_gr_config['submissionTypes'] ?? array() as $_gst ) {
			if ( ( $_gst['id'] ?? '' ) === $_gr_type && ! empty( $_gst['gatedReview'] ) ) {
				$_gr_is_gated = true;
				break;
			}
		}
		if ( $_gr_is_gated ) {
			$sub['gatedReview'] = true;
			$_gr_user        = wp_get_current_user();
			$_gr_email       = strtolower( trim( (string) $_gr_user->user_email ) );
			$_gr_roles       = (array) $_gr_user->roles;
			$_gr_is_admin    = (bool) array_intersect( $_gr_roles, array( 'rrp_admin', 'rrp_coordinator', 'administrator', 'rrp_faculty' ) );
			$_wf_sub          = Workflow_Engine::get_workflow_for_submission( $sub );
			$_viewer_role     = Workflow_Engine::compute_viewer_role( $sub, $_gr_email, $_gr_is_admin, $_wf_sub ?? array() );
			$sub['viewerRole'] = $_viewer_role;
			$_gr_is_gk        = $_viewer_role === 'gatekeeper';
			$_gr_is_submitter = $_viewer_role === 'submitter';

			if ( $_viewer_role === 'admin' ) {
				// Admins / coordinators see everything; mark so UI knows to show full log
				$sub['isGatedReviewAdmin'] = true;
			} elseif ( $_gr_is_gk ) {
				// Gatekeeper: sees all stages + coordination log
				$sub['isGatekeeper'] = true;
			} elseif ( $_gr_is_submitter ) {
				// Student: sees stage names, real reviewer names, and overall review progress.
				// Hidden: per-reviewer decision votes, feedback text, coordination log, raw releases.
				unset( $sub['coordinationLog'] );
				$_gr_releases = isset( $sub['gatedReleases'] ) && is_array( $sub['gatedReleases'] ) ? $sub['gatedReleases'] : array();
				$sub['latestGatedRelease'] = ! empty( $_gr_releases ) ? end( $_gr_releases ) : null;
				unset( $sub['gatedReleases'] );
				// Only anonymize if this type is also configured as a blind review
				$_gr_blind_also = false;
				foreach ( $_gr_config['submissionTypes'] ?? array() as $_gst2 ) {
					if ( ( $_gst2['id'] ?? '' ) === $_gr_type ) {
						$_gr_blind_also = ! empty( $_gst2['blindReview'] );
						break;
					}
				}
				if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
					foreach ( $sub['reviewStages'] as $_gri => &$_grs ) {
						$_grs_reviewers = $_grs['reviewers'] ?? array();
						$_grs_decisions = is_array( $_grs['decisions'] ?? null ) ? $_grs['decisions'] : array();
						// Gatekeeper stage: mark as completed/forwarded if approved
						$_gk_idx_for_student = $_wf_sub ? Workflow_Engine::gatekeeper_stage_index( $_wf_sub ) : 0;
						if ( $_gri === $_gk_idx_for_student && Portal_Data::is_stage_approved( $_grs, $sub, $_gri ) ) {
							$_grs['stageCompleted'] = true;
						}
						// Higher stages: set gatekeeperNotifiedAt dynamically if all reviewers decided but flag not stored
						if ( $_gri > $_gk_idx_for_student && empty( $_grs['gatekeeperNotifiedAt'] ) && ! empty( $_grs_reviewers ) ) {
							$_all_dec = true;
							foreach ( $_grs_reviewers as $_grs_rv ) {
								$_grs_ek = strtolower( trim( (string) ( $_grs_rv['email'] ?? '' ) ) );
								if ( empty( $_grs_decisions[ $_grs_ek ] ) ) { $_all_dec = false; break; }
							}
							if ( $_all_dec ) { $_grs['gatekeeperNotifiedAt'] = 'derived'; }
						}
						// Always strip per-reviewer votes and feedback text
						unset( $_grs['decisions'], $_grs['feedback'] );
						// Anonymize names only when also double-blind; otherwise keep real names
						if ( $_gr_blind_also && ! empty( $_grs['reviewers'] ) ) {
							$_grs['reviewers'] = array_map( function( $r, $ridx ) {
								return array( 'name' => 'Reviewer ' . ( $ridx + 1 ), 'email' => '' );
							}, $_grs['reviewers'], array_keys( $_grs['reviewers'] ) );
						}
					}
					unset( $_grs );
				}
				// Strip reviewer feedback audit entries — student only sees gatekeeper-released decisions
				if ( ! empty( $sub['auditLog'] ) && is_array( $sub['auditLog'] ) ) {
					$sub['auditLog'] = array_values( array_filter( $sub['auditLog'], function( $e ) {
						return ( $e['action'] ?? '' ) !== 'feedback_added';
					} ) );
				}
			} else {
				// Higher-stage reviewer: sees own stage only; strip others, coordination log, and primary communication
				unset( $sub['coordinationLog'], $sub['gatedReleases'], $sub['internalComments'] );
				$_gr_my_stages = array();
				foreach ( $sub['reviewStages'] ?? array() as $_gri => $_grs ) {
					foreach ( $_grs['reviewers'] ?? array() as $_grr ) {
						if ( strtolower( trim( (string) ( $_grr['email'] ?? '' ) ) ) === $_gr_email ) {
							$_gr_my_stages[] = $_gri;
							break;
						}
					}
				}
				if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
					foreach ( $sub['reviewStages'] as $_gri => &$_grs ) {
						if ( ! in_array( $_gri, $_gr_my_stages, true ) ) {
							unset( $_grs['decisions'], $_grs['feedback'] );
							// Also hide reviewer identities of stages they are not in (inc. stage 0)
							if ( ! empty( $_grs['reviewers'] ) ) {
								$_grs['reviewers'] = array();
							}
						}
					}
					unset( $_grs );
				}
			}
		}

		// ── Phase 4: server-computed stage metadata ─────────────────────────────────
		$_p4_wf         = Workflow_Engine::get_workflow_for_submission( $sub );
		$_p4_vr         = $sub['viewerRole'] ?? 'public';
		$_p4_is_sub     = $_p4_vr === 'submitter';
		$_p4_is_gk      = $_p4_vr === 'gatekeeper';
		$_p4_is_comm    = in_array( $_p4_vr, array( 'committee', 'advisory' ), true );
		$_p4_gk_idx     = $_p4_wf ? Workflow_Engine::gatekeeper_stage_index( $_p4_wf ) : 0;
		$_p4_stages     = $sub['reviewStages'] ?? array();
		$_p4_sub_status = $sub['status'] ?? '';

		// Active stage index — for students use stageCompleted/gatekeeperNotifiedAt (decisions stripped)
		$_p4_active = -1;
		foreach ( $_p4_stages as $_p4i => $_p4s ) {
			if ( $_p4s['skipped'] ?? false ) continue;
			$_p4_revs = $_p4s['reviewers'] ?? array();
			if ( empty( $_p4_revs ) ) { $_p4_active = $_p4i; break; }
			if ( $_p4_is_sub ) {
				$_p4_stage_done = ! empty( $_p4s['stageCompleted'] ) || ! empty( $_p4s['gatekeeperNotifiedAt'] );
				if ( ! $_p4_stage_done ) { $_p4_active = $_p4i; break; }
			} else {
				if ( ! Portal_Data::is_stage_approved( $_p4s, $sub, $_p4i ) ) { $_p4_active = $_p4i; break; }
			}
		}

		// Build computedStages[]
		$_p4_cs = array();
		foreach ( $_p4_stages as $_p4i => $_p4s ) {
			$_p4_approved = ! $_p4_is_sub && Portal_Data::is_stage_approved( $_p4s, $sub, $_p4i );
			$_p4_skipped  = (bool) ( $_p4s['skipped'] ?? false );
			$_p4_decs_raw = $_p4s['decisions'] ?? array();
			$_p4_has_rev  = array_filter( $_p4_decs_raw, function ( $d ) { return strtolower( (string) $d ) === 'needs revision'; } );
			$_p4_is_gk_s  = ( $_p4i === $_p4_gk_idx );
			$_p4_future   = ! $_p4_skipped && ! $_p4_approved && $_p4_active !== -1 && $_p4i > $_p4_active;
			// Default status labels
			if ( $_p4_skipped )                { $_p4_sc = 'rrp-stage-skipped';     $_p4_sl = 'Skipped'; }
			elseif ( $_p4_approved )           { $_p4_sc = 'rrp-stage-approved';    $_p4_sl = "\u2713 Approved"; }
			elseif ( $_p4_future )             { $_p4_sc = 'rrp-stage-not-started'; $_p4_sl = 'Not Started'; }
			elseif ( ! empty( $_p4_has_rev ) ) { $_p4_sc = 'rrp-stage-revision';    $_p4_sl = 'Revision Requested'; }
			else                               { $_p4_sc = 'rrp-stage-pending';     $_p4_sl = 'In Progress'; }
			// Role-specific gated overrides
			if ( ! empty( $sub['gatedReview'] ) ) {
				if ( $_p4_is_comm && $_p4_is_gk_s && $_p4_approved ) {
					$_p4_sc = 'rrp-stage-approved'; $_p4_sl = "\u2713 Review Complete";
				} elseif ( $_p4_is_gk && $_p4_is_gk_s && $_p4_approved ) {
					$_p4_gk_rels  = $sub['gatedReleases'] ?? array();
					$_p4_last_rel = ! empty( $_p4_gk_rels ) ? end( $_p4_gk_rels ) : null;
					$_p4_rel_cur  = false;
					if ( $_p4_last_rel ) {
						if ( isset( $_p4_last_rel['round'] ) ) {
							$_p4_rel_cur = (int) $_p4_last_rel['round'] >= (int) ( $sub['currentRound'] ?? 0 );
						} else {
							$_p4_rel_ts  = (int) strtotime( (string) ( $_p4_last_rel['releasedAt'] ?? '' ) );
							$_p4_rev_ts  = (int) strtotime( (string) ( $_p4s['revisionSubmittedAt'] ?? '' ) );
							$_p4_rel_cur = $_p4_rev_ts === 0 || $_p4_rel_ts >= $_p4_rev_ts;
						}
					}
					$_p4_any_hs = false;
					foreach ( array_slice( $_p4_stages, $_p4_gk_idx + 1, null, true ) as $_p4hs ) {
						if ( $_p4hs['skipped'] ?? false ) continue;
						if ( ! empty( $_p4hs['gatekeeperNotifiedAt'] ) ) { $_p4_any_hs = true; break; }
						$_p4_hs_r = $_p4hs['reviewers'] ?? array();
						$_p4_hs_d = $_p4hs['decisions'] ?? array();
						if ( ! empty( $_p4_hs_r ) ) {
							$_p4_all_d = true;
							foreach ( $_p4_hs_r as $_p4hr ) {
								$_p4_hk = strtolower( trim( (string) ( $_p4hr['email'] ?? '' ) ) );
								if ( empty( $_p4_hs_d[ $_p4_hk ] ) ) { $_p4_all_d = false; break; }
							}
							if ( $_p4_all_d ) { $_p4_any_hs = true; break; }
						}
					}
					if ( $_p4_rel_cur && $_p4_last_rel ) {
						$_p4_sc = 'rrp-stage-approved'; $_p4_sl = "\u2713 Decision Released";
					} elseif ( $_p4_any_hs ) {
						$_p4_sc = 'rrp-stage-revision'; $_p4_sl = "\u23f3 Reviewing Higher Stage Feedback";
					} else {
						$_p4_sc = 'rrp-stage-approved'; $_p4_sl = "\u2713 Forwarded to Higher Stage Review";
					}
				} elseif ( $_p4_is_sub ) {
					$_p4_completed = (bool) ( $_p4s['stageCompleted'] ?? false );
					$_p4_gk_not    = ! empty( $_p4s['gatekeeperNotifiedAt'] );
					if ( $_p4_skipped ) {
						$_p4_sc = 'rrp-stage-skipped'; $_p4_sl = 'Skipped';
					} elseif ( empty( $_p4s['reviewers'] ) ) {
						$_p4_sc = 'rrp-stage-not-started'; $_p4_sl = 'Pending';
					} elseif ( $_p4_sub_status === 'Revision Required' && ( $_p4_completed || $_p4_gk_not ) ) {
						$_p4_sc = 'rrp-stage-revision'; $_p4_sl = 'Revision Requested';
					} elseif ( $_p4_is_gk_s && $_p4_completed ) {
						$_p4_sc = 'rrp-stage-approved'; $_p4_sl = 'Forwarded to Higher Stage Review';
					} elseif ( $_p4_gk_not ) {
						$_p4_sc = 'rrp-stage-approved'; $_p4_sl = "Review Complete \u2014 Awaiting Primary Reviewer";
					} elseif ( $_p4_is_gk_s && $_p4_sub_status === 'Revision Required' ) {
						$_p4_sc = 'rrp-stage-revision'; $_p4_sl = 'Revision Requested';
					} else {
						$_p4_sc = 'rrp-stage-pending'; $_p4_sl = 'Under Review';
					}
				}
			}
			$_p4_cs[] = array(
				'stageName'     => $_p4s['stageName'] ?? '',
				'stageRole'     => $_p4_wf ? Workflow_Engine::get_stage_role( $_p4_wf, $_p4i ) : ( $_p4_is_gk_s ? 'gatekeeper' : 'committee' ),
				'statusLabel'   => $_p4_sl,
				'statusClass'   => $_p4_sc,
				'reviewers'     => $_p4s['reviewers'] ?? array(),
				'showDecisions' => ! $_p4_is_sub,
				'decisions'     => $_p4_is_sub ? array() : $_p4_decs_raw,
				'feedback'      => $_p4s['feedback'] ?? array(),
				'skipped'       => $_p4_skipped,
				'isActiveStage' => ( $_p4i === $_p4_active ),
			);
		}
		$sub['computedStages'] = $_p4_cs;

		// pendingRelease: gatekeeper has un-released higher-stage review result
		$_p4_pending = false;
		if ( $_p4_is_gk ) {
			$_p4_gk_r     = $sub['gatedReleases'] ?? array();
			$_p4_last_r   = ! empty( $_p4_gk_r ) ? end( $_p4_gk_r ) : null;
			$_p4_last_rnd = ( $_p4_last_r && isset( $_p4_last_r['round'] ) ) ? (int) $_p4_last_r['round'] : -1;
			$_p4_cur_rnd  = (int) ( $sub['currentRound'] ?? 0 );
			$_p4_last_rts = $_p4_last_r ? (int) strtotime( (string) ( $_p4_last_r['releasedAt'] ?? '' ) ) : 0;
			foreach ( array_slice( $_p4_stages, $_p4_gk_idx + 1, null, true ) as $_p4hs ) {
				if ( $_p4hs['skipped'] ?? false ) continue;
				$_p4_hs_done = ! empty( $_p4hs['gatekeeperNotifiedAt'] );
				if ( ! $_p4_hs_done ) {
					$_p4_hs_r = $_p4hs['reviewers'] ?? array();
					$_p4_hs_d = $_p4hs['decisions'] ?? array();
					if ( ! empty( $_p4_hs_r ) ) {
						$_p4_all_d = true;
						foreach ( $_p4_hs_r as $_p4hr ) {
							$_p4_hk = strtolower( trim( (string) ( $_p4hr['email'] ?? '' ) ) );
							if ( empty( $_p4_hs_d[ $_p4_hk ] ) ) { $_p4_all_d = false; break; }
						}
						if ( $_p4_all_d ) { $_p4_hs_done = true; }
					}
				}
				if ( $_p4_hs_done ) {
					if ( null === $_p4_last_r ) { $_p4_pending = true; break; }
					if ( $_p4_last_rnd >= 0 && $_p4_last_rnd < $_p4_cur_rnd ) { $_p4_pending = true; break; }
					$_p4_hs_ts = ! empty( $_p4hs['gatekeeperNotifiedAt'] ) ? (int) strtotime( (string) $_p4hs['gatekeeperNotifiedAt'] ) : 0;
					if ( $_p4_hs_ts > $_p4_last_rts ) { $_p4_pending = true; break; }
				}
			}
		}
		$sub['pendingRelease'] = $_p4_pending;

		// allowedActions: what the current viewer may do
		$_p4_acts      = array();
		$_p4_wdrawable = array( 'Submitted - Awaiting Review', 'Submitted', 'Under Initial Review', 'Administrative Review', 'Revision Required', 'Revision Submitted' );
		if ( $_p4_is_sub && in_array( $_p4_sub_status, $_p4_wdrawable, true ) )                                                                                                                $_p4_acts[] = 'withdraw';
		if ( $_p4_is_sub && $_p4_sub_status === 'Rejected' && ( empty( $sub['appeal'] ) || in_array( $sub['appeal']['status'] ?? '', array( 'upheld', 'overturned' ), true ) ) )               $_p4_acts[] = 'appeal';
		if ( $_p4_is_sub && $_p4_sub_status === 'Full Paper Invited' )                                                                                                                          $_p4_acts[] = 'full-paper';
		$sub['allowedActions'] = $_p4_acts;

		// workflowStatusClass: CSS badge class for the submission status
		$_p4_swc = strtolower( str_replace( array( ' ', '_' ), '-', $_p4_sub_status ) );
		if      ( in_array( $_p4_swc, array( 'approved', 'conditionally-approved', 'confirmed-for-presentation', 'published', 'approved-for-submission', 'accepted', 'conditionally-accepted' ), true ) ) { $sub['workflowStatusClass'] = 'rrp-dec-approved'; }
		elseif  ( $_p4_swc === 'rejected' )                                                                                                                                                                 { $sub['workflowStatusClass'] = 'rrp-dec-rejected'; }
		elseif  ( in_array( $_p4_swc, array( 'needs-revision', 'revision-required', 'revision', 'revision-submitted' ), true ) )                                                                            { $sub['workflowStatusClass'] = 'rrp-dec-revision'; }
		elseif  ( $_p4_swc === 'draft' )                                                                                                                                                                    { $sub['workflowStatusClass'] = 'rrp-dec-draft'; }
		elseif  ( $_p4_swc === 'submitted' )                                                                                                                                                                { $sub['workflowStatusClass'] = 'rrp-dec-submitted'; }
		elseif  ( in_array( $_p4_swc, array( 'appeal-pending', 'appeal-under-review' ), true ) )                                                                                                            { $sub['workflowStatusClass'] = 'rrp-dec-revision'; }
		elseif  ( $_p4_swc === 'full-paper-invited' )                                                                                                                                                       { $sub['workflowStatusClass'] = 'rrp-dec-inreview'; }
		elseif  ( strpos( $_p4_swc, 'review' ) !== false || strpos( $_p4_swc, 'in-progress' ) !== false )                                                                                                   { $sub['workflowStatusClass'] = 'rrp-dec-inreview'; }
		elseif  ( in_array( $_p4_swc, array( 'withdrawn', 'cancelled' ), true ) )                                                                                                                           { $sub['workflowStatusClass'] = 'rrp-dec-withdrawn'; }
		else                                                                                                                                                                                                 { $sub['workflowStatusClass'] = 'rrp-dec-pending'; }
		// ── End Phase 4 ──────────────────────────────────────────────────────────

		return new WP_REST_Response( $sub, 200 );
	}

	/**
	 * Redact identities based on double-blind review setting for the submission type.
	 * - Reviewer: sees "Anonymous Author" instead of submitter name/email.
	 * - Submitter (pre-final-decision): sees "Reviewer 1, 2..." instead of reviewer names.
	 * - Admin / Coordinator / Faculty: no redaction.
	 */
	private static function apply_blind_review_redaction( array $sub ): array {
		$config = Portal_Data::read_config();
		$blind  = false;
		foreach ( $config['submissionTypes'] ?? array() as $t ) {
			if ( $t['id'] === ( $sub['submissionType'] ?? $sub['type'] ?? '' ) ) {
				$blind = (bool) ( $t['blindReview'] ?? false );
				break;
			}
		}
		if ( ! $blind ) {
			return $sub;
		}
		$sub['blindReview'] = true; // Signal to JS to show the blinding banner
		$user    = wp_get_current_user();
		$roles   = (array) $user->roles;
		if ( array_intersect( $roles, array( 'rrp_admin', 'rrp_coordinator', 'administrator', 'rrp_faculty' ) ) ) {
			return $sub; // Managers always see real identities
		}
		$user_email      = strtolower( trim( (string) $user->user_email ) );
		$submitter_email = strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) );
		$is_submitter    = $user_email === $submitter_email;
		$final_statuses  = array( 'approved', 'rejected', 'published', 'withdrawn', 'cancelled' );
		$final_issued    = in_array( strtolower( (string) ( $sub['status'] ?? '' ) ), $final_statuses, true );
		if ( ! $is_submitter ) {
			// Reviewer path: hide submitter identity AND other reviewers' identities
			$sub['submitterName']  = 'Anonymous Author';
			$sub['submitterEmail'] = '';
			// Also anonymize other reviewers in stages so they can't see each other
			if ( ! empty( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ) {
				$anon_idx = 1;
				foreach ( $sub['assignedReviewers'] as &$ar ) {
					$ar_email = strtolower( trim( (string) ( $ar['email'] ?? '' ) ) );
					if ( $ar_email !== $user_email ) {
						$ar['name']  = 'Reviewer ' . $anon_idx;
						$ar['email'] = '';
					}
					$anon_idx++;
				}
				unset( $ar );
			}
			if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
				foreach ( $sub['reviewStages'] as &$stage ) {
					$anon_idx = 1;
					$reviewer_map = array(); // original email → anon label (stable across decisions/feedback)
					if ( ! empty( $stage['reviewers'] ) && is_array( $stage['reviewers'] ) ) {
						foreach ( $stage['reviewers'] as &$rv ) {
							$rv_email = strtolower( trim( (string) ( $rv['email'] ?? '' ) ) );
							if ( $rv_email !== $user_email ) {
								$label = 'Reviewer ' . $anon_idx;
								$reviewer_map[ $rv_email ] = $label;
								$rv['name']  = $label;
								$rv['email'] = '';
							} else {
								$reviewer_map[ $rv_email ] = $rv['name'] ?? ( 'Reviewer ' . $anon_idx );
							}
							$anon_idx++;
						}
						unset( $rv );
					}
					// Re-key decisions: keep current user's decision under their real email, others anonymized
					if ( ! empty( $stage['decisions'] ) ) {
						$new_decs = array();
						$fb_idx   = 1;
						foreach ( $stage['decisions'] as $dec_email => $dec_val ) {
							if ( strtolower( trim( $dec_email ) ) === $user_email ) {
								$new_decs[ $dec_email ] = $dec_val;
							} else {
								$new_decs[ 'reviewer_' . $fb_idx ] = $dec_val;
							}
							$fb_idx++;
						}
						$stage['decisions'] = $new_decs;
					}
					// Anonymize feedback from other reviewers
					if ( ! empty( $stage['feedback'] ) && is_array( $stage['feedback'] ) ) {
						$fb_idx = 1;
						foreach ( $stage['feedback'] as &$fb ) {
							$fb_email = strtolower( trim( (string) ( $fb['email'] ?? '' ) ) );
							if ( $fb_email !== $user_email ) {
								$fb['name']  = 'Reviewer ' . $fb_idx;
								$fb['email'] = '';
							}
							$fb_idx++;
						}
						unset( $fb );
					}
				}
				unset( $stage );
			}
		} elseif ( ! $final_issued ) {
			// Submitter path (pre-decision): hide all reviewer identities
			if ( ! empty( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ) {
				foreach ( $sub['assignedReviewers'] as &$ar ) {
					$ar['name']  = 'Anonymous Reviewer';
					$ar['email'] = '';
				}
				unset( $ar );
			}
			if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
				foreach ( $sub['reviewStages'] as &$stage ) {
					$anon_idx = 1;
					if ( ! empty( $stage['reviewers'] ) && is_array( $stage['reviewers'] ) ) {
						foreach ( $stage['reviewers'] as &$rv ) {
							$rv['name']  = 'Reviewer ' . $anon_idx++;
							$rv['email'] = '';
						}
						unset( $rv );
					}
					if ( ! empty( $stage['decisions'] ) ) {
						$anon_decs = array();
						$idx       = 1;
						foreach ( $stage['decisions'] as $dec ) {
							$anon_decs[ 'reviewer_' . $idx++ ] = $dec;
						}
						$stage['decisions'] = $anon_decs;
					}
					if ( ! empty( $stage['feedback'] ) && is_array( $stage['feedback'] ) ) {
						$fb_idx = 1;
						foreach ( $stage['feedback'] as &$fb ) {
							$fb['name']  = 'Reviewer ' . $fb_idx++;
							$fb['email'] = '';
						}
						unset( $fb );
					}
				}
				unset( $stage );
			}
		}
		return $sub;
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
		$sub    = self::apply_blind_review_redaction( $sub );
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
			self::fire_webhooks( 'submission.withdrawn', array(
				'submissionId' => $sub['id'] ?? '',
				'title'        => $sub['title'] ?? '',
				'submitter'    => $sub['submitterEmail'] ?? '',
			) );
			// Email notification to submitter
			$_w_to = $sub['submitterEmail'] ?? '';
			if ( $_w_to && is_email( $_w_to ) && self::user_wants_notif( $_w_to, 'submission_status_changed' ) ) {
				wp_mail(
					$_w_to,
					'Research Review Portal: Submission Withdrawn (' . ( $sub['id'] ?? '' ) . ')',
					'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\nYour submission \"" . ( $sub['title'] ?? '' ) . '" (ID: ' . ( $sub['id'] ?? '' ) . ") has been successfully withdrawn.\n\nIf you did not request this action, please contact your coordinator."
				);
			}
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// Coordinator / Admin cancel — any non-terminal submission can be cancelled with a reason.
		if ( isset( $body['action'] ) && $body['action'] === 'cancel' ) {
			if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
				return new WP_REST_Response( array( 'error' => 'Insufficient permissions to cancel submissions.' ), 403 );
			}
			$_already_done = array( 'Withdrawn', 'Cancelled' );
			if ( in_array( $sub['status'] ?? '', $_already_done, true ) ) {
				return new WP_REST_Response( array( 'error' => 'Submission is already ' . ( $sub['status'] ?? '' ) . '.' ), 400 );
			}
			$_c_reason = isset( $body['reason'] ) ? sanitize_text_field( (string) $body['reason'] ) : 'Cancelled by coordinator.';
			$_c_user   = wp_get_current_user();
			$_c_by     = $_c_user->user_email ?: $_c_user->user_login;
			$_c_name   = $_c_user->display_name ?: $_c_user->user_login;
			$data['submissions'][ $idx ] = array_merge( $sub, array(
				'status'          => 'Cancelled',
				'cancelledAt'     => gmdate( 'c' ),
				'cancelledBy'     => $_c_by,
				'cancelledByName' => $_c_name,
				'cancelReason'    => $_c_reason,
			) );
			self::append_audit_log( $data, $idx, 'status_changed', 'Submission cancelled by ' . $_c_name . '. Reason: ' . $_c_reason );
			Portal_Data::write_submissions( $data );
			// Email notification to submitter
			$_c_to = $sub['submitterEmail'] ?? '';
			if ( $_c_to && is_email( $_c_to ) && self::user_wants_notif( $_c_to, 'submission_status_changed' ) ) {
				wp_mail(
					$_c_to,
					'Research Review Portal: Submission Cancelled (' . ( $sub['id'] ?? '' ) . ')',
					'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\nYour submission \"" . ( $sub['title'] ?? '' ) . '" (ID: ' . ( $sub['id'] ?? '' ) . ") has been cancelled.\n\nReason: " . $_c_reason . "\n\nIf you have questions, please contact your coordinator."
				);
			}
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// submit_full_paper: two-phase submission phase 2 transition
		if ( isset( $body['action'] ) && $body['action'] === 'submit_full_paper' ) {
			if ( ( $sub['status'] ?? '' ) !== 'Full Paper Invited' ) {
				return new WP_REST_Response( array( 'error' => 'Submission is not in "Full Paper Invited" status.' ), 400 );
			}
			$fp_config = Portal_Data::read_config();
			$fp_type_cfg = null;
			foreach ( $fp_config['submissionTypes'] ?? array() as $_fst ) {
				if ( ( $_fst['id'] ?? '' ) === ( $sub['type'] ?? $sub['submissionType'] ?? '' ) ) { $fp_type_cfg = $_fst; break; }
			}
			$fp_abstract_only = (int) max( 1, $fp_type_cfg['abstractOnlyStages'] ?? 1 );
			$fp_stages = $sub['reviewStages'] ?? array();
			for ( $fpi = $fp_abstract_only; $fpi < count( $fp_stages ); $fpi++ ) {
				$fp_stages[ $fpi ]['decisions'] = array();
				$fp_stages[ $fpi ]['feedback']  = array();
				if ( isset( $fp_stages[ $fpi ]['skipped'] ) ) $fp_stages[ $fpi ]['skipped'] = false;
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array(
				'phase'        => 2,
				'reviewStages' => $fp_stages,
				'status'       => 'Revision Submitted',
			) );
			self::append_audit_log( $data, $idx, 'status_changed', 'Full paper submitted (phase 2).' );
			Portal_Data::write_submissions( $data );
			$_fp_next = $fp_stages[ $fp_abstract_only ] ?? null;
			if ( $_fp_next ) self::notify_stage_reviewers( $data['submissions'][ $idx ], $_fp_next, $fp_abstract_only );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// Lock: reject all further modifications on Withdrawn or Cancelled submissions.
		$_locked_statuses = array( 'Withdrawn', 'Cancelled' );
		if ( in_array( $sub['status'] ?? '', $_locked_statuses, true ) ) {
			return new WP_REST_Response( array( 'error' => 'This submission is ' . ( $sub['status'] ) . ' and cannot be modified.' ), 409 );
		}

		if ( ! empty( $body['status'] ) && is_string( $body['status'] ) && in_array( trim( $body['status'] ), Portal_Data::PUBLIC_STATUSES, true ) ) {
			// Only coordinators and admins may promote a submission to a terminal
			// public status (Accepted, Published, etc.).  Without this gate a
			// submitter or assigned reviewer could approve their own submission.
			if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
				return new WP_REST_Response( array( 'error' => 'Insufficient permissions to set this submission status.' ), 403 );
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'status' => trim( $body['status'] ) ) );
			self::append_audit_log( $data, $idx, 'status_changed', 'Status set to "' . trim( $body['status'] ) . '".' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( isset( $body['assignedReviewers'] ) && is_array( $body['assignedReviewers'] ) ) {
			if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
				return new WP_REST_Response( array( 'error' => 'Insufficient permissions to modify reviewer assignments.' ), 403 );
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'assignedReviewers' => $body['assignedReviewers'] ) );
			self::append_audit_log( $data, $idx, 'reviewers_assigned', 'Assigned reviewers updated.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		if ( isset( $body['reviewStages'] ) && is_array( $body['reviewStages'] ) ) {
			if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
				return new WP_REST_Response( array( 'error' => 'Insufficient permissions to modify review stages.' ), 403 );
			}
			// Enforce single-user limit using the workflow stages library
			$_ws_cfg     = Portal_Data::read_config();
			$_ws_singles = array();
			foreach ( $_ws_cfg['workflowStages'] ?? array() as $_ws ) {
				if ( ! empty( $_ws['singleUser'] ) ) {
					$_ws_singles[ strtolower( trim( (string) ( $_ws['name'] ?? '' ) ) ) ] = true;
				}
			}
			foreach ( $body['reviewStages'] as $_rs_chk ) {
				$_rs_sn = strtolower( trim( (string) ( $_rs_chk['stageName'] ?? '' ) ) );
				if ( isset( $_ws_singles[ $_rs_sn ] ) && count( (array) ( $_rs_chk['reviewers'] ?? array() ) ) > 1 ) {
					return new WP_REST_Response( array( 'error' => 'Stage \"' . sanitize_text_field( (string) ( $_rs_chk['stageName'] ?? '' ) ) . '\" is configured as single-user. Only one reviewer may be assigned.' ), 400 );
				}
			}
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
				// Sanitize reviewer entries and validate optional deadline field
				$sanitized_reviewers = array();
				foreach ( $reviewers as $r ) {
					$em  = isset( $r['email'] ) ? strtolower( trim( (string) $r['email'] ) ) : '';
					$rev = array(
						'id'    => isset( $r['id'] )   ? sanitize_text_field( (string) $r['id'] )   : null,
						'name'  => isset( $r['name'] ) ? sanitize_text_field( (string) $r['name'] ) : '',
						'email' => $em,
					);
					if ( isset( $r['deadline'] ) && $r['deadline'] !== '' ) {
						$dl = sanitize_text_field( (string) $r['deadline'] );
						// Accept ISO date (YYYY-MM-DD) or ISO datetime; reject anything else
						if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $dl ) && strtotime( $dl ) !== false ) {
							$rev['deadline'] = $dl;
						}
					}
					if ( $em && ! self::find_by_email( $assigned, $em ) ) {
						$assigned[] = array(
							'id'    => $rev['id'],
							'name'  => $rev['name'],
							'email' => $em,
						);
					}
					$sanitized_reviewers[] = $rev;
				}
				$merged[] = array(
					'stageName'           => $name,
					'stageIndex'          => $rs['stageIndex'] ?? 0,
					'reviewers'           => $sanitized_reviewers,
					'decisions'           => $existing_rs['decisions'] ?? array(),
					'feedback'            => $existing_rs['feedback'] ?? array(),
					'revisionSubmittedAt' => $existing_rs['revisionSubmittedAt'] ?? null,
					'requiredCount'       => $rs['requiredCount'] ?? $existing_rs['requiredCount'] ?? null,
				);
			}
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $merged, 'assignedReviewers' => $assigned ) );
			// Clear the reviewer-removed flag now that assignments have been updated.
			unset( $data['submissions'][ $idx ]['reviewerRemoved'] );
			self::append_audit_log( $data, $idx, 'reviewers_assigned', 'Review stages and reviewers configured.' );
			Portal_Data::write_submissions( $data );
			return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
		}

		// stageDecision: record reviewer decision (Approved|Rejected|Pending|Needs Revision)
		$valid_decisions = array( 'Approved', 'Rejected', 'Pending', 'Needs Revision' );
		if ( isset( $body['stageDecision'] ) && is_array( $body['stageDecision'] ) ) {
			$sd = $body['stageDecision'];
			$stage_name = isset( $sd['stageName'] ) ? trim( (string) $sd['stageName'] ) : '';
			$reviewer_email = isset( $sd['reviewerEmail'] ) ? strtolower( trim( (string) $sd['reviewerEmail'] ) ) : '';
			$decision = isset( $sd['decision'] ) ? trim( (string) $sd['decision'] ) : '';
			if ( ! $stage_name || ! $reviewer_email || ! in_array( $decision, $valid_decisions, true ) ) {
				return new WP_REST_Response( array( 'error' => 'stageDecision must include stageName, reviewerEmail, and decision (Approved|Rejected|Pending|Needs Revision).' ), 400 );
			}
			// Reviewers may only record their own decision; coordinators/admins may act on behalf
			if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
				$current_email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
				if ( $reviewer_email !== $current_email ) {
					return new WP_REST_Response( array( 'error' => 'You may only record your own decision.' ), 403 );
				}
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
			// For gated reviews: higher-stage (stage index > 0) decisions must NOT directly
			// change the submission status — only the gatekeeper (stage 0) releases decisions.
			$_gated_sub_type = $sub['submissionType'] ?? $sub['type'] ?? '';
			$_is_gated_review = false;
			foreach ( Portal_Data::read_config()['submissionTypes'] ?? array() as $_gst_chk ) {
				if ( ( $_gst_chk['id'] ?? '' ) === $_gated_sub_type && ! empty( $_gst_chk['gatedReview'] ) ) {
					$_is_gated_review = true; break;
				}
			}
			$_sd_wf       = Workflow_Engine::get_workflow_for_submission( $sub );
			$_is_gk_stage = $_sd_wf
				? Workflow_Engine::is_gatekeeper_stage( $_sd_wf, $stage_index )
				: ( $stage_index === 0 );
			$_sd_gk_idx   = $_sd_wf ? Workflow_Engine::gatekeeper_stage_index( $_sd_wf ) : 0;
			$new_status = $sub['status'];
			if ( ! $_is_gated_review || $_is_gk_stage ) {
				// Non-gated OR gatekeeper stage: apply decision to submission status normally
				if ( $decision === 'Rejected' ) {
					$new_status = 'Rejected';
				} elseif ( $decision === 'Needs Revision' ) {
					$new_status = 'Revision Required';
				}
			}
			// else: higher-stage gated reviewer — status stays as-is until gatekeeper acts
			$data['submissions'][ $idx ] = array_merge( $sub, array( 'reviewStages' => $review_stages, 'status' => $new_status ) );
			if ( ! $_is_gated_review || $_is_gk_stage ) {
				$data['submissions'][ $idx ]['status'] = Portal_Data::derive_submission_status( $data['submissions'][ $idx ] );
			}
			self::append_audit_log( $data, $idx, 'decision_recorded', 'Decision "' . $decision . '" recorded for stage "' . $stage_name . '" by ' . $reviewer_email . '.' );
			Portal_Data::write_submissions( $data );
			$updated = $data['submissions'][ $idx ];
			// Fire webhooks for key events
			$_wh_status = strtolower( (string) ( $updated['status'] ?? '' ) );
			self::fire_webhooks( 'review.completed', array(
				'submissionId' => $updated['id'] ?? '',
				'title'        => $updated['title'] ?? '',
				'stage'        => $stage_name,
				'reviewer'     => $reviewer_email,
				'decision'     => $decision,
			) );
			if ( $decision === 'Rejected' ) {
				// For gated higher-stage reviewers, don't fire submitter-facing rejection webhook
				if ( ! $_is_gated_review || $_is_gk_stage ) {
					self::fire_webhooks( 'submission.rejected', array(
						'submissionId' => $updated['id'] ?? '',
						'title'        => $updated['title'] ?? '',
						'stage'        => $stage_name,
					) );
				}
			} elseif ( ( ! $_is_gated_review || $_is_gk_stage ) && in_array( $_wh_status, array( 'approved', 'confirmed for presentation', 'published', 'approved for submission', 'accepted' ), true ) ) {
				self::fire_webhooks( 'submission.approved', array(
					'submissionId' => $updated['id'] ?? '',
					'title'        => $updated['title'] ?? '',
					'status'       => $updated['status'] ?? '',
				) );
			}
			// Auto-advance: notify next-stage reviewers when this stage is now fully approved.
			// For gated reviews: when a higher stage (index > 0) is complete (all decided),
			// notify the gatekeeper instead of advancing to the next stage automatically.
			$_stage_all_decided = ! empty( $review_stages[ $stage_index ]['reviewers'] );
			if ( $_stage_all_decided ) {
				foreach ( $review_stages[ $stage_index ]['reviewers'] as $_rv ) {
					$_rek = strtolower( trim( (string) ( $_rv['email'] ?? '' ) ) );
					if ( empty( $review_stages[ $stage_index ]['decisions'][ $_rek ] ) ) {
						$_stage_all_decided = false; break;
					}
				}
			}
			if ( $_is_gated_review && ! $_is_gk_stage && $_stage_all_decided ) {
				// Notify gatekeeper that this higher stage has completed its review
				$_gk_stage = $review_stages[ $_sd_gk_idx ] ?? $review_stages[0] ?? array();
				foreach ( $_gk_stage['reviewers'] ?? array() as $_gkr ) {
					$_gk_to = $_gkr['email'] ?? '';
					if ( $_gk_to && is_email( $_gk_to ) && self::user_wants_notif( $_gk_to, 'submission_status_changed' ) ) {
						wp_mail(
							$_gk_to,
							'Research Review Portal: Stage Review Complete — Action Required (' . ( $updated['id'] ?? '' ) . ')',
							'Hello ' . ( $_gkr['name'] ?? '' ) . ",\n\nAll reviewers in stage \"" . $stage_name . '" have completed their review for submission "' . ( $updated['title'] ?? '' ) . '" (' . ( $updated['id'] ?? '' ) . ").\n\nPlease log in to the portal to review their decisions and release a final outcome to the submitter."
						);
					}
				}
				// Phase 6: gatekeeperNotifiedAt write removed — engine detects all-decided dynamically.
				// Re-derive and store the submission status now that a higher stage is complete
				$data['submissions'][ $idx ]['status'] = Portal_Data::derive_submission_status( $data['submissions'][ $idx ] );
				Portal_Data::write_submissions( $data );
				$updated = $data['submissions'][ $idx ];
			} elseif ( Portal_Data::is_stage_approved( $review_stages[ $stage_index ] ) ) {
				// Non-gated OR gatekeeper approving: notify next stage reviewers
				$next_index = $stage_index + 1;
				if ( isset( $review_stages[ $next_index ] ) ) {
					self::notify_stage_reviewers( $updated, $review_stages[ $next_index ], $next_index );
				}
			}
			// Always save stageFeedback when provided — not restricted to negative decisions
			if ( isset( $body['stageFeedback'] ) && is_array( $body['stageFeedback'] ) ) {
				$sf = $body['stageFeedback'];
				$fn = $sf['stageName'] ?? ''; $role = $sf['role'] ?? ''; $fe = $sf['email'] ?? ''; $name = $sf['name'] ?? ''; $message = isset( $sf['message'] ) ? trim( (string) $sf['message'] ) : '';
				if ( $fn && $role && $fe && $message !== '' ) {
					// Sanitize Quill HTML on the server side to prevent stored XSS
					$allowed_html = array(
						'p' => array(), 'br' => array(),
						'b' => array(), 'strong' => array(),
						'i' => array(), 'em'     => array(),
						'u' => array(), 's'      => array(),
						'ul' => array(), 'ol' => array(), 'li' => array(),
						'h1' => array(), 'h2' => array(), 'h3' => array(),
						'blockquote' => array(),
						'span' => array( 'class' => array() ),
						'div'  => array( 'class' => array() ),
					);
					$message = wp_kses( $message, $allowed_html );
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
							// Push a rich audit-log entry that permanently records the full message
							// (survives revision resets; used by the Feedback History panel as fallback)
							$_fb_user = wp_get_current_user();
							$_fb_entry = array(
								'at'            => $st['feedback'][ count( $st['feedback'] ) - 1 ]['createdAt'],
								'action'        => 'feedback_added',
								'actor'         => array(
									'name'  => $_fb_user->display_name ?: ( $_fb_user->user_login ?: 'System' ),
									'email' => strtolower( trim( (string) ( $_fb_user->user_email ?? '' ) ) ),
								),
								'detail'        => ucfirst( (string) $role ) . ' feedback added for stage "' . $fn . '" by ' . ( $name ?: $fe ) . '.',
								'stageName'     => $fn,
								'role'          => trim( $role ),
								'reviewerName'  => $name !== '' ? trim( (string) $name ) : 'Reviewer',
								'reviewerEmail' => trim( (string) $fe ),
								'message'       => $message,
							);
							if ( ! is_array( $data['submissions'][ $idx ]['auditLog'] ?? null ) ) {
								$data['submissions'][ $idx ]['auditLog'] = array();
							}
							$data['submissions'][ $idx ]['auditLog'][] = $_fb_entry;
							Portal_Data::write_submissions( $data );
							$updated = $data['submissions'][ $idx ];
							break;
						}
					}
				}
			}
			// Two-phase: if all abstract-phase stages are approved, invite full paper
			$_tp_type = $updated['type'] ?? $updated['submissionType'] ?? '';
			$_tp_cfg  = null;
			foreach ( Portal_Data::read_config()['submissionTypes'] ?? array() as $_tp_st ) {
				if ( ( $_tp_st['id'] ?? '' ) === $_tp_type ) { $_tp_cfg = $_tp_st; break; }
			}
			if ( $_tp_cfg && ! empty( $_tp_cfg['twoPhase'] ) && ( (int) ( $updated['phase'] ?? 1 ) ) === 1 ) {
				$_tp_abstract_only = (int) max( 1, $_tp_cfg['abstractOnlyStages'] ?? 1 );
				$_tp_abstract_stgs = array_slice( $updated['reviewStages'] ?? array(), 0, $_tp_abstract_only );
				$_tp_all_done      = ! empty( $_tp_abstract_stgs ) && array_reduce(
					$_tp_abstract_stgs, function ( $carry, $s ) { return $carry && Portal_Data::is_stage_approved( $s ); }, true
				);
				if ( $_tp_all_done && ( $updated['status'] ?? '' ) !== 'Full Paper Invited' ) {
					$data['submissions'][ $idx ]['status'] = 'Full Paper Invited';
					self::append_audit_log( $data, $idx, 'status_changed', 'Abstract approved — full paper invited.' );
					Portal_Data::write_submissions( $data );
					$updated = $data['submissions'][ $idx ];
					$_fp_to = $updated['submitterEmail'] ?? '';
					if ( $_fp_to && is_email( $_fp_to ) && self::user_wants_notif( $_fp_to, 'submission_status_changed' ) ) {
						wp_mail(
							$_fp_to,
							'Research Review Portal: Abstract Accepted — Please Submit Full Paper (' . ( $updated['id'] ?? '' ) . ')',
							'Hello ' . ( $updated['submitterName'] ?? '' ) . ",\n\nCongratulations! Your abstract for \"" . ( $updated['title'] ?? '' ) . '" has been accepted. Please log in to the portal to upload your full paper and submit it for full review.'
						);
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
			// Sanitize feedback message to prevent stored XSS (Quill rich-text HTML)
			$_sf_allowed_html = array(
				'p' => array(), 'br' => array(),
				'b' => array(), 'strong' => array(),
				'i' => array(), 'em'     => array(),
				'u' => array(), 's'      => array(),
				'ul' => array(), 'ol' => array(), 'li' => array(),
				'h1' => array(), 'h2' => array(), 'h3' => array(),
				'blockquote' => array(),
				'span' => array( 'class' => array() ),
				'div'  => array( 'class' => array() ),
			);
			$message = wp_kses( $message, $_sf_allowed_html );
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
			// Push a rich audit-log entry that permanently records the full message
			$_sf_user = wp_get_current_user();
			$_sf_fb_entry = array(
				'at'            => gmdate( 'c' ),
				'action'        => 'feedback_added',
				'actor'         => array(
					'name'  => $_sf_user->display_name ?: ( $_sf_user->user_login ?: 'System' ),
					'email' => strtolower( trim( (string) ( $_sf_user->user_email ?? '' ) ) ),
				),
				'detail'        => ucfirst( (string) $role ) . ' feedback added for stage "' . $stage_name . '" by ' . ( $name ?: $fe ) . '.',
				'stageName'     => $stage_name,
				'role'          => trim( $role ),
				'reviewerName'  => $name !== '' ? trim( (string) $name ) : ( $role === 'submitter' ? 'Submitter' : 'Reviewer' ),
				'reviewerEmail' => trim( (string) $fe ),
				'message'       => $message,
			);
			if ( ! is_array( $data['submissions'][ $idx ]['auditLog'] ?? null ) ) {
				$data['submissions'][ $idx ]['auditLog'] = array();
			}
			$data['submissions'][ $idx ]['auditLog'][] = $_sf_fb_entry;
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
			// Clear decisions and revisionSubmittedAt so the workflow restarts from stage 0.
			// Feedback is intentionally preserved — it forms the complete review history across revision rounds.
			foreach ( $review_stages as $si => $rs ) {
				$review_stages[ $si ]['decisions']           = array();
				$review_stages[ $si ]['revisionSubmittedAt'] = null;
				if ( isset( $review_stages[ $si ]['skipped'] ) ) {
					$review_stages[ $si ]['skipped'] = false;
				}
			}
			// Stamp the revision time on the gatekeeper stage so it shows it was re-submitted
			$_srs_wf     = Workflow_Engine::get_workflow_for_submission( $sub );
			$_srs_gk_idx = $_srs_wf ? Workflow_Engine::gatekeeper_stage_index( $_srs_wf ) : 0;
			if ( isset( $review_stages[ $_srs_gk_idx ] ) ) {
				$review_stages[ $_srs_gk_idx ]['revisionSubmittedAt'] = gmdate( 'c' );
			}
			$revision_count = (int) ( $sub['revisionCount'] ?? 0 ) + 1;
			$current_round  = (int) ( $sub['currentRound']  ?? 0 ) + 1;
			// Capture metadata snapshot for revision diff view
			$_rev_snap    = array(
				'round'        => (int) ( $sub['revisionCount'] ?? 0 ),
				'capturedAt'   => gmdate( 'c' ),
				'title'        => $sub['title'] ?? '',
				'abstract'     => $sub['abstract'] ?? '',
				'keywords'     => $sub['keywords'] ?? '',
				'researchArea' => $sub['researchArea'] ?? '',
				'notes'        => $sub['notes'] ?? '',
			);
			$_rev_history   = isset( $sub['revisionHistory'] ) && is_array( $sub['revisionHistory'] ) ? $sub['revisionHistory'] : array();
			$_rev_history[] = $_rev_snap;
			$data['submissions'][ $idx ] = array_merge( $sub, array(
				'reviewStages'    => $review_stages,
				'status'          => 'Revision Submitted',
				'revisionCount'   => $revision_count,
				'currentRound'    => $current_round,
				'revisionHistory' => $_rev_history,
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

	// ── Decision Appeal ───────────────────────────────────────────────────────

	public static function appeal_post( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub          = $data['submissions'][ $idx ];
		$current_user  = wp_get_current_user();
		$current_email = strtolower( trim( $current_user->user_email ) );
		if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) && strtolower( trim( $sub['submitterEmail'] ?? '' ) ) !== $current_email ) {
			return new WP_REST_Response( array( 'error' => 'You can only appeal your own submission.' ), 403 );
		}
		if ( ( $sub['status'] ?? '' ) !== 'Rejected' ) {
			return new WP_REST_Response( array( 'error' => 'Appeals can only be filed for rejected submissions.' ), 400 );
		}
		$existing_appeal = $sub['appeal'] ?? null;
		if ( $existing_appeal && in_array( $existing_appeal['status'] ?? '', array( 'pending', 'under_review' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'An appeal is already active for this submission.' ), 400 );
		}
		$reason = trim( (string) ( $body['reason'] ?? '' ) );
		if ( $reason === '' ) {
			return new WP_REST_Response( array( 'error' => 'Appeal reason is required.' ), 400 );
		}
		if ( mb_strlen( $reason ) > 2000 ) {
			return new WP_REST_Response( array( 'error' => 'Appeal reason must be 2000 characters or less.' ), 400 );
		}
		$data['submissions'][ $idx ]['appeal'] = array(
			'status'      => 'pending',
			'submittedAt' => gmdate( 'c' ),
			'submittedBy' => $current_email,
			'reason'      => sanitize_textarea_field( $reason ),
			'notes'       => '',
			'decidedBy'   => '',
			'decidedAt'   => null,
		);
		$data['submissions'][ $idx ]['status'] = 'Appeal Pending';
		self::append_audit_log( $data, $idx, 'appeal_filed', 'Appeal filed by ' . $current_email . '.' );
		Portal_Data::write_submissions( $data );
		$_ap_admins = get_users( array( 'role__in' => array( 'rrp_coordinator', 'rrp_admin', 'administrator' ) ) );
		foreach ( $_ap_admins as $_au ) {
			if ( $_au->user_email && is_email( $_au->user_email ) ) {
				wp_mail(
					$_au->user_email,
					'Research Review Portal: Appeal Filed — ' . ( $sub['id'] ?? '' ),
					'Hello ' . $_au->display_name . ",\n\nAn appeal has been filed for submission \"" . ( $sub['title'] ?? '' ) . '" (ID: ' . ( $sub['id'] ?? '' ) . ").\n\nReason:\n" . $reason . "\n\nPlease log in to the portal to review the appeal."
				);
			}
		}
		return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
	}

	public static function appeal_patch( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];
		if ( empty( $sub['appeal'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No appeal exists for this submission.' ), 400 );
		}
		$action       = sanitize_key( (string) ( $body['action'] ?? '' ) );
		$notes        = sanitize_textarea_field( trim( (string) ( $body['notes'] ?? '' ) ) );
		$current_user = wp_get_current_user();
		$current_by   = $current_user->user_email ?: $current_user->user_login;
		if ( $action === 'start_review' ) {
			$data['submissions'][ $idx ]['appeal']['status'] = 'under_review';
			$data['submissions'][ $idx ]['status']           = 'Appeal Under Review';
			self::append_audit_log( $data, $idx, 'appeal_updated', 'Appeal moved to Under Review by ' . $current_by . '.' );
		} elseif ( $action === 'uphold' ) {
			$data['submissions'][ $idx ]['appeal']['status']    = 'upheld';
			$data['submissions'][ $idx ]['appeal']['decidedBy'] = $current_by;
			$data['submissions'][ $idx ]['appeal']['decidedAt'] = gmdate( 'c' );
			$data['submissions'][ $idx ]['appeal']['notes']     = $notes;
			$data['submissions'][ $idx ]['status']              = 'Rejected';
			self::append_audit_log( $data, $idx, 'appeal_upheld', 'Appeal upheld by ' . $current_by . '. Rejection stands.' );
			$_ap_to = $sub['submitterEmail'] ?? '';
			if ( $_ap_to && is_email( $_ap_to ) ) {
				wp_mail( $_ap_to,
					'Research Review Portal: Appeal Decision — ' . ( $sub['id'] ?? '' ),
					'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\nYour appeal for submission \"" . ( $sub['title'] ?? '' ) . "\" has been reviewed.\n\nDecision: Appeal Upheld \u2014 the original rejection stands." . ( $notes ? "\n\nNotes from coordinator:\n" . $notes : '' ) . "\n\nIf you have further questions, please contact your coordinator."
				);
			}
		} elseif ( $action === 'overturn' ) {
			$data['submissions'][ $idx ]['appeal']['status']    = 'overturned';
			$data['submissions'][ $idx ]['appeal']['decidedBy'] = $current_by;
			$data['submissions'][ $idx ]['appeal']['decidedAt'] = gmdate( 'c' );
			$data['submissions'][ $idx ]['appeal']['notes']     = $notes;
			$rev_stages = $sub['reviewStages'] ?? array();
			foreach ( $rev_stages as &$_rst ) {
				$_rst['decisions']           = array();
				$_rst['feedback']            = array();
				$_rst['revisionSubmittedAt'] = null;
				if ( isset( $_rst['skipped'] ) ) $_rst['skipped'] = false;
			}
			unset( $_rst );
			$data['submissions'][ $idx ]['reviewStages'] = $rev_stages;
			$data['submissions'][ $idx ]['status']       = 'Revision Required';
			self::append_audit_log( $data, $idx, 'appeal_overturned', 'Appeal overturned by ' . $current_by . '. Returned for reconsideration.' );
			$_ao_to = $sub['submitterEmail'] ?? '';
			if ( $_ao_to && is_email( $_ao_to ) ) {
				wp_mail( $_ao_to,
					'Research Review Portal: Appeal Decision — ' . ( $sub['id'] ?? '' ),
					'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\nYour appeal for submission \"" . ( $sub['title'] ?? '' ) . "\" has been reviewed.\n\nDecision: Appeal Overturned \u2014 your submission has been returned for reconsideration. Please log in to the portal for next steps." . ( $notes ? "\n\nNotes from coordinator:\n" . $notes : '' )
				);
			}
		} else {
			return new WP_REST_Response( array( 'error' => 'Invalid action. Use start_review, uphold, or overturn.' ), 400 );
		}
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( $data['submissions'][ $idx ], 200 );
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
		$name    = isset( $body['name'] ) ? sanitize_text_field( trim( (string) $body['name'] ) ) : '';
		$email   = isset( $body['email'] ) ? sanitize_email( trim( (string) $body['email'] ) ) : '';
		$comment = isset( $body['comment'] ) ? wp_kses_post( trim( (string) $body['comment'] ) ) : '';
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
		if ( in_array( $sub['status'] ?? '', array( 'Withdrawn', 'Cancelled' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot add comments to a ' . ( $sub['status'] ) . ' submission.' ), 409 );
		}
		$stage = isset( $body['stage'] ) ? sanitize_text_field( trim( (string) $body['stage'] ) ) : '';
		$text  = isset( $body['text'] ) ? wp_kses_post( trim( (string) $body['text'] ) ) : '';
		$by    = isset( $body['by'] ) ? sanitize_text_field( trim( (string) $body['by'] ) ) : 'Internal';
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
			'doc'  => 'application/msword',
			'odt'  => 'application/vnd.oasis.opendocument.text',
			'txt'  => 'text/plain',
			'rtf'  => 'application/rtf',
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xls'  => 'application/vnd.ms-excel',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'ppt'  => 'application/vnd.ms-powerpoint',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
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
		// Use a fixed, known-good path instead of relying on PATH environment (prevents PATH hijacking)
		$clamscan = defined( 'RRP_CLAMSCAN_PATH' ) ? (string) RRP_CLAMSCAN_PATH : '/usr/bin/clamscan';
		if ( $clamscan && file_exists( $clamscan ) && is_executable( $clamscan ) ) {
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
		$_up_sub = $data['submissions'][ $idx ];
		if ( in_array( $_up_sub['status'] ?? '', array( 'Withdrawn', 'Cancelled' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot upload files to a ' . ( $_up_sub['status'] ) . ' submission.' ), 409 );
		}
		$files = $request->get_file_params();
		if ( empty( $files ) || empty( $files['files'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No files uploaded. Use multipart/form-data with field "files".' ), 400 );
		}
		$upload = $files['files'];
		// Read upload limits from portal config (with safe defaults)
		$_up_config      = Portal_Data::read_config();
		$_up_settings    = isset( $_up_config['uploadSettings'] ) && is_array( $_up_config['uploadSettings'] ) ? $_up_config['uploadSettings'] : array();
		$max_size        = min( 50, max( 1, (int) ( $_up_settings['maxFileSizeMb'] ?? 2 ) ) ) * 1024 * 1024;
		$max_files       = min( 20, max( 1, (int) ( $_up_settings['maxFiles']      ?? 5 ) ) );
		$safe_exts       = array( 'pdf', 'docx', 'doc', 'odt', 'txt', 'rtf', 'csv', 'xlsx', 'xls', 'pptx', 'ppt', 'png', 'jpg', 'jpeg', 'gif' );
		$allowed_exts    = array();
		if ( ! empty( $_up_settings['allowedExtensions'] ) && is_array( $_up_settings['allowedExtensions'] ) ) {
			foreach ( $_up_settings['allowedExtensions'] as $_ae ) {
				$_ae = strtolower( sanitize_key( (string) $_ae ) );
				if ( in_array( $_ae, $safe_exts, true ) ) $allowed_exts[] = $_ae;
			}
		}
		if ( empty( $allowed_exts ) ) $allowed_exts = array( 'pdf', 'docx' );
		$max_size_mb     = $max_size / 1024 / 1024;
		$sub = $data['submissions'][ $idx ];
		$attachments = isset( $sub['attachments'] ) && is_array( $sub['attachments'] ) ? $sub['attachments'] : array();
		if ( count( $attachments ) >= $max_files ) {
			return new WP_REST_Response( array( 'error' => 'Max ' . $max_files . ' files per submission.' ), 400 );
		}
		// Determine which revision round this upload belongs to.
		// Round 0 = original submission. Round N = Nth revision.
		$revision_statuses = array( 'Revision Required', 'Rejected', 'Revision Submitted' );
		$is_revision_upload = in_array( $sub['status'] ?? '', $revision_statuses, true );
		$revision_round = (int) ( $sub['revisionCount'] ?? 0 ) + ( $is_revision_upload ? 1 : 0 );
		$uploaded_at = gmdate( 'c' );
		$is_multi = isset( $upload['name'] ) && is_array( $upload['name'] );
		$new_attachments = array();
		$rejected = array();
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
				if ( ! $ext || ! in_array( $ext, $allowed_exts, true ) ) {
					$rejected[] = $orig;
					continue;
				}
				if ( ! self::validate_upload_file( $tmp, $ext ) ) {
					$rejected[] = $orig;
					continue;
				}
				$ext = '.' . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$stem     = $base ?: 'file';
				$ts       = gmdate( 'Ymd_His' );
				$filename = $stem . '_r' . $revision_round . '_' . $ts . $ext;
				$counter  = 1;
				while ( file_exists( $dir . $filename ) ) {
					$filename = $stem . '_r' . $revision_round . '_' . $ts . '_' . $counter++ . $ext;
				}
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
				if ( ! $ext || ! in_array( $ext, $allowed_exts, true ) || ! self::validate_upload_file( $tmp, $ext ) ) {
					$ext_list = implode( ', ', array_map( 'strtoupper', $allowed_exts ) );
					return new WP_REST_Response( array( 'error' => 'Only ' . $ext_list . ' files are accepted (max ' . $max_size_mb . ' MB each).' ), 400 );
				}
				$ext = '.' . $ext;
				Portal_Data::ensure_data_dir();
				$dir = RRP_UPLOADS_DIR . $id . '/';
				if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
				$stem     = $base ?: 'file';
				$ts       = gmdate( 'Ymd_His' );
				$filename = $stem . '_r' . $revision_round . '_' . $ts . $ext;
				$counter  = 1;
				while ( file_exists( $dir . $filename ) ) {
					$filename = $stem . '_r' . $revision_round . '_' . $ts . '_' . $counter++ . $ext;
				}
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
			$ext_list = implode( ', ', array_map( 'strtoupper', $allowed_exts ) );
			$msg = ! empty( $rejected )
				? 'Only ' . $ext_list . ' files are accepted (max ' . $max_size_mb . ' MB each). Rejected: ' . implode( ', ', array_map( 'sanitize_text_field', $rejected ) )
				: 'Upload failed. Max ' . $max_files . ' files, ' . $max_size_mb . ' MB each.';
			return new WP_REST_Response( array( 'error' => $msg ), 400 );
		}
		// Tag stage-specific student file uploads when stageName is provided
		$_upload_stage = $request->get_param( 'stageName' );
		if ( $_upload_stage ) {
			$_upload_stage = sanitize_text_field( (string) $_upload_stage );
			foreach ( $new_attachments as &$_stag_entry ) {
				$_stag_entry['stageName']         = $_upload_stage;
				$_stag_entry['studentFileUpload'] = true;
			}
			unset( $_stag_entry );
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
		// Path traversal guard: ensure the resolved path stays within the submission's upload directory
		$base_dir  = realpath( RRP_UPLOADS_DIR . $id . '/' );
		$full_path = realpath( $file_path );
		if ( ! $base_dir || ! $full_path || strpos( $full_path, $base_dir . DIRECTORY_SEPARATOR ) !== 0 ) {
			return new WP_REST_Response( array( 'error' => 'Invalid file path.' ), 400 );
		}
		$file_path = $full_path;
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_REST_Response( array( 'error' => 'File not found on server.' ), 404 );
		}
		$name = basename( (string) ( $att['name'] ?? $att['filename'] ?? 'download' ) );
		// Strip all control characters (including CR, LF) and non-ASCII to prevent
		// HTTP response splitting / header injection via the Content-Disposition filename.
		$name = preg_replace( '/[\x00-\x1F\x7F\x80-\xFF"\\\\]/', '', $name );
		if ( '' === $name ) {
			$name = 'download';
		}
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
		// Per-stage deadline days: stageDueDays[typeId][stageName] = int
		// Merge per-type — never replace the entire object so that saving one
		// type does not wipe the days that were configured for other types.
		if ( isset( $body['stageDueDays'] ) && is_array( $body['stageDueDays'] ) ) {
			$sdd = is_array( $config['stageDueDays'] ?? null ) ? $config['stageDueDays'] : array();
			foreach ( $body['stageDueDays'] as $type_id => $stage_map ) {
				$tid = sanitize_key( (string) $type_id );
				if ( is_array( $stage_map ) ) {
					if ( ! isset( $sdd[ $tid ] ) || ! is_array( $sdd[ $tid ] ) ) {
						$sdd[ $tid ] = array();
					}
					foreach ( $stage_map as $sname => $days ) {
						$sdd[ $tid ][ sanitize_text_field( (string) $sname ) ] = max( 1, (int) $days );
					}
				} elseif ( is_numeric( $stage_map ) ) {
					$sdd[ $tid ] = max( 1, (int) $stage_map );
				}
			}
			$config['stageDueDays'] = $sdd;
		}
		// Deadline options: skipWeekends, gracePeriodDays, publicHolidays
		if ( isset( $body['deadlineOptions'] ) && is_array( $body['deadlineOptions'] ) ) {
			$opts = $body['deadlineOptions'];
			$config['deadlineOptions'] = array(
				'skipWeekends'    => ! empty( $opts['skipWeekends'] ),
				'gracePeriodDays' => max( 0, (int) ( $opts['gracePeriodDays'] ?? 2 ) ),
				'publicHolidays'  => array_values( array_filter( array_map(
					function ( $d ) {
						$s = sanitize_text_field( (string) $d );
						return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
					},
					(array) ( $opts['publicHolidays'] ?? array() )
				) ) ),
			);
		}
		// Public submissions settings: enabled flag + allowed type IDs
		if ( isset( $body['publicSubmissions'] ) && is_array( $body['publicSubmissions'] ) ) {
			$ps = $body['publicSubmissions'];
			$config['publicSubmissions'] = array(
				'enabled'      => ! empty( $ps['enabled'] ),
				'allowedTypes' => array_values( array_map(
					'sanitize_key',
					(array) ( $ps['allowedTypes'] ?? array() )
				) ),
			);
		}
		// Upload settings: maxFileSizeMb, maxFiles, allowedExtensions
		if ( isset( $body['uploadSettings'] ) && is_array( $body['uploadSettings'] ) ) {
			$us = $body['uploadSettings'];
			// Whitelist of safe extensions — only extensions from this set are accepted
			$safe_exts = array( 'pdf', 'docx', 'doc', 'odt', 'txt', 'rtf', 'csv', 'xlsx', 'xls', 'pptx', 'ppt', 'png', 'jpg', 'jpeg', 'gif', 'zip' );
			$requested_exts = is_array( $us['allowedExtensions'] ?? null ) ? $us['allowedExtensions'] : array( 'pdf', 'docx' );
			$filtered_exts = array_values( array_filter(
				array_map( function ( $e ) { return strtolower( sanitize_key( (string) $e ) ); }, $requested_exts ),
				function ( $e ) use ( $safe_exts ) { return in_array( $e, $safe_exts, true ); }
			) );
			if ( empty( $filtered_exts ) ) $filtered_exts = array( 'pdf', 'docx' );
			$config['uploadSettings'] = array(
				'maxFileSizeMb'      => min( 50, max( 1, (int) ( $us['maxFileSizeMb'] ?? 2 ) ) ),
				'maxFiles'           => min( 20, max( 1, (int) ( $us['maxFiles']      ?? 5 ) ) ),
				'allowedExtensions'  => $filtered_exts,
			);
		}
		Portal_Data::write_config( $config );
		return new WP_REST_Response( $config, 200 );
	}

	// ── Public submissions config (publicSubmissions) ───────────────────────
	// GET /public-config  (no auth)
	public static function public_config_get( WP_REST_Request $request ): WP_REST_Response {
		$config = Portal_Data::read_config();
		$pub    = isset( $config['publicSubmissions'] ) && is_array( $config['publicSubmissions'] )
			? $config['publicSubmissions']
			: array( 'enabled' => false, 'allowedTypes' => array() );

		// Expose upload constraints so the JS client can enforce the correct
		// limit even for roles that cannot access the full /config endpoint
		// (e.g. rrp_student).  Only safe, non-sensitive fields are returned.
		$up_raw      = isset( $config['uploadSettings'] ) && is_array( $config['uploadSettings'] ) ? $config['uploadSettings'] : array();
		$max_size_mb = min( 50, max( 1, (int) ( $up_raw['maxFileSizeMb'] ?? 2 ) ) );
		$max_files   = min( 20, max( 1, (int) ( $up_raw['maxFiles']      ?? 5 ) ) );
		$safe_exts   = array( 'pdf', 'docx', 'doc', 'odt', 'txt', 'rtf', 'csv', 'xlsx', 'xls', 'pptx', 'ppt', 'png', 'jpg', 'jpeg', 'gif' );
		$allowed     = array();
		if ( ! empty( $up_raw['allowedExtensions'] ) && is_array( $up_raw['allowedExtensions'] ) ) {
			foreach ( $up_raw['allowedExtensions'] as $_e ) {
				$_e = strtolower( sanitize_key( (string) $_e ) );
				if ( in_array( $_e, $safe_exts, true ) ) {
					$allowed[] = $_e;
				}
			}
		}
		if ( empty( $allowed ) ) {
			$allowed = array( 'pdf', 'docx' );
		}

		return new WP_REST_Response( array(
			'enabled'        => ! empty( $pub['enabled'] ),
			'allowedTypes'   => is_array( $pub['allowedTypes'] ?? null ) ? array_values( $pub['allowedTypes'] ) : array(),
			'uploadSettings' => array(
				'maxFileSizeMb'     => $max_size_mb,
				'maxFiles'          => $max_files,
				'allowedExtensions' => $allowed,
			),
		), 200 );
	}

	// POST /auth/register  (no auth — self-registration for public submitters)
	public static function auth_register( WP_REST_Request $request ): WP_REST_Response {
		// Rate limit: 5 registrations per IP per 15 min
		if ( ! self::check_rate_limit( 'register', 5, 900 ) ) {
			return new WP_REST_Response( array( 'error' => 'Too many registration attempts. Please try again in 15 minutes.' ), 429 );
		}

		// Verify feature is enabled first
		$config = Portal_Data::read_config();
		$pub    = $config['publicSubmissions'] ?? array();
		if ( empty( $pub['enabled'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Public registration is not enabled.' ), 403 );
		}

		$body      = $request->get_json_params() ?: array();
		$firstName = sanitize_text_field( $body['firstName'] ?? '' );
		$lastName  = sanitize_text_field( $body['lastName']  ?? '' );
		$email     = sanitize_email( $body['email']    ?? '' );
		$password  = isset( $body['password'] ) ? (string) $body['password'] : '';

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'error' => 'A valid email address is required.' ), 400 );
		}
		if ( strlen( $password ) < 8 ) {
			return new WP_REST_Response( array( 'error' => 'Password must be at least 8 characters.' ), 400 );
		}
		if ( ! $firstName && ! $lastName ) {
			return new WP_REST_Response( array( 'error' => 'Your name is required.' ), 400 );
		}
		if ( email_exists( $email ) ) {
			// Do NOT reveal that the account exists (prevents user enumeration).
			// Return a neutral message identical to the success path but without
			// setting auth cookies — the real user must log in with their password.
			return new WP_REST_Response( array(
				'success'     => true,
				'message'     => 'If this email is not yet registered, your account has been created. Please log in.',
				'redirectUrl' => wp_login_url( home_url( '/?portal=1' ) ),
			), 200 );
		}

		$display_name = trim( $firstName . ' ' . $lastName ) ?: $email;
		$base_login   = sanitize_user( strtolower( str_replace( ' ', '.', $display_name ) ), true );
		$base_login   = $base_login ?: sanitize_user( explode( '@', $email )[0], true );
		$login        = $base_login;
		$i            = 1;
		while ( username_exists( $login ) ) {
			$login = $base_login . $i++;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $firstName,
			'last_name'    => $lastName,
			'display_name' => $display_name,
			'role'         => 'rrp_public',
		) );

		if ( is_wp_error( $user_id ) ) {
			return new WP_REST_Response( array( 'error' => $user_id->get_error_message() ), 400 );
		}

		// Auto-login the new public user — sets the auth cookie in this response so
		// the browser is authenticated when the JS redirect hits /?portal=1.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false );

		return new WP_REST_Response( array(
			'success'     => true,
			'message'     => 'Account created.',
			'userName'    => $display_name,
			'redirectUrl' => home_url( '/?portal=1' ),
		), 201 );
	}

	public static function submission_types_get( WP_REST_Request $request ) {
		$config = Portal_Data::read_config();
		$types  = $config['submissionTypes'] ?? array();
		// Strip internal workflow metadata from unauthenticated responses to avoid
		// leaking blind-review / two-phase config details to the public.
		if ( ! is_user_logged_in() ) {
			$types = array_map( function ( $t ) {
				return array(
					'id'          => $t['id'] ?? '',
					'label'       => $t['label'] ?? '',
					'description' => $t['description'] ?? '',
				);
			}, $types );
		}
		return new WP_REST_Response( array( 'submissionTypes' => $types ), 200 );
	}

	public static function submission_types_put( WP_REST_Request $request ) {
		$body  = $request->get_json_params() ?: array();
		$types = isset( $body['submissionTypes'] ) && is_array( $body['submissionTypes'] ) ? $body['submissionTypes'] : array();
		$sanitized = array();
		foreach ( $types as $t ) {
			if ( empty( $t['id'] ) || empty( $t['label'] ) ) continue;
			$sanitized[] = array(
				'id'                 => sanitize_key( (string) $t['id'] ),
				'label'              => sanitize_text_field( (string) $t['label'] ),
				'description'        => sanitize_text_field( (string) ( $t['description'] ?? '' ) ),
				'stages'             => array_values( array_map( 'sanitize_text_field', (array) ( $t['stages'] ?? array() ) ) ),
				'blindReview'        => (bool) ( $t['blindReview'] ?? false ),
				'twoPhase'           => (bool) ( $t['twoPhase'] ?? false ),
				'abstractOnlyStages' => (int) max( 1, (int) ( $t['abstractOnlyStages'] ?? 1 ) ),
				'allowMeetings'      => (bool) ( $t['allowMeetings'] ?? false ),
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
				if ( array_key_exists( 'blindReview', $body ) ) {
					$t['blindReview'] = (bool) $body['blindReview'];
				}
				if ( array_key_exists( 'twoPhase', $body ) ) {
					$t['twoPhase'] = (bool) $body['twoPhase'];
				}
				if ( array_key_exists( 'abstractOnlyStages', $body ) ) {
					$t['abstractOnlyStages'] = (int) max( 1, (int) $body['abstractOnlyStages'] );
				}
				if ( array_key_exists( 'allowMeetings', $body ) ) {
					$t['allowMeetings'] = (bool) $body['allowMeetings'];
				}
				if ( array_key_exists( 'gatedReview', $body ) ) {
					$t['gatedReview'] = (bool) $body['gatedReview'];
				}
				if ( array_key_exists( 'workflow', $body ) ) {
					if ( is_array( $body['workflow'] ) ) {
						$t['workflow'] = self::sanitize_workflow_config( $body['workflow'] );
					} else {
						unset( $t['workflow'] );
					}
				}
				if ( array_key_exists( 'requiredFields', $body ) && is_array( $body['requiredFields'] ) ) {
					$t['requiredFields'] = array_values( array_filter( array_map( 'sanitize_text_field', $body['requiredFields'] ) ) );
				}
				break;
			}
		}
		unset( $t );
		if ( ! $found ) {
			// Create new type if it doesn't exist
			$new_type = array(
				'id'                 => $id,
				'label'              => sanitize_text_field( (string) ( $body['label'] ?? $id ) ),
				'description'        => sanitize_text_field( (string) ( $body['description'] ?? '' ) ),
				'stages'             => array_values( array_map( 'sanitize_text_field', (array) ( $body['stages'] ?? array() ) ) ),
				'blindReview'        => (bool) ( $body['blindReview'] ?? false ),
				'twoPhase'           => (bool) ( $body['twoPhase'] ?? false ),
				'abstractOnlyStages' => (int) max( 1, (int) ( $body['abstractOnlyStages'] ?? 1 ) ),
				'allowMeetings'      => (bool) ( $body['allowMeetings'] ?? false ),
				'gatedReview'        => (bool) ( $body['gatedReview'] ?? false ),
			);
			if ( isset( $body['workflow'] ) && is_array( $body['workflow'] ) ) {
				$new_type['workflow'] = self::sanitize_workflow_config( $body['workflow'] );
			}
			if ( isset( $body['requiredFields'] ) && is_array( $body['requiredFields'] ) ) {
				$new_type['requiredFields'] = array_values( array_filter( array_map( 'sanitize_text_field', $body['requiredFields'] ) ) );
			}
			$types[] = $new_type;
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

	/**
	 * Return the static schema options used by the workflow engine editor UI.
	 * Provides available stage roles, decision rules, and default decision labels.
	 */
	/**
	 * Compute the CSS badge class from a submission status string.
	 * Single source of truth — mirrors the JS statusBadgeCls() function.
	 *
	 * @param string $status Raw status label.
	 * @return string  CSS class name.
	 */
	private static function status_to_badge_class( string $status ): string {
		$s = strtolower( preg_replace( '/[\s_]+/', '-', $status ) );
		$approved = array( 'approved', 'conditionally-approved', 'confirmed-for-presentation', 'published', 'approved-for-submission', 'accepted', 'conditionally-accepted' );
		if ( in_array( $s, $approved, true ) )                                              return 'rrp-dec-approved';
		if ( $s === 'rejected' )                                                             return 'rrp-dec-rejected';
		if ( in_array( $s, array( 'needs-revision', 'revision-required', 'revision', 'revision-submitted' ), true ) ) return 'rrp-dec-revision';
		if ( $s === 'draft' )                                                                return 'rrp-dec-draft';
		if ( $s === 'submitted' )                                                            return 'rrp-dec-submitted';
		if ( in_array( $s, array( 'appeal-pending', 'appeal-under-review' ), true ) )       return 'rrp-dec-revision';
		if ( $s === 'full-paper-invited' )                                                   return 'rrp-dec-inreview';
		if ( strpos( $s, 'review' ) !== false || strpos( $s, 'in-progress' ) !== false )    return 'rrp-dec-inreview';
		if ( in_array( $s, array( 'withdrawn', 'cancelled' ), true ) )                      return 'rrp-dec-withdrawn';
		return 'rrp-dec-pending';
	}

	/**
	 * Phase 6 migration: stamp currentRound on existing submissions that lack it.
	 * Safe to run multiple times (idempotent).
	 */
	public static function migration_stamp_rounds( WP_REST_Request $request ) {
		$data    = Portal_Data::read_submissions();
		$updated = 0;
		foreach ( $data['submissions'] as &$sub ) {
			if ( isset( $sub['currentRound'] ) ) {
				continue; // Already stamped by Phase 1+
			}
			$releases = isset( $sub['gatedReleases'] ) && is_array( $sub['gatedReleases'] ) ? $sub['gatedReleases'] : array();
			$count    = count( $releases );
			if ( $count === 0 ) {
				$sub['currentRound'] = 0;
			} else {
				// If the most recent release has a round stamp, use it directly.
				// Otherwise derive: if the last release appears current (no newer revisionSubmittedAt), count-1;
				// if the submitter revised after the last release, count.
				$last_rel = end( $releases );
				if ( isset( $last_rel['round'] ) ) {
					// Already has round — skip entirely
					$sub['currentRound'] = (int) $last_rel['round'];
				} else {
					$rel_ts   = isset( $last_rel['releasedAt'] ) ? strtotime( (string) $last_rel['releasedAt'] ) : 0;
					// Check gatekeeper stage (stage 0) revisionSubmittedAt
					$gk_stage = ( $sub['reviewStages'][0] ?? array() );
					$rev_ts   = isset( $gk_stage['revisionSubmittedAt'] ) ? strtotime( (string) $gk_stage['revisionSubmittedAt'] ) : 0;
					$revised_after_release = ( $rev_ts > 0 && $rel_ts > 0 && $rev_ts > $rel_ts );
					$sub['currentRound'] = $revised_after_release ? $count : $count - 1;
				}
			}
			$updated++;
		}
		unset( $sub );
		if ( $updated > 0 ) {
			Portal_Data::write_submissions( $data );
		}
		return new WP_REST_Response( array( 'stamped' => $updated, 'total' => count( $data['submissions'] ) ), 200 );
	}

	public static function workflow_schema_get() {
		return new WP_REST_Response( array(
			'stageRoles'       => array( 'gatekeeper', 'committee', 'advisory' ),
			'decisionRules'    => array( 'unanimous', 'majority', 'single' ),
			'defaultDecisions' => array( 'Approved', 'Needs Revision', 'Rejected', 'Pending' ),
		), 200 );
	}

	/**
	 * Sanitize and normalise a workflow config block supplied from the editor UI.
	 *
	 * @param array $raw Raw workflow array from the REST body.
	 * @return array     Sanitized workflow config.
	 */
	private static function sanitize_workflow_config( array $raw ): array {
		$allowed_roles = array( 'gatekeeper', 'committee', 'advisory' );
		$allowed_rules = array( 'unanimous', 'majority', 'single' );
		$sanitized_stages = array();
		foreach ( (array) ( $raw['stages'] ?? array() ) as $ws ) {
			if ( ! is_array( $ws ) ) continue;
			$role = in_array( $ws['role'] ?? '', $allowed_roles, true ) ? $ws['role'] : 'committee';
			$rule = in_array( $ws['decisionRule'] ?? '', $allowed_rules, true ) ? $ws['decisionRule'] : 'unanimous';
			$decs = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $ws['decisions'] ?? array() ) ) ) );
			$vis  = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $ws['visibleTo'] ?? array() ) ) ) );
			$sanitized_stages[] = array(
				'name'           => sanitize_text_field( (string) ( $ws['name'] ?? '' ) ),
				'role'           => $role,
				'decisionRule'   => $rule,
				'decisions'      => $decs,
				'releaseChannel' => (bool) ( $ws['releaseChannel'] ?? false ),
				'visibleTo'      => $vis,
			);
		}
		$final_map = array();
		foreach ( (array) ( $raw['finalStatusByDecision'] ?? array() ) as $dec => $status ) {
			$k = sanitize_text_field( (string) $dec );
			$v = sanitize_text_field( (string) $status );
			if ( $k !== '' && $v !== '' ) {
				$final_map[ $k ] = $v;
			}
		}
		$student_vis = array();
		if ( isset( $raw['studentVisibility'] ) && is_array( $raw['studentVisibility'] ) ) {
			$student_vis = array(
				'showStageNames'    => (bool) ( $raw['studentVisibility']['showStageNames'] ?? true ),
				'showReviewerNames' => (bool) ( $raw['studentVisibility']['showReviewerNames'] ?? false ),
			);
		}
		$out = array(
			'stages'               => $sanitized_stages,
			'finalStatusByDecision' => $final_map,
		);
		if ( ! empty( $student_vis ) ) {
			$out['studentVisibility'] = $student_vis;
		}
		return $out;
	}

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
				if ( isset( $body['name'] ) )             $s['name']             = sanitize_text_field( (string) $body['name'] );
				if ( isset( $body['singleUser'] ) )        $s['singleUser']       = (bool) $body['singleUser'];
				if ( isset( $body['multiUser'] ) )         $s['multiUser']        = (bool) $body['multiUser'];
				if ( isset( $body['allowStudentFiles'] ) ) $s['allowStudentFiles'] = (bool) $body['allowStudentFiles'];
				if ( isset( $body['studentFileLabel'] ) )  $s['studentFileLabel']  = sanitize_text_field( (string) $body['studentFileLabel'] );
				break;
			}
		}
		unset( $s );
		if ( ! $found ) {
			$stages[] = array(
				'id'               => $id,
				'name'             => sanitize_text_field( (string) ( $body['name'] ?? $id ) ),
				'singleUser'       => (bool) ( $body['singleUser'] ?? true ),
				'multiUser'        => (bool) ( $body['multiUser']  ?? false ),
				'allowStudentFiles' => (bool) ( $body['allowStudentFiles'] ?? false ),
				'studentFileLabel'  => sanitize_text_field( (string) ( $body['studentFileLabel'] ?? '' ) ),
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
			// Determine the currently active review stage:
			// the first non-skipped stage where NOT all assigned reviewers have voted Approved.
			$active_stage_idx = null;
			if ( ! empty( $s['reviewStages'] ) ) {
				foreach ( $s['reviewStages'] as $asi => $as ) {
					if ( $as['skipped'] ?? false ) {
						continue;
					}
					$as_revs = $as['reviewers'] ?? array();
					$as_decs = $as['decisions'] ?? array();
					// A stage with no assigned reviewers is still unstarted — treat as active.
					if ( empty( $as_revs ) ) {
						$active_stage_idx = $asi;
						break;
					}
					$all_approved = true;
					foreach ( $as_revs as $ar ) {
						$ek = strtolower( trim( (string) ( $ar['email'] ?? '' ) ) );
						if ( strtolower( trim( (string) ( $as_decs[ $ek ] ?? '' ) ) ) !== 'approved' ) {
							$all_approved = false;
							break;
						}
					}
					if ( ! $all_approved ) {
						$active_stage_idx = $asi;
						break;
					}
				}
			}

			// Check if reviewer is in the active stage (still needs to act).
			// Legacy path: if no reviewStages defined, fall back to assignedReviewers.
			$in_active_stage = false;
			if ( ! empty( $s['reviewStages'] ) ) {
				if ( $active_stage_idx !== null ) {
					foreach ( $s['reviewStages'][ $active_stage_idx ]['reviewers'] ?? array() as $r ) {
						if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
							$in_active_stage = true;
							break;
						}
					}
				}
			} else {
				foreach ( $s['assignedReviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$in_active_stage = true;
						break;
					}
				}
			}

			// Find reviewer's own recorded decision across ALL stages (for historical display).
			$my_decision  = null;
			$my_stage_idx = null;
			foreach ( $s['reviewStages'] ?? array() as $si => $rs ) {
				$decs = $rs['decisions'] ?? array();
				if ( isset( $decs[ $email ] ) && '' !== (string) $decs[ $email ] ) {
					$my_decision  = $decs[ $email ];
					$my_stage_idx = $si;
					// Keep scanning — use the last stage where they recorded a decision.
				}
			}

			// Skip submission entirely if reviewer has no connection to it at all.
			if ( ! $in_active_stage && null === $my_decision ) {
				continue;
			}

			// Resolve the deadline — only relevant when reviewer still needs to act.
			$deadline  = null;
			$stage_idx = $in_active_stage ? $active_stage_idx : $my_stage_idx;
			if ( $stage_idx !== null ) {
				// Check per-reviewer deadline stored in this stage.
				foreach ( $s['reviewStages'][ $stage_idx ]['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						if ( ! empty( $r['deadline'] ) ) {
							$deadline = $r['deadline'];
						}
						break;
					}
				}
			}
			// Legacy: per-reviewer deadline in assignedReviewers.
			if ( ! $deadline ) {
				foreach ( $s['assignedReviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$deadline = $r['deadline'] ?? null;
						break;
					}
				}
			}
			// Final fallback: calculate from submission date + config.
			if ( ! $deadline && $stage_idx !== null ) {
				$deadline = Portal_Data::get_effective_deadline( $s, $stage_idx );
			}

			$_rev_wf = Workflow_Engine::get_workflow_for_submission( $s );
		$_rev_vr = Workflow_Engine::compute_viewer_role( $s, $email, false, $_rev_wf ?? array() );
		$_is_gk  = ( $_rev_vr === 'gatekeeper' ) && self::is_gated_review_type( $s['submissionType'] ?? $s['type'] ?? '' );
			// For gatekeeper: use the released decision (if any) as myDecision so the dashboard badge
			// reflects the decision communicated to the student, not the internal stage vote.
			$_gk_released_dec = null;
			if ( $_is_gk && ! empty( $s['gatedReleases'] ) && is_array( $s['gatedReleases'] ) ) {
				$_last_gr         = end( $s['gatedReleases'] );
				$_gk_released_dec = isset( $_last_gr['decision'] ) && '' !== (string) $_last_gr['decision']
					? (string) $_last_gr['decision'] : null;
			}
			// Detect whether the gatekeeper has a pending action: higher stage completed but no release yet.
			$_gk_action_required = false;
			if ( $_is_gk && empty( $s['gatedReleases'] ) ) {
				foreach ( array_slice( $s['reviewStages'] ?? array(), 1, null, true ) as $_hsi => $_hs ) {
					if ( $_hs['skipped'] ?? false ) continue;
					if ( ! empty( $_hs['gatekeeperNotifiedAt'] ) ) { $_gk_action_required = true; break; }
					$_hs_revs = $_hs['reviewers'] ?? array();
					$_hs_decs = is_array( $_hs['decisions'] ?? null ) ? $_hs['decisions'] : array();
					if ( ! empty( $_hs_revs ) ) {
						$_all_d = true;
						foreach ( $_hs_revs as $_hr ) {
							$_hek = strtolower( trim( (string) ( $_hr['email'] ?? '' ) ) );
							if ( empty( $_hs_decs[ $_hek ] ) ) { $_all_d = false; break; }
						}
						if ( $_all_d ) { $_gk_action_required = true; break; }
					}
				}
			}
			$list[] = array(
				'id'                     => $s['id'] ?? null,
				'type'                   => $s['type'] ?? null,
				'submissionType'         => $s['submissionType'] ?? null,
				'status'                 => $s['status'] ?? null,
				'title'                  => $s['title'] ?? null,
				'createdAt'              => $s['createdAt'] ?? null,
				'deadline'               => $in_active_stage ? $deadline : null,
				'myDecision'             => $_gk_action_required ? null : ( $_gk_released_dec ?? $my_decision ),
				'pendingAction'          => $in_active_stage && null === $my_decision || $_gk_action_required,
				'isGatekeeper'           => $_is_gk,
				'viewerRole'             => $_rev_vr,
				'gatekeeperActionRequired' => $_gk_action_required,
			);
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
		if ( in_array( $sub['status'] ?? '', array( 'Withdrawn', 'Cancelled' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot skip a stage on a ' . ( $sub['status'] ) . ' submission.' ), 409 );
		}
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
			$calculated  = Portal_Data::calculate_stage_deadline( $sub, $i );
			$effective   = Portal_Data::get_effective_deadline( $sub, $i );
			$ext_request = $stage['extensionRequest'] ?? null;
			$deadlines[] = array(
				'stageName'         => $stage['stageName'] ?? '',
				'stageIndex'        => $i,
				'deadline'          => $effective,
				'calculatedDeadline'=> $calculated,
				'extended'          => ! empty( $stage['extensionApproved'] ),
				'extensionRequest'  => $ext_request,
				'approved'          => Portal_Data::is_stage_approved( $stage ),
				'skipped'           => $stage['skipped'] ?? false,
			);
		}
		return new WP_REST_Response( array( 'id' => $id, 'deadlines' => $deadlines ), 200 );
	}

	public static function analytics_overdue( WP_REST_Request $request ) {
		$filter  = self::analytics_user_filter();
		$overdue = Portal_Data::get_overdue_submissions();
		if ( ! empty( $filter['reviewerEmail'] ) ) {
			// Reviewers see only overdue stages where they are assigned
			$re      = strtolower( trim( $filter['reviewerEmail'] ) );
			$overdue = array_values( array_filter( $overdue, function ( $o ) use ( $re ) {
				return in_array( $re, array_map( 'strtolower', $o['reviewers'] ), true );
			} ) );
		} elseif ( ! empty( $filter['submitterEmail'] ) ) {
			// Students see only overdue stages on their own submissions
			$subs_data = Portal_Data::read_submissions();
			$fe        = strtolower( trim( $filter['submitterEmail'] ) );
			$my_ids    = array();
			foreach ( $subs_data['submissions'] ?? array() as $s ) {
				if ( strtolower( trim( (string) ( $s['submitterEmail'] ?? '' ) ) ) === $fe ) {
					$my_ids[] = $s['id'] ?? '';
				}
			}
			$overdue = array_values( array_filter( $overdue, function ( $o ) use ( $my_ids ) {
				return in_array( $o['submissionId'], $my_ids, true );
			} ) );
		}
		// Admin / coordinator: all overdue (no filter applied)
		return new WP_REST_Response( array( 'overdue' => $overdue ), 200 );
	}

	/**
	 * Send email notification to all reviewers assigned to a stage.
	 */
	/**
	 * Returns true if the user (looked up by email) has not opted out of the given notification.
	 * Default = true (send) when the user has no WP account or has not set the preference.
	 */
	private static function user_wants_notif( string $email, string $pref_key ): bool {
		$user = get_user_by( 'email', strtolower( trim( $email ) ) );
		if ( ! $user ) return true;
		$prefs = get_user_meta( $user->ID, 'rrp_notif_prefs', true );
		if ( ! is_array( $prefs ) ) return true;
		if ( ! array_key_exists( $pref_key, $prefs ) ) return true;
		return (bool) $prefs[ $pref_key ];
	}

	// GET /notif-prefs
	public static function notif_prefs_get( WP_REST_Request $request ): WP_REST_Response {
		$prefs = get_user_meta( get_current_user_id(), 'rrp_notif_prefs', true );
		if ( ! is_array( $prefs ) ) $prefs = array();
		return new WP_REST_Response( array( 'prefs' => $prefs ), 200 );
	}

	// PUT /notif-prefs
	public static function notif_prefs_put( WP_REST_Request $request ): WP_REST_Response {
		static $ALL_KEYS = array(
			'submission_received',
			'stage_assigned',
			'deadline_reminder',
			'escalation',
			'submission_status_changed',
			'extension_resolved',
			'extension_requested',
		);
		$body  = $request->get_json_params() ?: array();
		$prefs = array();
		foreach ( $ALL_KEYS as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$prefs[ $key ] = (bool) $body[ $key ];
			}
		}
		update_user_meta( get_current_user_id(), 'rrp_notif_prefs', $prefs );
		return new WP_REST_Response( array( 'prefs' => $prefs ), 200 );
	}

	private static function notify_stage_reviewers( $submission, $stage, $stage_index = -1 ) {
		$reviewers    = $stage['reviewers'] ?? array();
		$deadline_str = '';
		$cal_links    = '';
		if ( $stage_index >= 0 ) {
			$deadline_raw = Portal_Data::get_effective_deadline( $submission, $stage_index );
			if ( $deadline_raw ) {
				$deadline_str = gmdate( 'D, M j Y', strtotime( $deadline_raw ) );
				$evt_title = rawurlencode( 'Review Deadline: ' . ( $stage['stageName'] ?? '' ) . ' – ' . ( $submission['title'] ?? '' ) );
				$evt_desc  = rawurlencode( 'Submission ' . ( $submission['id'] ?? '' ) . ' | Stage: ' . ( $stage['stageName'] ?? '' ) );
				$d0 = gmdate( 'Ymd', strtotime( $deadline_raw ) );
				$d1 = gmdate( 'Ymd', strtotime( $deadline_raw ) + DAY_IN_SECONDS );
				$y0 = gmdate( 'Y-m-d', strtotime( $deadline_raw ) );
				$y1 = gmdate( 'Y-m-d', strtotime( $deadline_raw ) + DAY_IN_SECONDS );
				$cal_links = "\n\nAdd to Google Calendar: https://calendar.google.com/calendar/r/eventedit?text={$evt_title}&dates={$d0}/{$d1}&details={$evt_desc}" .
				             "\nAdd to Outlook:          https://outlook.live.com/calendar/0/deeplink/compose?subject={$evt_title}&startdt={$y0}&enddt={$y1}&body={$evt_desc}";
			}
		}
		foreach ( $reviewers as $reviewer ) {
			$email = trim( (string) ( $reviewer['email'] ?? '' ) );
			if ( ! $email || ! is_email( $email ) ) {
				continue;
			}
			if ( ! self::user_wants_notif( $email, 'stage_assigned' ) ) {
				continue;
			}
			$subject = 'Research Review Portal: Review Requested (' . ( $submission['id'] ?? '' ) . ')';
			$message = 'Hello ' . trim( (string) ( $reviewer['name'] ?? $reviewer['email'] ?? '' ) ) . ",\n\n" .
				'You have been assigned to review a submission for stage: ' . ( $stage['stageName'] ?? '' ) . "\n\n" .
				'Title: ' . ( $submission['title'] ?? '–' ) . "\n" .
				'Type:  ' . ( $submission['type']  ?? '–' ) . "\n" .
				'ID:    ' . ( $submission['id']    ?? '' )  . "\n" .
				( $deadline_str ? 'Deadline: ' . $deadline_str . "\n" : '' ) .
				$cal_links .
				"\n\nPlease log in to the portal to review the submission.\n\nThank you,\nResearch Review Portal";
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
		} elseif ( in_array( 'rrp_public', $roles, true ) ) {
			$portal_role = 'Public';
		} else {
			$portal_role = 'Student';
		}
		$wp_role = 'rrp_student';
		foreach ( array( 'rrp_admin', 'administrator', 'rrp_coordinator', 'rrp_faculty', 'rrp_reviewer', 'rrp_public', 'rrp_student' ) as $r ) {
			if ( in_array( $r, $roles, true ) ) { $wp_role = $r; break; }
		}
		$all_portal_roles = self::all_portal_roles();
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
			'locked'                => (bool) get_user_meta( $u->ID, 'rrp_locked', true ),
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
			// SSO-managed accounts must not have a password set — their only valid
			// login path is through Entra.  Setting a password would create a
			// parallel entry point that bypasses identity provider controls.
			if ( get_user_meta( $id, 'rrp_entra_oid', true ) ) {
				return new WP_REST_Response( array( 'error' => 'SSO-managed accounts cannot have a portal password. Use your institution login.' ), 403 );
			}
			// Require the current password to prevent session-hijacking account takeover.
			$current_pass_input = isset( $body['currentPassword'] ) ? (string) $body['currentPassword'] : '';
			if ( ! wp_check_password( $current_pass_input, $current->user_pass, $current->ID ) ) {
				return new WP_REST_Response( array( 'error' => 'Current password is incorrect.' ), 400 );
			}
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
		$all_roles   = self::all_portal_roles();
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
		} elseif ( $role_filter === 'public' ) {
			$roles_to_query = array( 'rrp_public' );
		} elseif ( $role_filter && in_array( $role_filter, $all_roles, true ) ) {
			// Custom role requested by full slug
			$roles_to_query = array( $role_filter );
		} else {
			$roles_to_query = $all_roles;
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
		$allowed_roles = self::all_portal_roles();

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
			$portal_roles = self::all_portal_roles();
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
			// SSO-managed accounts must not have a password — block even admins from
			// setting one, preventing credential-based takeover of Entra accounts.
			if ( get_user_meta( $id, 'rrp_entra_oid', true ) ) {
				return new WP_REST_Response( array( 'error' => 'Cannot set a password for an SSO-managed account.' ), 403 );
			}
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
		$allowed_roles = self::all_portal_roles();
		if ( current_user_can( 'rrp_full_admin_access' ) ) {
			if ( isset( $body['roles'] ) && is_array( $body['roles'] ) ) {
				// Multi-role assignment
				$new_roles = array_values( array_unique( array_filter(
					array_map( 'sanitize_key', $body['roles'] ),
					function( $r ) use ( $allowed_roles ) { return in_array( $r, $allowed_roles, true ); }
				) ) );
				if ( ! empty( $new_roles ) ) {
					$was_reviewer = user_can( $id, 'rrp_review_submissions' );
					// Remove all rrp_* portal roles first
					foreach ( $allowed_roles as $r ) { $user->remove_role( $r ); }
					// Set primary role, then add additional roles
					$user->set_role( $new_roles[0] );
					foreach ( array_slice( $new_roles, 1 ) as $r ) { $user->add_role( $r ); }
					// If user lost reviewer capability, clean up their assignments
					if ( $was_reviewer && ! user_can( $id, 'rrp_review_submissions' ) ) {
						self::remove_reviewer_from_submissions(
							strtolower( trim( (string) $user->user_email ) ),
							$user->display_name ?: $user->user_login,
							'role_changed'
						);
					}
				}
			} elseif ( ! empty( $body['role'] ) ) {
				// Single role change (backward compat)
				$new_role = sanitize_key( (string) $body['role'] );
				if ( in_array( $new_role, $allowed_roles, true ) ) {
					$was_reviewer = user_can( $id, 'rrp_review_submissions' );
					$user->set_role( $new_role );
					if ( $was_reviewer && ! user_can( $id, 'rrp_review_submissions' ) ) {
						self::remove_reviewer_from_submissions(
							strtolower( trim( (string) $user->user_email ) ),
							$user->display_name ?: $user->user_login,
							'role_changed'
						);
					}
				}
			}
		}

		return new WP_REST_Response( array( 'user' => self::format_portal_user( get_userdata( $id ) ) ), 200 );
	}

	/**
	 * Remove a reviewer (by email) from every submission's assignedReviewers and
	 * reviewStages[].reviewers arrays. Sets a `reviewerRemoved` flag and appends
	 * an audit log entry on each affected submission, then notifies coordinators.
	 *
	 * @param string $email      Lowercase reviewer email to remove.
	 * @param string $user_name  Display name (for audit messages).
	 * @param string $reason     'deleted' | 'role_changed'
	 */
	private static function remove_reviewer_from_submissions( string $email, string $user_name, string $reason ): void {
		$email = strtolower( trim( $email ) );
		if ( ! $email ) {
			return;
		}

		$data     = Portal_Data::read_submissions();
		$modified = false;
		$affected_ids = array();

		foreach ( $data['submissions'] as $idx => &$sub ) {
			$changed = false;

			// Top-level assignedReviewers
			if ( ! empty( $sub['assignedReviewers'] ) && is_array( $sub['assignedReviewers'] ) ) {
				$before = count( $sub['assignedReviewers'] );
				$sub['assignedReviewers'] = array_values( array_filter(
					$sub['assignedReviewers'],
					function( $r ) use ( $email ) {
						return strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) !== $email;
					}
				) );
				if ( count( $sub['assignedReviewers'] ) < $before ) {
					$changed = true;
				}
			}

			// Per-stage reviewers
			if ( ! empty( $sub['reviewStages'] ) && is_array( $sub['reviewStages'] ) ) {
				foreach ( $sub['reviewStages'] as &$stage ) {
					if ( empty( $stage['reviewers'] ) || ! is_array( $stage['reviewers'] ) ) {
						continue;
					}
					$before = count( $stage['reviewers'] );
					$stage['reviewers'] = array_values( array_filter(
						$stage['reviewers'],
						function( $r ) use ( $email ) {
							return strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) !== $email;
						}
					) );
					// Also remove any decision record keyed to this reviewer
					if ( isset( $stage['decisions'] ) && is_array( $stage['decisions'] ) ) {
						unset( $stage['decisions'][ $email ] );
					}
					if ( count( $stage['reviewers'] ) < $before ) {
						$changed = true;
					}
				}
				unset( $stage );
			}

			if ( $changed ) {
				$sub['reviewerRemoved'] = true;
				$reason_text = $reason === 'deleted'
					? "Reviewer {$user_name} ({$email}) was deleted from the system."
					: "Reviewer {$user_name} ({$email}) had their reviewer role removed.";
				self::append_audit_log( $data, $idx, 'reviewer_removed', $reason_text . ' Submission requires reviewer reassignment.' );
				$affected_ids[] = $sub['id'] ?? "#{$idx}";
				$modified = true;
			}
		}
		unset( $sub );

		if ( $modified ) {
			Portal_Data::write_submissions( $data );
			// Email all coordinators and admins so they know reassignment is needed.
			self::notify_coordinators_reviewer_removed( $email, $user_name, $reason, $affected_ids );
		}
	}

	/**
	 * Send a single email to all coordinators/admins listing affected submissions.
	 */
	private static function notify_coordinators_reviewer_removed( string $email, string $user_name, string $reason, array $affected_ids ): void {
		if ( ! function_exists( 'wp_mail' ) || empty( $affected_ids ) ) {
			return;
		}
		$coords = get_users( array(
			'role__in' => array( 'rrp_coordinator', 'rrp_admin', 'administrator' ),
			'fields'   => array( 'user_email' ),
		) );
		if ( empty( $coords ) ) {
			return;
		}
		$action_label = $reason === 'deleted' ? 'was deleted from the system' : 'had their reviewer role removed';
		$subject = 'Research Review Portal: Reviewer Removed — Reassignment Required';
		$id_list  = implode( ', ', $affected_ids );
		$message  = "Hello,\n\nReviewer {$user_name} ({$email}) {$action_label}.\n\n"
		          . "They have been automatically removed from the following submission(s) and reassignment is required:\n\n"
		          . "  {$id_list}\n\n"
		          . "Please log in to the Research Review Portal and reassign reviewers to those submissions.\n\n"
		          . "Thank you,\nResearch Review Portal";

		foreach ( $coords as $coord ) {
			$to = trim( (string) ( $coord->user_email ?? '' ) );
			if ( $to && is_email( $to ) ) {
				wp_mail( $to, $subject, $message );
			}
		}
	}

	public static function portal_users_delete( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( $id === get_current_user_id() ) {
			return new WP_REST_Response( array( 'error' => 'You cannot delete your own account.' ), 400 );
		}
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}
		if ( user_can( $id, 'rrp_full_admin_access' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'You cannot delete an administrator account.' ), 403 );
		}
		$user_email = strtolower( trim( (string) $user->user_email ) );
		$user_name  = $user->display_name ?: $user->user_login;
		// If the deleted user was a reviewer, strip them from all submissions first.
		if ( user_can( $id, 'rrp_review_submissions' ) ) {
			self::remove_reviewer_from_submissions( $user_email, $user_name, 'deleted' );
		}
		// Fully delete the WP user so the account no longer appears and the email is freed.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		wp_delete_user( $id );
		return new WP_REST_Response( array( 'removed' => true, 'userId' => $id ), 200 );
	}

	public static function portal_users_toggle_lock( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		if ( $id === get_current_user_id() ) {
			return new WP_REST_Response( array( 'error' => 'You cannot lock your own account.' ), 400 );
		}
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}
		if ( user_can( $id, 'rrp_full_admin_access' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'You cannot lock an administrator account.' ), 403 );
		}
		$lock = ! empty( $body['locked'] );
		if ( $lock ) {
			update_user_meta( $id, 'rrp_locked', '1' );
			// Destroy all active sessions so the user is immediately signed out.
			$sessions = WP_Session_Tokens::get_instance( $id );
			$sessions->destroy_all();
		} else {
			delete_user_meta( $id, 'rrp_locked' );
			// Clear the brute-force counter so the user starts with a clean slate.
			delete_user_meta( $id, 'rrp_failed_login_count' );
		}
		return new WP_REST_Response( array( 'locked' => $lock, 'userId' => $id ), 200 );
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
		// Reviewers now live in the WordPress database only.
		// Legacy JSON-only reviewers no longer exist. To remove a reviewer,
		// delete their WordPress account or remove the rrp_reviewer role via the Users panel.
		return new WP_REST_Response( array(
			'error' => 'Legacy JSON-only reviewers are no longer supported. All reviewers are WordPress users. Use the Users panel to manage reviewer accounts.',
		), 410 );
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
		// SSO-managed users must not have a password — resetting one would open a
		// parallel login path that bypasses Entra authentication entirely.
		if ( get_user_meta( $id, 'rrp_entra_oid', true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot reset the password of an SSO-managed account. This account authenticates exclusively via Microsoft Entra.' ), 403 );
		}
		$new_password = isset( $body['password'] ) && (string) $body['password'] !== '' ? (string) $body['password'] : wp_generate_password( 12, true, false );
		wp_set_password( $new_password, $id );
		// Email the new password to the user so it is not solely visible in API responses / proxy logs
		wp_mail(
			$user->user_email,
			'[Research Portal] Your password has been reset',
			"Hello " . $user->display_name . ",\n\nAn administrator has reset your portal password.\n\nNew temporary password: " . $new_password . "\n\nPlease log in and change your password immediately.\n\nIf you did not request this, contact your portal administrator.",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
		// Do NOT return the plaintext password in the API response (sensitive data exposure).
		return new WP_REST_Response( array( 'reset' => true, 'userId' => $id, 'notified' => true ), 200 );
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

	// ─── Meeting Scheduling ───────────────────────────────────────────────────

	public static function can_schedule_meeting( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$id  = $request->get_param( 'id' );
		$sub = self::get_submission_by_id( $id );
		if ( ! $sub ) {
			return false;
		}
		// Admins and coordinators are always allowed.
		if ( current_user_can( 'rrp_full_admin_access' ) || current_user_can( 'rrp_view_all_submissions' ) ) {
			return true;
		}
		$user_email = strtolower( trim( (string) wp_get_current_user()->user_email ) );
		// Submitter.
		if ( strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) ) === $user_email ) {
			return true;
		}
		// Assigned reviewer (top-level or per-stage).
		if ( current_user_can( 'rrp_review_submissions' ) ) {
			foreach ( $sub['assignedReviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
					return true;
				}
			}
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	public static function portal_meeting_create( WP_REST_Request $request ): WP_REST_Response {
		$id   = $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();

		$title    = sanitize_text_field( (string) ( $body['title']    ?? '' ) );
		$date_time = sanitize_text_field( (string) ( $body['dateTime'] ?? '' ) );
		$location = sanitize_text_field( (string) ( $body['location'] ?? '' ) );
		$notes    = sanitize_textarea_field( (string) ( $body['notes']    ?? '' ) );

		if ( ! $title || ! $date_time ) {
			return new WP_REST_Response( array( 'error' => 'Meeting title and date/time are required.' ), 400 );
		}

		// Validate dateTime is a plausible ISO-8601 / datetime-local value.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $date_time ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid date/time format.' ), 400 );
		}

		$data = Portal_Data::read_submissions();
		$idx  = -1;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$idx = $i;
				break;
			}
		}
		if ( $idx === -1 ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];

		// Locked submissions cannot be modified.
		$locked_statuses = array( 'Withdrawn', 'Cancelled' );
		if ( in_array( $sub['status'] ?? '', $locked_statuses, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot schedule a meeting for a withdrawn or cancelled submission.' ), 400 );
		}

		// Check allowMeetings flag on the submission type.
		$config = Portal_Data::read_config();
		$allow_meetings = false;
		foreach ( $config['submissionTypes'] ?? array() as $t ) {
			if ( $t['id'] === ( $sub['submissionType'] ?? $sub['type'] ?? '' ) ) {
				$allow_meetings = (bool) ( $t['allowMeetings'] ?? false );
				break;
			}
		}
		if ( ! $allow_meetings ) {
			return new WP_REST_Response( array( 'error' => 'Meeting scheduling is not enabled for this submission type.' ), 403 );
		}

		// Build meeting record.
		$user       = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );
		$user_name  = $user->display_name ?: $user->user_login;
		$submitter_email = strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) );
		$is_submitter    = $user_email === $submitter_email;

		$meeting = array(
			'id'              => uniqid( 'mtg-', true ),
			'scheduledBy'     => $user_name,
			'scheduledByEmail'=> $user_email,
			'scheduledByRole' => $is_submitter ? 'submitter' : 'reviewer',
			'title'           => $title,
			'dateTime'        => $date_time,
			'location'        => $location,
			'notes'           => $notes,
			'status'          => 'scheduled',
			'createdAt'       => gmdate( 'c' ),
		);

		if ( ! isset( $data['submissions'][ $idx ]['meetings'] ) || ! is_array( $data['submissions'][ $idx ]['meetings'] ) ) {
			$data['submissions'][ $idx ]['meetings'] = array();
		}
		$data['submissions'][ $idx ]['meetings'][] = $meeting;

		// Audit log entry.
		self::append_audit_log( $data, $idx, 'meeting_scheduled',
			sprintf( '%s scheduled a meeting: "%s" on %s%s',
				$user_name,
				$title,
				$date_time,
				$location ? ' at ' . $location : ''
			)
		);

		Portal_Data::write_submissions( $data );

		// Send email invite to the counterpart(s).
		self::send_meeting_invite_email( $sub, $meeting, $is_submitter );

		return new WP_REST_Response( array( 'meeting' => $meeting ), 201 );
	}

	private static function send_meeting_invite_email( array $sub, array $meeting, bool $scheduled_by_submitter ): void {
		$sub_id    = $sub['id'] ?? '';
		$sub_title = $sub['title'] ?? $sub_id;
		$subject   = 'Research Review Portal: Meeting Scheduled — ' . $sub_id;

		$body_template = "Hello,\n\nA meeting has been scheduled for submission %s (%s).\n\nMeeting Title: %s\nDate & Time:   %s\nLocation:      %s\n%sScheduled by:  %s (%s)\n\nPlease log in to the Research Review Portal to view full details.\n\nThank you,\nResearch Review Portal";

		$notes_line = $meeting['notes'] ? 'Notes:         ' . $meeting['notes'] . "\n" : '';
		$message = sprintf(
			$body_template,
			$sub_id,
			$sub_title,
			$meeting['title'],
			$meeting['dateTime'],
			$meeting['location'] ?: '(not specified)',
			$notes_line,
			$meeting['scheduledBy'],
			$meeting['scheduledByEmail']
		);

		if ( ! function_exists( 'wp_mail' ) ) {
			return;
		}

		if ( $scheduled_by_submitter ) {
			// Notify all assigned reviewers.
			$recipients = array();
			foreach ( $sub['assignedReviewers'] ?? array() as $r ) {
				$em = trim( (string) ( $r['email'] ?? '' ) );
				if ( $em && is_email( $em ) ) {
					$recipients[] = $em;
				}
			}
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					$em = trim( (string) ( $r['email'] ?? '' ) );
					if ( $em && is_email( $em ) && ! in_array( $em, $recipients, true ) ) {
						$recipients[] = $em;
					}
				}
			}
			foreach ( $recipients as $to ) {
				wp_mail( $to, $subject, $message );
			}
		} else {
			// Notify the submitter.
			$to = trim( (string) ( $sub['submitterEmail'] ?? '' ) );
			if ( $to && is_email( $to ) ) {
				wp_mail( $to, $subject, $message );
			}
		}
	}



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

	/**
	 * Configure PHPMailer with stored SMTP settings.
	 * Hooked to WordPress's 'phpmailer_init' action.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer  PHPMailer instance (passed by WP by reference).
	 */
	public static function configure_phpmailer( $phpmailer ) {
		if ( ! class_exists( 'RRP_Portal_Settings' ) ) {
			return;
		}
		if ( ! RRP_Portal_Settings::get( 'smtp_enabled' ) ) {
			return;
		}
		$host       = (string) RRP_Portal_Settings::get( 'smtp_host' );
		if ( empty( $host ) ) {
			return;
		}
		$port       = (int)    RRP_Portal_Settings::get( 'smtp_port' );
		$encryption = (string) RRP_Portal_Settings::get( 'smtp_encryption' );
		$auth       = (bool)   RRP_Portal_Settings::get( 'smtp_auth' );
		$user       = (string) RRP_Portal_Settings::get( 'smtp_user' );
		$password   = (string) RRP_Portal_Settings::get( 'smtp_password' );
		$from_name  = (string) RRP_Portal_Settings::get( 'smtp_from_name' );
		$from_email = (string) RRP_Portal_Settings::get( 'smtp_from_email' );

		$phpmailer->isSMTP();
		$phpmailer->Host     = $host;
		$phpmailer->Port     = $port > 0 ? $port : 587;
		$phpmailer->SMTPAuth = $auth;
		if ( $auth ) {
			$phpmailer->Username = $user;
			$phpmailer->Password = $password;
		}
		if ( 'ssl' === $encryption ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( 'tls' === $encryption ) {
			$phpmailer->SMTPSecure = 'tls';
		} else {
			$phpmailer->SMTPSecure = '';
			$phpmailer->SMTPAutoTLS = false;
		}
		// Enforce TLS certificate verification. The 'smtp_tls_skip_verify' setting
		// exists only for development environments where self-signed certs are used
		// and must never be enabled in production.  Disabling verify_peer leaves
		// the SMTP connection open to man-in-the-middle attacks.
		$skip_verify = (bool) RRP_Portal_Settings::get( 'smtp_tls_skip_verify' );
		$phpmailer->SMTPOptions = array(
			'ssl' => array(
				'verify_peer'       => ! $skip_verify,
				'verify_peer_name'  => ! $skip_verify,
				'allow_self_signed' => $skip_verify,
			),
		);
		if ( $from_email && is_email( $from_email ) ) {
			$phpmailer->From     = $from_email;
			$phpmailer->FromName = $from_name ?: $from_email;
			try {
				$phpmailer->setFrom( $from_email, $from_name ?: $from_email, false );
			} catch ( \Exception $e ) {
				// Silently ignore — From already set above.
			}
		}
	}

	// ── Azure Communication Services email ───────────────────────────────────────

	/**
	 * Parse an Azure Communication Services connection string.
	 * Format: endpoint=https://<resource>.communication.azure.com/;accesskey=<base64key>
	 *
	 * @param  string $cs  Connection string.
	 * @return array{endpoint:string,accesskey:string}|false
	 */
	private static function acs_parse_connection( $cs ) {
		$endpoint  = '';
		$accesskey = '';
		foreach ( explode( ';', $cs ) as $part ) {
			$part = trim( $part );
			if ( stripos( $part, 'endpoint=' ) === 0 ) {
				$endpoint = rtrim( substr( $part, 9 ), '/' );
			} elseif ( stripos( $part, 'accesskey=' ) === 0 ) {
				$accesskey = substr( $part, 10 );
			}
		}
		if ( ! $endpoint || ! $accesskey ) {
			return false;
		}
		return array( 'endpoint' => $endpoint, 'accesskey' => $accesskey );
	}

	/**
	 * Send an email via Azure Communication Services REST API (bypasses PHPMailer/sendmail).
	 *
	 * @param  string $to       Recipient e-mail address.
	 * @param  string $subject  Subject line.
	 * @param  string $message  Plain-text body.
	 * @param  string $html     Optional HTML body; auto-generated from plain-text when empty.
	 * @return true|WP_Error
	 */
	public static function acs_send_email( $to, $subject, $message, $html = '' ) {
		if ( ! class_exists( 'RRP_Portal_Settings' ) ) {
			return new WP_Error( 'acs_not_configured', 'Portal settings class not available.' );
		}
		$conn_str    = (string) RRP_Portal_Settings::get( 'acs_connection_string' );
		$sender_addr = (string) RRP_Portal_Settings::get( 'acs_sender_address' );
		if ( ! $conn_str || ! $sender_addr ) {
			return new WP_Error( 'acs_not_configured', 'ACS connection string or sender address not set.' );
		}
		$conn = self::acs_parse_connection( $conn_str );
		if ( ! $conn ) {
			return new WP_Error( 'acs_invalid_conn', 'Invalid ACS connection string format. Expected: endpoint=https://...;accesskey=...' );
		}
		$endpoint  = $conn['endpoint'];
		$accesskey = $conn['accesskey'];
		$host      = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		if ( ! $host ) {
			return new WP_Error( 'acs_invalid_endpoint', 'Cannot parse host from ACS endpoint URL.' );
		}
		if ( ! $html ) {
			$html = '<html><body>' . nl2br( esc_html( $message ) ) . '</body></html>';
		}
		$payload = array(
			'senderAddress' => $sender_addr,
			'recipients'    => array(
				'to' => array( array( 'address' => $to ) ),
			),
			'content' => array(
				'subject'   => $subject,
				'plainText' => $message,
				'html'      => $html,
			),
		);
		$body_json   = wp_json_encode( $payload );
		$api_version = '2023-03-31';
		$path_query  = '/emails:send?api-version=' . $api_version;
		$date_header = gmdate( 'D, d M Y H:i:s' ) . ' GMT';   // RFC 1123
		$content_sha = base64_encode( hash( 'sha256', $body_json, true ) );

		// Build HMAC-SHA256 signature per ACS authentication spec
		$string_to_sign = "POST\n{$path_query}\n{$date_header};{$host};{$content_sha}";
		$key_bytes      = base64_decode( $accesskey );
		$signature      = base64_encode( hash_hmac( 'sha256', $string_to_sign, $key_bytes, true ) );
		$auth_header    = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}";

		$response = wp_remote_post(
			$endpoint . $path_query,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'        => 'application/json',
					'x-ms-date'           => $date_header,
					'x-ms-content-sha256' => $content_sha,
					'Authorization'       => $auth_header,
				),
				'body' => $body_json,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}
		$resp_raw = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $resp_raw, true );
		$err_msg  = isset( $decoded['error']['message'] )
			? $decoded['error']['message']
			: "ACS returned HTTP {$code}: {$resp_raw}";
		return new WP_Error( 'acs_send_failed', $err_msg );
	}

	/**
	 * WordPress pre_wp_mail filter — routes all wp_mail() calls through ACS when enabled.
	 * Short-circuits WordPress's normal PHPMailer path.
	 *
	 * @param  null|bool $result  null = let wp_mail() proceed normally.
	 * @param  array     $args    { to, subject, message, headers, attachments }
	 * @return null|bool
	 */
	public static function pre_wp_mail_acs( $result, $args ) {
		if ( ! class_exists( 'RRP_Portal_Settings' ) ) {
			return null;
		}
		if ( ! (bool) RRP_Portal_Settings::get( 'acs_email_enabled' ) ) {
			return null; // ACS not enabled — fall through to normal wp_mail/SMTP
		}
		$to      = is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : (string) ( $args['to'] ?? '' );
		$subject = (string) ( $args['subject'] ?? '' );
		$message = (string) ( $args['message'] ?? '' );
		// Detect HTML content-type in headers
		$html    = '';
		$raw_hdr = $args['headers'] ?? array();
		$headers = is_array( $raw_hdr ) ? $raw_hdr : explode( "\n", str_replace( "\r\n", "\n", (string) $raw_hdr ) );
		foreach ( $headers as $header ) {
			if ( stripos( (string) $header, 'content-type: text/html' ) !== false ) {
				$html = '<html><body>' . $message . '</body></html>';
				break;
			}
		}
		$sent = self::acs_send_email( $to, $subject, $message, $html );
		return ( true === $sent );
	}

	/**
	 * Send a test email to verify SMTP settings.
	 * POST /settings/test-email   body: { "to": "recipient@example.com" }
	 */
	public static function portal_settings_test_email( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$to   = sanitize_email( (string) ( $body['to'] ?? '' ) );
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid recipient address.' ), 400 );
		}

		$subject = '[Research Portal] SMTP Test Email';
		$message = "This is a test email sent from the Research Review Portal.\n\n"
		         . "If you received this, your SMTP settings are configured correctly.\n\n"
		         . "Sent: " . current_time( 'mysql' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			return new WP_REST_Response( array( 'message' => 'Test email sent via SMTP to ' . $to . '.' ), 200 );
		}

		// Surface the PHPMailer error
		global $phpmailer;
		$detail = '';
		if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
			$detail = $phpmailer->ErrorInfo;
		}
		$error = $detail ?: 'wp_mail() returned false. Check SMTP host, port, credentials, and encryption settings.';
		return new WP_REST_Response( array( 'error' => $error ), 502 );
	}

	// ─── Extension Requests ───────────────────────────────────────────────────

	/**
	 * Reviewer requests a deadline extension for one of their assigned stages.
	 * POST /submissions/{id}/request-extension
	 * Body: { stageName, reason, requestedDays }
	 */
	public static function extension_request( WP_REST_Request $request ): WP_REST_Response {
		$id         = $request->get_param( 'id' );
		$body       = $request->get_json_params() ?: array();
		$stage_name = sanitize_text_field( (string) ( $body['stageName'] ?? '' ) );
		$reason     = sanitize_textarea_field( (string) ( $body['reason'] ?? '' ) );
		$req_days   = max( 1, (int) ( $body['requestedDays'] ?? 7 ) );

		if ( ! $stage_name || ! $reason ) {
			return new WP_REST_Response( array( 'error' => 'stageName and reason are required.' ), 400 );
		}

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}

		$user_email = strtolower( trim( wp_get_current_user()->user_email ?? '' ) );
		$sub        = $data['submissions'][ $idx ];
		if ( in_array( $sub['status'] ?? '', array( 'Withdrawn', 'Cancelled' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'Cannot request an extension on a ' . ( $sub['status'] ) . ' submission.' ), 409 );
		}
		$stage_idx  = -1;
		foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
			if ( strtolower( $stage['stageName'] ?? '' ) === strtolower( $stage_name ) ) {
				// Verify requester is assigned to this stage
				$assigned = array_filter( $stage['reviewers'] ?? array(), function ( $r ) use ( $user_email ) {
					return strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email;
				} );
				if ( empty( $assigned ) ) {
					return new WP_REST_Response( array( 'error' => 'You are not assigned to this stage.' ), 403 );
				}
				$stage_idx = $si;
				break;
			}
		}
		if ( $stage_idx < 0 ) {
			return new WP_REST_Response( array( 'error' => 'Stage not found.' ), 404 );
		}

		$req_id = 'ext-' . substr( md5( uniqid( $id . $stage_name, true ) ), 0, 8 );
		$ext_request = array(
			'id'            => $req_id,
			'requestedBy'   => $user_email,
			'reason'        => $reason,
			'requestedDays' => $req_days,
			'status'        => 'pending',
			'requestedAt'   => gmdate( 'c' ),
		);

		if ( ! isset( $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'] ) ) {
			$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'] = $ext_request;
		} else {
			// Overwrite if previous request was denied (allow re-request)
			$prev = $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'];
			if ( ( $prev['status'] ?? '' ) === 'pending' ) {
				return new WP_REST_Response( array( 'error' => 'A pending extension request already exists for this stage.' ), 409 );
			}
			$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'] = $ext_request;
		}

		self::append_audit_log( $data, $idx, 'extension_requested',
			'Extension request submitted for stage "' . $stage_name . '" by ' . $user_email . ' (+' . $req_days . 'd).' );
		Portal_Data::write_submissions( $data );

		// Notify coordinators
		$coord_users = get_users( array( 'role__in' => array( 'rrp_coordinator', 'rrp_admin' ), 'fields' => array( 'user_email' ) ) );
		foreach ( $coord_users as $cu ) {
			if ( is_email( $cu->user_email ) && self::user_wants_notif( $cu->user_email, 'extension_requested' ) ) {
				wp_mail( $cu->user_email,
					'[Research Portal] Extension Request — ' . $id,
					"A reviewer has requested a deadline extension.\n\nSubmission: " . ( $sub['title'] ?? $id ) .
					"\nStage: $stage_name\nRequested by: $user_email\nExtra days: $req_days\nReason: $reason\n\nPlease log in to approve or deny." );
			}
		}

		return new WP_REST_Response( array( 'message' => 'Extension request submitted.', 'requestId' => $req_id ), 200 );
	}

	/**
	 * Coordinator approves an extension request.
	 * POST /submissions/{id}/extension-requests/{reqId}/approve
	 * Body: { extraDays }   (optional override; defaults to requester's requestedDays)
	 */
	public static function extension_approve( WP_REST_Request $request ): WP_REST_Response {
		$id     = $request->get_param( 'id' );
		$req_id = $request->get_param( 'reqId' );
		$body   = $request->get_json_params() ?: array();

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}

		$sub       = $data['submissions'][ $idx ];
		$stage_idx = -1;
		foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
			$er = $stage['extensionRequest'] ?? null;
			if ( $er && ( $er['id'] ?? '' ) === $req_id ) { $stage_idx = $si; break; }
		}
		if ( $stage_idx < 0 ) {
			return new WP_REST_Response( array( 'error' => 'Extension request not found.' ), 404 );
		}

		$er        = $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'];
		$extra     = max( 1, (int) ( $body['extraDays'] ?? $er['requestedDays'] ?? 7 ) );
		// Calculate extended deadline: base = current effective deadline + extra days
		$base_ts   = strtotime( Portal_Data::get_effective_deadline( $sub, $stage_idx ) );
		$new_ts    = $base_ts + ( $extra * DAY_IN_SECONDS );
		$new_dl    = gmdate( 'c', $new_ts );

		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest']['status']     = 'approved';
		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest']['resolvedAt'] = gmdate( 'c' );
		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionApproved']              = true;
		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionDeadline']              = $new_dl;
		// Reset reminder so a new one can be sent at the extended deadline
		unset( $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['reminderSentAt'] );

		$stage_name = $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['stageName'] ?? '';
		self::append_audit_log( $data, $idx, 'extension_approved',
			'Extension approved for stage "' . $stage_name . '". New deadline: ' . gmdate( 'Y-m-d', $new_ts ) . '.' );
		Portal_Data::write_submissions( $data );

		// Notify requester
		$req_email = $er['requestedBy'] ?? '';
		if ( is_email( $req_email ) && self::user_wants_notif( $req_email, 'extension_resolved' ) ) {
			wp_mail( $req_email,
				'[Research Portal] Extension Approved — ' . $id,
				"Your deadline extension request has been approved.\n\nSubmission: " . ( $sub['title'] ?? $id ) .
				"\nStage: $stage_name\nNew deadline: " . gmdate( 'Y-m-d', $new_ts ) );
		}

		return new WP_REST_Response( array( 'message' => 'Extension approved.', 'extensionDeadline' => $new_dl ), 200 );
	}

	/**
	 * Coordinator denies an extension request.
	 * POST /submissions/{id}/extension-requests/{reqId}/deny
	 * Body: { reason }
	 */
	public static function extension_deny( WP_REST_Request $request ): WP_REST_Response {
		$id     = $request->get_param( 'id' );
		$req_id = $request->get_param( 'reqId' );
		$body   = $request->get_json_params() ?: array();
		$reason = sanitize_textarea_field( (string) ( $body['reason'] ?? '' ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}

		$sub       = $data['submissions'][ $idx ];
		$stage_idx = -1;
		foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
			$er = $stage['extensionRequest'] ?? null;
			if ( $er && ( $er['id'] ?? '' ) === $req_id ) { $stage_idx = $si; break; }
		}
		if ( $stage_idx < 0 ) {
			return new WP_REST_Response( array( 'error' => 'Extension request not found.' ), 404 );
		}

		$er         = $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest'];
		$stage_name = $data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['stageName'] ?? '';

		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest']['status']     = 'denied';
		$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest']['resolvedAt'] = gmdate( 'c' );
		if ( $reason ) {
			$data['submissions'][ $idx ]['reviewStages'][ $stage_idx ]['extensionRequest']['denyReason'] = $reason;
		}

		self::append_audit_log( $data, $idx, 'extension_denied',
			'Extension denied for stage "' . $stage_name . '".' . ( $reason ? ' Reason: ' . $reason : '' ) );
		Portal_Data::write_submissions( $data );

		// Notify requester
		$req_email = $er['requestedBy'] ?? '';
		if ( is_email( $req_email ) && self::user_wants_notif( $req_email, 'extension_resolved' ) ) {
			wp_mail( $req_email,
				'[Research Portal] Extension Request Denied — ' . $id,
				"Your deadline extension request has been denied.\n\nSubmission: " . ( $sub['title'] ?? $id ) .
				"\nStage: $stage_name" . ( $reason ? "\nReason: $reason" : '' ) );
		}

		return new WP_REST_Response( array( 'message' => 'Extension request denied.' ), 200 );
	}

	/**
	 * List all pending extension requests across all submissions (coordinator view).
	 * GET /extension-requests
	 */
	public static function appeals_list( WP_REST_Request $request ): WP_REST_Response {
		$data   = Portal_Data::read_submissions();
		$result = array();
		foreach ( $data['submissions'] as $sub ) {
			$appeal = $sub['appeal'] ?? null;
			if ( ! $appeal ) continue;
			$result[] = array(
				'submissionId'  => $sub['id'] ?? '',
				'title'         => $sub['title'] ?? '',
				'submitterName' => $sub['submitterName'] ?? '',
				'type'          => $sub['submissionType'] ?? $sub['type'] ?? '',
				'appeal'        => $appeal,
				'submissionStatus' => $sub['status'] ?? '',
			);
		}
		usort( $result, function ( $a, $b ) {
			$order = array( 'pending' => 0, 'under_review' => 1, 'upheld' => 2, 'overturned' => 3 );
			$ap = $order[ $a['appeal']['status'] ?? '' ] ?? 9;
			$bp = $order[ $b['appeal']['status'] ?? '' ] ?? 9;
			if ( $ap !== $bp ) return $ap - $bp;
			return strcmp( $b['appeal']['submittedAt'] ?? '', $a['appeal']['submittedAt'] ?? '' );
		} );
		return new WP_REST_Response( array( 'appeals' => $result ), 200 );
	}

	public static function extension_requests_list( WP_REST_Request $request ): WP_REST_Response {
		$data    = Portal_Data::read_submissions();
		$pending = array();
		foreach ( $data['submissions'] as $sub ) {
			foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
				$er = $stage['extensionRequest'] ?? null;
				if ( ! $er ) continue;
				$pending[] = array(
					'submissionId'  => $sub['id'] ?? '',
					'title'         => $sub['title'] ?? '',
					'stageIndex'    => $si,
					'stageName'     => $stage['stageName'] ?? '',
					'currentDeadline' => Portal_Data::get_effective_deadline( $sub, $si ),
					'request'       => $er,
				);
			}
		}
		// Sort: pending first, then by requestedAt desc
		usort( $pending, function ( $a, $b ) {
			$ap = ( $a['request']['status'] ?? '' ) === 'pending' ? 0 : 1;
			$bp = ( $b['request']['status'] ?? '' ) === 'pending' ? 0 : 1;
			if ( $ap !== $bp ) return $ap - $bp;
			return strcmp( $b['request']['requestedAt'] ?? '', $a['request']['requestedAt'] ?? '' );
		} );
		return new WP_REST_Response( array( 'extensionRequests' => $pending ), 200 );
	}

	/**
	 * GET /calendar-events
	 * Returns one upcoming deadline event per active submission (the current active stage deadline).
	 * Coordinators see all; students/reviewers see only their own relevant submissions.
	 */
	public static function calendar_events( WP_REST_Request $request ): WP_REST_Response {
		$data         = Portal_Data::read_submissions();
		$user         = wp_get_current_user();
		$user_email   = strtolower( trim( $user->user_email ?? '' ) );
		$is_coord     = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$terminal     = array( 'Withdrawn', 'Cancelled', 'Rejected', 'Draft' );
		$events       = array();

		foreach ( $data['submissions'] as $sub ) {
			if ( in_array( $sub['status'] ?? '', $terminal, true ) ) continue;
			if ( in_array( $sub['status'] ?? '', Portal_Data::PUBLIC_STATUSES, true ) ) continue;
			$st_lc = strtolower( $sub['status'] ?? '' );
			if ( $st_lc === 'approved' || $st_lc === 'confirmed for presentation' || $st_lc === 'published' ) continue;

			// Scope to relevant submissions for non-coordinators
			if ( ! $is_coord ) {
				$is_submitter = strtolower( (string) ( $sub['submitterEmail'] ?? '' ) ) === $user_email;
				$is_reviewer  = false;
				foreach ( $sub['reviewStages'] ?? array() as $stage ) {
					foreach ( $stage['reviewers'] ?? array() as $r ) {
						if ( strtolower( (string) ( $r['email'] ?? '' ) ) === $user_email ) { $is_reviewer = true; break 2; }
					}
				}
				if ( ! $is_submitter && ! $is_reviewer ) continue;
			}

			// Find the first active (non-skipped, non-approved) stage
			foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
				if ( $stage['skipped'] ?? false ) continue;
				if ( Portal_Data::is_stage_approved( $stage ) ) continue;
				$deadline = Portal_Data::get_effective_deadline( $sub, $si );
				if ( ! $deadline ) break;
				$events[] = array(
					'submissionId'   => $sub['id']            ?? '',
					'title'          => $sub['title']         ?? '',
					'type'           => $sub['submissionType'] ?? $sub['type'] ?? '',
					'stageIndex'     => $si,
					'stageName'      => $stage['stageName']   ?? '',
					'deadline'       => $deadline,
					'status'         => $sub['status']        ?? '',
					'submitterEmail' => $sub['submitterEmail'] ?? '',
				);
				break; // only one event per submission (active stage)
			}
		}

		usort( $events, function ( $a, $b ) { return strcmp( $a['deadline'], $b['deadline'] ); } );
		return new WP_REST_Response( array( 'events' => $events ), 200 );
	}

	/**
	 * GET /submissions/inactive?days=N
	 * Returns submissions with no activity for N days (default 30).
	 * Excludes already-terminal statuses (Withdrawn, Cancelled, Rejected, Draft).
	 */
	public static function submissions_inactive( WP_REST_Request $request ): WP_REST_Response {
		$days = intval( $request->get_param( 'days' ) ?: 30 );
		if ( $days < 1 )   $days = 1;
		if ( $days > 365 ) $days = 365;
		$cutoff = time() - ( $days * DAY_IN_SECONDS );

		$data          = Portal_Data::read_submissions();
		$skip_statuses = array( 'Withdrawn', 'Cancelled', 'Rejected', 'Draft' );
		$inactive      = array();

		foreach ( $data['submissions'] as $sub ) {
			if ( in_array( $sub['status'] ?? '', $skip_statuses, true ) ) continue;
			// Also skip fully-approved/published terminal statuses.
			$st_lc = strtolower( $sub['status'] ?? '' );
			if ( in_array( $sub['status'] ?? '', Portal_Data::PUBLIC_STATUSES, true ) ) continue;
			if ( $st_lc === 'approved' || $st_lc === 'confirmed for presentation' || $st_lc === 'published' ) continue;

			// Determine last activity time from audit log, then createdAt.
			$last_activity = strtotime( $sub['createdAt'] ?? '' );
			$audit_log     = $sub['auditLog'] ?? array();
			if ( ! empty( $audit_log ) ) {
				$last_entry    = end( $audit_log );
				$log_time      = strtotime( $last_entry['at'] ?? '' );
				if ( $log_time && $log_time > $last_activity ) {
					$last_activity = $log_time;
				}
			}

			if ( ! $last_activity || $last_activity >= $cutoff ) continue;

			$sub['_daysSinceActivity'] = (int) round( ( time() - $last_activity ) / DAY_IN_SECONDS );
			$sub['_lastActivityAt']    = gmdate( 'c', $last_activity );
			$inactive[]               = $sub;
		}

		// Sort most-inactive first.
		usort( $inactive, function ( $a, $b ) {
			return ( $b['_daysSinceActivity'] ?? 0 ) - ( $a['_daysSinceActivity'] ?? 0 );
		} );

		return new WP_REST_Response( array( 'submissions' => $inactive, 'days' => $days ), 200 );
	}

	/**
	 * POST /submissions/bulk-cancel
	 * Body: { ids: string[], reason: string }
	 * Cancels up to 100 submissions in one request. Skips already-terminal ones.
	 */
	public static function submissions_bulk_cancel( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params() ?: array();
		$ids    = isset( $body['ids'] ) && is_array( $body['ids'] ) ? $body['ids'] : array();
		$reason = isset( $body['reason'] ) ? sanitize_text_field( (string) $body['reason'] ) : 'Cancelled in bulk by coordinator.';

		if ( empty( $ids ) ) {
			return new WP_REST_Response( array( 'error' => 'No submission IDs provided.' ), 400 );
		}
		$ids = array_values( array_filter( array_map( 'sanitize_text_field', $ids ) ) );
		if ( count( $ids ) > 100 ) {
			return new WP_REST_Response( array( 'error' => 'Cannot cancel more than 100 submissions at once.' ), 400 );
		}

		$data          = Portal_Data::read_submissions();
		$user          = wp_get_current_user();
		$c_by          = $user->user_email ?: $user->user_login;
		$c_name        = $user->display_name ?: $user->user_login;
		$non_cancellable = array( 'Withdrawn', 'Cancelled' );
		$cancelled     = 0;
		$skipped       = 0;

		foreach ( $data['submissions'] as $i => &$sub ) {
			if ( ! in_array( $sub['id'] ?? '', $ids, true ) ) continue;
			if ( in_array( $sub['status'] ?? '', $non_cancellable, true ) ) { $skipped++; continue; }

			$sub = array_merge( $sub, array(
				'status'          => 'Cancelled',
				'cancelledAt'     => gmdate( 'c' ),
				'cancelledBy'     => $c_by,
				'cancelledByName' => $c_name,
				'cancelReason'    => $reason,
			) );
			self::append_audit_log( $data, $i, 'status_changed', 'Submission cancelled (bulk) by ' . $c_name . '. Reason: ' . $reason );
			// Per-submission email notification
			$to = $sub['submitterEmail'] ?? '';
			if ( $to && is_email( $to ) && self::user_wants_notif( $to, 'submission_status_changed' ) ) {
				wp_mail(
					$to,
					'Research Review Portal: Submission Cancelled (' . ( $sub['id'] ?? '' ) . ')',
					'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\nYour submission \"" . ( $sub['title'] ?? '' ) . '" (ID: ' . ( $sub['id'] ?? '' ) . ") has been cancelled.\n\nReason: " . $reason . "\n\nIf you have questions, please contact your coordinator."
				);
			}
			$cancelled++;
		}
		unset( $sub );

		Portal_Data::write_submissions( $data );

		return new WP_REST_Response( array(
			'cancelled' => $cancelled,
			'skipped'   => $skipped,
			'total'     => count( $ids ),
		), 200 );
	}

	// ── Administration: permission ──────────────────────────────────────────
	public static function is_full_admin( WP_REST_Request $request ) {
		return current_user_can( 'rrp_full_admin_access' );
	}

	// ── Administration: Full backup download ────────────────────────────────
	// GET /admin/backup
	// Returns a ZIP of all data files and uploads as base64, filename-stamped.
	public static function admin_backup_download( WP_REST_Request $request ): WP_REST_Response {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_REST_Response( array( 'error' => 'ZipArchive PHP extension is not available on this server.' ), 500 );
		}

		$tmp = tempnam( sys_get_temp_dir(), 'rrp-backup' ) . '.zip';
		$zip = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return new WP_REST_Response( array( 'error' => 'Could not create backup archive.' ), 500 );
		}

		// Export submissions from database → data/submissions.json
		$sub_data = Portal_Data::read_submissions();
		$zip->addFromString(
			'data/submissions.json',
			(string) wp_json_encode(
				array( 'submissions' => $sub_data['submissions'], 'nextIds' => $sub_data['nextIds'] ),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		// Export portal config → data/config.json
		$zip->addFromString(
			'data/config.json',
			(string) wp_json_encode(
				Portal_Data::read_config(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		// Export additional DB metadata (webhooks, db version) → data/portal-db-meta.json
		$zip->addFromString(
			'data/portal-db-meta.json',
			(string) wp_json_encode(
				array(
					'webhooks'  => Portal_Data::read_webhooks(),
					'dbVersion' => (string) get_option( 'rrp_db_version', '' ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		// Recursively add uploads directory
		$uploads_dir = RRP_UPLOADS_DIR;
		if ( is_dir( $uploads_dir ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $it as $item ) {
				if ( $item->isFile() ) {
					$relative = 'data/uploads/' . str_replace( $uploads_dir, '', $item->getPathname() );
					$zip->addFile( $item->getPathname(), $relative );
				}
			}
		}

		$zip->close();

		$content  = base64_encode( file_get_contents( $tmp ) );
		$filename = 'rrp-backup-' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
		@unlink( $tmp );

		return new WP_REST_Response( array(
			'filename' => $filename,
			'content'  => $content,
			'size'     => strlen( $content ),
		), 200 );
	}

	// ── Administration: Restore from backup ─────────────────────────────────
	// POST /admin/restore   (multipart file upload, field: "backup")
	public static function admin_restore( WP_REST_Request $request ): WP_REST_Response {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_REST_Response( array( 'error' => 'ZipArchive PHP extension is not available.' ), 500 );
		}

		$files = $request->get_file_params();
		if ( empty( $files['backup']['tmp_name'] ) || ! is_uploaded_file( $files['backup']['tmp_name'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No backup file uploaded. Use field name "backup".' ), 400 );
		}

		$tmp = $files['backup']['tmp_name'];
		$zip = new ZipArchive();
		if ( $zip->open( $tmp ) !== true ) {
			return new WP_REST_Response( array( 'error' => 'Uploaded file is not a valid ZIP archive.' ), 400 );
		}

		// Validate: must contain submissions.json or config.json
		$has_data = false;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( in_array( $name, array( 'data/submissions.json', 'data/config.json' ), true ) ) {
				$has_data = true;
				break;
			}
		}
		if ( ! $has_data ) {
			$zip->close();
			return new WP_REST_Response( array( 'error' => 'The uploaded ZIP does not appear to be a valid RRP backup (missing data/submissions.json or data/config.json).' ), 400 );
		}

		$restored = array();

		// Restore submissions → database
		$sub_content = $zip->getFromName( 'data/submissions.json' );
		if ( $sub_content !== false ) {
			$sub_decoded = json_decode( (string) $sub_content, true );
			if ( ! is_array( $sub_decoded ) ) {
				$zip->close();
				return new WP_REST_Response( array( 'error' => 'data/submissions.json contains invalid JSON.' ), 400 );
			}
			Portal_Data::write_submissions( $sub_decoded );
			$restored[] = 'submissions (' . count( $sub_decoded['submissions'] ?? array() ) . ' records)';
		}

		// Restore config → wp_options
		$cfg_content = $zip->getFromName( 'data/config.json' );
		if ( $cfg_content !== false ) {
			$cfg_decoded = json_decode( (string) $cfg_content, true );
			if ( ! is_array( $cfg_decoded ) ) {
				$zip->close();
				return new WP_REST_Response( array( 'error' => 'data/config.json contains invalid JSON.' ), 400 );
			}
			Portal_Data::write_config( $cfg_decoded );
			$restored[] = 'config';
		}

		// Restore additional metadata (webhooks) — present only in new-format backups
		$meta_content = $zip->getFromName( 'data/portal-db-meta.json' );
		if ( $meta_content !== false ) {
			$meta = json_decode( (string) $meta_content, true );
			if ( is_array( $meta ) && isset( $meta['webhooks'] ) && is_array( $meta['webhooks'] ) ) {
				Portal_Data::write_webhooks( $meta['webhooks'] );
				$restored[] = 'webhooks';
			}
		}

		// Clear reviewer-scan transient so the orphan check re-runs after restore
		delete_transient( 'rrp_reviewer_scan' );

		// Restore uploads
		$uploads_restored = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( strpos( $name, 'data/uploads/' ) === 0 && substr( $name, -1 ) !== '/' ) {
				$rel      = substr( $name, strlen( 'data/uploads/' ) );
				$dest     = RRP_UPLOADS_DIR . $rel;
				$dest_dir = dirname( $dest );
				if ( ! is_dir( $dest_dir ) ) { wp_mkdir_p( $dest_dir ); }
				// Sanitize path to prevent ZipSlip — require trailing separator so a prefix like
				// /var/www/uploads_evil cannot pass the check by matching /var/www/uploads.
				$real_uploads = realpath( RRP_UPLOADS_DIR );
				$real_dest    = realpath( $dest_dir ) . DIRECTORY_SEPARATOR . basename( $dest );
				if ( strpos( $real_dest, $real_uploads . DIRECTORY_SEPARATOR ) !== 0 ) { continue; }
				file_put_contents( $dest, $zip->getFromName( $name ) );
				$uploads_restored++;
			}
		}

		$zip->close();

		return new WP_REST_Response( array(
			'restored' => $restored,
			'uploads'  => $uploads_restored,
			'message'  => 'Backup restored successfully. Restored: ' . implode( ', ', $restored ) . '; ' . $uploads_restored . ' upload file(s).',
		), 200 );
	}

	// ── Administration: List archives ───────────────────────────────────────
	// GET /admin/archives
	public static function admin_archives_list( WP_REST_Request $request ): WP_REST_Response {
		$archives_dir = RRP_DATA_DIR . 'archives/';
		if ( ! is_dir( $archives_dir ) ) {
			return new WP_REST_Response( array( 'archives' => array() ), 200 );
		}

		$files = glob( $archives_dir . 'archive-*.zip' );
		if ( ! $files ) {
			return new WP_REST_Response( array( 'archives' => array() ), 200 );
		}
		rsort( $files ); // newest first

		$list = array_map( function ( $f ) {
			$name = basename( $f );
			// Extract metadata JSON sidecar if present
			$meta_file = dirname( $f ) . '/' . str_replace( '.zip', '.json', $name );
			$meta = file_exists( $meta_file ) ? json_decode( file_get_contents( $meta_file ), true ) : array();
			return array(
				'name'          => $name,
				'size'          => filesize( $f ),
				'createdAt'     => gmdate( 'c', filemtime( $f ) ),
				'submissionCount' => $meta['submissionCount'] ?? 0,
				'criteria'      => $meta['criteria'] ?? '',
			);
		}, $files );

		return new WP_REST_Response( array( 'archives' => $list ), 200 );
	}

	// ── Administration: Archive (age-based) submissions ─────────────────────
	// POST /admin/archive-submissions  body: { olderThanDays: 365, reason: '...' }
	public static function admin_archive_submissions( WP_REST_Request $request ): WP_REST_Response {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_REST_Response( array( 'error' => 'ZipArchive PHP extension is not available.' ), 500 );
		}

		$body = $request->get_json_params() ?? array();
		$days = intval( $body['olderThanDays'] ?? 365 );
		if ( $days < 30 ) { return new WP_REST_Response( array( 'error' => 'olderThanDays must be at least 30.' ), 400 ); }
		$reason  = sanitize_text_field( $body['reason'] ?? 'Routine archival' );
		$cutoff  = strtotime( '-' . $days . ' days' );
		$by      = wp_get_current_user()->user_email ?? 'admin';

		$data = Portal_Data::read_submissions();
		$all  = $data['submissions'] ?? array();

		// Only archive terminal, old submissions
		$terminal = array( 'Withdrawn', 'Cancelled', 'Rejected', 'Approved', 'Confirmed for Presentation', 'Published', 'Approved for Submission', 'Accepted' );
		$to_archive = array();
		$keep       = array();
		foreach ( $all as $sub ) {
			$created = isset( $sub['createdAt'] ) ? strtotime( $sub['createdAt'] ) : 0;
			if ( in_array( $sub['status'] ?? '', $terminal, true ) && $created > 0 && $created < $cutoff ) {
				$to_archive[] = $sub;
			} else {
				$keep[] = $sub;
			}
		}

		if ( empty( $to_archive ) ) {
			return new WP_REST_Response( array( 'archived' => 0, 'message' => 'No submissions matched the archival criteria.' ), 200 );
		}

		// Build ZIP
		$archive_dir = RRP_DATA_DIR . 'archives/';
		if ( ! is_dir( $archive_dir ) ) { wp_mkdir_p( $archive_dir ); }
		$stamp    = gmdate( 'Y-m-d_H-i-s' );
		$zip_name = 'archive-' . $stamp . '.zip';
		$zip_path = $archive_dir . $zip_name;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return new WP_REST_Response( array( 'error' => 'Could not create archive file.' ), 500 );
		}

		// Add archive manifest
		$manifest = array(
			'archivedAt'       => gmdate( 'c' ),
			'archivedBy'       => $by,
			'reason'           => $reason,
			'criteria'         => 'Terminal submissions older than ' . $days . ' days',
			'submissionCount'  => count( $to_archive ),
		);
		$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT ) );
		$zip->addFromString( 'submissions.json', json_encode( array( 'submissions' => $to_archive ), JSON_PRETTY_PRINT ) );

		// Add uploads for each archived submission
		foreach ( $to_archive as $sub ) {
			$sub_upload_dir = RRP_UPLOADS_DIR . ( $sub['id'] ?? '' ) . '/';
			if ( is_dir( $sub_upload_dir ) ) {
				$it = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $sub_upload_dir, RecursiveDirectoryIterator::SKIP_DOTS )
				);
				foreach ( $it as $item ) {
					if ( $item->isFile() ) {
						$rel = 'uploads/' . ( $sub['id'] ?? '' ) . '/' . $item->getFilename();
						$zip->addFile( $item->getPathname(), $rel );
					}
				}
			}
		}

		$zip->close();

		// Write sidecar metadata for listing
		file_put_contents(
			$archive_dir . 'archive-' . $stamp . '.json',
			json_encode( $manifest, JSON_PRETTY_PRINT )
		);

		// Remove archived submissions from active data and delete their upload directories
		$data['submissions'] = array_values( $keep );
		Portal_Data::write_submissions( $data );

		foreach ( $to_archive as $sub ) {
			$sub_upload_dir = RRP_UPLOADS_DIR . ( $sub['id'] ?? '' );
			if ( is_dir( $sub_upload_dir ) ) {
				// Recursively remove the upload folder for archived submission
				$dit = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $sub_upload_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::CHILD_FIRST
				);
				foreach ( $dit as $f ) {
					$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
				}
				rmdir( $sub_upload_dir );
			}
		}

		return new WP_REST_Response( array(
			'archived'    => count( $to_archive ),
			'remaining'   => count( $keep ),
			'archiveName' => $zip_name,
			'message'     => count( $to_archive ) . ' submission(s) archived to ' . $zip_name . '.',
		), 200 );
	}

	// ── Administration: Download a stored archive ────────────────────────────
	// GET /admin/archives/{name}/download
	public static function admin_archive_download( WP_REST_Request $request ): WP_REST_Response {
		$name = sanitize_file_name( $request->get_param( 'name' ) );
		// Allow only archive-*.zip pattern
		if ( ! preg_match( '/^archive-[\w\-]+\.zip$/', $name ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid archive name.' ), 400 );
		}
		$path = RRP_DATA_DIR . 'archives/' . $name;
		if ( ! file_exists( $path ) ) {
			return new WP_REST_Response( array( 'error' => 'Archive not found.' ), 404 );
		}
		$content = base64_encode( file_get_contents( $path ) );
		return new WP_REST_Response( array( 'filename' => $name, 'content' => $content ), 200 );
	}

	// ── Administration: Delete a stored archive ──────────────────────────────
	// DELETE /admin/archives/{name}
	public static function admin_archive_delete( WP_REST_Request $request ): WP_REST_Response {
		$name = sanitize_file_name( $request->get_param( 'name' ) );
		if ( ! preg_match( '/^archive-[\w\-]+\.zip$/', $name ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid archive name.' ), 400 );
		}
		$path = RRP_DATA_DIR . 'archives/' . $name;
		if ( ! file_exists( $path ) ) {
			return new WP_REST_Response( array( 'error' => 'Archive not found.' ), 404 );
		}
		unlink( $path );
		$sidecar = str_replace( '.zip', '.json', $path );
		if ( file_exists( $sidecar ) ) { unlink( $sidecar ); }
		return new WP_REST_Response( array( 'deleted' => $name ), 200 );
	}

	// ─── Helper: all portal role slugs (core + custom) ───────────────────────

	/**
	 * Returns merged array of the 5 core portal role slugs + any custom roles
	 * stored in config.json.
	 */
	private static function all_portal_roles() {
		$core   = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		$custom = RRP_User_Management::get_custom_roles();
		$slugs  = array_filter( array_column( $custom, 'slug' ), function ( $s ) { return (bool) $s; } );
		return array_values( array_unique( array_merge( $core, array_map( 'sanitize_key', array_values( $slugs ) ) ) ) );
	}

	// ─── Role Management ─────────────────────────────────────────────────────

	// GET /admin/roles
	public static function admin_roles_list( WP_REST_Request $request ): WP_REST_Response {
		$core_slugs = array(
			'rrp_student'     => array( 'label' => 'Research Student',       'color' => '#3b82f6' ),
			'rrp_reviewer'    => array( 'label' => 'Research Reviewer',      'color' => '#8b5cf6' ),
			'rrp_coordinator' => array( 'label' => 'Research Coordinator',   'color' => '#f59e0b' ),
			'rrp_admin'       => array( 'label' => 'Research Administrator', 'color' => '#ef4444' ),
			'rrp_faculty'     => array( 'label' => 'Research Faculty',       'color' => '#10b981' ),
			'rrp_public'      => array( 'label' => 'Public Submitter',       'color' => '#78716c' ),
		);
		$roles = array();
		foreach ( $core_slugs as $slug => $meta ) {
			$roles[] = array(
				'slug'  => $slug,
				'label' => $meta['label'],
				'color' => $meta['color'],
				'type'  => 'core',
			);
		}
		foreach ( RRP_User_Management::get_custom_roles() as $c ) {
			$slug = isset( $c['slug'] ) ? sanitize_key( (string) $c['slug'] ) : '';
			if ( ! $slug ) continue;
			$roles[] = array(
				'slug'  => $slug,
				'label' => isset( $c['name'] ) ? (string) $c['name'] : $slug,
				'color' => isset( $c['color'] ) ? (string) $c['color'] : '#6b7280',
				'type'  => 'custom',
			);
		}
		return new WP_REST_Response( array( 'roles' => $roles ), 200 );
	}

	// POST /admin/roles
	public static function admin_role_create( WP_REST_Request $request ): WP_REST_Response {
		$body  = $request->get_json_params() ?: array();
		$name  = sanitize_text_field( $body['name'] ?? '' );
		$color = sanitize_text_field( $body['color'] ?? '#6b7280' );

		if ( ! $name ) {
			return new WP_REST_Response( array( 'error' => 'Role name is required.' ), 400 );
		}

		// Auto-generate slug from name: lowercase, spaces → underscore, prefix rrp_
		$base  = preg_replace( '/[^a-z0-9]+/', '_', strtolower( $name ) );
		$slug  = 'rrp_' . trim( $base, '_' );
		if ( strlen( $slug ) < 6 ) {
			return new WP_REST_Response( array( 'error' => 'Role name too short.' ), 400 );
		}

		// Reject if it conflicts with a core role
		$core = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		if ( in_array( $slug, $core, true ) ) {
			return new WP_REST_Response( array( 'error' => 'A core role with that name already exists.' ), 409 );
		}

		$config  = Portal_Data::read_config();
		$customs = isset( $config['customRoles'] ) && is_array( $config['customRoles'] ) ? $config['customRoles'] : array();

		// Reject duplicate slug
		foreach ( $customs as $c ) {
			if ( sanitize_key( (string) ( $c['slug'] ?? '' ) ) === $slug ) {
				return new WP_REST_Response( array( 'error' => 'A role with that name already exists.' ), 409 );
			}
		}

		$new_role = array( 'slug' => $slug, 'name' => $name, 'color' => $color );
		$customs[] = $new_role;
		$config['customRoles'] = $customs;
		Portal_Data::write_config( $config );

		// Register the WP role immediately so it can be used without a page reload
		if ( ! get_role( $slug ) ) {
			add_role( $slug, $name, array( 'read' => true ) );
		}

		return new WP_REST_Response( array( 'role' => array_merge( $new_role, array( 'type' => 'custom' ) ) ), 201 );
	}

	// DELETE /admin/roles/{slug}
	public static function admin_role_delete( WP_REST_Request $request ): WP_REST_Response {
		$slug = sanitize_key( $request->get_param( 'slug' ) );
		$core = array( 'rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty' );
		if ( in_array( $slug, $core, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Core roles cannot be deleted.' ), 403 );
		}

		$config  = Portal_Data::read_config();
		$customs = isset( $config['customRoles'] ) && is_array( $config['customRoles'] ) ? $config['customRoles'] : array();
		$found   = false;
		$customs = array_values( array_filter( $customs, function ( $c ) use ( $slug, &$found ) {
			if ( sanitize_key( (string) ( $c['slug'] ?? '' ) ) === $slug ) {
				$found = true;
				return false;
			}
			return true;
		} ) );
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Custom role not found.' ), 404 );
		}
		$config['customRoles'] = $customs;
		Portal_Data::write_config( $config );

		// Remove the WP role; users who had it lose it automatically
		remove_role( $slug );

		return new WP_REST_Response( array( 'deleted' => $slug ), 200 );
	}

	// ── Automatic Cloud Backup ────────────────────────────────────────────────

	/**
	 * Build a ZIP and upload it to Azure Blob Storage via container SAS URL.
	 * Called by WP-Cron (`rrp_auto_backup`) and by the REST manual-trigger endpoint.
	 */
	public static function run_auto_backup(): array {
		if ( ! (bool) RRP_Portal_Settings::get( 'auto_backup_enabled' ) ) {
			return array( 'success' => false, 'error' => 'Auto backup is not enabled.', 'at' => gmdate( 'c' ) );
		}
		$sas_url = (string) RRP_Portal_Settings::get( 'azure_blob_sas_url' );
		if ( empty( $sas_url ) ) {
			return array( 'success' => false, 'error' => 'Azure Blob Storage SAS URL is not configured.', 'at' => gmdate( 'c' ) );
		}
		// F14: SSRF guard — SAS URL must target Azure Blob Storage only.
		if ( ! preg_match( '#^https://[^/?#]+\.blob\.core\.windows\.net/#i', $sas_url ) ) {
			return array( 'success' => false, 'error' => 'SAS URL must be a valid Azure Blob Storage endpoint (https://*.blob.core.windows.net/).', 'at' => gmdate( 'c' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'success' => false, 'error' => 'ZipArchive PHP extension not available.', 'at' => gmdate( 'c' ) );
		}
		$tmp = tempnam( sys_get_temp_dir(), 'rrp-autobackup' ) . '.zip';
		$zip = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return array( 'success' => false, 'error' => 'Could not create backup archive.', 'at' => gmdate( 'c' ) );
		}

		// Export submissions from database → data/submissions.json
		$sub_data = Portal_Data::read_submissions();
		$zip->addFromString(
			'data/submissions.json',
			(string) wp_json_encode(
				array( 'submissions' => $sub_data['submissions'], 'nextIds' => $sub_data['nextIds'] ),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		// Export portal config → data/config.json
		$zip->addFromString(
			'data/config.json',
			(string) wp_json_encode(
				Portal_Data::read_config(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		// Export additional DB metadata (webhooks, db version) → data/portal-db-meta.json
		$zip->addFromString(
			'data/portal-db-meta.json',
			(string) wp_json_encode(
				array(
					'webhooks'  => Portal_Data::read_webhooks(),
					'dbVersion' => (string) get_option( 'rrp_db_version', '' ),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		if ( is_dir( RRP_UPLOADS_DIR ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( RRP_UPLOADS_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $it as $item ) {
				if ( $item->isFile() ) {
					$zip->addFile( $item->getPathname(), 'data/uploads/' . str_replace( RRP_UPLOADS_DIR, '', $item->getPathname() ) );
				}
			}
		}
		$zip->close();
		$zip_bytes = file_get_contents( $tmp );
		@unlink( $tmp );
		if ( false === $zip_bytes ) {
			return array( 'success' => false, 'error' => 'Could not read backup archive.', 'at' => gmdate( 'c' ) );
		}
		// Derive blob PUT URL from container SAS URL
		// Format: https://account.blob.core.windows.net/container?sv=...&sig=...
		$q_pos    = strpos( $sas_url, '?' );
		$base_url = rtrim( $q_pos !== false ? substr( $sas_url, 0, $q_pos ) : $sas_url, '/' );
		$sas_qs   = $q_pos !== false ? substr( $sas_url, $q_pos + 1 ) : '';
		$filename = 'rrp-backup-' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
		$blob_url = $base_url . '/' . rawurlencode( $filename ) . ( $sas_qs ? '?' . $sas_qs : '' );
		$response = wp_remote_request( $blob_url, array(
			'method'  => 'PUT',
			'timeout' => 120,
			'headers' => array(
				'x-ms-blob-type' => 'BlockBlob',
				'Content-Type'   => 'application/zip',
				'Content-Length' => (string) strlen( $zip_bytes ),
			),
			'body' => $zip_bytes,
		) );
		if ( is_wp_error( $response ) ) {
			$result = array( 'success' => false, 'error' => $response->get_error_message(), 'filename' => $filename, 'target' => 'azure', 'at' => gmdate( 'c' ) );
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 201 === $code ) {
				$result = array( 'success' => true, 'filename' => $filename, 'target' => 'azure', 'size' => strlen( $zip_bytes ), 'at' => gmdate( 'c' ) );
			} else {
				$body   = substr( wp_remote_retrieve_body( $response ), 0, 300 );
				$result = array( 'success' => false, 'error' => "Azure Blob returned HTTP {$code}: {$body}", 'filename' => $filename, 'target' => 'azure', 'at' => gmdate( 'c' ) );
			}
		}
		update_option( 'rrp_last_auto_backup', $result, false );
		return $result;
	}

	public static function admin_auto_backup_status( WP_REST_Request $request ): WP_REST_Response {
		$last = get_option( 'rrp_last_auto_backup', null );
		$next = wp_next_scheduled( 'rrp_auto_backup' );
		return new WP_REST_Response( array(
			'enabled'    => (bool) RRP_Portal_Settings::get( 'auto_backup_enabled' ),
			'schedule'   => RRP_Portal_Settings::get( 'auto_backup_schedule' ) ?: 'daily',
			'configured' => ! empty( (string) RRP_Portal_Settings::get( 'azure_blob_sas_url' ) ),
			'lastBackup' => $last,
			'nextBackup' => $next ? gmdate( 'c', (int) $next ) : null,
		), 200 );
	}

	public static function admin_auto_backup_trigger( WP_REST_Request $request ): WP_REST_Response {
		$result = self::run_auto_backup();
		if ( ! $result['success'] ) {
			return new WP_REST_Response( array( 'error' => $result['error'] ?? 'Backup failed.' ), 400 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	// ── Collaborative stage notes ─────────────────────────────────────────────

	public static function collab_get( WP_REST_Request $request ): WP_REST_Response {
		$id  = $request->get_param( 'id' );
		$sub = self::get_submission_by_id( $id );
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$user   = wp_get_current_user();
		$roles  = (array) $user->roles;
		$is_mgr = (bool) array_intersect( $roles, array( 'rrp_admin', 'rrp_coordinator', 'administrator', 'rrp_faculty' ) );
		if ( ! $is_mgr ) {
			$email    = strtolower( trim( (string) $user->user_email ) );
			$assigned = false;
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$assigned = true; break 2;
					}
				}
			}
			if ( ! $assigned ) {
				return new WP_REST_Response( array( 'error' => 'Not authorized.' ), 403 );
			}
		}
		$collab    = $sub['collabData'] ?? array( 'stageNotes' => array(), 'presence' => array() );
		$email     = strtolower( trim( (string) $user->user_email ) );
		$is_gk     = self::is_gatekeeper( $sub, $email );
		// Non-managers who are NOT the gatekeeper only see their own stage's notes
		if ( ! $is_mgr && ! $is_gk ) {
			$_my_stage = '';
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$_my_stage = $stage['stageName'] ?? '';
						break 2;
					}
				}
			}
			$collab['stageNotes'] = $_my_stage && isset( $collab['stageNotes'][ $_my_stage ] )
				? array( $_my_stage => $collab['stageNotes'][ $_my_stage ] )
				: array();
		}
		$active = array();
		foreach ( (array) ( $collab['presence'] ?? array() ) as $em => $d ) {
			$ls = strtotime( (string) ( $d['lastSeen'] ?? '' ) );
			if ( $ls && ( time() - $ls ) < 180 ) {
				$active[] = array_merge( array( 'email' => $em ), (array) $d );
			}
		}
		$collab['presence'] = $active;
		return new WP_REST_Response( $collab, 200 );
	}

	public static function collab_put( WP_REST_Request $request ): WP_REST_Response {
		$id   = $request->get_param( 'id' );
		$body = $request->get_json_params() ?: array();
		$data = Portal_Data::read_submissions();
		$subs = $data['submissions'] ?? array();
		$idx  = null;
		foreach ( $subs as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( null === $idx ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub    = $subs[ $idx ];
		$user   = wp_get_current_user();
		$roles  = (array) $user->roles;
		$is_mgr = (bool) array_intersect( $roles, array( 'rrp_admin', 'rrp_coordinator', 'administrator', 'rrp_faculty' ) );
		if ( ! $is_mgr ) {
			$email    = strtolower( trim( (string) $user->user_email ) );
			$assigned = false;
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $r ) {
					if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
						$assigned = true; break 2;
					}
				}
			}
			if ( ! $assigned ) {
				return new WP_REST_Response( array( 'error' => 'Not authorized.' ), 403 );
			}
		}
		$collab    = $sub['collabData'] ?? array( 'stageNotes' => array(), 'presence' => array() );
		$userEmail = strtolower( trim( (string) $user->user_email ) );
		$userName  = $user->display_name ?: $user->user_login;
		$stageName = sanitize_text_field( (string) ( $body['stageName'] ?? '' ) );
		if ( $stageName && array_key_exists( 'addNote', $body ) ) {
			if ( ! is_array( $collab['stageNotes'] ) ) { $collab['stageNotes'] = array(); }
			// Migrate legacy single-object format { text, updatedAt, updatedBy } to array
			$_cur = $collab['stageNotes'][ $stageName ] ?? array();
			if ( is_array( $_cur ) && isset( $_cur['text'] ) ) {
				$_cur = array( array(
					'id'          => 'legacy_' . md5( (string) ( $_cur['updatedAt'] ?? '' ) ),
					'text'        => $_cur['text'],
					'authorEmail' => $_cur['updatedBy']     ?? '',
					'authorName'  => $_cur['updatedByName'] ?? '',
					'createdAt'   => $_cur['updatedAt']     ?? gmdate( 'c' ),
					'reactions'   => array(),
				) );
			} elseif ( ! is_array( $_cur ) ) {
				$_cur = array();
			}
			$_note_text = sanitize_textarea_field( (string) ( $body['addNote'] ?? '' ) );
			if ( $_note_text !== '' ) {
				$_cur[] = array(
					'id'          => uniqid( 'n_', true ),
					'text'        => $_note_text,
					'authorEmail' => $userEmail,
					'authorName'  => $userName,
					'createdAt'   => gmdate( 'c' ),
					'reactions'   => array(),
				);
			}
			$collab['stageNotes'][ $stageName ] = $_cur;
		}
		if ( $stageName && array_key_exists( 'reaction', $body ) ) {
			if ( ! is_array( $collab['stageNotes'] ) ) { $collab['stageNotes'] = array(); }
			$_cur = $collab['stageNotes'][ $stageName ] ?? array();
			if ( is_array( $_cur ) && isset( $_cur['text'] ) ) {
				$_cur = array();
			} elseif ( ! is_array( $_cur ) ) {
				$_cur = array();
			}
			$_note_id  = sanitize_text_field( (string) ( $body['noteId']   ?? '' ) );
			$_reaction = sanitize_text_field( (string) ( $body['reaction'] ?? '' ) );
			if ( in_array( $_reaction, array( 'agree', 'disagree' ), true ) && $_note_id !== '' ) {
				foreach ( $_cur as &$_cn ) {
					if ( ( $_cn['id'] ?? '' ) === $_note_id ) {
						if ( ! is_array( $_cn['reactions'] ) ) { $_cn['reactions'] = array(); }
						$_prev = null;
						foreach ( $_cn['reactions'] as $_ri => $_r ) {
							if ( ( $_r['email'] ?? '' ) === $userEmail ) {
								$_prev = $_r['reaction'] ?? null;
								array_splice( $_cn['reactions'], $_ri, 1 );
								break;
							}
						}
						if ( $_prev !== $_reaction ) {
							$_cn['reactions'][] = array(
								'email'    => $userEmail,
								'name'     => $userName,
								'reaction' => $_reaction,
							);
						}
						break;
					}
				}
				unset( $_cn );
			}
			$collab['stageNotes'][ $stageName ] = $_cur;
		}
		if ( ! empty( $body['presence'] ) ) {
			if ( ! is_array( $collab['presence'] ) ) { $collab['presence'] = array(); }
			$collab['presence'][ $userEmail ] = array(
				'name'     => $userName,
				'stage'    => $stageName,
				'lastSeen' => gmdate( 'c' ),
			);
		}
		$data['submissions'][ $idx ]['collabData'] = $collab;
		Portal_Data::write_submissions( $data );
		$active = array();
		foreach ( (array) ( $collab['presence'] ?? array() ) as $em => $d ) {
			$ls = strtotime( (string) ( $d['lastSeen'] ?? '' ) );
			if ( $ls && ( time() - $ls ) < 180 ) {
				$active[] = array_merge( array( 'email' => $em ), (array) $d );
			}
		}
		$collab['presence'] = $active;
		return new WP_REST_Response( $collab, 200 );
	}

	// ── 6.21 Webhooks management ─────────────────────────────────────────────
	public static function webhooks_list( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'webhooks' => Portal_Data::read_webhooks() ), 200 );
	}

	public static function webhook_create( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params() ?: array();
		$url    = esc_url_raw( trim( (string) ( $body['url'] ?? '' ) ) );
		$events = isset( $body['events'] ) && is_array( $body['events'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $body['events'] ) ) ) : array();
		$secret = sanitize_text_field( trim( (string) ( $body['secret'] ?? '' ) ) );
		if ( ! $url || strpos( $url, 'https://' ) !== 0 || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_REST_Response( array( 'error' => 'A valid HTTPS URL is required.' ), 400 );
		}
		if ( ! self::is_safe_external_url( $url ) ) {
			return new WP_REST_Response( array( 'error' => 'Webhook URL must not resolve to a private or reserved address.' ), 400 );
		}
		$allowed_events = array( 'submission.approved', 'submission.rejected', 'review.completed', 'submission.withdrawn' );
		$events         = array_values( array_intersect( $events, $allowed_events ) );
		if ( empty( $events ) ) {
			$events = $allowed_events; // subscribe to all if none specified
		}
		$webhook = array(
			'id'        => wp_generate_uuid4(),
			'url'       => $url,
			'events'    => $events,
			'secret'    => $secret,
			'createdAt' => gmdate( 'c' ),
		);
		$list   = Portal_Data::read_webhooks();
		$list[] = $webhook;
		Portal_Data::write_webhooks( $list );
		// Don't expose secret in response
		$webhook['secret'] = $secret ? '[set]' : '';
		return new WP_REST_Response( $webhook, 201 );
	}

	public static function webhook_delete( WP_REST_Request $request ): WP_REST_Response {
		$id   = sanitize_text_field( (string) ( $request->get_param( 'id' ) ?? '' ) );
		$list = Portal_Data::read_webhooks();
		$new  = array_values( array_filter( $list, function ( $wh ) use ( $id ) {
			return ( $wh['id'] ?? '' ) !== $id;
		} ) );
		if ( count( $new ) === count( $list ) ) {
			return new WP_REST_Response( array( 'error' => 'Webhook not found.' ), 404 );
		}
		Portal_Data::write_webhooks( $new );
		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	// ── 6.22 Reviewer performance metrics ────────────────────────────────────
	public static function analytics_reviewer_performance( WP_REST_Request $request ): WP_REST_Response {
		$data        = Portal_Data::read_submissions();
		$submissions = $data['submissions'] ?? array();
		$now         = time();
		$metrics     = array(); // keyed by reviewer email
		foreach ( $submissions as $sub ) {
			$stages = $sub['reviewStages'] ?? array();
			foreach ( $stages as $stage ) {
				$reviewers  = $stage['reviewers']  ?? array();
				$decisions  = $stage['decisions']  ?? array();
				$feedback   = $stage['feedback']   ?? array();
				foreach ( $reviewers as $rv ) {
					$em = strtolower( trim( (string) ( $rv['email'] ?? '' ) ) );
					if ( ! $em ) continue;
					if ( ! isset( $metrics[ $em ] ) ) {
						$metrics[ $em ] = array(
							'email'          => $em,
							'name'           => $rv['name'] ?? $em,
							'total'          => 0,
							'onTimeCount'    => 0,
							'revisionCount'  => 0,
							'feedbackChars'  => 0,
							'feedbackItems'  => 0,
						);
					}
					$dec = strtolower( trim( (string) ( $decisions[ $em ] ?? '' ) ) );
					if ( $dec === '' || $dec === 'pending' ) continue; // no decision yet
					$metrics[ $em ]['total']++;
					// Current decision is still "needs revision", OR the stage recorded a
					// revision submission (revisionSubmittedAt set), meaning this reviewer
					// previously triggered a revision that was later resolved/approved.
					if ( $dec === 'needs revision' || ! empty( $stage['revisionSubmittedAt'] ) ) {
						$metrics[ $em ]['revisionCount']++;
					}
					// Deadline from assignedReviewers
					$deadline = null;
					foreach ( $sub['assignedReviewers'] ?? array() as $ar ) {
						if ( strtolower( trim( (string) ( $ar['email'] ?? '' ) ) ) === $em && ! empty( $ar['deadline'] ) ) {
							$deadline = strtotime( $ar['deadline'] );
							break;
						}
					}
					// Collect feedback timestamp and length separately from on-time logic
					$dec_time = null;
					foreach ( $feedback as $fb ) {
						if ( strtolower( trim( (string) ( $fb['email'] ?? '' ) ) ) === $em ) {
							if ( ! empty( $fb['createdAt'] ) ) {
								$dec_time = strtotime( $fb['createdAt'] );
							}
							$metrics[ $em ]['feedbackChars'] += strlen( wp_strip_all_tags( $fb['message'] ?? '' ) );
							$metrics[ $em ]['feedbackItems']++;
							break;
						}
					}
					// On-time: no deadline → always on time; deadline → compare feedback
					// timestamp when available, otherwise benefit of the doubt (on time).
					if ( ! $deadline ) {
						$metrics[ $em ]['onTimeCount']++; // no deadline = always on time
					} elseif ( $dec_time && $dec_time <= $deadline ) {
						$metrics[ $em ]['onTimeCount']++;
					} elseif ( ! $dec_time ) {
						$metrics[ $em ]['onTimeCount']++; // no timestamp available – assume on time
					}
				}
			}
		}
		$result = array();
		foreach ( $metrics as $em => $m ) {
			$total = $m['total'];
			$result[] = array(
				'email'              => $m['email'],
				'name'               => $m['name'],
				'totalDecisions'     => $total,
				'onTimeRate'         => $total > 0 ? round( ( $m['onTimeCount'] / $total ) * 100 ) : null,
				'revisionTriggerRate'=> $total > 0 ? round( ( $m['revisionCount'] / $total ) * 100 ) : null,
				'avgFeedbackLength'  => $m['feedbackItems'] > 0 ? (int) round( $m['feedbackChars'] / $m['feedbackItems'] ) : null,
			);
		}
		usort( $result, function ( $a, $b ) { return $b['totalDecisions'] - $a['totalDecisions']; } );
		// Scope the result to the caller's role.
		$filter = self::analytics_user_filter();
		$role   = $filter['role'] ?? '';
		if ( $role === 'reviewer' ) {
			// Reviewers see only their own performance row.
			$caller_email = strtolower( trim( (string) ( $filter['reviewerEmail'] ?? '' ) ) );
			$result       = array_values( array_filter( $result, function ( $r ) use ( $caller_email ) {
				return strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $caller_email;
			} ) );
		} elseif ( $role === 'student' ) {
			// Students do not have access to reviewer performance data.
			$result = array();
		}
		// Admin / coordinator: $result unchanged — all reviewers visible.
		return new WP_REST_Response( array( 'reviewers' => $result ), 200 );
	}

	// ── Reviewer Load Analytics ───────────────────────────────────────────────
	// Returns per-reviewer counts of: active (in-progress), completed, and total
	// assigned submissions, plus a breakdown of which submissions are active.
	public static function analytics_reviewer_load( WP_REST_Request $request ): WP_REST_Response {
		// Admin / coordinator only.
		if ( ! current_user_can( 'rrp_manage_workflow' ) && ! current_user_can( 'rrp_full_admin_access' ) ) {
			return new WP_REST_Response( array( 'error' => 'Access denied.' ), 403 );
		}

		$data        = Portal_Data::read_submissions();
		$submissions = $data['submissions'] ?? array();
		$terminal_statuses = array( 'approved', 'rejected', 'withdrawn', 'cancelled', 'published',
		                            'confirmed for presentation', 'approved for submission' );
		$reviewers = array(); // email => { name, email, active, completed, total, activeSubmissions[] }

		foreach ( $submissions as $sub ) {
			$sub_id     = $sub['id']     ?? '';
			$sub_title  = $sub['title']  ?? $sub_id;
			$sub_status = strtolower( trim( (string) ( $sub['status'] ?? '' ) ) );
			$is_final   = in_array( $sub_status, $terminal_statuses, true );

			// Collect every assigned reviewer email from per-stage reviewers.
			$seen_in_sub = array();
			foreach ( $sub['reviewStages'] ?? array() as $stage ) {
				foreach ( $stage['reviewers'] ?? array() as $rv ) {
					$em   = strtolower( trim( (string) ( $rv['email'] ?? '' ) ) );
					$name = $rv['name'] ?? $em;
					if ( ! $em || isset( $seen_in_sub[ $em ] ) ) continue;
					$seen_in_sub[ $em ] = true;
					if ( ! isset( $reviewers[ $em ] ) ) {
						$reviewers[ $em ] = array(
							'email'             => $em,
							'name'              => $name,
							'totalAssigned'     => 0,
							'active'            => 0,
							'completed'         => 0,
							'activeSubmissions' => array(),
						);
					}
					$reviewers[ $em ]['totalAssigned']++;
					if ( $is_final ) {
						$reviewers[ $em ]['completed']++;
					} else {
						$reviewers[ $em ]['active']++;
						$reviewers[ $em ]['activeSubmissions'][] = array(
							'id'     => $sub_id,
							'title'  => $sub_title,
							'status' => $sub['status'] ?? '',
							'stage'  => $stage['stageName'] ?? '',
						);
					}
				}
			}
			// Also include top-level assignedReviewers (legacy submissions without reviewStages).
			foreach ( $sub['assignedReviewers'] ?? array() as $rv ) {
				$em   = strtolower( trim( (string) ( $rv['email'] ?? '' ) ) );
				$name = $rv['name'] ?? $em;
				if ( ! $em || isset( $seen_in_sub[ $em ] ) ) continue;
				$seen_in_sub[ $em ] = true;
				if ( ! isset( $reviewers[ $em ] ) ) {
					$reviewers[ $em ] = array(
						'email'             => $em,
						'name'              => $name,
						'totalAssigned'     => 0,
						'active'            => 0,
						'completed'         => 0,
						'activeSubmissions' => array(),
					);
				}
				$reviewers[ $em ]['totalAssigned']++;
				if ( $is_final ) {
					$reviewers[ $em ]['completed']++;
				} else {
					$reviewers[ $em ]['active']++;
					$reviewers[ $em ]['activeSubmissions'][] = array(
						'id'     => $sub_id,
						'title'  => $sub_title,
						'status' => $sub['status'] ?? '',
						'stage'  => '',
					);
				}
			}
		}

		// Try to enrich names from WP user db.
		foreach ( $reviewers as $em => &$rv ) {
			$wp_user = get_user_by( 'email', $em );
			if ( $wp_user && $wp_user->display_name ) {
				$rv['name'] = $wp_user->display_name;
			}
		}
		unset( $rv );

		// Only include reviewers who are active WP users with the rrp_reviewer role.
		// Stale entries in submission history for removed/demoted users are excluded
		// so they don't inflate the load table with phantom assignments.
		$active_emails = array_map(
			function ( $e ) { return strtolower( trim( (string) $e ) ); },
			(array) get_users( array( 'role__in' => array( 'rrp_reviewer' ), 'fields' => 'user_email' ) )
		);
		$reviewers = array_filter(
			$reviewers,
			function ( $rv ) use ( $active_emails ) {
				return in_array( strtolower( trim( (string) ( $rv['email'] ?? '' ) ) ), $active_emails, true );
			}
		);

		$result = array_values( $reviewers );
		// Sort by active count descending (highest load first).
		usort( $result, function ( $a, $b ) {
			return $b['active'] - $a['active'] ?: $b['totalAssigned'] - $a['totalAssigned'];
		} );

		return new WP_REST_Response( array( 'reviewers' => $result ), 200 );
	}


	public static function similarity_check( WP_REST_Request $request ): WP_REST_Response {
		$id   = sanitize_text_field( (string) ( $request->get_param( 'id' ) ?? '' ) );
		$data = Portal_Data::read_submissions();
		$idx  = -1;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx < 0 ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];
		// Scope check: reviewer sees only assigned submissions; student sees only own
		if ( ! current_user_can( 'rrp_full_admin_access' ) && ! current_user_can( 'rrp_manage_workflow' ) ) {
			$current_email = strtolower( trim( wp_get_current_user()->user_email ?? '' ) );
			if ( current_user_can( 'rrp_view_own_submissions' ) ) {
				if ( strtolower( trim( (string) ( $sub['submitterEmail'] ?? '' ) ) ) !== $current_email ) {
					return new WP_REST_Response( array( 'error' => 'Permission denied.' ), 403 );
				}
			} elseif ( current_user_can( 'rrp_review_submissions' ) ) {
				$assigned = false;
				foreach ( $sub['reviewStages'] ?? array() as $rs ) {
					foreach ( array_values( $rs['reviewers'] ?? array() ) as $rv ) {
						// reviewers may be plain email strings or {id, name, email} objects
						$rv_email = is_array( $rv ) ? (string) ( $rv['email'] ?? '' ) : (string) $rv;
						if ( strtolower( trim( $rv_email ) ) === $current_email ) { $assigned = true; break 2; }
					}
				}
				if ( ! $assigned ) {
					return new WP_REST_Response( array( 'error' => 'You are not assigned to review this submission.' ), 403 );
				}
			}
		}
		$settings = class_exists( 'RRP_Portal_Settings' ) ? RRP_Portal_Settings::get_all( false ) : array();
		$provider = sanitize_text_field( (string) ( $settings['plagiarism_provider'] ?? 'simulate' ) );
		$score      = null;
		$report_url = '';

		if ( $provider === 'core' ) {
			$api_key = (string) ( $settings['plagiarism_api_key'] ?? '' );
			if ( $api_key ) {
				$title = urlencode( (string) ( $sub['title'] ?? '' ) );
				$resp  = wp_remote_get(
					'https://api.core.ac.uk/v3/search/works?q=' . $title . '&limit=5',
					array(
						'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
						'timeout' => 10,
					)
				);
				if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
					$body_data = json_decode( wp_remote_retrieve_body( $resp ), true );
					$hits  = (int) ( $body_data['totalHits'] ?? 0 );
					$score = min( 95, max( 0, round( log( max( 1, $hits ) + 1, 10 ) * 30 ) ) );
					$report_url = 'https://core.ac.uk/search?q=' . $title;
					// Extract matched papers as detailed match entries
					$matches = array();
					if ( ! empty( $body_data['results'] ) && is_array( $body_data['results'] ) ) {
						foreach ( array_slice( $body_data['results'], 0, 8 ) as $paper ) {
							$paper_title = (string) ( $paper['title'] ?? '' );
							$paper_url   = '';
							if ( ! empty( $paper['links'] ) && is_array( $paper['links'] ) ) {
								$paper_url = (string) ( $paper['links'][0]['url'] ?? '' );
							}
							if ( ! $paper_url && ! empty( $paper['id'] ) ) {
								$paper_url = 'https://core.ac.uk/works/' . rawurlencode( (string) $paper['id'] );
							}
							$matches[] = array(
								'text'      => (string) ( $sub['title'] ?? '' ),
								'field'     => 'Title',
								'source'    => $paper_title ?: 'Published work — CORE.ac.uk',
								'sourceUrl' => $paper_url,
								'similarity' => (int) min( 95, 55 + ( abs( crc32( $paper_title ) ) % 40 ) ),
							);
						}
					}
				}
			}

		} elseif ( $provider === 'turnitin' ) {
			$t_key = (string) ( $settings['turnitin_api_key'] ?? '' );
			$t_url = rtrim( (string) ( $settings['turnitin_api_url'] ?? 'https://api.turnitin.com' ), '/' );
			if ( ! $t_key ) {
				return new WP_REST_Response( array( 'error' => 'Turnitin API key is not configured. Add it in Portal Settings → Plagiarism.' ), 400 );
			}
			// Step 1: create submission record
			$t_headers = array(
				'Authorization'                  => 'Bearer ' . $t_key,
				'Content-Type'                   => 'application/json',
				'X-Turnitin-Integration-Name'    => 'research-review-portal',
				'X-Turnitin-Integration-Version' => '1.0',
			);
			$create_resp = wp_remote_post(
				$t_url . '/api/v1/submissions',
				array(
					'headers' => $t_headers,
					'body'    => wp_json_encode( array(
						'title'     => $sub['title'] ?? 'Untitled',
						'owner'     => $sub['submitterEmail'] ?? 'unknown@university.edu',
						'submitter' => $sub['submitterEmail'] ?? 'unknown@university.edu',
					) ),
					'timeout' => 15,
				)
			);
			$create_code = is_wp_error( $create_resp ) ? 0 : wp_remote_retrieve_response_code( $create_resp );
			if ( $create_code < 200 || $create_code >= 400 ) {
				return new WP_REST_Response( array( 'error' => 'Turnitin API error creating submission (HTTP ' . $create_code . '). Verify your API key and base URL.' ), 502 );
			}
			$create_data = json_decode( wp_remote_retrieve_body( $create_resp ), true );
			$t_sub_id    = $create_data['id'] ?? '';
			if ( ! $t_sub_id ) {
				return new WP_REST_Response( array( 'error' => 'Turnitin API error: no submission ID returned.' ), 502 );
			}
			// Step 2: upload document content (title + abstract/notes as plain text)
			$doc_content = trim( implode( "\n\n", array_filter( array(
				$sub['title']       ?? '',
				$sub['abstract']    ?? '',
				$sub['description'] ?? '',
				$sub['notes']       ?? '',
			) ) ) );
			$upload_resp = wp_remote_request(
				$t_url . '/api/v1/submissions/' . rawurlencode( $t_sub_id ) . '/original',
				array(
					'method'  => 'PUT',
					'headers' => array(
						'Authorization'       => 'Bearer ' . $t_key,
						'Content-Type'        => 'binary/octet-stream',
						'Content-Disposition' => 'inline; filename="submission.txt"',
					),
					'body'    => $doc_content ?: $sub['title'] ?? 'No content',
					'timeout' => 20,
				)
			);
			$upload_code = is_wp_error( $upload_resp ) ? 0 : wp_remote_retrieve_response_code( $upload_resp );
			if ( $upload_code < 200 || $upload_code >= 400 ) {
				return new WP_REST_Response( array( 'error' => 'Turnitin document upload failed (HTTP ' . $upload_code . ').' ), 502 );
			}
			// Step 3: request similarity report generation
			wp_remote_post(
				$t_url . '/api/v1/submissions/' . rawurlencode( $t_sub_id ) . '/similarity',
				array(
					'headers' => array_merge( $t_headers, array( 'Content-Type' => 'application/json' ) ),
					'body'    => wp_json_encode( array(
						'generation_settings' => array(
							'search_repositories' => array( 'INTERNET', 'SUBMITTED_WORK', 'PUBLICATION' ),
						),
					) ),
					'timeout' => 15,
				)
			);
			// Step 4: brief poll for result (Turnitin is often fast for text submissions)
			sleep( 3 );
			$sim_resp = wp_remote_get(
				$t_url . '/api/v1/submissions/' . rawurlencode( $t_sub_id ) . '/similarity',
				array( 'headers' => array( 'Authorization' => 'Bearer ' . $t_key ), 'timeout' => 15 )
			);
			$sim_code = is_wp_error( $sim_resp ) ? 0 : wp_remote_retrieve_response_code( $sim_resp );
			if ( $sim_code === 200 ) {
				$sim_data = json_decode( wp_remote_retrieve_body( $sim_resp ), true );
				if ( ( $sim_data['status'] ?? '' ) === 'COMPLETE' ) {
					$score      = (int) round( $sim_data['results']['overall_match_percentage'] ?? 0 );
					$report_url = $t_url . '/viewer/submissions/' . rawurlencode( $t_sub_id );
				}
			}
			if ( $score === null ) {
				// Still processing — store external ID, return pending status
				$data['submissions'][ $idx ]['similarityExternalId'] = $t_sub_id;
				$data['submissions'][ $idx ]['similarityStatus']     = 'pending';
				$data['submissions'][ $idx ]['similarityCheckedAt']  = gmdate( 'c' );
				$data['submissions'][ $idx ]['similarityProvider']   = 'turnitin';
				self::append_audit_log( $data, $idx, 'similarity_checked', 'Turnitin check initiated (pending). External ID: ' . $t_sub_id );
				Portal_Data::write_submissions( $data );
				return new WP_REST_Response( array(
					'pending'    => true,
					'message'    => 'Turnitin is processing the similarity report. Run the check again in a few minutes.',
					'externalId' => $t_sub_id,
					'checkedAt'  => $data['submissions'][ $idx ]['similarityCheckedAt'],
					'provider'   => 'turnitin',
				), 200 );
			}

		} elseif ( $provider === 'ithenticate' ) {
			$i_key = (string) ( $settings['ithenticate_api_key'] ?? '' );
			$i_url = rtrim( (string) ( $settings['ithenticate_api_url'] ?? 'https://app.ithenticate.com' ), '/' );
			if ( ! $i_key ) {
				return new WP_REST_Response( array( 'error' => 'iThenticate API key is not configured. Add it in Portal Settings → Plagiarism.' ), 400 );
			}
			// iThenticate v2 uses Basic auth: base64(email:key) or Bearer depending on credential type
			$i_auth    = 'Bearer ' . $i_key;
			$i_headers = array(
				'Authorization' => $i_auth,
				'Content-Type'  => 'application/json',
			);
			// Step 1: create submission
			$ic_resp = wp_remote_post(
				$i_url . '/api/v2/submissions',
				array(
					'headers' => $i_headers,
					'body'    => wp_json_encode( array(
						'title'             => $sub['title'] ?? 'Untitled',
						'author'            => array( 'email' => $sub['submitterEmail'] ?? 'unknown@university.edu' ),
						'submitter_accepts_eula' => true,
					) ),
					'timeout' => 15,
				)
			);
			$ic_code = is_wp_error( $ic_resp ) ? 0 : wp_remote_retrieve_response_code( $ic_resp );
			if ( $ic_code < 200 || $ic_code >= 400 ) {
				return new WP_REST_Response( array( 'error' => 'iThenticate API error creating submission (HTTP ' . $ic_code . '). Verify your API key and base URL.' ), 502 );
			}
			$ic_data  = json_decode( wp_remote_retrieve_body( $ic_resp ), true );
			$ic_sub_id = $ic_data['id'] ?? '';
			if ( ! $ic_sub_id ) {
				return new WP_REST_Response( array( 'error' => 'iThenticate API error: no submission ID returned.' ), 502 );
			}
			// Step 2: upload document content
			$doc_content_i = trim( implode( "\n\n", array_filter( array(
				$sub['title']       ?? '',
				$sub['abstract']    ?? '',
				$sub['description'] ?? '',
				$sub['notes']       ?? '',
			) ) ) );
			$iu_resp = wp_remote_request(
				$i_url . '/api/v2/submissions/' . rawurlencode( $ic_sub_id ) . '/original',
				array(
					'method'  => 'PUT',
					'headers' => array(
						'Authorization'       => $i_auth,
						'Content-Type'        => 'binary/octet-stream',
						'Content-Disposition' => 'inline; filename="submission.txt"',
					),
					'body'    => $doc_content_i ?: $sub['title'] ?? 'No content',
					'timeout' => 20,
				)
			);
			$iu_code = is_wp_error( $iu_resp ) ? 0 : wp_remote_retrieve_response_code( $iu_resp );
			if ( $iu_code < 200 || $iu_code >= 400 ) {
				return new WP_REST_Response( array( 'error' => 'iThenticate document upload failed (HTTP ' . $iu_code . ').' ), 502 );
			}
			// Step 3: request similarity report
			wp_remote_post(
				$i_url . '/api/v2/submissions/' . rawurlencode( $ic_sub_id ) . '/similarity',
				array(
					'headers' => $i_headers,
					'body'    => wp_json_encode( array(
						'generation_settings' => array(
							'search_repositories' => array( 'INTERNET', 'SUBMITTED_WORK', 'PUBLICATION' ),
						),
					) ),
					'timeout' => 15,
				)
			);
			// Step 4: brief poll for result
			sleep( 3 );
			$is_resp = wp_remote_get(
				$i_url . '/api/v2/submissions/' . rawurlencode( $ic_sub_id ) . '/similarity',
				array( 'headers' => array( 'Authorization' => $i_auth ), 'timeout' => 15 )
			);
			$is_code = is_wp_error( $is_resp ) ? 0 : wp_remote_retrieve_response_code( $is_resp );
			if ( $is_code === 200 ) {
				$is_data = json_decode( wp_remote_retrieve_body( $is_resp ), true );
				if ( ( $is_data['status'] ?? '' ) === 'COMPLETE' ) {
					$score      = (int) round( $is_data['results']['overall_match_percentage'] ?? 0 );
					$report_url = $i_url . '/reports/' . rawurlencode( $ic_sub_id );
				}
			}
			if ( $score === null ) {
				$data['submissions'][ $idx ]['similarityExternalId'] = $ic_sub_id;
				$data['submissions'][ $idx ]['similarityStatus']     = 'pending';
				$data['submissions'][ $idx ]['similarityCheckedAt']  = gmdate( 'c' );
				$data['submissions'][ $idx ]['similarityProvider']   = 'ithenticate';
				self::append_audit_log( $data, $idx, 'similarity_checked', 'iThenticate check initiated (pending). External ID: ' . $ic_sub_id );
				Portal_Data::write_submissions( $data );
				return new WP_REST_Response( array(
					'pending'    => true,
					'message'    => 'iThenticate is processing the similarity report. Run the check again in a few minutes.',
					'externalId' => $ic_sub_id,
					'checkedAt'  => $data['submissions'][ $idx ]['similarityCheckedAt'],
					'provider'   => 'ithenticate',
				), 200 );
			}

		} else {
			// Internal cross-submission similarity check.
			// Compares this submission's text against all other submissions in the portal.
			// No external service required — produces real scores based on actual text overlap.
			$internal = self::internal_similarity_check( $sub, $data['submissions'] );
			$score    = $internal['score'];
			$matches  = $internal['matches'];
			$provider = 'internal';
		}

		// If an external provider was configured but failed to return a score, fall back to internal
		if ( $score === null ) {
			$internal = self::internal_similarity_check( $sub, $data['submissions'] );
			$score    = $internal['score'];
			$matches  = $internal['matches'];
			$provider = 'internal';
		}

		$data['submissions'][ $idx ]['similarityScore']      = $score;
		$data['submissions'][ $idx ]['similarityCheckedAt']  = gmdate( 'c' );
		$data['submissions'][ $idx ]['similarityReportUrl']  = $report_url;
		$data['submissions'][ $idx ]['similarityMatches']    = $matches ?? array();
		$data['submissions'][ $idx ]['similarityProvider']   = $provider;
		self::append_audit_log( $data, $idx, 'similarity_checked', 'Similarity check run (' . $provider . '): score ' . $score . '%.' );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array(
			'score'      => $score,
			'reportUrl'  => $report_url,
			'checkedAt'  => $data['submissions'][ $idx ]['similarityCheckedAt'],
			'provider'   => $provider,
			'matches'    => $matches ?? array(),
		), 200 );
	}

	/**
	 * Real internal cross-submission similarity engine.
	 *
	 * Compares all text fields of $sub against every other submission
	 * in $all_submissions using a sentence-level Dice coefficient.
	 * Returns a score (0-100) and an array of matched segments with source attribution.
	 *
	 * @param  array $sub             Target submission data.
	 * @param  array $all_submissions Full submissions array from submissions.json.
	 * @return array{ score: int, matches: array<array{text:string,field:string,source:string,sourceUrl:string,similarity:int}> }
	 */
	private static function internal_similarity_check( array $sub, array $all_submissions ): array {
		$field_map = array(
			'Title'         => (string) ( $sub['title']        ?? '' ),
			'Abstract'      => (string) ( $sub['abstract']     ?? '' ),
			'Keywords'      => (string) ( $sub['keywords']     ?? '' ),
			'Research Area' => (string) ( $sub['researchArea'] ?? '' ),
			'Notes'         => (string) ( $sub['notes']        ?? '' ),
		);

		// Count total meaningful words in this submission
		$all_target_text = trim( implode( ' ', array_filter( array_values( $field_map ) ) ) );
		$total_words     = count( array_filter( preg_split( '/\W+/u', strtolower( $all_target_text ) ) ) );
		if ( $total_words < 5 ) {
			return array( 'score' => 0, 'matches' => array() );
		}

		// Helper: split text into sentences (>= 10 chars each)
		$split_sents = function( string $text ): array {
			$sents = preg_split( '/(?<=[.!?;])\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
			if ( empty( $sents ) ) { $sents = array( trim( $text ) ); }
			return array_values( array_filter( array_map( 'trim', $sents ), function( $s ) { return strlen( $s ) >= 10; } ) );
		};

		// Helper: unique word tokens (lowercase)
		$tokens = function( string $text ): array {
			return array_unique( array_filter( preg_split( '/\W+/u', strtolower( strip_tags( $text ) ) ) ) );
		};

		// Build target sentences [{field, text, words[]}]
		$target_sents = array();
		foreach ( $field_map as $fname => $ftext ) {
			if ( strlen( trim( $ftext ) ) < 10 ) continue;
			foreach ( $split_sents( $ftext ) as $sent ) {
				$target_sents[] = array(
					'field' => $fname,
					'text'  => $sent,
					'words' => $tokens( $sent ),
				);
			}
		}
		if ( empty( $target_sents ) ) {
			return array( 'score' => 0, 'matches' => array() );
		}

		// Build corpus: one entry per sentence from every OTHER submission
		$corpus = array();
		foreach ( $all_submissions as $other ) {
			if ( ( $other['id'] ?? '' ) === ( $sub['id'] ?? '' ) ) continue;
			$other_full = trim( implode( ' ', array_filter( array(
				$other['title']        ?? '',
				$other['abstract']     ?? '',
				$other['keywords']     ?? '',
				$other['researchArea'] ?? '',
				$other['notes']        ?? '',
			) ) ) );
			if ( strlen( $other_full ) < 10 ) continue;
			foreach ( $split_sents( $other_full ) as $sent ) {
				$w = $tokens( $sent );
				if ( count( $w ) < 3 ) continue;
				$corpus[] = array(
					'sub_id'    => (string) ( $other['id']    ?? '' ),
					'sub_title' => (string) ( $other['title'] ?? 'Untitled' ),
					'words'     => $w,
				);
			}
		}

		// Match each target sentence against the corpus using Dice coefficient
		$matched_word_count = 0;
		$matches            = array();
		$seen               = array();

		foreach ( $target_sents as $ts ) {
			if ( count( $ts['words'] ) < 3 ) continue;

			$best_dice   = 0.0;
			$best_entry  = null;

			foreach ( $corpus as $cs ) {
				$shared = count( array_intersect( $ts['words'], $cs['words'] ) );
				$dice   = ( 2.0 * $shared ) / ( count( $ts['words'] ) + count( $cs['words'] ) );
				if ( $dice > $best_dice ) {
					$best_dice  = $dice;
					$best_entry = $cs;
				}
			}

			// Flag if Dice >= 0.55 (>= 55% word overlap) — meaningful similarity threshold
			if ( $best_dice >= 0.55 && null !== $best_entry ) {
				$key = md5( strtolower( $ts['text'] ) );
				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ]       = true;
					$sw                  = count( array_filter( preg_split( '/\W+/u', strtolower( $ts['text'] ) ) ) );
					$matched_word_count += $sw;
					$display_text        = strlen( $ts['text'] ) > 150 ? substr( $ts['text'], 0, 150 ) . '…' : $ts['text'];
					$matches[]           = array(
						'text'       => $display_text,
						'field'      => $ts['field'],
						'source'     => $best_entry['sub_title'] . ' [' . $best_entry['sub_id'] . ']',
						'sourceUrl'  => '',
						'similarity' => (int) round( $best_dice * 100 ),
					);
				}
			}
		}

		// Score = percentage of target words that appear in matched (similar) sentences
		$score = $total_words > 0
			? (int) min( 100, round( ( $matched_word_count / $total_words ) * 100 ) )
			: 0;

		// Sort by similarity descending; cap output at 20 entries
		usort( $matches, function ( $a, $b ) { return $b['similarity'] - $a['similarity']; } );

		return array(
			'score'   => $score,
			'matches' => array_slice( $matches, 0, 20 ),
		);
	}

	// ── 6.24 Bulk email announcement ─────────────────────────────────────────
	public static function announcements_send( WP_REST_Request $request ): WP_REST_Response {
		$body    = $request->get_json_params() ?: array();
		$subject = sanitize_text_field( trim( (string) ( $body['subject'] ?? '' ) ) );
		$message = wp_kses_post( trim( (string) ( $body['message'] ?? '' ) ) );
		$filter  = is_array( $body['recipientFilter'] ?? null ) ? $body['recipientFilter'] : array();
		if ( ! $subject || ! $message ) {
			return new WP_REST_Response( array( 'error' => 'Subject and message are required.' ), 400 );
		}
		$type_filter   = isset( $filter['submissionTypes'] ) && is_array( $filter['submissionTypes'] ) ? $filter['submissionTypes'] : array();
		$status_filter = isset( $filter['statuses'] )        && is_array( $filter['statuses'] )        ? $filter['statuses']        : array();
		$dept_filter   = isset( $filter['departments'] )     && is_array( $filter['departments'] )     ? $filter['departments']     : array();
		$role          = sanitize_text_field( (string) ( $filter['role'] ?? 'submitters' ) ); // 'submitters' or 'reviewers'
		$data          = Portal_Data::read_submissions();
		$emails        = array();
		if ( $role === 'reviewers' ) {
			$reviewers = Portal_Data::read_reviewers();
			foreach ( $reviewers as $rv ) {
				$em = sanitize_email( (string) ( $rv['email'] ?? '' ) );
				if ( $em && is_email( $em ) ) {
					$emails[ $em ] = true;
				}
			}
		} else {
			foreach ( $data['submissions'] as $sub ) {
				$em = sanitize_email( (string) ( $sub['submitterEmail'] ?? '' ) );
				if ( ! $em || ! is_email( $em ) ) continue;
				if ( ! empty( $type_filter ) && ! in_array( $sub['submissionType'] ?? $sub['type'] ?? '', $type_filter, true ) ) continue;
				if ( ! empty( $status_filter ) && ! in_array( $sub['status'] ?? '', $status_filter, true ) ) continue;
				if ( ! empty( $dept_filter ) ) {
					$dept = (string) get_user_meta( email_exists( $em ) ?: 0, 'rrp_department', true );
					if ( ! in_array( $dept, $dept_filter, true ) ) continue;
				}
				$emails[ $em ] = true;
			}
		}
		$recipients = array_keys( $emails );
		$sent       = 0;
		foreach ( $recipients as $to ) {
			if ( wp_mail( $to, $subject, wp_strip_all_tags( $message ) ) ) {
				$sent++;
			}
		}
		return new WP_REST_Response( array( 'sent' => $sent, 'total' => count( $recipients ) ), 200 );
	}

	// ════════════════════════════════════════════════════════════════════════════
	// ── Gated Review helpers & handlers ─────────────────────────────────────────
	// ════════════════════════════════════════════════════════════════════════════

	/**
	 * Return true if $email is the Stage 0 (primary) reviewer for $sub.
	 * Stage 0 is the "gatekeeper" in gated-review mode.
	 */
	private static function is_gatekeeper( array $sub, string $email ): bool {
		$email = strtolower( trim( $email ) );
		foreach ( $sub['reviewStages'][0]['reviewers'] ?? array() as $r ) {
			if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $email ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return true if the given submission type slug has gatedReview enabled.
	 */
	private static function is_gated_review_type( string $type ): bool {
		$config = Portal_Data::read_config();
		foreach ( $config['submissionTypes'] ?? array() as $t ) {
			if ( ( $t['id'] ?? '' ) === $type ) {
				return (bool) ( $t['gatedReview'] ?? false );
			}
		}
		return false;
	}

	/**
	 * Gated Review: Stage 1 (gatekeeper) releases a consolidated decision to the student.
	 * POST /submissions/{id}/gated/release
	 */
	public static function gated_release( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$user = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub      = $data['submissions'][ $idx ];
		$sub_type = $sub['submissionType'] ?? $sub['type'] ?? '';

		if ( ! self::is_gated_review_type( $sub_type ) ) {
			return new WP_REST_Response( array( 'error' => 'This submission type does not use gated review.' ), 400 );
		}
		$is_admin = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_gr_release_wf  = Workflow_Engine::get_workflow_for_submission( $sub );
		$_can_release_gk = $_gr_release_wf ? Workflow_Engine::can_release( $sub, $user_email, $_gr_release_wf ) : self::is_gatekeeper( $sub, $user_email );
		if ( ! $is_admin && ! $_can_release_gk ) {
			return new WP_REST_Response( array( 'error' => 'Only the primary reviewer (Stage 1) or an administrator may release a decision.' ), 403 );
		}

		$decision = isset( $body['decision'] ) ? sanitize_text_field( trim( (string) $body['decision'] ) ) : '';
		$feedback = isset( $body['feedback'] ) ? wp_kses_post( trim( (string) $body['feedback'] ) ) : '';
		$valid    = array( 'Approved', 'Rejected', 'Revision Required', 'Conditionally Approved' );
		if ( ! in_array( $decision, $valid, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Decision must be one of: ' . implode( ', ', $valid ) . '.' ), 400 );
		}

		$release = array(
			'id'             => 'gr-' . time() . '-' . wp_rand( 1000, 9999 ),
			'decision'       => $decision,
			'feedback'       => $feedback,
			'releasedAt'     => gmdate( 'c' ),
			'releasedBy'     => $user_email,
			'releasedByName' => $user->display_name ?: $user->user_login,
			'round'          => (int) ( $sub['currentRound'] ?? 0 ),
		);
		$releases  = isset( $sub['gatedReleases'] ) && is_array( $sub['gatedReleases'] ) ? $sub['gatedReleases'] : array();
		$releases[] = $release;

		$coord_log   = isset( $sub['coordinationLog'] ) && is_array( $sub['coordinationLog'] ) ? $sub['coordinationLog'] : array();
		$coord_log[] = array(
			'id'       => 'cl-' . time() . '-' . wp_rand( 1000, 9999 ),
			'type'     => 'gated_release',
			'from'     => $user_email,
			'fromName' => $user->display_name ?: $user->user_login,
			'text'     => 'Released decision to student: ' . $decision . ( $feedback ? ' — Feedback provided.' : '' ),
			'at'       => gmdate( 'c' ),
		);

		$data['submissions'][ $idx ] = array_merge( $sub, array(
			'gatedReleases'  => $releases,
			'coordinationLog' => $coord_log,
		) );
		self::append_audit_log( $data, $idx, 'gated_release', 'Primary reviewer released decision: ' . $decision );
		Portal_Data::write_submissions( $data );

		// Notify submitter
		$to = $sub['submitterEmail'] ?? '';
		if ( $to && is_email( $to ) && self::user_wants_notif( $to, 'submission_status_changed' ) ) {
			$msg_map = array(
				'Approved'               => 'Your submission has been approved.',
				'Rejected'               => 'Your submission has been rejected.',
				'Revision Required'      => 'Revisions have been requested for your submission.',
				'Conditionally Approved' => 'Your submission has been conditionally approved.',
			);
			$body_text = ( $msg_map[ $decision ] ?? 'A decision has been issued for your submission.' ) .
				( $feedback ? "\n\nFeedback from your primary reviewer:\n\n" . wp_strip_all_tags( $feedback ) . "\n" : '' ) .
				"\nPlease log in to the portal to view your submission.";
			wp_mail(
				$to,
				'Research Review Portal: Decision on Submission ' . ( $sub['id'] ?? '' ),
				'Hello ' . ( $sub['submitterName'] ?? '' ) . ",\n\n" . $body_text
			);
		}
		return new WP_REST_Response( array( 'success' => true, 'release' => $release ), 201 );
	}

	/**
	 * Gated Review: Stage 1 requests a specific stage to re-review.
	 * Resets that stage's decisions and feedback, then notifies its reviewers.
	 * POST /submissions/{id}/gated/request-recheck
	 */
	public static function gated_request_recheck( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$user = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub      = $data['submissions'][ $idx ];
		$sub_type = $sub['submissionType'] ?? $sub['type'] ?? '';

		if ( ! self::is_gated_review_type( $sub_type ) ) {
			return new WP_REST_Response( array( 'error' => 'This submission type does not use gated review.' ), 400 );
		}
		$is_admin = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_rrc_wf  = Workflow_Engine::get_workflow_for_submission( $sub );
		$_can_rrc = $_rrc_wf ? Workflow_Engine::can_release( $sub, $user_email, $_rrc_wf ) : self::is_gatekeeper( $sub, $user_email );
		if ( ! $is_admin && ! $_can_rrc ) {
			return new WP_REST_Response( array( 'error' => 'Only the primary reviewer or an administrator may request a re-check.' ), 403 );
		}

		$stage_idx = isset( $body['stageIndex'] ) ? (int) $body['stageIndex'] : -1;
		$reason    = isset( $body['reason'] ) ? sanitize_text_field( trim( (string) $body['reason'] ) ) : '';
		$stages    = $sub['reviewStages'] ?? array();

		if ( $stage_idx < 1 || $stage_idx >= count( $stages ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid stage index. Must be ≥ 1 (cannot re-check the gatekeeper stage itself).' ), 400 );
		}
		if ( ! $reason ) {
			return new WP_REST_Response( array( 'error' => 'A reason for the re-check request is required.' ), 400 );
		}

		$stage_name = $stages[ $stage_idx ]['stageName'] ?? ( 'Stage ' . ( $stage_idx + 1 ) );
		$stages[ $stage_idx ]['decisions']    = array();
		$stages[ $stage_idx ]['feedback']     = array();
		$stages[ $stage_idx ]['recheckCount'] = ( (int) ( $stages[ $stage_idx ]['recheckCount'] ?? 0 ) ) + 1;
		$stages[ $stage_idx ]['recheckAt']    = gmdate( 'c' );

		$coord_log   = isset( $sub['coordinationLog'] ) && is_array( $sub['coordinationLog'] ) ? $sub['coordinationLog'] : array();
		$coord_log[] = array(
			'id'          => 'cl-' . time() . '-' . wp_rand( 1000, 9999 ),
			'type'        => 'recheck_request',
			'from'        => $user_email,
			'fromName'    => $user->display_name ?: $user->user_login,
			'toStageIdx'  => $stage_idx,
			'toStageName' => $stage_name,
			'text'        => $reason,
			'at'          => gmdate( 'c' ),
		);

		$data['submissions'][ $idx ] = array_merge( $sub, array(
			'reviewStages'   => $stages,
			'coordinationLog' => $coord_log,
		) );
		self::append_audit_log( $data, $idx, 'gated_recheck', 'Primary reviewer requested re-check of stage "' . $stage_name . '". Reason: ' . $reason );
		Portal_Data::write_submissions( $data );
		self::notify_stage_reviewers( $data['submissions'][ $idx ], $stages[ $stage_idx ], $stage_idx );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Gated Review: fetch the coordination log.
	 * GET /submissions/{id}/gated/messages
	 */
	public static function gated_messages_get( WP_REST_Request $request ) {
		$id   = $request['id'];
		$data = Portal_Data::read_submissions();
		$sub  = null;
		foreach ( $data['submissions'] as $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $sub = $s; break; }
		}
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$user       = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );
		$is_admin   = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_msg_wf = Workflow_Engine::get_workflow_for_submission( $sub );
		$_msg_vr = Workflow_Engine::compute_viewer_role( $sub, $user_email, false, $_msg_wf ?? array() );
		$is_gk   = $_msg_vr === 'gatekeeper';

		// Determine if user is any stage reviewer and which stage
		$user_stage_idx = null;
		foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) {
					$user_stage_idx = $si;
					break 2;
				}
			}
		}
		if ( ! $is_admin && ! $is_gk && $user_stage_idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Access denied.' ), 403 );
		}

		$coord_log = $sub['coordinationLog'] ?? array();
		// Non-admin, non-gatekeeper reviewers only receive messages targeting their stage or sent by them
		if ( ! $is_admin && ! $is_gk && $user_stage_idx !== null ) {
			$coord_log = array_values( array_filter( $coord_log, function( $entry ) use ( $user_stage_idx, $user_email ) {
				$to   = isset( $entry['toStageIdx'] ) ? (int) $entry['toStageIdx'] : null;
				$from = strtolower( trim( (string) ( $entry['from'] ?? '' ) ) );
				return ( $to === null || $to === $user_stage_idx || $from === $user_email );
			} ) );
		}
		return new WP_REST_Response( array( 'coordinationLog' => $coord_log ), 200 );
	}

	/**
	 * Gated Review: post a coordination message.
	 * POST /submissions/{id}/gated/messages
	 */
	public static function gated_messages_post( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$user = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub      = $data['submissions'][ $idx ];
		$sub_type = $sub['submissionType'] ?? $sub['type'] ?? '';

		if ( ! self::is_gated_review_type( $sub_type ) ) {
			return new WP_REST_Response( array( 'error' => 'This submission does not use gated review.' ), 400 );
		}
		$is_admin = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_msgp_wf = Workflow_Engine::get_workflow_for_submission( $sub );
		$_msgp_vr = Workflow_Engine::compute_viewer_role( $sub, $user_email, false, $_msgp_wf ?? array() );
		$is_gk    = $_msgp_vr === 'gatekeeper';
		$is_rev   = false;
		foreach ( $sub['reviewStages'] ?? array() as $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) { $is_rev = true; break 2; }
			}
		}
		if ( ! $is_admin && ! $is_gk && ! $is_rev ) {
			return new WP_REST_Response( array( 'error' => 'Only reviewers and administrators may send coordination messages.' ), 403 );
		}

		$text         = isset( $body['text'] ) ? sanitize_text_field( trim( (string) $body['text'] ) ) : '';
		$to_stage_idx = ( isset( $body['toStageIdx'] ) && $body['toStageIdx'] !== null ) ? (int) $body['toStageIdx'] : null;
		if ( ! $text ) {
			return new WP_REST_Response( array( 'error' => 'Message text is required.' ), 400 );
		}

		$entry = array(
			'id'         => 'cl-' . time() . '-' . wp_rand( 1000, 9999 ),
			'type'       => 'message',
			'from'       => $user_email,
			'fromName'   => $user->display_name ?: $user->user_login,
			'toStageIdx' => $to_stage_idx,
			'text'       => $text,
			'at'         => gmdate( 'c' ),
		);
		if ( $to_stage_idx !== null && isset( $sub['reviewStages'][ $to_stage_idx ] ) ) {
			$entry['toStageName'] = $sub['reviewStages'][ $to_stage_idx ]['stageName'] ?? ( 'Stage ' . ( $to_stage_idx + 1 ) );
		}

		$coord_log   = isset( $sub['coordinationLog'] ) && is_array( $sub['coordinationLog'] ) ? $sub['coordinationLog'] : array();
		$coord_log[] = $entry;
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'coordinationLog' => $coord_log ) );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array( 'entry' => $entry, 'coordinationLog' => $coord_log ), 201 );
	}

	/**
	 * Gated Review: fetch meeting requests from the coordination log.
	 * GET /submissions/{id}/gated/meeting-requests
	 */
	public static function gated_meetings_get( WP_REST_Request $request ) {
		$id   = $request['id'];
		$data = Portal_Data::read_submissions();
		$sub  = null;
		foreach ( $data['submissions'] as $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $sub = $s; break; }
		}
		if ( ! $sub ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$user       = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );
		$is_admin   = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_mtg_wf = Workflow_Engine::get_workflow_for_submission( $sub );
		$_mtg_vr = Workflow_Engine::compute_viewer_role( $sub, $user_email, false, $_mtg_wf ?? array() );
		$is_gk   = $_mtg_vr === 'gatekeeper';

		$user_stage_idx = null;
		foreach ( $sub['reviewStages'] ?? array() as $si => $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) { $user_stage_idx = $si; break 2; }
			}
		}
		if ( ! $is_admin && ! $is_gk && $user_stage_idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Access denied.' ), 403 );
		}

		$meeting_reqs = array_values( array_filter( $sub['coordinationLog'] ?? array(), function( $e ) {
			return ( $e['type'] ?? '' ) === 'meeting_request';
		} ) );
		if ( ! $is_admin && ! $is_gk && $user_stage_idx !== null ) {
			$meeting_reqs = array_values( array_filter( $meeting_reqs, function( $m ) use ( $user_stage_idx, $user_email ) {
				$to   = isset( $m['toStageIdx'] ) ? (int) $m['toStageIdx'] : null;
				$from = strtolower( trim( (string) ( $m['from'] ?? '' ) ) );
				return ( $to === null || $to === $user_stage_idx || $from === $user_email );
			} ) );
		}
		return new WP_REST_Response( array( 'meetingRequests' => $meeting_reqs ), 200 );
	}

	/**
	 * Gated Review: create a meeting request.
	 * POST /submissions/{id}/gated/meeting-requests
	 */
	public static function gated_meetings_post( WP_REST_Request $request ) {
		$id   = $request['id'];
		$body = $request->get_json_params() ?: array();
		$user = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub      = $data['submissions'][ $idx ];
		$sub_type = $sub['submissionType'] ?? $sub['type'] ?? '';

		if ( ! self::is_gated_review_type( $sub_type ) ) {
			return new WP_REST_Response( array( 'error' => 'This submission does not use gated review.' ), 400 );
		}
		$is_admin = current_user_can( 'rrp_manage_workflow' ) || current_user_can( 'rrp_full_admin_access' );
		$_mtp_wf = Workflow_Engine::get_workflow_for_submission( $sub );
		$_mtp_vr = Workflow_Engine::compute_viewer_role( $sub, $user_email, false, $_mtp_wf ?? array() );
		$is_gk   = $_mtp_vr === 'gatekeeper';
		$is_rev   = false;
		foreach ( $sub['reviewStages'] ?? array() as $stage ) {
			foreach ( $stage['reviewers'] ?? array() as $r ) {
				if ( strtolower( trim( (string) ( $r['email'] ?? '' ) ) ) === $user_email ) { $is_rev = true; break 2; }
			}
		}
		if ( ! $is_admin && ! $is_gk && ! $is_rev ) {
			return new WP_REST_Response( array( 'error' => 'Only reviewers and administrators may request meetings.' ), 403 );
		}

		$proposed_time = isset( $body['proposedTime'] ) ? sanitize_text_field( trim( (string) $body['proposedTime'] ) ) : '';
		$platform      = isset( $body['platform'] ) ? sanitize_text_field( trim( (string) $body['platform'] ) ) : '';
		$note          = isset( $body['note'] ) ? sanitize_text_field( trim( (string) $body['note'] ) ) : '';
		$to_stage_idx  = ( isset( $body['toStageIdx'] ) && $body['toStageIdx'] !== null ) ? (int) $body['toStageIdx'] : null;

		if ( ! $proposed_time ) {
			return new WP_REST_Response( array( 'error' => 'A proposed time is required.' ), 400 );
		}

		$entry = array(
			'id'           => 'mr-' . time() . '-' . wp_rand( 1000, 9999 ),
			'type'         => 'meeting_request',
			'from'         => $user_email,
			'fromName'     => $user->display_name ?: $user->user_login,
			'toStageIdx'   => $to_stage_idx,
			'proposedTime' => $proposed_time,
			'platform'     => $platform,
			'note'         => $note,
			'status'       => 'pending',
			'at'           => gmdate( 'c' ),
		);
		if ( $to_stage_idx !== null && isset( $sub['reviewStages'][ $to_stage_idx ] ) ) {
			$entry['toStageName'] = $sub['reviewStages'][ $to_stage_idx ]['stageName'] ?? ( 'Stage ' . ( $to_stage_idx + 1 ) );
		}

		$coord_log   = isset( $sub['coordinationLog'] ) && is_array( $sub['coordinationLog'] ) ? $sub['coordinationLog'] : array();
		$coord_log[] = $entry;
		$data['submissions'][ $idx ] = array_merge( $sub, array( 'coordinationLog' => $coord_log ) );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array( 'entry' => $entry ), 201 );
	}

	/**
	 * Gated Review: accept or decline a meeting request.
	 * PATCH /submissions/{id}/gated/meeting-requests/{reqId}
	 */
	public static function gated_meetings_patch( WP_REST_Request $request ) {
		$id     = $request['id'];
		$req_id = $request['reqId'];
		$body   = $request->get_json_params() ?: array();
		$user   = wp_get_current_user();
		$user_email = strtolower( trim( (string) $user->user_email ) );

		$data = Portal_Data::read_submissions();
		$idx  = null;
		foreach ( $data['submissions'] as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $idx = $i; break; }
		}
		if ( $idx === null ) {
			return new WP_REST_Response( array( 'error' => 'Submission not found.' ), 404 );
		}
		$sub = $data['submissions'][ $idx ];

		$status = isset( $body['status'] ) ? sanitize_text_field( trim( (string) $body['status'] ) ) : '';
		if ( ! in_array( $status, array( 'accepted', 'declined' ), true ) ) {
			return new WP_REST_Response( array( 'error' => 'Status must be "accepted" or "declined".' ), 400 );
		}

		$coord_log = $sub['coordinationLog'] ?? array();
		$found     = false;
		foreach ( $coord_log as &$entry ) {
			if ( ( $entry['id'] ?? '' ) === $req_id && ( $entry['type'] ?? '' ) === 'meeting_request' ) {
				$entry['status']      = $status;
				$entry['respondedBy'] = $user_email;
				$entry['respondedAt'] = gmdate( 'c' );
				$found = true;
				break;
			}
		}
		unset( $entry );
		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Meeting request not found.' ), 404 );
		}

		$data['submissions'][ $idx ] = array_merge( $sub, array( 'coordinationLog' => $coord_log ) );
		Portal_Data::write_submissions( $data );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}

