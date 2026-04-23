<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppealController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GatedReleaseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\SsoAuthController;
use App\Http\Controllers\ReviewerPoolController;
use App\Http\Controllers\DocumentAnnotationController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionAuthorController;
use App\Http\Controllers\SubmissionMessageController;
use App\Http\Controllers\SubmissionReviewerController;
use App\Http\Controllers\SubmissionMeetingController;
use App\Http\Controllers\SubmissionTypeController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CustomRoleController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Public routes (no auth required) ─────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login',               [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('accept-author-invite', [SubmissionAuthorController::class, 'acceptInvite'])->middleware('throttle:5,1');
});

// Public system info (org name/logo for login screen)
Route::get('system/public', [SystemController::class, 'publicInfo']);

// SSO token exchange — consumes the one-time code from the callback redirect
Route::post('auth/sso-exchange', [AuthController::class, 'ssoExchange'])->middleware('throttle:10,1');

// ── SSO login flow (public — browser redirect) ────────────────────────────────
Route::prefix('sso/{provider}')->group(function () {
    Route::get('redirect', [SsoAuthController::class, 'redirect']);
    Route::get('callback', [SsoAuthController::class, 'callback']);
});

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout',    [AuthController::class, 'logout']);
        Route::get('me',         [AuthController::class, 'me']);
        Route::patch('password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::patch('profile',  [AuthController::class, 'updateProfile'])->middleware('throttle:20,1');
        // SSO identity management
        Route::get('sso/identities',                     [SsoAuthController::class, 'listIdentities']);
        Route::post('sso/link',                          [SsoAuthController::class, 'initiateLink'])->middleware('throttle:10,1');
        Route::delete('sso/identities/{identity}',       [SsoAuthController::class, 'unlinkIdentity']);
    });

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // ── User management ───────────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('/',                          [UserController::class, 'index']);
        Route::post('/',                         [UserController::class, 'store']);
        Route::get('/{user}',                    [UserController::class, 'show']);
        Route::patch('/{user}',                  [UserController::class, 'update']);
        Route::delete('/{user}',                 [UserController::class, 'destroy']);
        Route::post('/{user}/activate',          [UserController::class, 'activate']);
        Route::post('/{user}/unlock',            [UserController::class, 'unlock']);
        Route::post('/{user}/reset-password',    [UserController::class, 'resetPassword']);
        Route::patch('/{user}/roles',            [UserController::class, 'updateRoles']);
        Route::get('/{user}/coordinator-groups',      [UserController::class, 'coordinatorGroups']);
        Route::post('/{user}/coordinator-groups',     [UserController::class, 'addCoordinatorGroup']);
        Route::delete('/{user}/coordinator-groups/{group}', [UserController::class, 'removeCoordinatorGroup']);
    });

    // ── Group management ──────────────────────────────────────────────────────
    Route::prefix('groups')->group(function () {
        Route::get('/',                                   [GroupController::class, 'index']);
        Route::post('/',                                  [GroupController::class, 'store']);
        Route::get('/{group}',                            [GroupController::class, 'show']);
        Route::patch('/{group}',                          [GroupController::class, 'update']);
        Route::delete('/{group}',                         [GroupController::class, 'destroy']);
        Route::get('/{group}/members',                    [GroupController::class, 'members']);
        Route::post('/{group}/members',                   [GroupController::class, 'addMembers']);
        Route::patch('/{group}/members/{user}',           [GroupController::class, 'updateMember']);
        Route::delete('/{group}/members/{user}',          [GroupController::class, 'removeMember']);
    });

    // ── Admin-only system routes ───────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('system')->group(function () {

        // Organization
        Route::get('organization',              [SystemController::class, 'getOrganization']);
        Route::patch('organization',            [SystemController::class, 'updateOrganization']);
        Route::post('organization/logo',        [SystemController::class, 'uploadLogo']);

        // Feature flags
        Route::get('feature-flags',             [SystemController::class, 'getFeatureFlags']);
        Route::patch('feature-flags/{key}',     [SystemController::class, 'updateFeatureFlag']);

        // Password policy
        Route::get('password-policy',           [SystemController::class, 'getPasswordPolicy']);
        Route::patch('password-policy',         [SystemController::class, 'updatePasswordPolicy']);

        // Email settings
        Route::get('email',                     [SystemController::class, 'getEmail']);
        Route::patch('email',                   [SystemController::class, 'updateEmail']);
        Route::post('email/test',               [SystemController::class, 'testEmail']);

        // SSO providers
        Route::get('sso',                       [SystemController::class, 'getSsoProviders']);
        Route::post('sso',                      [SystemController::class, 'createSsoProvider']);
        Route::patch('sso/{provider}',          [SystemController::class, 'updateSsoProvider']);
        Route::delete('sso/{provider}',         [SystemController::class, 'deleteSsoProvider']);

        // Notification templates
        Route::get('notification-templates',             [SystemController::class, 'getNotificationTemplates']);
        Route::get('notification-templates/{template}',  [SystemController::class, 'getNotificationTemplate']);
        Route::patch('notification-templates/{template}', [SystemController::class, 'updateNotificationTemplate']);

        // Backup
        Route::get('backup',  [SystemController::class, 'listBackups']);
        Route::post('backup', [SystemController::class, 'runBackup']);
    });

    // ── Programs ──────────────────────────────────────────────────────────────
    Route::get('programs', [ProgramController::class, 'index']);
    Route::middleware('role:admin,coordinator')->group(function () {
        Route::post('programs',              [ProgramController::class, 'store']);
        Route::patch('programs/{id}',        [ProgramController::class, 'update']);
        Route::delete('programs/{id}',       [ProgramController::class, 'destroy']);
    });

    // ── Submission Types (active list, for submission form) ──────────────────
    Route::get('submission-types', function (\Illuminate\Http\Request $r) {
        return app(SubmissionTypeController::class)->index($r->merge(['active_only' => true]));
    });

    // ── Admin: Submission Categories + Workflows ──────────────────────────────
    Route::middleware('role:admin,coordinator')->prefix('admin')->group(function () {
        // Submission type (categories) CRUD
        Route::get('submission-types',           [SubmissionTypeController::class, 'index']);
        Route::get('submission-types/{id}',      [SubmissionTypeController::class, 'show']);
        Route::post('submission-types',          [SubmissionTypeController::class, 'store']);
        Route::patch('submission-types/{id}',    [SubmissionTypeController::class, 'update']);
        Route::delete('submission-types/{id}',   [SubmissionTypeController::class, 'destroy']);
        // Group access management
        Route::get('submission-types/{id}/groups',   [SubmissionTypeController::class, 'getGroups']);
        Route::put('submission-types/{id}/groups',   [SubmissionTypeController::class, 'syncGroups']);
        // User (direct) access management
        Route::get('submission-types/{id}/users',    [SubmissionTypeController::class, 'getUsers']);
        Route::put('submission-types/{id}/users',    [SubmissionTypeController::class, 'syncUsers']);

        // Workflow CRUD
        Route::get('workflows',                          [WorkflowController::class, 'index']);
        Route::get('workflows/{id}',                     [WorkflowController::class, 'show']);
        Route::post('workflows',                         [WorkflowController::class, 'store']);
        Route::patch('workflows/{id}',                   [WorkflowController::class, 'update']);
        Route::delete('workflows/{id}',                  [WorkflowController::class, 'destroy']);
        Route::post('workflows/{id}/stages',             [WorkflowController::class, 'addStage']);
        Route::patch('workflows/{id}/stages/{stageId}',  [WorkflowController::class, 'updateStage']);
        Route::delete('workflows/{id}/stages/{stageId}', [WorkflowController::class, 'deleteStage']);

        // Reviewer pool (pre-assignment of reviewers to categories)
        Route::get('reviewer-pools',          [ReviewerPoolController::class, 'index']);
        Route::post('reviewer-pools',         [ReviewerPoolController::class, 'store']);
        Route::delete('reviewer-pools/{id}',  [ReviewerPoolController::class, 'destroy']);

        // Audit logs
        Route::get('audit-logs',         [AuditLogController::class, 'index']);
        Route::get('audit-logs/actions', [AuditLogController::class, 'actions']);

        // Analytics
        Route::get('analytics/overview',         [AnalyticsController::class, 'overview']);
        Route::get('analytics/turnaround',       [AnalyticsController::class, 'turnaround']);
        Route::get('analytics/reviewer-load',    [AnalyticsController::class, 'reviewerLoad']);
        Route::get('analytics/metrics',          [AnalyticsController::class, 'metrics']);

        // Coordinator pending actions (extensions, conflicts, unassigned submissions)
        Route::get('reviewer-pending-actions',   [SubmissionReviewerController::class, 'pendingActions']);

        // Webhooks
        Route::get('webhooks/events',          [WebhookController::class, 'events']);
        Route::get('webhooks',                 [WebhookController::class, 'index']);
        Route::post('webhooks',                [WebhookController::class, 'store']);
        Route::get('webhooks/{id}',            [WebhookController::class, 'show']);
        Route::patch('webhooks/{id}',          [WebhookController::class, 'update']);
        Route::delete('webhooks/{id}',         [WebhookController::class, 'destroy']);
        Route::get('webhooks/{id}/deliveries', [WebhookController::class, 'deliveries']);

        // Appeals management
        Route::get('appeals',         [AppealController::class, 'index']);
        Route::get('appeals/{id}',    [AppealController::class, 'show']);
        Route::patch('appeals/{id}',  [AppealController::class, 'update']);

        // Announcements
        Route::get('announcements',                      [AnnouncementController::class, 'index']);
        Route::post('announcements',                     [AnnouncementController::class, 'store']);
        Route::patch('announcements/{announcement}',     [AnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}',    [AnnouncementController::class, 'destroy']);
        Route::post('announcements/{announcement}/broadcast', [AnnouncementController::class, 'broadcast']);

        // Custom roles (workflow stage labels)
        Route::get('custom-roles',                  [CustomRoleController::class, 'index']);
        Route::post('custom-roles',                 [CustomRoleController::class, 'store']);
        Route::patch('custom-roles/{customRole}',   [CustomRoleController::class, 'update']);
        Route::delete('custom-roles/{customRole}',  [CustomRoleController::class, 'destroy']);
    });

    // ── Gated reviews — accessible to admin, coordinator, and assigned gatekeeper reviewers ──
    Route::middleware('role:admin,coordinator,reviewer')->prefix('admin')->group(function () {
        Route::get('gated-reviews',                          [GatedReleaseController::class, 'index']);
        Route::get('gated-reviews/{submissionId}',           [GatedReleaseController::class, 'show']);
        Route::post('gated-reviews',                         [GatedReleaseController::class, 'store']);
        Route::post('gated-reviews/{submissionId}/recheck',  [GatedReleaseController::class, 'recheck']);
    });

    // ── Reviewer self-analytics (accessible to reviewer role only) ───────────
    Route::middleware('role:reviewer,admin,coordinator')->group(function () {
        Route::get('analytics/my-stats',               [AnalyticsController::class, 'myStats']);
        Route::get('analytics/my-daily',               [AnalyticsController::class, 'myDailyActivity']);
        Route::get('analytics/my-submission-breakdown',[AnalyticsController::class, 'mySubmissionBreakdown']);
    });

    // ── Calendar ─────────────────────────────────────────────────────────────
    Route::get('calendar/deadlines', [SubmissionController::class, 'calendarDeadlines']);

    // ── Submissions ───────────────────────────────────────────────────────────
    Route::prefix('submissions')->group(function () {
        Route::get('/',     [SubmissionController::class, 'index']);
        Route::get('/my-reviews', [SubmissionController::class, 'myReviews']);
        Route::post('/',    [SubmissionController::class, 'store']);
        Route::get('/{id}', [SubmissionController::class, 'show']);
        Route::patch('/{id}', [SubmissionController::class, 'update']);
        Route::delete('/{id}', [SubmissionController::class, 'destroy']);
        Route::post('/{id}/submit',    [SubmissionController::class, 'submit']);
        Route::post('/{id}/withdraw',  [SubmissionController::class, 'withdraw']);
        Route::get('/{id}/activity',   [SubmissionController::class, 'activityLog']);
        Route::get('/{id}/feedback',   [SubmissionController::class, 'feedback']);
        Route::post('/{id}/appeal',    [SubmissionController::class, 'submitAppeal'])->middleware('throttle:5,1');
        Route::get('/{id}/review-progress', [SubmissionController::class, 'reviewProgress']);
        // Gated release — accessible to admin/coordinator OR the assigned gatekeeper reviewer
        Route::get('/{id}/gated-release',  [GatedReleaseController::class, 'showForSubmission']);
        Route::post('/{id}/gated-release', [GatedReleaseController::class, 'storeForSubmission']);
        Route::middleware('role:admin,coordinator')->group(function () {
            Route::post('/{id}/advance-review', [SubmissionController::class, 'advanceReview']);
        });
        Route::get('/{id}/versions',  [SubmissionController::class, 'listVersions']);
        Route::post('/{id}/versions', [SubmissionController::class, 'uploadVersion']);
        Route::get('/{id}/files/{version}/{filename}', [SubmissionController::class, 'downloadFile'])
            ->where('filename', '.+');

        // Inline document annotations
        Route::get('/{id}/annotations',                   [DocumentAnnotationController::class, 'index']);
        Route::post('/{id}/annotations',                  [DocumentAnnotationController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('/{id}/annotations/{annotationId}', [DocumentAnnotationController::class, 'destroy']);

        // Submission messages (communication tab)
        Route::get('/{id}/messages',                      [SubmissionMessageController::class, 'index']);
        Route::post('/{id}/messages',                     [SubmissionMessageController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('/{id}/messages/{messageId}',       [SubmissionMessageController::class, 'destroy']);

        // Authors
        Route::get('/{id}/authors',                              [SubmissionAuthorController::class, 'index']);
        Route::post('/{id}/authors',                             [SubmissionAuthorController::class, 'store']);
        Route::patch('/{id}/authors/reorder',                    [SubmissionAuthorController::class, 'reorder']);
        Route::delete('/{id}/authors/{authorId}',                [SubmissionAuthorController::class, 'destroy']);
        Route::post('/{id}/authors/{authorId}/resend-invite',    [SubmissionAuthorController::class, 'resendInvite']);

        // Reviewers (assign/manage per submission) — assign/remove restricted to coordinator+
        Route::get('/{id}/reviewers',                            [SubmissionReviewerController::class, 'index']);
        Route::middleware('role:admin,coordinator')->group(function () {
            Route::post('/{id}/reviewers',                       [SubmissionReviewerController::class, 'store']);
            Route::delete('/{id}/reviewers/{reviewerId}',        [SubmissionReviewerController::class, 'destroy']);
            Route::get('/{id}/reviewer-pool-suggestions',        [SubmissionReviewerController::class, 'poolSuggestions']);
        });
        Route::patch('/{id}/reviewers/{reviewerId}',             [SubmissionReviewerController::class, 'update']);
        Route::post('/{id}/reviewers/{reviewerId}/request-extension', [SubmissionReviewerController::class, 'requestExtension'])->middleware('throttle:10,1');
        Route::post('/{id}/reviewers/{reviewerId}/flag-conflict',      [SubmissionReviewerController::class, 'flagConflict'])->middleware('throttle:10,1');
        Route::post('/{id}/reviewers/{reviewerId}/resolve-conflict',   [SubmissionReviewerController::class, 'resolveConflict'])->middleware('throttle:10,1');

        // Meetings (request/schedule by reviewer or submitter; confirm by admin)
        Route::get('/{id}/meetings',                             [SubmissionMeetingController::class, 'index']);
        Route::post('/{id}/meetings',                            [SubmissionMeetingController::class, 'store']);
        Route::delete('/{id}/meetings/{meetingId}',              [SubmissionMeetingController::class, 'destroy']);
        Route::middleware('role:admin,coordinator')->group(function () {
            Route::patch('/{id}/meetings/{meetingId}',           [SubmissionMeetingController::class, 'update']);
        });

        // Similarity check
        Route::get('/{id}/similarity', [SubmissionController::class, 'similarity']);

    }); // end prefix('submissions')

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::prefix('notifications')->group(function () {
        Route::get('/',             [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/read',        [NotificationController::class, 'markRead']);
    });

}); // end middleware('auth:sanctum')

