<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('work_requests', 'requires_pre_repair_inspection')) {
                $table->boolean('requires_pre_repair_inspection')->default(false)->after('status');
            }
        });

        if (! Schema::hasTable('work_request_pre_repair_inspections')) {
            Schema::create('work_request_pre_repair_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_request_id')->constrained('work_requests')->cascadeOnDelete();
                $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('noted_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('draft');
                $table->string('asset_type')->nullable();
                $table->string('building_facility')->nullable();
                $table->string('property_no')->nullable();
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->string('serial_no')->nullable();
                $table->date('date_of_purchase')->nullable();
                $table->decimal('purchase_cost', 14, 2)->nullable();
                $table->string('warranty_status')->nullable();
                $table->text('location_of_defect')->nullable();
                $table->text('nature_of_defect')->nullable();
                $table->text('cause_of_defect')->nullable();
                $table->text('recommendations')->nullable();
                $table->json('material_items')->nullable();
                $table->json('labor_items')->nullable();
                $table->decimal('total_material_cost', 14, 2)->nullable();
                $table->decimal('total_labor_cost', 14, 2)->nullable();
                $table->decimal('indirect_cost', 14, 2)->nullable();
                $table->decimal('total_estimated_repair_cost', 14, 2)->nullable();
                $table->timestamp('inspected_at')->nullable();
                $table->timestamp('noted_at')->nullable();
                $table->text('return_reason')->nullable();
                $table->timestamps();

                $table->unique('work_request_id', 'work_request_pre_repair_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_request_pre_repair_inspections');

        Schema::table('work_requests', function (Blueprint $table) {
            if (Schema::hasColumn('work_requests', 'requires_pre_repair_inspection')) {
                $table->dropColumn('requires_pre_repair_inspection');
            }
        });
    }
};
