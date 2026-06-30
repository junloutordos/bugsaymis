<?php

namespace App\Http\Controllers;

use App\Mail\ErrorReportSubmittedMail;
use App\Models\ErrorReport;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ErrorReportController extends Controller
{
    // MIS personnel IDs (Junlou=1, Michael=2)
    private const MIS_USER_IDS = [1, 2];

    // ── MIS management index ──────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('error-reports.manage');

        $query = ErrorReport::with(['reporter:id,name,position', 'assignee:id,name'])
            ->orderByRaw("FIELD(status,'open','in_progress','resolved')")
            ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($search = $request->input('search')) {
            $query->where(fn ($q) =>
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('report_no', 'like', "%{$search}%")
                  ->orWhereHas('reporter', fn ($u) => $u->where('name', 'like', "%{$search}%"))
            );
        }

        $reports = $query->get()->map(fn ($r) => $this->mapReport($r));

        $misList = User::whereIn('id', self::MIS_USER_IDS)->get(['id', 'name']);

        return Inertia::render('ErrorReports/Index', [
            'reports'  => $reports,
            'misList'  => $misList,
            'filters'  => $request->only(['status', 'priority', 'search']),
            'counts'   => [
                'open'        => ErrorReport::where('status', 'open')->count(),
                'in_progress' => ErrorReport::where('status', 'in_progress')->count(),
                'resolved'    => ErrorReport::where('status', 'resolved')->count(),
            ],
        ]);
    }

    // ── Reporter's own history ────────────────────────────────────────────────

    public function myReports(): Response
    {
        $reports = ErrorReport::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->mapReport($r));

        return Inertia::render('ErrorReports/My', [
            'reports' => $reports,
        ]);
    }

    // ── Submit a new report ───────────────────────────────────────────────────

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'required|string|max:5000',
            'page_url'       => 'nullable|string|max:500',
            'browser_info'   => 'nullable|array',
            'screenshot_b64' => 'nullable|string',
        ]);

        $screenshotPath = null;
        if (! empty($data['screenshot_b64'])) {
            $screenshotPath = $this->storeScreenshot($data['screenshot_b64']);
        }

        $reportNo = $this->generateReportNo();

        $report = ErrorReport::create([
            'report_no'       => $reportNo,
            'user_id'         => Auth::id(),
            'title'           => $data['title'],
            'description'     => $data['description'],
            'page_url'        => $data['page_url'] ?? null,
            'browser_info'    => $data['browser_info'] ?? null,
            'screenshot_path' => $screenshotPath,
            'status'          => 'open',
            'priority'        => 'medium',
        ]);

        $report->load('reporter:id,name');

        $this->notifyMIS($report);

        return response()->json(['message' => "Error report {$reportNo} submitted. The MIS team will look into it."]);
    }

    // ── MIS updates a report (status, priority, action, assignee) ────────────

    public function update(Request $request, ErrorReport $errorReport): RedirectResponse
    {
        $this->authorize('error-reports.manage');

        $data = $request->validate([
            'status'       => 'sometimes|in:open,in_progress,resolved',
            'priority'     => 'sometimes|in:low,medium,high,critical',
            'assigned_to'  => 'sometimes|nullable|exists:users,id',
            'action_taken' => 'sometimes|nullable|string|max:5000',
        ]);

        if (isset($data['status']) && $data['status'] === 'resolved' && $errorReport->status !== 'resolved') {
            $data['resolved_at'] = now();
        }
        if (isset($data['status']) && $data['status'] !== 'resolved') {
            $data['resolved_at'] = null;
        }

        $errorReport->update($data);

        // Notify reporter when resolved
        if (isset($data['status']) && $data['status'] === 'resolved') {
            $reporter = $errorReport->reporter;
            if ($reporter) {
                NotificationService::notifyUser(
                    $reporter,
                    'Error Report',
                    $errorReport->report_no,
                    'Resolved',
                    route('error-reports.my'),
                );
            }
        }

        return back()->with('success', 'Report updated.');
    }

    // ── Screenshot proxy (serves S3 file privately) ───────────────────────────

    public function screenshot(ErrorReport $errorReport)
    {
        // Reporter or MIS can view
        $user = Auth::user();
        $isMis = in_array($user->id, self::MIS_USER_IDS) || $user->isSuperAdmin();
        if (! $isMis && $errorReport->user_id !== $user->id) {
            abort(403);
        }

        if (! $errorReport->screenshot_path) {
            abort(404);
        }

        $content  = Storage::disk('s3')->get($errorReport->screenshot_path);
        $mimeType = Storage::disk('s3')->mimeType($errorReport->screenshot_path) ?: 'image/png';

        return response($content, 200)->header('Content-Type', $mimeType);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateReportNo(): string
    {
        $year   = now()->format('Y');
        $prefix = "ERR-{$year}";

        $seq = ErrorReport::where('report_no', 'like', "{$prefix}-%")
            ->select(DB::raw("MAX(CAST(SUBSTRING_INDEX(report_no, '-', -1) AS UNSIGNED)) as seq"))
            ->value('seq') ?? 0;

        return sprintf('%s-%04d', $prefix, $seq + 1);
    }

    private function storeScreenshot(string $dataUri): ?string
    {
        if (! preg_match('/^data:(image\/[a-z+]+);base64,(.+)$/', $dataUri, $matches)) {
            return null;
        }

        $mime    = $matches[1];
        $ext     = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };
        $decoded = base64_decode($matches[2]);
        if ($decoded === false) {
            return null;
        }

        $s3Key = 'error-reports/' . now()->format('Y/m') . '/' . Auth::id() . '_' . time() . '.' . $ext;
        Storage::disk('s3')->put($s3Key, $decoded);

        return $s3Key;
    }

    private function notifyMIS(ErrorReport $report): void
    {
        $misUsers = User::whereIn('id', self::MIS_USER_IDS)->get();

        foreach ($misUsers as $mis) {
            try {
                Mail::to($mis->email)->queue(new ErrorReportSubmittedMail($report));
            } catch (\Throwable $e) {
                logger()->error('Failed to send error report email', ['user_id' => $mis->id, 'error' => $e->getMessage()]);
            }

            try {
                NotificationService::notifyUser(
                    $mis,
                    'Error Report',
                    $report->report_no,
                    'New report submitted by ' . ($report->reporter?->name ?? 'a user'),
                    route('error-reports.index'),
                );
            } catch (\Throwable $e) {
                logger()->error('Failed to send error report notification', ['mis_id' => $mis->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function mapReport(ErrorReport $r): array
    {
        return [
            'id'              => $r->id,
            'report_no'       => $r->report_no,
            'title'           => $r->title,
            'description'     => $r->description,
            'page_url'        => $r->page_url,
            'browser_info'    => $r->browser_info,
            'has_screenshot'  => (bool) $r->screenshot_path,
            'screenshot_url'  => $r->screenshot_path ? route('error-reports.screenshot', $r->id) : null,
            'status'          => $r->status,
            'priority'        => $r->priority,
            'assigned_to'     => $r->assigned_to,
            'assignee_name'   => $r->assignee?->name,
            'action_taken'    => $r->action_taken,
            'resolved_at'     => $r->resolved_at?->toIso8601String(),
            'created_at'      => $r->created_at->toIso8601String(),
            'reporter'        => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name, 'position' => $r->reporter->position ?? null] : null,
        ];
    }
}
