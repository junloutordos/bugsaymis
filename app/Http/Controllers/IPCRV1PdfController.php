<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use App\Services\PerformanceManagement\IPCRV1PdfService;
use Illuminate\Http\Request;

class IPCRV1PdfController extends Controller
{
    public function show(Request $request, int $id, IPCRV1PdfService $pdf)
    {
        $ipcr = EmployeeIPCR::with(['user'])->findOrFail($id);
        $user = $request->user();

        // Same authorization surface as the existing Show pages: any of the
        // 5 view routes (employee-ipcr.show, division-employee-ipcr.show,
        // pmt-ipcr.show, hr-ipcr.show, admin-ipcr.show) already gate on
        // ipcr.view (or role:Administrator) at the route level with no
        // additional per-record ownership check in EmployeeIPCRController::show().
        // This mirrors that: the owner, or anyone holding ipcr.view /
        // ipcr.monitor / ipcr.approve / ipcr.admin, or an Administrator.
        $isOwner = $ipcr->user_id === $user->id;
        $isPrivileged = $user->hasAnyPermission(['ipcr.view', 'ipcr.monitor', 'ipcr.approve', 'ipcr.admin'])
            || $user->hasRole('Administrator');

        abort_unless($isOwner || $isPrivileged, 403, 'You are not authorized to view this IPCR.');

        return $pdf->stream($ipcr);
    }
}
