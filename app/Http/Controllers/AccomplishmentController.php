<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\AccomplishmentPhoto;
use App\Models\EmployeeIPCRPlan;
use App\Services\GoogleDriveService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccomplishmentController extends Controller
{
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024; // 5MB, matches prior file-upload cap

    private const ALLOWED_PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
    ];

    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

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

        $query = Accomplishment::with([
                'ipcrPlan' => fn($q) => $q->with(['ipcr:id,rating_period', 'plan:id,success_indicator']),
                'photos',
            ])
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

        $this->assertLinkedIpcrMutable($validated['ipcr_plan_id'] ?? null);

        Accomplishment::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Accomplishment recorded.');
    }

    public function update(Request $request, Accomplishment $accomplishment)
    {
        $this->authorizeOwner($accomplishment);
        $this->assertLinkedIpcrMutable($accomplishment->ipcr_plan_id);

        $validated = $request->validate([
            'ipcr_plan_id'       => 'nullable|exists:employee_ipcrs_plan,id',
            'accomplishment_date' => 'required|date',
            'description'        => 'required|string|max:2000',
        ]);

        $this->assertLinkedIpcrMutable($validated['ipcr_plan_id'] ?? null);

        $accomplishment->update($validated);

        return redirect()->back()->with('success', 'Accomplishment updated.');
    }

    public function destroy(Accomplishment $accomplishment)
    {
        $this->authorizeOwner($accomplishment);
        $this->assertLinkedIpcrMutable($accomplishment->ipcr_plan_id);

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
        $this->assertLinkedIpcrMutable($accomplishment->ipcr_plan_id);

        $request->validate([
            'photo_base64' => 'nullable|string', // base64 data URI, images only, 5MB max
            'drive_link'   => 'nullable|url|max:500',
        ]);

        if (! $request->filled('photo_base64') && ! $request->filled('drive_link')) {
            return redirect()->back()->withErrors(['proof' => 'Upload a file or paste a Drive link.']);
        }

        // Handle file upload
        if ($request->filled('photo_base64')) {
            if (! preg_match('/^data:([^;]+);base64,(.+)$/', $request->input('photo_base64'), $m)) {
                return redirect()->back()->withErrors(['photo' => 'Invalid file format.']);
            }

            $mime = strtolower(trim($m[1]));
            if (! isset(self::ALLOWED_PHOTO_MIMES[$mime])) {
                return redirect()->back()->withErrors(['photo' => 'Photo must be a JPG, PNG, or GIF image.']);
            }

            $binary = base64_decode($m[2], true);
            if ($binary === false) {
                return redirect()->back()->withErrors(['photo' => 'Invalid file data.']);
            }

            if (strlen($binary) > self::MAX_PHOTO_BYTES) {
                return redirect()->back()->withErrors(['photo' => 'Photo must be smaller than 5MB.']);
            }

            $ext           = self::ALLOWED_PHOTO_MIMES[$mime];
            $safeBasename  = $accomplishment->id . '_' . now()->format('YmdHis') . '.' . $ext;
            $slug          = $accomplishment->id . '_' . now()->format('YmdHis') . '_' . $safeBasename;
            $photoData     = [
                'accomplishment_id' => $accomplishment->id,
                'file_name'         => $safeBasename,
            ];

            if (config('services.google_drive.credentials')) {
                try {
                    $result = app(GoogleDriveService::class)->uploadRaw($binary, $slug, $mime);
                    $photoData['google_drive_file_id'] = $result['file_id'];
                    $photoData['google_drive_link']    = $result['link'];
                } catch (\Throwable $e) {
                    return redirect()->back()->withErrors(['photo' => 'Drive upload failed: ' . $e->getMessage()]);
                }
            } else {
                $path = 'accomplishments/' . uniqid() . '.' . $ext;
                Storage::disk('s3')->put($path, $binary);
                $photoData['local_path'] = $path;
            }

            AccomplishmentPhoto::create($photoData);
        }

        // Handle drive link (independent — both can be submitted together)
        if ($request->filled('drive_link')) {
            AccomplishmentPhoto::create([
                'accomplishment_id' => $accomplishment->id,
                'google_drive_link' => $request->input('drive_link'),
                'file_name'         => 'Drive Link',
            ]);
        }

        return redirect()->back()->with('success', 'Proof added.');
    }

    public function deletePhoto(AccomplishmentPhoto $photo)
    {
        $this->authorizeOwner($photo->accomplishment);
        $this->assertLinkedIpcrMutable($photo->accomplishment->ipcr_plan_id);

        if ($photo->local_path) {
            Storage::disk('public')->delete($photo->local_path);
        } elseif ($photo->google_drive_file_id && config('services.google_drive.credentials')) {
            try {
                app(GoogleDriveService::class)->delete($photo->google_drive_file_id);
            } catch (\Throwable) {
                // Ignore Drive errors — remove DB record anyway
            }
        }

        $photo->delete();

        return redirect()->back()->with('success', 'Proof removed.');
    }

    // ───── Monthly report data ────────────────────────────────────────────────

    public function monthlyReport(Request $request)
    {
        $user  = auth()->user();
        $month = $request->query('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);

        $accomplishments = Accomplishment::with([
                'ipcrPlan' => fn($q) => $q->with(['ipcr:id,rating_period', 'plan:id,success_indicator']),
                'photos',
            ])
            ->where('user_id', $user->id)
            ->whereYear('accomplishment_date', $year)
            ->whereMonth('accomplishment_date', $mon)
            ->orderBy('accomplishment_date')
            ->get();

        $user->loadMissing('division.divisionchief');
        $immediateHead = $user->division?->divisionchief;

        return response()->json([
            'month'           => $month,
            'employee'        => $user->only('id', 'name', 'position'),
            'accomplishments' => $accomplishments,
            'immediate_head'  => $immediateHead ? $immediateHead->only('name', 'position') : null,
        ]);
    }

    // ───── Helpers ────────────────────────────────────────────────────────────

    private function authorizeOwner(Accomplishment $accomplishment): void
    {
        if ($accomplishment->user_id !== auth()->id()) {
            abort(403, 'You can only modify your own accomplishments.');
        }
    }

    /**
     * Diary entries linked to an IPCR plan freeze with the IPCR
     * (Director Signed or closed rating period). Standalone entries stay editable.
     */
    private function assertLinkedIpcrMutable(?int $ipcrPlanId): void
    {
        if (! $ipcrPlanId) {
            return;
        }

        $ipcr = EmployeeIPCRPlan::with('ipcr.period')->find($ipcrPlanId)?->ipcr;

        if ($ipcr) {
            $this->workflow->assertMutable($ipcr);
        }
    }
}
