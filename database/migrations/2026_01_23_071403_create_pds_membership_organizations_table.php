<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_membership_organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pds_id');
            $table->string('organization_name');
            $table->timestamps();

            $table->foreign('pds_id')->references('id')->on('pds')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_membership_organizations');
    }
};

