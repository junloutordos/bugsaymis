<?php

namespace App\Services;

use App\Models\WFHAccomplishment;
use App\Models\WFHAttendance;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WFHService
{
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
            $fileName   = "WFH/{$user->id}/{$dateFolder}/accomplishment_{$photo->getClientOriginalName()}";
            $uploaded = $this->drive->upload($photo, $fileName);

            $payload['google_drive_file_id'] = $uploaded['file_id'];
            $payload['google_drive_link']    = $uploaded['link'];
            $payload['file_name']            = $photo->getClientOriginalName();
        }

        return WFHAccomplishment::create($payload);
    }

    /**
     * Delete a WFH accomplishment. Also removes the Google Drive file if present.
     */
    public function deleteAccomplishment(WFHAccomplishment $accomplishment): void
    {
        if ($accomplishment->google_drive_file_id) {
            $this->drive->delete($accomplishment->google_drive_file_id);
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
     * Decode a base64 data URI, write it to a temp file, wrap it in an
     * UploadedFile-compatible structure, then upload to Google Drive.
     *
     * @return array{ file_id: string, link: string }
     */
    private function uploadBase64Photo(string $dataUri, string $drivePath): array
    {
        // Strip the data URI prefix (e.g. "data:image/jpeg;base64,")
        if (str_contains($dataUri, ',')) {
            [, $base64] = explode(',', $dataUri, 2);
        } else {
            $base64 = $dataUri;
        }

        $imageData = base64_decode($base64);

        $tmpPath = tempnam(sys_get_temp_dir(), 'wfh_') . '.jpg';
        file_put_contents($tmpPath, $imageData);

        $uploadedFile = new UploadedFile(
            $tmpPath,
            basename($drivePath),
            'image/jpeg',
            null,
            true   // test mode — skip is_uploaded_file() check
        );

        $result = $this->drive->upload($uploadedFile, $drivePath);

        @unlink($tmpPath);

        return $result;
    }
}
