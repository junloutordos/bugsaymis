<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropLocationColumnsFromAssets extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'location_division_id')) {
                $table->dropForeign(['location_division_id']);
                $table->dropColumn('location_division_id');
            }
            if (Schema::hasColumn('assets', 'location_office_id')) {
                $table->dropForeign(['location_office_id']);
                $table->dropColumn('location_office_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('location_division_id')->nullable()->after('condition')->constrained('divisions')->nullOnDelete();
            $table->foreignId('location_office_id')->nullable()->after('location_division_id')->constrained('offices')->nullOnDelete();
        });
    }
}
