<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_waivers')) {
            return;
        }

        Schema::create('rh_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_intern_id')->unique()->index();
            $table->boolean('can_go_home_alone')->default(false);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_contact', 50)->nullable();
            $table->timestamp('signed_by_student_at')->nullable();
            $table->timestamp('signed_by_guardian_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_waivers');
    }
};
