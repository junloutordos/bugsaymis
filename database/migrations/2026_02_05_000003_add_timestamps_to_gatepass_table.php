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
        if (Schema::hasTable('gatepass')) {
            Schema::table('gatepass', function (Blueprint $table) {
                if (!Schema::hasColumn('gatepass', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('gatepass', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
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
        if (Schema::hasTable('gatepass')) {
            Schema::table('gatepass', function (Blueprint $table) {
                if (Schema::hasColumn('gatepass', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
                if (Schema::hasColumn('gatepass', 'created_at')) {
                    $table->dropColumn('created_at');
                }
            });
        }
    }
};
