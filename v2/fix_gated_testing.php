<?php
/**
 * Fix script: Reset "Testing gated review" submission to correct PENDING_RELEASE state.
 *
 * Current (wrong) state: ACCEPTED — the gatekeeper incorrectly issued ACCEPTED via the
 * old panel. Under the corrected workflow the gatekeeper cannot accept; acceptance only
 * happens when all stages auto-pass.
 *
 * Stage situation:
 *   Stage 1 Chair Review   (is_gatekeeper=TRUE)  — reviewer1 APPROVED    → PASSED
 *   Stage 2 Committee Review                      — reviewer2 APPROVED, reviewer3 REVISED → REVISION_REQUIRED (ALL strategy)
 *   Stage 3 Program Director                      — pending reviewer, no decision yet
 *
 * Correct state: PENDING_RELEASE, gatekeeper sees Stage 2 REVISION_REQUIRED
 */
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$sub = DB::table('submissions')
    ->where('title', 'like', '%Testing gated review%')
    ->first();

if (!$sub) {
    echo "ERROR: Submission 'Testing gated review' not found.\n";
    exit(1);
}

$subId = $sub->id;
echo "=== Submission: {$sub->title} (id={$subId}) ===\n";
echo "Current status: {$sub->status}\n";

// Remove the incorrect gated_release ACCEPTED record.
$deleted = DB::table('gated_releases')
    ->where('submission_id', $subId)
    ->where('decision', 'ACCEPTED')
    ->delete();
echo "Deleted {$deleted} incorrect ACCEPTED gated_release record(s).\n";

// Set correct metadata: Stage 2 (Committee Review) → REVISION_REQUIRED
$metadata = [
    'pending_gatekeeper_stage_id'      => 'f8933de3-18f8-4e51-8e40-7b3cdc063691',
    'pending_gatekeeper_stage_name'    => 'Committee Review',
    'pending_gatekeeper_stage_outcome' => 'REVISION_REQUIRED',
    'pending_revision_stage_id'        => 'f8933de3-18f8-4e51-8e40-7b3cdc063691',
    'pending_revision_stage_name'      => 'Committee Review',
];

$rows = DB::table('submissions')
    ->where('id', $subId)
    ->update([
        'status'   => 'PENDING_RELEASE',
        'metadata' => json_encode($metadata),
    ]);
echo "Submission update: {$rows} row(s) affected.\n";

// Write audit log entry.
DB::table('audit_logs')->insert([
    'id'            => \Illuminate\Support\Str::uuid()->toString(),
    'submission_id' => $subId,
    'actor_id'      => null,
    'action'        => 'MANUAL_STATE_FIX',
    'before_state'  => json_encode(['status' => $sub->status]),
    'after_state'   => json_encode(['status' => 'PENDING_RELEASE', 'reason' => 'Reset after incorrect ACCEPTED via old gated-release panel']),
    'created_at'    => now(),
]);
echo "Audit log written.\n";

echo "\n=== After fix ===\n";
$after = DB::table('submissions')->where('id', $subId)->first();
echo "Status:   {$after->status}\n";
echo "Metadata: {$after->metadata}\n";

echo "\nReviewer assignments:\n";
$reviewers = DB::table('submission_reviewers')
    ->where('submission_id', $subId)
    ->get();
foreach ($reviewers as $r) {
    $user  = DB::table('users')->where('id', $r->user_id)->first();
    $stage = DB::table('stage_definitions')->where('id', $r->stage_id)->first();
    echo "  " . ($user->email ?? $r->user_id)
       . " → " . ($stage->name ?? $r->stage_id)
       . " | status={$r->status} | decision=" . ($r->decision ?? 'null') . "\n";
}

echo "\nDone. reviewer1 (gatekeeper) should now see the PENDING_RELEASE panel for Committee Review → REVISION_REQUIRED.\n";
