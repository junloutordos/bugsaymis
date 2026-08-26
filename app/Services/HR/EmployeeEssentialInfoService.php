<?php

namespace App\Services\HR;

use App\Models\HR\EmployeeProfile;
use App\Models\Pds;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Detects and fills the essential employee data needed for the ID card and
 * emergency-contact features, but not yet captured anywhere:
 *  - Date of birth              → written into PDSPersonalInfo (PDS source of truth)
 *  - Residential address        → written into PDSPersonalInfo's 8 structured fields
 *  - Emergency contact (name/mobile/address) → written into EmployeeProfile
 *
 * Existing PDS data is always treated as authoritative and never queried
 * again once present — this service only fills genuine gaps, prompted once
 * via a mandatory (non-dismissable) login modal.
 */
class EmployeeEssentialInfoService
{
    /**
     * Residential fields that must all be present for the address to be
     * considered "complete enough" to skip the prompt. Street/subdivision/
     * region/zip are commonly blank even on a fully filled-out PDS.
     */
    private const REQUIRED_ADDRESS_FIELDS = [
        'residential_house',
        'residential_barangay',
        'residential_city',
        'residential_province',
    ];

    /**
     * Returns the list of missing field groups for the given user:
     * a subset of ['date_of_birth', 'residential_address', 'emergency_contact'].
     * Empty array means nothing is missing — no prompt needed.
     */
    public function missingFields(User $user): array
    {
        $missing = [];

        $personalInfo = $user->pds?->personalInfo;

        if (empty($personalInfo?->date_of_birth)) {
            $missing[] = 'date_of_birth';
        }

        if (! $this->hasCompleteAddress($personalInfo)) {
            $missing[] = 'residential_address';
        }

        $profile = $user->employeeProfile;
        if (
            empty($profile?->emergency_contact_name) ||
            empty($profile?->emergency_contact_phone) ||
            empty($profile?->emergency_contact_address)
        ) {
            $missing[] = 'emergency_contact';
        }

        return $missing;
    }

    private function hasCompleteAddress(?object $personalInfo): bool
    {
        if (! $personalInfo) {
            return false;
        }

        foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
            if (empty($personalInfo->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Persist only the field groups actually supplied. Never overwrites PDS
     * data that already exists — missingFields() should have been used to
     * determine what to ask for, but this method is defensive regardless:
     * it only writes when the incoming value is non-empty.
     */
    public function save(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            if (! empty($data['date_of_birth']) || $this->hasAnyAddressField($data)) {
                $this->savePersonalInfo($user, $data);
            }

            if ($this->hasAnyEmergencyContactField($data)) {
                $this->saveEmergencyContact($user, $data);
            }
        });
    }

    private function hasAnyAddressField(array $data): bool
    {
        foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
            if (! empty($data[$field] ?? null)) {
                return true;
            }
        }

        return ! empty($data['residential_street'] ?? null)
            || ! empty($data['residential_subdivision'] ?? null)
            || ! empty($data['residential_region'] ?? null)
            || ! empty($data['residential_zip_code'] ?? null);
    }

    private function hasAnyEmergencyContactField(array $data): bool
    {
        return ! empty($data['emergency_contact_name'] ?? null)
            || ! empty($data['emergency_contact_phone'] ?? null)
            || ! empty($data['emergency_contact_address'] ?? null);
    }

    private function savePersonalInfo(User $user, array $data): void
    {
        $pds = $user->pds ?? Pds::create(['user_id' => $user->id]);

        $personalInfo = $pds->personalInfo;

        $addressFields = [
            'residential_house', 'residential_street', 'residential_subdivision',
            'residential_barangay', 'residential_city', 'residential_province',
            'residential_region', 'residential_zip_code',
        ];

        $payload = [];

        if (! empty($data['date_of_birth'])) {
            $payload['date_of_birth'] = $data['date_of_birth'];
        }

        foreach ($addressFields as $field) {
            if (! empty($data[$field] ?? null)) {
                $payload[$field] = $data[$field];
            }
        }

        if (empty($payload)) {
            return;
        }

        if (! $personalInfo) {
            // Fresh PDS — surname/first_name are NOT NULL on pds_personal_info.
            // Backfill from the user's on-file name so the row can be created;
            // the employee will refine this later when they complete their PDS.
            [$surname, $firstName] = $this->splitName($user->name);

            $pds->personalInfo()->create(array_merge([
                'surname'    => $surname,
                'first_name' => $firstName,
            ], $payload));
        } else {
            $personalInfo->update($payload);
        }
    }

    private function saveEmergencyContact(User $user, array $data): void
    {
        $payload = array_filter([
            'emergency_contact_name'    => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone'   => $data['emergency_contact_phone'] ?? null,
            'emergency_contact_address' => $data['emergency_contact_address'] ?? null,
        ], fn ($v) => ! empty($v));

        if (empty($payload)) {
            return;
        }

        EmployeeProfile::updateOrCreate(['user_id' => $user->id], $payload);
    }

    /**
     * Users are stored "Lastname, Firstname M.I." — split for the PDS's
     * separate surname/first_name columns. Falls back gracefully if the
     * stored name has no comma (legacy/irregular records).
     */
    private function splitName(string $name): array
    {
        if (str_contains($name, ',')) {
            [$surname, $rest] = array_map('trim', explode(',', $name, 2));

            return [$surname !== '' ? $surname : $name, $rest !== '' ? $rest : $name];
        }

        return [$name, $name];
    }
}
