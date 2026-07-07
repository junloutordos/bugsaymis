<?php

namespace App\Services;

use App\Models\LostFound\HonestyPoint;
use App\Models\LostFound\LostFoundItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * All Lost & Found custody transitions. Every state change writes an
 * immutable lost_found_movements row; honesty points are written here
 * and nowhere else — only when GSU confirms physical receipt.
 */
class LostFoundService
{
    public const POINTS_PER_TURNOVER = 10;

    // ── Reporting ─────────────────────────────────────────────────────────────

    /**
     * File a lost report or a found-item report.
     * Actor: ['user_id' => ?, 'student_id' => ?, 'name' => ?]
     *
     * @return LostFoundItem
     */
    public function report(array $data, array $actor): LostFoundItem
    {
        return DB::transaction(function () use ($data, $actor) {
            $photoPath = null;
            if (! empty($data['photo_base64'])) {
                $photoPath = $this->uploadPhoto($data['photo_base64']);
            }

            $item = LostFoundItem::create([
                'type'                => $data['type'],
                'item_name'           => $data['item_name'],
                'description'         => $data['description'] ?? null,
                'category'            => $data['category'] ?? null,
                'location'            => $data['location'] ?? null,
                'date_lost_found'     => $data['date_lost_found'],
                'photo_path'          => $photoPath,
                'reporter_user_id'    => $actor['user_id'] ?? null,
                'reporter_student_id' => $actor['student_id'] ?? null,
                'reporter_name'       => $actor['name'] ?? null,
                'status'              => $data['type'] === 'found' ? 'pending_turnover' : 'open',
            ]);

            $this->logMovement($item, 'reported', $actor, $data['location'] ?? null,
                $data['type'] === 'found'
                    ? 'Found item reported — for turnover to GSU.'
                    : 'Lost item reported.');

            return $item;
        });
    }

    /**
     * GSU logs a walk-in turnover in one step (no prior report): the item
     * goes straight to custody and the finder — if an employee or student —
     * earns honesty points immediately.
     */
    public function logWalkIn(array $data, array $finder, User $gsu): LostFoundItem
    {
        return DB::transaction(function () use ($data, $finder, $gsu) {
            $item = $this->report(array_merge($data, ['type' => 'found']), $finder);
            $this->receive($item, $gsu, $data['storage_location'] ?? null);

            return $item->refresh();
        });
    }

    // ── GSU custody actions ───────────────────────────────────────────────────

    /**
     * GSU confirms physical receipt of a found item. Awards honesty points
     * to the finder (employee or student) exactly once per item.
     */
    public function receive(LostFoundItem $item, User $gsu, ?string $storageLocation = null): void
    {
        abort_unless($item->type === 'found' && $item->status === 'pending_turnover', 422,
            'Only found items awaiting turnover can be received.');

        DB::transaction(function () use ($item, $gsu, $storageLocation) {
            $item->update([
                'status'           => 'in_custody',
                'storage_location' => $storageLocation,
                'turned_over_at'   => now(),
                'received_by'      => $gsu->id,
            ]);

            $this->logMovement($item, 'received', ['user_id' => $gsu->id],
                $storageLocation ?: 'GSU',
                'Turnover confirmed — item now in GSU custody.');

            $this->awardPoints($item, $gsu);
        });
    }

    /** Update the GSU storage location (shelf/box). */
    public function storeAt(LostFoundItem $item, User $gsu, string $storageLocation): void
    {
        abort_unless($item->type === 'found' && $item->status === 'in_custody', 422,
            'Only items in custody can be moved.');

        $item->update(['storage_location' => $storageLocation]);

        $this->logMovement($item, 'stored', ['user_id' => $gsu->id], $storageLocation,
            "Moved to {$storageLocation}.");
    }

