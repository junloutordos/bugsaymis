<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_link_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_contact_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('relationship', 50)->default('guardian');
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending','confirmed','denied','cancelled','expired'])
                  ->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('parent_contact_id')
                  ->references('id')->on('parent_contacts')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_link_requests');
    }
};
