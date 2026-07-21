<?php

namespace App\Services;

use App\Jobs\NotifyOedIssuanceUpload;
use App\Models\OedIssuance;
use App\Models\OedIssuanceRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeManagementService
{
    // ── File storage ──────────────────────────────────────────────────────────

    public function storeFile(string $base64, string $originalFilename, ?string $mime, int $year): array
    {
        $matches = [];
        if (preg_match('/^data:([^;]+);base64,/', $base64, $matches)) {
            $mime = $mime ?: $matches[1];
        }
        $raw = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $base64));

        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'pdf';

        $path = 'knowledge-management/' . $year . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('s3')->put($path, $raw);

        return [
            'file_path' => $path,
            'file_name' => $originalFilename,
            'file_mime' => $mime ?: 'application/pdf',
            'file_size' => strlen($raw),
        ];
    }

    public function deleteFile(string $path): void
    {
        if (Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }

    // ── Supersession ──────────────────────────────────────────────────────────

    public function linkSupersession(OedIssuance $old, OedIssuance $new): void
    {
        $old->update([
            'status'           => 'superseded',
            'superseded_by_id' => $new->id,
        ]);
    }

    // ── Recipient fan-out ─────────────────────────────────────────────────────

    public function buildRecipients(OedIssuance $issuance, string $recipientType, array $ids): void
    {
        if ($recipientType === 'all' || empty($ids)) {
            return;
        }

        $userIds = match ($recipientType) {
            'individual' => User::employees()->where('status', '<>', 'inactive')->whereIn('id', $ids)->pluck('id'),
            'office'     => User::employees()->where('status', '<>', 'inactive')->whereIn('office_id', $ids)->pluck('id'),
            'division'   => User::employees()->where('status', '<>', 'inactive')->whereIn('division_id', $ids)->pluck('id'),
            default      => collect(),
        };

        $rows = $userIds->unique()->map(fn ($uid) => [
            'oed_issuance_id' => $issuance->id,
            'user_id'         => $uid,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->all();

        if ($rows) {
            OedIssuanceRecipient::insert($rows);
        }
    }

    public function rebuildRecipients(OedIssuance $issuance, string $recipientType, array $ids): void
    {
        $issuance->recipients()->delete();
        $this->buildRecipients($issuance, $recipientType, $ids);
    }

    // ── Acknowledgment ────────────────────────────────────────────────────────

    public function acknowledge(OedIssuance $issuance, int $userId): void
    {
        $issuance->acknowledgments()->firstOrCreate(
            ['user_id' => $userId],
            ['acknowledged_at' => now()],
        );
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    public function notifyUpload(OedIssuance $issuance): void
    {
        NotifyOedIssuanceUpload::dispatch($issuance->id);
    }
}
