<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Submission;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Evaluates review stages for a submission sequentially and advances
 * the submission status when warranted.
 *
 * ── Gated review workflow ────────────────────────────────────────────────────
 *  Stages are evaluated in order (all stages, including the gatekeeper stage).
 *  The `is_gatekeeper` flag on a stage identifies which reviewer IS the gatekeeper —
 *  it does NOT mean the stage is skipped.
 *
 *  Stage outcome → action:
 *    PENDING              → wait for remaining reviewer decisions
 *    PASSED               → advance to next stage
 *    FAILED / REVISION_REQUIRED, stage IS gatekeeper → send to submitter directly
 *                           (gatekeeper already made this decision themselves)
 *    FAILED / REVISION_REQUIRED, stage is NOT gatekeeper → route to gatekeeper
 *                           (PENDING_RELEASE); notify gatekeeper to resolve
 *
 *  All stages PASSED → auto-ACCEPTED (no further gatekeeper sign-off required)
 *
 * ── Non-gated review workflow ────────────────────────────────────────────────
 *  Same sequential evaluation; any FAILED / REVISION_REQUIRED sends directly
 *  to the submitter (REVISION_REQUIRED). No gatekeeper escalation.
 *
 * ── Revision restart ────────────────────────────────────────────────────────
 *  When transitioning to REVISION_REQUIRED, `pending_revision_stage_id` is stored
 *  in submission metadata. SubmissionController::advanceReview() reads this to
 *  apply the workflow's `revision_restart_policy` when review is re-initiated.
 */
class WorkflowAdvancer
{
    public function __construct(private StageEvaluator $evaluator) {}

    /**
     * Called after a reviewer submits a decision.
     * Evaluates stages sequentially and transitions submission status if warranted.
     */
    public function evaluateAndAdvance(Submission $submission): void
    {
        $submission->load(['submissionType.workflow.stages']);

        $stages = $submission->submissionType?->workflow?->stages ?? collect();

        if ($stages->isEmpty()) {
            return;
        }

        // Only process when the submission is actively in review.
        if (!in_array($submission->status, [
            Submission::STATUS_IN_REVIEW,
            Submission::STATUS_AWAITING_REVIEWERS,
        ])) {
            return;
        }

        $isGatedReview = (bool) ($submission->submissionType?->is_gated_review);

        // Evaluate ALL stages sequentially in ascending order.
        // The gatekeeper stage is included — it is just another review stage
        // whose reviewer happens to also be the gatekeeper for escalations.
        $evalStages = $stages->sortBy('order');

        if ($evalStages->isEmpty()) {
            return;
        }

        foreach ($evalStages as $stage) {
            $outcome = $this->evaluator->evaluate($submission->id, $stage);

            if ($outcome === 'PENDING') {
                // Stage incomplete — stop and wait for remaining reviewer decisions.
                return;
            }

            if ($outcome === 'PASSED') {
                // Stage approved — advance to next stage.
                continue;
            }

            // ── FAILED or REVISION_REQUIRED ────────────────────────────────
            $metadata = $submission->metadata ?? [];
            // Store which stage triggered the failure for the restart-policy logic.
            $metadata['pending_revision_stage_id']   = $stage->id;
            $metadata['pending_revision_stage_name'] = $stage->name;

            if ($isGatedReview && !$stage->is_gatekeeper) {
                // A non-gatekeeper stage on a gated-review submission failed.
                // Escalate to the gatekeeper — they will read all feedback and
                // decide to send consolidated feedback to the submitter or
                // send the stage back for re-review.
                $metadata['pending_gatekeeper_stage_id']      = $stage->id;
                $metadata['pending_gatekeeper_stage_name']    = $stage->name;
                $metadata['pending_gatekeeper_stage_outcome'] = $outcome;
                $submission->update(['metadata' => $metadata]);

                $action = $outcome === 'FAILED'
                    ? 'auto_pending_gatekeeper_rejection'
                    : 'auto_pending_gatekeeper_revision';

                $this->transition($submission, Submission::STATUS_PENDING_RELEASE, $action);
                $this->notifyGatekeepers($submission, $stage->name, $outcome);
            } else {
                // Either:
                //  a) The gatekeeper stage itself failed — gatekeeper already made this
                //     decision, so send the revision request directly to the submitter.
                //  b) Non-gated review — failures always go straight to the submitter.
                unset(
                    $metadata['pending_gatekeeper_stage_id'],
                    $metadata['pending_gatekeeper_stage_name'],
                    $metadata['pending_gatekeeper_stage_outcome']
                );
                $submission->update(['metadata' => $metadata]);

                $this->transition($submission, Submission::STATUS_REVISION_REQUIRED, 'auto_revision_required');
                $this->notifySubmitterRevisionRequired($submission, $stage->name, $outcome);
            }
            return;
        }

        // All stages passed → auto-advance to final state.
        $finalStatus = $submission->submissionType?->workflow?->final_status_on_pass
            ?? Submission::STATUS_ACCEPTED;
        $this->transition($submission, $finalStatus, 'auto_accepted_all_stages');
    }

