<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lost & Found module (GSU custody).
     *
     * lost_found_items — one row per report.
     *   type 'lost'  → owner reports a lost item
     *                  status: open → matched → claimed | closed
     *   type 'found' → finder reports/turns over a found item
     *                  status: pending_turnover → in_custody → claimed | disposed
     *   Reporter is an employee (reporter_user_id), a student
     *   (reporter_student_id — legacy MyISAM table, no FK), or a walk-in
     *   visitor (reporter_name only).
     *
     * lost_found_movements — immutable custody/event trail per item.
     *
     * honesty_points — ledger; rows are written only by LostFoundService
     * when GSU confirms physical receipt of a found item.
     */
    public function up(): void
    {
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['lost', 'found']);
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('location')->nullable()->comment('Where the item was lost/found');
            $table->date('date_lost_found');
            $table->string('photo_path')->nullable()->comment('S3 key');

            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reporter_student_id')->nullable()->comment('FK to legacy students.id (MyISAM, no constraint)');
            $table->string('reporter_name')->nullable()->comment('Walk-in / visitor finder');

            $table->string('status', 30)->comment('lost: open|matched|claimed|closed; found: pending_turnover|in_custody|claimed|disposed');
            $table->string('storage_location')->nullable()->comment('GSU shelf/box label');
            $table->foreignId('matched_item_id')->nullable()->constrained('lost_found_items')->nullOnDelete()
                ->comment('lost report ↔ found item link');

            $table->timestamp('turned_over_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->string('claimed_by_name')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('reporter_user_id');
            $table->index('reporter_student_id');
        });

        Schema::create('lost_found_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_found_item_id')->constrained('lost_found_items')->cascadeOnDelete();
            $table->string('action', 30)->comment('reported|turned_over|received|stored|matched|claimed|released|disposed|closed|updated');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('actor_student_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('lost_found_item_id');
        });

        Schema::create('honesty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable()->comment('FK to legacy students.id (MyISAM, no constraint)');
            $table->unsignedSmallInteger('points');
            $table->string('reason', 60)->default('found_item_turnover');
            $table->foreignId('lost_found_item_id')->nullable()->constrained('lost_found_items')->nullOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honesty_points');
        Schema::dropIfExists('lost_found_movements');
        Schema::dropIfExists('lost_found_items');
    }
};
