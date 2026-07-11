<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\PerformanceIndicator;
use App\Models\WorkDistributionPlan;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Admin management of IPCR fiscal years and semestral rating periods
 * (CSC MC No. 6, s. 2012 — performance evaluation is done semi-annually).
 */
class IPCRRatingPeriodController extends Controller
{
    public function index()
    {
        $periods = IPCRRatingPeriod::withCount('ipcrs')
            ->orderByDesc('year')
            ->orderBy('semester')
            ->get();

        return Inertia::render('PerformanceManagement/RatingPeriods', [
            'periods' => $periods,
        ]);
    }

    /**
     * Create a fiscal year: both semestral periods with canonical labels.
     * Labels must match the IPCRRatingPeriodSeeder format so string-based
     * backfill/matching stays consistent.
     */
    public function storeFiscalYear(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $year = (int) $data['year'];

        if (IPCRRatingPeriod::where('year', $year)->exists()) {
            throw ValidationException::withMessages([
                'year' => "Fiscal Year {$year} already has rating periods.",
            ]);
        }

        $semesters = [
            1 => ['label' => "January 1 to June 30, {$year}",    'start' => "{$year}-01-01", 'end' => "{$year}-06-30"],
            2 => ['label' => "July 1 to December 31, {$year}",   'start' => "{$year}-07-01", 'end' => "{$year}-12-31"],
        ];

        DB::transaction(function () use ($year, $semesters) {
            foreach ($semesters as $semester => $def) {
                IPCRRatingPeriod::create([
                    'label'      => $def['label'],
                    'year'       => $year,
                    'semester'   => $semester,
                    'start_date' => $def['start'],
                    'end_date'   => $def['end'],
                    'status'     => IPCRRatingPeriod::STATUS_OPEN,
                    'is_active'  => true,
                    'is_current' => false,
                ]);
            }
        });

        AuditLogger::log([
            'action'         => 'ipcr_fiscal_year_created',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => 0,
            'new_values'     => ['year' => $year],
        ]);

        return back()->with('success', "Fiscal Year {$year} created with two semestral rating periods.");
    }

    public function update(Request $request, IPCRRatingPeriod $period)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        // CSC MC No. 6, s. 2012: appraisal period is at least 90 days, at most one year
        $days = \Carbon\Carbon::parse($data['start_date'])->diffInDays(\Carbon\Carbon::parse($data['end_date']));
        if ($days < 90 || $days > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'Per CSC SPMS rules, a rating period must cover at least 90 days and at most one year.',
            ]);
        }

        $period->update($data);

        AuditLogger::log([
            'action'         => 'ipcr_rating_period_updated',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => $period->id,
            'new_values'     => $data,
        ]);

        return back()->with('success', 'Rating period updated.');
    }

    /**
     * Close a period — everything in it becomes read-only.
     */
    public function close(IPCRRatingPeriod $period)
    {
        $period->update(['status' => IPCRRatingPeriod::STATUS_CLOSED, 'is_current' => false]);

        AuditLogger::log([
            'action'         => 'ipcr_rating_period_closed',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => $period->id,
            'new_values'     => ['status' => 'closed'],
        ]);

        return back()->with('success', "\"{$period->label}\" closed. All IPCRs in this period are now read-only.");
    }

    public function reopen(IPCRRatingPeriod $period)
    {
        $period->update(['status' => IPCRRatingPeriod::STATUS_OPEN]);

        AuditLogger::log([
            'action'         => 'ipcr_rating_period_reopened',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => $period->id,
            'new_values'     => ['status' => 'open'],
        ]);

        return back()->with('success', "\"{$period->label}\" reopened.");
    }

    public function setCurrent(IPCRRatingPeriod $period)
    {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'A closed period cannot be set as current. Reopen it first.',
            ]);
        }

        DB::transaction(function () use ($period) {
            IPCRRatingPeriod::where('is_current', true)->update(['is_current' => false]);
            $period->update(['is_current' => true]);
        });

        AuditLogger::log([
            'action'         => 'ipcr_rating_period_set_current',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => $period->id,
            'new_values'     => ['is_current' => true],
        ]);

        return back()->with('success', "\"{$period->label}\" is now the current rating period.");
    }

    /**
     * Deep-clone the performance framework (Agency Outcomes → Performance
     * Indicators → Work Distribution Plans, incl. division/office/committee/
     * special-assignment/personnel links) from a source FY into a target FY.
     * Source rows with NULL fiscal_year (legacy "all years") are included.
     */
    public function copyFramework(Request $request)
    {
        $data = $request->validate([
            'source_year' => 'required|integer|min:2000|max:2100|different:target_year',
            'target_year' => 'required|integer|min:2000|max:2100',
        ]);

        $source = (int) $data['source_year'];
        $target = (int) $data['target_year'];

        if (AgencyOutcome::where('fiscal_year', $target)->exists()
            || WorkDistributionPlan::where('fiscal_year', $target)->exists()) {
            throw ValidationException::withMessages([
                'target_year' => "FY {$target} already has framework entries. Copy is only allowed into an empty fiscal year.",
            ]);
        }

        $counts = DB::transaction(function () use ($source, $target) {
            $outcomes = AgencyOutcome::forFiscalYear($source)->get();
            $outcomeMap = [];   // old id => new id
            foreach ($outcomes as $outcome) {
                $clone = $outcome->replicate(['fiscal_year']);
                $clone->fiscal_year = $target;
                $clone->save();
                $outcomeMap[$outcome->id] = $clone->id;
            }

            $indicators = PerformanceIndicator::with('divisions')->forFiscalYear($source)->get();
            $indicatorMap = [];
            foreach ($indicators as $indicator) {
                $clone = $indicator->replicate(['fiscal_year']);
                $clone->fiscal_year = $target;
                $clone->agency_outcome_id = $outcomeMap[$indicator->agency_outcome_id] ?? $indicator->agency_outcome_id;
                $clone->save();
                $clone->divisions()->sync($indicator->divisions->pluck('id'));
                $indicatorMap[$indicator->id] = $clone->id;
            }

            $plans = WorkDistributionPlan::with(['offices', 'committees', 'specialAssignments', 'personnel'])
                ->forFiscalYear($source)->get();
            foreach ($plans as $plan) {
                $clone = $plan->replicate(['fiscal_year']);
                $clone->fiscal_year = $target;
                $clone->performance_indicator_id = $indicatorMap[$plan->performance_indicator_id] ?? $plan->performance_indicator_id;
                $clone->save();
                $clone->offices()->sync($plan->offices->pluck('id'));
                $clone->committees()->sync($plan->committees->pluck('id'));
                $clone->specialAssignments()->sync($plan->specialAssignments->pluck('id'));
                $clone->personnel()->sync($plan->personnel->pluck('id'));
            }

            return ['outcomes' => count($outcomeMap), 'indicators' => count($indicatorMap), 'plans' => $plans->count()];
        });

        AuditLogger::log([
            'action'         => 'ipcr_framework_copied',
            'auditable_type' => IPCRRatingPeriod::class,
            'auditable_id'   => 0,
            'new_values'     => ['source_year' => $source, 'target_year' => $target] + $counts,
        ]);

        return back()->with('success', "Framework copied from FY {$source} to FY {$target}: {$counts['outcomes']} outcome(s), {$counts['indicators']} indicator(s), {$counts['plans']} plan(s).");
    }
}
