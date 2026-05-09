<?php

namespace App\Services;

use App\Models\WFHAccomplishment;
use App\Models\WFHAttendance;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WFHService
{
    // GoogleDriveService kept for backward-compatible deletion of old Drive files
    public function __construct(private readonly GoogleDriveService $drive) {}

    // ─── Time In ──────────────────────────────────────────────────────────────

    /**
     * Record a time-in for today. Throws ValidationException if the user
     * has already timed in today.
     *
     * @param  string      $photoBase64  Base64 data URI from the camera capture
     * @param  string|null $ip
     * @param  float|null  $lat
     * @param  float|null  $lng
     */
    public function timeIn(string $photoBase64, ?string $ip, ?float $lat, ?float $lng): WFHAttendance
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $existing = WFHAttendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'date' => 'You have already timed in today.',
            ]);
        }

        $uploaded = $this->uploadBase64Photo(
            $photoBase64,
            "WFH/{$user->id}/{$today}/time_in.jpg"
        );

        return WFHAttendance::create([
            'user_id'               => $user->id,
            'date'                  => $today,
            'time_in'               => Carbon::now(),
            'time_in_photo_file_id' => $uploaded['file_id'],
            'time_in_photo_link'    => $uploaded['link'],
            'ip_address'            => $ip,
            'latitude'              => $lat,
            'longitude'             => $lng,
        ]);
    }

    // ─── Time Out ─────────────────────────────────────────────────────────────

    /**
     * Record a time-out for today. Throws ValidationException if the user
     * has not yet timed in, or has already timed out.
     */
    public function timeOut(string $photoBase64, ?float $lat, ?float $lng): WFHAttendance
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $attendance = WFHAttendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'date' => 'You have not timed in today.',
            ]);
        }

        if ($attendance->isTimedOut()) {
            throw ValidationException::withMessages([
                'date' => 'You have already timed out today.',
            ]);
        }

        $uploaded = $this->uploadBase64Photo(
            $photoBase64,
            "WFH/{$user->id}/{$today}/time_out.jpg"
        );

        $attendance->update([
            'time_out'               => Carbon::now(),
            'time_out_photo_file_id' => $uploaded['file_id'],
            'time_out_photo_link'    => $uploaded['link'],
            'latitude'               => $lat ?? $attendance->latitude,
            'longitude'              => $lng ?? $attendance->longitude,
        ]);

        return $attendance->fresh();
    }

    // ─── Break Out (start of lunch) ───────────────────────────────────────────

    public function breakOut(): WFHAttendance
    {
        $user       = Auth::user();
        $attendance = $this->todayAttendance($user->id);

        if (! $attendance) {
            throw ValidationException::withMessages(['date' => 'You have not timed in today.']);
        }
        if ($attendance->break_out) {
            throw ValidationException::withMessages(['date' => 'You have already started your break today.']);
        }
        if ($attendance->time_out) {
            throw ValidationException::withMessages(['date' => 'You have already timed out for the day.']);
        }

        $attendance->update(['break_out' => Carbon::now()]);

        return $attendance->fresh();
    }

    // ─── Break In (return from lunch) ─────────────────────────────────────────

    public function breakIn(): WFHAttendance
    {
        $user       = Auth::user();
        $attendance = $this->todayAttendance($user->id);

        if (! $attendance?->break_out) {
            throw ValidationException::withMessages(['date' => 'You have not started a break yet.']);
        }
        if ($attendance->break_in) {
            throw ValidationException::withMessages(['date' => 'You have already returned from your break today.']);
        }
        if ($attendance->time_out) {
            throw ValidationException::withMessages(['date' => 'You have already timed out for the day.']);
        }

        $attendance->update(['break_in' => Carbon::now()]);

        return $attendance->fresh();
    }

    // ─── Accomplishments ──────────────────────────────────────────────────────

    /**
     * Store a WFH accomplishment for the authenticated user.
     * The user must have timed in today.
     *
     * @param  array             $data  Validated data from StoreAccomplishmentRequest
     * @param  UploadedFile|null $photo File upload (when proof_type = 'photo')
     */
    public function storeAccomplishment(array $data, ?UploadedFile $photo): WFHAccomplishment
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        if (! empty($data['attendance_id'])) {
            $attendance = WFHAttendance::where('id', $data['attendance_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $attendance) {
                throw ValidationException::withMessages([
                    'attendance_id' => 'Attendance record not found or does not belong to you.',
                ]);
            }
        } else {
            $attendance = WFHAttendance::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            if (! $attendance) {
                throw ValidationException::withMessages([
                    'date' => 'You must time in before adding accomplishments.',
                ]);
            }
        }

        $description = $data['description'] ?? null;
        $payload = [
            'wfh_attendance_id' => $attendance->id,
            'user_id'           => $user->id,
            'title'             => $data['title'] ?? mb_strimwidth($description ?? '', 0, 100, '…'),
            'description'       => $description,
            'time_from'         => $data['time_from'] ?? null,
            'time_to'           => $data['time_to'] ?? null,
            'proof_type'        => $data['proof_type'] ?? null,
            'proof_link'        => $data['proof_link'] ?? null,
        ];

        if (($data['proof_type'] ?? null) === 'photo' && $photo) {
            $dateFolder = $attendance->getRawOriginal('date') ?? $today;
            $s3Key      = "WFH/{$user->id}/{$dateFolder}/accomplishment_{$photo->getClientOriginalName()}";

            Storage::disk('s3')->put($s3Key, file_get_contents($photo->getRealPath()));

            // Encode the S3 key so it can be passed as a single URL-safe route segment
            $encodedKey = $this->encodeS3Key($s3Key);

            $payload['google_drive_file_id'] = $encodedKey;
            $payload['google_drive_link']    = url("/hr/wfh/photo/{$encodedKey}");
            $payload['file_name']            = $photo->getClientOriginalName();
        }

        return WFHAccomplishment::create($payload);
    }

    /**
     * Delete a WFH accomplishment. Removes the file from S3 (new) or Drive (legacy).
     */
    public function deleteAccomplishment(WFHAccomplishment $accomplishment): void
    {
        if ($accomplishment->google_drive_file_id) {
            $decoded = $this->decodeS3Key($accomplishment->google_drive_file_id);
            if ($decoded) {
                Storage::disk('s3')->delete($decoded);
            } else {
                // Legacy: old Google Drive file ID
                try { $this->drive->delete($accomplishment->google_drive_file_id); } catch (\Throwable) {}
            }
        }

        $accomplishment->delete();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function todayAttendance(int $userId): ?WFHAttendance
    {
        return WFHAttendance::where('user_id', $userId)
            ->where('date', Carbon::today()->toDateString())
            ->first();
    }

    /**
     * Decode a base64 data URI, upload the raw image to S3, and return the
     * encoded S3 key and a proxy URL for serving it through the app.
     *
     * @return array{ file_id: string, link: string }
     */
    private function uploadBase64Photo(string $dataUri, string $s3Key): array
    {
        if (str_contains($dataUri, ',')) {
            [, $base64] = explode(',', $dataUri, 2);
        } else {
            $base64 = $dataUri;
        }

        $imageData = base64_decode($base64);

        Storage::disk('s3')->put($s3Key, $imageData);

        $encodedKey = $this->encodeS3Key($s3Key);

        return [
            'file_id' => $encodedKey,
            'link'    => url("/hr/wfh/photo/{$encodedKey}"),
        ];
    }

    /**
     * Encode an S3 key as URL-safe base64 so it fits a single route segment.
     * Prefix "s3:" distinguishes encoded S3 keys from legacy Drive file IDs.
     */
    private function encodeS3Key(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    /**
     * Decode an encoded S3 key. Returns the S3 path, or null if it is a
     * legacy Google Drive file ID (does not start with the "s3." prefix).
     */
    private function decodeS3Key(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }
        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad    = strlen($padded) % 4;
        if ($pad) $padded .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($padded, true);
        return ($decoded !== false) ? $decoded : null;
    }
}
