<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\PublishJobItemRequest;
use App\Http\Requests\Recruitment\StoreJobItemRequest;
use App\Models\ApplicationRequirement;
use App\Models\JobItem;
use App\Models\Office;
use App\Models\RecruitmentType;
use App\Models\SalaryGrade;
use App\Services\Recruitment\ArtCardService;
use App\Services\Recruitment\JobItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class JobItemController extends Controller
{
    public function __construct(
        private JobItemService $service,
        private ArtCardService $artCardService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        $query = JobItem::with(['recruitmentType', 'office', 'creator', 'requirements'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(fn ($q) =>
                $q->where('position_title', 'like', "%{$search}%")
                  ->orWhere('plantilla_item_no', 'like', "%{$search}%")
            );
        }

        if ($typeId = $request->input('type_id')) {
            $query->where('recruitment_type_id', $typeId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $items = $query->paginate(20)->withQueryString();

        // Build salary grade lookup: grade => [step => rate] for JS auto-fill
        $salaryTable = SalaryGrade::where('is_current', true)
            ->orderBy('grade')->orderBy('step')
            ->get(['grade', 'step', 'monthly_rate'])
            ->groupBy('grade')
            ->map(fn ($rows) => $rows->keyBy('step')->map(fn ($r) => $r->monthly_rate));

        return Inertia::render('Recruitment/JobItems/Index', [
            'items'            => $items,
            'recruitmentTypes' => RecruitmentType::where('is_active', true)->get(['id', 'name']),
            'offices'          => Office::orderBy('name')->get(['id', 'name']),
            'filters'          => $request->only(['search', 'type_id', 'status']),
            'allRequirements'  => ApplicationRequirement::active()->get(['id', 'name', 'description', 'accepted_formats']),
            'salaryTable'      => $salaryTable,
        ]);
    }

    public function store(StoreJobItemRequest $request)
    {
        $validated = $request->validated();
        $requirementIds  = $validated['requirement_ids']     ?? [];
        $mandatoryFlags  = $validated['requirement_mandatory'] ?? [];

        // Strip requirement fields before passing to service
        $itemData = collect($validated)->except(['requirement_ids', 'requirement_mandatory'])->toArray();

        $item = $this->service->create($itemData);

        // Sync requirements with mandatory flags
        $this->syncRequirements($item, $requirementIds, $mandatoryFlags);
        $this->generateArtCard($item);

        return back()->with('success', "Job item '{$item->position_title}' created.");
    }

    public function update(StoreJobItemRequest $request, JobItem $jobItem)
    {
        $this->authorize('update', $jobItem);

        $validated = $request->validated();
        $requirementIds = $validated['requirement_ids']      ?? [];
        $mandatoryFlags = $validated['requirement_mandatory'] ?? [];

        $itemData = collect($validated)->except(['requirement_ids', 'requirement_mandatory'])->toArray();

        $this->service->update($jobItem, $itemData);
        $this->syncRequirements($jobItem, $requirementIds, $mandatoryFlags);
        $this->generateArtCard($jobItem);

        return back()->with('success', 'Job item updated.');
    }

    public function changeStatus(Request $request, JobItem $jobItem)
    {
        $this->authorize('update', $jobItem);

        $validated = $request->validate([
            'status' => ['required', 'in:draft,approved,published,closed'],
        ]);

        $this->service->changeStatus($jobItem, $validated['status']);

        return back()->with('success', "Status changed to {$validated['status']}.");
    }

    public function publish(PublishJobItemRequest $request, JobItem $jobItem)
    {
        $this->authorize('publish', $jobItem);

        if ($jobItem->status !== 'approved') {
            return back()->withErrors(['error' => 'Only approved job items can be published.']);
        }

        $vacancy = $this->service->publish($jobItem, $request->validated());
        $this->generateArtCard($jobItem);

        return back()->with('success', "Published. Vacancy #{$vacancy->id} is now open.");
    }

    public function destroy(JobItem $jobItem)
    {
        $this->authorize('delete', $jobItem);

        try {
            $this->service->delete($jobItem);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Job item deleted.');
    }

    // ── Art card ───────────────────────────────────────────────────────────────

    public function downloadArtCard(Request $request, JobItem $jobItem, string $type)
    {
        $this->authorize('recruitment.view');
        abort_unless(in_array($type, ['cover', 'detail'], true), 404);
        abort_unless($jobItem->art_card_generated_at, 404, 'Art card has not been generated yet.');

        $path = $type === 'cover' ? $this->artCardService->coverPath($jobItem) : $this->artCardService->detailPath($jobItem);
        abort_unless(Storage::disk('s3')->exists($path), 404);

        $content = Storage::disk('s3')->get($path);
        $slug     = str()->slug($jobItem->position_title);
        $filename = "{$slug}-{$type}.jpg";

        return response($content, 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function regenerateArtCard(JobItem $jobItem)
    {
        $this->authorize('update', $jobItem);

        $this->generateArtCard($jobItem);

        return back()->with('success', 'Art card regenerated.');
    }

    private function generateArtCard(JobItem $item): void
    {
        try {
            $this->artCardService->generate($item);
        } catch (\Throwable $e) {
            logger()->warning('Failed to generate recruitment art card', [
                'job_item_id' => $item->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function syncRequirements(JobItem $item, array $ids, array $mandatoryFlags): void
    {
        if (empty($ids)) {
            $item->requirements()->detach();
            return;
        }

        $sync = [];
        foreach ($ids as $id) {
            $sync[$id] = ['is_mandatory' => (bool) ($mandatoryFlags[$id] ?? true)];
        }
        $item->requirements()->sync($sync);
    }
}
