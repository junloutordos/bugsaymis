<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetDivisionScorecardTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_division_scorecard'; }
    public function description(): string { return 'Returns a per-division rollup comparison (headcount, leave days/employee, IPCR submission rate, request completion rate). Only populated for a campus-wide view (Administrator/OCD) — empty for a Division Chief, since this is inherently a cross-division comparison.'; }
    protected function sectionKey(): string { return 'scorecard'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
