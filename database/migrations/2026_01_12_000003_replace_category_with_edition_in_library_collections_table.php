<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('library_collections')) {
            Schema::table('library_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('library_collections', 'edition')) {
                    $table->string('edition')->nullable()->after('call_number');
                }
                if (Schema::hasColumn('library_collections', 'category')) {
                    $table->dropColumn('category');
                }
            });

            // Backfill edition from any existing column or leave null (no-op)
            try {
                // if collection_type contains edition-like value, consider migrating, otherwise skip
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('library_collections')) {
            Schema::table('library_collections', function (Blueprint $table) {
                if (Schema::hasColumn('library_collections', 'edition')) {
                    $table->dropColumn('edition');
                }
                if (! Schema::hasColumn('library_collections', 'category')) {
                    $table->string('category')->nullable()->after('call_number');
                }
            });
        }
    }
};
