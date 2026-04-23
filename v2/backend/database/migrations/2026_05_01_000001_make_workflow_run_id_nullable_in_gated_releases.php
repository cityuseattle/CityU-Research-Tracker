<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make workflow_run_id nullable in gated_releases.
 *
 * The workflow_runs table is not used in practice — all existing and future
 * gated_release records set workflow_run_id to NULL. The FK constraint and
 * NOT NULL requirement are therefore dropped so the table is not blocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop FK constraint (Laravel naming convention)
        DB::statement('ALTER TABLE gated_releases DROP CONSTRAINT IF EXISTS gated_releases_workflow_run_id_foreign');

        // Make the column nullable
        DB::statement('ALTER TABLE gated_releases ALTER COLUMN workflow_run_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gated_releases ALTER COLUMN workflow_run_id SET NOT NULL');

        DB::statement('ALTER TABLE gated_releases ADD CONSTRAINT gated_releases_workflow_run_id_foreign
            FOREIGN KEY (workflow_run_id) REFERENCES workflow_runs(id)');
    }
};
