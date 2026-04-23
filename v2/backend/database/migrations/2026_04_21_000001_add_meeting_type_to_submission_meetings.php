<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_meetings', function (Blueprint $table) {
            $table->enum('meeting_type', [
                'gatekeeper_student',   // Gatekeeper schedules with submitter
                'gatekeeper_reviewers', // Gatekeeper schedules with a stage's reviewers
                'reviewer_reviewer',    // Reviewer schedules with peer reviewers (same stage)
                'student_gatekeeper',   // Submitter schedules with gatekeeper
            ])->nullable()->after('stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('submission_meetings', function (Blueprint $table) {
            $table->dropColumn('meeting_type');
        });
    }
};
