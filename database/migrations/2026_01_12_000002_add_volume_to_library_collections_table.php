<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('library_collections') && ! Schema::hasColumn('library_collections', 'volume')) {
            Schema::table('library_collections', function (Blueprint $table) {
                $table->string('volume')->nullable()->after('collection_type');
            });

            // backfill existing rows from collection_type
            try {
                DB::table('library_collections')->whereNull('volume')->update(['volume' => DB::raw('collection_type')]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('library_collections') && Schema::hasColumn('library_collections', 'volume')) {
            Schema::table('library_collections', function (Blueprint $table) {
                $table->dropColumn('volume');
            });
        }
    }
};
