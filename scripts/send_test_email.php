<?php

// Bootstrap Laravel application for a one-off script
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FacilityRequest;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\FacilityRequestCreatedMail;

echo "Looking up latest facility request...\n";
$fr = FacilityRequest::latest()->first();
if (! $fr) {
    echo "No facility request found — creating a test one.\n";
    $fr = FacilityRequest::create([
        'requestor' => 'Test Requestor',
        'unit' => 'Test Division',
        'activity' => 'Test Activity',
        'purpose' => 'Test purpose',
        'date_start' => now()->toDateString(),
        'date_end' => now()->toDateString(),
        'time_start' => '09:00',
        'time_end' => '11:00',
        'venue' => ['Main Hall'],
        'equipment' => ['Chairs','Projector'],
        'date_filed' => now(),
        'status' => 'Pending',
        'email' => 'test@example.com'
    ]);
}

// try to find division chief email from division relation or divisions table
$chiefEmail = null;
if ($fr->unit) {
    // match division by name
    $div = Division::where('division_name', $fr->unit)->first();
    if ($div && $div->division_chief_id) {
        $dc = User::find($div->division_chief_id);
        if ($dc && $dc->email) $chiefEmail = $dc->email;
    }
}

echo "Found chief email: " . ($chiefEmail ?? 'none') . "\n";

if (! $chiefEmail) {
    echo "No division chief email found. Aborting send.\n";
    exit(1);
}

try {
    $approveUrl = url('/facility-requests');
    Mail::to($chiefEmail)->send(new FacilityRequestCreatedMail($fr, $approveUrl, null));
    echo "Mail queued/sent to $chiefEmail\n";
} catch (Throwable $e) {
    echo "Send failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
