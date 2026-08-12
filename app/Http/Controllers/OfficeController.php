<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Administrator']);
    }

    public function index()
    {
        // Eager-load division and unit head user to avoid N+1 queries.
        $offices = Office::with(['division', 'unitHeadUser'])
            ->select('id', 'name', 'division_id', 'unit_head', 'qr_survey_token', 'qr_survey_enabled')
            ->orderBy('name')
            ->get()
            ->map(function (Office $office) {
                $office->survey_url = $office->surveyUrl();
                return $office;
            });
        $divisions = \App\Models\Division::where('status', 'active')->orderBy('division_name')->get();
        $users = User::employees()->select('id', 'name')->orderBy('name')->get();
        return inertia('DataManagement/Offices/Index', compact('offices', 'divisions', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191|unique:offices,name',
            'description' => 'nullable|string',
            'division_id' => 'required|exists:divisions,id',
            'unit_head' => 'nullable|exists:users,id',
        ]);

        $office = Office::create($data);

        return redirect()->back()->with('success', 'Office created.');
    }

    public function update(Request $request, Office $office)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191|unique:offices,name,' . $office->id,
            'description' => 'nullable|string',
            'division_id' => 'required|exists:divisions,id',
            'unit_head' => 'nullable|exists:users,id',
        ]);

        $office->update($data);

        return redirect()->back()->with('success', 'Office updated.');
    }

    public function destroy(Office $office)
    {
        $office->delete();
        return redirect()->back()->with('success', 'Office deleted.');
    }

    /**
     * Download a premium printable PDF containing the office's QR survey code.
     */
    public function qrSurveyPdf(Office $office)
    {
        $path = app(\App\Services\OfficeQrPdfService::class)->generate($office);

        return response()->download($path, "QR-Survey-{$office->name}.pdf")->deleteFileAfterSend(true);
    }

    /**
     * Inline SVG preview of the office's QR survey code (for the admin modal).
     */
    public function qrSurveyPreview(Office $office)
    {
        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(240)->margin(1)->generate($office->surveyUrl());

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Regenerate the office's QR token (invalidates the old QR code/printout).
     */
    public function regenerateQrSurveyToken(Office $office)
    {
        $office->update(['qr_survey_token' => Office::generateUniqueToken()]);

        return redirect()->back()->with('success', 'QR survey code regenerated. Reprint and replace the old QR code.');
    }

    /**
     * Enable/disable the office's public QR survey without deleting the token.
     */
    public function toggleQrSurvey(Office $office)
    {
        $office->update(['qr_survey_enabled' => ! $office->qr_survey_enabled]);

        return redirect()->back()->with('success', $office->qr_survey_enabled ? 'QR survey enabled.' : 'QR survey disabled.');
    }
}
