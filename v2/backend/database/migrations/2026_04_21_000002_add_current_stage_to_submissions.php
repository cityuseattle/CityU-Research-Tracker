<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Tracks which review stage is currently active for this submission.
            // Null when the submission is not in review (DRAFT, SUBMITTED, ACCEPTED, etc.).
            $table->uuid('current_stage_id')->nullable()->after('is_locked');

            // When the submission entered the current_stage (first reviewer assigned).
            $table->timestampTz('current_stage_entered_at')->nullable()->after('current_stage_id');

            $table->foreign('current_stage_id')
                ->references('id')
                ->on('stage_definitions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['current_stage_id']);
            $table->dropColumn(['current_stage_id', 'current_stage_entered_at']);
        });
    }
};
