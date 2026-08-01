<?php

namespace Tests\Feature\HR;

use App\Models\HR\ServiceRecordEntry;
use App\Models\HR\ServiceRecordEvidence;
use App\Models\HR\HrDocumentRequest;
use App\Models\HR\HrDocumentRequestType;
use App\Models\HR\HrIssuedDocument;
use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\PDSWorkExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\HR\HrDocumentRequestService;
use App\Services\HR\HrDocumentPdfService;
use App\Services\HR\ServiceRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceRecordModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_unauthorized_employee_cannot_open_the_hr_registry(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('hr.service-records.index'))
            ->assertForbidden();
    }

    public function test_employee_can_only_view_own_verified_entries(): void
    {
        $employee = $this->userWithPermissions('Employee', ['hr.service-records.view-own']);
        $other = User::factory()->create();
        ServiceRecordEntry::create($this->entryData($employee, ['status' => 'verified']));
        ServiceRecordEntry::create($this->entryData($employee, [
            'position_title' => 'Unverified Draft',
            'service_from' => '2025-01-01',
            'status' => 'draft',
        ]));

        $this->actingAs($employee)
            ->get(route('hr.service-records.my'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('HR/ServiceRecords/Show')
                ->has('entries', 1)
                ->where('entries.0.status', 'verified'));

        $this->actingAs($employee)
            ->get(route('hr.service-records.show', $other))
            ->assertForbidden();
    }

    public function test_cos_and_job_order_cannot_be_saved_as_creditable_service(): void
    {
        $hr = $this->userWithPermissions('HR Officer', ['hr.service-records.manage']);
        $employee = User::factory()->create();

        foreach (['job_order', 'contract_of_service'] as $status) {
            $this->actingAs($hr)
                ->post(route('hr.service-records.store', $employee), [
                    'service_from' => '2024-01-01',
                    'position_title' => 'Project Assistant',
                    'employment_status' => $status,
                    'agency_name' => 'PSHS-CRC',
                    'credited_as_government_service' => true,
                ])
                ->assertSessionHasErrors('credited_as_government_service');
        }

        $this->assertDatabaseCount('hr_service_record_entries', 0);
    }

    public function test_verification_requires_assumption_date_and_evidence(): void
    {
        $verifier = User::factory()->create();
        $employee = User::factory()->create();
        $entry = ServiceRecordEntry::create($this->entryData($employee));

        try {
            app(ServiceRecordService::class)->verify($entry, $verifier);
            $this->fail('Expected verification to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assumed_at', $exception->errors());
            $this->assertArrayHasKey('evidence', $exception->errors());
        }

        $entry->update(['assumed_at' => '2020-01-01']);
        ServiceRecordEvidence::create([
            'service_record_entry_id' => $entry->id,
            'document_type' => 'appointment',
            'title' => 'Appointment Paper',
            's3_path' => 'test/appointment.pdf',
            'original_filename' => 'appointment.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'sha256' => hash('sha256', 'test'),
            'uploaded_by' => $verifier->id,
        ]);
        ServiceRecordEvidence::create([
            'service_record_entry_id' => $entry->id,
            'document_type' => 'assumption_to_duty',
            'title' => 'Certification of Assumption to Duty',
            's3_path' => 'test/assumption.pdf',
            'original_filename' => 'assumption.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'sha256' => hash('sha256', 'assumption'),
            'uploaded_by' => $verifier->id,
        ]);

        app(ServiceRecordService::class)->verify($entry->fresh(), $verifier);

        $this->assertDatabaseHas('hr_service_record_entries', [
            'id' => $entry->id,
            'status' => 'verified',
            'verified_by' => $verifier->id,
        ]);
    }

    public function test_overlapping_verified_creditable_period_is_rejected(): void
    {
        $verifier = User::factory()->create();
        $employee = User::factory()->create();
        ServiceRecordEntry::create($this->entryData($employee, [
            'service_from' => '2020-01-01',
            'service_to' => '2022-12-31',
            'assumed_at' => '2020-01-01',
            'status' => 'verified',
        ]));
        $overlap = ServiceRecordEntry::create($this->entryData($employee, [
            'service_from' => '2022-06-01',
            'service_to' => '2023-12-31',
            'assumed_at' => '2022-06-01',
            'position_title' => 'Senior Science Research Specialist',
        ]));
        ServiceRecordEvidence::create([
            'service_record_entry_id' => $overlap->id,
            'document_type' => 'appointment',
            'title' => 'Promotion Appointment',
            's3_path' => 'test/promotion.pdf',
            'original_filename' => 'promotion.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'sha256' => hash('sha256', 'promotion'),
        ]);
        ServiceRecordEvidence::create([
            'service_record_entry_id' => $overlap->id,
            'document_type' => 'assumption_to_duty',
            'title' => 'Assumption to Duty',
            's3_path' => 'test/promotion-assumption.pdf',
            'original_filename' => 'promotion-assumption.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'sha256' => hash('sha256', 'promotion-assumption'),
        ]);

        $this->expectException(ValidationException::class);
        app(ServiceRecordService::class)->verify($overlap, $verifier);
    }

    public function test_pds_import_creates_unverified_drafts_without_duplicates(): void
    {
        $hr = User::factory()->create();
        $employee = User::factory()->create();
        $pds = Pds::create(['user_id' => $employee->id]);
        PDSWorkExperience::create([
            'pds_id' => $pds->id,
            'from_date' => '2018-01-01',
            'to_date' => '2019-12-31',
            'position' => 'Administrative Assistant',
            'agency' => 'Department of Science and Technology',
            'appointment_status' => 'Permanent',
            'government_service' => 'Yes',
        ]);

        $service = app(ServiceRecordService::class);
        $this->assertSame(1, $service->importPds($employee, $hr));
        $this->assertSame(0, $service->importPds($employee->fresh(), $hr));
        $this->assertDatabaseHas('hr_service_record_entries', [
            'user_id' => $employee->id,
            'source_type' => 'pds',
            'status' => 'draft',
            'employment_status' => 'permanent',
        ]);
    }

    public function test_service_record_document_payload_uses_only_verified_master_ledger_entries(): void
    {
        $employee = User::factory()->create(['name' => 'Maria Santos', 'employee_no' => 'CRC-001']);
        $pds = Pds::create(['user_id' => $employee->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id,
            'surname' => 'Santos',
            'first_name' => 'Maria',
            'date_of_birth' => '1990-05-10',
            'place_of_birth' => 'Butuan City',
        ]);
        ServiceRecordEntry::create($this->entryData($employee, [
            'assumed_at' => '2020-01-01',
            'status' => 'verified',
            'salary_amount' => 480000,
            'salary_basis' => 'annual',
        ]));
        ServiceRecordEntry::create($this->entryData($employee, [
            'service_from' => '2018-01-01',
            'service_to' => '2019-12-31',
            'position_title' => 'Unverified Prior Work',
            'status' => 'draft',
        ]));
        $type = HrDocumentRequestType::create([
            'code' => 'SR-TEST',
            'name' => 'Service Record',
            'template_key' => 'service_record',
            'requires_ocd_approval' => true,
            'signatory' => 'ocd',
            'sla_business_days' => 7,
            'supports_digital' => true,
            'supports_pickup' => true,
            'is_active' => true,
        ]);
        $request = HrDocumentRequest::create([
            'reference_no' => 'HRDR-2026-0001',
            'requester_id' => $employee->id,
            'request_type_id' => $type->id,
            'status' => 'submitted',
            'purpose' => 'Retirement processing',
            'copies' => 1,
            'delivery_method' => 'digital',
        ]);

        $payload = app(HrDocumentRequestService::class)->defaultPayload($request->load(['type', 'requester']));

        $this->assertCount(1, $payload['service_rows']);
        $this->assertSame('Science Research Specialist', $payload['service_rows'][0]['position']);
        $this->assertSame('1990-05-10', $payload['birth_date']);
        $this->assertSame('Butuan City', $payload['birth_place']);
        $this->assertArrayHasKey('service_record_snapshot', $payload);
    }

    public function test_government_service_record_pdf_renders_from_the_frozen_snapshot(): void
    {
        $employee = User::factory()->create(['name' => 'Juan Dela Cruz', 'employee_no' => 'CRC-002']);
        $issuer = User::factory()->create(['name' => 'HR Officer']);
        ServiceRecordEntry::create($this->entryData($employee, [
            'assumed_at' => '2020-01-01',
            'status' => 'verified',
        ]));
        $type = HrDocumentRequestType::create([
            'code' => 'SR-PDF', 'name' => 'Service Record', 'template_key' => 'service_record',
            'requires_ocd_approval' => false, 'signatory' => 'hr', 'sla_business_days' => 7,
            'supports_digital' => true, 'supports_pickup' => true, 'is_active' => true,
        ]);
        $request = HrDocumentRequest::create([
            'reference_no' => 'HRDR-2026-0002', 'requester_id' => $employee->id,
            'request_type_id' => $type->id, 'status' => 'processing', 'purpose' => 'Official use',
            'copies' => 1, 'delivery_method' => 'digital',
        ]);
        $payload = app(HrDocumentRequestService::class)->defaultPayload($request->load(['type', 'requester']));
        $request->update(['document_payload' => $payload]);
        $issued = HrIssuedDocument::create([
            'request_id' => $request->id,
            'control_number' => 'HRDOC-2026-0002',
            'version' => 1,
            's3_path' => 'pending',
            'content_hash' => hash('sha256', 'service-record'),
            'qr_token' => (string) Str::uuid(),
            'issued_by' => $issuer->id,
            'issued_at' => now(),
        ]);

        $path = app(HrDocumentPdfService::class)->generate($request->fresh('type'), $issued);

        Storage::disk('s3')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('s3')->get($path));
    }

    private function entryData(User $employee, array $overrides = []): array
    {
        return [
            'user_id' => $employee->id,
            'service_from' => '2020-01-01',
            'service_to' => null,
            'position_title' => 'Science Research Specialist',
            'employment_status' => 'permanent',
            'appointment_nature' => 'original',
            'agency_name' => 'Philippine Science High School – Caraga Region Campus',
            'credited_as_government_service' => true,
            'salary_amount' => 360000,
            'salary_basis' => 'annual',
            'source_type' => 'manual',
            'status' => 'draft',
            ...$overrides,
        ];
    }

    private function userWithPermissions(string $name, array $permissionNames): User
    {
        $role = Role::firstOrCreate(['name' => "{$name} Role"]);
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName], [
                'module' => 'HR Service Records',
                'description' => $permissionName,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
