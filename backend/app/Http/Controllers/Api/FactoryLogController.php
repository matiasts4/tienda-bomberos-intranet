<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FactoryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactoryLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FactoryLog::with('user:id,name,email')
            ->select(
                '*',
                DB::raw('LEAD(created_at) OVER (PARTITION BY entity_type, entity_id ORDER BY created_at ASC) as exit_date')
            );

        if ($request->filled('entity_type')) {
            $query->where('entity_type', (string) $request->query('entity_type'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', (int) $request->query('entity_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($logs);
    }
}
