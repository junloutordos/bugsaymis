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
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('division_name'); // Division name

            // Division Chief (foreign key from users.id)
            $table->unsignedBigInteger('division_chief_id')->nullable();
            $table->foreign('division_chief_id')->references('id')->on('users')->nullOnDelete();

            $table->year('year')->nullable(); // Year assigned/established
            $table->enum('status', ['active', 'not_active'])->default('active'); // Chief status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
