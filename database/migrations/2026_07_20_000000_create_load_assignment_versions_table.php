<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_assignment_versions', function (Blueprint $table) {
            $table->id();

            // Scope — whole term, every assignment type (teaching, research,
            // admin, cocurricular, committee) — see LoadAssignmentVersionService.
            $table->unsignedBigInteger('academic_term_id');

            // User-given checkpoint label + optional description.
            $table->string('label', 100);
            $table->text('notes')->nullable();

            // Every load_assignments row for the term at save time, as raw
            // attributes (JSON array of row-dicts) — same pattern as
            // schedule_versions.schedule_snapshot.
            $table->longText('assignment_snapshot');

            // Cached count so the list view doesn't need to decode the JSON.
            $table->integer('assignment_count')->default(0);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index('academic_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_assignment_versions');
    }
};
