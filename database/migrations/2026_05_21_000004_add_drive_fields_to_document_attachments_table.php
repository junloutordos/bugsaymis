<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            $table->string('gdrive_file_id')->nullable()->after('file_path');
            $table->string('gdrive_link')->nullable()->after('gdrive_file_id');
            // Make file_path nullable so Drive-only attachments are valid
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            $table->dropColumn(['gdrive_file_id', 'gdrive_link']);
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
