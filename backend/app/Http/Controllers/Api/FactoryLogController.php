<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FactoryLogController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = \App\Models\FactoryLog::select('*', \Illuminate\Support\Facades\DB::raw('LEAD(created_at) OVER (PARTITION BY entity_type, entity_id ORDER BY created_at ASC) as exit_date'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json($logs);
    }
}
