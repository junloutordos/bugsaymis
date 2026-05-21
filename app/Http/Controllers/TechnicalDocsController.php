<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class TechnicalDocsController extends Controller
{
    public function index()
    {
        return Inertia::render('TechDocs/Index', [
            'routes'      => $this->getRoutes(),
            'schema'      => $this->getSchema(),
            'permissions' => $this->getPermissions(),
            'stats'       => $this->getStats(),
        ]);
    }

    private function getRoutes(): array
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($r) => ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'sanctum'))
            ->map(function ($r) {
                $action  = $r->getActionName();
                $parts   = explode('@', $action);
                $ctrl    = count($parts) === 2
                    ? str_replace('App\\Http\\Controllers\\', '', $parts[0]) . '@' . $parts[1]
                    : $action;

                return [
                    'methods'    => array_values(array_diff($r->methods(), ['HEAD'])),
                    'uri'        => $r->uri(),
                    'name'       => $r->getName() ?? '',
                    'controller' => $ctrl,
                    'middleware' => array_values($r->gatherMiddleware()),
                ];
            })
            ->values()
            ->toArray();

        return $routes;
    }

    private function getSchema(): array
    {
        $db = DB::getDatabaseName();

        $columns = DB::select("
            SELECT
                c.TABLE_NAME,
                c.COLUMN_NAME,
                c.COLUMN_TYPE,
                c.IS_NULLABLE,
                c.COLUMN_KEY,
                c.COLUMN_DEFAULT,
                c.EXTRA,
                c.COLUMN_COMMENT,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME
            FROM information_schema.COLUMNS c
            LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                ON  k.TABLE_SCHEMA = c.TABLE_SCHEMA
                AND k.TABLE_NAME   = c.TABLE_NAME
                AND k.COLUMN_NAME  = c.COLUMN_NAME
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
            WHERE c.TABLE_SCHEMA = ?
            ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION
        ", [$db]);

        $tables = [];
        foreach ($columns as $col) {
            $t = $col->TABLE_NAME;
            if (! isset($tables[$t])) {
                $tables[$t] = ['name' => $t, 'columns' => []];
            }
            $tables[$t]['columns'][] = [
                'name'       => $col->COLUMN_NAME,
                'type'       => $col->COLUMN_TYPE,
                'nullable'   => $col->IS_NULLABLE === 'YES',
                'key'        => $col->COLUMN_KEY,
                'default'    => $col->COLUMN_DEFAULT,
                'extra'      => $col->EXTRA,
                'fk_table'   => $col->REFERENCED_TABLE_NAME,
                'fk_column'  => $col->REFERENCED_COLUMN_NAME,
            ];
        }

        return array_values($tables);
    }

    private function getPermissions(): array
    {
        $perms = DB::table('permissions')->orderBy('name')->pluck('name');

        $grouped = [];
        foreach ($perms as $p) {
            $group = explode('.', $p)[0];
            if (! isset($grouped[$group])) {
                $grouped[$group] = ['group' => $group, 'permissions' => []];
            }
            $grouped[$group]['permissions'][] = $p;
        }

        return array_values($grouped);
    }

    private function getStats(): array
    {
        $db = DB::getDatabaseName();

        return [
            'tables'      => DB::select("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$db])[0]->c,
            'routes'      => count(Route::getRoutes()->getRoutes()),
            'permissions' => DB::table('permissions')->count(),
            'users'       => DB::table('users')->where('status', '<>', 'inactive')->count(),
        ];
    }
}
