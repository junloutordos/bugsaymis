<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            // Replace location_office_id FK to reference rooms
            if (Schema::hasColumn('work_requests', 'location_office_id')) {
                // drop existing FK if any
                try { $table->dropForeign(['location_office_id']); } catch (\Throwable $e) {}
                $table->foreign('location_office_id')->references('id')->on('rooms')->nullOnDelete();
            }

            // Replace location_division_id FK to reference buildings
            if (Schema::hasColumn('work_requests', 'location_division_id')) {
                try { $table->dropForeign(['location_division_id']); } catch (\Throwable $e) {}
                $table->foreign('location_division_id')->references('id')->on('buildings')->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (Schema::hasColumn('work_requests', 'location_office_id')) {
                try { $table->dropForeign(['location_office_id']); } catch (\Throwable $e) {}
                $table->foreign('location_office_id')->references('id')->on('offices')->nullOnDelete();
            }

            if (Schema::hasColumn('work_requests', 'location_division_id')) {
                try { $table->dropForeign(['location_division_id']); } catch (\Throwable $e) {}
                $table->foreign('location_division_id')->references('id')->on('divisions')->nullOnDelete();
            }
        });
    }
};
