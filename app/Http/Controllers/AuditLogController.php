<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            $perPage = min((int) ($request->input('per_page') ?: 15), 100);

            if ($request->input('log') === 'activity') {
                $q = ActivityLog::with('user:id,name')
                    ->when($request->input('search'), fn ($x, $s) => $x->where('event', 'like', "%{$s}%"))
                    ->orderByDesc('id');
            } else {
                $q = AuditLog::with('user:id,name')
                    ->when($request->input('action'), fn ($x, $a) => $x->where('action', $a))
                    ->when($request->input('search'), fn ($x, $s) => $x->where('auditable_type', 'like', "%{$s}%"))
                    ->orderByDesc('id');
            }

            return $this->paginate($q->paginate($perPage)->withQueryString());
        }

        return view('admin.audit.index');
    }
}
