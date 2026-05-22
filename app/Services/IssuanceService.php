<?php

namespace App\Services;

use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
            // Recipient rows per user in each selected office
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

    // ── QR code SVG ──────────────────────────────────────────────────────────

    public function generateQrSvg(Issuance $issuance, int $size = 120): string
    {
        $url = route('issuances.verify', $issuance->qr_token);
        return QrCode::format('svg')->size($size)->margin(1)->generate($url);
    }

    // ── PDF generation ────────────────────────────────────────────────────────

    public function generatePdf(Issuance $issuance): string
    {
        ini_set('memory_limit', '256M');

        $sig    = $issuance->signature;
        $sigUri = $sig?->signer ? app(DigitalSignatureService::class)->getSignatureDataUri($sig->signer) : null;
        $qrSvg  = $this->generateQrSvg($issuance, 80);
        $qrB64  = base64_encode($qrSvg);

        // OCD user for signing block
        $ocdUser = $sig?->signer ?? $issuance->creator;

        $html = view('issuances.pdf', compact('issuance', 'sig', 'sigUri', 'ocdUser', 'qrB64'))->render();

        $headerImg = public_path('images/report_header.jpeg');
        $footerImg = public_path('images/report_footer.jpeg');

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_left'    => 15,
            'margin_right'   => 15,
            'margin_top'     => 42,   // space for the header image
            'margin_bottom'  => 22,   // space for the footer image
            'margin_header'  => 0,
            'margin_footer'  => 0,
            'tempDir'        => sys_get_temp_dir(),
            'fontdata'       => (new FontVariables())->getDefaults()['fontdata'],
            'fontDir'        => (new ConfigVariables())->getDefaults()['fontDir'],
        ]);

        $mpdf->SetTitle($issuance->type_label . ' — ' . $issuance->control_number);

        $mpdf->SetHTMLHeader('
            <div style="margin:0; padding:0;">
                <img src="' . $headerImg . '" style="width:100%; display:block;" />
            </div>
        ');

        $mpdf->SetHTMLFooter('
            <div style="margin:0; padding:0;">
                <img src="' . $footerImg . '" style="width:100%; display:block;" />
            </div>
        ');

        $mpdf->WriteHTML($html);

        $path = 'issuances/' . $issuance->control_number . '.pdf';
        \Illuminate\Support\Facades\Storage::disk('s3')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
