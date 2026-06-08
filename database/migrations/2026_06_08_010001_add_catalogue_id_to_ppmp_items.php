<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->unsignedBigInteger('catalogue_id')->nullable()->after('ppmp_id');
            $table->foreign('catalogue_id')->references('id')->on('ppmp_catalogue')->nullOnDelete();
            $table->index('catalogue_id');
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropForeign(['catalogue_id']);
            $table->dropIndex(['catalogue_id']);
            $table->dropColumn('catalogue_id');
        });
    }
};
