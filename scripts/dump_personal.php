<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$id = $argv[1] ?? 5;
$row = DB::table('pds_personal_info')->where('pds_id', $id)->first();

echo json_encode($row, JSON_PRETTY_PRINT);
