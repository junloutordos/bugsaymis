<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use App\Services\Atlas\AtlasModuleHealthService;
use Inertia\Inertia;

class AtlasModuleController extends Controller
{
    public function __construct(
        private readonly AtlasModuleHealthService $health,
    ) {}

    public function index()
    {
        $modules = $this->health->getAllModuleHealth();

        return Inertia::render('Atlas/ModulesDashboard', [
            'modules'        => $modules,
            'summary'        => $this->health->getSummary($modules),
            'integrations'   => $this->health->getIntegrationStatus(),
            'categoryLabels' => config('atlas-modules.category_labels'),
            'maturityLabels' => config('atlas-modules.maturity_labels'),
            'cachedAt'       => now('Asia/Manila')->toISOString(),
        ]);
    }

    public function refresh()
    {
        $this->health->clearCache();
        $modules = $this->health->getAllModuleHealth();

        return response()->json([
            'modules'      => $modules,
            'summary'      => $this->health->getSummary($modules),
            'integrations' => $this->health->getIntegrationStatus(),
            'cachedAt'     => now('Asia/Manila')->toISOString(),
        ]);
    }
}
