<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDatetimesToGuidanceConsultationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->dateTime('date_time_preferred')->nullable()->after('status');
            $table->dateTime('date_time_assigned')->nullable()->after('date_time_preferred');
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
            $table->dropColumn(['date_time_preferred', 'date_time_assigned']);
        });
    }
}
