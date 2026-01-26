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
            // Rename requestor -> requestor_id when present
            if (Schema::hasColumn('consultations', 'requestor')) {
                try {
                    $table->renameColumn('requestor', 'requestor_id');
                } catch (\Exception $e) {
                    // rename may require doctrine/dbal; ignore so migration can continue
                }
            }

            // Ensure requestor_id is an integer-ready column (nullable unsigned big integer)
            if (Schema::hasColumn('consultations', 'requestor_id')) {
                try {
                    $table->unsignedBigInteger('requestor_id')->nullable()->change();
                } catch (\Exception $e) {
                    // altering column type may require doctrine/dbal; skip if not available
                }
            } else {
                // If the renamed column didn't exist, add the integer column
                $table->unsignedBigInteger('requestor_id')->nullable()->after('id');
            }

            // Drop old contact fields if present
            if (Schema::hasColumn('consultations', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('consultations', 'contact')) {
                $table->dropColumn('contact');
            }

            // New medical fields
            if (! Schema::hasColumn('consultations', 'temperature')) {
                $table->decimal('temperature', 5, 2)->nullable()->after('requestor_id');
            }
            if (! Schema::hasColumn('consultations', 'pulse_rate')) {
                $table->integer('pulse_rate')->nullable()->after('temperature');
            }
            if (! Schema::hasColumn('consultations', 'oxygen')) {
                $table->integer('oxygen')->nullable()->after('pulse_rate');
            }
            if (! Schema::hasColumn('consultations', 'blood_pressure')) {
                $table->string('blood_pressure')->nullable()->after('oxygen');
            }
            if (! Schema::hasColumn('consultations', 'action_taken')) {
                $table->text('action_taken')->nullable()->after('blood_pressure');
            }
            if (! Schema::hasColumn('consultations', 'medicine_given')) {
                $table->text('medicine_given')->nullable()->after('action_taken');
            }
            if (! Schema::hasColumn('consultations', 'time_out')) {
                $table->time('time_out')->nullable()->after('medicine_given');
            }
            if (! Schema::hasColumn('consultations', 'disposition')) {
                $table->string('disposition')->nullable()->after('time_out');
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
            // Drop the newly added medical fields if they exist
            $drop = [];
            foreach (['disposition','time_out','medicine_given','action_taken','blood_pressure','oxygen','pulse_rate','temperature'] as $col) {
                if (Schema::hasColumn('consultations', $col)) {
                    $drop[] = $col;
                }
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }

            // Re-create email and contact as nullable strings if they do not exist
            if (! Schema::hasColumn('consultations', 'email')) {
                $table->string('email')->nullable()->after('requestor_id');
            }
            if (! Schema::hasColumn('consultations', 'contact')) {
                $table->string('contact')->nullable()->after('email');
            }

            // Rename requestor_id back to requestor when present
            if (Schema::hasColumn('consultations', 'requestor_id')) {
                try {
                    $table->renameColumn('requestor_id', 'requestor');
                } catch (\Exception $e) {
                    // ignore if rename not possible in this environment
                }
            }
        });
    }
};
