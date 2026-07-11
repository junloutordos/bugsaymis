<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_rating_periods', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester')->nullable()->after('year');
            $table->date('start_date')->nullable()->after('semester');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('status', 20)->default('open')->after('end_date');
            $table->boolean('is_current')->default(false)->after('status');
            $table->index(['year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_rating_periods', function (Blueprint $table) {
            $table->dropIndex(['year', 'semester']);
            $table->dropColumn(['semester', 'start_date', 'end_date', 'status', 'is_current']);
        });
    }
};
