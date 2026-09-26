<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['action', 'user_id', 'date_from', 'date_to']);

        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::actions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
