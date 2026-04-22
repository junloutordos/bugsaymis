<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_item_id')->constrained('job_items')->restrictOnDelete();
            $table->date('posting_date');
            $table->date('closing_date');
            $table->string('publication_type')->default('internal'); // internal, external, both
            $table->enum('status', ['open', 'closed', 'cancelled', 'filled'])->default('open');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'closing_date']);
            $table->index('job_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
    }
};
