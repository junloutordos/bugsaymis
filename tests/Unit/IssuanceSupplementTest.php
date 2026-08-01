<?php

namespace Tests\Unit;

use App\Models\Issuance;
use App\Services\IssuanceService;
use Tests\TestCase;

class IssuanceSupplementTest extends TestCase
{
    public function test_it_exposes_supplement_labels_and_display_number(): void
    {
        $supplement = new Issuance([
            'parent_issuance_id' => 10,
            'document_kind' => 'corrigendum',
            'control_number' => 'SUP-TECHNICAL',
            'reference_number' => 'Corrigendum No. 1 to MEMO-2026-08-001',
        ]);

        $this->assertTrue($supplement->isSupplement());
        $this->assertSame('Corrigendum', $supplement->document_kind_label);
        $this->assertSame('Corrigendum No. 1 to MEMO-2026-08-001', $supplement->display_number);
    }

    public function test_archive_state_is_independent_of_release_status(): void
    {
        $issuance = new Issuance([
            'status' => 'released',
            'archived_at' => '2026-08-01 10:00:00',
        ]);

        $this->assertTrue($issuance->isReleased());
        $this->assertTrue($issuance->isArchived());
    }

    public function test_supplement_hash_covers_its_parent_kind_and_reference(): void
    {
        $base = new Issuance([
            'control_number' => 'SUP-TECHNICAL',
            'parent_issuance_id' => 10,
            'document_kind' => 'erratum',
            'supplement_no' => 1,
            'reference_number' => 'Erratum No. 1 to MEMO-2026-08-001',
            'title' => 'Correction',
            'content' => '<p>Corrected text.</p>',
        ]);

        $changed = clone $base;
        $changed->reference_number = 'Erratum No. 2 to MEMO-2026-08-001';

        $service = new IssuanceService();
        $this->assertNotSame($service->computeHash($base), $service->computeHash($changed));
    }
}
