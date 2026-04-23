<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with('creator:id,name')->latest();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)
                  ->where(function ($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                  });
        }

        $announcements = $query->paginate($request->integer('per_page', 20));

        return response()->json($announcements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:200',
            'body'       => 'required|string|max:5000',
            'type'       => 'in:info,warning,success,danger',
            'target'     => 'string|max:100',
            'expires_at' => 'nullable|date|after:now',
            'broadcast'  => 'boolean',
        ]);

        $announcement = Announcement::create([
            'title'      => $validated['title'],
            'body'       => $validated['body'],
            'type'       => $validated['type'] ?? 'info',
            'target'     => $validated['target'] ?? 'all',
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by' => Auth::id(),
            'is_active'  => true,
        ]);

        // Broadcast as notifications if requested
        if (!empty($validated['broadcast'])) {
            $this->broadcastAnnouncement($announcement);
        }

        return response()->json(['data' => $announcement->load('creator:id,name')], 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title'      => 'sometimes|string|max:200',
            'body'       => 'sometimes|string|max:5000',
            'type'       => 'sometimes|in:info,warning,success,danger',
            'target'     => 'sometimes|string|max:100',
            'expires_at' => 'nullable|date',
            'is_active'  => 'sometimes|boolean',
        ]);

        $announcement->update($validated);

        return response()->json(['data' => $announcement->load('creator:id,name')]);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->json(null, 204);
    }

    public function broadcast(Announcement $announcement)
    {
        $this->broadcastAnnouncement($announcement);
        return response()->json(['message' => 'Announcement broadcasted successfully.']);
    }

    private function broadcastAnnouncement(Announcement $announcement): void
    {
        $query = User::where('is_active', true);

        $target = $announcement->target ?? 'all';

        if (str_starts_with($target, 'role:')) {
            $role = substr($target, 5);
            $query->whereJsonContains('roles', $role);
        }

        $users = $query->get();

        foreach ($users as $user) {
            Notification::create([
                'user_id'     => $user->id,
                'type'        => 'announcement',
                'title'       => $announcement->title,
                'body'        => $announcement->body,
                'data'        => ['announcement_id' => $announcement->id, 'ann_type' => $announcement->type],
                'is_read'     => false,
            ]);
        }
    }
}
