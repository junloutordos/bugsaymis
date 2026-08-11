<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('original_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The employee on leave/travel being substituted for');

            $table->foreignId('substitute_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The employee covering');

            $table->morphs('absentable'); // absentable_type, absentable_id — LeaveApplication or TravelRequest

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status', 30)->default('pending_approval');
            // pending_approval | approved | rejected | revoked

            $table->foreignId('nominated_by')->constrained('users')->cascadeOnDelete();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['original_user_id', 'status'], 'idx_sub_original_status');
            $table->index(['substitute_user_id', 'status'], 'idx_sub_substitute_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
