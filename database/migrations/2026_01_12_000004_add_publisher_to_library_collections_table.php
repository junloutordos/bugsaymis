<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('library_collections') && ! Schema::hasColumn('library_collections', 'publisher')) {
            Schema::table('library_collections', function (Blueprint $table) {
                $table->string('publisher')->nullable()->after('author_publisher');
            });

            // Optionally backfill publisher from author_publisher or leave null
            try {
                DB::table('library_collections')->whereNull('publisher')->update(['publisher' => DB::raw('author_publisher')]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('library_collections') && Schema::hasColumn('library_collections', 'publisher')) {
            Schema::table('library_collections', function (Blueprint $table) {
                $table->dropColumn('publisher');
            });
        }
    }
};
