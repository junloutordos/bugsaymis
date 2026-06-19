<?php

namespace App\Services\HR;

use App\Models\HR\FaceEnrollment;
use App\Models\HR\OnlineTimePunch;
use App\Models\User;
use Aws\Rekognition\RekognitionClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FaceRecognitionService
{
    /** Punch types must be recorded in this order within a single work day. */
    private const PUNCH_SEQUENCE = ['time_in_am', 'time_out_am', 'time_in_pm', 'time_out_pm'];

    private const LIVENESS_THRESHOLD = 90.0;
    private const MATCH_VERIFIED_THRESHOLD = 90.0;
    private const MATCH_REVIEW_THRESHOLD = 80.0;
    private const LOCKOUT_WINDOW_MINUTES = 5;
    private const LOCKOUT_FAILURE_COUNT = 5;

    public function __construct(private readonly DTRService $dtrService) {}

    // ─── Enrollment ───────────────────────────────────────────────────────────

    /**
     * Self-enroll a reference face photo. Stays "pending" until HR approves it —
     * enrollment is the trust anchor for every future match, so it requires a
     * human review step rather than activating instantly.
     */
    public function enroll(User $user, string $photoBase64, ?string $consentIp): FaceEnrollment
    {
        if (FaceEnrollment::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'photo' => 'You already have a face enrollment pending HR approval.',
            ]);
        }

        $imageBytes = $this->decodeBase64Image($photoBase64);

        $faces = $this->rekognition()->detectFaces([
            'Image'      => ['Bytes' => $imageBytes],
            'Attributes' => ['DEFAULT'],
        ]);

        $count = count($faces['FaceDetails'] ?? []);
        if ($count === 0) {
            throw ValidationException::withMessages(['photo' => 'No face was detected in the photo. Please try again.']);
        }
        if ($count > 1) {
            throw ValidationException::withMessages(['photo' => 'Multiple faces detected. Make sure only you are in frame.']);
        }

        $s3Key = "face_enrollments/{$user->id}/" . now()->timestamp . '.jpg';
        Storage::disk('s3')->put($s3Key, $imageBytes);

        return FaceEnrollment::create([
            'user_id'           => $user->id,
            's3_key'            => $s3Key,
            'status'            => 'pending',
            'consent_given_at'  => now(),
            'consent_ip'        => $consentIp,
            'enrolled_at'       => now(),
            'is_active'         => true,
        ]);
    }

    public function approve(FaceEnrollment $enrollment, User $approver): void
    {
        FaceEnrollment::where('user_id', $enrollment->user_id)
            ->where('id', '!=', $enrollment->id)
            ->update(['is_active' => false]);

        $enrollment->update([
            'status'      => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'is_active'   => true,
        ]);
    }

    public function reject(FaceEnrollment $enrollment, User $approver, string $reason): void
    {
        $enrollment->update([
            'status'            => 'rejected',
            'approved_by'       => $approver->id,
            'approved_at'       => now(),
            'rejection_reason'  => $reason,
            'is_active'         => false,
        ]);
    }

    // ─── Liveness Session ─────────────────────────────────────────────────────

    public function createLivenessSession(): string
    {
        $result = $this->rekognition()->createFaceLivenessSession([]);

        return $result['SessionId'];
    }

    // ─── Punch Verification ───────────────────────────────────────────────────

    public function verifyPunch(
        User $user,
        string $sessionId,
        string $punchType,
        ?string $ip,
        ?float $lat,
        ?float $lng,
        ?string $userAgent,
    ): OnlineTimePunch {
        $today = Carbon::today()->toDateString();

        if (! in_array($punchType, self::PUNCH_SEQUENCE, true)) {
            throw ValidationException::withMessages(['punch_type' => 'Invalid punch type.']);
        }

        if (OnlineTimePunch::where('user_id', $user->id)->where('work_date', $today)->where('punch_type', $punchType)->exists()) {
            throw ValidationException::withMessages(['punch_type' => 'You have already recorded this punch today.']);
        }

        $sequenceIndex = array_search($punchType, self::PUNCH_SEQUENCE, true);
        if ($sequenceIndex > 0) {
            $previousType = self::PUNCH_SEQUENCE[$sequenceIndex - 1];
            $hasPrevious  = OnlineTimePunch::where('user_id', $user->id)
                ->where('work_date', $today)
                ->where('punch_type', $previousType)
                ->where('match_status', 'verified')
                ->exists();

            if (! $hasPrevious) {
                throw ValidationException::withMessages([
                    'punch_type' => 'You must record "' . str_replace('_', ' ', $previousType) . '" first.',
                ]);
            }
        }

        $enrollment = FaceEnrollment::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'You must enroll your face (and be approved by HR) before using Online Time Punches.',
            ]);
        }

        if ($this->isLockedOut($user->id)) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'   => 'manual_review',
                'failure_reason' => 'repeated_failures',
            ], $ip, $lat, $lng, $userAgent);
        }

        $liveness = $this->rekognition()->getFaceLivenessSessionResults(['SessionId' => $sessionId]);

        if (($liveness['Status'] ?? null) !== 'SUCCEEDED') {
            throw ValidationException::withMessages([
                'liveness' => 'Face liveness check did not complete. Please try again.',
            ]);
        }

        $confidence       = (float) ($liveness['Confidence'] ?? 0);
        $referenceBytes   = isset($liveness['ReferenceImage']['Bytes']) ? (string) $liveness['ReferenceImage']['Bytes'] : null;
        $photoS3Key       = null;

        if ($referenceBytes) {
            $photoS3Key = "online_punches/{$user->id}/{$today}/{$punchType}.jpg";
            Storage::disk('s3')->put($photoS3Key, $referenceBytes);
        }

        if ($confidence < self::LIVENESS_THRESHOLD || ! $referenceBytes) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'liveness_session_id'  => $sessionId,
                'liveness_confidence'  => $confidence,
                'photo_s3_key'         => $photoS3Key,
                'match_status'         => 'rejected',
                'failure_reason'       => 'liveness_failed',
            ], $ip, $lat, $lng, $userAgent);
        }

        $enrolledBytes = Storage::disk('s3')->get($enrollment->s3_key);

        $compare = $this->rekognition()->compareFaces([
            'SourceImage'         => ['Bytes' => $referenceBytes],
            'TargetImage'         => ['Bytes' => $enrolledBytes],
            'SimilarityThreshold' => 0,
        ]);

        $similarity = $compare['FaceMatches'][0]['Similarity'] ?? 0;

        [$matchStatus, $failureReason] = match (true) {
            $similarity >= self::MATCH_VERIFIED_THRESHOLD => ['verified', null],
            $similarity >= self::MATCH_REVIEW_THRESHOLD   => ['manual_review', 'low_confidence'],
            default                                         => ['rejected', 'no_match'],
        };

        $punch = $this->savePunch($user, $enrollment, $punchType, $today, [
            'liveness_session_id'  => $sessionId,
            'liveness_confidence'  => $confidence,
            'photo_s3_key'         => $photoS3Key,
            'match_score'          => $similarity,
            'match_status'         => $matchStatus,
            'failure_reason'       => $failureReason,
        ], $ip, $lat, $lng, $userAgent);

        if ($matchStatus === 'verified') {
            $this->dtrService->generate($user->id, $today, $today);
        }

        return $punch;
    }

    // ─── Image Proxy Helper ───────────────────────────────────────────────────

    public function encodeS3Key(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeS3Key(string $fileId): ?string
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

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function savePunch(
        User $user,
        FaceEnrollment $enrollment,
        string $punchType,
        string $workDate,
        array $attributes,
        ?string $ip,
        ?float $lat,
        ?float $lng,
        ?string $userAgent,
    ): OnlineTimePunch {
        return OnlineTimePunch::create(array_merge([
            'user_id'             => $user->id,
            'face_enrollment_id'  => $enrollment->id,
            'work_date'           => $workDate,
            'punch_type'          => $punchType,
            'punched_at'          => now(),
            'ip_address'          => $ip,
            'latitude'            => $lat,
            'longitude'           => $lng,
            'user_agent'          => $userAgent,
        ], $attributes));
    }

    /**
     * Flag a user for manual review once they have accumulated repeated
     * non-verified punch attempts within a short window — likely spoofing
     * attempts rather than genuine lighting/camera failures.
     */
    private function isLockedOut(int $userId): bool
    {
        return OnlineTimePunch::where('user_id', $userId)
            ->where('match_status', '!=', 'verified')
            ->where('created_at', '>=', now()->subMinutes(self::LOCKOUT_WINDOW_MINUTES))
            ->count() >= self::LOCKOUT_FAILURE_COUNT;
    }

    private function decodeBase64Image(string $dataUri): string
    {
        if (str_contains($dataUri, ',')) {
            [, $base64] = explode(',', $dataUri, 2);
        } else {
            $base64 = $dataUri;
        }

        return base64_decode($base64);
    }

    private function rekognition(): RekognitionClient
    {
        $config = [
            'version' => 'latest',
            'region'  => config('services.rekognition.region'),
        ];

        // Only pass explicit static credentials when both are set (local dev
        // with an IAM access key). In production there are none — the AWS
        // SDK falls back to its default provider chain (ECS task role),
        // exactly like Storage::disk('s3') already does.
        $key    = env('AWS_ACCESS_KEY_ID');
        $secret = env('AWS_SECRET_ACCESS_KEY');
        if (! empty($key) && ! empty($secret)) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        return new RekognitionClient($config);
    }
}
