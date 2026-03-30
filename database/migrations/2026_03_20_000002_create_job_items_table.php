<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_type_id')->constrained('recruitment_types')->restrictOnDelete();
            $table->string('position_title');
            $table->string('plantilla_item_no')->nullable()->index();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->string('employment_type'); // plantilla, cos, jo, outsourced, gip, ojt
            $table->string('education_level')->nullable();
            $table->string('subject_area')->nullable();
            $table->string('duration_type')->nullable(); // permanent, contractual, seasonal
            $table->string('budget_source')->nullable(); // DBM, CHED, local
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'published', 'closed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['recruitment_type_id', 'status']);
            $table->index('office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_items');
    }
};
