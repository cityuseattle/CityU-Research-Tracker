<?php

namespace App\Http\Controllers;

use App\Models\AppealRequest;
use App\Models\AuditLog;
use App\Models\GatedRelease;
use App\Models\Notification;
use App\Models\Submission;
use App\Models\SubmissionReviewer;
use App\Models\SubmissionType;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Models\StageDefinition;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    // ── Submissions ───────────────────────────────────────────────────────────

    /**
     * GET /api/submissions/my-reviews
     * Returns submissions where the current user is an assigned reviewer.
     *
     * ?mode=submissions  — all submissions reviewer is part of (any stage, including future-only)
     * ?mode=assignments  — only active pending assignments (current stage, no decision yet)
     * default            — current deduplication (active or most recently completed)
     */
    public function myReviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $mode = $request->get('mode', 'default');

        // Load ALL non-declined assignments for this reviewer (across all stages).
        $allAssignments = SubmissionReviewer::with([
            'submission' => fn($q) => $q->with([
                'submitter:id,name,email',
                'submissionType:id,slug,label',
                'program:id,name',
                'currentStage:id,name,order',
            ]),
            'stage:id,name,stage_role_label,order',
        ])
        ->where('user_id', $user->id)
        ->where('status', '!=', 'declined')
        ->get();

        // ── Filter/deduplicate depending on mode ─────────────────────────────
        $chosen = collect();

        if ($mode === 'assignments') {
            // Only active pending: stage matches submission's current stage AND no decision yet
            foreach ($allAssignments->groupBy('submission_id') as $assignments) {
                $s = $assignments->first()?->submission;
                if (!$s) continue;
                $active = $assignments->first(
                    fn($a) => $a->stage_id === $s->current_stage_id && $a->decision === null
                );
                if ($active) {
                    $chosen->push($active);
                }
            }
        } elseif ($mode === 'submissions') {
            // All submissions — one per submission, prefer active stage, include future-only
            foreach ($allAssignments->groupBy('submission_id') as $assignments) {
                $s = $assignments->first()?->submission;
                if (!$s) continue;

                $active = $assignments->first(fn($a) => $a->stage_id === $s->current_stage_id);
                if ($active) { $chosen->push($active); continue; }

                $lastCompleted = $assignments
                    ->filter(fn($a) => $a->decision !== null)
                    ->sortByDesc('decision_at')
                    ->first();
                if ($lastCompleted) { $chosen->push($lastCompleted); continue; }

                // Include future-only (show first future assignment)
                $future = $assignments->sortBy(fn($a) => $a->stage?->order ?? 999)->first();
                if ($future) $chosen->push($future);
            }
        } else {
            // Default: active stage OR most recently completed; hide future-only
            foreach ($allAssignments->groupBy('submission_id') as $assignments) {
                $s = $assignments->first()?->submission;
                if (!$s) continue;

                $active = $assignments->first(fn($a) => $a->stage_id === $s->current_stage_id);
                if ($active) { $chosen->push($active); continue; }

                $lastCompleted = $assignments
                    ->filter(fn($a) => $a->decision !== null)
                    ->sortByDesc('decision_at')
                    ->first();
                if ($lastCompleted) $chosen->push($lastCompleted);
                // else: future-only — hidden
            }
        }

        // Sort: overdue first, then due-soon, then by due_at asc
        $chosen = $chosen->sortBy([
            fn($a) => $a->due_at ? 0 : 1,
            fn($a) => $a->due_at?->toDateString() ?? '9999-12-31',
        ]);

        $today = now()->toDateString();

        $data = $chosen->map(function ($a) use ($today) {
            $s = $a->submission;

            $isOverdue = $a->due_at && $a->due_at->toDateString() < $today && $a->decision === null;
            $isDueSoon = $a->due_at && !$isOverdue
                && $a->due_at->toDateString() <= now()->addDays(3)->toDateString()
                && $a->decision === null;

            return [
                'assignment_id'             => $a->id,
                'assignment_status'         => $a->status,
                'due_at'                    => $a->due_at?->toDateString(),
                'decision'                  => $a->decision,
                'decision_at'               => $a->decision_at?->toIso8601String(),
                'comments'                  => $a->comments,
                'is_overdue'                => $isOverdue,
                'is_due_soon'               => $isDueSoon,
                // Extension request fields
                'extension_status'          => $a->extension_status,
                'extension_reason'          => $a->extension_reason,
                'extension_requested_days'  => $a->extension_requested_days,
                'extension_requested_at'    => $a->extension_requested_at?->toIso8601String(),
                'extension_request_count'   => $a->extension_request_count ?? 0,
                // Conflict of interest
                'conflict_flagged'          => $a->conflict_flagged ?? false,
                'conflict_reason'           => $a->conflict_reason,
                // The specific stage this reviewer is assigned to (their review stage)
                'stage'                     => $a->stage ? [
                    'id'   => $a->stage->id,
                    'name' => $a->stage->name,
                    'role' => $a->stage->stage_role_label,
                ] : null,
                'submission' => [
                    'id'              => $s->id,
                    'title'           => $s->title,
                    'status'          => $s->status,
                    'current_version' => $s->current_version,
                    'submission_type' => $s->submissionType ? [
                        'id'    => $s->submissionType->id,
                        'slug'  => $s->submissionType->slug,
                        'label' => $s->submissionType->label,
                    ] : null,
                    'submitter' => [
                        'id'    => $s->submitter->id,
                        'name'  => $s->submitter->name,
                        'email' => $s->submitter->email,
                    ],
                    'created_at'               => $s->created_at,
                    'program'                  => $s->program ? ['name' => $s->program->name] : null,
                    'current_stage'            => $s->currentStage ? [
                        'id'   => $s->currentStage->id,
                        'name' => $s->currentStage->name,
                    ] : null,
                    'current_stage_entered_at' => $s->current_stage_entered_at?->toIso8601String(),
                ],
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/submissions/{id}/similarity
     * Returns similar submissions based on title + abstract word overlap (Jaccard similarity).
     */
    public function similarity(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        // Build word set for this submission
        $targetText = strtolower(strip_tags($submission->title . ' ' . ($submission->abstract ?? '')));
        $targetWords = array_filter(array_unique(preg_split('/\W+/', $targetText)), fn($w) => strlen($w) > 3);
        $targetSet   = array_flip($targetWords);

        if (count($targetWords) < 5) {
            return response()->json(['data' => [], 'message' => 'Insufficient text for similarity check.']);
        }

        // Compare against other submissions in the same type (excluding self)
        $candidates = Submission::where('id', '!=', $id)
            ->whereNotNull('submission_type_id')
            ->where('submission_type_id', $submission->submission_type_id)
            ->whereNotIn('status', ['DRAFT', 'WITHDRAWN'])
            ->with('submitter:id,name')
            ->limit(200)
            ->get();

        $results = [];
        foreach ($candidates as $c) {
            $cText   = strtolower(strip_tags($c->title . ' ' . ($c->abstract ?? '')));
            $cWords  = array_filter(array_unique(preg_split('/\W+/', $cText)), fn($w) => strlen($w) > 3);
            $cSet    = array_flip($cWords);

            $intersect = count(array_intersect_key($targetSet, $cSet));
            $union     = count(array_unique(array_merge(array_keys($targetSet), array_keys($cSet))));
            $score     = $union > 0 ? round(($intersect / $union) * 100, 1) : 0;

            if ($score >= 20) {
                $results[] = [
                    'submission_id'  => $c->id,
                    'title'          => $c->title,
                    'submitter'      => $c->submitter?->name,
                    'status'         => $c->status,
                    'score'          => $score,
                    'created_at'     => $c->created_at?->toDateString(),
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, 10);

        return response()->json(['data' => $results, 'total_compared' => $candidates->count()]);
    }

    /**
     * GET /api/submissions
     */
    public function index(Request $request): JsonResponse
    {        $user = $request->user();

        $query = Submission::with(['submitter:id,name,email', 'submissionType:id,slug,label', 'program:id,name', 'currentStage:id,name,order'])
            ->visibleTo($user);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('type')) {
            $query->whereHas('submissionType', fn($q) => $q->where('slug', $request->type));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn($q) => $q->where('title', 'ilike', $term)
                ->orWhere('abstract', 'ilike', $term));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $submissions->map(fn($s) => $this->toListItem($s)),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page'    => $submissions->lastPage(),
                'per_page'     => $submissions->perPage(),
                'total'        => $submissions->total(),
            ],
        ]);
    }

    /**
     * POST /api/submissions
     * Creates a new DRAFT submission.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'submission_type_id' => ['required', 'uuid', 'exists:submission_types,id'],
            'program_id'         => ['nullable', 'uuid', 'exists:programs,id'],
            'title'              => ['required', 'string', 'max:500'],
            'abstract'           => ['nullable', 'string', 'max:10000'],
            'metadata'           => ['nullable', 'array'],
        ]);

        $submission = Submission::create([
            ...$data,
            'submitter_id'    => $request->user()->id,
            'status'          => Submission::STATUS_DRAFT,
            'current_version' => 0,
            'metadata'        => $this->sanitizeMetadata($data['metadata'] ?? []),
        ]);

        $submission->load(['submitter:id,name,email', 'submissionType:id,slug,label', 'program:id,name']);

        return response()->json(['data' => $this->toDetail($submission)], 201);
    }

    /**
     * GET /api/submissions/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $submission = Submission::with([
            'submitter:id,name,email',
            'submissionType:id,slug,label,allowed_extensions,max_file_size_mb,max_files,is_gated_review',
            'program:id,name',
            'versions',
        ])->findOrFail($id);

        $this->authorize('view', $submission);

        return response()->json(['data' => $this->toDetail($submission)]);
    }

    /**
     * PATCH /api/submissions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('update', $submission);

        $data = $request->validate([
            'title'    => ['sometimes', 'string', 'max:500'],
            'abstract' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'array'],
            'program_id' => ['sometimes', 'nullable', 'uuid', 'exists:programs,id'],
        ]);

        if (isset($data['metadata'])) {
            $data['metadata'] = $this->sanitizeMetadata($data['metadata']);
        }

        $submission->update($data);
        $submission->load(['submitter:id,name,email', 'submissionType:id,slug,label', 'program:id,name']);

        return response()->json(['data' => $this->toDetail($submission)]);
    }

    /**
     * Strip reserved internal metadata keys from user-supplied metadata to
     * prevent manipulation of gated-review workflow state.
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $reserved = [
            'pending_gatekeeper_stage_id',
            'pending_gatekeeper_stage_name',
            'pending_gatekeeper_stage_outcome',
        ];
        return array_diff_key($metadata, array_flip($reserved));
    }

    /**
     * DELETE /api/submissions/{id}
     * Admin/coordinator — cancel submission.
     * Kept as DELETE for API semantics; delegates to the cancel logic.
     */
    public function destroy(string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('cancel', $submission);

        return $this->performCancel($submission);
    }

    /**
     * POST /api/submissions/{id}/cancel
     * Explicit cancel endpoint — admin/coordinator only.
     */
    public function cancel(string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('cancel', $submission);

        return $this->performCancel($submission);
    }

    private function performCancel(Submission $submission): JsonResponse
    {
        $submission->update([
            'status'           => Submission::STATUS_CANCELLED,
            'is_locked'        => true,
            'current_stage_id' => null,
        ]);

        // Notify the submitter
        if ($submission->submitter) {
            app(NotificationService::class)->notify(
                $submission->submitter,
                Notification::TYPE_SUBMISSION_SUBMITTED, // reuse generic type
                [
                    'submission_id'    => $submission->id,
                    'submission_title' => $submission->title,
                    'message'          => 'Your submission has been cancelled by an administrator.',
                ]
            );
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/submissions/{id}/submit
     * Moves a DRAFT submission to SUBMITTED.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('update', $submission);

        if ($submission->status !== Submission::STATUS_DRAFT
            && $submission->status !== Submission::STATUS_REVISION_REQUIRED) {
            return response()->json(['message' => 'Only DRAFT or REVISION_REQUIRED submissions can be submitted.'], 422);
        }

        if ($submission->versions()->count() === 0) {
            return response()->json(['message' => 'Upload at least one file before submitting.'], 422);
        }

        // ── Revision resubmission: skip admin queue if reviewers are already assigned ──
        if ($submission->status === Submission::STATUS_REVISION_REQUIRED) {
            $submission->update(['status' => Submission::STATUS_RESUBMITTED]);

            $hasReviewers = $submission->reviewers()
                ->where('status', '!=', 'declined')
                ->exists();

            if ($hasReviewers) {
                // Apply restart policy (reset decisions) and send straight to IN_REVIEW.
                $this->applyRevisionRestartPolicy($submission);
                $submission->update(['status' => Submission::STATUS_IN_REVIEW]);
                // Re-set current stage to the first stage that has active pending reviewers.
                $this->syncCurrentStageFromReviewers($submission);

                // Notify all active reviewers that a revised version is ready.
                $reviewerUsers = $submission->reviewers()
                    ->with('user:id,name,email')
                    ->where('status', '!=', 'declined')
                    ->get()
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->values();

                if ($reviewerUsers->isNotEmpty()) {
                    app(NotificationService::class)->notify(
                        $reviewerUsers->all(),
                        Notification::TYPE_REVIEWER_ASSIGNED,
                        [
                            'submission_id'    => $submission->id,
                            'submission_title' => $submission->title,
                            'message'          => 'A revised version has been submitted and is ready for your review.',
                        ]
                    );
                }
            } else {
                // No reviewers yet — notify coordinators to assign.
                $coordinators = User::whereJsonContains('roles', 'admin')
                    ->orWhereJsonContains('roles', 'coordinator')
                    ->get();
                if ($coordinators->isNotEmpty()) {
                    app(NotificationService::class)->notify(
                        $coordinators->all(),
                        Notification::TYPE_SUBMISSION_SUBMITTED,
                        [
                            'submission_id'    => $submission->id,
                            'submission_title' => $submission->title,
                        ]
                    );
                }
            }

            return response()->json(['data' => $this->toDetail($submission->fresh(['submitter', 'submissionType', 'program', 'versions']))]);
        }

        // ── Fresh submission (DRAFT → SUBMITTED) ──────────────────────────────
        $submission->update([
            'status' => Submission::STATUS_SUBMITTED,
        ]);

        // Notify admins and coordinators of the new submission
        $coordinators = User::whereJsonContains('roles', 'admin')
            ->orWhereJsonContains('roles', 'coordinator')
            ->get();
        if ($coordinators->isNotEmpty()) {
            app(NotificationService::class)->notify($coordinators->all(), Notification::TYPE_SUBMISSION_SUBMITTED, [
                'submission_id'    => $submission->id,
                'submission_title' => $submission->title,
            ]);
        }

        return response()->json(['data' => $this->toDetail($submission->fresh(['submitter', 'submissionType', 'program', 'versions']))]);
    }

    /**
     * POST /api/submissions/{id}/withdraw
     * Owner can withdraw any non-terminal submission at any time.
     * When in active review, reviewer assignments are removed and stage cleared.
     */
    public function withdraw(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('withdraw', $submission);

        $activeReviewStatuses = [
            Submission::STATUS_AWAITING_REVIEWERS,
            Submission::STATUS_IN_REVIEW,
            Submission::STATUS_RESUBMITTED,
            Submission::STATUS_PENDING_RELEASE,
        ];

        // Remove active reviewer assignments so it disappears from reviewer queues
        if (in_array($submission->status, $activeReviewStatuses)) {
            $submission->reviewers()->delete();
            $submission->update(['current_stage_id' => null]);
        }

        $submission->update([
            'status'    => Submission::STATUS_WITHDRAWN,
            'is_locked' => true,
        ]);

        // Notify coordinators/admins so they know it was pulled
        $coordinators = User::where(function ($q) {
            $q->whereJsonContains('roles', 'admin')
              ->orWhereJsonContains('roles', 'coordinator');
        })->get();

        if ($coordinators->isNotEmpty()) {
            app(NotificationService::class)->notify(
                $coordinators->all(),
                Notification::TYPE_SUBMISSION_SUBMITTED, // closest generic type
                [
                    'submission_id'    => $submission->id,
                    'submission_title' => $submission->title,
                    'message'          => 'A submission has been withdrawn by the student.',
                ]
            );
        }

        return response()->json(['data' => $this->toDetail($submission->fresh(['submitter', 'submissionType', 'program', 'versions']))]);
    }

    // ── File versions ─────────────────────────────────────────────────────────

    /**
     * GET /api/submissions/{id}/versions
     */
    public function listVersions(string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        return response()->json(['data' => $submission->versions]);
    }

    /**
     * POST /api/submissions/{id}/versions
     * Uploads one or more files and creates a new SubmissionVersion.
     */
    public function uploadVersion(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('uploadVersion', $submission);

        // Load the submission type constraints
        $type = $submission->submissionType;
        $maxMb         = $type ? $type->max_file_size_mb : 8;
        $allowedExts   = $type ? $type->allowed_extensions : ['pdf', 'docx'];
        $maxFiles      = $type ? $type->max_files : 5;

        $request->validate([
            'files'          => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*'        => [
                'file',
                'max:' . ($maxMb * 1024),
                function ($attr, $value, $fail) use ($allowedExts) {
                    // Validate by actual MIME type (server-detected), not client-supplied extension
                    $detectedMime = $value->getMimeType() ?? '';
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, $allowedExts)) {
                        $fail("File extension '.{$ext}' is not allowed. Allowed: " . implode(', ', $allowedExts));
                        return;
                    }
                    // Block dangerous MIME types regardless of extension
                    $blockedMimes = [
                        'application/x-php', 'text/x-php', 'application/php',
                        'application/x-httpd-php', 'application/x-httpd-php-source',
                        'text/html', 'application/javascript', 'text/javascript',
                        'application/x-sh', 'application/x-executable',
                    ];
                    if (in_array($detectedMime, $blockedMimes)) {
                        $fail("File type '{$detectedMime}' is not permitted.");
                    }
                },
            ],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $nextVersion = $submission->versions()->max('version_number') + 1;
        $storedPaths = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store("uploads/{$submission->id}/v{$nextVersion}");
            $storedPaths[] = $path;
        }

        $version = SubmissionVersion::create([
            'submission_id'  => $submission->id,
            'version_number' => $nextVersion,
            'document_paths' => $storedPaths,
            'change_summary' => $request->change_summary,
            'submitted_at'   => now(),
            'created_by'     => $request->user()->id,
        ]);

        // Update submission's current version number
        $submission->update(['current_version' => $nextVersion]);

        return response()->json(['data' => $version], 201);
    }

    /**
     * GET /api/submissions/{id}/files/{version}/{filename}
     * Secure file download with authorization.
     */
    public function downloadFile(Request $request, string $id, int $version, string $filename): mixed
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('downloadFile', $submission);

        // Sanitize filename to prevent path traversal attacks
        $filename = basename($filename);
        if ($filename === '' || $filename === '.') {
            return response()->json(['message' => 'Invalid filename.'], 400);
        }

        $path = "uploads/{$submission->id}/v{$version}/{$filename}";

        if (! Storage::exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::download($path, $filename);
    }

    // ── Student-facing endpoints ─────────────────────────────────────────────

    /**
     * GET /api/submissions/{id}/activity
     * Synthesised timeline of all events for this submission.
     */
    public function activityLog(Request $request, string $id): JsonResponse
    {
        $submission = Submission::with([
            'submitter:id,name',
            'versions',
            'submissionType:id,is_blind_review,is_gated_review',
            'reviewers' => function ($q) {
                $q->with(['user:id,name', 'stage:id,name'])->whereNotNull('decision_at');
            },
        ])->findOrFail($id);

        $this->authorize('view', $submission);

        $user           = $request->user();
        $roles          = (array) ($user->roles ?? []);
        $isAdminOrCoord = count(array_intersect($roles, ['admin', 'coordinator'])) > 0;
        $isGatedReview  = $submission->submissionType?->is_gated_review ?? false;

        // For gated-review submissions, determine whether the viewer should see
        // individual reviewer decisions or only the published GatedRelease event.
        $showPerReviewerDecisions = true;
        if ($isGatedReview && !$isAdminOrCoord) {
            $isGatekeeper = SubmissionReviewer::where('submission_id', $id)
                ->where('user_id', $user->id)
                ->whereHas('stage', fn ($q) => $q->where('is_gatekeeper', true))
                ->exists();

            if (!$isGatekeeper) {
                // Submitter (or any other non-privileged viewer): hide reviewer decisions
                $showPerReviewerDecisions = false;
            }
        }

        $events = collect();

        // Submission created
        $events->push([
            'id'    => 'created',
            'type'  => 'created',
            'label' => 'Submission created',
            'date'  => $submission->created_at,
            'actor' => $submission->submitter->name ?? null,
        ]);

        // Version uploads
        foreach ($submission->versions as $v) {
            $events->push([
                'id'    => 'v_' . $v->id,
                'type'  => 'version_uploaded',
                'label' => 'Version ' . $v->version_number . ' uploaded',
                'date'  => $v->submitted_at,
                'note'  => $v->change_summary,
            ]);
        }

        // Reviewer decisions (admin/coordinator/gatekeeper only for gated reviews)
        if ($showPerReviewerDecisions) {
            $isBlind = $submission->submissionType?->is_blind_review ?? false;
            foreach ($submission->reviewers as $r) {
                $decisionLabels = [
                    'approve' => 'Approved by reviewer',
                    'reject'  => 'Rejected by reviewer',
                    'revise'  => 'Revision requested',
                ];
                $events->push([
                    'id'       => 'review_' . $r->id,
                    'type'     => 'review_decision',
                    'label'    => $decisionLabels[$r->decision] ?? 'Reviewer decision made',
                    'date'     => $r->decision_at,
                    'actor'    => $isBlind ? null : $r->user?->name,
                    'stage'    => $r->stage?->name,
                    'decision' => $r->decision,
                ]);
            }
        }

        // For gated reviews visible to submitter/non-gatekeeper: surface the published release event
        if ($isGatedReview && !$showPerReviewerDecisions) {
            $release = GatedRelease::where('submission_id', $id)->latest('released_at')->first();
            if ($release) {
                $decisionLabel = match ($release->decision) {
                    'ACCEPTED'               => 'Release decision: Accepted',
                    'CONDITIONALLY_ACCEPTED' => 'Release decision: Conditionally accepted',
                    'REVISION_REQUIRED'      => 'Release decision: Revision required',
                    'REJECTED'               => 'Release decision: Rejected',
                    default                  => 'Release decision issued',
                };
                $events->push([
                    'id'    => 'gated_release_' . $release->id,
                    'type'  => 'gated_release',
                    'label' => $decisionLabel,
                    'date'  => $release->released_at,
                ]);
            }
        }

        // Audit logs specific to this submission
        $skip = ['AUTH_LOGIN', 'AUTH_LOGOUT'];
        // For gated-review non-privileged viewers, also skip reviewer-decision audit entries
        if ($isGatedReview && !$showPerReviewerDecisions) {
            $skip[] = 'REVIEWER_DECISION';
        }
        $auditLogs = AuditLog::where('submission_id', $id)
            ->whereNotIn('action', $skip)
            ->orderBy('created_at')
            ->get();
        foreach ($auditLogs as $log) {
            $events->push([
                'id'    => 'audit_' . $log->id,
                'type'  => 'system',
                'label' => ucwords(strtolower(str_replace('_', ' ', $log->action))),
                'date'  => $log->created_at,
            ]);
        }

        return response()->json([
            'data' => $events->sortBy('date')->values(),
        ]);
    }

    /**
     * GET /api/submissions/{id}/feedback
     * Reviewer decisions + comments visible to the caller.
     *
     * Visibility rules for gated-review submissions:
     *   - admin / coordinator  → see all stage reviewer decisions
     *   - gatekeeper reviewer  → see all stage reviewer decisions
     *   - submitter            → see only the latest GatedRelease feedback (not per-reviewer)
     *   - other reviewer       → see only their own stage's decisions
     *
     * Non-gated submissions return all reviewer decisions to any authorised viewer.
     */
    public function feedback(Request $request, string $id): JsonResponse
    {
        $submission = Submission::with('submissionType:id,is_blind_review,is_gated_review')->findOrFail($id);
        $this->authorize('view', $submission);

        $user           = $request->user();
        $roles          = (array) ($user->roles ?? []);
        $isAdminOrCoord = count(array_intersect($roles, ['admin', 'coordinator'])) > 0;
        $isBlind        = $submission->submissionType?->is_blind_review ?? false;
        $isGatedReview  = $submission->submissionType?->is_gated_review ?? false;

        $appeal = AppealRequest::where('submission_id', $id)->latest()->first();
        $appealData = $appeal ? [
            'id'              => $appeal->id,
            'status'          => $appeal->status,
            'grounds'         => $appeal->grounds,
            'resolution_note' => $appeal->resolution_note,
            'created_at'      => $appeal->created_at,
        ] : null;

        // ── Gated-review visibility rules ────────────────────────────────────
        if ($isGatedReview && !$isAdminOrCoord) {
            $isGatekeeper = SubmissionReviewer::where('submission_id', $id)
                ->where('user_id', $user->id)
                ->whereHas('stage', fn ($q) => $q->where('is_gatekeeper', true))
                ->exists();

            if (!$isGatekeeper) {
                // Submitter: show only the published GatedRelease feedback
                if ($submission->submitter_id === $user->id) {
                    $latestRelease = GatedRelease::where('submission_id', $id)
                        ->latest('released_at')
                        ->first();

                    $feedbackItems = $latestRelease ? [[
                        'id'               => $latestRelease->id,
                        'stage'            => null,
                        'decision'         => $latestRelease->decision,
                        'decision_at'      => $latestRelease->released_at,
                        'comments'         => $latestRelease->feedback,
                        'reviewer'         => null,
                        'is_gated_release' => true,
                    ]] : [];

                    return response()->json(['data' => $feedbackItems, 'appeal' => $appealData]);
                }

                // Non-gatekeeper reviewer: show only decisions from their own stage
                $reviewers = $submission->reviewers()
                    ->with(['user:id,name', 'stage:id,name,stage_role_label'])
                    ->where('stage_id', function ($sub) use ($id, $user) {
                        $sub->select('stage_id')
                            ->from('submission_reviewers')
                            ->where('submission_id', $id)
                            ->where('user_id', $user->id)
                            ->limit(1);
                    })
                    ->whereNotNull('decision')
                    ->orderBy('decision_at')
                    ->get();

                $feedbackItems = $reviewers->map(fn ($r) => [
                    'id'          => $r->id,
                    'stage'       => $r->stage ? [
                        'id'   => $r->stage->id,
                        'name' => $r->stage->name,
                        'role' => $r->stage->stage_role_label,
                    ] : null,
                    'decision'    => $r->decision,
                    'decision_at' => $r->decision_at,
                    'comments'    => $r->comments,
                    'reviewer'    => $isBlind ? null : ($r->user ? ['name' => $r->user->name] : null),
                ]);

                return response()->json(['data' => $feedbackItems->values(), 'appeal' => $appealData]);
            }
            // Gatekeeper falls through to the full view below
        }

        // ── Default: admin, coordinator, gatekeeper, or non-gated review ─────
        $reviewers = $submission->reviewers()
            ->with(['user:id,name', 'stage:id,name,stage_role_label'])
            ->whereNotNull('decision')
            ->orderBy('decision_at')
            ->get();

        $feedbackItems = $reviewers->map(fn ($r) => [
            'id'          => $r->id,
            'stage'       => $r->stage ? [
                'id'   => $r->stage->id,
                'name' => $r->stage->name,
                'role' => $r->stage->stage_role_label,
            ] : null,
            'decision'    => $r->decision,
            'decision_at' => $r->decision_at,
            'comments'    => $r->comments,
            'reviewer'    => $isBlind ? null : ($r->user ? ['name' => $r->user->name] : null),
        ]);

        return response()->json(['data' => $feedbackItems->values(), 'appeal' => $appealData]);
    }

    /**
     * POST /api/submissions/{id}/appeal
     * Submit an appeal for a rejected submission.
     */
    public function submitAppeal(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        // Only the original submitter can file an appeal
        if ($submission->submitter_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the submission author can file an appeal.'], 403);
        }

        if ($submission->status !== Submission::STATUS_REJECTED) {
            return response()->json(['message' => 'Only rejected submissions can be appealed.'], 422);
        }

        $existing = AppealRequest::where('submission_id', $id)
            ->whereIn('status', [AppealRequest::STATUS_PENDING, AppealRequest::STATUS_UNDER_REVIEW])
            ->exists();
        if ($existing) {
            return response()->json(['message' => 'An appeal is already pending.'], 422);
        }

        $data = $request->validate([
            'grounds' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $appeal = AppealRequest::create([
            'submission_id' => $id,
            'submitter_id'  => Auth::id(),
            'grounds'       => $data['grounds'],
            'status'        => AppealRequest::STATUS_PENDING,
        ]);

        // Mark submission as appeal-pending so it surfaces in appeals queue
        $submission->update(['status' => Submission::STATUS_APPEAL_PENDING]);

        return response()->json([
            'data' => [
                'id'         => $appeal->id,
                'status'     => $appeal->status,
                'grounds'    => $appeal->grounds,
                'created_at' => $appeal->created_at,
            ],
        ], 201);
    }

    /**
     * GET /api/submissions/{id}/review-progress
     * Returns workflow stage progress with reviewer assignment status.
     */
    public function reviewProgress(string $id): JsonResponse
    {
        $submission = Submission::with([
            'submissionType.workflow.stages',
            'reviewers.user',
        ])->findOrFail($id);

        $this->authorize('view', $submission);

        $stages = $submission->submissionType?->workflow?->stages ?? collect();

        $isBlind       = $submission->submissionType?->is_blind_review ?? false;
        $isGatedReview = $submission->submissionType?->is_gated_review ?? false;
        $allowMeetings = $submission->submissionType?->allow_meetings ?? false;

        // Meetings are never available for blind reviews
        $meetingsEnabled = $allowMeetings && !$isBlind;

        if ($stages->isEmpty()) {
            return response()->json([
                'data'                => [],
                'is_blind'            => $isBlind,
                'is_gated_review'     => $isGatedReview,
                'allow_meetings'      => $meetingsEnabled,
                'user_meeting_context' => null,
            ]);
        }

        // Admins/coordinators always see reviewer names even in blind reviews
        $requestingUser = request()->user();
        $isAdminOrCoord = $requestingUser->hasAnyRole(['admin', 'coordinator']);
        $showReviewers  = !$isBlind || $isAdminOrCoord;

        $reviewers = $submission->reviewers;

        $isActive = in_array($submission->status, [
            Submission::STATUS_AWAITING_REVIEWERS,
            Submission::STATUS_IN_REVIEW,
            Submission::STATUS_REVISION_REQUIRED,
        ]);

        $isComplete = in_array($submission->status, [
            Submission::STATUS_ACCEPTED,
            Submission::STATUS_CONDITIONALLY_ACCEPTED,
            Submission::STATUS_REJECTED,
        ]);

        $stageData = $stages->map(function ($stage) use ($reviewers, $isActive, $isComplete, $showReviewers, $submission) {
            $stageReviewers = $reviewers->where('stage_id', $stage->id);
            $total     = $stageReviewers->count();
            $completed = $stageReviewers->filter(fn($r) => $r->decision !== null)->count();

            if ($isComplete) {
                $status = 'completed';
            } elseif ($total === 0) {
                $status = $isActive ? 'needs_assignment' : 'pending';
            } elseif ($completed === $total) {
                $status = 'completed';
            } elseif ($completed > 0) {
                $status = 'in_progress';
            } else {
                $status = 'assigned';
            }

            $decisions = $stageReviewers->filter(fn($r) => $r->decision !== null)->pluck('decision');
            $outcome = null;
            if ($decisions->isNotEmpty()) {
                if ($decisions->contains('reject')) {
                    $outcome = 'rejected';
                } elseif ($decisions->contains('revise')) {
                    $outcome = 'revision';
                } elseif ($decisions->every(fn($d) => $d === 'approve')) {
                    $outcome = 'approved';
                }
            }

            // Compute effective stage due date:
            // 1) Use the earliest explicit reviewer due_at if any are set
            // 2) Fallback: use earliest assigned_at + stage.due_days
            $stageDueAt = $stageReviewers
                ->filter(fn($r) => $r->due_at !== null)
                ->min(fn($r) => $r->due_at)
                ?->toDateString();

            if ($stageDueAt === null && $stage->due_days) {
                // Prefer the recorded stage-entry timestamp for accuracy.
                if ($stage->id === $submission->current_stage_id && $submission->current_stage_entered_at) {
                    $stageDueAt = $submission->current_stage_entered_at->copy()->addDays($stage->due_days)->toDateString();
                } elseif ($stageReviewers->isNotEmpty()) {
                    // Legacy fallback for records predating this fix: use earliest assigned_at.
                    $earliest = $stageReviewers->min(fn($r) => $r->assigned_at);
                    if ($earliest) {
                        $stageDueAt = $earliest->copy()->addDays($stage->due_days)->toDateString();
                    }
                }
            }

            // Per-reviewer effective due date (for display in panel)
            $reviewerList = $showReviewers
                ? $stageReviewers->map(function ($r) use ($stage) {
                    $effectiveDue = $r->due_at?->toDateString()
                        ?? ($stage->due_days
                            ? $r->assigned_at->copy()->addDays($stage->due_days)->toDateString()
                            : null);
                    return [
                        'id'     => $r->id,
                        'name'   => $r->user?->name,
                        'status' => $r->status,
                        'due_at' => $effectiveDue,
                    ];
                })->values()
                : null;

            return [
                'id'              => $stage->id,
                'name'            => $stage->name,
                'order'           => $stage->order,
                'role_label'      => $stage->stage_role_label,
                'is_gatekeeper'   => (bool) $stage->is_gatekeeper,
                'reviewers_count' => $total,
                'completed_count' => $completed,
                'status'          => $status,
                'outcome'         => $outcome,
                'min_approvals'   => $stage->min_approvals,
                'due_days'        => $stage->due_days ?: null,
                'stage_due_at'    => $stageDueAt,
                'reviewers'       => $reviewerList,
            ];
        });

        // Compute per-user meeting context
        $isSubmitter      = $submission->submitter_id === $requestingUser->id;
        $myAssignment     = $reviewers->where('user_id', $requestingUser->id)->first();
        $myStage          = $myAssignment ? $stages->firstWhere('id', $myAssignment->stage_id) : null;
        $isGatekeeperUser = (bool) ($myStage?->is_gatekeeper ?? false);
        $isReviewerUser   = $myAssignment !== null;
        $gatekeeperStage  = $stages->firstWhere('is_gatekeeper', true);

        $userMeetingContext = [
            'is_submitter'       => $isSubmitter,
            'is_reviewer'        => $isReviewerUser && !$isGatekeeperUser,
            'is_gatekeeper'      => $isGatekeeperUser,
            'reviewer_stage_id'  => ($isReviewerUser && !$isGatekeeperUser) ? $myAssignment->stage_id : null,
            'gatekeeper_stage_id' => $gatekeeperStage?->id,
        ];

        return response()->json([
            'data'                => $stageData->values(),
            'is_blind'            => $isBlind,
            'is_gated_review'     => $isGatedReview,
            'allow_meetings'      => $meetingsEnabled,
            'user_meeting_context' => $userMeetingContext,
        ]);
    }

    /**
     * POST /api/submissions/{id}/advance-review
     * Admin/coordinator: SUBMITTED → AWAITING_REVIEWERS, or AWAITING_REVIEWERS → IN_REVIEW.
     */
    public function advanceReview(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $roles = $request->user()->roles ?? [];

        if (!in_array('admin', $roles) && !in_array('coordinator', $roles)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // SUBMITTED or RESUBMITTED → move to AWAITING_REVIEWERS
        if ($submission->status === Submission::STATUS_SUBMITTED
            || $submission->status === Submission::STATUS_RESUBMITTED) {
            $submission->update([
                'status'           => Submission::STATUS_AWAITING_REVIEWERS,
                // Clear current stage when entering the awaiting queue; a stage
                // becomes 'current' as soon as the first reviewer is assigned.
                'current_stage_id'         => null,
                'current_stage_entered_at' => null,
            ]);
        } elseif ($submission->status === Submission::STATUS_AWAITING_REVIEWERS) {
            if (!$submission->reviewers()->exists()) {
                return response()->json([
                    'message' => 'Assign at least one reviewer before starting the review.',
                ], 422);
            }

            // Apply revision restart policy when re-initiating review after a revision cycle.
            $hasPreviousDecisions = $submission->reviewers()->whereNotNull('decision')->exists();
            if ($hasPreviousDecisions) {
                $this->applyRevisionRestartPolicy($submission);
            }

            $submission->update(['status' => Submission::STATUS_IN_REVIEW]);
            // Ensure current stage reflects the first pending reviewer stage.
            $this->syncCurrentStageFromReviewers($submission);
        } else {
            return response()->json([
                'message' => 'Submission cannot be advanced from its current status.',
            ], 422);
        }

        return response()->json([
            'data' => $this->toDetail($submission->fresh(['submitter', 'submissionType', 'program', 'versions'])),
        ]);
    }

    /**
     * Apply the configured revision restart policy for a submission that is
     * re-entering review after a REVISION_REQUIRED cycle.
     *
     * Resets reviewer decisions according to the workflow policy and clears
     * all revision/gatekeeper metadata from the submission.
     */
    private function applyRevisionRestartPolicy(Submission $submission): void
    {
        $submission->load('submissionType.workflow');
        $policy   = $submission->submissionType?->workflow?->revision_restart_policy ?? 'FULL_RESTART';
        $metadata = $submission->metadata ?? [];

        if ($policy === 'RESUME_FROM_STAGE' && !empty($metadata['pending_revision_stage_id'])) {
            // Reset only the failed stage and all subsequent stages.
            $failedStage = StageDefinition::find($metadata['pending_revision_stage_id']);
            if ($failedStage) {
                $stageIdsToReset = StageDefinition::where('workflow_id', $failedStage->workflow_id)
                    ->where('order', '>=', $failedStage->order)
                    ->pluck('id');
                SubmissionReviewer::where('submission_id', $submission->id)
                    ->whereIn('stage_id', $stageIdsToReset)
                    ->update(['decision' => null, 'decision_at' => null, 'comments' => null, 'status' => 'pending']);
            }
        } else {
            // FULL_RESTART (default): reset all reviewer decisions.
            SubmissionReviewer::where('submission_id', $submission->id)
                ->update(['decision' => null, 'decision_at' => null, 'comments' => null, 'status' => 'pending']);
        }

        // Clear all revision and gatekeeper metadata from the previous cycle.
        // Also reset current stage — it will be re-set when reviewers are re-assigned.
        unset(
            $metadata['pending_revision_stage_id'],
            $metadata['pending_revision_stage_name'],
            $metadata['pending_gatekeeper_stage_id'],
            $metadata['pending_gatekeeper_stage_name'],
            $metadata['pending_gatekeeper_stage_outcome']
        );
        $submission->update([
            'metadata'                 => $metadata,
            'current_stage_id'         => null,
            'current_stage_entered_at' => null,
        ]);
    }

    /**
     * Set current_stage_id / current_stage_entered_at on a submission to reflect
     * the lowest-order stage that currently has at least one active reviewer with
     * a pending decision.  Called after status changes to IN_REVIEW.
     */
    private function syncCurrentStageFromReviewers(Submission $submission): void
    {
        $submission->load('reviewers.stage');

        $firstPending = $submission->reviewers
            ->filter(fn($r) => $r->status !== 'declined' && $r->decision === null)
            ->filter(fn($r) => $r->stage !== null)
            ->sortBy(fn($r) => $r->stage->order)
            ->first();

        if ($firstPending) {
            // Only update entered_at when the stage actually changes.
            if ($submission->current_stage_id !== $firstPending->stage_id) {
                $submission->update([
                    'current_stage_id'         => $firstPending->stage_id,
                    'current_stage_entered_at' => now(),
                ]);
            }
        } else {
            $submission->update([
                'current_stage_id'         => null,
                'current_stage_entered_at' => null,
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function toListItem(Submission $s): array
    {
        return [
            'id'              => $s->id,
            'title'           => $s->title,
            'status'          => $s->status,
            'current_version' => $s->current_version,
            'submission_type' => $s->submissionType ? [
                'id'    => $s->submissionType->id,
                'slug'  => $s->submissionType->slug,
                'label' => $s->submissionType->label,
            ] : null,
            'program'         => $s->program ? [
                'id'   => $s->program->id,
                'name' => $s->program->name,
            ] : null,
            'submitter'       => [
                'id'    => $s->submitter->id,
                'name'  => $s->submitter->name,
                'email' => $s->submitter->email,
            ],
            'created_at'           => $s->created_at,
            'updated_at'           => $s->updated_at,
            'current_stage'        => $s->currentStage ? [
                'id'   => $s->currentStage->id,
                'name' => $s->currentStage->name,
            ] : null,
            'current_stage_entered_at' => $s->current_stage_entered_at?->toIso8601String(),
        ];
    }

    private function toDetail(Submission $s): array
    {
        $base = $this->toListItem($s);
        $base['abstract']  = $s->abstract;
        $base['metadata']  = $s->metadata;
        $base['is_locked'] = $s->is_locked;

        // Expose is_gated_review so the frontend knows to apply gated-review UI rules
        if (isset($base['submission_type']) && $base['submission_type'] && $s->submissionType) {
            $base['submission_type']['is_gated_review'] = $s->submissionType->is_gated_review ?? false;
        }

        $base['versions']  = $s->relationLoaded('versions')
            ? $s->versions->map(fn($v) => [
                'id'             => $v->id,
                'version_number' => $v->version_number,
                'document_paths' => $v->document_paths,
                'change_summary' => $v->change_summary,
                'submitted_at'   => $v->submitted_at,
                'file_count'     => count($v->document_paths),
            ])->values()
            : [];

        return $base;
    }

    /**
     * GET /api/calendar/deadlines
     * Returns deadline events for the current user.
     * Students → deadlines from their own submission's reviewer assignments.
     * Reviewers → their own review deadlines.
     * Admins/Coordinators → all deadlines across submissions.
     */
    public function calendarDeadlines(Request $request): JsonResponse
    {
        $user  = $request->user();
        $roles = (array) ($user->roles ?? []);

        $events = collect();

        // Helper: compute effective due date for a reviewer assignment
        $effectiveDate = function ($a) {
            if ($a->due_at) return $a->due_at->toDateString();
            if ($a->stage?->due_days && $a->assigned_at) {
                return $a->assigned_at->copy()->addDays($a->stage->due_days)->toDateString();
            }
            return null;
        };

        // Reviewer (and admin/coordinator) → their own review assignments
        if (array_intersect($roles, ['reviewer', 'admin', 'coordinator'])) {
            $adminOrCoord = array_intersect($roles, ['admin', 'coordinator']);

            $reviewerQuery = SubmissionReviewer::with([
                'submission:id,title,status',
                'stage:id,name,due_days',
            ])
            ->where('status', '!=', 'declined');

            if (!$adminOrCoord) {
                $reviewerQuery->where('user_id', $user->id);
            } else {
                $reviewerQuery->with('user:id,name');
            }

            $assignments = $reviewerQuery->get();

            $assignments->each(function ($a) use ($events, $adminOrCoord, $effectiveDate) {
                if (!$a->submission) return;
                $date = $effectiveDate($a);
                if (!$date) return;
                $events->push([
                    'type'          => 'review_due',
                    'date'          => $date,
                    'submission_id' => $a->submission_id,
                    'title'         => $a->submission->title,
                    'stage'         => $a->stage?->name,
                    'reviewer'      => $adminOrCoord ? ($a->user?->name ?? '—') : null,
                    'is_completed'  => $a->decision !== null,
                ]);
            });

            // Stage-level aggregate events (admin/coordinator only)
            if ($adminOrCoord) {
                $assignments->groupBy(fn($a) => $a->submission_id . ':' . $a->stage_id)
                    ->each(function ($group) use ($events, $effectiveDate) {
                        $first = $group->first();
                        if (!$first->submission) return;

                        $stageDates = $group->map(fn($a) => $effectiveDate($a))->filter();
                        if ($stageDates->isEmpty()) return;

                        $stageMaxDate = $stageDates->max();
                        $allCompleted = $group->every(fn($a) => $a->decision !== null);

                        // For completed stages, use the actual last decision_at
                        if ($allCompleted) {
                            $lastDecision = $group
                                ->filter(fn($a) => $a->decision_at !== null)
                                ->max(fn($a) => $a->decision_at);
                            if ($lastDecision) {
                                $stageMaxDate = $lastDecision->toDateString();
                            }
                        }

                        $events->push([
                            'type'          => 'stage_complete',
                            'date'          => $stageMaxDate,
                            'submission_id' => $first->submission_id,
                            'title'         => $first->submission->title,
                            'stage'         => $first->stage?->name,
                            'reviewer'      => null,
                            'is_completed'  => $allCompleted,
                        ]);
                    });
            }
        }

        // Student (submitter) → deadlines on their own submissions' reviewer assignments
        if (in_array('student', $roles)) {
            $submittedIds = Submission::where('submitter_id', $user->id)->pluck('id');
            $studentAssignments = SubmissionReviewer::with('submission:id,title', 'stage:id,name,due_days')
                ->whereIn('submission_id', $submittedIds)
                ->where('status', '!=', 'declined')
                ->get();

            // Stage-level events for students (they see when each stage is expected to complete)
            $studentAssignments->groupBy(fn($a) => $a->submission_id . ':' . $a->stage_id)
                ->each(function ($group) use ($events, $effectiveDate) {
                    $first = $group->first();
                    if (!$first->submission) return;

                    $stageDates = $group->map(fn($a) => $effectiveDate($a))->filter();
                    if ($stageDates->isEmpty()) return;

                    $stageMaxDate = $stageDates->max();
                    $allCompleted = $group->every(fn($a) => $a->decision !== null);

                    if ($allCompleted) {
                        $lastDecision = $group
                            ->filter(fn($a) => $a->decision_at !== null)
                            ->max(fn($a) => $a->decision_at);
                        if ($lastDecision) {
                            $stageMaxDate = $lastDecision->toDateString();
                        }
                    }

                    $events->push([
                        'type'          => 'stage_complete',
                        'date'          => $stageMaxDate,
                        'submission_id' => $first->submission_id,
                        'title'         => $first->submission->title,
                        'stage'         => ($first->stage?->name ?? 'Review') . ' stage deadline',
                        'reviewer'      => null,
                        'is_completed'  => $allCompleted,
                    ]);
                });
        }

        // Deduplicate and sort
        $sorted = $events->sortBy('date')->values();

        return response()->json(['data' => $sorted]);
    }
}
