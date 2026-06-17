<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\OedIssuance;
use App\Models\OedIssuanceCategory;
use App\Models\Office;
use App\Models\User;
use App\Services\KnowledgeManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KnowledgeManagementController extends Controller
{
    public function __construct(private KnowledgeManagementService $svc) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user       = $request->user();
        $canManage  = $user->hasPermission('km.manage');

        $query = OedIssuance::with(['category', 'uploader:id,name'])
            ->withCount(['acknowledgments', 'recipients'])
            ->withExists(['acknowledgments as is_acknowledged' => fn ($q) => $q->where('user_id', $user->id)]);

        if (! $canManage) {
            $query->where('status', 'active')
                ->where(function ($q) use ($user) {
                    $q->where('recipient_type', 'all')
                        ->orWhereHas('recipients', fn ($r) => $r->where('user_id', $user->id));
                });
        }

        $issuances = $query->orderByDesc('issued_date')->get();

        return Inertia::render('KnowledgeManagement/Index', [
            'issuances'        => $issuances,
            'categories'       => OedIssuanceCategory::where('is_active', true)->orderBy('label')->get(['code', 'label']),
            'allCategories'    => $canManage
                ? OedIssuanceCategory::orderBy('label')->get(['code', 'label', 'description', 'is_active'])
                : [],
            'canManage'        => $canManage,
            'totalActiveUsers' => User::where('status', '<>', 'inactive')->count(),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        return Inertia::render('KnowledgeManagement/Create', [
            'categories'       => OedIssuanceCategory::where('is_active', true)->orderBy('label')->get(['code', 'label']),
            'supersedeOptions' => OedIssuance::where('status', 'active')
                ->orderByDesc('issued_date')
                ->get(['id', 'title', 'reference_no', 'issued_date']),
            'offices'   => Office::orderBy('name')->get(['id', 'name']),
            'divisions' => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']),
            'users'     => User::where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'division_id']),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $validated = $this->validateRequest($request, fileRequired: true);

        $year = (int) now()->year;
        $file = $this->svc->storeFile(
            $validated['file_base64'],
            $validated['file_name'],
            $validated['file_mime'] ?? null,
            $year,
        );

        $issuance = OedIssuance::create([
            'category_code'   => $validated['category_code'],
            'reference_no'    => $validated['reference_no'] ?? null,
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'issued_date'     => $validated['issued_date'],
            'effective_date'  => $validated['effective_date'] ?? null,
            'file_path'       => $file['file_path'],
            'file_name'       => $file['file_name'],
            'file_mime'       => $file['file_mime'],
            'file_size'       => $file['file_size'],
            'status'          => 'active',
            'recipient_type'  => $validated['recipient_type'],
            'uploaded_by'     => $request->user()->id,
        ]);

        $this->svc->buildRecipients($issuance, $validated['recipient_type'], $validated['recipient_ids'] ?? []);

        if (! empty($validated['supersedes_id'])) {
            $old = OedIssuance::find($validated['supersedes_id']);
            if ($old) {
                $this->svc->linkSupersession($old, $issuance);
            }
        }

        $this->svc->notifyUpload($issuance);

        return redirect()->route('km.show', $issuance->id)->with('success', 'Document uploaded successfully.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, OedIssuance $oedIssuance)
    {
        $user      = $request->user();
        $canManage = $user->hasPermission('km.manage');

        abort_if(! $canManage && ! $oedIssuance->isVisibleTo($user->id), 403);

        $oedIssuance->load(['category', 'uploader:id,name', 'supersededBy:id,title,reference_no', 'supersedes:id,title,reference_no']);

        $this->svc->acknowledge($oedIssuance, $user->id);

        $ackStats = null;
        if ($canManage) {
            $total = $oedIssuance->recipient_type === 'all'
                ? User::where('status', '<>', 'inactive')->count()
                : $oedIssuance->recipients()->count();
            $acknowledged = $oedIssuance->acknowledgments()->count();

            $ackStats = [
                'total'        => $total,
                'acknowledged' => $acknowledged,
                'percentage'   => $total > 0 ? (int) round($acknowledged / $total * 100) : 0,
                'recent'       => $oedIssuance->acknowledgments()
                    ->with('user:id,name')
                    ->latest('acknowledged_at')
                    ->limit(20)
                    ->get()
                    ->map(fn ($a) => [
                        'user'            => $a->user?->only('id', 'name'),
                        'acknowledged_at' => $a->acknowledged_at?->toISOString(),
                    ]),
            ];

            if ($oedIssuance->recipient_type !== 'all') {
                $oedIssuance->load(['recipients.user:id,name,office_id,division_id']);
            }
        }

        return Inertia::render('KnowledgeManagement/Show', [
            'issuance'  => $oedIssuance,
            'canManage' => $canManage,
            'ackStats'  => $ackStats,
        ]);
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Request $request, OedIssuance $oedIssuance)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $oedIssuance->load('recipients');

        return Inertia::render('KnowledgeManagement/Edit', [
            'issuance' => [
                ...$oedIssuance->toArray(),
                'issued_date'    => $oedIssuance->issued_date?->format('Y-m-d'),
                'effective_date' => $oedIssuance->effective_date?->format('Y-m-d'),
                'recipient_ids'  => $oedIssuance->recipients->pluck('user_id'),
            ],
            'categories' => OedIssuanceCategory::where('is_active', true)->orderBy('label')->get(['code', 'label']),
            'offices'    => Office::orderBy('name')->get(['id', 'name']),
            'divisions'  => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']),
            'users'      => User::where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'division_id']),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, OedIssuance $oedIssuance)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $validated = $this->validateRequest($request, fileRequired: false);
        $validated['status'] = $request->validate([
            'status' => 'required|in:active,superseded,archived',
        ])['status'];

        $data = [
            'category_code'  => $validated['category_code'],
            'reference_no'   => $validated['reference_no'] ?? null,
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? null,
            'issued_date'    => $validated['issued_date'],
            'effective_date' => $validated['effective_date'] ?? null,
            'status'         => $validated['status'],
            'recipient_type' => $validated['recipient_type'],
        ];

        if (! empty($validated['file_base64'])) {
            $this->svc->deleteFile($oedIssuance->file_path);
            $file = $this->svc->storeFile(
                $validated['file_base64'],
                $validated['file_name'],
                $validated['file_mime'] ?? null,
                (int) now()->year,
            );
            $data['file_path'] = $file['file_path'];
            $data['file_name'] = $file['file_name'];
            $data['file_mime'] = $file['file_mime'];
            $data['file_size'] = $file['file_size'];
        }

        $oedIssuance->update($data);

        $this->svc->rebuildRecipients($oedIssuance, $validated['recipient_type'], $validated['recipient_ids'] ?? []);

        if (! empty($validated['supersedes_id'])) {
            $old = OedIssuance::find($validated['supersedes_id']);
            if ($old && $old->id !== $oedIssuance->id) {
                $this->svc->linkSupersession($old, $oedIssuance);
            }
        }

        return back()->with('success', 'Document updated successfully.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Request $request, OedIssuance $oedIssuance)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $this->svc->deleteFile($oedIssuance->file_path);
        $oedIssuance->delete();

        return redirect()->route('km.index')->with('success', 'Document deleted.');
    }

    // ── Acknowledge ───────────────────────────────────────────────────────────

    public function acknowledge(Request $request, OedIssuance $oedIssuance)
    {
        $user = $request->user();
        abort_if(! $user->hasPermission('km.manage') && ! $oedIssuance->isVisibleTo($user->id), 403);

        $this->svc->acknowledge($oedIssuance, $user->id);

        return back();
    }

    // ── File proxy ────────────────────────────────────────────────────────────

    public function viewFile(Request $request, OedIssuance $oedIssuance)
    {
        $user      = $request->user();
        $canManage = $user->hasPermission('km.manage');
        abort_if(! $canManage && ! $oedIssuance->isVisibleTo($user->id), 403);
        abort_if(! Storage::disk('s3')->exists($oedIssuance->file_path), 404);

        $content = Storage::disk('s3')->get($oedIssuance->file_path);

        return response($content, 200, [
            'Content-Type'        => $oedIssuance->file_mime ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $oedIssuance->file_name . '"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    public function download(Request $request, OedIssuance $oedIssuance)
    {
        $user      = $request->user();
        $canManage = $user->hasPermission('km.manage');
        abort_if(! $canManage && ! $oedIssuance->isVisibleTo($user->id), 403);
        abort_if(! Storage::disk('s3')->exists($oedIssuance->file_path), 404);

        $content = Storage::disk('s3')->get($oedIssuance->file_path);

        return response($content, 200, [
            'Content-Type'        => $oedIssuance->file_mime ?: 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $oedIssuance->file_name . '"',
        ]);
    }

    // ── Category management ───────────────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:10', 'unique:oed_issuance_categories,code'],
            'label'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['code']      = strtoupper($validated['code']);
        $validated['is_active'] = true;

        OedIssuanceCategory::create($validated);

        return back()->with('success', 'Category "' . $validated['label'] . '" added.');
    }

    public function updateCategory(Request $request, string $code)
    {
        abort_unless($request->user()->hasPermission('km.manage'), 403);

        $category = OedIssuanceCategory::findOrFail($code);

        $validated = $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['required', 'boolean'],
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated.');
    }

    // ── Shared validation ─────────────────────────────────────────────────────

    private function validateRequest(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'category_code'   => ['required', Rule::exists('oed_issuance_categories', 'code')],
            'reference_no'    => 'nullable|string|max:100',
            'title'           => 'required|string|max:500',
            'description'     => 'nullable|string',
            'issued_date'     => 'required|date',
            'effective_date'  => 'nullable|date',
            'supersedes_id'   => 'nullable|exists:oed_issuances,id',
            'file_base64'     => $fileRequired ? 'required|string' : 'nullable|string',
            'file_name'       => $fileRequired ? 'required|string|max:255' : 'nullable|string|max:255',
            'file_mime'       => 'nullable|string|max:100',
            'recipient_type'  => 'required|in:all,office,division,individual',
            'recipient_ids'   => 'nullable|array',
            'recipient_ids.*' => 'integer',
        ]);
    }
}
