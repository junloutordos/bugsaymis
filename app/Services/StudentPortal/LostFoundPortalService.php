<?php

namespace App\Services\StudentPortal;

use App\Models\LostFound\LostFoundItem;
use App\Services\LostFoundService;
use Illuminate\Http\Request;

/**
 * Shared student-facing Lost & Found logic for the student web portal
 * and the AtlasGo mobile API — both surfaces render the same payloads.
 */
class LostFoundPortalService
{
    public function __construct(private readonly LostFoundService $svc) {}

    /**
     * Data for the student Lost & Found screen.
     * $student is a students-table row (object with ->id).
     */
    public function screenData(object $student): array
    {
        $board = LostFoundItem::inCustody()
            ->orderByDesc('turned_over_at')
            ->limit(100)
            ->get()
            ->map(fn ($i) => $this->svc->mapItem($i));

        $myReports = LostFoundItem::where('reporter_student_id', $student->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => $this->svc->mapItem($i));

        return [
            'board'      => $board->values()->all(),
            'my_reports' => $myReports->values()->all(),
            'my_points'  => $this->svc->pointsFor(studentId: $student->id),
            'categories' => LostFoundItem::CATEGORIES,
        ];
    }

    /**
     * Validate and file a report on behalf of the student.
     * Returns the created item mapped for display.
     */
    public function storeReport(Request $request, object $student): array
    {
        $data = $request->validate([
            'type'            => 'required|in:lost,found',
            'item_name'       => 'required|string|max:150',
            'description'     => 'nullable|string|max:1000',
            'category'        => 'nullable|string|max:40',
            'location'        => 'nullable|string|max:150',
            'date_lost_found' => 'required|date|before_or_equal:today',
            'photo_base64'    => 'nullable|string|starts_with:data:image',
        ]);

        $item = $this->svc->report($data, ['student_id' => $student->id]);

        return $this->svc->mapItem($item);
    }

    /** The item photo, only for board items or the student's own reports. */
    public function photo(object $student, LostFoundItem $item)
    {
        $visible = ($item->type === 'found' && in_array($item->status, ['in_custody', 'claimed'], true))
            || (int) $item->reporter_student_id === (int) $student->id;

        abort_unless($visible, 403);

        return $this->svc->photoResponse($item);
    }
}