    /** Link a lost report to a found item in custody. */
    public function match(LostFoundItem $lostReport, LostFoundItem $foundItem, User $gsu): void
    {
        abort_unless($lostReport->type === 'lost' && $lostReport->status === 'open', 422,
            'Only open lost reports can be matched.');
        abort_unless($foundItem->type === 'found' && $foundItem->status === 'in_custody', 422,
            'Only items in GSU custody can be matched.');

        DB::transaction(function () use ($lostReport, $foundItem, $gsu) {
            $lostReport->update(['status' => 'matched', 'matched_item_id' => $foundItem->id]);
            $foundItem->update(['matched_item_id' => $lostReport->id]);

            $this->logMovement($lostReport, 'matched', ['user_id' => $gsu->id], null,
                "Matched with found item #{$foundItem->id} ({$foundItem->item_name}) — for claiming at GSU.");
            $this->logMovement($foundItem, 'matched', ['user_id' => $gsu->id], null,
                "Matched with lost report #{$lostReport->id}.");
        });
    }

    /** Release a custody item to its owner. */
    public function release(LostFoundItem $item, User $gsu, string $claimedByName): void
    {
        abort_unless($item->type === 'found' && $item->status === 'in_custody', 422,
            'Only items in custody can be released.');

        DB::transaction(function () use ($item, $gsu, $claimedByName) {
            $item->update([
                'status'          => 'claimed',
                'claimed_at'      => now(),
                'claimed_by_name' => $claimedByName,
                'released_by'     => $gsu->id,
            ]);

            $this->logMovement($item, 'released', ['user_id' => $gsu->id], null,
                "Released to {$claimedByName}.");

            // Close the linked lost report, if any.
            if ($item->matched_item_id) {
                $lost = LostFoundItem::find($item->matched_item_id);
                if ($lost && $lost->type === 'lost') {
                    $lost->update(['status' => 'claimed', 'claimed_at' => now()]);
                    $this->logMovement($lost, 'claimed', ['user_id' => $gsu->id], null,
                        'Matched item claimed at GSU.');
                }
            }
        });
    }

    /** Dispose/donate an unclaimed item after the retention period. */
    public function dispose(LostFoundItem $item, User $gsu, ?string $notes = null): void
    {
        abort_unless($item->type === 'found' && $item->status === 'in_custody', 422,
            'Only items in custody can be disposed.');

        $item->update(['status' => 'disposed']);

        $this->logMovement($item, 'disposed', ['user_id' => $gsu->id], null,
            $notes ?: 'Disposed/donated after retention period.');
    }

    /** Reporter (or GSU) closes a lost report that is no longer relevant. */
    public function closeLost(LostFoundItem $item, array $actor, ?string $notes = null): void
    {
        abort_unless($item->type === 'lost' && in_array($item->status, ['open', 'matched'], true), 422,
            'Only open lost reports can be closed.');

        $item->update(['status' => 'closed']);

        $this->logMovement($item, 'closed', $actor, null, $notes ?: 'Report closed.');
    }

    // ── Honesty points ────────────────────────────────────────────────────────

    private function awardPoints(LostFoundItem $item, User $gsu): void
    {
        // Only identifiable finders earn points, exactly once per item.
        if (! $item->reporter_user_id && ! $item->reporter_student_id) {
            return;
        }
        if (HonestyPoint::where('lost_found_item_id', $item->id)->exists()) {
            return;
        }

        HonestyPoint::create([
            'user_id'            => $item->reporter_user_id,
            'student_id'         => $item->reporter_student_id,
            'points'             => self::POINTS_PER_TURNOVER,
            'reason'             => 'found_item_turnover',
            'lost_found_item_id' => $item->id,
            'awarded_by'         => $gsu->id,
        ]);
    }

    /** Total honesty points for an employee or student. */
    public function pointsFor(?int $userId = null, ?int $studentId = null): int
    {
        return (int) HonestyPoint::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->sum('points');
    }

