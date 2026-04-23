<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubmissionMeetingController extends Controller
{
    /**
     * GET /api/submissions/{id}/meetings
     * List all meetings for a submission. Optionally filter by ?stage_id=X.
     */
    public function index(Request $request, string $submissionId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('view', $submission);

        $query = SubmissionMeeting::with(['requester:id,name,email', 'stage:id,name'])
            ->where('submission_id', $submissionId);

        if ($request->filled('stage_id')) {
            $query->where('stage_id', $request->stage_id);
        }

        $meetings = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $meetings->map(fn($m) => $this->toResource($m))]);
    }

    /**
     * POST /api/submissions/{id}/meetings
     *
     * Meeting scheduling rules:
     *  - Blind review      → never allowed (regardless of allow_meetings)
     *  - allow_meetings=false → not allowed
     *  - Gated review (non-blind):
     *      submitter    → student_gatekeeper  (meet with gatekeeper)
     *      gatekeeper   → gatekeeper_student  (own stage) OR gatekeeper_reviewers (other stage)
     *      reviewer     → reviewer_reviewer   (must supply stage_id = own stage)
     *      admin/coord  → any type, no restriction
     *  - Non-gated review: any authenticated viewer may request (meeting_type = null)
     */
    public function store(Request $request, string $submissionId): JsonResponse
    {
        $submission = Submission::with([
            'submissionType.workflow.stages',
            'reviewers',
        ])->findOrFail($submissionId);

        $this->authorize('view', $submission);

        $isBlind       = $submission->submissionType?->is_blind_review ?? false;
        $isGated       = $submission->submissionType?->is_gated_review ?? false;
        $allowMeetings = $submission->submissionType?->allow_meetings ?? false;

        if (!$allowMeetings) {
            return response()->json(['message' => 'Meeting scheduling is not enabled for this submission type.'], 403);
        }

        if ($isBlind) {
            return response()->json(['message' => 'Meeting scheduling is not available for blind reviews.'], 403);
        }

        $user           = $request->user();
        $isAdminOrCoord = $user->hasAnyRole(['admin', 'coordinator']);
        $stages         = $submission->submissionType?->workflow?->stages ?? collect();

        $isSubmitter      = $submission->submitter_id === $user->id;
        $myAssignment     = $submission->reviewers->where('user_id', $user->id)->first();
        $myStage          = $myAssignment ? $stages->firstWhere('id', $myAssignment->stage_id) : null;
        $isGatekeeperUser = (bool) ($myStage?->is_gatekeeper ?? false);
        $isReviewer       = $myAssignment !== null;
        $gatekeeperStage  = $stages->firstWhere('is_gatekeeper', true);

        if ($isGated && !$isAdminOrCoord) {
            // Determine allowed meeting types for this user
            if ($isSubmitter) {
                $allowedTypes = ['student_gatekeeper'];
            } elseif ($isGatekeeperUser) {
                $allowedTypes = ['gatekeeper_student', 'gatekeeper_reviewers'];
            } elseif ($isReviewer) {
                $allowedTypes = ['reviewer_reviewer'];
            } else {
                return response()->json(['message' => 'You are not a participant of this submission.'], 403);
            }

            $data = $request->validate([
                'meeting_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
                'stage_id'     => ['nullable', 'uuid', 'exists:stage_definitions,id'],
                'title'        => ['required', 'string', 'max:255'],
                'description'  => ['nullable', 'string', 'max:5000'],
                'proposed_at'  => ['nullable', 'date'],
            ]);

            // Resolve stage_id from meeting type
            $meetingType = $data['meeting_type'];
            if ($meetingType === 'student_gatekeeper') {
                $data['stage_id'] = $gatekeeperStage?->id;
            } elseif ($meetingType === 'gatekeeper_student') {
                $data['stage_id'] = $gatekeeperStage?->id;
            } elseif ($meetingType === 'gatekeeper_reviewers') {
                if (empty($data['stage_id'])) {
                    return response()->json(['message' => 'stage_id is required when scheduling a meeting with reviewers.'], 422);
                }
                // Verify stage is not the gatekeeper stage itself
                if ($data['stage_id'] === $gatekeeperStage?->id) {
                    return response()->json(['message' => 'Use gatekeeper_student type to meet with the submitter, not a reviewer stage.'], 422);
                }
            } elseif ($meetingType === 'reviewer_reviewer') {
                // Force stage to the reviewer's own stage
                $data['stage_id'] = $myAssignment->stage_id;
            }
        } else {
            // Non-gated review or admin: plain validation, no type enforcement
            $data = $request->validate([
                'stage_id'    => ['nullable', 'uuid', 'exists:stage_definitions,id'],
                'title'       => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'proposed_at' => ['nullable', 'date'],
            ]);
            $data['meeting_type'] = null;
        }

        $meeting = SubmissionMeeting::create([
            'id'           => Str::uuid()->toString(),
            'submission_id' => $submissionId,
            'stage_id'     => $data['stage_id'] ?? null,
            'meeting_type' => $data['meeting_type'] ?? null,
            'requested_by' => $user->id,
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'proposed_at'  => $data['proposed_at'] ?? null,
            'status'       => 'requested',
        ]);

        $meeting->load(['requester:id,name,email', 'stage:id,name']);

        return response()->json(['data' => $this->toResource($meeting)], 201);
    }

    /**
     * PATCH /api/submissions/{id}/meetings/{meetingId}
     * Confirm, cancel, or complete a meeting. Admin/coordinator only.
     */
    public function update(Request $request, string $submissionId, string $meetingId): JsonResponse
    {
        $meeting = SubmissionMeeting::where('submission_id', $submissionId)
            ->where('id', $meetingId)
            ->firstOrFail();

        $data = $request->validate([
            'status'       => ['sometimes', 'in:confirmed,cancelled,completed'],
            'confirmed_at' => ['sometimes', 'nullable', 'date'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:500'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $meeting->update($data);
        $meeting->load(['requester:id,name,email', 'stage:id,name']);

        return response()->json(['data' => $this->toResource($meeting)]);
    }

    /**
     * DELETE /api/submissions/{id}/meetings/{meetingId}
     * Cancel/delete a meeting. Requester or admin/coordinator.
     */
    public function destroy(Request $request, string $submissionId, string $meetingId): JsonResponse
    {
        $meeting = SubmissionMeeting::where('submission_id', $submissionId)
            ->where('id', $meetingId)
            ->firstOrFail();

        $user = $request->user();
        if ($meeting->requested_by !== $user->id && !$user->hasAnyRole(['admin', 'coordinator'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $meeting->delete();

        return response()->json(null, 204);
    }

    private function toResource(SubmissionMeeting $m): array
    {
        return [
            'id'            => $m->id,
            'submission_id' => $m->submission_id,
            'stage_id'      => $m->stage_id,
            'meeting_type'  => $m->meeting_type,
            'stage'         => $m->stage ? ['id' => $m->stage->id, 'name' => $m->stage->name] : null,
            'requested_by'  => $m->requested_by,
            'requester'     => $m->requester
                ? ['id' => $m->requester->id, 'name' => $m->requester->name, 'email' => $m->requester->email]
                : null,
            'title'         => $m->title,
            'description'   => $m->description,
            'proposed_at'   => $m->proposed_at?->toISOString(),
            'status'        => $m->status,
            'confirmed_at'  => $m->confirmed_at?->toISOString(),
            'meeting_link'  => $m->meeting_link,
            'notes'         => $m->notes,
            'created_at'    => $m->created_at->toISOString(),
        ];
    }
}

