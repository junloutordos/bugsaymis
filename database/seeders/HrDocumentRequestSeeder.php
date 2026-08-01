<?php

namespace Database\Seeders;

use App\Models\HR\HrDocumentRequestType;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class HrDocumentRequestSeeder extends Seeder
{
    private const PERMISSIONS = [
        'hr.document-requests.file' => 'File and track own HR document requests',
        'hr.document-requests.process' => 'Review and process employee HR document requests',
        'hr.document-requests.issue' => 'Issue completed HR documents',
        'hr.document-requests.manage-types' => 'Manage the HR document request catalog',
        'hr.document-requests.view-reports' => 'View HR document request turnaround reports',
        'hr.document-requests.ocd-approve' => 'Approve and sign HR documents as OCD',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'HR Document Requests',
                'description' => $description,
            ]);
        }

        $this->grant(
            ['Administrator', 'Faculty', 'Staff', 'DivisionChief', 'OCD', 'HR', 'MIS', 'Payroll Officer'],
            ['hr.document-requests.file'],
        );
        $this->grant(['Administrator', 'HR'], [
            'hr.document-requests.process',
            'hr.document-requests.issue',
            'hr.document-requests.manage-types',
            'hr.document-requests.view-reports',
        ]);
        $this->grant(['Administrator', 'OCD'], [
            'hr.document-requests.ocd-approve',
            'hr.document-requests.view-reports',
        ]);

        $types = [
            ['code' => 'COE', 'name' => 'Certificate of Employment', 'template_key' => 'employment', 'description' => 'Certifies current employment, position, and appointment details.', 'requires_ocd_approval' => false, 'signatory' => 'hr', 'sla_business_days' => 3],
            ['code' => 'COEC', 'name' => 'Certificate of Employment with Compensation', 'template_key' => 'employment_compensation', 'description' => 'Certifies employment and current compensation information.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 5],
            ['code' => 'SR', 'name' => 'Service Record', 'template_key' => 'service_record', 'description' => 'Official chronological record of government service.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 7],
            ['code' => 'CLC', 'name' => 'Certificate of Leave Credits', 'template_key' => 'leave_credits', 'description' => 'Certifies current vacation and sick leave balances.', 'requires_ocd_approval' => false, 'signatory' => 'hr', 'sla_business_days' => 3],
            ['code' => 'LSR', 'name' => 'Certificate of Last Salary Received', 'template_key' => 'last_salary', 'description' => 'Certifies the employee’s latest salary information.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 5],
            ['code' => 'NPAC', 'name' => 'Certificate of No Pending Administrative Case', 'template_key' => 'no_pending_case', 'description' => 'Certifies whether the employee has a pending administrative case on record.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 5],
            ['code' => 'EGS', 'name' => 'Certificate of Employment and Government Service', 'template_key' => 'government_service', 'description' => 'Certifies PSHS employment and accumulated government service.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 5],
            ['code' => 'OTHER', 'name' => 'Other HR Certification', 'template_key' => 'custom', 'description' => 'Other official HR certification subject to HR review.', 'requires_ocd_approval' => true, 'signatory' => 'ocd', 'sla_business_days' => 7],
        ];

        foreach ($types as $index => $type) {
            HrDocumentRequestType::updateOrCreate(['code' => $type['code']], [
                ...$type,
                'instructions' => 'State the intended purpose and receiving agency. HR may request supporting documents.',
                'supports_digital' => true,
                'supports_pickup' => true,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    private function grant(array $roleNames, array $permissionNames): void
    {
        $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
        Role::whereIn('name', $roleNames)->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }
}
