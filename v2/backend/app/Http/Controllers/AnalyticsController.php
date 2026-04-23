<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionReviewer;
use App\Models\SubmissionType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/analytics/overview
     * High-level counts for the admin dashboard.
     */
    public function overview(Request $request): JsonResponse
    {
        // Submission counts by status
        $byStatus = Submission::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Submission counts by type
        $byType = Submission::select(
                'submission_types.label',
                DB::raw('count(submissions.id) as count')
            )
            ->leftJoin('submission_types', 'submissions.submission_type_id', '=', 'submission_types.id')
            ->groupBy('submission_types.label')
            ->pluck('count', 'label');

        // Monthly submission trend (last 12 months)
        $monthlyTrend = DB::select("
            SELECT to_char(created_at, 'YYYY-MM') AS month, count(*) AS count
            FROM submissions
            WHERE created_at >= now() - interval '12 months'
            GROUP BY month
            ORDER BY month
        ");

        // Active users (logged in within last 30 days)
        $activeUsers = User::where('last_login_at', '>=', now()->subDays(30))->count();

        // Total counts
        $totalSubmissions  = Submission::count();
        $totalUsers        = User::where('is_active', true)->count();
        $pendingReview     = Submission::whereIn('status', ['SUBMITTED', 'IN_REVIEW'])->count();
        $completedThisMonth = Submission::whereIn('status', ['ACCEPTED', 'CONDITIONALLY_ACCEPTED', 'REJECTED'])
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        // ── Extended coordinator/admin stats ──────────────────────────────────
        $activeSubmissions = Submission::whereIn('status', [
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

        $totalReviews     = SubmissionReviewer::where('status', '!=', 'declined')->count();
        $completedReviews = SubmissionReviewer::where('status', 'completed')->count();
        $underReview      = Submission::where('status', Submission::STATUS_IN_REVIEW)->count();
        $revisionRequired = Submission::where('status', Submission::STATUS_REVISION_REQUIRED)->count();
        $cancelled        = Submission::where('status', Submission::STATUS_CANCELLED)->count();
        $withdrawn        = Submission::where('status', Submission::STATUS_WITHDRAWN)->count();
        $activeReviewers  = User::where('is_active', true)
            ->whereRaw("roles @> ?", [json_encode(['reviewer'])])
            ->count();

        return response()->json([
            'data' => [
                'total_submissions'       => $totalSubmissions,
                'total_active_users'      => $totalUsers,
                'active_users_30d'        => $activeUsers,
                'pending_review'          => $pendingReview,
                'completed_this_month'    => $completedThisMonth,
                'by_status'               => $byStatus,
                'by_type'                 => $byType,
                'monthly_trend'           => $monthlyTrend,
                // Extended stats
                'active_submissions'      => $activeSubmissions,
                'pending_assignment'      => $pendingAssignment,
                'overdue_reviews'         => $overdueReviews,
                'total_reviews'           => $totalReviews,
                'completed_reviews'       => $completedReviews,
                'under_review'            => $underReview,
                'revision_required'       => $revisionRequired,
                'cancelled'               => $cancelled,
                'withdrawn'               => $withdrawn,
                'active_reviewers'        => $activeReviewers,
            ],
        ]);
    }

    /**
     * GET /api/analytics/turnaround
     * Average review turnaround time by submission type.
     */
    public function turnaround(Request $request): JsonResponse
    {
        // Average days from SUBMITTED to first decision (accepted/rejected/revision)
        $rows = DB::select("
            SELECT
                st.label AS type_label,
                count(s.id) AS total,
                round(avg(
                    extract(epoch from (s.updated_at - s.created_at)) / 86400.0
                )::numeric, 1) AS avg_days,
                round(min(
                    extract(epoch from (s.updated_at - s.created_at)) / 86400.0
                )::numeric, 1) AS min_days,
                round(max(
                    extract(epoch from (s.updated_at - s.created_at)) / 86400.0
                )::numeric, 1) AS max_days
            FROM submissions s
            LEFT JOIN submission_types st ON s.submission_type_id = st.id
            WHERE s.status IN ('ACCEPTED','CONDITIONALLY_ACCEPTED','REJECTED','REVISION_REQUIRED')
            GROUP BY st.label
            ORDER BY avg_days DESC NULLS LAST
        ");

        // Per-stage average (using submission_reviewers decision timing)
        $stageRows = DB::select("
            SELECT
                sd.stage_role_label,
                count(sr.id) AS total,
                round(avg(
                    extract(epoch from (sr.decision_at - sr.assigned_at)) / 86400.0
                )::numeric, 1) AS avg_days
            FROM submission_reviewers sr
            JOIN stage_definitions sd ON sd.id = sr.stage_id
            WHERE sr.decision IS NOT NULL
              AND sr.assigned_at IS NOT NULL
              AND sr.decision_at IS NOT NULL
            GROUP BY sd.stage_role_label
            ORDER BY avg_days DESC NULLS LAST
        ");

        return response()->json([
            'data' => [
                'by_type'  => $rows,
                'by_stage' => $stageRows,
            ],
        ]);
    }

    /**
     * GET /api/analytics/reviewer-load
     * Reviewer workload — active assignments and decision rate.
     */
    public function reviewerLoad(Request $request): JsonResponse
    {
        $rows = DB::select("
            SELECT
                u.id,
                u.name,
                u.email,
                count(sr.id) FILTER (WHERE sr.decision IS NULL)  AS pending_count,
                count(sr.id) FILTER (WHERE sr.decision IS NOT NULL) AS completed_count,
                count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL AND sr.decision_at::date <= sr.due_at) AS on_time_count,
                count(sr.id) FILTER (WHERE sr.due_at IS NOT NULL AND sr.due_at < CURRENT_DATE AND sr.decision IS NULL) AS overdue_count,
                count(sr.id) AS total_count,
                round(
                    100.0 * count(sr.id) FILTER (WHERE sr.decision IS NOT NULL)
                    / NULLIF(count(sr.id), 0), 1
                ) AS completion_rate,
                round(avg(
                    CASE WHEN sr.decision IS NOT NULL AND sr.assigned_at IS NOT NULL AND sr.decision_at IS NOT NULL
                    THEN extract(epoch from (sr.decision_at - sr.assigned_at)) / 86400.0
                    ELSE NULL END
                )::numeric, 1) AS avg_turnaround_days,
                round(
                    100.0 * count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL AND sr.decision_at::date <= sr.due_at)
                    / NULLIF(count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL), 0), 1
                ) AS on_time_rate,
                max(sr.assigned_at) AS last_assigned_at
            FROM users u
            JOIN submission_reviewers sr ON sr.user_id = u.id
            GROUP BY u.id, u.name, u.email
            HAVING count(sr.id) > 0
            ORDER BY pending_count DESC, total_count DESC
            LIMIT 50
        ");

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/analytics/metrics
     * High-level KPI metrics: Late Alerts, Avg Time to Decision,
     * Avg Stages / Submission, Mean Reviewer Load.
     */
    public function metrics(Request $request): JsonResponse
    {
        // Late Alerts: reviewer assignments that are overdue
        $lateAlerts = SubmissionReviewer::where('status', '!=', 'completed')
            ->where('status', '!=', 'declined')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->count();

        // Avg Time to Decision: average days from submission created_at to final-decision updated_at
        $timeRow = DB::selectOne("
            SELECT round(avg(
                extract(epoch from (updated_at - created_at)) / 86400.0
            )::numeric, 1) AS avg_days
            FROM submissions
            WHERE status IN ('ACCEPTED','CONDITIONALLY_ACCEPTED','REJECTED')
        ");

        // Avg Stages / Submission: mean distinct stages per submission
        $stagesRow = DB::selectOne("
            SELECT round(avg(stage_count)::numeric, 1) AS avg_stages
            FROM (
                SELECT submission_id, count(DISTINCT stage_id) AS stage_count
                FROM submission_reviewers
                GROUP BY submission_id
            ) sub
        ");

        // Mean Reviewer Load: average pending (undecided) assignments per reviewer
        $loadRow = DB::selectOne("
            SELECT round(avg(pending_count)::numeric, 1) AS mean_load
            FROM (
                SELECT user_id, count(*) AS pending_count
                FROM submission_reviewers
                WHERE decision IS NULL
                  AND status NOT IN ('declined','completed')
                GROUP BY user_id
            ) sub
        ");

        return response()->json([
            'data' => [
                'late_alerts'          => $lateAlerts,
                'avg_time_to_decision' => $timeRow?->avg_days,
                'avg_stages'           => $stagesRow?->avg_stages,
                'mean_reviewer_load'   => $loadRow?->mean_load,
            ],
        ]);
    }

    /**
     * GET /api/analytics/my-stats
     * Personal reviewer statistics — accessible by the authenticated reviewer.
     */
    public function myStats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $row = DB::selectOne("
            SELECT
                count(sr.id) AS total_assigned,
                count(sr.id) FILTER (WHERE sr.decision IS NULL) AS pending,
                count(sr.id) FILTER (WHERE sr.decision IS NOT NULL) AS completed,
                count(sr.id) FILTER (WHERE sr.due_at IS NOT NULL AND sr.due_at < CURRENT_DATE AND sr.decision IS NULL) AS overdue,
                count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL AND sr.decision_at::date <= sr.due_at) AS on_time,
                round(avg(
                    CASE WHEN sr.decision IS NOT NULL AND sr.assigned_at IS NOT NULL AND sr.decision_at IS NOT NULL
                    THEN extract(epoch from (sr.decision_at - sr.assigned_at)) / 86400.0
                    ELSE NULL END
                )::numeric, 1) AS avg_turnaround_days,
                round(
                    100.0 * count(sr.id) FILTER (WHERE sr.decision IS NOT NULL)
                    / NULLIF(count(sr.id), 0), 1
                ) AS completion_rate,
                round(
                    100.0 * count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL AND sr.decision_at::date <= sr.due_at)
                    / NULLIF(count(sr.id) FILTER (WHERE sr.decision IS NOT NULL AND sr.due_at IS NOT NULL), 0), 1
                ) AS on_time_rate
            FROM submission_reviewers sr
            WHERE sr.user_id = ?
        ", [$userId]);

        $decisionBreakdown = DB::select("
            SELECT decision, count(*) as count
            FROM submission_reviewers
            WHERE user_id = ? AND decision IS NOT NULL
            GROUP BY decision
        ", [$userId]);

        $monthlyTrend = DB::select("
            SELECT to_char(decision_at, 'YYYY-MM') AS month, count(*) AS count
            FROM submission_reviewers
            WHERE user_id = ? AND decision IS NOT NULL
              AND decision_at >= now() - interval '12 months'
            GROUP BY month ORDER BY month
        ", [$userId]);

        return response()->json([
            'data' => [
                'summary'            => $row,
                'decision_breakdown' => $decisionBreakdown,
                'monthly_trend'      => $monthlyTrend,
            ],
        ]);
    }

    /**
     * GET /api/analytics/my-daily
     * Returns the last 30 days of daily decision activity for the reviewer.
     */
    public function myDailyActivity(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $rows = DB::select("
            SELECT
                to_char(decision_at, 'YYYY-MM-DD') AS day,
                decision,
                count(*) AS cnt
            FROM submission_reviewers
            WHERE user_id = ?
              AND decision IS NOT NULL
              AND decision_at >= now() - interval '30 days'
            GROUP BY day, decision
            ORDER BY day
        ", [$userId]);

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r->day][$r->decision] = (int) $r->cnt;
        }

        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $result[] = [
                'day'     => $day,
                'approve' => $byDay[$day]['approve'] ?? 0,
                'reject'  => $byDay[$day]['reject']  ?? 0,
                'revise'  => $byDay[$day]['revise']  ?? 0,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/analytics/my-submission-breakdown
     * Returns reviewer assignments grouped by status and submission type.
     */
    public function mySubmissionBreakdown(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $byStatus = DB::select("
            SELECT
                CASE
                    WHEN sr.decision IS NOT NULL THEN 'completed'
                    WHEN sr.due_at IS NOT NULL AND sr.due_at < CURRENT_DATE THEN 'overdue'
                    ELSE 'pending'
                END AS status_bucket,
                count(*) AS cnt
            FROM submission_reviewers sr
            WHERE sr.user_id = ?
              AND sr.status != 'declined'
            GROUP BY status_bucket
        ", [$userId]);

        $byType = DB::select("
            SELECT
                COALESCE(st.label, 'Unknown') AS type_label,
                count(*) AS cnt
            FROM submission_reviewers sr
            JOIN submissions s ON s.id = sr.submission_id
            LEFT JOIN submission_types st ON st.id = s.submission_type_id
            WHERE sr.user_id = ?
              AND sr.status != 'declined'
            GROUP BY type_label
            ORDER BY cnt DESC
            LIMIT 10
        ", [$userId]);

        return response()->json([
            'data' => [
                'by_status' => $byStatus,
                'by_type'   => $byType,
            ],
        ]);
    }
}
