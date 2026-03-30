<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->string('drive_file_id')->nullable()->after('original_name');
            $table->string('drive_url')->nullable()->after('drive_file_id');
            $table->unsignedBigInteger('file_size')->nullable()->after('drive_url'); // bytes
            $table->string('mime_type')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'drive_file_id', 'drive_url', 'file_size', 'mime_type']);
        });
    }
};
