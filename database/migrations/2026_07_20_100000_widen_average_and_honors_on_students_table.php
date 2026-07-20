<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // average was float(4,2) — max 99.99, but a perfect 100.00 average is a legitimate value.
        if (Schema::hasColumn('students', 'average')) {
            DB::statement("ALTER TABLE `students` MODIFY `average` float(5,2) DEFAULT NULL");
        }

        // honors was varchar(255) — some students list multiple honors/awards that exceed that.
        if (Schema::hasColumn('students', 'honors')) {
            DB::statement("ALTER TABLE `students` MODIFY `honors` varchar(1000) DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'average')) {
            DB::statement("ALTER TABLE `students` MODIFY `average` float(4,2) DEFAULT NULL");
        }

        if (Schema::hasColumn('students', 'honors')) {
            DB::statement("ALTER TABLE `students` MODIFY `honors` varchar(255) DEFAULT NULL");
        }
    }
};
