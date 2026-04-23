<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;

class Submission extends Model
{
    use HasUuids;

    protected $table = 'submissions';

    const STATUS_DRAFT               = 'DRAFT';
    const STATUS_SUBMITTED           = 'SUBMITTED';
    const STATUS_RESUBMITTED         = 'RESUBMITTED';
    const STATUS_AWAITING_REVIEWERS  = 'AWAITING_REVIEWERS';
    const STATUS_IN_REVIEW           = 'IN_REVIEW';
    const STATUS_REVISION_REQUIRED   = 'REVISION_REQUIRED';
    const STATUS_PENDING_RELEASE     = 'PENDING_RELEASE';
    const STATUS_ACCEPTED            = 'ACCEPTED';
    const STATUS_CONDITIONALLY_ACCEPTED = 'CONDITIONALLY_ACCEPTED';
    const STATUS_REJECTED            = 'REJECTED';
    const STATUS_APPEAL_PENDING      = 'APPEAL_PENDING';
    const STATUS_WITHDRAWN           = 'WITHDRAWN';
    const STATUS_CANCELLED           = 'CANCELLED';

    const EDITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVISION_REQUIRED,
    ];

    protected $fillable = [
        'submission_type_id', 'program_id', 'submitter_id',
        'title', 'abstract', 'status', 'current_version',
        'is_locked', 'metadata',
        'current_stage_id', 'current_stage_entered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked'                => 'boolean',
            'metadata'                 => 'array',
            'current_version'          => 'integer',
            'current_stage_entered_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function currentStage()
    {
        return $this->belongsTo(StageDefinition::class, 'current_stage_id');
    }

    public function submissionType()
    {
        return $this->belongsTo(SubmissionType::class, 'submission_type_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function versions()
    {
        return $this->hasMany(SubmissionVersion::class, 'submission_id')
                    ->orderBy('version_number', 'desc');
    }

    public function latestVersion()
    {
        return $this->hasOne(SubmissionVersion::class, 'submission_id')
                    ->latestOfMany('version_number');
    }

    public function authors()
    {
        return $this->hasMany(SubmissionAuthor::class, 'submission_id')
                    ->orderBy('author_order');
    }

    public function reviewers()
    {
        return $this->hasMany(SubmissionReviewer::class, 'submission_id');
    }

    public function gatedReleases()
    {
        return $this->hasMany(GatedRelease::class, 'submission_id');
    }

    public function appeals()
    {
        return $this->hasMany(AppealRequest::class, 'submission_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filter submissions visible to the given user based on their role.
     * - admin / coordinator: all submissions
     * - reviewer: submissions they are assigned to (phase 3; for now all IN_REVIEW)
     * - student: own submissions only
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $roles = $user->roles ?? [];

        if (in_array('admin', $roles) || in_array('coordinator', $roles)) {
            return $query; // All submissions
        }

        if (in_array('reviewer', $roles)) {
            // Reviewers see submissions they are explicitly assigned to
            // OR their own submissions (if they are also a student)
            return $query->where(function (Builder $q) use ($user) {
                $q->where('submitter_id', $user->id)
                  ->orWhereHas('reviewers', fn($r) => $r->where('user_id', $user->id));
            });
        }

        // Student / default: own submissions, scoped to accessible types
        $userGroupIds = $user->groups()->pluck('groups.id')->toArray();
        $userId = $user->id;

        return $query->where('submitter_id', $user->id)
            ->whereHas('submissionType', function (Builder $sq) use ($userId, $userGroupIds) {
                $sq->where(function (Builder $q) use ($userId, $userGroupIds) {
                    // Open to all: no group AND no user restrictions
                    $q->where(function (Builder $inner) {
                        $inner->whereDoesntHave('allowedGroups')
                              ->whereDoesntHave('allowedUsers');
                    })
                    // OR user is in an allowed group
                    ->orWhereHas('allowedGroups', fn ($g) => $g->whereIn('groups.id', $userGroupIds))
                    // OR user is directly assigned
                    ->orWhereHas('allowedUsers', fn ($u) => $u->where('users.id', $userId));
                });
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES) && ! $this->is_locked;
    }
}
