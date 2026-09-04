<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DostChainController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'pillar.mode' => 'required|in:existing,new,skip',
            'pillar.id' => 'required_if:pillar.mode,existing|nullable|exists:dost_pillars,id',
            'pillar.name' => 'required_if:pillar.mode,new|nullable|string|max:255',
            'pillar.outcome_statement' => 'nullable|string',

            'strategy.mode' => 'required|in:existing,new,skip',
            'strategy.id' => 'required_if:strategy.mode,existing|nullable|exists:dost_strategies,id',
            'strategy.name' => 'required_if:strategy.mode,new|nullable|string|max:255',

            'sub_strategy.mode' => 'required|in:existing,new,skip',
            'sub_strategy.id' => 'required_if:sub_strategy.mode,existing|nullable|exists:dost_sub_strategies,id',
            'sub_strategy.description' => 'required_if:sub_strategy.mode,new|nullable|string',

            'program.mode' => 'required|in:existing,new,skip',
            'program.id' => 'required_if:program.mode,existing|nullable|exists:agency_org_outcomes,id',
            'program.outcome' => 'required_if:program.mode,new|nullable|string|max:255',
            'program.function_type' => 'required_if:program.mode,new|nullable|string|max:255',
        ]);

        $result = DB::transaction(function () use ($data) {
            $programId = $this->resolveProgram($data['program']);
            $pillarId = $this->resolvePillar($data['pillar']);
            $strategyId = $this->resolveStrategy($data['strategy'], $pillarId);
            $subStrategyId = $this->resolveSubStrategy($data['sub_strategy'], $strategyId);

            if ($programId && $pillarId) {
                DostPillar::find($pillarId)->agencyOutcomes()->syncWithoutDetaching([$programId]);
            }
            if ($programId && $strategyId) {
                DostStrategy::find($strategyId)->agencyOutcomes()->syncWithoutDetaching([$programId]);
            }

            return [
                'pillar_id' => $pillarId,
                'strategy_id' => $strategyId,
                'sub_strategy_id' => $subStrategyId,
                'agency_outcome_id' => $programId,
            ];
        });

        return response()->json($result);
    }

    private function resolveProgram(array $program): ?int
    {
        return match ($program['mode']) {
            'existing' => (int) $program['id'],
            'new' => AgencyOutcome::create([
                'outcome' => $program['outcome'],
                'function_type' => $program['function_type'],
            ])->id,
            'skip' => null,
        };
    }

    private function resolvePillar(array $pillar): ?int
    {
        return match ($pillar['mode']) {
            'existing' => (int) $pillar['id'],
            'new' => DostPillar::create([
                'name' => $pillar['name'],
                'outcome_statement' => $pillar['outcome_statement'] ?? null,
            ])->id,
            'skip' => null,
        };
    }

    private function resolveStrategy(array $strategy, ?int $pillarId): ?int
    {
        if ($strategy['mode'] === 'existing') {
            return (int) $strategy['id'];
        }

        if ($strategy['mode'] === 'new') {
            if (! $pillarId) {
                throw ValidationException::withMessages([
                    'pillar.mode' => 'A Pillar must be selected or created before creating a new Strategy.',
                ]);
            }

            return DostStrategy::create([
                'dost_pillar_id' => $pillarId,
                'name' => $strategy['name'],
            ])->id;
        }

        return null;
    }

    private function resolveSubStrategy(array $subStrategy, ?int $strategyId): ?int
    {
        if ($subStrategy['mode'] === 'existing') {
            return (int) $subStrategy['id'];
        }

        if ($subStrategy['mode'] === 'new') {
            if (! $strategyId) {
                throw ValidationException::withMessages([
                    'strategy.mode' => 'A Strategy must be selected or created before creating a new Sub-Strategy.',
                ]);
            }

            return DostSubStrategy::create([
                'dost_strategy_id' => $strategyId,
                'description' => $subStrategy['description'],
            ])->id;
        }

        return null;
    }
}
