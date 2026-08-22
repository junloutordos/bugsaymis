<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Support\Collection;

class NoticeAudienceResolver
{
    /**
     * @return array{users: Collection<int, User>, students: Collection<int, Student>, parents: Collection<int, ParentContact>}
     */
    public function resolve(string $audience): array
    {
        return match ($audience) {
            'employees' => [
                'users'    => $this->employees(),
                'students' => collect(),
                'parents'  => collect(),
            ],
            'students' => [
                'users'    => collect(),
                'students' => $this->students(),
                'parents'  => collect(),
            ],
            'parents' => [
                'users'    => collect(),
                'students' => collect(),
                'parents'  => $this->parents(),
            ],
            'all' => [
                'users'    => $this->employees(),
                'students' => $this->students(),
                'parents'  => $this->parents(),
            ],
            default => [
                'users' => collect(), 'students' => collect(), 'parents' => collect(),
            ],
        };
    }

    private function employees(): Collection
    {
        return User::employees()->where('status', '<>', 'inactive')->get();
    }

    private function students(): Collection
    {
        // Only students who actually have an AtlasGo account can receive
        // anything (no other delivery channel exists for students).
        return Student::whereHas('credential')->get();
    }

    private function parents(): Collection
    {
        return ParentContact::where('status', 'active')->get();
    }
}
