<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pds;

$pds = Pds::find(5);
$personal = [
    'name_ext' => 'jr',
    'citizenship_filipino' => 'no',
    'citizenship_dual' => 'yes',
    'citizenship_dual_type' => 'By Birth',
    'citizenship_dual_country' => 'asd',
];
$res = $pds->personalInfo()->updateOrCreate(['pds_id' => $pds->id], $personal);
echo json_encode($res, JSON_PRETTY_PRINT);
