<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')
            ->where('room_type', 'Laboratory')
            ->where('name', 'like', 'Computer Laboratory Room%')
            ->update(['room_type' => 'Computer Laboratory']);
    }

    public function down(): void
    {
        DB::table('rooms')
            ->where('room_type', 'Computer Laboratory')
            ->where('name', 'like', 'Computer Laboratory Room%')
            ->update(['room_type' => 'Laboratory']);
    }
};
