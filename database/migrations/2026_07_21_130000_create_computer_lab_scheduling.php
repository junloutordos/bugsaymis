<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('requires_computer_lab')->default(false)->after('has_ilp');
        });

        Schema::create('computer_lab_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->unique()->constrained('class_schedules')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms')->cascadeOnDelete();
            $table->date('booking_date')->nullable();
            $table->string('day_of_week', 12)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('booking_type', 30)->default('other');
            $table->string('title', 180);
            $table->text('purpose')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('conflict_note')->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'day_of_week', 'start_time'], 'idx_lab_term_recurring');
            $table->index(['room_id', 'booking_date', 'start_time'], 'idx_lab_room_dated');
            $table->index(['booking_type', 'status'], 'idx_lab_type_status');
        });

        DB::table('subjects')
            ->where(function ($query) {
                $query->where('code', 'like', 'CS%')
                    ->orWhere('code', 'like', 'EL-CS%')
                    ->orWhere('code', 'like', 'EL-DS%')
                    ->orWhere('code', 'like', 'EL-DMT%')
                    ->orWhere('code', 'like', 'EL-DMIOT%')
                    ->orWhere('name', 'like', '%Computer Science%')
                    ->orWhere('name', 'like', '%Computer%')
                    ->orWhere('name', 'like', '%Data Science%')
                    ->orWhere('name', 'like', '%Design and Make Tech%')
                    ->orWhere('name', 'like', '%Design and Make: IoT%')
                    ->orWhere('name', 'like', '%Design and Make:IoT%');
            })
            ->update(['requires_computer_lab' => true]);

        $labs = DB::table('rooms')
            ->where('room_type', 'Computer Laboratory')
            ->get(['id', 'name', 'code']);

        DB::table('classrooms')
            ->where('classroom_type', 'ict_lab')
            ->whereNull('room_id')
            ->get(['id', 'name', 'code'])
            ->each(function ($classroom) use ($labs) {
                preg_match('/(\d+)\D*$/', $classroom->code ?: $classroom->name, $match);
                $number = $match[1] ?? null;
                if (! $number) {
                    return;
                }

                $room = $labs->first(function ($lab) use ($number) {
                    return preg_match('/'.$number.'\D*$/', $lab->code ?: $lab->name) === 1;
                });

                if ($room) {
                    DB::table('classrooms')->where('id', $classroom->id)->update(['room_id' => $room->id]);
                }
            });

        $now = now();
        foreach ([
            ['name' => 'computer_labs.book', 'description' => 'Request use of an available computer laboratory'],
            ['name' => 'computer_labs.manage', 'description' => 'Manage computer laboratory schedules and booking requests'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                ['module' => 'Computer Laboratories', 'description' => $permission['description'], 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $bookPermission = DB::table('permissions')->where('name', 'computer_labs.book')->value('id');
        $managePermission = DB::table('permissions')->where('name', 'computer_labs.manage')->value('id');

        $employeeRoles = [
            'HR', 'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
            'DivisionChief', 'OCD', 'PMT', 'Faculty', 'Staff', 'Registrar',
            'Records', 'Librarian', 'Nurse', 'Guidance', 'GSU Head',
            'InformationOfficer', 'Dorm Manager', 'CID Chief',
        ];

        $roleIds = DB::table('roles')->whereIn('name', $employeeRoles)->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $bookPermission, 'role_id' => $roleId]);
        }

        $managerRoleIds = DB::table('roles')->whereIn('name', ['MIS', 'CID Chief'])->pluck('id');
        foreach ($managerRoleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $managePermission, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['computer_labs.book', 'computer_labs.manage'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('computer_lab_bookings');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('requires_computer_lab');
        });
    }
};
