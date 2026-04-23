<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /**
     * GET /api/programs
     */
    public function index(Request $request): JsonResponse
    {
        $query = Program::with('programDirector:id,name,email')
            ->when($request->boolean('active_only', true), fn($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(fn($q2) => $q2->where('name', 'ilike', $term)
                    ->orWhere('school', 'ilike', $term));
            });

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->orderBy('name')->get()]);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $programs = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $programs->items(),
            'meta' => [
                'current_page' => $programs->currentPage(),
                'last_page'    => $programs->lastPage(),
                'per_page'     => $programs->perPage(),
                'total'        => $programs->total(),
            ],
        ]);
    }

    /**
     * POST /api/programs
     * Admin or coordinator.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Program::class);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'school'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'          => ['sometimes', 'nullable', 'string'],
            'program_director_id'  => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'group_id'             => ['sometimes', 'nullable', 'uuid', 'exists:groups,id'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $program = Program::create($data);
        $program->load('programDirector:id,name,email');

        return response()->json(['data' => $program], 201);
    }

    /**
     * PATCH /api/programs/{program}
     * Admin or coordinator.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        $this->authorize('update', $program);

        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'school'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'          => ['sometimes', 'nullable', 'string'],
            'program_director_id'  => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'group_id'             => ['sometimes', 'nullable', 'uuid', 'exists:groups,id'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $program->update($data);
        $program->load('programDirector:id,name,email');

        return response()->json(['data' => $program]);
    }

    /**
     * DELETE /api/programs/{program}
     * Admin only — deactivates instead of deleting.
     */
    public function destroy(string $id): JsonResponse
    {
        $program = Program::findOrFail($id);
        $this->authorize('delete', $program);

        $program->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
