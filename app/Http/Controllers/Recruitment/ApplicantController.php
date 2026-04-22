<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreApplicantRequest;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        $query = Applicant::withCount('applications')->latest();

        if ($search = $request->input('search')) {
            $query->where(fn ($q) =>
                $q->where('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            );
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $applicants = $query->paginate(20)->withQueryString();

        return Inertia::render('Recruitment/Applicants/Index', [
            'applicants' => $applicants,
            'filters'    => $request->only(['search', 'status']),
        ]);
    }

    public function show(Applicant $applicant)
    {
        $this->authorize('recruitment.view');

        $applicant->load([
            'documents',
            'applications.jobVacancy.jobItem.recruitmentType',
            'applications.rankingSummary',
            'applications.selection',
            'applications.placement',
        ]);

        return Inertia::render('Recruitment/Applicants/Show', [
            'applicant' => $applicant,
        ]);
    }

    public function store(StoreApplicantRequest $request)
    {
        $applicant = Applicant::create($request->validated());

        return back()->with('success', "Applicant {$applicant->full_name} added.");
    }

    public function update(StoreApplicantRequest $request, Applicant $applicant)
    {
        $this->authorize('recruitment.manage');

        $applicant->update($request->validated());

        return back()->with('success', 'Applicant record updated.');
    }

    public function destroy(Applicant $applicant)
    {
        $this->authorize('recruitment.manage');

        if ($applicant->applications()->active()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete an applicant with active applications.']);
        }

        $applicant->delete();

        return back()->with('success', 'Applicant removed.');
    }

    // ── Document Management ────────────────────────────────────────────────────

    public function uploadDocument(Request $request, Applicant $applicant)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'file'          => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = $request->file('file')->store(
            "recruitment/applicants/{$applicant->id}/documents",
            'local'
        );

        ApplicantDocument::create([
            'applicant_id'  => $applicant->id,
            'document_type' => $validated['document_type'],
            'file_path'     => $path,
            'status'        => 'pending',
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function verifyDocument(Request $request, Applicant $applicant, ApplicantDocument $document)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'status'  => ['required', 'in:verified,rejected'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $document->update($validated);

        return back()->with('success', 'Document status updated.');
    }

    public function destroyDocument(Applicant $applicant, ApplicantDocument $document)
    {
        $this->authorize('recruitment.manage');

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
