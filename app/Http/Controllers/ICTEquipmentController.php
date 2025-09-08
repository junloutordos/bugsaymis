<?php

namespace App\Http\Controllers;

use App\Models\ICTEquipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class ICTEquipmentController extends Controller
{
    public function index()
    {
        $equipments = ICTEquipment::all();

        return Inertia::render('ITJobRequests/ICTEquipments', [
            'equipments' => $equipments,
        ]);
    }

    public function store(Request $request)
    {
        $equipment = ICTEquipment::create($request->all());

        // Generate QR Code (ID-based)
        $fileName = 'qrcodes/equipment_' . $equipment->id . '.png';
        $qrImage = QrCode::format('png')->size(200)->generate($equipment->id);

        Storage::disk('public')->put($fileName, $qrImage);

        $equipment->qr_code_path = 'storage/' . $fileName;
        $equipment->save();

        return response()->json($equipment, 201);
    }

    public function show($id)
    {
        return ICTEquipment::findOrFail($id);
    }
}
