<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('application_requirements')
            ->where('name', 'Personal Data Sheet (PDS)')
            ->update(['description' => 'CSC Form 212 (Revised 2025 or latest edition), duly accomplished and signed']);
    }

    public function down(): void
    {
        DB::table('application_requirements')
            ->where('name', 'Personal Data Sheet (PDS)')
            ->update(['description' => 'CSC Form 212 (Revised 2017 or latest edition), duly accomplished and signed']);
    }
};
