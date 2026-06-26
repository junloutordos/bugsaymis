<?php

namespace App\Console\Commands;

use App\Services\PdfTextExtractorService;
use Illuminate\Console\Command;

class TestTextractConnection extends Command
{
    protected $signature   = 'issuances:test-textract';
    protected $description = 'Test whether AWS Textract is reachable (not blocked by org SCP)';

    public function handle(PdfTextExtractorService $extractor): int
    {
        $this->info('Testing AWS Textract connectivity...');

        $ok = $extractor->testTextractConnection();

        if ($ok) {
            $this->info('✔ Textract is reachable. Image-PDF fallback will work.');
        } else {
            $this->error('✘ Textract is NOT reachable. It may be blocked by an org SCP or credentials are insufficient.');
            $this->line('  Image-based PDFs will produce empty content_text (smalot only).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
