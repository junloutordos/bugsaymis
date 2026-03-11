<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssignedPersonnelToGuidanceConsultations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->text('assigned_personnel')->nullable()->after('date_time_assigned');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->dropColumn('assigned_personnel');
        });
    }
}
