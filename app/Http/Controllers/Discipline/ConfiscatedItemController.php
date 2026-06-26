<?php

namespace App\Http\Controllers\Discipline;

use App\Http\Controllers\Controller;
use App\Models\Discipline\DisciplineConfiscatedItem;
use App\Services\Discipline\ConfiscationPdfService;
use App\Services\Discipline\StudentResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConfiscatedItemController extends Controller
{
    public function __construct(
        private ConfiscationPdfService $pdfService,
        private StudentResolver $students,
    ) {}

    public function index(Request $request)
    {
        $status = trim($request->query('status', ''));
        $search = trim($request->query('search', ''));

        $items = DisciplineConfiscatedItem::with(['confiscatedBy:id,name', 'claimAckBy:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('receipt_no', 'like', "%{$search}%")
                ->orWhere('item_description', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $map = $this->studentNameMap($items->getCollection()->pluck('student_id'));
        $items->getCollection()->transform(function ($i) use ($map) {
            $i->student_name = $map[$i->student_id] ?? ('Student #' . $i->student_id);
            return $i;
        });

        return Inertia::render('Discipline/Confiscated/Index', [
            'items'   => $items,
            'filters' => ['status' => $status, 'search' => $search],
            'staff'   => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'         => ['required', 'integer', 'exists:students,id'],
            'item_description'   => ['required', 'string'],
            'place'              => ['nullable', 'string', 'max:255'],
            'reason'             => ['nullable', 'string'],
            'discipline_case_id' => ['nullable', 'integer', 'exists:discipline_cases,id'],
        ]);

        $item = DB::transaction(function () use ($validated, $request) {
            return DisciplineConfiscatedItem::create(array_merge($validated, [
                'receipt_no'     => $this->generateReceiptNo(),
                'confiscated_by' => $request->user()->id,
                'confiscated_at' => now(),
                'status'         => 'held',
            ]));
        });

        return back()->with('success', "Confiscation receipt {$item->receipt_no} created.");
    }

    public function claim(Request $request, DisciplineConfiscatedItem $item)
    {
        if ($item->status === 'claimed') {
            return back()->with('success', 'Item already claimed.');
        }

        $validated = $request->validate([
            'claim_ack_role' => ['required', 'in:adviser,counselor,dorm'],
            'claim_ack_by'   => ['required', 'integer', 'exists:users,id'],
            'claim_remarks'  => ['nullable', 'string'],
        ]);

        $item->update(array_merge($validated, [
            'status'     => 'claimed',
            'claimed_at' => now(),
        ]));

        return back()->with('success', 'Item marked as claimed.');
    }

    public function dispose(DisciplineConfiscatedItem $item)
    {
        $item->update(['status' => 'disposed']);

        return back()->with('success', 'Item marked as disposed.');
    }

    public function pdf(DisciplineConfiscatedItem $item)
    {
        return $this->pdfService->stream($item);
    }

    /* ===================== STUDENT SEARCH ===================== */
    public function searchStudents(Request $request)
    {
        return response()->json([
            'students' => $this->students->search(
                (string) $request->query('q', ''),
                (int) $request->query('limit', 10),
            ),
        ]);
    }

    private function generateReceiptNo(): string
    {
        $prefix = 'CONF-' . now()->format('Y-m');
        $latest = DisciplineConfiscatedItem::where('receipt_no', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(receipt_no, '-', -1) AS UNSIGNED)) as seq")
            ->value('seq');

        return sprintf('%s-%03d', $prefix, ($latest ?? 0) + 1);
    }

    private function studentNameMap($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('students')->whereIn('id', $ids)
            ->get(['id', 'lastname', 'firstname'])
            ->mapWithKeys(fn ($s) => [
                $s->id => trim(($s->lastname ? $s->lastname . ', ' : '') . ($s->firstname ?? '')) ?: ('Student #' . $s->id),
            ])->all();
    }
}
