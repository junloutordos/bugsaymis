<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('library_collections')) {
            Schema::table('library_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('library_collections', 'year')) {
                    $table->string('year')->nullable()->after('edition');
                }
                if (! Schema::hasColumn('library_collections', 'isbn')) {
                    $table->string('isbn')->nullable()->after('year');
                }
                if (! Schema::hasColumn('library_collections', 'subject')) {
                    $table->string('subject')->nullable()->after('isbn');
                }
                if (! Schema::hasColumn('library_collections', 'category')) {
                    $table->string('category')->nullable()->after('subject');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('library_collections')) {
            Schema::table('library_collections', function (Blueprint $table) {
                if (Schema::hasColumn('library_collections', 'year')) {
                    $table->dropColumn('year');
                }
                if (Schema::hasColumn('library_collections', 'isbn')) {
                    $table->dropColumn('isbn');
                }
                if (Schema::hasColumn('library_collections', 'subject')) {
                    $table->dropColumn('subject');
                }
                if (Schema::hasColumn('library_collections', 'category')) {
                    $table->dropColumn('category');
                }
            });
        }
    }
};
