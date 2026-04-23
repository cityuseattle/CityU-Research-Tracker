<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatedRelease extends Model
{
    use HasUuids;

    protected $table = 'gated_releases';

    public $timestamps = false;

    const DECISION_ACCEPTED               = 'ACCEPTED';
    const DECISION_CONDITIONALLY_ACCEPTED = 'CONDITIONALLY_ACCEPTED';
    const DECISION_REVISION_REQUIRED      = 'REVISION_REQUIRED';
    const DECISION_REJECTED               = 'REJECTED';
    const DECISION_STAGE_RECHECK          = 'STAGE_RECHECK';

    protected $fillable = [
        'submission_id',
        'workflow_run_id',   // nullable — workflow_runs table is reserved for future use
        'version_number',
        'decision',
        'feedback',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