    /** Top finders (employees and students combined) for the leaderboard. */
    public function leaderboard(int $limit = 10): array
    {
        $rows = HonestyPoint::with(['user:id,name', 'student:id,firstname,lastname,middlename'])
            ->get()
            ->groupBy(fn ($p) => $p->user_id ? "u{$p->user_id}" : "s{$p->student_id}")
            ->map(function ($group) {
                $first = $group->first();
                $name  = $first->user_id
                    ? ($first->user?->name ?? 'Employee')
                    : ($first->student?->full_name ?? 'Student');

                return [
                    'name'   => $name,
                    'kind'   => $first->user_id ? 'employee' : 'student',
                    'points' => (int) $group->sum('points'),
                    'items'  => $group->count(),
                ];
            })
            ->sortByDesc('points')
            ->take($limit)
            ->values()
            ->all();

        return $rows;
    }

    // ── Photos ────────────────────────────────────────────────────────────────

    private function uploadPhoto(string $dataUri): string
    {
        if (str_contains($dataUri, ',')) {
            [, $base64] = explode(',', $dataUri, 2);
        } else {
            $base64 = $dataUri;
        }

        $path = 'lost_found/' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.jpg';
        Storage::disk('s3')->put($path, base64_decode($base64));

        return $path;
    }

    /** Stream an item photo from private S3 (used by all three surfaces). */
    public function photoResponse(LostFoundItem $item): Response
    {
        abort_if(empty($item->photo_path), 404);
        abort_unless(Storage::disk('s3')->exists($item->photo_path), 404);

        return response(Storage::disk('s3')->get($item->photo_path), 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ── Shared serialization ──────────────────────────────────────────────────

    /** Map an item for any frontend surface. */
    public function mapItem(LostFoundItem $item, bool $forManage = false): array
    {
        $row = [
            'id'              => $item->id,
            'type'            => $item->type,
            'item_name'       => $item->item_name,
            'description'     => $item->description,
            'category'        => $item->category,
            'category_label'  => LostFoundItem::CATEGORIES[$item->category] ?? $item->category,
            'location'        => $item->location,
            'date_lost_found' => $item->date_lost_found?->format('Y-m-d'),
            'has_photo'       => ! empty($item->photo_path),
            'status'          => $item->status,
            'custody_label'   => $item->custody_label,
            'created_at'      => $item->created_at?->toIso8601String(),
        ];

        if ($forManage) {
            $row += [
                'reporter_name'    => $item->reporter_display_name,
                'reporter_kind'    => $item->reporter_user_id ? 'employee' : ($item->reporter_student_id ? 'student' : 'visitor'),
                'storage_location' => $item->storage_location,
                'matched_item_id'  => $item->matched_item_id,
                'turned_over_at'   => $item->turned_over_at?->toIso8601String(),
                'received_by'      => $item->receivedBy?->name,
                'claimed_at'       => $item->claimed_at?->toIso8601String(),
                'claimed_by_name'  => $item->claimed_by_name,
                'released_by'      => $item->releasedBy?->name,
                'movements'        => $item->movements->map(fn ($m) => [
                    'action'   => $m->action,
                    'location' => $m->location,
                    'notes'    => $m->notes,
                    'actor'    => $m->actor_user_id
                        ? ($m->actorUser?->name ?? 'Employee')
                        : ($m->actor_student_id ? ($m->actorStudent?->full_name ?? 'Student') : ($item->reporter_name ?? '—')),
                    'at'       => $m->created_at?->toIso8601String(),
                ])->all(),
            ];
        }

        return $row;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function logMovement(LostFoundItem $item, string $action, array $actor, ?string $location = null, ?string $notes = null): void
    {
        $item->movements()->create([
            'action'           => $action,
            'location'         => $location,
            'notes'            => $notes,
            'actor_user_id'    => $actor['user_id'] ?? null,
            'actor_student_id' => $actor['student_id'] ?? null,
        ]);
    }
}
