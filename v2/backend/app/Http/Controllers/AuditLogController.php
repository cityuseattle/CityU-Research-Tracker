<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['actor:id,name,email'])
            ->orderBy('created_at', 'desc');

        // Filter by submission
        if ($request->filled('submission_id')) {
            $query->where('submission_id', $request->submission_id);
        }

        // Filter by actor
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->actor_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Date range — validate before passing to DB to prevent errors from malformed values
        if ($request->filled('from')) {
            if (!strtotime($request->from)) {
                return response()->json(['message' => 'Invalid from date.'], 422);
            }
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            if (!strtotime($request->to)) {
                return response()->json(['message' => 'Invalid to date.'], 422);
            }
            $query->where('created_at', '<=', $request->to);
        }

        // Search in data JSON
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                  ->orWhereRaw("data::text ilike ?", ["%{$search}%"]);
            });
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/audit-logs/actions
     * Distinct action values for filter dropdowns.
     */
    public function actions(): JsonResponse
    {
        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json(['data' => $actions]);
    }
}
