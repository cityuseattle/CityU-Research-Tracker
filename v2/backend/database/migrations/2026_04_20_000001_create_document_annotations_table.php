<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inline document annotations.
 *
 * Reviewers (and coordinators/admins) can highlight a passage from
 * a submission document and attach a comment. The selected text is
 * stored as `quote` so it is preserved even if the document is replaced
 * by a later version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_annotations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('submission_id');
            $table->integer('version_number');
            $table->string('filename');
            $table->uuid('annotator_id');          // user who added the annotation
            $table->text('quote');                 // selected / referenced text excerpt
            $table->text('comment');               // the annotation body
            $table->string('position_hint', 100)->nullable(); // e.g. "paragraph 3"
            $table->timestampsTz();

            $table->foreign('submission_id')
                  ->references('id')->on('submissions')
                  ->cascadeOnDelete();

            $table->foreign('annotator_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->index(['submission_id', 'version_number', 'filename'], 'idx_doc_annotations_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_annotations');
    }
};
