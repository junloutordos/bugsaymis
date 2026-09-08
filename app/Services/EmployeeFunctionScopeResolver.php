<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeFunctionScopeResolver
{
    public function resolve(string $scope, array $userIds, array $filters): Collection
    {
        $query = User::employees()->where('status', '<>', 'inactive')->with(['office', 'division']);

        return match ($scope) {
            'all' => $query->get(),
            'selected' => $query->whereIn('id', $userIds)->get(),
            'filtered' => $this->applyFilters($query, $filters)->get(),
            default => collect(),
        };
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['position'])) {
            $query->whereIn('position', $filters['position']);
        }

        if (! empty($filters['role_id'])) {
            $query->whereIn('role_id', $filters['role_id']);
        }

        if (! empty($filters['office_id'])) {
            $query->whereIn('office_id', $filters['office_id']);
        }

        if (! empty($filters['division_id'])) {
            $query->whereIn('division_id', $filters['division_id']);
        }

        if (! empty($filters['emp_category'])) {
            $query->whereIn('emp_category', $filters['emp_category']);
        }

        return $query;
    }
}
