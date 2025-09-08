<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ICTEquipment;
use League\Csv\Reader;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ImportICTEquipments extends Command
{
    protected $signature = 'import:ict-equipments';
    protected $description = 'Import old ICT Equipments from CSV and generate QR codes';

    public function handle()
    {
        $filePath = storage_path('app/imports/old_ict_equipments.csv');

        if (!file_exists($filePath)) {
            $this->error("CSV file not found: $filePath");
            return Command::FAILURE;
        }

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(null); // ✅ No header, treat rows as indexed

        $records = $csv->getRecords();
        $count = 0;

        foreach ($records as $row) {
            $equipment = ICTEquipment::create([
                'property_no'   => $row[0] ?? null,
                'device_type'   => $row[1] ?? null,
                'description'   => $row[2] ?? null,
                'location'      => $row[3] ?? null,
                'date_entry'    => $this->parseDate($row[4] ?? null),
                'unit'          => $row[5] ?? null,
                'owner'         => $row[6] ?? null,
                'status'        => $row[7] ?? null,
                'remarks'       => $row[8] ?? null,
                'encodedby'     => $row[9] ?? null,
                'amount'        => is_numeric($row[10] ?? null) ? $row[10] : null,
                'date_acquired' => $this->parseDate($row[11] ?? null),
                'serialno'      => $row[12] ?? null,
                'category'      => $row[13] ?? null,
            ]);

            // ✅ Generate QR Code
            $fileName = 'qrcodes/equipment_' . $equipment->id . '.png';
            $qrImage = QrCode::format('png')->size(200)->generate($equipment->id);

            Storage::disk('public')->put($fileName, $qrImage);

            $equipment->qr_code_path = 'storage/' . $fileName;
            $equipment->save();

            $count++;
        }

        $this->info("Imported $count ICT Equipment records successfully!");
        return Command::SUCCESS;
    }

    /**
     * Safely parse a date value.
     * Returns null if not a valid date.
     */
    private function parseDate($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; // Ignore invalid dates
        }
    }
}
