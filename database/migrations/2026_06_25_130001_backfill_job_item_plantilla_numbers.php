<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_items', 'plantilla_item_no')) {
            return;
        }

        $jobItems = DB::table('job_items')
            ->whereNotNull('plantilla_item_no')
            ->where('plantilla_item_no', '<>', '')
            ->get(['id', 'plantilla_item_no']);

        foreach ($jobItems as $jobItem) {
            $placement = DB::table('placements')
                ->join('applications', 'placements.application_id', '=', 'applications.id')
                ->join('job_vacancies', 'applications.job_vacancy_id', '=', 'job_vacancies.id')
                ->where('job_vacancies.job_item_id', $jobItem->id)
                ->whereIn('placements.status', ['pending', 'active', 'completed'])
                ->orderByDesc('placements.id')
                ->first(['placements.id']);

            DB::table('job_item_plantilla_numbers')->insert([
                'job_item_id'       => $jobItem->id,
                'plantilla_item_no' => $jobItem->plantilla_item_no,
                'status'            => $placement ? 'filled' : 'vacant',
                'placement_id'      => $placement->id ?? null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('job_item_plantilla_numbers')->truncate();
    }
};
