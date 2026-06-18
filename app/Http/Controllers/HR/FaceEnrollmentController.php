<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\FaceEnrollmentRequest;
use App\Models\HR\FaceEnrollment;
use App\Services\HR\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FaceEnrollmentController extends Controller
{
    public function __construct(private readonly FaceRecognitionService $faceService) {}

    // ─── Employee: Self-Enroll ────────────────────────────────────────────────

    public function selfEnrollPage()
    {
        $enrollment = FaceEnrollment::where('user_id', Auth::id())
            ->where('is_active', true)
            ->latest()
            ->first();

        return Inertia::render('HumanResource/OnlinePunch/Enrollment/SelfEnroll', [
            'enrollment' => $this->withPhotoUrl($enrollment),
        ]);
    }

    public function store(FaceEnrollmentRequest $request)
    {
        $enrollment = $this->faceService->enroll(
            user:        Auth::user(),
            photoBase64: $request->validated('photo'),
            consentIp:   $request->ip(),
        );

        return back()->with('success', 'Face enrollment submitted. Awaiting HR approval.')->with('enrollment', $enrollment);
    }

    public function myStatus()
    {
        $enrollment = FaceEnrollment::where('user_id', Auth::id())
            ->where('is_active', true)
            ->latest()
            ->first();

        return response()->json(['enrollment' => $this->withPhotoUrl($enrollment)]);
    }

    // ─── HR: Review Queue ─────────────────────────────────────────────────────

    public function index()
    {
        return Inertia::render('HumanResource/OnlinePunch/Enrollment/Manage');
    }

    public function data(Request $request)
    {
        $query = FaceEnrollment::with('user:id,name,position')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $paginated = $query->paginate(30);
        $paginated->getCollection()->transform(fn ($e) => $this->withPhotoUrl($e));

        return response()->json($paginated);
    }

    public function approve(FaceEnrollment $enrollment)
    {
        $this->faceService->approve($enrollment, Auth::user());

        return back()->with('success', 'Face enrollment approved.');
    }

    public function reject(Request $request, FaceEnrollment $enrollment)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->faceService->reject($enrollment, Auth::user(), $request->input('reason'));

        return back()->with('success', 'Face enrollment rejected.');
    }

    // ─── Image Proxy ──────────────────────────────────────────────────────────

    public function photo(string $fileId)
    {
        if (! preg_match('/^[a-zA-Z0-9_.=-]+$/', $fileId)) {
            abort(400);
        }

        $s3Key = $this->faceService->decodeS3Key($fileId);
        if (! $s3Key || ! Storage::disk('s3')->exists($s3Key)) {
            abort(404);
        }

        $contents = Storage::disk('s3')->get($s3Key);
        if (! $contents) {
            abort(404);
        }

        return response($contents, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function withPhotoUrl(?FaceEnrollment $enrollment): ?array
    {
        if (! $enrollment) {
            return null;
        }

        $data = $enrollment->toArray();
        $data['photo_url'] = route('hr.face-enrollment.photo', [
            'fileId' => $this->faceService->encodeS3Key($enrollment->s3_key),
        ]);

        return $data;
    }
}
