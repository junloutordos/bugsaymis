<?php

namespace App\Http\Controllers;

use App\Models\DostSubStrategy;
use Illuminate\Http\Request;

class DostSubStrategyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'dost_strategy_id' => 'required|exists:dost_strategies,id',
            'description' => 'required|string',
        ]);

        DostSubStrategy::create($data);

        return back()->with('success', 'Sub-Strategy created.');
    }

    public function update(Request $request, DostSubStrategy $dostSubStrategy)
    {
        $data = $request->validate([
            'dost_strategy_id' => 'required|exists:dost_strategies,id',
            'description' => 'required|string',
        ]);

        $dostSubStrategy->update($data);

        return back()->with('success', 'Sub-Strategy updated.');
    }

    public function destroy(DostSubStrategy $dostSubStrategy)
    {
        $dostSubStrategy->delete();

        return back()->with('success', 'Sub-Strategy deleted.');
    }
}
