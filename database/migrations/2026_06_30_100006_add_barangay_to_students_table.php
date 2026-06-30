<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('students', 'barangay')) {
            DB::statement("ALTER TABLE `students` ADD COLUMN `barangay` varchar(255) DEFAULT NULL AFTER `houseno`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'barangay')) {
            DB::statement("ALTER TABLE `students` DROP COLUMN `barangay`");
        }
    }
};
