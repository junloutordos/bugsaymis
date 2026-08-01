<?php

namespace App\Services;

use App\Models\Administration\CoaCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateOfAppearanceService
{
    private const PDF_RENDER_VERSION = 'v2';

    public function __construct(
        private DigitalSignatureService $signatureService,
        private PersonNameFormatter $nameFormatter,
    ) {}

    // ── Control number generation ─────────────────────────────────────────────

    /**
     * Next COA-YYYY-NNNN for the current year. Call inside the issuing
     * transaction — the lockForUpdate serializes concurrent issues.
     */
    public function nextControlNumber(): string
    {
        $year = now()->format('Y');

        $max = DB::table('coa_certificates')
            ->where('control_number', 'like', "COA-{$year}-%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(control_number, '-', -1) AS UNSIGNED)) as seq")
            ->value('seq');

        return sprintf('COA-%s-%04d', $year, ($max ?? 0) + 1);
    }

    // ── Content hash (tamper detection) ──────────────────────────────────────

    public function computeHash(CoaCertificate $cert): string
    {
        $visit = $cert->visit;

        return hash('sha256', implode('|', [
            $cert->control_number,
            $cert->visitor_name,
            $cert->organization ?? '',
            $cert->email,
            $visit->purpose,
            $visit->date_from?->format('Y-m-d'),
            $visit->date_to?->format('Y-m-d') ?? '',
        ]));
    }

    // ── QR helper ─────────────────────────────────────────────────────────────

    public function generateQrSvg(CoaCertificate $cert, int $size = 120): string
    {
        $url = route('coa.verify', $cert->qr_token);
        return QrCode::format('svg')->size($size)->margin(1)->generate($url);
    }

    /**
     * Preserve the exact electronic signature used for this certificate. User
     * signature uploads replace the profile image, while issued certificates
     * must remain reproducible with the image used at signing time.
     */
    public function snapshotSignature(User $signer, CoaCertificate $cert): ?string
    {
        $dataUri = $this->signatureService->getSignatureDataUri($signer);
        if (! $dataUri || ! preg_match('/^data:image\/[^;]+;base64,(.+)$/s', $dataUri, $matches)) {
            return null;
        }

        $imageData = base64_decode($matches[1], true);
        if ($imageData === false) {
            return null;
        }

        $path = "signatures/documents/coa/certificate_{$cert->id}.png";
        Storage::disk('s3')->put($path, $imageData, ['ContentType' => 'image/png']);

        return $path;
    }

    // ── PDF generation ────────────────────────────────────────────────────────

    public function pdfPath(CoaCertificate $cert): string
    {
        return 'coa/' . self::PDF_RENDER_VERSION . '/' . $cert->control_number . '.pdf';
    }

    public function generatePdf(CoaCertificate $cert): string
    {
        $cert->loadMissing([
            'visit',
            'signature.signer:id,name,position,prenominal_title,postnominal_title',
        ]);

        $signatureUri = $this->resolveSignatureUri($cert);
        $signerName = $cert->signature?->signer
            ? $this->nameFormatter->formal($cert->signature->signer)
            : 'PSHS-CRC';

        $html = view('coa.pdf', [
            'cert'         => $cert,
            'visit'        => $cert->visit,
            'signatureUri' => $signatureUri,
            'signerName'   => $signerName,
        ])->render();

        $headerImg = public_path('images/report_header.jpeg');
        $footerImg = public_path('images/report_footer.jpeg');

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_top'    => 42,
            'margin_bottom' => 22,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir'       => sys_get_temp_dir(),
            'fontdata'      => (new FontVariables())->getDefaults()['fontdata'],
            'fontDir'       => (new ConfigVariables())->getDefaults()['fontDir'],
        ]);

        $mpdf->SetTitle('Certificate of Appearance — ' . $cert->control_number);
        $mpdf->SetHTMLHeader('<div style="margin:0;padding:0;"><img src="' . $headerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->SetHTMLFooter('<div style="margin:0;padding:0;"><img src="' . $footerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->WriteHTML($html);

        // QR at page-absolute bottom-left: x=20 (left margin), y=247 (297-22margin-22qr-6text)
        $this->stampQrNative($mpdf, $cert, 20, 247, 20);

        $pdfContent = $mpdf->Output('', 'S');

        $path = $this->pdfPath($cert);
        Storage::disk('s3')->put($path, $pdfContent);

        return $path;
    }

    /**
     * Resolve the image that was associated with the signing event. Older COA
     * signatures did not snapshot the path, so retain the signer fallback for
     * certificates issued before this fix.
     */
    private function resolveSignatureUri(CoaCertificate $cert): ?string
    {
        $signature = $cert->signature;
        if (! $signature) {
            return null;
        }

        $snapshotPath = $signature->metadata['signature_path'] ?? null;
        $signatureUri = $this->signatureService->getImageDataUriFromPath($snapshotPath);

        if (! $signatureUri && $signature->signer) {
            $signatureUri = $this->signatureService->getSignatureDataUri($signature->signer);
        }

        return $signatureUri;
    }

    // Stamp QR onto the mPDF instance via the native Image() API.
    // position:fixed/absolute with inline SVG is silently dropped by mPDF;
    // Image() with a temp SVG file is the only reliable path (see IssuanceService).
    private function stampQrNative(Mpdf $mpdf, CoaCertificate $cert, float $qrX, float $qrY, float $marginLeft = 0): void
    {
        $tmpSvg = sys_get_temp_dir() . '/coa_qr_' . $cert->id . '_' . time() . '.svg';
        file_put_contents($tmpSvg, $this->generateQrSvg($cert, 88));
        try {
            $mpdf->Image($tmpSvg, $qrX, $qrY, 22, 22);
            $textX = $qrX - $marginLeft - 2;
            $textY = $qrY + 22.5;
            $mpdf->SetFont('helvetica', '', 5);
            $mpdf->SetTextColor(100, 116, 139);
            $mpdf->SetXY($textX, $textY);
            $mpdf->Cell(30, 2.5, 'Scan to verify', 0, 1, 'C');
            $hash = substr($cert->content_hash ?? '', 0, 16);
            if ($hash) {
                $mpdf->SetFont('courier', '', 4);
                $mpdf->SetTextColor(148, 163, 184);
                $mpdf->SetX($textX);
                $mpdf->Cell(30, 2, $hash . chr(133), 0, 0, 'C');
            }
        } finally {
            @unlink($tmpSvg);
        }
    }
}
