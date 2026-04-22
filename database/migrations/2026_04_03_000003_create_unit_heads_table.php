<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_heads', function (Blueprint $table) {
            $table->id();

            // ── Core References ───────────────────────────────────────────────
            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->cascadeOnDelete()
                ->comment('The unit this person heads');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The employee designated as head');

            // ── Designation Details ───────────────────────────────────────────
            $table->string('designation_title')
                ->comment('Official designation title, e.g. Division Chief, Head, Director');

            $table->boolean('is_acting')->default(false)
                ->comment('True = Officer-in-Charge / Acting; False = regular designation');

            $table->boolean('is_current')->default(true)
                ->comment('Whether this is the currently active head designation');

            // ── Effectivity ───────────────────────────────────────────────────
            $table->date('effective_date')
                ->comment('Date designation takes effect');
            $table->date('end_date')->nullable()
                ->comment('NULL = currently serving; set when superseded or ended');

            // ── Documentary Basis ─────────────────────────────────────────────
            $table->string('designation_order', 100)->nullable()
                ->comment('Office Order / Administrative Order number for the designation');
            $table->string('designation_order_date')->nullable()
                ->comment('Date of the designation order document');

            $table->text('remarks')->nullable();

            // ── Audit ─────────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index('organizational_unit_id');
            $table->index('user_id');
            $table->index('is_current');
            $table->index('effective_date');
            $table->index(['organizational_unit_id', 'is_current'], 'idx_uh_unit_current');
            $table->index(['organizational_unit_id', 'end_date'],   'idx_uh_unit_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_heads');
    }
};
