<?php

namespace App\Http\Controllers;

use App\Models\ICTEquipment;
use App\Models\IctEquipmentAssignmentHistory;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentEnrollmentToken;
use App\Models\IctEquipmentHealthHistory;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class ICTEquipmentController extends Controller
{
    public function index(Request $request)
    {
        $search   = $request->input('search');
        $category = $request->input('category');
        $status   = $request->input('status');
        $perPage  = min((int) $request->query('per_page', 15), 1000);

        $query = ICTEquipment::with([
            'owner',
            'room',
            'agentDevice.healthSnapshot',
            'alerts' => function ($q) {
                $q->where('status', 'open')->orderBy('created_at', 'desc');
            },
            'pmsHistory' => function ($q) {
                $q->orderBy('pms_date', 'desc');
            }
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('property_no', 'like', "%{$search}%")
                  ->orWhereHas('owner', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('room', fn($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $equipments = $query->paginate($perPage)->withQueryString();
        $users = User::all();
        $rooms = Room::orderBy('name')->get();

        return Inertia::render('ITJobRequests/ICTEquipments', [
            'equipments' => $equipments,
            'users'      => $users,
            'rooms'      => $rooms,
            'filters'    => $request->only('search', 'category', 'status', 'per_page'),
        ]);
    }

    /**
     * GET /ict-agent/health-dashboard
     *
     * Campus-wide fleet view, sorted highest-risk-first by default. Trend
     * data per device is intentionally NOT eager-loaded here — it's fetched
     * on demand via healthHistory() when a row is expanded, since loading
     * full history for every device upfront wouldn't scale with fleet size.
     */
    public function healthDashboard(Request $request)
    {
        $sort = $request->input('sort', 'risk_score');
        $direction = $request->input('direction', 'desc');

        $devices = IctEquipmentDevice::with(['equipment.owner', 'equipment.room'])
            ->withCount(['alerts as open_alerts_count' => fn ($q) => $q->where('status', 'open')])
            ->whereNotNull('equipment_id')
            ->orderBy(in_array($sort, ['risk_score', 'health_score', 'last_checkin_at']) ? $sort : 'risk_score', $direction === 'asc' ? 'asc' : 'desc')
            ->get();

        return Inertia::render('ITJobRequests/IctAgentHealthDashboard', [
            'devices' => $devices,
            'sort'    => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * GET /ict-agent/health-dashboard/{device}/history
     *
     * Last 30 days of scored checkins for one device's trend chart.
     */
    public function healthHistory(IctEquipmentDevice $device)
    {
        $history = IctEquipmentHealthHistory::where('device_id', $device->id)
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get(['health_score', 'risk_score', 'recorded_at', 'payload']);

        return response()->json(['history' => $history]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:255',
            'owner_id' => 'required|exists:users,id',
            'property_no' => 'nullable|string|max:255',
            'serial_no' => 'required|string|max:255',
            'description' => 'required|string',
            'date_acquired' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'status' => 'required|string|max:255',

            // ✅ NEW
            'room_id' => 'required|exists:rooms,id',

            'remarks' => 'nullable|string',
            'warranty_expires_at' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:255',
            'decommissioned_at' => 'nullable|date',
        ]);

        $equipment = ICTEquipment::create($data);

        IctEquipmentAssignmentHistory::create([
            'equipment_id' => $equipment->id,
            'user_id' => $equipment->owner_id,
            'assigned_at' => now(),
        ]);

        // QR Code (unchanged)
        $url = url('/equipment/' . $equipment->id);
        $fileName = 'qrcodes/equipment_' . $equipment->id . '.svg';

        $qrSvg = QrCode::size(200)->generate($url);
        Storage::disk('public')->put($fileName, $qrSvg);

        $equipment->qr_code_path = $fileName;
        $equipment->save();

        return redirect()->back()->with('success', 'Equipment added successfully.');
    }

    public function update(Request $request, ICTEquipment $ictEquipment)
{
    $data = $request->validate([
        'category' => 'required|string|max:255',
        'owner_id' => 'required|exists:users,id',
        'property_no' => 'nullable|string|max:255',
        'serial_no' => 'required|string|max:255',
        'description' => 'required|string',
        'date_acquired' => 'nullable|date',
        'amount' => 'nullable|numeric',
        'status' => 'required|string|max:255',
        'room_id' => 'required|exists:rooms,id',
        'remarks' => 'nullable|string',
        'warranty_expires_at' => 'nullable|date',
        'warranty_provider' => 'nullable|string|max:255',
        'decommissioned_at' => 'nullable|date',
    ]);

    $previousOwnerId = $ictEquipment->owner_id;

    // Update equipment data
    $ictEquipment->update($data);

    if ((int) $data['owner_id'] !== (int) $previousOwnerId) {
        IctEquipmentAssignmentHistory::where('equipment_id', $ictEquipment->id)
            ->where('user_id', $previousOwnerId)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        IctEquipmentAssignmentHistory::create([
            'equipment_id' => $ictEquipment->id,
            'user_id' => $data['owner_id'],
            'assigned_at' => now(),
        ]);
    }

    /**
     * 🔁 REGENERATE QR CODE
     */
    // Delete old QR if exists
    if ($ictEquipment->qr_code_path) {
        $oldPath = ltrim(str_replace('storage/', '', $ictEquipment->qr_code_path), '/');
        Storage::disk('public')->delete($oldPath);
    }

    // Generate new QR
    $url = url('/equipment/' . $ictEquipment->id);
    $fileName = 'qrcodes/equipment_' . $ictEquipment->id . '.svg';

    $qrSvg = QrCode::size(200)->generate($url);
    Storage::disk('public')->put($fileName, $qrSvg);

    // Save new QR path (no storage/ prefix — storageUrl() handles the base)
    $ictEquipment->update([
        'qr_code_path' => $fileName,
    ]);

    return redirect()->back()->with('success', 'Equipment updated and QR code regenerated.');
}

    public function destroy(ICTEquipment $ictEquipment)
    {
        if ($ictEquipment->qr_code_path) {
            $path = ltrim(str_replace('storage/', '', $ictEquipment->qr_code_path), '/');
            Storage::disk('public')->delete($path);
        }

        $ictEquipment->delete();

        return redirect()->back()->with('success', 'Equipment deleted successfully.');
    }

    /**
     * Generate and stream a QR code SVG for the given equipment on demand.
     * No S3 involved — generated fresh every request. Bypasses all S3 ACL issues.
     */
    public function qrCode(ICTEquipment $ictEquipment): \Illuminate\Http\Response
    {
        $url = url('/equipment/' . $ictEquipment->id);
        $svg = QrCode::size(200)->generate($url);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    // ✅ Public view when QR code is scanned
    public function publicShow(ICTEquipment $ictEquipment)
    {
        $ictEquipment->load([
            'owner',
            'room', // ✅ LOAD ROOM RELATIONSHIP
        ]);

        return Inertia::render('ITJobRequests/EquipmentPublicView', [
            'equipment' => $ictEquipment,
        ]);
    }

    public function generateReport(Request $request)
    {
        $request->validate([
            'groupBy' => 'required|in:category,location',
        ]);

        $groupBy = $request->input('groupBy');
        $equipments = ICTEquipment::with(['owner', 'room'])->get();
        $groupedData = [];

        if ($groupBy === 'category') {
            foreach ($equipments as $eq) {
                $category = $eq->category ?? 'Uncategorized';
                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }
                $groupedData[$category][] = $eq;
            }
        } else {
            foreach ($equipments as $eq) {
                $roomName = $eq->room?->name ?? 'No Location';
                if (!isset($groupedData[$roomName])) {
                    $groupedData[$roomName] = [];
                }
                $groupedData[$roomName][] = $eq;
            }
        }

        // Sort groups alphabetically
        ksort($groupedData);

        $reportTitle = $groupBy === 'category' 
            ? 'LIST OF ICT EQUIPMENT REPORT BY CATEGORY' 
            : 'LIST OF ICT EQUIPMENT REPORT BY LOCATION';

        $html = view('reports.ict-equipment-report', [
            'groupedData' => $groupedData,
            'reportTitle' => $reportTitle,
            'groupBy' => $groupBy,
            'generatedDate' => now()->format('F d, Y'),
        ])->render();

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_footer' => 10,
                'default_font' => 'Arial',
            ]);

            $mpdf->WriteHTML($html);
            
            $filename = 'ict-equipment-report-' . date('Y-m-d-His') . '.pdf';
            
            // Get the PDF content as binary string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Return as a download response
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            logger()->error('PDF generation error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate a one-time enrollment token an MIS staff member hands to the
     * ICT agent installer. The agent exchanges it for a device token on
     * first enroll; it expires after 24h whether used or not.
     */
    public function generateEnrollmentToken(Request $request)
    {
        $token = IctEquipmentEnrollmentToken::create([
            'token' => Str::random(40),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'token' => $token->token,
            'expires_at' => $token->expires_at,
        ]);
    }
}
