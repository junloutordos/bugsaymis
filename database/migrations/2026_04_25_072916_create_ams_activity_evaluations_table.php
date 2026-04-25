<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();

            // Who evaluated
            $table->enum('participant_type', ['employee', 'student']);
            $table->unsignedBigInteger('participant_id');
            $table->string('evaluator_name')->nullable();

            // Section A — Objectives (4 items, includes not_applicable)
            $likertFull = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
            $table->enum('obj_1', $likertFull)->nullable()->comment('Aligned with vision and mission');
            $table->enum('obj_2', $likertFull)->nullable()->comment('Relevant to needs of participants');
            $table->enum('obj_3', $likertFull)->nullable()->comment('Contributed to development of participants');
            $table->enum('obj_4', $likertFull)->nullable()->comment('Objectives were achieved');

            // Section B — Management (6 items, includes not_applicable)
            $table->enum('mgmt_1', $likertFull)->nullable()->comment('Organizers were visible and responsive');
            $table->enum('mgmt_2', $likertFull)->nullable()->comment('Coordination and flow were clear');
            $table->enum('mgmt_3', $likertFull)->nullable()->comment('Time was managed effectively');
            $table->enum('mgmt_4', $likertFull)->nullable()->comment('Transitions were smooth');
            $table->enum('mgmt_5', $likertFull)->nullable()->comment('Activity was well-organized');
            $table->enum('mgmt_6', $likertFull)->nullable()->comment('Participants were actively engaged');

            // Section C — Physical Arrangements (3 items, no not_applicable)
            $likertBase = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree'];
            $table->enum('phys_1', $likertBase)->nullable()->comment('Venue and materials were ready and adequate');
            $table->enum('phys_2', $likertBase)->nullable()->comment('Sound system and equipment functioned properly');
            $table->enum('phys_3', $likertBase)->nullable()->comment('Participants were properly guided within the venue');

            // Open-ended
            $table->text('suggestions')->nullable();
            $table->text('other_comments')->nullable();

            $table->timestamps();

            // One evaluation per participant per activity
            $table->unique(['activity_id', 'participant_type', 'participant_id'], 'uq_eval_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_evaluations');
    }
};
