<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notification extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'data',
        'read_at',
        'emailed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'read_at'    => 'datetime',
            'emailed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    // ── Notification type constants ───────────────────────────────────────────

    const TYPE_REVIEWER_ASSIGNED     = 'reviewer_assigned';
    const TYPE_DECISION_SUBMITTED    = 'decision_submitted';
    const TYPE_SUBMISSION_ACCEPTED   = 'submission_accepted';
    const TYPE_SUBMISSION_REJECTED   = 'submission_rejected';
    const TYPE_REVISION_REQUIRED     = 'revision_required';
    const TYPE_SUBMISSION_SUBMITTED  = 'submission_submitted';
    const TYPE_REVIEWER_ACCEPTED     = 'reviewer_accepted';
    const TYPE_REVIEWER_DECLINED     = 'reviewer_declined';
    const TYPE_STAGE_COMPLETE        = 'stage_complete';
    const TYPE_EXTENSION_REQUESTED   = 'extension_requested';
    const TYPE_EXTENSION_RESOLVED    = 'extension_resolved';
    const TYPE_CONFLICT_FLAGGED      = 'conflict_flagged';
    const TYPE_CONFLICT_RESOLVED     = 'conflict_resolved';
}
