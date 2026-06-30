<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psgc_regions', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 100);
        });
    }
    public function down(): void { Schema::dropIfExists('psgc_regions'); }
};
