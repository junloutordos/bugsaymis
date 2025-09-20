<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            if (Schema::hasColumn('ict_pms', 'pms_date')) {
                $table->dropColumn('pms_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            $table->date('pms_date')->nullable();
        });
    }
};
