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
        Schema::table('consultations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('consultations', 'unit')) {
                $cols[] = 'unit';
            }
            if (Schema::hasColumn('consultations', 'notes')) {
                $cols[] = 'notes';
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'unit')) {
                $table->string('unit')->nullable();
            }
            if (! Schema::hasColumn('consultations', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }
};
