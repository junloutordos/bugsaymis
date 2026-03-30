<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AllowanceType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AllowanceTypeController extends Controller
{
    public function index()
    {
        $this->authorize('payroll.manage');

        return Inertia::render('HR/Allowances/Index', [
            'types' => AllowanceType::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('payroll.manage');

        $validated = $request->validate([
            'code'          => 'required|unique:allowance_types|max:20',
            'name'          => 'required|max:100',
            'description'   => 'nullable|max:500',
            'category'      => 'required|in:allowance,deduction,bonus,other',
            'is_taxable'    => 'boolean',
            'is_mandatory'  => 'boolean',
            'is_fixed_amount' => 'boolean',
            'default_amount'  => 'nullable|numeric|min:0',
            'is_active'     => 'boolean',
            'sort_order'    => 'nullable|integer',
        ]);

        AllowanceType::create($validated);

        return back()->with('success', 'Allowance type created successfully.');
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        $this->authorize('payroll.manage');

        $validated = $request->validate([
            'code'          => 'required|max:20|unique:allowance_types,code,' . $allowanceType->id,
            'name'          => 'required|max:100',
            'description'   => 'nullable|max:500',
            'category'      => 'required|in:allowance,deduction,bonus,other',
            'is_taxable'    => 'boolean',
            'is_mandatory'  => 'boolean',
            'is_fixed_amount' => 'boolean',
            'default_amount'  => 'nullable|numeric|min:0',
            'is_active'     => 'boolean',
            'sort_order'    => 'nullable|integer',
        ]);

        $allowanceType->update($validated);

        return back()->with('success', 'Allowance type updated successfully.');
    }

    public function toggle(AllowanceType $allowanceType)
    {
        $this->authorize('payroll.manage');

        $allowanceType->update(['is_active' => ! $allowanceType->is_active]);

        return back()->with('success', 'Allowance type status updated.');
    }
}
