<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class GenerateTechnicalDocs extends Command
{
    protected $signature = 'docs:generate {--output= : Full path for the PDF output file}';

    protected $description = 'Generate a comprehensive technical documentation PDF for Atlas';

    public function handle(): int
    {
        // Large schema (217 tables) + routes (957) needs extra memory and PCRE headroom
        ini_set('memory_limit', '512M');
        ini_set('pcre.backtrack_limit', 10000000);
        ini_set('pcre.recursion_limit', 10000000);

        $output = $this->option('output')
            ?? base_path('output/pdf/atlas-technical-documentation-edition-2.0-'.date('Y-m-d').'.pdf');

        File::ensureDirectoryExists(dirname($output));

        $this->info('Collecting data…');

        $data = [
            'generated_at' => now()->format('F j, Y \a\t g:i A'),
            'document' => [
                'edition' => '2.0',
                'document_id' => 'ATLAS-TD-2026-002',
                'snapshot_date' => now()->format('F j, Y'),
                'software_version' => '1.0.0',
                'rights_holder' => 'Philippine Science High School - Caraga Region Campus in Butuan City',
                'classification' => 'Controlled Registration Copy',
            ],
            'stats' => $this->getStats(),
            'routes' => $this->getRoutes(),
            'schema' => $this->getSchema(),
            'permissions' => $this->getPermissions(),
        ];

        $this->info('Rendering HTML…');

        $html = view('docs.technical', $data)->render();

        $this->info('Generating PDF (this may take a minute for large schemas)…');

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontDirs = $defaultConfig['fontDir'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => sys_get_temp_dir(),
            'fontDir' => array_merge($defaultFontDirs, []),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'],
        ]);

        $mpdf->SetTitle('Atlas Technical Documentation - Edition 2.0');
        $mpdf->SetAuthor('Philippine Science High School - Caraga Region Campus in Butuan City');
        $mpdf->SetSubject('Comprehensive technical documentation and copyright registration deposit copy for the Atlas campus management information system');
        $mpdf->SetKeywords('Atlas, PSHS-CRC, technical documentation, software architecture, campus management information system');
        $mpdf->SetCreator('Atlas docs:generate');

        $mpdf->SetHTMLHeader('
            <table width="100%" style="font-size:8pt; color:#94a3b8; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">
                <tr>
                    <td>Atlas - Technical Documentation · Edition 2.0</td>
                    <td style="text-align:right;">ATLAS-TD-2026-002 · Controlled Copy</td>
                </tr>
            </table>
        ');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:8pt; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:4px;">
                <tr>
                    <td>© 2026 PSHS-CRC · ATLAS-TD-2026-002</td>
                    <td style="text-align:right;">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        // Split CSS from body for better mPDF memory handling on large documents
        preg_match('/<style>(.*?)<\/style>/s', $html, $cssMatch);
        $css = $cssMatch[1] ?? '';
        $body = preg_replace('/<style>.*?<\/style>/s', '', $html);

        $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($body, HTMLParserMode::HTML_BODY);
        $mpdf->Output($output, 'F');

        $this->newLine();
        $this->info('✓ PDF saved to: '.$output);
        $this->line('  Size: '.round(filesize($output) / 1024).' KB');
        $this->line('  SHA-256: '.hash_file('sha256', $output));

        return self::SUCCESS;
    }

    // ── Data collectors (same logic as TechnicalDocsController) ──────────────

    private function getStats(): array
    {
        $db = DB::getDatabaseName();

        return [
            'tables' => DB::select('SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$db])[0]->c,
            'routes' => count(Route::getRoutes()->getRoutes()),
            'permissions' => $this->getPermissionNames()->count(),
        ];
    }

    private function getRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->getName() && ! str_starts_with($r->uri(), '_'))
            ->map(function ($r) {
                $action = $r->getActionName();
                $parts = explode('@', $action);
                $ctrl = count($parts) === 2
                    ? str_replace('App\\Http\\Controllers\\', '', $parts[0]).'@'.$parts[1]
                    : $action;
                $mw = collect($r->gatherMiddleware())
                    ->filter(fn ($m) => in_array($m, ['auth', 'auth:sanctum']) || str_starts_with($m, 'role:') || str_starts_with($m, 'permission:'))
                    ->values()->implode(', ');

                return [
                    'methods' => implode('|', array_diff($r->methods(), ['HEAD'])),
                    'uri' => $r->uri(),
                    'name' => $r->getName(),
                    'controller' => $ctrl,
                    'auth' => $mw,
                ];
            })
            ->sortBy('uri')
            ->values()
            ->toArray();
    }

    private function getSchema(): array
    {
        $db = DB::getDatabaseName();
        $columns = DB::select('
            SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE,
                   c.COLUMN_KEY, c.EXTRA,
                   k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
            FROM information_schema.COLUMNS c
            LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                ON  k.TABLE_SCHEMA = c.TABLE_SCHEMA
                AND k.TABLE_NAME   = c.TABLE_NAME
                AND k.COLUMN_NAME  = c.COLUMN_NAME
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
            WHERE c.TABLE_SCHEMA = ?
            ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION
        ', [$db]);

        $tables = [];
        foreach ($columns as $col) {
            $t = $col->TABLE_NAME;
            if (! isset($tables[$t])) {
                $tables[$t] = ['name' => $t, 'columns' => []];
            }
            $tables[$t]['columns'][] = [
                'name' => $col->COLUMN_NAME,
                'type' => $col->COLUMN_TYPE,
                'nullable' => $col->IS_NULLABLE === 'YES',
                'key' => $col->COLUMN_KEY,
                'extra' => $col->EXTRA,
                'fk' => $col->REFERENCED_TABLE_NAME ? "{$col->REFERENCED_TABLE_NAME}.{$col->REFERENCED_COLUMN_NAME}" : '',
            ];
        }

        return array_values($tables);
    }

    private function getPermissions(): array
    {
        $perms = $this->getPermissionNames();
        $grouped = [];
        foreach ($perms as $p) {
            $group = explode('.', $p)[0];
            $grouped[$group][] = $p;
        }

        return $grouped;
    }

    /**
     * Build the documentation catalogue from both the live database and the
     * canonical permission seeder. This keeps a newly provisioned or lightly
     * seeded documentation database from silently omitting production RBAC
     * definitions, while retaining permissions introduced by migrations.
     */
    private function getPermissionNames()
    {
        $databasePermissions = Schema::hasTable('permissions')
            ? DB::table('permissions')->pluck('name')
            : collect();
        $seederSource = File::get(database_path('seeders/PermissionsSeeder.php'));

        preg_match_all("/'name'\\s*=>\\s*'([^']+)'/", $seederSource, $matches);

        $routePermissions = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
            ->flatMap(fn ($middleware) => preg_split('/[|,]/', substr($middleware, strlen('permission:'))))
            ->map(fn ($permission) => trim($permission));

        return $databasePermissions
            ->merge($matches[1] ?? [])
            ->merge($routePermissions)
            ->filter(fn ($name) => is_string($name) && str_contains($name, '.'))
            ->unique()
            ->sort()
            ->values();
    }
}
