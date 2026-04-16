# CityU Research Tracker — Workflow Migration Plan (Option B)

## Overview

Transition from static hardcoded workflow logic to a fully dynamic, config-driven
workflow engine where every behavioral rule (stage roles, decision options,
transition triggers, visibility) is defined in `wp_options` config and interpreted
by a `Workflow_Engine` class. No more `i === 0` assumptions, hardcoded decision
strings, or timestamp-based heuristics.

---

## Phases

### Phase 1 — `currentRound` field ✅ COMPLETE (pending deploy)

Replace the fragile `releasedAt` vs `revisionSubmittedAt` timestamp comparison
with a simple integer round counter.

**Changes made:**
- `class-portal-rest.php` → `gated_release()`: release object now carries `'round' => currentRound`
- `class-portal-rest.php` → `stageRevisionSubmitted` PATCH handler: increments `currentRound` on the submission
- `class-portal-data.php` → `derive_submission_status()`: compares `release.round < submission.currentRound` (integer); falls back to timestamp comparison for legacy releases without a `round` field

**Why:** Timestamp comparison can fail if revision and release happen within the same
second, or on clock drift. Integer round is unambiguous.

---

### Phase 2 — `Workflow_Engine` class

Introduce a new `class-workflow-engine.php` alongside existing code. Submissions
with a config-defined workflow use the engine; others fall back to existing logic.

**New class methods:**
```php
Workflow_Engine::get_workflow(string $type): ?array
Workflow_Engine::get_stage_role(array $wf, int $idx): string         // 'gatekeeper'|'committee'|'advisory'
Workflow_Engine::evaluate_stage(array $stage, string $rule): string  // 'approved'|'revision'|'rejected'|'pending'
Workflow_Engine::is_gatekeeper_stage(array $wf, int $idx): bool
Workflow_Engine::get_valid_decisions(array $wf, int $stage_idx): array
Workflow_Engine::derive_status(array $submission, array $wf): string
Workflow_Engine::compute_viewer_role(array $submission, string $email, bool $is_admin): string
Workflow_Engine::get_visible_stages(array $submission, string $viewer_role, array $wf): array
Workflow_Engine::can_release(array $submission, string $email, array $wf): bool
```

**Config shape per submission type:**
```json
{
  "id": "grant",
  "label": "Grant Application",
  "gatedReview": true,
  "workflow": {
    "stages": [
      {
        "name": "Chair Review",
        "role": "gatekeeper",
        "decisionRule": "single",
        "decisions": ["Approved", "Needs Revision", "Rejected"],
        "releaseChannel": true,
        "visibleTo": ["gatekeeper", "admin"]
      },
      {
        "name": "Committee Review",
        "role": "committee",
        "decisionRule": "unanimous",
        "decisions": ["Approved", "Needs Revision", "Rejected"],
        "visibleTo": ["gatekeeper", "admin", "committee"]
      }
    ],
    "transitions": [
      { "trigger": "stage_all_approved", "stageRole": "committee", "next": "notify_gatekeeper" },
      { "trigger": "release_decision",   "decision": "Approved",          "status": "Approved" },
      { "trigger": "release_decision",   "decision": "Revision Required", "status": "Revision Required" },
      { "trigger": "release_decision",   "decision": "Rejected",          "status": "Rejected" }
    ],
    "finalStatusByDecision": {
      "Approved": "Confirmed for Presentation"
    },
    "studentVisibility": {
      "showStageNames": true,
      "showReviewerNames": true,
      "decisionVisibleAfterRelease": true
    }
  }
}
```

**Hook-in points:**
- `is_stage_approved()` → call `Workflow_Engine::evaluate_stage()` when workflow exists
- `derive_submission_status()` → prepend engine call when workflow exists

---

### Phase 3 — Data-driven role detection

Replace all `stage_index === 0` / `is_gatekeeper()` checks with engine calls.

**`class-portal-rest.php` changes:**

| Location | Before | After |
|---|---|---|
| `submission_get()` | `$_gr_is_gk` branch | `Workflow_Engine::compute_viewer_role()` |
| `feedback()` | hardcoded `$valid_decisions` array | `Workflow_Engine::get_valid_decisions()` |
| `gated_release()` | `self::is_gatekeeper()` | `Workflow_Engine::can_release()` |
| reviewer dashboard | `$_is_gk = self::is_gatekeeper(...)` | `$_viewer_role = Workflow_Engine::compute_viewer_role(...)` |

