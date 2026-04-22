<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->string('ppmp_number', 50)->nullable()->unique()->after('id');
            $table->string('title', 255)->nullable()->after('ppmp_number');
            $table->unsignedBigInteger('division_id')->nullable()->after('title');
            $table->unsignedBigInteger('office_id')->nullable()->after('division_id');
            $table->unsignedBigInteger('prepared_by')->nullable()->after('office_id');
            $table->string('status', 20)->default('draft')->after('price');
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('consolidated_at')->nullable()->after('approved_at');
            $table->text('remarks')->nullable()->after('consolidated_at');
            $table->integer('fiscal_year')->nullable()->after('remarks');

            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
            $table->foreign('office_id')->references('id')->on('offices')->nullOnDelete();
            $table->foreign('prepared_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['fiscal_year', 'status']);
            $table->index(['division_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['office_id']);
            $table->dropForeign(['prepared_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['fiscal_year', 'status']);
            $table->dropIndex(['division_id', 'fiscal_year']);
            $table->dropColumn([
                'ppmp_number', 'title', 'division_id', 'office_id', 'prepared_by',
                'status', 'approved_by', 'submitted_at', 'approved_at',
                'consolidated_at', 'remarks', 'fiscal_year',
            ]);
        });
    }
};
