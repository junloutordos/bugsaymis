<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove all RES-* designations — both old per-group entries (RES-G10-GROUP-*, etc.)
        // and simple grade-level ones (RES-G7 through RES-G12).
        // RES-G* designations are now auto-created on demand by ResearchAdvisoryController
        // when the first advisory group for a grade level is saved.
        // Safe: verified 0 load_assignments reference any RES-* designation in production.
        DB::table('designations')->where('code', 'LIKE', 'RES-%')->delete();
    }

    public function down(): void
    {
        // Not reversible — designations are auto-created by the RA module as needed.
    }
};
