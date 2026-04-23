<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GatedRelease;
use App\Models\Submission;
use App\Models\SubmissionReviewer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatedReleaseController extends Controller
{
    // ── List ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/gated-reviews
     * Returns submissions that need a gated release decision (PENDING_RELEASE)
     * plus recently decided ones (ACCEPTED / CONDITIONALLY_ACCEPTED / REJECTED
     * with a gated_release record).
     *
     * Admin/coordinator: see all gated submissions.
     * Reviewer: see only submissions where they are assigned to a gatekeeper stage.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $roles = (array) ($user->roles ?? []);
        $isAdminOrCoordinator = count(array_intersect($roles, ['admin', 'coordinator'])) > 0;

        // Reviewers only see their own gatekeeper assignments.
        $scopeIds = null;
        if (!$isAdminOrCoordinator) {
            $scopeIds = SubmissionReviewer::where('user_id', $user->id)
                ->whereHas('stage', fn ($q) => $q->where('is_gatekeeper', true))
                ->pluck('submission_id');
        }

        $applyScope = function ($q) use ($scopeIds) {
            if ($scopeIds !== null) {
                $q->whereIn('id', $scopeIds);
            }
        };

        $pending = Submission::with([
            'submitter:id,name,email',
            'submissionType:id,slug,label,is_gated_review',
            'program:id,name',
        ])
        ->whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
        ->where('status', Submission::STATUS_PENDING_RELEASE)
        ->tap($applyScope)
        ->orderBy('updated_at', 'desc')
        ->get();

        $decided = Submission::with([
            'submitter:id,name,email',
            'submissionType:id,slug,label,is_gated_review',
            'program:id,name',
        ])
        ->whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
        ->whereIn('status', [
            Submission::STATUS_ACCEPTED,
            Submission::STATUS_CONDITIONALLY_ACCEPTED,
            Submission::STATUS_REJECTED,
        ])
        ->whereHas('gatedReleases')
        ->tap($applyScope)
        ->orderBy('updated_at', 'desc')
        ->limit(20)
        ->get();

        $format = fn (Submission $s) => [
            'id'              => $s->id,
            'title'           => $s->title,
            'status'          => $s->status,
            'submitter_name'  => $s->submitter?->name,
            'submission_type' => $s->submissionType?->label,
            'program'         => $s->program?->name,
            'current_version' => $s->current_version,
            'updated_at'      => $s->updated_at,
            'pending_gatekeeper_stage_name'    => ($s->metadata ?? [])['pending_gatekeeper_stage_name'] ?? null,
            'pending_gatekeeper_stage_outcome' => ($s->metadata ?? [])['pending_gatekeeper_stage_outcome'] ?? null,
        ];

        return response()->json([
            'pending'        => $pending->map($format)->values(),
            'recently_decided' => $decided->map($format)->values(),
        ]);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/gated-reviews/{submissionId}
     * Full submission detail with reviewer decisions and any gated release record.
     * Reviewers may only view submissions they are assigned to as gatekeeper.
     */
    public function show(string $submissionId): JsonResponse
    {
        $user  = request()->user();
        $roles = (array) ($user->roles ?? []);
        if (!count(array_intersect($roles, ['admin', 'coordinator'])) &&
            !$this->canAccessGatedRelease($user, $roles, $submissionId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submission = Submission::with([
            'submitter:id,name,email,organization',
            'submissionType:id,slug,label,is_gated_review',
            'program:id,name',
            'versions',
            'authors.user:id,name,email',
            'reviewers.user:id,name,email',
            'reviewers.stage:id,name,stage_role_label,order',
        ])
        ->whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
        ->findOrFail($submissionId);

        $latestRelease = GatedRelease::with('releasedBy:id,name,email')
            ->where('submission_id', $submissionId)
            ->latest('released_at')
            ->first();

        // Group reviewer decisions by stage
        $stages = $submission->reviewers
            ->groupBy('stage_id')
            ->map(function ($stageReviewers) {
                $first = $stageReviewers->first();
                return [
                    'stage_id'     => $first->stage_id,
                    'stage_name'   => $first->stage?->name,
                    'stage_role'   => $first->stage?->stage_role_label,
                    'order'        => $first->stage?->order,
                    'reviewers'    => $stageReviewers->map(fn ($r) => [
                        'reviewer_id'   => $r->user_id,
                        'reviewer_name' => $r->user?->name,
                        'decision'      => $r->decision,
                        'decision_at'   => $r->decision_at,
                        'comments'      => $r->comments,
                        'status'        => $r->status,
                    ])->values(),
                ];
            })
            ->sortBy('order')
            ->values();

        return response()->json([
            'id'              => $submission->id,
            'title'           => $submission->title,
            'abstract'        => $submission->abstract,
            'status'          => $submission->status,
            'current_version' => $submission->current_version,
            'submitter'       => $submission->submitter ? [
                'id'           => $submission->submitter->id,
                'name'         => $submission->submitter->name,
                'email'        => $submission->submitter->email,
                'organization' => $submission->submitter->organization,
            ] : null,
            'submission_type' => $submission->submissionType ? [
                'id'              => $submission->submissionType->id,
                'label'           => $submission->submissionType->label,
                'is_gated_review' => $submission->submissionType->is_gated_review,
            ] : null,
            'program'         => $submission->program ? [
                'id'   => $submission->program->id,
                'name' => $submission->program->name,
            ] : null,
            'versions'        => $submission->versions->map(fn ($v) => [
                'version_number' => $v->version_number,
                'filename'       => $v->filename,
                'submitted_at'   => $v->submitted_at,
            ])->values(),
            'review_stages'   => $stages,
            'gated_release'   => $latestRelease ? [
                'id'          => $latestRelease->id,
                'decision'    => $latestRelease->decision,
                'feedback'    => $latestRelease->feedback,
                'released_by' => $latestRelease->releasedBy?->name,
                'released_at' => $latestRelease->released_at,
            ] : null,
            'pending_gatekeeper_stage' => isset(($submission->metadata ?? [])['pending_gatekeeper_stage_id']) ? [
                'id'      => $submission->metadata['pending_gatekeeper_stage_id'],
                'name'    => $submission->metadata['pending_gatekeeper_stage_name'] ?? null,
                'outcome' => $submission->metadata['pending_gatekeeper_stage_outcome'] ?? null,
            ] : null,
            'updated_at'      => $submission->updated_at,
        ]);
    }

    // ── Issue release ─────────────────────────────────────────────────────────

    /**
     * POST /api/admin/gated-reviews
     * Issue a gated release decision. Body: { submission_id, decision, feedback? }
     * Reviewers may only act on submissions they are assigned to as gatekeeper.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'submission_id' => ['required', 'uuid'],
            'decision'      => ['required', 'in:REVISION_REQUIRED'],
            'feedback'      => ['required', 'string', 'max:5000'],
        ]);

        $user  = $request->user();
        $roles = (array) ($user->roles ?? []);
        if (!count(array_intersect($roles, ['admin', 'coordinator'])) &&
            !$this->canAccessGatedRelease($user, $roles, $data['submission_id'])) {
            return response()->json(['message' => 'Forbidden. Only the assigned gatekeeper or admin can issue a release decision.'], 403);
        }

        $submission = Submission::whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
            ->where('status', Submission::STATUS_PENDING_RELEASE)
            ->findOrFail($data['submission_id']);

        // Gatekeeper can only send consolidated feedback to the submitter.
        // Accepting / rejecting outright is not permitted — all stages must
        // pass on their own merits for auto-acceptance, or the gatekeeper
        // sends back for re-review via the recheck endpoint.
        $newStatus = Submission::STATUS_REVISION_REQUIRED;

        // Clear gatekeeper pending metadata now that a decision is being issued.
        $metadata = $submission->metadata ?? [];
        unset(
            $metadata['pending_gatekeeper_stage_id'],
            $metadata['pending_gatekeeper_stage_name'],
            $metadata['pending_gatekeeper_stage_outcome']
        );
        $submission->update(['metadata' => $metadata]);

        $release = GatedRelease::create([
            'submission_id'   => $submission->id,
            'workflow_run_id' => null,
            'version_number'  => $submission->current_version,
            'decision'        => GatedRelease::DECISION_REVISION_REQUIRED,
            'feedback'        => $data['feedback'],
            'released_by'     => $request->user()->id,
            'released_at'     => now(),
        ]);

        $oldStatus = $submission->status;
        $submission->update(['status' => $newStatus]);

        AuditLog::create([
            'submission_id' => $submission->id,
            'actor_id'      => $request->user()->id,
            'action'        => 'GATED_RELEASE_ISSUED',
            'before_state'  => ['status' => $oldStatus],
            'after_state'   => ['status' => $newStatus, 'decision' => $data['decision']],
        ]);

        app(NotificationService::class)->notifyStatusChange($submission->fresh(), $newStatus);

        return response()->json([
            'message'    => 'Release decision issued successfully.',
            'submission' => ['id' => $submission->id, 'status' => $newStatus],
            'release'    => [
                'id'          => $release->id,
                'decision'    => $release->decision,
                'feedback'    => $release->feedback,
                'released_at' => $release->released_at,
            ],
        ], 201);
    }

    // ── Recheck ───────────────────────────────────────────────────────────────

    /**
     * POST /api/admin/gated-reviews/{submissionId}/recheck
     * Gatekeeper disagrees with reviewer decisions — resets that stage's reviewer
     * decisions so reviewers can reconsider, then returns submission to IN_REVIEW.
     * Body: { reason }
     * Reviewers may only act on submissions they are assigned to as gatekeeper.
     */
    public function recheck(Request $request, string $submissionId): JsonResponse
    {
        $user  = $request->user();
        $roles = (array) ($user->roles ?? []);
        if (!count(array_intersect($roles, ['admin', 'coordinator'])) &&
            !$this->canAccessGatedRelease($user, $roles, $submissionId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $submission = Submission::whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
            ->where('status', Submission::STATUS_PENDING_RELEASE)
            ->findOrFail($submissionId);

        $metadata  = $submission->metadata ?? [];
        $stageId   = $metadata['pending_gatekeeper_stage_id']   ?? null;
        $stageName = $metadata['pending_gatekeeper_stage_name'] ?? null;

        // Reset reviewer decisions for the triggering stage so they can re-review.
        if ($stageId) {
            SubmissionReviewer::where('submission_id', $submissionId)
                ->where('stage_id', $stageId)
                ->update([
                    'decision'    => null,
                    'decision_at' => null,
                    'comments'    => null,
                    'status'      => 'pending',
                ]);

            // Re-notify the reviewers of that stage.
            $stageReviewers = SubmissionReviewer::with('user')
                ->where('submission_id', $submissionId)
                ->where('stage_id', $stageId)
                ->get();

            foreach ($stageReviewers as $sr) {
                if ($sr->user) {
                    app(NotificationService::class)->notify(
                        $sr->user,
                        \App\Models\Notification::TYPE_REVIEWER_ASSIGNED,
                        [
                            'submission_id'    => $submissionId,
                            'submission_title' => $submission->title,
                            'stage_name'       => $stageName,
                            'note'             => 'Gatekeeper has requested a re-review: ' . $data['reason'],
                        ]
                    );
                }
            }
        }

        // Record the gatekeeper recheck decision for audit history.
        GatedRelease::create([
            'submission_id'   => $submission->id,
            'workflow_run_id' => null,
            'version_number'  => $submission->current_version,
            'decision'        => GatedRelease::DECISION_STAGE_RECHECK,
            'feedback'        => $data['reason'],
            'released_by'     => $request->user()->id,
            'released_at'     => now(),
        ]);

        // Clear pending gatekeeper metadata and return to IN_REVIEW.
        unset(
            $metadata['pending_gatekeeper_stage_id'],
            $metadata['pending_gatekeeper_stage_name'],
            $metadata['pending_gatekeeper_stage_outcome']
        );

        $oldStatus = $submission->status;
        $submission->update([
            'status'   => Submission::STATUS_IN_REVIEW,
            'metadata' => $metadata,
        ]);

        AuditLog::create([
            'submission_id' => $submission->id,
            'actor_id'      => $request->user()->id,
            'action'        => 'GATED_RECHECK_REQUESTED',
            'before_state'  => ['status' => $oldStatus],
            'after_state'   => [
                'status'   => Submission::STATUS_IN_REVIEW,
                'reason'   => $data['reason'],
                'stage_id' => $stageId,
            ],
        ]);

        return response()->json(['message' => 'Stage sent back for re-review. Reviewer decisions have been reset.']);
    }

    // ── Gatekeeper-accessible submission-scoped endpoints ────────────────────

    /**
     * GET /api/submissions/{id}/gated-release
     * Full gated release detail for a single submission.
     * Accessible to admin, coordinator, and the assigned gatekeeper reviewer.
     */
    public function showForSubmission(Request $request, string $submissionId): JsonResponse
    {
        $user = $request->user();
        $roles = (array) ($user->roles ?? []);

        if (!$this->canAccessGatedRelease($user, $roles, $submissionId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $this->show($submissionId);
    }

    /**
     * POST /api/submissions/{id}/gated-release
     * Issue a gated release decision for a single submission.
     * Accessible to admin, coordinator, and the assigned gatekeeper reviewer.
     */
    public function storeForSubmission(Request $request, string $submissionId): JsonResponse
    {
        $user = $request->user();
        $roles = (array) ($user->roles ?? []);

        if (!$this->canAccessGatedRelease($user, $roles, $submissionId)) {
            return response()->json(['message' => 'Forbidden. Only the assigned gatekeeper or admin can issue a release decision.'], 403);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:REVISION_REQUIRED'],
            'feedback' => ['required', 'string', 'max:5000'],
        ]);

        $submission = Submission::whereHas('submissionType', fn ($q) => $q->where('is_gated_review', true))
            ->where('status', Submission::STATUS_PENDING_RELEASE)
            ->findOrFail($submissionId);

        $newStatus = Submission::STATUS_REVISION_REQUIRED;

        // Clear gatekeeper pending metadata.
        $metadata = $submission->metadata ?? [];
        unset(
            $metadata['pending_gatekeeper_stage_id'],
            $metadata['pending_gatekeeper_stage_name'],
            $metadata['pending_gatekeeper_stage_outcome']
        );
        $submission->update(['metadata' => $metadata]);

        $release = GatedRelease::create([
            'submission_id'   => $submission->id,
            'workflow_run_id' => null,
            'version_number'  => $submission->current_version,
            'decision'        => GatedRelease::DECISION_REVISION_REQUIRED,
            'feedback'        => $data['feedback'],
            'released_by'     => $user->id,
            'released_at'     => now(),
        ]);

        $oldStatus = $submission->status;
        $submission->update(['status' => $newStatus]);

        AuditLog::create([
            'submission_id' => $submission->id,
            'actor_id'      => $user->id,
            'action'        => 'GATED_RELEASE_ISSUED',
            'before_state'  => ['status' => $oldStatus],
            'after_state'   => ['status' => $newStatus, 'decision' => $data['decision']],
        ]);

        app(NotificationService::class)->notifyStatusChange($submission->fresh(), $newStatus);

        return response()->json([
            'message'    => 'Release decision issued successfully.',
            'submission' => ['id' => $submission->id, 'status' => $newStatus],
            'release'    => [
                'id'          => $release->id,
                'decision'    => $release->decision,
                'feedback'    => $release->feedback,
                'released_at' => $release->released_at,
            ],
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns true if the user may access the gated release endpoints
     * for the given submission (admin/coordinator or assigned gatekeeper).
     */
    private function canAccessGatedRelease(mixed $user, array $roles, string $submissionId): bool
    {
        if (count(array_intersect($roles, ['admin', 'coordinator'])) > 0) {
            return true;
        }

        return SubmissionReviewer::where('submission_id', $submissionId)
            ->where('user_id', $user->id)
            ->whereHas('stage', fn ($q) => $q->where('is_gatekeeper', true))
            ->exists();
    }
}
