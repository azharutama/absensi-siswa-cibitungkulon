<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'model_type' => ['nullable', 'string', 'in:Siswa,Guru,Kelas,Periode'],
            'activity_type' => ['nullable', 'string', 'in:create,update,delete'],
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,nama')
            ->when($filters['model_type'] ?? null, fn ($query, $type) => $query->where('model_type', $type))
            ->when($filters['activity_type'] ?? null, fn ($query, $type) => $query->where('activity_type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('activity-log.index', compact('logs'));
    }
}
