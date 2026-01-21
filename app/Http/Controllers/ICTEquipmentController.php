<?php

namespace App\Http\Controllers;

use App\Models\ICTEquipment;
use App\Models\User;
use App\Models\Room; // ✅ ADDED
use Illuminate\Http\Request;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class ICTEquipmentController extends Controller
{
    public function index()
    {
        $equipments = ICTEquipment::with([
            'owner',
            'room', // ✅ ADDED
            'pmsHistory' => function ($q) {
                $q->orderBy('pms_date', 'desc');
            }
        ])->get();

        $users = User::all();
        $rooms = Room::orderBy('name')->get(); // ✅ ADDED

        return Inertia::render('ITJobRequests/ICTEquipments', [
            'equipments' => $equipments,
            'users' => $users,
            'rooms' => $rooms, // ✅ ADDED
        ]);
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
        ]);

        $equipment = ICTEquipment::create($data);

        // QR Code (unchanged)
        $url = url('/equipment/' . $equipment->id);
        $fileName = 'qrcodes/equipment_' . $equipment->id . '.svg';

        $qrSvg = QrCode::size(200)->generate($url);
        Storage::disk('public')->put($fileName, $qrSvg);

        $equipment->qr_code_path = 'storage/' . $fileName;
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
    ]);

    // Update equipment data
    $ictEquipment->update($data);

    /**
     * 🔁 REGENERATE QR CODE
     */
    // Delete old QR if exists
    if ($ictEquipment->qr_code_path) {
        $oldPath = str_replace('storage/', '', $ictEquipment->qr_code_path);
        Storage::disk('public')->delete($oldPath);
    }

    // Generate new QR
    $url = url('/equipment/' . $ictEquipment->id);
    $fileName = 'qrcodes/equipment_' . $ictEquipment->id . '.svg';

    $qrSvg = QrCode::size(200)->generate($url);
    Storage::disk('public')->put($fileName, $qrSvg);

    // Save new QR path
    $ictEquipment->update([
        'qr_code_path' => 'storage/' . $fileName,
    ]);

    return redirect()->back()->with('success', 'Equipment updated and QR code regenerated.');
}

    public function destroy(ICTEquipment $ictEquipment)
    {
        if ($ictEquipment->qr_code_path) {
            $path = str_replace('storage/', '', $ictEquipment->qr_code_path);
            Storage::disk('public')->delete($path);
        }

        $ictEquipment->delete();

        return redirect()->back()->with('success', 'Equipment deleted successfully.');
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
}
