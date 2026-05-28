<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuance_series_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 20);
            $table->smallInteger('year')->unsigned();
            $table->integer('series_start')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['type_code', 'year']);
            $table->foreign('type_code')
                ->references('code')->on('issuance_types')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_series_settings');
    }
};
