<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('audit-logs.view');

        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        $logs = $query->latest('created_at')->paginate(30)->withQueryString();
        $entityTypes = AuditLog::select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return view('admin.audit-logs.index', compact('logs', 'entityTypes'));
    }
}
