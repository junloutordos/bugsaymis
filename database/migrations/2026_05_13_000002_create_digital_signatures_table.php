<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');
            $table->foreignId('signer_id')->constrained('users');
            $table->string('document_hash', 64);
            $table->text('signature');
            $table->uuid('verification_token')->unique();
            $table->string('document_title');
            $table->json('metadata')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(['signable_type', 'signable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_signatures');
    }
};
