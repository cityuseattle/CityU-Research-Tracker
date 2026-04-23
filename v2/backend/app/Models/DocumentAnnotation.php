<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnnotation extends Model
{
    use HasUuids;

    protected $table = 'document_annotations';

    protected $fillable = [
        'submission_id',
        'version_number',
        'filename',
        'annotator_id',
        'quote',
        'comment',
        'position_hint',
    ];

    protected $casts = [
        'version_number' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function annotator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annotator_id');
    }
}
