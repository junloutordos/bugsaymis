<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use App\Models\Atlas\AtlasModuleSetting;
use App\Models\User;
use App\Services\Atlas\AtlasMaturityService;
use App\Services\Atlas\AtlasMetricsService;
use App\Services\Atlas\AtlasModuleHealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AtlasModuleController extends Controller
{
    public function __construct(
        private readonly AtlasModuleHealthService $health,
        private readonly AtlasMetricsService      $metrics,
        private readonly AtlasMaturityService     $maturity,
    ) {}

    public function index()
    {
        $modules = $this->health->getAllModuleHealth();

        // Load stored overall maturity scores keyed by module_key for card display
        $storedScores = \App\Models\Atlas\AtlasModuleMaturityScore::selectRaw(
            'module_key, AVG(score) as avg_score'
        )->groupBy('module_key')->pluck('avg_score', 'module_key')->toArray();

        $modulesWithMaturity = array_map(function ($m) use ($storedScores) {
            $m['maturity_overall'] = isset($storedScores[$m['key']])
                ? round($storedScores[$m['key']], 1)
                : null;
            return $m;
        }, $modules);

        return Inertia::render('Atlas/ModulesDashboard', [
            'modules'            => $modulesWithMaturity,
            'summary'            => $this->health->getSummary($modules),
            'integrations'       => $this->health->getIntegrationStatus(),
            'categoryLabels'     => config('atlas-modules.category_labels'),
            'maturityLabels'     => config('atlas-modules.maturity_labels'),
            'maturityDimensions' => config('atlas-modules.maturity_dimensions'),
            'cachedAt'           => now('Asia/Manila')->toISOString(),
        ]);
    }

    public function refresh()
    {
        $this->health->clearCache();
        $modules = $this->health->getAllModuleHealth();

        $storedScores = \App\Models\Atlas\AtlasModuleMaturityScore::selectRaw(
            'module_key, AVG(score) as avg_score'
        )->groupBy('module_key')->pluck('avg_score', 'module_key')->toArray();

        $modulesWithMaturity = array_map(function ($m) use ($storedScores) {
            $m['maturity_overall'] = isset($storedScores[$m['key']])
                ? round($storedScores[$m['key']], 1)
                : null;
            return $m;
        }, $modules);

        return response()->json([
            'modules'      => $modulesWithMaturity,
            'summary'      => $this->health->getSummary($modules),
            'integrations' => $this->health->getIntegrationStatus(),
            'cachedAt'     => now('Asia/Manila')->toISOString(),
        ]);
    }

    public function moduleMetrics(string $key)
    {
        $modules = $this->health->getAllModuleHealth();
        $module  = collect($modules)->firstWhere('key', $key);

        if (! $module) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        $metrics  = $this->metrics->getModuleMetrics($module);
        $radar    = $this->maturity->getRadarData($key);
        $scores   = $this->maturity->getScores($key);
        $overall  = $this->maturity->getOverallScore($key);
        $settings = AtlasModuleSetting::with('owner:id,name')->find($key)
            ?? new AtlasModuleSetting(['module_key' => $key, 'version' => '1.0.0']);

        $users = User::employees()->where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'module'     => $module,
            'metrics'    => $metrics,
            'maturity'   => ['radar' => $radar, 'scores' => $scores, 'overall' => $overall],
            'settings'   => $settings,
            'dimensions' => config('atlas-modules.maturity_dimensions'),
            'users'      => $users,
        ]);
    }

    public function saveMaturity(Request $request, string $key)
    {
        $validated = $request->validate([
            'dimension'         => 'required|string|in:functional,technical,security,ai_readiness,data_quality,ux,governance',
            'criteria_checks'   => 'required|array',
            'criteria_checks.*' => 'boolean',
            'notes'             => 'nullable|string|max:500',
        ]);

        $score = $this->maturity->upsertScore(
            $key,
            $validated['dimension'],
            $validated['criteria_checks'],
            $validated['notes'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'score'   => $score,
            'radar'   => $this->maturity->getRadarData($key),
            'overall' => $this->maturity->getOverallScore($key),
        ]);
    }

    public function saveSettings(Request $request, string $key)
    {
        $validated = $request->validate([
            'owner_id'  => 'nullable|exists:users,id',
            'sla_hours' => 'nullable|integer|min:1|max:8760',
            'version'   => 'nullable|string|max:20',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $settings = AtlasModuleSetting::updateOrCreate(
            ['module_key' => $key],
            $validated
        );

        $this->metrics->clearModuleCache($key);

        return response()->json([
            'settings' => $settings->load('owner:id,name'),
        ]);
    }

}
