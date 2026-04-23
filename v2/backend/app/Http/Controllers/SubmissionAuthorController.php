<?php

namespace App\Http\Controllers;

use App\Models\PasswordPolicy;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmissionAuthorController extends Controller
{
    /**
     * GET /api/submissions/{submissionId}/authors
     */
    public function index(string $submissionId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('view', $submission);

        $authors = SubmissionAuthor::where('submission_id', $submissionId)
            ->orderBy('author_order')
            ->get();

        return response()->json(['data' => $authors->map(fn($a) => $this->toResource($a))]);
    }

    /**
     * POST /api/submissions/{submissionId}/authors
     *
     * Add a co-author by email. If the email matches an existing user,
     * the author record is linked immediately. Otherwise an invite token
     * is generated so the person can register and claim their authorship.
     */
    public function store(Request $request, string $submissionId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('update', $submission);

        $data = $request->validate([
            'email'       => ['required', 'email', 'max:255'],
            'name'        => ['required', 'string', 'max:255'],
            'affiliation' => ['nullable', 'string', 'max:500'],
        ]);

        $email = strtolower(trim($data['email']));

        // Prevent duplicate
        if (SubmissionAuthor::where('submission_id', $submissionId)->where('email', $email)->exists()) {
            return response()->json(['message' => 'This author is already on this submission.'], 422);
        }

        // Determine the next order position
        $nextOrder = (SubmissionAuthor::where('submission_id', $submissionId)->max('author_order') ?? 0) + 1;

        // Check if a user account already exists
        $existingUser = User::where('email', $email)->first();

        $author = SubmissionAuthor::create([
            'id'            => Str::uuid()->toString(),
            'submission_id' => $submissionId,
            'user_id'       => $existingUser?->id,
            'name'          => $data['name'],
            'email'         => $email,
            'affiliation'   => $data['affiliation'] ?? null,
            'is_corresponding' => false,
            'author_order'  => $nextOrder,
            'invite_token'  => $existingUser ? null : Str::random(64),
            'invited_at'    => $existingUser ? null : now(),
        ]);

        // TODO: dispatch SendAuthorInviteEmail job when mail is configured
        // if (!$existingUser) {
        //     Mail::to($email)->send(new AuthorInviteMail($author, $submission));
        // }

        return response()->json(['data' => $this->toResource($author)], 201);
    }

    /**
     * DELETE /api/submissions/{submissionId}/authors/{authorId}
     *
     * Cannot remove the corresponding author.
     */
    public function destroy(string $submissionId, string $authorId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('update', $submission);

        $author = SubmissionAuthor::where('submission_id', $submissionId)
            ->where('id', $authorId)
            ->firstOrFail();

        if ($author->is_corresponding) {
            return response()->json(['message' => 'Cannot remove the corresponding author.'], 422);
        }

        $author->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/submissions/{submissionId}/authors/{authorId}/resend-invite
     *
     * Regenerates and resends the invite for a pending (non-user) author.
     */
    public function resendInvite(string $submissionId, string $authorId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('update', $submission);

        $author = SubmissionAuthor::where('submission_id', $submissionId)
            ->where('id', $authorId)
            ->firstOrFail();

        if ($author->user_id) {
            return response()->json(['message' => 'This author has already joined.'], 422);
        }

        $author->update([
            'invite_token' => Str::random(64),
            'invited_at'   => now(),
        ]);

        // TODO: dispatch SendAuthorInviteEmail job

        return response()->json(['data' => $this->toResource($author)]);
    }

    /**
     * POST /api/auth/accept-author-invite
     *
     * Public endpoint. An invited co-author submits their token + account details
     * to register and claim their authorship.
     */
    public function acceptInvite(Request $request): JsonResponse
    {
        $policy = PasswordPolicy::find(1);
        $passwordRules = ['required', 'string', 'confirmed'];
        if ($policy) {
            $pass = \Illuminate\Validation\Rules\Password::min($policy->min_length ?? 8);
            if ($policy->require_uppercase) $pass = $pass->mixedCase();
            if ($policy->require_number)    $pass = $pass->numbers();
            if ($policy->require_special)   $pass = $pass->symbols();
            $passwordRules[] = $pass;
        } else {
            $passwordRules[] = 'min:8';
        }

        $data = $request->validate([
            'token'      => ['required', 'string', 'size:64'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'password'   => $passwordRules,
        ]);

        $author = SubmissionAuthor::where('invite_token', $data['token'])
            ->whereNull('joined_at')
            ->where('invited_at', '>=', now()->subDays(30))
            ->first();

        if (!$author) {
            return response()->json(['message' => 'Invalid or expired invite token.'], 404);
        }

        // Check if a user with this email already exists (edge case: registered after invite was sent)
        $user = User::where('email', $author->email)->first();

        if (!$user) {
            $user = DB::transaction(function () use ($data, $author) {
                return User::create([
                    'id'         => Str::uuid()->toString(),
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'],
                    'name'       => "{$data['first_name']} {$data['last_name']}",
                    'email'      => $author->email,
                    'password'   => bcrypt($data['password']),
                    'org_role'   => 'researcher',
                    'is_active'  => true,
                ]);
            });
        }

        $author->update([
            'user_id'     => $user->id,
            'joined_at'   => now(),
            'invite_token' => null,
        ]);

        // Also update name/affiliation on the author record if empty
        if (!$author->name) {
            $author->update(['name' => $user->name]);
        }

        return response()->json([
            'message' => 'Account created successfully. You can now log in.',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * PATCH /api/submissions/{submissionId}/authors/reorder
     *
     * Accepts an ordered array of author IDs and updates author_order accordingly.
     * Body: { "order": ["uuid-1", "uuid-2", ...] }
     */
    public function reorder(Request $request, string $submissionId): JsonResponse
    {
        $submission = Submission::findOrFail($submissionId);
        $this->authorize('update', $submission);

        $data = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ]);

        $ids = $data['order'];

        DB::transaction(function () use ($submissionId, $ids) {
            foreach ($ids as $position => $authorId) {
                SubmissionAuthor::where('submission_id', $submissionId)
                    ->where('id', $authorId)
                    ->update(['author_order' => $position + 1]);
            }
        });

        $authors = SubmissionAuthor::where('submission_id', $submissionId)
            ->orderBy('author_order')
            ->get();

        return response()->json(['data' => $authors->map(fn($a) => $this->toResource($a))]);
    }

    private function toResource(SubmissionAuthor $a): array
    {
        return [
            'id'               => $a->id,
            'submission_id'    => $a->submission_id,
            'user_id'          => $a->user_id,
            'name'             => $a->name,
            'email'            => $a->email,
            'affiliation'      => $a->affiliation,
            'is_corresponding' => $a->is_corresponding,
            'author_order'     => $a->author_order,
            'has_account'      => (bool) $a->user_id,
            'invite_pending'   => !$a->user_id && (bool) $a->invite_token,
            'invited_at'       => $a->invited_at?->toIso8601String(),
            'joined_at'        => $a->joined_at?->toIso8601String(),
            'added_at'         => $a->added_at,
        ];
    }
}
