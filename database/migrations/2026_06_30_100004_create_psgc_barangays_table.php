<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psgc_barangays', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 100);
            $table->string('city_code', 20);
            $table->index('city_code');
        });
    }
    public function down(): void { Schema::dropIfExists('psgc_barangays'); }
};
