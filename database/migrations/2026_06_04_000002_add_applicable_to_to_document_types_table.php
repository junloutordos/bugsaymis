<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('applicable_to')->default('both')->after('is_active');
            // Values: 'internal' | 'external' | 'both'
        });

        // Classify the 15 seeded types
        $external = ['LTR', 'IND'];
        $internal = ['MEMO', 'LEAVE', 'RAT', 'CERT', 'PR', 'TO', 'ARPT', 'SO', 'VCH', 'CON', 'RFQ', 'DIR'];
        // 'OTH' stays as 'both' (the column default)

        DB::table('document_types')->whereIn('code', $external)->update(['applicable_to' => 'external']);
        DB::table('document_types')->whereIn('code', $internal)->update(['applicable_to' => 'internal']);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('applicable_to');
        });
    }
};
