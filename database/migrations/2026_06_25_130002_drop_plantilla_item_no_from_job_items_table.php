<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_items', 'plantilla_item_no')) {
            return;
        }

        Schema::table('job_items', function (Blueprint $table) {
            $table->dropColumn('plantilla_item_no');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_items', 'plantilla_item_no')) {
            return;
        }

        Schema::table('job_items', function (Blueprint $table) {
            $table->string('plantilla_item_no')->nullable()->index()->after('position_title');
        });
    }
};
