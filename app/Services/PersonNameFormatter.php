<?php

namespace App\Services;

use App\Models\User;

class PersonNameFormatter
{
    public function givenName(User $user): string
    {
        $user->loadMissing('pds.personalInfo');
        $firstName = trim((string) $user->pds?->personalInfo?->first_name);

        if ($firstName !== '') {
            return preg_replace('/\s+/', ' ', $firstName);
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) $user->name));
        if ($name === '') {
            return '';
        }

        if (str_contains($name, ',')) {
            [, $givenNames] = array_map('trim', explode(',', $name, 2));

            return preg_split('/\s+/', $givenNames)[0] ?? '';
        }

        return preg_split('/\s+/', $name)[0] ?? '';
    }

    public function formal(User $user): string
    {
        $user->loadMissing('pds.personalInfo');
        $info = $user->pds?->personalInfo;

        if ($info?->first_name && $info?->surname) {
            return $this->assemble($info->first_name, $info->middle_name, $info->surname, $info->name_ext);
        }

        return $this->fromDisplayName($user->name);
    }

    private function assemble(string $first, ?string $middle, string $last, ?string $suffix = null): string
    {
        $middle = $this->cleanPart($middle);
        $suffix = $this->cleanPart($suffix);

        $initial = $middle !== null ? mb_substr($middle, 0, 1).'.' : null;

        return mb_strtoupper(implode(' ', array_filter([trim($first), $initial, trim($last), $suffix])));
    }

    /**
     * Some PDS records have placeholder text ("N/A", "None", etc.) typed
     * into optional fields like middle name or suffix instead of being left
     * blank — strip those so they don't get printed as part of the name.
     */
    private function cleanPart(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));

        return in_array($normalized, ['NA', 'NONE'], true) ? null : $value;
    }

    private function fromDisplayName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (str_contains($name, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $name, 2));
            $parts = preg_split('/\s+/', $rest);
            $middle = count($parts) > 1 ? array_pop($parts) : null;

            return $this->assemble(implode(' ', $parts), $middle, $last);
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) < 2) {
            return mb_strtoupper($name);
        }
        $last = array_pop($parts);
        $middle = count($parts) > 1 ? array_pop($parts) : null;

        return $this->assemble(implode(' ', $parts), $middle, $last);
    }
}
