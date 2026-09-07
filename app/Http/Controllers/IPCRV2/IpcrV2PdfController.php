<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2PdfService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;

class IpcrV2PdfController extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function show(Request $request, int $id, IpcrV2PdfService $pdf)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);
        $user = $request->user();

        $isOwner = $record->user_id === $user->id;
        $isBroadlyPrivileged = $user->hasAnyRole(['OCD', 'PMT', 'HR', 'Administrator']);
        $isDivisionChiefInScope = $user->hasRole('DivisionChief') && $record->user?->division_id === $user->division_id;

        abort_unless(
            $isOwner || $isBroadlyPrivileged || $isDivisionChiefInScope || $this->workflow->canManage($user, $record),
            403,
            'You are not authorized to view this IPCR V2.'
        );

        return $pdf->stream($record);
    }
}
