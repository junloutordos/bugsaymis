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

    public function nextControlNumber(string $type, int $year): array
    {
        $max = DB::table('issuances')
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->max('series_no') ?? 0;

        $seriesNo      = $max + 1;
        $controlNumber = strtoupper($type) . '-' . $year . '-' . str_pad($seriesNo, 3, '0', STR_PAD_LEFT);

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
            $users = User::where('status', '<>', 'inactive')->pluck('id');
            $rows  = $users->map(fn($uid) => [
                'issuance_id' => $issuance->id,
                'user_id'     => $uid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();
            IssuanceRecipient::insert($rows);

        } elseif ($issuance->recipient_type === 'office') {
            $officeIds = $data['office_ids'] ?? [];
            $users = User::whereIn('office_id', $officeIds)
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
            $rows    = collect($userIds)->map(fn($uid) => [
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

    private function qrOverlayHtml(Issuance $issuance, float $qrX, float $qrY): string
    {
        $svgB64 = base64_encode($this->generateQrSvg($issuance, 90));
        $hash   = substr($issuance->content_hash ?? '', 0, 16);
        return '<div style="position:fixed;left:' . $qrX . 'mm;top:' . $qrY . 'mm;width:26mm;text-align:center;">'
            . '<img src="data:image/svg+xml;base64,' . $svgB64 . '" style="width:22mm;height:22mm;display:block;margin:0 auto;" />'
            . '<div style="font-size:4.5pt;color:#94a3b8;margin-top:0.5mm;">Scan to verify</div>'
            . '<div style="font-size:4pt;color:#94a3b8;">- - - - - - -</div>'
            . ($hash ? '<div style="font-size:4pt;color:#94a3b8;font-family:Courier,monospace;">' . $hash . chr(133) . '</div>' : '')
            . '</div>';
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
        $qrB64   = base64_encode($this->generateQrSvg($issuance, 80));

        $html = view('issuances.pdf', compact('issuance', 'sig', 'sigUri', 'ocdUser', 'qrB64'))->render();

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
    // TCPDF is the standard PHP PDF stamping engine — proven reliable for this use case.
    private function stampQrOnPdf(string $pdfContent, Issuance $issuance): string
    {
        $tmpPdf = sys_get_temp_dir() . '/issuance_scan_' . $issuance->id . '.pdf';
        $tmpQr  = sys_get_temp_dir() . '/issuance_qr_' . $issuance->id . '.svg';
        file_put_contents($tmpPdf, $pdfContent);
        file_put_contents($tmpQr, $this->generateQrSvg($issuance, 120));

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('CRCMIS');
            $pdf->SetTitle($issuance->type_label . ' — ' . $issuance->control_number);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);

            $pageCount = $pdf->setSourceFile($tmpPdf);
            logger()->info('Issuance stampQrOnPdf', ['id' => $issuance->id, 'pages' => $pageCount]);

            $qrSize = 22;
            $hash   = substr($issuance->content_hash ?? '', 0, 16);

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

                // Place SVG QR at absolute coordinates on top of imported page
                $pdf->ImageSVG($tmpQr, $qrX, $qrY, $qrSize, $qrSize, '', '', '', 0, false);

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
            @unlink($tmpQr);
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

            $html = '<img src="' . $tmpImg . '" style="width:210mm;display:block;" />'
                  . $this->qrOverlayHtml($issuance, 210 - 35, 297 - 39);

            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } finally {
            @unlink($tmpImg);
        }
    }
}
