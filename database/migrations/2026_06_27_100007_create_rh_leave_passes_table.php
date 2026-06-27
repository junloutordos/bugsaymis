<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_leave_passes')) {
            return;
        }

        Schema::create('rh_leave_passes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_intern_id')->index();
            $table->enum('purpose', ['go_home', 'school_activity', 'other']);
            $table->string('destination')->nullable();
            $table->boolean('with_companion')->default(false);
            $table->string('companion_name')->nullable();
            $table->string('companion_contact', 50)->nullable();
            $table->timestamp('student_signature_at')->nullable();

            // Approval
            $table->enum('status', ['pending', 'approved', 'rejected', 'departed', 'returned', 'overdue'])
                  ->default('pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('expected_return_at')->nullable();

            // Guard departure log
            $table->timestamp('actual_departure_at')->nullable();
            $table->unsignedBigInteger('guard_out_id')->nullable();

            // Guard return log
            $table->timestamp('actual_return_at')->nullable();
            $table->unsignedBigInteger('guard_in_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_leave_passes');
    }
};
