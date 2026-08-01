<?php

namespace Tests\Feature\HR;

use App\Models\HR\HrDocumentRequest;
use App\Models\HR\HrDocumentRequestType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalInboxService;
use App\Services\HR\HrDocumentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrDocumentRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_employee_can_file_with_a_base64_attachment_and_track_the_request(): void
    {
        $employee = $this->userWithPermission('Employee Test', 'hr.document-requests.file');
        $type = $this->type();

        $response = $this->actingAs($employee)->post(route('hr.document-requests.store'), [
            'request_type_id' => $type->id,
            'purpose' => 'Bank loan application',
            'copies' => 1,
            'delivery_method' => 'digital',
            'attachments' => [[
                'name' => 'support.pdf',
                'data_uri' => 'data:application/pdf;base64,'.base64_encode('%PDF-test'),
            ]],
        ]);

        $request = HrDocumentRequest::with('attachments')->firstOrFail();
        $response->assertRedirect(route('hr.document-requests.show', $request));
        $this->assertSame('submitted', $request->status);
        $this->assertMatchesRegularExpression('/^HRDR-\d{4}-\d{4}$/', $request->reference_no);
        $this->assertCount(1, $request->attachments);
        Storage::disk('s3')->assertExists($request->attachments->first()->getRawOriginal('s3_path'));
        $this->assertDatabaseHas('hr_document_request_events', [
            'request_id' => $request->id,
            'action' => 'submitted',
            'to_status' => 'submitted',
        ]);
    }

    public function test_employee_cannot_open_another_employees_request(): void
    {
        $owner = User::factory()->create();
        $other = $this->userWithPermission('Other Employee', 'hr.document-requests.file');
        $request = $this->submit($owner, $this->type());

        $this->actingAs($other)
            ->get(route('hr.document-requests.show', $request))
            ->assertForbidden();
    }

    public function test_ocd_required_document_appears_in_the_inbox_and_is_pin_signed(): void
    {
        $employee = User::factory()->create(['name' => 'Juan Dela Cruz', 'position' => 'Science Research Specialist']);
        $processor = User::factory()->create(['name' => 'HR Officer']);
        $ocd = $this->userWithPermission('OCD Approver', 'hr.document-requests.ocd-approve', 'OCD', [
            'electronic_signature' => 'signatures/ocd.png',
            'signature_pin' => bcrypt('654321'),
        ]);
        Storage::disk('s3')->put('signatures/ocd.png', $this->png());

        $service = app(HrDocumentRequestService::class);
        $request = $this->submit($employee, $this->type(true));
        $service->start($request->load(['type', 'requester']), $processor);
        $service->saveDraft($request, $processor, [
            'employee_name' => 'JUAN DELA CRUZ',
            'position' => $employee->position,
            'body' => 'Prepared and reviewed certification content.',
            'service_rows' => [],
            'certification_clause' => 'Issued for the stated purpose.',
        ], 'Ready for OCD review.');
        $service->routeToOcd($request->load('type'), $processor);

        $tab = collect((new ApprovalInboxService($ocd))->getPendingItems())
            ->firstWhere('type', 'hr_document_requests');
        $this->assertNotNull($tab);
        $this->assertSame(1, $tab['count']);

        $this->actingAs($ocd)
            ->post(route('approvals.approve', ['type' => 'hr_document_requests', 'id' => $request->id]), [
                'pin' => 'wrong-pin',
            ])->assertSessionHasErrors('pin');
        $this->assertSame('pending_ocd_approval', $request->fresh()->status);

        $this->actingAs($ocd)
            ->post(route('approvals.approve', ['type' => 'hr_document_requests', 'id' => $request->id]), [
                'pin' => '654321',
            ])->assertRedirect();

        $this->assertSame('ready_for_release', $request->fresh()->status);
        $this->assertDatabaseHas('digital_signatures', [
            'signable_type' => HrDocumentRequest::class,
            'signable_id' => $request->id,
            'signer_id' => $ocd->id,
        ]);
        $this->assertNull(collect((new ApprovalInboxService($ocd))->getPendingItems())
            ->firstWhere('type', 'hr_document_requests'));
    }

    public function test_hr_can_pin_sign_issue_download_and_publicly_verify_a_document(): void
    {
        $employee = User::factory()->create(['name' => 'Maria Santos', 'position' => 'Administrative Officer']);
        $issuer = User::factory()->create([
            'name' => 'HR Signatory',
            'position' => 'Human Resource Management Officer',
            'electronic_signature' => 'signatures/hr.png',
            'signature_pin' => bcrypt('123456'),
        ]);
        Storage::disk('s3')->put('signatures/hr.png', $this->png());

        $service = app(HrDocumentRequestService::class);
        $request = $this->submit($employee, $this->type());
        $service->start($request->load(['type', 'requester']), $issuer);
        $issued = $service->issue($request, $issuer, '123456');

        $this->assertSame('issued', $request->fresh()->status);
        $this->assertSame('processing', $request->events()->where('action', 'issued')->value('from_status'));
        Storage::disk('s3')->assertExists($issued->getRawOriginal('s3_path'));
        $this->assertStringStartsWith('%PDF', Storage::disk('s3')->get($issued->getRawOriginal('s3_path')));

        $this->get(route('hr.document-requests.verify', $issued->qr_token))
            ->assertOk()
            ->assertSee($issued->control_number)
            ->assertSee('Authentic issuance record');
    }

    private function submit(User $employee, HrDocumentRequestType $type): HrDocumentRequest
    {
        return app(HrDocumentRequestService::class)->submit($employee, $type, [
            'purpose' => 'Official transaction',
            'copies' => 1,
            'delivery_method' => 'digital',
        ]);
    }

    private function type(bool $requiresOcd = false): HrDocumentRequestType
    {
        return HrDocumentRequestType::create([
            'code' => $requiresOcd ? 'TEST-OCD' : 'TEST-HR',
            'name' => $requiresOcd ? 'OCD Certification' : 'HR Certification',
            'description' => 'Workflow test type',
            'template_key' => 'employment',
            'requires_ocd_approval' => $requiresOcd,
            'signatory' => $requiresOcd ? 'ocd' : 'hr',
            'sla_business_days' => 3,
            'supports_digital' => true,
            'supports_pickup' => true,
            'is_active' => true,
        ]);
    }

    private function userWithPermission(string $name, string $permissionName, string $roleName = 'Test Employee', array $attributes = []): User
    {
        $permission = Permission::firstOrCreate(['name' => $permissionName], [
            'module' => 'HR Document Requests',
            'description' => $permissionName,
        ]);
        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create(['name' => $name, ...$attributes]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
