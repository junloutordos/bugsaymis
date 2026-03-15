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
                if (! Schema::hasColumn('guidance_consultations', 'referral_category')) {
                    $table->text('referral_category')->nullable()->after('referred_by');
                }

                if (! Schema::hasColumn('guidance_consultations', 'behavior_spotted')) {
                    $table->text('behavior_spotted')->nullable()->after('referral_category');
                }

                if (! Schema::hasColumn('guidance_consultations', 'brief_description')) {
                    $table->text('brief_description')->nullable()->after('behavior_spotted');
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
                if (Schema::hasColumn('guidance_consultations', 'brief_description')) {
                    $table->dropColumn('brief_description');
                }

                if (Schema::hasColumn('guidance_consultations', 'behavior_spotted')) {
                    $table->dropColumn('behavior_spotted');
                }

                if (Schema::hasColumn('guidance_consultations', 'referral_category')) {
                    $table->dropColumn('referral_category');
                }
            });
        }
    }
};
