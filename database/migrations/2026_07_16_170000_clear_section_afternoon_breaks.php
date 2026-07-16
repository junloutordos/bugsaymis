<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumns('sections', ['afternoon_break_start', 'afternoon_break_end'])) {
            return;
        }

        DB::table('sections')->update([
            'afternoon_break_start' => null,
            'afternoon_break_end' => null,
        ]);
    }

    public function down(): void
    {
        // Cleared schedule overrides cannot be reconstructed reliably.
    }
};
