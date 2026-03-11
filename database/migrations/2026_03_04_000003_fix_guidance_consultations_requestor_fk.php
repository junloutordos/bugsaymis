<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixGuidanceConsultationsRequestorFk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('guidance_consultations', function (Blueprint $table) {
            // If a foreign key exists on requestor_id to users, drop it so student ids can be stored.
            try {
                $table->dropForeign(['requestor_id']);
            } catch (\Exception $e) {
                // ignore if the foreign key does not exist
            }

            // Make the column nullable and unsigned big integer (no FK)
            $table->unsignedBigInteger('requestor_id')->nullable()->change();
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
            $table->unsignedBigInteger('requestor_id')->nullable(false)->change();
            $table->foreign('requestor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
