<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_housekeeping_conforme')) {
            return;
        }

        Schema::create('rh_housekeeping_conforme', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_housekeeping_check_id')->index();
            $table->unsignedBigInteger('rh_intern_id')->index();
            $table->timestamp('conforme_at')->nullable();
            $table->timestamps();

            $table->unique(['rh_housekeeping_check_id', 'rh_intern_id'], 'rh_hk_conforme_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_housekeeping_conforme');
    }
};
