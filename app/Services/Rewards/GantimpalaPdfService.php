<?php

namespace App\Services\Rewards;

use App\Models\GantimpalaNomination;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GantimpalaPdfService
{
    public function generate(GantimpalaNomination $nomination): string
    {
        $nominatorSignatureUri = $this->imageDataUri($nomination->nominator_signature_path);
        $supervisorSignatureUri = $this->imageDataUri($nomination->supervisor_signature_path);

        $html = view('rewards.gantimpala.pdf', [
            'nomination'             => $nomination,
            'nominatorSignatureUri'  => $nominatorSignatureUri,
            'supervisorSignatureUri' => $supervisorSignatureUri,
        ])->render();

        $mpdf = new Mpdf([
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'margin_left'  => 18,
            'margin_right' => 18,
            'margin_top'   => 36,
            'margin_bottom' => 22,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir'      => sys_get_temp_dir(),
            'fontdata'     => (new FontVariables)->getDefaults()['fontdata'],
            'fontDir'      => (new ConfigVariables)->getDefaults()['fontDir'],
        ]);

        $header = public_path('images/report_header.jpeg');
        if (file_exists($header)) {
            $mpdf->SetHTMLHeader('<img src="' . $header . '" style="width:100%;display:block;">');
        }

        $mpdf->SetTitle('Gantimpala Agad Award — ' . $nomination->reference_no);
        $mpdf->WriteHTML($html);

        $tmpSvg = sys_get_temp_dir() . '/gantimpala_qr_' . $nomination->id . '_' . time() . '.svg';
        file_put_contents($tmpSvg, QrCode::format('svg')->size(90)->margin(1)->generate(
            route('rewards.gantimpala.show', $nomination->id)
        ));
        try {
            $mpdf->Image($tmpSvg, 178, 260, 18, 18);
            $mpdf->SetFont('helvetica', '', 5);
            $mpdf->SetXY(175, 279);
            $mpdf->Cell(24, 2.5, 'Scan to view record', 0, 0, 'C');
        } finally {
            @unlink($tmpSvg);
        }

        $content = $mpdf->Output('', 'S');
        $path = "rewards/gantimpala/{$nomination->id}/form4_{$nomination->reference_no}.pdf";
        Storage::disk('s3')->put($path, $content, ['ContentType' => 'application/pdf']);

        $nomination->update(['pdf_path' => $path]);

        return $path;
    }

    private function imageDataUri(?string $s3Path): ?string
    {
        if (! $s3Path || ! Storage::disk('s3')->exists($s3Path)) {
            return null;
        }

        $contents = Storage::disk('s3')->get($s3Path);
        $mime = str_ends_with($s3Path, '.jpg') || str_ends_with($s3Path, '.jpeg') ? 'image/jpeg' : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
