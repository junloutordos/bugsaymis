<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opcr_indicators', function (Blueprint $table) {
            $table->text('accomplishment')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('opcr_indicators', function (Blueprint $table) {
            $table->dropColumn('accomplishment');
        });
    }
};
