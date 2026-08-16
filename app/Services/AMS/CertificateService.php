<?php

namespace App\Services\AMS;

use App\Mail\AMS\ActivityCertificateMail;
use App\Models\AMS\Activity;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    public function delete(?string $storagePath): void
    {
        if ($storagePath) {
            Storage::disk('s3')->delete($storagePath);
        }
    }

    /**
     * Generate a certificate PDF for one participant, save it, and return the storage path.
     */
    public function buildAndSave(
        Activity $activity,
        string   $name,
        mixed    $hoursAttended,
        int      $participantId
    ): string {
        $verifyHash = md5($participantId . '-' . $activity->id);
        $verifyUrl  = url(route('ams.certificates.verify', [
            'activity' => $activity->id,
            'hash'     => $verifyHash,
        ]));

        $qrSvg     = QrCode::size(150)->generate($verifyUrl);
        $qrDataUrl = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $bgPath         = 'file://' . public_path('images/ams/certtemplatebg.jpg');
        $dateStart      = $activity->start_date?->format('F d, Y') ?? '';
        $dateEnd        = $activity->end_date?->format('F d, Y') ?? '';
        $day            = $activity->end_date?->format('j') ?? date('j');
        $monthYear      = $activity->end_date?->format('F Y') ?? date('F Y');
        $dateOfIssuance = $this->ordinal((int) $day) . ' day of ' . $monthYear;

        $html = view('ams.certificate', compact(
            'activity', 'name', 'hoursAttended',
            'dateStart', 'dateEnd', 'dateOfIssuance',
            'qrDataUrl', 'bgPath'
        ))->render();

        try {
            $mpdf = new Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4-L',
                'margin_left'   => 0,
                'margin_right'  => 0,
                'margin_top'    => 0,
                'margin_bottom' => 0,
                'tempDir'       => sys_get_temp_dir(),
                'fontDir'       => [storage_path('fonts')],
                'fontdata'      => [
                    'montserrat' => [
                        'R' => 'Montserrat-Regular.ttf',
                        'B' => 'Montserrat-Bold.ttf',
                        'I' => 'Montserrat-Italic.ttf',
                    ],
                    'montserrateb' => [
                        'R' => 'Montserrat-ExtraBold.ttf',
                    ],
                ],
                'default_font'  => 'montserrat',
            ]);

            $mpdf->SetTitle('Certificate of Participation — ' . $activity->title);
            $mpdf->SetAuthor('PSHS-CRC');
            $mpdf->WriteHTML($html);

            $pdfContent = $mpdf->Output('', 'S');

        } catch (MpdfException $e) {
            throw new \RuntimeException('PDF generation failed: ' . $e->getMessage(), 0, $e);
        }

        $dir      = "ams/certificates/{$activity->id}";
        $filename = 'Certificate_' . md5($name) . '.pdf';
        $fullPath = "{$dir}/{$filename}";

        Storage::disk('s3')->makeDirectory($dir);
        Storage::disk('s3')->put($fullPath, $pdfContent);

        return $fullPath;
    }

    /**
     * Send a certificate email to the given address.
     */
    public function sendCertificateEmail(
        Activity $activity,
        string   $email,
        string   $recipientName,
        string   $storagePath
    ): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;

        try {
            Mail::to($email)->send(
                new ActivityCertificateMail($activity, $recipientName, $storagePath)
            );
        } catch (\Throwable $e) {
            \Log::warning("AMS: certificate email failed for {$email}: " . $e->getMessage());
            report($e);
        }
    }

    private function ordinal(int $n): string
    {
        $s = ['th','st','nd','rd','th','th','th','th','th','th'];
        if ($n % 100 >= 11 && $n % 100 <= 13) return $n . 'th';
        return $n . $s[$n % 10];
    }
}
