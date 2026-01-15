<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->integer('copies')->nullable();
            $table->integer('sheets_per_set')->nullable();
            $table->date('date_needed');
            $table->time('time_needed')->nullable();
            $table->text('purposes')->nullable();
            $table->text('details')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedBigInteger('requestor_id')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
