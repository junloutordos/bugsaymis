<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('office_area')
                ->constrained('rooms')->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()->after('school_year')
                ->constrained('school_years')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropConstrainedForeignId('school_year_id');
        });
    }
};
