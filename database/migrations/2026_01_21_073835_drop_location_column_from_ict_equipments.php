<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ict_equipments')) return;
        Schema::table('ict_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('ict_equipments', 'location')) {
                $table->dropColumn('location');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ict_equipments')) return;
        Schema::table('ict_equipments', function (Blueprint $table) {
            $table->string('location')->nullable();
        });
    }
};
