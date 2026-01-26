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
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'consultation_type')) {
                $table->string('consultation_type')->nullable()->after('requestor_id');
            }

            if (! Schema::hasColumn('consultations', 'requestor_type')) {
                $table->string('requestor_type')->nullable()->after('requestor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'requestor_type')) {
                $table->dropColumn('requestor_type');
            }
            if (Schema::hasColumn('consultations', 'consultation_type')) {
                $table->dropColumn('consultation_type');
            }
        });
    }
};
