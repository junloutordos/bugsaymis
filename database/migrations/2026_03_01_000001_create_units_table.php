<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Insert provided units
        $units = [
            'Bottle',
            'Box',
            'Bundle',
            'Can',
            'Carton',
            'Case',
            'Centimeter (cm)',
            'Contract',
            'Cubic meter (m³)',
            'Day',
            'Dozen',
            'Foot (ft)',
            'Gallon',
            'Gram (g)',
            'Gross',
            'Hour',
            'Inch (in)',
            'Jar',
            'Job',
            'Kilogram (kg)',
            'Linear meter',
            'Liter (L)',
            'Lot',
            'Meter (m)',
            'Milligram (mg)',
            'Milliliter (mL)',
            'Millimeter (mm)',
            'Month',
            'Pack',
            'Pair',
            'Piece (pc)',
            'Pint',
            'Pound (lb)',
            'Project',
            'Quart',
            'Ream',
            'Roll',
            'Sack',
            'Session',
            'Set',
            'Square foot (sqft)',
            'Square meter (sqm)',
            'Ton',
            'Trip',
            'Tube',
            'Unit',
            'Week',
            'Yard',
            'Year',
        ];

        $now = now();
        $rows = array_map(function($name) use ($now) {
            return ['name' => $name, 'created_at' => $now, 'updated_at' => $now];
        }, $units);

        DB::table('units')->insert($rows);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('units');
    }
};
