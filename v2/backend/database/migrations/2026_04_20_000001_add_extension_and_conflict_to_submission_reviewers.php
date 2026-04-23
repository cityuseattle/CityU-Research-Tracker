<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add extension-request and conflict-of-interest columns to submission_reviewers.
 *
 * Extension request flow:
 *   Reviewer fills in extension_reason + extension_requested_days → status becomes 'extension_pending'
 *   Coordinator/admin approves (extension_status = 'approved') → due_at is extended, status reverts to previous
 *   Coordinator/admin rejects (extension_status = 'rejected') → no change to due_at
 *
 * Conflict of interest flow:
 *   Reviewer fills in conflict_reason → conflict_flagged = true
 *   Coordinator/admin resolves by reassigning (removes this record or adds another)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            // Extension request
            $table->text('extension_reason')->nullable()->after('reminder_sent_at');
            $table->unsignedSmallInteger('extension_requested_days')->nullable()->after('extension_reason');
            $table->string('extension_status', 20)->nullable()->after('extension_requested_days');
            // null | pending | approved | rejected
            $table->timestampTz('extension_requested_at')->nullable()->after('extension_status');
            $table->timestampTz('extension_resolved_at')->nullable()->after('extension_requested_at');

            // Conflict of interest
            $table->boolean('conflict_flagged')->default(false)->after('extension_resolved_at');
            $table->text('conflict_reason')->nullable()->after('conflict_flagged');
            $table->timestampTz('conflict_flagged_at')->nullable()->after('conflict_reason');
        });
    }

    public function down(): void
    {
        Schema::table('submission_reviewers', function (Blueprint $table) {
            $table->dropColumn([
                'extension_reason',
                'extension_requested_days',
                'extension_status',
                'extension_requested_at',
                'extension_resolved_at',
                'conflict_flagged',
                'conflict_reason',
                'conflict_flagged_at',
            ]);
        });
    }
};