**Server now sends** `viewerRole` field on every submission response instead of the
separate `isGatekeeper` / `isGatedReviewAdmin` boolean flags.

---

### Phase 4 — Server-computed stage statuses

The biggest structural change. Server pre-computes per-stage status labels and
visibility-strips data before sending to JS. JS becomes a pure template renderer.

**Server sends `computedStages[]`:**
```json
{
  "computedStages": [
    {
      "stageName": "Chair Review",
      "stageRole": "gatekeeper",
      "statusLabel": "✓ Decision Released",
      "statusClass": "approved",
      "reviewers": [],
      "showDecisions": false
    },
    {
      "stageName": "Committee Review",
      "stageRole": "committee",
      "statusLabel": "Revision Requested",
      "statusClass": "revision",
      "reviewers": [{"name": "Committe1", "email": "..."}],
      "showDecisions": true,
      "decisions": {"committe1@...": "Needs Revision", "committe2@...": "Approved"}
    }
  ]
}
```

**JS changes:**
- `stagesHtml` builder (~180 lines of logic) → simple `computedStages.map()` HTML template
- Student timeline block (~60 lines) → replaced, uses `computedStages`
- Remove all `isGatedReview`, `isGatekeeper`, `isGatedReviewAdmin` branches
- `canWithdraw`, `canAppeal`, `canFullPaper` → driven by `sub.allowedActions[]` from server
- Status badge color → driven by `sub.workflowStatusClass` from server
- Gated release button → shown when `sub.viewerRole === 'gatekeeper'` and `sub.pendingRelease === true`

---

### Phase 5 — Config UI + workflow editor

Admin panel workflow editor:
- Per submission type: define stages, assign roles, set `decisionRule`, set decisions, set final status labels
- Saves to `rrp_config` in `wp_options` (existing mechanism)
- New REST endpoint: `GET /config/workflow-schema` for JS to fetch decision options dynamically
- Remove hardcoded `WORKFLOW_STAGES` constant
- Remove `validate_submission()` switch block → config-driven field requirements

---

### Phase 6 — Data migration + cleanup

- One-time migration script: stamp `currentRound` on existing submissions = `count(gatedReleases)` minus 1 if last release is current round, else `count(gatedReleases)`
- Remove old `derive_submission_status()` code paths once all types have workflow config
- Remove `is_stage_approved()` in favour of `Workflow_Engine::evaluate_stage()`
- Remove `stageCompleted` / `gatekeeperNotifiedAt` magic flags — engine computes these
- Remove JS `statusBadgeCls()` hardcoded string matching → replaced by `workflowStatusClass` from server

---

## Timeline estimate

| Phase | Scope | Duration | Risk |
|---|---|---|---|
| 1 | `currentRound` field, replace timestamp comparison | 3–4 days | Low |
| 2 | `Workflow_Engine` class, config schema | 1 week | Medium |
| 3 | Data-driven role detection, `viewerRole` from server | 1.5 weeks | Medium |
| 4 | Server-computed stage statuses, JS simplified | 2 weeks | High |
| 5 | Admin workflow editor UI | 2 weeks | Low |
| 6 | Migration script, remove dead code | 3–4 days | Low |
| **Total** | | **~8 weeks** | |

Each phase is independently deployable. Submissions without a `workflow` config block
keep working via existing code until Phase 6.

---

## Key files

| File | Relevant sections |
|---|---|
| `includes/class-portal-data.php` | `derive_submission_status()` ~line 618, `is_stage_approved()` ~line 600 |
| `includes/class-portal-rest.php` | `gated_release()` ~line 7049, `feedback()` ~line 2758, `submission_get()` ~line 1648, reviewer dashboard ~line 3679 |
| `assets/portal.js` | `statusBadgeCls()` ~line 129, `stagesHtml` builder ~line 3660, student timeline ~line 3840 |

## Deploy command

```
python deploy.py
```
from `d:\Development\CityU-Research-Tracker`
