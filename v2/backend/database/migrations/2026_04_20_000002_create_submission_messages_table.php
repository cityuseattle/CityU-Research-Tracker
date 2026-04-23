<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Submission messages — per-submission threaded communication between
 * the submitter (student), assigned reviewers, and coordinators/admins.
 *
 * body_html stores the rendered rich-text HTML produced by the frontend editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('submission_id');
            $table->uuid('sender_id');
            $table->text('body_html');
            $table->timestampsTz();

            $table->foreign('submission_id')
                  ->references('id')->on('submissions')
                  ->cascadeOnDelete();

            $table->foreign('sender_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->index(['submission_id', 'created_at'], 'idx_sub_messages');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_messages');
    }
};
