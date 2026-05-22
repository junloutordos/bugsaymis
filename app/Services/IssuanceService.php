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

    private function generateQrPng(Issuance $issuance, int $size = 90): string
    {
        $url = route('issuances.verify', $issuance->qr_token);
        return QrCode::format('png')->size($size)->margin(1)->generate($url);
    }

    // ── PDF generation — public entry point ──────────────────────────────────

    public function generatePdf(Issuance $issuance): string
    {
        ini_set('memory_limit', '256M');

        $pdfContent = $issuance->attachment_path
            ? $this->stampQrOnScan($issuance)
            : $this->generateContentPdf($issuance);

        $path = 'issuances/' . $issuance->control_number . '.pdf';
        Storage::disk('s3')->put($path, $pdfContent);

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

        // Write QR PNG to temp file for fpdi/mPDF use
        $qrPng    = $this->generateQrPng($issuance, 90);
        $tmpQr    = sys_get_temp_dir() . '/issuance_qr_' . $issuance->id . '.png';
        file_put_contents($tmpQr, $qrPng);

        try {
            if (str_contains($mime, 'pdf')) {
                $result = $this->stampQrOnPdf($scanContent, $tmpQr, $issuance);
            } else {
                $result = $this->stampQrOnImage($scanContent, $mime, $tmpQr, $issuance);
            }
        } finally {
            @unlink($tmpQr);
        }

        return $result;
    }

    // Stamp QR onto each page of a PDF scan using mPDF's built-in FpdiTrait
    private function stampQrOnPdf(string $pdfContent, string $qrPngPath, Issuance $issuance): string
    {
        $tmpPdf = sys_get_temp_dir() . '/issuance_scan_' . $issuance->id . '.pdf';
        file_put_contents($tmpPdf, $pdfContent);

        try {
            $mpdf      = new Mpdf([
                'mode'    => 'utf-8',
                'tempDir' => sys_get_temp_dir(),
                'fontdata'=> (new FontVariables())->getDefaults()['fontdata'],
                'fontDir' => (new ConfigVariables())->getDefaults()['fontDir'],
            ]);

            $pageCount = $mpdf->setSourceFile($tmpPdf);

            for ($page = 1; $page <= $pageCount; $page++) {
                $tplId = $mpdf->importPage($page);
                $size  = $mpdf->getTemplateSize($tplId);

                $w      = $size['width']  ?? 210;
                $h      = $size['height'] ?? 297;
                $orient = ($w > $h) ? 'L' : 'P';

                $mpdf->AddPage($orient, [$w, $h]);
                $mpdf->useTemplate($tplId, 0, 0, $w, $h);

                // QR stamp — 22mm × 22mm, 9mm from edges
                $qrSize = 22;
                $qrX    = $w - $qrSize - 9;
                $qrY    = $h - $qrSize - 9 - 5; // 5mm for label below

                // Write QR + label as fixed HTML overlay at absolute position
                $qrHtml = '
                    <div style="position:fixed; left:' . $qrX . 'mm; top:' . $qrY . 'mm; width:' . $qrSize . 'mm; text-align:center;">
                        <img src="' . $qrPngPath . '" style="width:' . $qrSize . 'mm; height:' . $qrSize . 'mm; display:block; margin:0 auto;" />
                        <div style="font-size:4.5pt; color:#94a3b8; margin-top:0.5mm;">Scan to verify</div>
                        <div style="font-size:4pt; color:#94a3b8; letter-spacing:0.5pt;">— — — — — — —</div>
                        <div style="font-size:4pt; color:#94a3b8; font-family:Courier,monospace;">' . substr($issuance->content_hash ?? '', 0, 16) . '…</div>
                    </div>';
                $mpdf->WriteHTML($qrHtml);
            }

            return $mpdf->Output('', 'S');
        } finally {
            @unlink($tmpPdf);
        }
    }

    // Stamp QR onto an image scan (JPG/PNG) using mPDF
    private function stampQrOnImage(string $imgContent, string $mime, string $qrPngPath, Issuance $issuance): string
    {
        $ext    = str_contains($mime, 'png') ? 'png' : 'jpg';
        $tmpImg = sys_get_temp_dir() . '/issuance_scan_' . $issuance->id . '.' . $ext;
        file_put_contents($tmpImg, $imgContent);

        try {
            $mpdf = new Mpdf([
                'format'        => 'A4',
                'margin_left'   => 0,
                'margin_right'  => 0,
                'margin_top'    => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir'       => sys_get_temp_dir(),
                'fontdata'      => (new FontVariables())->getDefaults()['fontdata'],
                'fontDir'       => (new ConfigVariables())->getDefaults()['fontDir'],
            ]);

            // Full-page image + fixed QR overlay bottom-right
            $html  = '<img src="' . $tmpImg . '" style="width:210mm; display:block;" />';
            $html .= '<div style="position:fixed; bottom:8mm; right:8mm; width:22mm; text-align:center;">';
            $html .= '<img src="' . $qrPngPath . '" style="width:22mm; height:22mm;" />';
            $html .= '<div style="font-size:5pt; color:#64748b; margin-top:0.5mm;">Scan to verify</div>';
            $html .= '</div>';

            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } finally {
            @unlink($tmpImg);
        }
    }
}
