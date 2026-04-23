<?php

namespace App\Http\Controllers;

use App\Models\DocumentAnnotation;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAnnotationController extends Controller
{
    /**
     * GET /api/submissions/{id}/annotations
     *
     * List annotations for a specific document file (version + filename required).
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $request->validate([
            'version'  => 'required|integer|min:1',
            'filename' => 'required|string',
        ]);

        $annotations = DocumentAnnotation::where('submission_id', $id)
            ->where('version_number', $request->integer('version'))
            ->where('filename', $request->string('filename'))
            ->with('annotator:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($a) => $this->format($a, $request->user()->id));

        return response()->json(['data' => $annotations->values()]);
    }

    /**
     * POST /api/submissions/{id}/annotations
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $data = $request->validate([
            'version_number' => 'required|integer|min:1',
            'filename'       => 'required|string|max:500',
            'quote'          => 'required|string|max:1000',
            'comment'        => 'required|string|max:5000',
            'position_hint'  => 'nullable|string|max:100',
        ]);

        $annotation = DocumentAnnotation::create([
            ...$data,
            'submission_id' => $id,
            'annotator_id'  => $request->user()->id,
        ]);

        $annotation->load('annotator:id,name');

        return response()->json($this->format($annotation, $request->user()->id), 201);
    }

    /**
     * DELETE /api/submissions/{id}/annotations/{annotationId}
     */
    public function destroy(Request $request, string $id, string $annotationId): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $annotation = DocumentAnnotation::where('submission_id', $id)
            ->where('id', $annotationId)
            ->firstOrFail();

        $user = $request->user();
        $isAdmin = is_array($user->roles)
            ? in_array('admin', $user->roles)
            : in_array('admin', json_decode($user->roles ?? '[]', true));

        if ($annotation->annotator_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'You can only delete your own annotations.'], 403);
        }

        $annotation->delete();

        return response()->json(null, 204);
    }

    private function format(DocumentAnnotation $a, string $currentUserId): array
    {
        return [
            'id'            => $a->id,
            'quote'         => $a->quote,
            'comment'       => $a->comment,
            'position_hint' => $a->position_hint,
            'annotator'     => [
                'id'   => $a->annotator->id,
                'name' => $a->annotator->name,
            ],
            'is_mine'    => $a->annotator_id === $currentUserId,
            'created_at' => $a->created_at,
        ];
    }
}
