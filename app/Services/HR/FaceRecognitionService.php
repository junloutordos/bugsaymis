<?php

namespace App\Services\HR;

use App\Models\HR\FaceEnrollment;
use App\Models\HR\OnlineTimePunch;
use App\Models\User;
use App\Services\CampusPresenceService;
use Aws\Rekognition\RekognitionClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaceRecognitionService
{
    /** Punch types must be recorded in this order within a single work day. */
    private const PUNCH_SEQUENCE = ['time_in_am', 'time_out_am', 'time_in_pm', 'time_out_pm'];

    private const MATCH_VERIFIED_THRESHOLD = 90.0;
    private const MATCH_REVIEW_THRESHOLD = 80.0;
    private const LOCKOUT_WINDOW_MINUTES = 5;
    private const LOCKOUT_FAILURE_COUNT = 5;
    public const MAX_PUNCH_ATTEMPTS = 3;

    private const CHALLENGE_TYPES = ['turn_head', 'blink'];
    private const CHALLENGE_TTL_SECONDS = 30;
    private const CHALLENGE_MIN_FRAMES = 4;
    private const CHALLENGE_MAX_FRAMES = 7;
    private const CHALLENGE_MAX_WINDOW_MS = 4000;
    private const CHALLENGE_TURN_HEAD_MIN_YAW_RANGE = 12.0;
    private const QUALITY_MIN_BRIGHTNESS = 35.0;
    private const QUALITY_MIN_SHARPNESS = 35.0;
    private const MAX_BBOX_AREA_RATIO = 1.6;

    /**
     * Set once per verifyPunch() call and read by savePunch() — never used to
     * gate a punch on its own (a non-match is inconclusive, see
     * NetworkTrustService), so it's threaded through as instance state rather
     * than adding a parameter to every one of savePunch()'s call sites.
     */
    private ?string $currentNetworkStatus = null;

    /** Set once per verifyPunch() call and read by savePunch() — same reasoning as above. */
    private int $currentAttemptNumber = 1;

    /** Set once per verifyPunch() call and read by savePunch() — same reasoning as above. */
    private ?float $currentAccuracy = null;

    public function __construct(
        private readonly DTRService $dtrService,
        private readonly CampusPresenceService $campusPresence,
    ) {}

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

    // ─── Liveness Challenge ─────────────────────────────────────────────────────

    /**
     * Issue a random challenge (head turn or blink) as a short-lived signed
     * token. The token carries its own state (user, type, issue time) so no
     * extra table is needed for this ephemeral session — it's validated on
     * submission and expires quickly to prevent pre-recorded replay.
     */
    public function issueChallenge(User $user): array
    {
        $type = self::CHALLENGE_TYPES[array_rand(self::CHALLENGE_TYPES)];

        $payload = [
            'session_id'     => (string) Str::uuid(),
            'user_id'        => $user->id,
            'challenge_type' => $type,
            'issued_at'      => now()->timestamp,
        ];

        return [
            'challenge_token' => Crypt::encryptString(json_encode($payload)),
            'challenge_type'  => $type,
            'instruction'     => $type === 'turn_head'
                ? 'Slowly turn your head to one side, then back to center.'
                : 'Blink your eyes naturally while looking at the camera.',
        ];
    }

    // ─── Punch Verification ───────────────────────────────────────────────────

    /**
     * Verify a punch against the employee's enrolled reference face.
     *
     * There is no real AWS Rekognition Face Liveness here — CreateFaceLivenessSession
     * is blocked account-wide by an AWS Organizations Service Control Policy outside
     * our control. Instead this runs a DIY challenge-response check: the client
     * captures a short burst of frames while performing a randomly-issued challenge
     * (head turn or blink), and this method verifies the Pose/EyesOpen sequence
     * actually moved the way the challenge demanded, on top of the existing
     * DetectFaces quality gate + CompareFaces identity match. This is meaningfully
     * harder to spoof than a single static photo, but is not depth-sensor-grade
     * liveness — a well-produced real-time video of the actual person could still
     * theoretically pass. Campus geofencing is enforced first, before any
     * Rekognition calls, since a location failure isn't a face-match failure.
     */
    /**
     * Resolve whether a punch attempt is allowed from this location/network.
     * Shared by verifyPunch() and the pre-check endpoint so the fail-fast UI
     * and the enforcement path can never disagree.
     *
     * status: ok | outside | no_permission | coarse
     */
    public function resolveLocationGate(?float $lat, ?float $lng, ?float $accuracy, ?string $ip): array
    {
        return $this->campusPresence->resolveLocationGate($lat, $lng, $accuracy, $ip);
    }

    public function verifyPunch(
        User $user,
        array $frames,
        string $challengeToken,
        string $punchType,
        ?string $ip,
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        ?string $userAgent,
    ): OnlineTimePunch {
        $today = Carbon::today()->toDateString();

        if (! in_array($punchType, self::PUNCH_SEQUENCE, true)) {
            throw ValidationException::withMessages(['punch_type' => 'Invalid punch type.']);
        }

        $existingForSlot = OnlineTimePunch::where('user_id', $user->id)
            ->where('work_date', $today)
            ->where('punch_type', $punchType)
            ->get();

        // verified/manual_review means the punch WAS recorded (the latter just
        // pending HR's confirmation) — no further attempts needed or allowed.
        if ($existingForSlot->whereIn('match_status', ['verified', 'manual_review'])->isNotEmpty()) {
            throw ValidationException::withMessages(['punch_type' => 'You have already recorded this punch today.']);
        }

        // Only genuine rejections count toward the retry cap.
        $rejectedCount = $existingForSlot->where('match_status', 'rejected')->count();
        if ($rejectedCount >= self::MAX_PUNCH_ATTEMPTS) {
            throw ValidationException::withMessages([
                'max_attempts' => "You've reached the maximum number of attempts. Please proceed to the fingerprint biometric device to record your attendance.",
            ]);
        }

        $this->currentAttemptNumber = $existingForSlot->count() + 1;

        $enrollment = FaceEnrollment::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'You must enroll your face (and be approved by HR) before using Online Time Punches.',
            ]);
        }

        // Location gate first — cheap, and keeps the audit trail honest (a
        // location failure is recorded as a location failure, not a face failure).
        $gate     = $this->resolveLocationGate($lat, $lng, $accuracy, $ip);
        $geofence = $gate['geofence'];

        $this->currentNetworkStatus = $gate['networkStatus'];
        $this->currentAccuracy      = $accuracy;

        if ($gate['status'] !== 'ok') {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'    => 'rejected',
                'failure_reason'  => match ($gate['status']) {
                    'outside'       => 'outside_geofence',
                    'no_permission' => 'no_location_permission',
                    'coarse'        => 'location_too_coarse',
                },
                'geofence_status' => $geofence['status'],
                'distance_meters' => $geofence['distanceMeters'],
            ], $ip, $lat, $lng, $userAgent);
        }

        if ($this->isLockedOut($user->id)) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'    => 'manual_review',
                'failure_reason'  => 'repeated_failures',
                'geofence_status' => $geofence['status'],
                'distance_meters' => $geofence['distanceMeters'],
            ], $ip, $lat, $lng, $userAgent);
        }

        try {
            $challenge = $this->decodeChallengeToken($challengeToken, $user);
        } catch (ValidationException $e) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'    => 'rejected',
                'failure_reason'  => 'challenge_invalid_or_expired',
                'geofence_status' => $geofence['status'],
                'distance_meters' => $geofence['distanceMeters'],
            ], $ip, $lat, $lng, $userAgent);
        }

        if (count($frames) < self::CHALLENGE_MIN_FRAMES || count($frames) > self::CHALLENGE_MAX_FRAMES) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'            => 'rejected',
                'failure_reason'          => 'invalid_frame_sequence',
                'geofence_status'         => $geofence['status'],
                'distance_meters'         => $geofence['distanceMeters'],
                'liveness_status'         => 'failed',
                'liveness_challenge_type' => $challenge['challenge_type'],
                'liveness_session_id'     => $challenge['session_id'],
            ], $ip, $lat, $lng, $userAgent);
        }

        usort($frames, fn ($a, $b) => $a['capturedAtMs'] <=> $b['capturedAtMs']);
        $windowMs = end($frames)['capturedAtMs'] - $frames[0]['capturedAtMs'];

        if ($windowMs < 0 || $windowMs > self::CHALLENGE_MAX_WINDOW_MS) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'match_status'            => 'rejected',
                'failure_reason'          => 'challenge_timeout',
                'geofence_status'         => $geofence['status'],
                'distance_meters'         => $geofence['distanceMeters'],
                'liveness_status'         => 'failed',
                'liveness_challenge_type' => $challenge['challenge_type'],
                'liveness_session_id'     => $challenge['session_id'],
            ], $ip, $lat, $lng, $userAgent);
        }

        $decodedFrames = array_map(fn ($f) => $this->decodeBase64Image($f['data']), $frames);

        $frameS3Keys = [];
        foreach ($decodedFrames as $i => $bytes) {
            $key = "online_punches/{$user->id}/{$today}/{$punchType}_frame{$i}.jpg";
            Storage::disk('s3')->put($key, $bytes);
            $frameS3Keys[] = $key;
        }

        $faceDetails = [];
        foreach ($decodedFrames as $i => $bytes) {
            $result = $this->rekognition()->detectFaces([
                'Image'      => ['Bytes' => $bytes],
                'Attributes' => ['ALL'],
            ]);

            $count = count($result['FaceDetails'] ?? []);

            if ($count === 0) {
                return $this->savePunch($user, $enrollment, $punchType, $today, [
                    'photo_s3_key'            => $frameS3Keys[$i],
                    'challenge_frames_s3_keys' => $frameS3Keys,
                    'match_status'            => 'rejected',
                    'failure_reason'          => 'no_face_detected',
                    'geofence_status'         => $geofence['status'],
                    'distance_meters'         => $geofence['distanceMeters'],
                    'liveness_status'         => 'failed',
                    'liveness_challenge_type' => $challenge['challenge_type'],
                    'liveness_session_id'     => $challenge['session_id'],
                ], $ip, $lat, $lng, $userAgent);
            }
            if ($count > 1) {
                return $this->savePunch($user, $enrollment, $punchType, $today, [
                    'photo_s3_key'            => $frameS3Keys[$i],
                    'challenge_frames_s3_keys' => $frameS3Keys,
                    'match_status'            => 'rejected',
                    'failure_reason'          => 'multiple_faces',
                    'geofence_status'         => $geofence['status'],
                    'distance_meters'         => $geofence['distanceMeters'],
                    'liveness_status'         => 'failed',
                    'liveness_challenge_type' => $challenge['challenge_type'],
                    'liveness_session_id'     => $challenge['session_id'],
                ], $ip, $lat, $lng, $userAgent);
            }

            $faceDetails[] = $result['FaceDetails'][0];
        }

        // Bounding-box consistency — guards against swapping in a different
        // photo mid-sequence rather than genuinely moving one face.
        $areas = array_map(fn ($f) => $f['BoundingBox']['Width'] * $f['BoundingBox']['Height'], $faceDetails);
        if (max($areas) / max(min($areas), 0.0001) > self::MAX_BBOX_AREA_RATIO) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'photo_s3_key'            => $frameS3Keys[0],
                'challenge_frames_s3_keys' => $frameS3Keys,
                'match_status'            => 'rejected',
                'failure_reason'          => 'inconsistent_face_frames',
                'geofence_status'         => $geofence['status'],
                'distance_meters'         => $geofence['distanceMeters'],
                'liveness_status'         => 'failed',
                'liveness_challenge_type' => $challenge['challenge_type'],
                'liveness_session_id'     => $challenge['session_id'],
            ], $ip, $lat, $lng, $userAgent);
        }

        // Pick the sharpest, best-lit frame as the reference for identity match.
        $bestIndex = 0;
        $bestScore = -1;
        foreach ($faceDetails as $i => $face) {
            $score = ($face['Quality']['Brightness'] ?? 0) + ($face['Quality']['Sharpness'] ?? 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        $bestQuality = $faceDetails[$bestIndex]['Quality'] ?? [];
        if (($bestQuality['Brightness'] ?? 0) < self::QUALITY_MIN_BRIGHTNESS || ($bestQuality['Sharpness'] ?? 0) < self::QUALITY_MIN_SHARPNESS) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'photo_s3_key'            => $frameS3Keys[$bestIndex],
                'challenge_frames_s3_keys' => $frameS3Keys,
                'match_status'            => 'rejected',
                'failure_reason'          => 'poor_image_quality',
                'geofence_status'         => $geofence['status'],
                'distance_meters'         => $geofence['distanceMeters'],
                'liveness_status'         => 'failed',
                'liveness_challenge_type' => $challenge['challenge_type'],
                'liveness_session_id'     => $challenge['session_id'],
            ], $ip, $lat, $lng, $userAgent);
        }

        $liveness = $this->evaluateChallenge($challenge['challenge_type'], $faceDetails);

        if (! $liveness['pass']) {
            return $this->savePunch($user, $enrollment, $punchType, $today, [
                'photo_s3_key'            => $frameS3Keys[$bestIndex],
                'challenge_frames_s3_keys' => $frameS3Keys,
                'match_status'            => 'rejected',
                'failure_reason'          => 'liveness_challenge_failed',
                'geofence_status'         => $geofence['status'],
                'distance_meters'         => $geofence['distanceMeters'],
                'liveness_status'         => 'failed',
                'liveness_challenge_type' => $challenge['challenge_type'],
                'liveness_session_id'     => $challenge['session_id'],
                'liveness_confidence'     => $liveness['confidence'],
            ], $ip, $lat, $lng, $userAgent);
        }

        $enrolledBytes = Storage::disk('s3')->get($enrollment->s3_key);

        $compare = $this->rekognition()->compareFaces([
            'SourceImage'         => ['Bytes' => $decodedFrames[$bestIndex]],
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
            'photo_s3_key'             => $frameS3Keys[$bestIndex],
            'challenge_frames_s3_keys' => $frameS3Keys,
            'match_score'              => $similarity,
            'match_status'             => $matchStatus,
            'failure_reason'           => $failureReason,
            'geofence_status'          => $geofence['status'],
            'distance_meters'          => $geofence['distanceMeters'],
            'liveness_status'          => 'passed',
            'liveness_challenge_type'  => $challenge['challenge_type'],
            'liveness_session_id'      => $challenge['session_id'],
            'liveness_confidence'      => $liveness['confidence'],
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

    /**
     * @param array<int, array{data: string, capturedAtMs: int}> $frames
     */
    private function decodeChallengeToken(string $token, User $user): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['challenge_token' => 'Invalid challenge token.']);
        }

        if (! is_array($payload) || ($payload['user_id'] ?? null) !== $user->id) {
            throw ValidationException::withMessages(['challenge_token' => 'Invalid challenge token.']);
        }

        if (! in_array($payload['challenge_type'] ?? null, self::CHALLENGE_TYPES, true)) {
            throw ValidationException::withMessages(['challenge_token' => 'Invalid challenge token.']);
        }

        if (now()->timestamp - (int) ($payload['issued_at'] ?? 0) > self::CHALLENGE_TTL_SECONDS) {
            throw ValidationException::withMessages(['challenge_token' => 'Challenge expired, please try again.']);
        }

        return $payload;
    }

    /**
     * Check the Pose/EyesOpen sequence against what the issued challenge demands.
     * A static photo cannot produce either pattern, unlike genuine low-confidence
     * identity matches where some leniency makes sense.
     */
    private function evaluateChallenge(string $challengeType, array $faceDetails): array
    {
        if ($challengeType === 'turn_head') {
            $yaws = array_map(fn ($f) => $f['Pose']['Yaw'] ?? 0.0, $faceDetails);
            $range = max($yaws) - min($yaws);

            return ['pass' => $range >= self::CHALLENGE_TURN_HEAD_MIN_YAW_RANGE, 'confidence' => round($range, 2)];
        }

        // blink: look for a closed-eye frame with open frames on both sides.
        $eyesOpen = array_map(fn ($f) => (bool) ($f['EyesOpen']['Value'] ?? true), $faceDetails);
        $count = count($eyesOpen);

        for ($i = 1; $i < $count - 1; $i++) {
            if ($eyesOpen[$i - 1] === true && $eyesOpen[$i] === false && $eyesOpen[$i + 1] === true) {
                $closedConfidence = $faceDetails[$i]['EyesOpen']['Confidence'] ?? 0.0;
                return ['pass' => true, 'confidence' => round($closedConfidence, 2)];
            }
        }

        return ['pass' => false, 'confidence' => 0.0];
    }

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
            'attempt_number'      => $this->currentAttemptNumber,
            'punched_at'          => now(),
            'ip_address'          => $ip,
            'latitude'            => $lat,
            'longitude'           => $lng,
            'accuracy_meters'     => $this->currentAccuracy,
            'network_status'      => $this->currentNetworkStatus,
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
