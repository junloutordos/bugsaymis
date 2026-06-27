<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhFeeLedger;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RhFeeController extends Controller
{
    private function hallScope(): ?string
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return null;
        return $user->rh_hall ?: null;
    }

    public function index(Request $request)
    {
        $this->authorize('rh.fees.manage');

        $hall   = $this->hallScope();
        $month  = (int) $request->input('month', now()->month);
        $year   = (int) $request->input('year', now()->year);
        $isPaid = $request->input('is_paid', '');
        $search = $request->input('search', '');

        $fees = RhFeeLedger::with(['intern.room'])
            ->whereHas('intern', function ($q) use ($hall) {
                if ($hall) $q->whereHas('room', fn($r) => $r->where('residence_hall', $hall));
            })
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->when($isPaid !== '', fn($q) => $q->where('is_paid', (bool) $isPaid))
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get()
            ->map(function ($f) use ($search) {
                $resolver = app(\App\Services\Discipline\StudentResolver::class);
                $student  = $resolver->resolve($f->intern->student_id ?? 0);
                $name     = $student['name'] ?? ('Student #' . $f->intern->student_id);
                if ($search && !str_contains(strtolower($name), strtolower($search))) return null;
                return [
                    'id'             => $f->id,
                    'intern_id'      => $f->rh_intern_id,
                    'student_name'   => $name,
                    'residence_hall' => $f->intern->room?->residence_hall,
                    'room_number'    => $f->intern->room?->room_number,
                    'period_month'   => $f->period_month,
                    'period_year'    => $f->period_year,
                    'period_label'   => $f->period_label,
                    'lodging_fee'    => $f->lodging_fee,
                    'appliance_fee'  => $f->appliance_fee,
                    'total_fee'      => $f->total_fee,
                    'is_paid'        => $f->is_paid,
                    'paid_at'        => $f->paid_at,
                    'notes'          => $f->notes,
                ];
            })
            ->filter()
            ->values();

        $summary = [
            'total_interns'  => $fees->count(),
            'total_amount'   => $fees->sum('total_fee'),
            'paid_amount'    => $fees->where('is_paid', true)->sum('total_fee'),
            'unpaid_count'   => $fees->where('is_paid', false)->count(),
        ];

        return Inertia::render('ResidenceHall/Fees/Index', [
            'fees'    => $fees,
            'summary' => $summary,
            'filters' => compact('month', 'year', 'isPaid', 'search'),
            'myHall'  => $hall,
        ]);
    }

    public function markPaid(Request $request, RhFeeLedger $rhFeeLedger)
    {
        $this->authorize('rh.fees.manage');

        $hall = $this->hallScope();
        if ($hall && $rhFeeLedger->intern->room && $rhFeeLedger->intern->room->residence_hall !== $hall) abort(403);

        $data = $request->validate(['notes' => 'nullable|string|max:500']);

        $rhFeeLedger->update([
            'is_paid' => true,
            'paid_at' => now(),
            'notes'   => $data['notes'] ?? $rhFeeLedger->notes,
        ]);

        return back()->with('success', 'Fee marked as paid.');
    }

    public function generate(Request $request)
    {
        $this->authorize('rh.fees.manage');

        $data = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $hall = $this->hallScope();

        $interns = RhIntern::with(['appliances', 'room'])
            ->where('status', 'active')
            ->when($hall, fn($q) => $q->whereHas('room', fn($r) => $r->where('residence_hall', $hall)))
            ->get();

        $created = 0;
        foreach ($interns as $intern) {
            $applianceFee = $intern->appliances->where('is_approved', true)->sum('fee_amount');
            $total        = (float) $intern->lodging_fee_monthly + (float) $applianceFee;

            $exists = RhFeeLedger::where('rh_intern_id', $intern->id)
                ->where('period_month', $data['month'])
                ->where('period_year', $data['year'])
                ->exists();

            if (!$exists) {
                RhFeeLedger::create([
                    'rh_intern_id' => $intern->id,
                    'period_month' => $data['month'],
                    'period_year'  => $data['year'],
                    'lodging_fee'  => $intern->lodging_fee_monthly,
                    'appliance_fee'=> $applianceFee,
                    'total_fee'    => $total,
                    'is_paid'      => false,
                ]);
                $created++;
            }
        }

        return back()->with('success', "Generated {$created} fee records.");
    }
}
