<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\Concerns\ResolvesDivisionLens;
use App\Services\ExecutiveDashboardService;

abstract class ExecutiveDashboardAdapterTool implements DynaTool
{
    use ResolvesDivisionLens;

    public function __construct(private readonly ExecutiveDashboardService $dashboard) {}

    abstract protected function sectionKey(): string;

    /** Whether this section's data actually varies by division (controls inputSchema only). */
    abstract protected function exposesDivisionFilter(): bool;

    public function inputSchema(): array
    {
        if (! $this->exposesDivisionFilter()) {
            return ['type' => 'object', 'properties' => (object) []];
        }

        return [
            'type' => 'object',
            'properties' => [
                'division_id' => [
                    'type' => 'integer',
                    'description' => 'Optional division ID to filter to (Administrator/OCD only — a Division Chief is always locked to their own division).',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $divisionId = $this->resolveDivisionId($user, $input['division_id'] ?? null);
        $section = $this->dashboard->build($divisionId)[$this->sectionKey()];

        // Only `scorecard` can legitimately be null (campus-lens only, per
        // ExecutiveDashboardService::build()) — the DynaTool interface requires an array
        // return, so a plain `?? []` here would silently hide *why* it's empty. Every other
        // section key is never null, so this fallback is unreachable for them.
        $section ??= ['note' => 'Not available for a division-locked view — this section is campus-wide only.'];

        // ExecutiveDashboardService's sections carry raw Collection/Carbon instances — fine
        // for Inertia's response pipeline, but Bedrock's Converse toolResult validator only
        // accepts null/numeric/string/bool/array, so anything else must be plain-arrayed first.
        return json_decode(json_encode($section), true);
    }
}