    /**
     * Notify gatekeeper reviewer(s) assigned to this submission that a decision is needed.
     * Falls back to admins/coordinators if no gatekeeper reviewer is explicitly assigned.
     */
    private function notifyGatekeepers(Submission $submission, string $stageName, string $outcome): void
    {
        $gatekeeperReviewers = SubmissionReviewer::with('user')
            ->where('submission_id', $submission->id)
            ->whereHas('stage', fn ($q) => $q->where('is_gatekeeper', true))
            ->get();

        $users = $gatekeeperReviewers->map(fn ($r) => $r->user)->filter()->values();

        if ($users->isEmpty()) {
            // Fall back to admin / coordinator users.
            $users = User::where(function ($q) {
                $q->whereJsonContains('roles', 'admin')
                  ->orWhereJsonContains('roles', 'coordinator');
            })->get();
        }

        $outcomeLabel = $outcome === 'FAILED' ? 'rejected' : 'requested revision on';
        $svc = app(NotificationService::class);

        foreach ($users as $user) {
            $svc->notify($user, Notification::TYPE_STAGE_COMPLETE, [
                'submission_id'    => $submission->id,
                'submission_title' => $submission->title,
                'stage_name'       => $stageName,
                'outcome'          => $outcome,
                'note'             => "Reviewers {$outcomeLabel} stage \"{$stageName}\". Your decision is required.",
            ]);
        }
    }

    /**
     * Notify the submitter that their submission requires revision.
     * Used when:
     *  - the gatekeeper stage itself fails (gatekeeper already decided)
     *  - any stage fails on a non-gated review type
     */
    private function notifySubmitterRevisionRequired(Submission $submission, string $stageName, string $outcome): void
    {
        $submission->loadMissing('submitter');
        if (!$submission->submitter) {
            return;
        }

        $outcomeLabel = $outcome === 'FAILED' ? 'rejected' : 'requested revision on';
        app(NotificationService::class)->notify($submission->submitter, Notification::TYPE_STAGE_COMPLETE, [
            'submission_id'    => $submission->id,
            'submission_title' => $submission->title,
            'stage_name'       => $stageName,
            'outcome'          => $outcome,
            'note'             => "Reviewers {$outcomeLabel} stage \"{$stageName}\". Please revise and resubmit.",
        ]);
    }

    private function transition(Submission $submission, string $newStatus, string $action): void
    {
        $old = $submission->status;
        $submission->update(['status' => $newStatus]);

        AuditLog::create([
            'submission_id' => $submission->id,
            'actor_id'      => null,   // system action
            'action'        => strtoupper($action),
            'before_state'  => ['status' => $old],
            'after_state'   => ['status' => $newStatus],
        ]);

        // Notify the submitter of the status change
        $submission->loadMissing('submitter');
        app(NotificationService::class)->notifyStatusChange($submission, $newStatus);
    }
}
