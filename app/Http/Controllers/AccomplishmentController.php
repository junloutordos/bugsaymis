<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\AccomplishmentPhoto;
use App\Models\EmployeeIPCRPlan;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccomplishmentController extends Controller
{
    // ───── Page ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user  = auth()->user();
        $month = $request->query('month'); // "YYYY-MM"

        // All IPCR plan rows belonging to this employee, for the dropdown
        $ipcrPlans = EmployeeIPCRPlan::with([
                'ipcr:id,rating_period',
                'plan:id,success_indicator',
            ])
            ->whereHas('ipcr', fn($q) => $q->where('user_id', $user->id))
            ->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'label'         => ($p->ipcr->rating_period ?? '—') . ' — ' . $p->plan->success_indicator,
                'rating_period' => $p->ipcr->rating_period,
            ]);

        $query = Accomplishment::with(['ipcrPlan.ipcr:id,rating_period', 'ipcrPlan.plan:id,success_indicator', 'photos'])
            ->where('user_id', $user->id)
            ->orderBy('accomplishment_date', 'desc');

        if ($month) {
            [$year, $mon] = explode('-', $month);
            $query->whereYear('accomplishment_date', $year)
                  ->whereMonth('accomplishment_date', $mon);
        }

        // Available months that have entries
        $months = Accomplishment::where('user_id', $user->id)
            ->selectRaw("DATE_FORMAT(accomplishment_date, '%Y-%m') as month")
            ->distinct()
            ->orderByDesc('month')
            ->pluck('month');

        return Inertia::render('PerformanceManagement/MyAccomplishments', [
            'accomplishments' => $query->get(),
            'ipcrPlans'       => $ipcrPlans,
            'months'          => $months,
            'selectedMonth'   => $month,
        ]);
    }

    // ───── CRUD ──────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ipcr_plan_id'       => 'nullable|exists:employee_ipcrs_plan,id',
            'accomplishment_date' => 'required|date',
            'description'        => 'required|string|max:2000',
        ]);

        Accomplishment::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Accomplishment recorded.');
    }

    public function update(Request $request, Accomplishment $accomplishment)
    {
        $this->authorizeOwner($accomplishment);

        $validated = $request->validate([
            'ipcr_plan_id'       => 'nullable|exists:employee_ipcrs_plan,id',
            'accomplishment_date' => 'required|date',
            'description'        => 'required|string|max:2000',
        ]);

        $accomplishment->update($validated);

        return redirect()->back()->with('success', 'Accomplishment updated.');
    }

    public function destroy(Accomplishment $accomplishment)
    {
        $this->authorizeOwner($accomplishment);

        // Delete Drive files before removing DB records
        if (class_exists(GoogleDriveService::class) && config('services.google_drive.credentials')) {
            $drive = app(GoogleDriveService::class);
            foreach ($accomplishment->photos as $photo) {
                if ($photo->google_drive_file_id) {
                    $drive->delete($photo->google_drive_file_id);
                }
            }
        }

        $accomplishment->delete();

        return redirect()->back()->with('success', 'Accomplishment deleted.');
    }

    // ───── Photo upload ───────────────────────────────────────────────────────

    public function uploadPhoto(Request $request, Accomplishment $accomplishment)
    {
        $this->authorizeOwner($accomplishment);

        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,gif,pdf|max:10240',
        ]);

        $file     = $request->file('photo');
        $fileName = $accomplishment->id . '_' . now()->format('YmdHis') . '_' . $file->getClientOriginalName();

        $photoData = [
            'accomplishment_id'    => $accomplishment->id,
            'file_name'            => $file->getClientOriginalName(),
            'google_drive_file_id' => null,
            'google_drive_link'    => null,
        ];

        // Upload to Google Drive if credentials are configured
        if (config('services.google_drive.credentials')) {
            try {
                $drive  = app(GoogleDriveService::class);
                $result = $drive->upload($file, $fileName);

                $photoData['google_drive_file_id'] = $result['file_id'];
                $photoData['google_drive_link']    = $result['link'];
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Drive upload failed: ' . $e->getMessage());
            }
        } else {
            // Fallback: accept a manually entered Drive link via form field
            $request->validate(['drive_link' => 'nullable|url|max:500']);
            $photoData['google_drive_link'] = $request->input('drive_link');
        }

        AccomplishmentPhoto::create($photoData);

        return redirect()->back()->with('success', 'Photo added.');
    }

    public function deletePhoto(AccomplishmentPhoto $photo)
    {
        $this->authorizeOwner($photo->accomplishment);

        if ($photo->google_drive_file_id && config('services.google_drive.credentials')) {
            try {
                app(GoogleDriveService::class)->delete($photo->google_drive_file_id);
            } catch (\Throwable) {
                // Ignore Drive errors — remove DB record anyway
            }
        }

        $photo->delete();

        return redirect()->back()->with('success', 'Photo removed.');
    }

    // ───── Monthly report data ────────────────────────────────────────────────

    public function monthlyReport(Request $request)
    {
        $user  = auth()->user();
        $month = $request->query('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);

        $accomplishments = Accomplishment::with(['ipcrPlan.plan:id,success_indicator', 'photos'])
            ->where('user_id', $user->id)
            ->whereYear('accomplishment_date', $year)
            ->whereMonth('accomplishment_date', $mon)
            ->orderBy('accomplishment_date')
            ->get();

        return response()->json([
            'month'           => $month,
            'employee'        => $user->only('id', 'name', 'position'),
            'accomplishments' => $accomplishments,
        ]);
    }

    // ───── Helpers ────────────────────────────────────────────────────────────

    private function authorizeOwner(Accomplishment $accomplishment): void
    {
        if ($accomplishment->user_id !== auth()->id()) {
            abort(403, 'You can only modify your own accomplishments.');
        }
    }
}
