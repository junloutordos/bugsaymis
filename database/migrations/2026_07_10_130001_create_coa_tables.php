<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_visits', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 500);
            $table->string('office_visited')->nullable();
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft'); // draft | issued
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('coa_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coa_visit_id')->constrained('coa_visits')->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('organization')->nullable();
            $table->string('position')->nullable();
            $table->string('email');
            // Assigned at issue time — NULL while the visit is a draft
            $table->string('control_number')->nullable()->unique();
            $table->uuid('qr_token')->unique();
            $table->string('content_hash', 64)->nullable();
            $table->string('email_status', 20)->default('pending'); // pending | sent | failed
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_certificates');
        Schema::dropIfExists('coa_visits');
    }
};
