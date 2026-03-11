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
                if (! Schema::hasColumn('guidance_consultations', 'consultation_type')) {
                    $table->string('consultation_type')->nullable()->after('teacher');
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
                if (Schema::hasColumn('guidance_consultations', 'consultation_type')) {
                    $table->dropColumn('consultation_type');
                }
            });
        }
    }
};
