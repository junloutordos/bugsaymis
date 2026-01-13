<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('library_collections') && Schema::hasColumn('library_collections', 'accession_number')) {
            Schema::table('library_collections', function (Blueprint $table) {
                // drop index/unique first if exists
                try {
                    $table->dropUnique(['accession_number']);
                } catch (\Throwable $e) {
                    // ignore if index doesn't exist
                }
                $table->dropColumn('accession_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('library_collections') && ! Schema::hasColumn('library_collections', 'accession_number')) {
            Schema::table('library_collections', function (Blueprint $table) {
                $table->string('accession_number')->nullable()->unique()->after('title');
            });
        }
    }
};
