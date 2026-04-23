<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionMessage;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionMessageController extends Controller
{
    /**
     * GET /api/submissions/{id}/messages
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $messages = SubmissionMessage::where('submission_id', $id)
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $this->format($m, $request->user()->id));

        return response()->json(['data' => $messages->values()]);
    }

    /**
     * POST /api/submissions/{id}/messages
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $data = $request->validate([
            'body_html' => 'required|string|max:20000',
        ]);

        // Sanitise HTML to prevent XSS — allow safe rich-text subset
        $clean = $this->purify($data['body_html']);
        if (strip_tags($clean) === '') {
            return response()->json(['message' => 'Message body cannot be empty.'], 422);
        }

        $message = SubmissionMessage::create([
            'submission_id' => $id,
            'sender_id'     => $request->user()->id,
            'body_html'     => $clean,
        ]);

        $message->load('sender:id,name');

        return response()->json($this->format($message, $request->user()->id), 201);
    }

    /**
     * DELETE /api/submissions/{id}/messages/{messageId}
     * Only sender or admin may delete.
     */
    public function destroy(Request $request, string $id, string $messageId): JsonResponse
    {
        $submission = Submission::findOrFail($id);
        $this->authorize('view', $submission);

        $message = SubmissionMessage::where('submission_id', $id)
            ->where('id', $messageId)
            ->firstOrFail();

        $user    = $request->user();
        $isAdmin = in_array('admin', (array) ($user->roles ?? []));

        if ($message->sender_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message->delete();

        return response()->json(null, 204);
    }

    private function format(SubmissionMessage $m, string $currentUserId): array
    {
        return [
            'id'         => $m->id,
            'body_html'  => $m->body_html,
            'sender'     => ['id' => $m->sender->id, 'name' => $m->sender->name],
            'is_mine'    => $m->sender_id === $currentUserId,
            'created_at' => $m->created_at,
        ];
    }

    private function purify(string $html): string
    {
        // HTMLPurifier is a composer dependency of Laravel sanitiser packages;
        // if it is not installed we fall back to a simple strip_tags allow-list.
        if (class_exists(HTMLPurifier::class)) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed',
                'p,br,strong,em,u,s,ul,ol,li,blockquote,a[href|title],span[style],h3,h4');
            $config->set('CSS.AllowedProperties', 'color,background-color');
            $config->set('HTML.TargetBlank', true);
            $config->set('AutoFormat.RemoveEmpty', true);
            return (new HTMLPurifier($config))->purify($html);
        }

        // Fallback: strip to safe tag subset, then neutralise any remaining javascript: hrefs
        $stripped = strip_tags($html,
            '<p><br><strong><em><u><s><ul><ol><li><blockquote><a><span><h3><h4>');
        // Remove javascript: and data: URI schemes from any remaining href/src attributes
        return preg_replace('/\b(href|src)\s*=\s*["\']?\s*(javascript|data):/i', '$1="#"', $stripped);
    }
}
