<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_ila_dates', function (Blueprint $table) {
            // Optional activity name a teacher can set before the date is
            // graded (e.g. "Reading Comprehension Worksheet") — surfaces in
            // the WAT print's Assessment column instead of the generic
            // "Independent Learning Activity" placeholder. Once graded, the
            // real class_record_assessments.title takes over instead.
            $table->string('title')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_ila_dates', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
