<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/stats
     * Returns role-appropriate dashboard statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $roles = $user->roles ?? [];

        if (in_array('admin', $roles)) {
            return $this->adminStats();
        }

        if (in_array('coordinator', $roles)) {
            return $this->coordinatorStats();
        }

        if (in_array('reviewer', $roles)) {
            return $this->reviewerStats($user);
        }

        return $this->studentStats($user);
    }

    // ── Role-specific stat builders ───────────────────────────────────────────

    private function coordinatorStats(): JsonResponse
    {
        $active = Submission::whereIn('status', [
            Submission::STATUS_SUBMITTED,
            Submission::STATUS_AWAITING_REVIEWERS,
            Submission::STATUS_IN_REVIEW,
            Submission::STATUS_REVISION_REQUIRED,
            Submission::STATUS_RESUBMITTED,
        ])->count();

        $pendingAssignment = Submission::where('status', Submission::STATUS_AWAITING_REVIEWERS)->count();

        $overdueReviews = SubmissionReviewer::where('status', '!=', 'completed')
            ->where('status', '!=', 'declined')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->distinct('submission_id')
            ->count('submission_id');

        $totalReviews = SubmissionReviewer::where('status', '!=', 'declined')->count();

        $completedReviews = SubmissionReviewer::where('status', 'completed')->count();

        $underReview = Submission::where('status', Submission::STATUS_IN_REVIEW)->count();

        $revisionRequired = Submission::where('status', Submission::STATUS_REVISION_REQUIRED)->count();

        $cancelled = Submission::where('status', Submission::STATUS_CANCELLED)->count();

        $withdrawn = Submission::where('status', Submission::STATUS_WITHDRAWN)->count();

        $activeUsers = User::where('is_active', true)->count();

        $activeReviewers = User::where('is_active', true)
            ->whereRaw("roles @> ?", [json_encode(['reviewer'])])
            ->count();

        return response()->json([
            'role'  => 'coordinator',
            'stats' => [
                ['key' => 'active_submissions',  'label' => 'Active Submissions',  'value' => $active],
                ['key' => 'pending_assignment',  'label' => 'Pending Assignment',  'value' => $pendingAssignment],
                ['key' => 'overdue',             'label' => 'Overdue Reviews',     'value' => $overdueReviews],
                ['key' => 'total_reviews',       'label' => 'Total Reviews',       'value' => $totalReviews],
                ['key' => 'completed_reviews',   'label' => 'Completed Reviews',   'value' => $completedReviews],
                ['key' => 'under_review',        'label' => 'Under Review',        'value' => $underReview],
                ['key' => 'revision_required',   'label' => 'Revision Required',   'value' => $revisionRequired],
                ['key' => 'cancelled',           'label' => 'Cancelled',           'value' => $cancelled],
                ['key' => 'withdrawn',           'label' => 'Withdrawn',           'value' => $withdrawn],
                ['key' => 'total_users',         'label' => 'Active Users',        'value' => $activeUsers],
                ['key' => 'active_reviewers',    'label' => 'Active Reviewers',    'value' => $activeReviewers],
            ],
        ]);
    }

    private function adminStats(): JsonResponse
    {
        $active = Submission::whereIn('status', [
            Submission::STATUS_SUBMITTED,
            Submission::STATUS_AWAITING_REVIEWERS,
            Submission::STATUS_IN_REVIEW,
            Submission::STATUS_REVISION_REQUIRED,
        ])->count();

        $pendingAssignment = Submission::where('status', Submission::STATUS_AWAITING_REVIEWERS)->count();

        $overdue = SubmissionReviewer::where('status', '!=', 'completed')
            ->where('status', '!=', 'declined')
            ->where('due_at', '<', now()->toDateString())
            ->distinct('submission_id')
            ->count('submission_id');

        $thisMonthCompleted = Submission::whereIn('status', [
            Submission::STATUS_ACCEPTED,
            Submission::STATUS_CONDITIONALLY_ACCEPTED,
            Submission::STATUS_REJECTED,
        ])
        ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
        ->count();

        $totalUsers = User::where('is_active', true)->count();

        return response()->json([
            'role'  => 'admin',
            'stats' => [
                ['key' => 'active_submissions',    'label' => 'Active Submissions',    'value' => $active],
                ['key' => 'pending_assignment',    'label' => 'Pending Assignment',    'value' => $pendingAssignment],
                ['key' => 'overdue',               'label' => 'Overdue Reviews',       'value' => $overdue],
                ['key' => 'completed_this_month',  'label' => 'Completed This Month',  'value' => $thisMonthCompleted],
                ['key' => 'total_users',           'label' => 'Active Users',          'value' => $totalUsers],
            ],
        ]);
    }

    private function reviewerStats(User $user): JsonResponse
    {
        $total = SubmissionReviewer::where('user_id', $user->id)
            ->where('status', '!=', 'declined')
            ->count();

        $pending = SubmissionReviewer::where('user_id', $user->id)
            ->whereNull('decision')
            ->where('status', '!=', 'declined')
            ->count();

        $overdue = SubmissionReviewer::where('user_id', $user->id)
            ->whereNull('decision')
            ->where('status', '!=', 'declined')
            ->where('due_at', '<', now()->toDateString())
            ->count();

        $completed = SubmissionReviewer::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        return response()->json([
            'role'  => 'reviewer',
            'stats' => [
                ['key' => 'total_assigned',  'label' => 'Total Assigned',  'value' => $total],
                ['key' => 'pending_reviews',  'label' => 'Pending Review',  'value' => $pending],
                ['key' => 'overdue',          'label' => 'Overdue',          'value' => $overdue],
                ['key' => 'completed',        'label' => 'Completed',        'value' => $completed],
            ],
        ]);
    }

    private function studentStats(User $user): JsonResponse
    {
        $mySubmissions = Submission::where('submitter_id', $user->id)->count();

        $draft = Submission::where('submitter_id', $user->id)
            ->where('status', Submission::STATUS_DRAFT)
            ->count();

        $awaitingReviewers = Submission::where('submitter_id', $user->id)
            ->where('status', Submission::STATUS_AWAITING_REVIEWERS)
            ->count();

        $inReview = Submission::where('submitter_id', $user->id)
            ->where('status', Submission::STATUS_IN_REVIEW)
            ->count();

        $accepted = Submission::where('submitter_id', $user->id)
            ->whereIn('status', [
                Submission::STATUS_ACCEPTED,
                Submission::STATUS_CONDITIONALLY_ACCEPTED,
            ])->count();

        $revisionRequired = Submission::where('submitter_id', $user->id)
            ->where('status', Submission::STATUS_REVISION_REQUIRED)
            ->count();

        $withdrawn = Submission::where('submitter_id', $user->id)
            ->whereIn('status', [
                Submission::STATUS_WITHDRAWN,
                Submission::STATUS_CANCELLED,
            ])->count();

        return response()->json([
            'role'  => 'student',
            'stats' => [
                ['key' => 'my_submissions',       'label' => 'My Submissions',           'value' => $mySubmissions],
                ['key' => 'draft',                'label' => 'Drafts',                   'value' => $draft],
                ['key' => 'awaiting_reviewers',   'label' => 'Awaiting Reviewer',        'value' => $awaitingReviewers],
                ['key' => 'under_review',         'label' => 'Under Review',             'value' => $inReview],
                ['key' => 'revision_required',  'label' => 'Revision Required',  'value' => $revisionRequired],
                ['key' => 'accepted',           'label' => 'Accepted',           'value' => $accepted],
                ['key' => 'withdrawn',          'label' => 'Withdrawn',          'value' => $withdrawn],
            ],
        ]);
    }
}
