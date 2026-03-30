<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('facility_requests')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                // change venue from varchar to text to allow JSON payload
                $table->text('venue')->nullable()->change();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('facility_requests')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                $table->string('venue', 150)->nullable()->change();
            });
        }
    }
};
