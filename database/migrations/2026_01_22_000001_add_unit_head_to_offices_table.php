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
        Schema::table('offices', function (Blueprint $table) {
            // nullable user id referencing users.id; indexed for lookups
            $table->unsignedBigInteger('unit_head')->nullable()->after('division_id');
            $table->index('unit_head');
            $table->foreign('unit_head')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('offices', function (Blueprint $table) {
            // drop foreign then index and column
            $table->dropForeign(['unit_head']);
            $table->dropIndex(['unit_head']);
            $table->dropColumn('unit_head');
        });
    }
};
