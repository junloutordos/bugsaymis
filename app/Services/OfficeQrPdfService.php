<?php

namespace App\Services;

use App\Models\Office;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OfficeQrPdfService
{
    /**
     * Generate a premium, printable A4 PDF containing the office's QR
     * client satisfaction survey code, with instructions for clients.
     *
     * Returns the absolute path of a temp PDF file (caller is responsible
     * for streaming/deleting it — see OfficeController::qrSurveyPdf).
     */
    public function generate(Office $office): string
    {
        $surveyUrl = $office->surveyUrl();
        $qrSvg     = QrCode::format('svg')->size(360)->margin(1)->generate($surveyUrl);
        $tmpSvg    = sys_get_temp_dir() . '/office_qr_' . $office->id . '_' . time() . '.svg';
        file_put_contents($tmpSvg, $qrSvg);

        $headerImg = public_path('images/report_header.jpeg');
        $footerImg = public_path('images/report_footer.jpeg');
        $logoImg   = public_path('images/pshslogo.png');

        $officeName = e($office->name);
        $token      = e($office->qr_survey_token);
        $generated  = now()->format('F d, Y \a\t g:i A');

        $html = <<<HTML
        <style>
            body { font-family: helvetica, sans-serif; }
            .card {
                margin-top: 8mm;
                border: 1.5pt solid #4338CA;
                border-radius: 4mm;
                padding: 10mm 12mm;
                text-align: center;
            }
            .eyebrow {
                color: #4338CA;
                font-size: 10pt;
                letter-spacing: 2pt;
                text-transform: uppercase;
                font-weight: bold;
                margin-bottom: 2mm;
            }
            .title {
                font-size: 20pt;
                font-weight: bold;
                color: #1E1B4B;
                margin-bottom: 1mm;
            }
            .office-name {
                font-size: 16pt;
                font-weight: bold;
                color: #312E81;
                background-color: #EEF2FF;
                border-radius: 3mm;
                padding: 3mm 6mm;
                display: inline-block;
                margin: 4mm 0 6mm 0;
            }
            .instructions {
                font-size: 10.5pt;
                color: #334155;
                line-height: 1.6;
                margin: 5mm auto 6mm auto;
                width: 140mm;
                text-align: left;
            }
            .instructions ol { margin: 0; padding-left: 5mm; }
            .instructions li { margin-bottom: 1.5mm; }
            .qr-frame {
                border: 1pt solid #C7D2FE;
                border-radius: 3mm;
                padding: 6mm;
                display: inline-block;
                background-color: #FAFAFF;
            }
            .scan-label {
                font-size: 9pt;
                color: #6366F1;
                font-weight: bold;
                letter-spacing: 1pt;
                text-transform: uppercase;
                margin-top: 3mm;
            }
            .footer-note {
                margin-top: 8mm;
                font-size: 8pt;
                color: #94A3B8;
            }
            .footer-note .token {
                font-family: courier, monospace;
                color: #A1A1AA;
            }
            .anon-badge {
                display: inline-block;
                margin-top: 4mm;
                font-size: 8.5pt;
                color: #059669;
                background-color: #ECFDF5;
                border: 1pt solid #A7F3D0;
                border-radius: 8mm;
                padding: 1.5mm 5mm;
            }
        </style>

        <div class="card">
            <div class="eyebrow">PSHS – Caraga Region Campus</div>
            <div class="title">Client Satisfaction Survey</div>

            <div class="office-name">{$officeName}</div>

            <div class="instructions">
                <strong>Paano sumali / How to give feedback:</strong>
                <ol>
                    <li>Open your phone's camera or QR scanner app.</li>
                    <li>Scan the QR code below.</li>
                    <li>Answer the short satisfaction survey about the service you availed.</li>
                    <li>Submit — your response is completely anonymous.</li>
                </ol>
            </div>

            <div class="qr-frame">
                <img src="{$tmpSvg}" style="width:70mm;height:70mm;" />
            </div>
            <div class="scan-label">Scan to give feedback</div>

            <div><span class="anon-badge">🔒 100% Anonymous — No login required</span></div>

            <div class="footer-note">
                Generated {$generated} &nbsp;•&nbsp; Survey code: <span class="token">{$token}</span>
            </div>
        </div>
        HTML;

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

        $mpdf->SetTitle('QR Client Satisfaction Survey — ' . $office->name);
        $mpdf->SetHTMLHeader('<div style="margin:0;padding:0;"><img src="' . $headerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->SetHTMLFooter('<div style="margin:0;padding:0;"><img src="' . $footerImg . '" style="width:100%;display:block;" /></div>');
        $mpdf->WriteHTML($html);

        $outPath = sys_get_temp_dir() . '/office_qr_survey_' . $office->id . '_' . time() . '.pdf';

        try {
            $mpdf->Output($outPath, 'F');
        } finally {
            @unlink($tmpSvg);
        }

        return $outPath;
    }
}
