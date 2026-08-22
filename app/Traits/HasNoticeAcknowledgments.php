<?php

namespace App\Traits;

use App\Models\NoticeAcknowledgment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared read/acknowledgment tracking for anything a recipient (User,
 * Student, or ParentContact) must explicitly dismiss — Announcements and
 * EmergencyAlerts both use this rather than each building their own
 * per-recipient-type pivot tables.
 */
trait HasNoticeAcknowledgments
{
    public function acknowledgments(): MorphMany
    {
        return $this->morphMany(NoticeAcknowledgment::class, 'notice');
    }

    public function isAcknowledgedBy(Model $recipient): bool
    {
        return $this->acknowledgments()
            ->where('recipient_type', get_class($recipient))
            ->where('recipient_id', $recipient->getKey())
            ->exists();
    }

    public function acknowledgeFor(Model $recipient): void
    {
        NoticeAcknowledgment::firstOrCreate([
            'notice_type'    => static::class,
            'notice_id'      => $this->getKey(),
            'recipient_type' => get_class($recipient),
            'recipient_id'   => $recipient->getKey(),
        ], [
            'acknowledged_at' => now(),
        ]);
    }
}
