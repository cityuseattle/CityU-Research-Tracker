<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomRoleController extends Controller
{
    public function index()
    {
        $roles = CustomRole::with('creator:id,name')->orderBy('name')->get();
        return response()->json(['data' => $roles]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:80|unique:custom_roles,name',
            'description' => 'nullable|string|max:300',
            'color'       => 'nullable|string|max:20',
        ]);

        $role = CustomRole::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color'       => $validated['color'] ?? '#6366f1',
            'created_by'  => Auth::id(),
        ]);

        return response()->json(['data' => $role->load('creator:id,name')], 201);
    }

    public function update(Request $request, CustomRole $customRole)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:80|unique:custom_roles,name,' . $customRole->id,
            'description' => 'nullable|string|max:300',
            'color'       => 'nullable|string|max:20',
        ]);

        $customRole->update($validated);

        return response()->json(['data' => $customRole->load('creator:id,name')]);
    }

    public function destroy(CustomRole $customRole)
    {
        $customRole->delete();
        return response()->json(null, 204);
    }
}
