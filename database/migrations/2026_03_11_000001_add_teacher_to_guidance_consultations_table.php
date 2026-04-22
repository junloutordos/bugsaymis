<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('guidance_consultations')) {
            Schema::table('guidance_consultations', function (Blueprint $table) {
                if (! Schema::hasColumn('guidance_consultations', 'teacher')) {
                    $table->unsignedBigInteger('teacher')->nullable()->after('followup_date');
                    $table->foreign('teacher')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('guidance_consultations')) {
            Schema::table('guidance_consultations', function (Blueprint $table) {
                if (Schema::hasColumn('guidance_consultations', 'teacher')) {
                    try {
                        $table->dropForeign(['teacher']);
                    } catch (\Throwable $e) {
                        // Ignore if constraint is missing or already dropped.
                    }

                    $table->dropColumn('teacher');
                }
            });
        }
    }
};
