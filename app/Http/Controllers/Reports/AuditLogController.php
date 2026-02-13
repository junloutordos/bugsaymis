<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administrator');
    }
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->has('user_id') && $request->query('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->has('action') && $request->query('action')) {
            $query->where('action', $request->query('action'));
        }

        $auditLogs = $query->paginate(25)->withQueryString();

        return Inertia::render('Reports/AuditLogs/Index', [
            'auditLogs' => $auditLogs,
        ]);
    }
}
