<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->foreignId('parent_issuance_id')->nullable()->after('id')
                ->constrained('issuances')->restrictOnDelete();
            $table->string('document_kind', 20)->default('primary')->after('parent_issuance_id');
            $table->unsignedSmallInteger('supplement_no')->nullable()->after('document_kind');
            $table->string('reference_number', 120)->nullable()->after('control_number');
            $table->timestamp('archived_at')->nullable()->after('released_at');
            $table->foreignId('archived_by')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 500)->nullable()->after('archived_by');

            $table->unique(
                ['parent_issuance_id', 'document_kind', 'supplement_no'],
                'issuances_parent_kind_number_unique'
            );
            $table->index(['parent_issuance_id', 'status']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->dropUnique('issuances_parent_kind_number_unique');
            $table->dropIndex(['parent_issuance_id', 'status']);
            $table->dropIndex(['archived_at']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('parent_issuance_id');
            $table->dropColumn([
                'document_kind', 'supplement_no', 'reference_number',
                'archived_at', 'archive_reason',
            ]);
        });
    }
};
