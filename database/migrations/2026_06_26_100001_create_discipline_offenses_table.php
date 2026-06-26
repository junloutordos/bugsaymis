<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipline_offenses')) {
            return;
        }

        Schema::create('discipline_offenses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();      // e.g. CC-1-01
            $table->string('category')->nullable();           // e.g. Uniform & Grooming, Academic Integrity
            $table->unsignedTinyInteger('level')->default(1);  // 1 | 2 | 3 per Code of Conduct
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('default_sanction')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['level', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_offenses');
    }
};
