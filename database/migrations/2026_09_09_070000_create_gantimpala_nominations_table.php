<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRAISE — Gantimpala Agad Award (Nomination Form 4).
     * Instant/spot recognition triggered by a commendation from an internal
     * Atlas user OR an external party via the public kiosk. No committee
     * evaluation/scoring cycle — goes straight to HR review → endorsement → decision.
     */
    public function up(): void
    {
        Schema::create('gantimpala_nominations', function (Blueprint $table) {
            $table->id();

            // ── Source / origin ──────────────────────────────────────────────
            $table->enum('source', ['atlas', 'kiosk'])->default('atlas');

            // ── Employee/Unit Commended ──────────────────────────────────────
            $table->foreignId('nominee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nominee_name');               // free text — may be a unit/office, not a person
            $table->string('nominee_title')->nullable();
            $table->string('nominee_department')->nullable();
            $table->string('nominee_address')->nullable();
            $table->string('nominee_contact_number')->nullable();
            $table->date('date_submitted');

            // ── Person/Entity Providing Commendation (Nominator) ─────────────
            $table->foreignId('nominator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nominator_name');
            $table->string('nominator_address')->nullable();
            $table->string('nominator_contact_number')->nullable();
            $table->string('nominator_email')->nullable();

            // ── Details of Commendable Action ────────────────────────────────
            $table->string('activity_conducted');          // Activity Conducted / Service Provided
            $table->date('activity_date')->nullable();
            $table->string('venue_location')->nullable();
            $table->text('other_information')->nullable(); // brief narrative of the commendable action

            // ── Certification / Signatures ───────────────────────────────────
            $table->string('nominator_signature_path')->nullable();   // S3 key, base64 PNG from signature pad
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_signature_path')->nullable();  // S3 key
            $table->timestamp('endorsed_at')->nullable();
            $table->foreignId('endorsed_by')->nullable()->constrained('users')->nullOnDelete();

            // ── HR Review / Decision ─────────────────────────────────────────
            $table->enum('status', ['pending', 'under_review', 'endorsed', 'approved', 'rejected', 'archived'])
                ->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // ── Kiosk abuse-mitigation metadata ───────────────────────────────
            $table->string('submitted_ip', 64)->nullable();
            $table->string('kiosk_device')->nullable();

            // ── Generated PDF (Form 4 replica) ───────────────────────────────
            $table->string('pdf_path')->nullable();
            $table->string('reference_no')->unique()->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gantimpala_nominations');
    }
};
