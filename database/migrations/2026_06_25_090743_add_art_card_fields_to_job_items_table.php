<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->string('submit_to_name')->nullable()->after('budget_source');
            $table->string('submit_to_title')->nullable()->after('submit_to_name');
            $table->date('contract_start_date')->nullable()->after('submit_to_title');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->timestamp('art_card_generated_at')->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->dropColumn([
                'submit_to_name',
                'submit_to_title',
                'contract_start_date',
                'contract_end_date',
                'art_card_generated_at',
            ]);
        });
    }
};
