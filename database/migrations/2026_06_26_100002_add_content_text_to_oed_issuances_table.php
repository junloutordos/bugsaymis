<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oed_issuances', function (Blueprint $table) {
            $table->longText('content_text')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('oed_issuances', function (Blueprint $table) {
            $table->dropColumn('content_text');
        });
    }
};
