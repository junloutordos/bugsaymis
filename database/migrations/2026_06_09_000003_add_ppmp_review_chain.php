<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->timestamp('property_officer_reviewed_at')->nullable()->after('division_remarks');
            $table->unsignedBigInteger('property_officer_reviewed_by')->nullable()->after('property_officer_reviewed_at');
            $table->text('property_officer_remarks')->nullable()->after('property_officer_reviewed_by');

            $table->timestamp('budget_officer_reviewed_at')->nullable()->after('property_officer_remarks');
            $table->unsignedBigInteger('budget_officer_reviewed_by')->nullable()->after('budget_officer_reviewed_at');
            $table->text('budget_officer_remarks')->nullable()->after('budget_officer_reviewed_by');

            $table->foreign('property_officer_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('budget_officer_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Create Property Officer role
        $propertyOfficerRoleId = DB::table('roles')->insertGetId([
            'name'       => 'Property Officer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed ppmp.property_officer_review permission
        $propPermId = DB::table('permissions')->insertGetId([
            'name'        => 'ppmp.property_officer_review',
            'module'      => 'PPMP',
            'description' => 'Review and endorse Division PPMP submissions as Property Officer',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Seed ppmp.budget_officer_review permission
        $budgetPermId = DB::table('permissions')->insertGetId([
            'name'        => 'ppmp.budget_officer_review',
            'module'      => 'PPMP',
            'description' => 'Review and endorse Division PPMP submissions as Budget Officer',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Assign ppmp.property_officer_review → Property Officer
        DB::table('permission_role')->insert([
            'permission_id' => $propPermId,
            'role_id'       => $propertyOfficerRoleId,
        ]);

        // Assign ppmp.budget_officer_review → Budget Officer (id=25)
        $budgetRoleId = DB::table('roles')->where('name', 'Budget Officer')->value('id');
        if ($budgetRoleId) {
            DB::table('permission_role')->insert([
                'permission_id' => $budgetPermId,
                'role_id'       => $budgetRoleId,
            ]);
        }

        // Assign ppmp.approve → OCD (id=6) if not already assigned
        $approvePermId = DB::table('permissions')->where('name', 'ppmp.approve')->value('id');
        $ocdRoleId     = DB::table('roles')->where('name', 'OCD')->value('id');
        if ($approvePermId && $ocdRoleId) {
            $alreadyAssigned = DB::table('permission_role')
                ->where('permission_id', $approvePermId)
                ->where('role_id', $ocdRoleId)
                ->exists();
            if (!$alreadyAssigned) {
                DB::table('permission_role')->insert([
                    'permission_id' => $approvePermId,
                    'role_id'       => $ocdRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['property_officer_reviewed_by']);
            $table->dropForeign(['budget_officer_reviewed_by']);
            $table->dropColumn([
                'property_officer_reviewed_at', 'property_officer_reviewed_by', 'property_officer_remarks',
                'budget_officer_reviewed_at',   'budget_officer_reviewed_by',   'budget_officer_remarks',
            ]);
        });

        $permIds = DB::table('permissions')
            ->whereIn('name', ['ppmp.property_officer_review', 'ppmp.budget_officer_review'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('name', ['ppmp.property_officer_review', 'ppmp.budget_officer_review'])->delete();
        DB::table('roles')->where('name', 'Property Officer')->delete();
    }
};
