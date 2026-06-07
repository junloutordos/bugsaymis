<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->unsignedBigInteger('ppmp_id')->nullable()->after('ppmp_checked');
            $table->foreign('ppmp_id')->references('id')->on('ppmp')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropForeign(['ppmp_id']);
            $table->dropColumn('ppmp_id');
        });
    }
};
