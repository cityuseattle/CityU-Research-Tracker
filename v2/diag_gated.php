<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 1. Find submission 'Testing gated review' ===\n";
$sub = DB::table('submissions')
    ->where('title', 'like', '%Testing gated review%')
    ->orWhere('title', 'like', '%testing gated%')
    ->first();

if (!$sub) {
    echo "Submission not found by title. Listing recent dissertation-prospectus submissions:\n";
    $subs = DB::table('submissions')
        ->join('submission_types', 'submissions.submission_type_id', '=', 'submission_types.id')
        ->where('submission_types.slug', 'dissertation-prospectus')
        ->orderBy('submissions.created_at', 'desc')
        ->limit(5)
        ->select('submissions.*', 'submission_types.slug as type_slug')
        ->get();
    foreach ($subs as $s) {
        echo "  id=" . $s->id . " title=" . $s->title . " status=" . $s->status . "\n";
    }
    exit;
}

$subId = $sub->id;
echo "Found: id=$subId title={$sub->title} status={$sub->status}\n";
echo "Metadata: " . ($sub->metadata ?? 'null') . "\n\n";

echo "=== 2. Submission type + workflow ===\n";
$type = DB::table('submission_types')->where('id', $sub->submission_type_id)->first();
echo "Type: " . $type->label . " (is_gated=" . ($type->is_gated_review ? 'yes' : 'no') . ") workflow_id=" . ($type->workflow_id ?? 'null') . "\n";

$workflow = DB::table('workflow_definitions')->where('id', $type->workflow_id)->first();
echo "Workflow: " . ($workflow->name ?? 'N/A') . "\n\n";

echo "=== 3. All stages with skip_condition ===\n";
$stages = DB::table('stage_definitions')
    ->where('workflow_id', $type->workflow_id)
    ->orderBy('order')
    ->get();
foreach ($stages as $s) {
    echo "  Stage[" . $s->order . "] id=" . $s->id . " name=" . $s->name
       . " | is_gatekeeper=" . ($s->is_gatekeeper ? 'TRUE' : 'false')
       . " | approval_strategy=" . ($s->approval_strategy ?? 'null')
       . " | min_approvals=" . ($s->min_approvals ?? 'null')
       . " | skip_condition=" . ($s->skip_condition ?? 'null') . "\n";
}

echo "\n=== 4. Reviewer assignments for this submission ===\n";
$reviewers = DB::table('submission_reviewers')
    ->where('submission_id', $subId)
    ->get();
foreach ($reviewers as $r) {
    $user  = DB::table('users')->where('id', $r->user_id)->first();
    $stage = DB::table('stage_definitions')->where('id', $r->stage_id)->first();
    echo "  " . ($user->email ?? $r->user_id)
       . " → Stage[" . ($stage->order ?? '?') . "] " . ($stage->name ?? $r->stage_id)
       . " | status=" . $r->status
       . " | decision=" . ($r->decision ?? 'null')
       . " | decision_at=" . ($r->decision_at ?? 'null') . "\n";
}

echo "\n=== 5. Audit log for this submission ===\n";
$logs = DB::table('audit_logs')
    ->where('submission_id', $subId)
    ->orderBy('created_at', 'asc')
    ->get();
foreach ($logs as $l) {
    echo "  [" . $l->created_at . "] action=" . $l->action
       . " before=" . ($l->before_state ?? 'null')
       . " after=" . ($l->after_state ?? 'null') . "\n";
}

echo "\n=== 6. Stage-by-stage evaluation simulation ===\n";
foreach ($stages as $s) {
    if ($s->is_gatekeeper) {
        echo "  Stage[" . $s->order . "] " . $s->name . " → SKIPPED (is_gatekeeper=true)\n";
        continue;
    }
    $stageReviewers = DB::table('submission_reviewers')
        ->where('submission_id', $subId)
        ->where('stage_id', $s->id)
        ->where('status', '!=', 'declined')
        ->get();
    $total      = $stageReviewers->count();
    $decided    = $stageReviewers->whereNotNull('decision')->count();
    $pending    = $total - $decided;
    $approvals  = $stageReviewers->where('decision', 'approve')->count();
    $revisions  = $stageReviewers->where('decision', 'revise')->count();
    $rejections = $stageReviewers->where('decision', 'reject')->count();
    $strategy   = strtoupper($s->approval_strategy ?? 'ALL');
    $minApprovals = (int)($s->min_approvals ?? 1);

    if ($rejections > 0) { $outcome = 'FAILED'; }
    else {
        $passed = match($strategy) {
            'ANY'      => $approvals >= 1,
            'MAJORITY' => $approvals >= $minApprovals,
            default    => $total > 0 && $approvals >= $total,
        };
        if ($passed) $outcome = 'PASSED';
        elseif ($pending > 0) $outcome = 'PENDING';
        elseif ($revisions > 0) $outcome = 'REVISION_REQUIRED';
        else $outcome = 'PENDING';
    }
    echo "  Stage[" . $s->order . "] " . $s->name . " → $outcome"
       . " (total=$total, decided=$decided, approvals=$approvals, pending=$pending, strategy=$strategy)\n";
}
