<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_parent_contact', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('parent_contact_id');
            $table->string('relationship', 50)->default('guardian')->comment('mother, father, guardian, etc.');

            $table->primary(['student_id', 'parent_contact_id']);
            // students table is MyISAM — FK not supported; using plain index
            $table->index('student_id');
            $table->foreign('parent_contact_id')->references('id')->on('parent_contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_parent_contact');
    }
};
