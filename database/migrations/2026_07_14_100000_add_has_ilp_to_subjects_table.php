<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subjects', 'has_ilp')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->boolean('has_ilp')->default(false)->after('is_active')
                      ->comment('Subject reserves one weekly session for a 30-min Independent Learning Period');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subjects', 'has_ilp')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('has_ilp');
            });
        }
    }
};
