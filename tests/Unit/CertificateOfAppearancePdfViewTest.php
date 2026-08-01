<?php

namespace Tests\Unit;

use App\Models\Administration\CoaCertificate;
use App\Models\Administration\CoaVisit;
use App\Models\DigitalSignature;
use App\Models\User;
use App\Services\CertificateOfAppearanceService;
use App\Services\DigitalSignatureService;
use App\Services\PersonNameFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateOfAppearancePdfViewTest extends TestCase
{
    public function test_it_appends_the_signers_signature_image_to_the_certificate(): void
    {
        [$certificate, $visit] = $this->certificateWithSignature();
        $signatureUri = 'data:image/png;base64,c2lnbmF0dXJl';
        $signerName = 'JUAN S. DELA CRUZ, LPT';

        $html = view('coa.pdf', [
            'cert' => $certificate,
            'visit' => $visit,
            'signatureUri' => $signatureUri,
            'signerName' => $signerName,
        ])->render();

        $this->assertStringContainsString('src="'.$signatureUri.'"', $html);
        $this->assertStringContainsString('Digital Signature', $html);
        $this->assertStringContainsString('MARIA SANTOS', $html);
        $this->assertStringContainsString('JUAN S. DELA CRUZ, LPT', $html);
        $this->assertStringContainsString('Campus Director', $html);
        $this->assertStringContainsString('Digitally signed (PIN-verified)', $html);
    }

    public function test_it_keeps_signature_spacing_when_no_image_is_available(): void
    {
        [$certificate, $visit] = $this->certificateWithSignature();
        $signatureUri = null;
        $signerName = 'JUAN S. DELA CRUZ, LPT';

        $html = view('coa.pdf', [
            'cert' => $certificate,
            'visit' => $visit,
            'signatureUri' => $signatureUri,
            'signerName' => $signerName,
        ])->render();

        $this->assertStringNotContainsString('alt="Digital Signature"', $html);
        $this->assertStringContainsString('height:50px', $html);
        $this->assertStringContainsString('JUAN S. DELA CRUZ, LPT', $html);
    }

    public function test_pdf_generation_uses_the_snapshotted_signature_path(): void
    {
        Storage::fake('s3');
        [$certificate] = $this->certificateWithSignature('signatures/user_1_issued.png');

        $signatureService = $this->createMock(DigitalSignatureService::class);
        $signatureService->expects($this->once())
            ->method('getImageDataUriFromPath')
            ->with('signatures/user_1_issued.png')
            ->willReturn($this->tinyPngDataUri());
        $signatureService->expects($this->never())->method('getSignatureDataUri');

        $path = (new CertificateOfAppearanceService($signatureService, new PersonNameFormatter))->generatePdf($certificate);

        $this->assertSame('coa/v2/COA-2026-0001.pdf', $path);
        Storage::disk('s3')->assertExists($path);
        $this->assertGreaterThan(1000, strlen(Storage::disk('s3')->get($path)));
    }

    public function test_pdf_generation_falls_back_for_older_signature_records(): void
    {
        Storage::fake('s3');
        [$certificate] = $this->certificateWithSignature();

        $signatureService = $this->createMock(DigitalSignatureService::class);
        $signatureService->expects($this->once())
            ->method('getImageDataUriFromPath')
            ->with(null)
            ->willReturn(null);
        $signatureService->expects($this->once())
            ->method('getSignatureDataUri')
            ->with($certificate->signature->signer)
            ->willReturn($this->tinyPngDataUri());

        $path = (new CertificateOfAppearanceService($signatureService, new PersonNameFormatter))->generatePdf($certificate);

        Storage::disk('s3')->assertExists($path);
    }

    public function test_it_snapshots_the_signature_image_in_s3(): void
    {
        Storage::fake('s3');
        [$certificate] = $this->certificateWithSignature();
        $signer = $certificate->signature->signer;

        $signatureService = $this->createMock(DigitalSignatureService::class);
        $signatureService->expects($this->once())
            ->method('getSignatureDataUri')
            ->with($signer)
            ->willReturn($this->tinyPngDataUri());

        $path = (new CertificateOfAppearanceService($signatureService, new PersonNameFormatter))
            ->snapshotSignature($signer, $certificate);

        $this->assertSame('signatures/documents/coa/certificate_1.png', $path);
        Storage::disk('s3')->assertExists($path);
    }

    private function certificateWithSignature(?string $signaturePath = null): array
    {
        $visit = new CoaVisit([
            'purpose' => 'official coordination',
            'date_from' => '2026-08-01',
            'issued_at' => '2026-08-01 09:30:00',
        ]);

        $signer = new User([
            'name' => 'dela Cruz, Juan Santos',
            'position' => 'Campus Director',
            'postnominal_title' => 'LPT',
        ]);
        $signer->setRelation('pds', null);

        $signature = new DigitalSignature([
            'signed_at' => Carbon::parse('2026-08-01 09:30:00'),
            'metadata' => $signaturePath ? ['signature_path' => $signaturePath] : [],
        ]);
        $signature->setRelation('signer', $signer);

        $certificate = new CoaCertificate([
            'visitor_name' => 'Maria Santos',
            'control_number' => 'COA-2026-0001',
            'qr_token' => '11111111-1111-4111-8111-111111111111',
            'content_hash' => str_repeat('a', 64),
        ]);
        $certificate->id = 1;
        $certificate->setRelation('visit', $visit);
        $certificate->setRelation('signature', $signature);

        return [$certificate, $visit];
    }

    private function tinyPngDataUri(): string
    {
        $image = imagecreatetruecolor(240, 80);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $ink = imagecolorallocatealpha($image, 15, 23, 42, 0);
        imageline($image, 15, 58, 55, 24, $ink);
        imageline($image, 55, 24, 92, 60, $ink);
        imageline($image, 92, 60, 132, 18, $ink);
        imageline($image, 132, 18, 220, 52, $ink);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
