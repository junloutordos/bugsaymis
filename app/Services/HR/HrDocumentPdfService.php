<?php

namespace App\Services\HR;

use App\Models\DigitalSignature;
use App\Models\HR\HrDocumentRequest;
use App\Models\HR\HrIssuedDocument;
use App\Services\DigitalSignatureService;
use App\Services\PersonNameFormatter;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class HrDocumentPdfService
{
    public function __construct(
        private DigitalSignatureService $signatures,
        private PersonNameFormatter $names,
    ) {}

    public function generate(HrDocumentRequest $request, HrIssuedDocument $issued): string
    {
        $request->loadMissing(['type', 'requester', 'processor']);
        $stage = $request->type->requires_ocd_approval ? 'ocd_approval' : 'hr_issuance';
        $signature = DigitalSignature::with('signer')
            ->where('signable_type', HrDocumentRequest::class)
            ->where('signable_id', $request->id)
            ->where('metadata->stage', $stage)
            ->latest()->first();

        $signatureUri = $signature
            ? $this->signatures->getImageDataUriFromPath($signature->metadata['signature_path'] ?? null)
            : null;
        $signer = $signature?->signer;
        $signerName = $signer ? $this->names->formal($signer) : 'AUTHORIZED OFFICIAL';

        $html = view('hr.document-requests.pdf', compact(
            'request', 'issued', 'signature', 'signatureUri', 'signer', 'signerName',
        ))->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 42,
            'margin_bottom' => 24,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir(),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'],
            'fontDir' => (new ConfigVariables)->getDefaults()['fontDir'],
        ]);
        $header = public_path('images/report_header.jpeg');
        $footer = public_path('images/report_footer.jpeg');
        $mpdf->SetTitle($request->type->name.' — '.$issued->control_number);
        $mpdf->SetHTMLHeader('<img src="'.$header.'" style="width:100%;display:block;">');
        $mpdf->SetHTMLFooter('<img src="'.$footer.'" style="width:100%;display:block;">');
        $mpdf->WriteHTML($html);

        $tmpSvg = sys_get_temp_dir().'/hrdoc_qr_'.$issued->id.'_'.time().'.svg';
        file_put_contents($tmpSvg, QrCode::format('svg')->size(90)->margin(1)->generate(route('hr.document-requests.verify', $issued->qr_token)));
        try {
            $mpdf->Image($tmpSvg, 20, 248, 21, 21);
            $mpdf->SetFont('helvetica', '', 5);
            $mpdf->SetXY(17, 269.5);
            $mpdf->Cell(27, 2.5, 'Scan to verify', 0, 0, 'C');
        } finally {
            @unlink($tmpSvg);
        }

        $content = $mpdf->Output('', 'S');
        $path = "hr/document-requests/{$request->id}/issued/v{$issued->version}.pdf";
        Storage::disk('s3')->put($path, $content, ['ContentType' => 'application/pdf']);

        return $path;
    }
}
