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
            $table->timestamp('division_reviewed_at')->nullable()->after('bac_remarks');
            $table->unsignedBigInteger('division_reviewed_by')->nullable()->after('division_reviewed_at');
            $table->text('division_remarks')->nullable()->after('division_reviewed_by');

            $table->foreign('division_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed the ppmp.division_review permission
        $permId = DB::table('permissions')->insertGetId([
            'name'        => 'ppmp.division_review',
            'module'      => 'PPMP',
            'description' => 'Review and endorse unit PPMP submissions as Division Chief',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Assign to DivisionChief role
        $roleId = DB::table('roles')->whereIn('name', ['DivisionChief', 'Division Chief'])->value('id');
        if ($roleId) {
            DB::table('permission_role')->insert([
                'permission_id' => $permId,
                'role_id'       => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['division_reviewed_by']);
            $table->dropColumn(['division_reviewed_at', 'division_reviewed_by', 'division_remarks']);
        });

        DB::table('permission_role')
            ->whereIn('permission_id', DB::table('permissions')->where('name', 'ppmp.division_review')->pluck('id'))
            ->delete();
        DB::table('permissions')->where('name', 'ppmp.division_review')->delete();
    }
};
