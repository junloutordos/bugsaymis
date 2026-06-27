<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_interns')) {
            return;
        }

        Schema::create('rh_interns', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id')->index();
            $table->unsignedBigInteger('school_year_id')->index();
            $table->unsignedBigInteger('rh_room_id')->nullable()->index();
            $table->string('bed_number', 10)->nullable();
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->decimal('lodging_fee_monthly', 8, 2)->default(0);
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->enum('status', ['active', 'checked_out', 'suspended', 'terminated'])
                  ->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_interns');
    }
};
