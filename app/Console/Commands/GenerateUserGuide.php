<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class GenerateUserGuide extends Command
{
    protected $signature = 'userguide:generate {--output= : Full path for the PDF output file}';

    protected $description = 'Generate the comprehensive Atlas User Guide PDF for all roles';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $output = $this->option('output')
            ?? base_path('output/pdf/atlas-user-guide-edition-1.0-'.date('Y-m-d').'.pdf');

        File::ensureDirectoryExists(dirname($output));

        $this->info('Rendering HTML…');

        $data = [
            'generated_at' => now()->format('F j, Y \a\t g:i A'),
            'document' => [
                'edition' => '1.0',
                'document_id' => 'ATLAS-UG-2026-001',
                'snapshot_date' => now()->format('F j, Y'),
                'software_version' => '1.0.0',
                'rights_holder' => 'Philippine Science High School - Caraga Region Campus in Butuan City',
            ],
        ];

        $html = view('docs.user-guide', $data)->render();

        $this->info('Generating PDF (this may take a minute)…');

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontDirs = $defaultConfig['fontDir'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => sys_get_temp_dir(),
            'fontDir' => array_merge($defaultFontDirs, []),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'],
        ]);

        $mpdf->SetTitle('Atlas User Guide - Edition 1.0');
        $mpdf->SetAuthor('Philippine Science High School - Caraga Region Campus in Butuan City');
        $mpdf->SetSubject('Comprehensive user guide for all Atlas roles and modules');
        $mpdf->SetKeywords('Atlas, PSHS-CRC, user guide, how to, modules, roles');
        $mpdf->SetCreator('Atlas userguide:generate');

        $mpdf->SetHTMLHeader('
            <table width="100%" style="font-size:8pt; color:#94a3b8; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">
                <tr>
                    <td>Atlas - User Guide · Edition 1.0</td>
                    <td style="text-align:right;">ATLAS-UG-2026-001</td>
                </tr>
            </table>
        ');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:8pt; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:4px;">
                <tr>
                    <td>© 2026 PSHS-CRC · ATLAS-UG-2026-001</td>
                    <td style="text-align:right;">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        preg_match('/<style>(.*?)<\/style>/s', $html, $cssMatch);
        $css = $cssMatch[1] ?? '';
        $body = preg_replace('/<style>.*?<\/style>/s', '', $html);

        $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($body, HTMLParserMode::HTML_BODY);
        $mpdf->Output($output, 'F');

        $this->newLine();
        $this->info('✓ PDF saved to: '.$output);
        $this->line('  Size: '.round(filesize($output) / 1024).' KB');
        $this->line('  SHA-256: '.hash_file('sha256', $output));

        return self::SUCCESS;
    }
}
