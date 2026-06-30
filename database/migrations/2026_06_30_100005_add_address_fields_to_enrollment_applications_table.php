<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('enrollment_applications', function (Blueprint $table) {
            $table->string('address_house', 200)->nullable()->after('address');
            $table->string('address_street', 200)->nullable()->after('address_house');
            $table->string('address_subdivision', 200)->nullable()->after('address_street');
            $table->string('address_barangay', 100)->nullable()->after('address_subdivision');
            $table->string('address_city', 100)->nullable()->after('address_barangay');
            $table->string('address_province', 100)->nullable()->after('address_city');
            $table->string('address_region', 100)->nullable()->after('address_province');
            $table->string('address_zip', 10)->nullable()->after('address_region');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_applications', function (Blueprint $table) {
            $table->dropColumn([
                'address_house', 'address_street', 'address_subdivision',
                'address_barangay', 'address_city', 'address_province',
                'address_region', 'address_zip',
            ]);
        });
    }
};
