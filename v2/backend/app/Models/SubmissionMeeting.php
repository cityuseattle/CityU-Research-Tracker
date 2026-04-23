<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionMeeting extends Model
{
    protected $table = 'submission_meetings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'submission_id', 'stage_id', 'meeting_type', 'requested_by',
        'title', 'description', 'proposed_at', 'status',
        'confirmed_at', 'meeting_link', 'notes',
    ];

    protected $casts = [
        'proposed_at'  => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function stage()
    {
        return $this->belongsTo(StageDefinition::class, 'stage_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
