<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->decimal('accuracy_meters', 10, 2)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropColumn('accuracy_meters');
        });
    }
};
