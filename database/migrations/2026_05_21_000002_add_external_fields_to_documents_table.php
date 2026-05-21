<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('origin_type')->default('internal')->after('overall_status'); // internal|external
            $table->string('source_office')->nullable()->after('origin_type');
            $table->string('sender_name')->nullable()->after('source_office');
            $table->date('date_of_document')->nullable()->after('sender_name');
            $table->date('date_received')->nullable()->after('date_of_document');
            $table->string('document_number')->nullable()->after('date_received'); // sender's own ref no.
            $table->string('priority')->default('Normal')->after('document_number'); // Normal|Urgent|Rush
            $table->timestamp('deadline_at')->nullable()->after('priority');
            $table->timestamp('completed_at')->nullable()->after('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'origin_type', 'source_office', 'sender_name',
                'date_of_document', 'date_received', 'document_number',
                'priority', 'deadline_at', 'completed_at',
            ]);
        });
    }
};
