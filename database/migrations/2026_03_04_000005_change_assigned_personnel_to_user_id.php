<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeAssignedPersonnelToUserId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Drop the existing text column if present, then add an unsignedBigInteger column
        Schema::table('guidance_consultations', function (Blueprint $table) {
            if (Schema::hasColumn('guidance_consultations', 'assigned_personnel')) {
                $table->dropColumn('assigned_personnel');
            }
        });

        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_personnel')->nullable()->after('date_time_assigned');
            $table->foreign('assigned_personnel')->references('id')->on('users')->onDelete('set null');
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
            // drop FK then column
            try {
                $table->dropForeign(['assigned_personnel']);
            } catch (\Exception $e) {
                // ignore
            }
            if (Schema::hasColumn('guidance_consultations', 'assigned_personnel')) {
                $table->dropColumn('assigned_personnel');
            }
        });

        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->text('assigned_personnel')->nullable()->after('date_time_assigned');
        });
    }
}
