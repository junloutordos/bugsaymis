<?php

namespace App\Services;

use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class IssuanceService
{
    // ── Control number generation ─────────────────────────────────────────────

    public function nextControlNumber(string $type, int $year, int $month): array
    {
        $offset = DB::table('issuance_series_settings')
            ->where('type_code', $type)
            ->where('year', $year)
            ->value('series_start') ?? 0;

        $max = DB::table('issuances')
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->max('series_no') ?? 0;

        $seriesNo = max($offset, $max) + 1;

        $padding = DB::table('issuance_types')
            ->where('code', $type)
            ->value('series_padding') ?? 3;

        $controlNumber = strtoupper($type) . '-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($seriesNo, $padding, '0', STR_PAD_LEFT);

        return [$controlNumber, $seriesNo];
    }

    // ── Content hash (tamper detection) ──────────────────────────────────────

    public function computeHash(Issuance $issuance): string
    {
        $payload = $issuance->content
            ?? ($issuance->attachment_path . '|' . $issuance->attachment_filename);

        return hash('sha256', implode('|', [
            $issuance->control_number,
            $payload,
            $issuance->title,
        ]));
    }

    // ── Recipient fan-out ─────────────────────────────────────────────────────

    public function buildRecipients(Issuance $issuance, array $data): void
    {
        $issuance->recipients()->delete();

        if ($issuance->recipient_type === 'all') {
            $users = User::employees()->where('status', '<>', 'inactive')->pluck('id');
            $rows  = $users->map(fn($uid) => [
                'issuance_id' => $issuance->id,
                'user_id'     => $uid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();
            IssuanceRecipient::insert($rows);

        } elseif ($issuance->recipient_type === 'office') {
            $officeIds = $data['office_ids'] ?? [];
            $users = User::employees()
                ->whereIn('office_id', $officeIds)
                ->where('status', '<>', 'inactive')
                ->pluck('id');
            $rows  = $users->map(fn($uid) => [
                'issuance_id' => $issuance->id,
                'user_id'     => $uid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();
            if (! empty($rows)) IssuanceRecipient::insert($rows);

        } elseif ($issuance->recipient_type === 'individual') {
            $userIds = $data['user_ids'] ?? [];
            $userIds = User::employees()->whereIn('id', $userIds)->pluck('id');
            $rows    = $userIds->map(fn($uid) => [
                'issuance_id' => $issuance->id,
                'user_id'     => $uid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();
            if (! empty($rows)) IssuanceRecipient::insert($rows);

        } elseif ($issuance->recipient_type === 'division') {
            $divisionIds = $data['division_ids'] ?? [];
            $users = User::employees()
                ->whereIn('division_id', $divisionIds)
                ->where('status', '<>', 'inactive')
                ->pluck('id');
            $rows  = $users->map(fn($uid) => [
                'issuance_id' => $issuance->id,
                'user_id'     => $uid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();
            if (! empty($rows)) IssuanceRecipient::insert($rows);
        }
    }

    // ── QR helpers ────────────────────────────────────────────────────────────

    public function generateQrSvg(Issuance $issuance, int $size = 120): string
    {
        $url = route('issuances.verify', $issuance->qr_token);
        return QrCode::format('svg')->size($size)->margin(1)->generate($url);
    }

    // Stamp QR onto an mPDF instance using the native Image() API.
    // position:fixed/absolute with inline SVG is silently dropped by mPDF;
    // Image() with a temp SVG file is the only reliable path.
    // qrX/qrY are page-absolute (from page top-left corner, ignoring margins).
    // marginLeft must match the Mpdf margin_left so SetXY text labels align correctly
    // (SetX in mPDF is relative to the left margin, SetY is page-absolute).
    private function stampQrNative(Mpdf $mpdf, Issuance $issuance, float $qrX, float $qrY, float $marginLeft = 0): void
    {
        $tmpSvg = sys_get_temp_dir() . '/qr_' . $issuance->id . '_' . time() . '.svg';
        file_put_contents($tmpSvg, $this->generateQrSvg($issuance, 88));
        try {
            $mpdf->Image($tmpSvg, $qrX, $qrY, 22, 22);
            $textX = $qrX - $marginLeft - 2;
            $textY = $qrY + 22.5;
            $mpdf->SetFont('helvetica', '', 5);
            $mpdf->SetTextColor(100, 116, 139);
            $mpdf->SetXY($textX, $textY);
            $mpdf->Cell(30, 2.5, 'Scan to verify', 0, 1, 'C');
            $hash = substr($issuance->content_hash ?? '', 0, 16);
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

    // ── PDF generation — public entry point ──────────────────────────────────

    public function generatePdf(Issuance $issuance): string
    {
        ini_set('memory_limit', '256M');

        $mode = $issuance->attachment_path ? 'scan' : 'editor';
        logger()->info('Issuance generatePdf start', [
            'id'              => $issuance->id,
            'control_number'  => $issuance->control_number,
            'mode'            => $mode,
            'attachment_path' => $issuance->attachment_path,
            'attachment_mime' => $issuance->attachment_mime,
        ]);

        $pdfContent = $mode === 'scan'
            ? $this->stampQrOnScan($issuance)
            : $this->generateContentPdf($issuance);

        $path = 'issuances/' . $issuance->control_number . '.pdf';
        Storage::disk('s3')->put($path, $pdfContent);

        logger()->info('Issuance generatePdf done', [
            'id'    => $issuance->id,
            'mode'  => $mode,
            'path'  => $path,
            'bytes' => strlen($pdfContent),
        ]);

        return $path;
    }

    // ── Mode A: typed content → mPDF with QR fixed lower-right ───────────────

    private function generateContentPdf(Issuance $issuance): string
    {
        $sig     = $issuance->signature;
        $sigUri  = $sig?->signer ? app(DigitalSignatureService::class)->getSignatureDataUri($sig->signer) : null;
        $ocdUser = $sig?->signer ?? $issuance->creator;

        $html = view('issuances.pdf', compact('issuance', 'sig', 'sigUri', 'ocdUser'))->render();

        $headerImg = public_path('images/report_header.jpeg');
        $footerImg = public_path('images/report_footer.jpeg');

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 42,
            'margin_bottom' => 22,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir'       => sys_get_temp_dir(),
            'fontdata'      => (new FontVariables())->getDefaults()['fontdata'],
            'fontDir'       => (new ConfigVariables())->getDefaults()['fontDir'],
        ]);

        $mpdf->SetTitle($issuance->type_label . ' — ' . $issuance->control_number);
        $mpdf->SetHTMLHeader('<div style="margin:0;padding:0;"><img src="' . $headerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->SetHTMLFooter('<div style="margin:0;padding:0;"><img src="' . $footerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->WriteHTML($html);

        // QR at page-absolute bottom-right: x=179 (210-9-22), y=247 (297-22margin-22qr-6text)
        $this->stampQrNative($mpdf, $issuance, 179, 247, 15);

        return $mpdf->Output('', 'S');
    }

    // ── Mode B: scanned upload → stamp QR onto original document ─────────────

    private function stampQrOnScan(Issuance $issuance): string
    {
        $scanContent = Storage::disk('s3')->get($issuance->attachment_path);
        $mime        = $issuance->attachment_mime ?? 'application/pdf';

        if (str_contains($mime, 'pdf')) {
            $result = $this->stampQrOnPdf($scanContent, $issuance);
        } else {
            $result = $this->stampQrOnImage($scanContent, $mime, $issuance);
        }

        return $result;
    }

    // Stamp QR onto each page of a PDF scan using TCPDF + FPDI.
    // Uses TCPDF's native write2DBarcode() — no SVG, no Imagick, no external image.
    // ImageSVG() was previously used but silently failed to render the path-based SVG
    // generated by simplesoftwareio/simple-qrcode v4 (fill-rule=evenodd + nested transforms).
    private function stampQrOnPdf(string $pdfContent, Issuance $issuance): string
    {
        $tmpPdf = sys_get_temp_dir() . '/issuance_scan_' . $issuance->id . '.pdf';
        file_put_contents($tmpPdf, $pdfContent);

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Atlas');
            $pdf->SetTitle($issuance->type_label . ' — ' . $issuance->control_number);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);

            $pageCount = $pdf->setSourceFile($tmpPdf);
            logger()->info('Issuance stampQrOnPdf', ['id' => $issuance->id, 'pages' => $pageCount]);

            $qrSize  = 22;
            $hash    = substr($issuance->content_hash ?? '', 0, 16);
            $qrUrl   = route('issuances.verify', $issuance->qr_token);
            $qrStyle = ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]];

            for ($p = 1; $p <= $pageCount; $p++) {
                $tplId  = $pdf->importPage($p);
                $size   = $pdf->getTemplateSize($tplId);
                $w      = $size['width'];
                $h      = $size['height'];
                $orient = $size['orientation'] ?? ($w > $h ? 'L' : 'P');

                $pdf->AddPage($orient, [$w, $h]);
                $pdf->useTemplate($tplId);

                $qrX = $w - $qrSize - 9;
                $qrY = $h - $qrSize - 9 - 8;

                // Native TCPDF QR — renders as PDF vector commands, no image library required
                $pdf->write2DBarcode($qrUrl, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle);

                // Labels under QR
                $pdf->SetFont('helvetica', '', 4.5);
                $pdf->SetTextColor(148, 163, 184);
                $pdf->SetXY($qrX - 2, $qrY + $qrSize + 0.5);
                $pdf->Cell($qrSize + 4, 3, 'Scan to verify', 0, 1, 'C');
                $pdf->SetX($qrX - 2);
                $pdf->Cell($qrSize + 4, 2.5, '- - - - - - -', 0, 1, 'C');
                if ($hash) {
                    $pdf->SetFont('courier', '', 4);
                    $pdf->SetX($qrX - 2);
                    $pdf->Cell($qrSize + 4, 2.5, $hash . chr(133), 0, 0, 'C');
                }
            }

            return $pdf->Output('', 'S');
        } finally {
            @unlink($tmpPdf);
        }
    }

    // Stamp QR onto an image scan (JPG/PNG)
    private function stampQrOnImage(string $imgContent, string $mime, Issuance $issuance): string
    {
        $ext    = str_contains($mime, 'png') ? 'png' : 'jpg';
        $tmpImg = sys_get_temp_dir() . '/issuance_scan_' . $issuance->id . '.' . $ext;
        file_put_contents($tmpImg, $imgContent);

        try {
            $mpdf = new Mpdf([
                'format'        => 'A4',
                'margin_left'   => 0, 'margin_right'  => 0,
                'margin_top'    => 0, 'margin_bottom' => 0,
                'margin_header' => 0, 'margin_footer' => 0,
                'tempDir'       => sys_get_temp_dir(),
                'fontdata'      => (new FontVariables())->getDefaults()['fontdata'],
                'fontDir'       => (new ConfigVariables())->getDefaults()['fontDir'],
            ]);

            $mpdf->WriteHTML('<img src="' . $tmpImg . '" style="width:210mm;display:block;" />');

            // QR at page-absolute bottom-right (0 margins): x=179 (210-9-22), y=258 (297-9-22-8text)
            $this->stampQrNative($mpdf, $issuance, 179, 258, 0);

            return $mpdf->Output('', 'S');
        } finally {
            @unlink($tmpImg);
        }
    }
}
