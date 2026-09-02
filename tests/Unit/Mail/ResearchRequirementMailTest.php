<?php

namespace Tests\Unit\Mail;

use App\Mail\ResearchRequirementMail;
use Tests\TestCase;

class ResearchRequirementMailTest extends TestCase
{
    public function test_renders_with_subject_and_details_table(): void
    {
        $mail = new ResearchRequirementMail(
            recipientName: 'Juan Dela Cruz',
            headerTitle: 'New Research Requirement Posted',
            lead: 'A new submission requirement has been posted for your research group.',
            details: [['Requirement', 'Chapter 1 Draft'], ['Due', 'September 20, 2026']],
            actionUrl: 'https://example.test/my-research-requirements',
            actionLabel: 'View Requirement',
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Juan Dela Cruz', $rendered);
        $this->assertStringContainsString('Chapter 1 Draft', $rendered);
        $this->assertStringContainsString('View Requirement', $rendered);
        $this->assertSame('New Research Requirement Posted', $mail->subject);
    }
}
