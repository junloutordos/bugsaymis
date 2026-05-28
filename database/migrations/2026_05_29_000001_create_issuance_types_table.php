<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuance_types', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->tinyInteger('series_padding')->unsigned()->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('issuance_types')->insert([
            ['code' => 'SO',     'label' => 'Special Order',       'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'TO',     'label' => 'Travel Order',         'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MEMO',   'label' => 'Memorandum',           'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OO',     'label' => 'Office Order',         'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AO',     'label' => 'Administrative Order', 'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CIRC',   'label' => 'Circular',             'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NOTICE', 'label' => 'Notice',               'description' => null, 'series_padding' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_types');
    }
};
