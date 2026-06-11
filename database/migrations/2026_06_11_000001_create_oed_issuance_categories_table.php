<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oed_issuance_categories', function (Blueprint $table) {
            $table->string('code', 30)->primary();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('oed_issuance_categories')->insert([
            ['code' => 'MEMO',  'label' => 'Memorandum',           'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MC',    'label' => 'Memorandum Circular',  'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OO',    'label' => 'Office Order',         'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SO',    'label' => 'Special Order',        'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AO',    'label' => 'Administrative Order', 'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EO',    'label' => 'Executive Order',      'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BR',    'label' => 'Board Resolution',     'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ADV',   'label' => 'Advisory',             'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GUIDE', 'label' => 'Guidelines',           'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTHER', 'label' => 'Other',                'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('oed_issuance_categories');
    }
};
