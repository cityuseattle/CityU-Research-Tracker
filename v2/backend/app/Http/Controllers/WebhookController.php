<?php

namespace App\Http\Controllers;

use App\Models\WebhookSubscription;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    // Known event types
    private const VALID_EVENTS = [
        'submission.created',
        'submission.submitted',
        'submission.status_changed',
        'submission.withdrawn',
        'reviewer.assigned',
        'reviewer.decision_submitted',
        'stage.advanced',
        'stage.completed',
        'notification.sent',
    ];

    /**
     * GET /api/admin/webhooks
     */
    public function index(): JsonResponse
    {
        $subs = WebhookSubscription::withCount('deliveries')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($s) => $this->toResource($s));

        return response()->json(['data' => $subs]);
    }

    /**
     * POST /api/admin/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'         => ['required', 'url', 'max:2000'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['string', 'in:' . implode(',', self::VALID_EVENTS)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ]);

        if ($this->isPrivateUrl($data['url'])) {
            return response()->json(['message' => 'Webhook URL must be a publicly reachable address.'], 422);
        }

        $secret = Str::random(40);
        $sub = WebhookSubscription::create([
            'url'         => $data['url'],
            'events'      => $data['events'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'secret_enc'  => encrypt($secret),   // Laravel built-in encryption
        ]);

        return response()->json([
            'data'   => $this->toResource($sub),
            'secret' => $secret,   // Only returned on creation
        ], 201);
    }

    /**
     * GET /api/admin/webhooks/{id}
     */
    public function show(string $id): JsonResponse
    {
        $sub = WebhookSubscription::withCount('deliveries')->findOrFail($id);
        return response()->json(['data' => $this->toResource($sub)]);
    }

    /**
     * PATCH /api/admin/webhooks/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $sub = WebhookSubscription::findOrFail($id);

        $data = $request->validate([
            'url'         => ['sometimes', 'url', 'max:2000'],
            'events'      => ['sometimes', 'array', 'min:1'],
            'events.*'    => ['string', 'in:' . implode(',', self::VALID_EVENTS)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
            'rotate_secret' => ['boolean'],
        ]);

        if (isset($data['url']) && $this->isPrivateUrl($data['url'])) {
            return response()->json(['message' => 'Webhook URL must be a publicly reachable address.'], 422);
        }

        if (!empty($data['rotate_secret'])) {
            $secret = Str::random(40);
            $data['secret_enc'] = encrypt($secret);
            unset($data['rotate_secret']);
        }

        $sub->fill($data)->save();

        $response = ['data' => $this->toResource($sub)];
        if (isset($secret)) {
            $response['secret'] = $secret;
        }

        return response()->json($response);
    }

    /**
     * DELETE /api/admin/webhooks/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        WebhookSubscription::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    /**
     * GET /api/admin/webhooks/{id}/deliveries
     */
    public function deliveries(string $id): JsonResponse
    {
        $sub = WebhookSubscription::findOrFail($id);

        $items = WebhookDelivery::where('webhook_subscription_id', $sub->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/webhooks/events
     * Return list of valid event types.
     */
    public function events(): JsonResponse
    {
        return response()->json(['data' => self::VALID_EVENTS]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns true if the URL resolves to a private/loopback/link-local address (SSRF guard).
     *
     * NOTE: This must be called both at subscription creation/update time AND immediately
     * before firing the HTTP request (in the delivery job), to prevent DNS-rebinding attacks
     * where a domain resolves to a public IP at validation time but a private IP at delivery time.
     */
    private function isPrivateUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true;
        }

        // Block non-HTTPS schemes
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['https', 'http'], true)) {
            return true;
        }

        // If the host is an IP address, check ranges directly
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        // Block common internal/metadata hostnames (prevents DNS rebinding to these targets)
        $lower = strtolower($host);
        $blockedHosts = [
            'localhost',
            'metadata.google.internal',   // GCP metadata
            'instance-data',              // Azure legacy metadata alias
        ];
        if (in_array($lower, $blockedHosts, true)) {
            return true;
        }

        // Block by suffix
        $blockedSuffixes = ['.local', '.internal', '.localdomain', '.localhost'];
        foreach ($blockedSuffixes as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                return true;
            }
        }

        // Resolve the hostname and check resulting IP (FILTER_FLAG_NO_RES_RANGE covers 169.254.0.0/16)
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // DNS resolution failed — treat as private to be safe
            return true;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function toResource(WebhookSubscription $sub): array
    {
        return [
            'id'              => $sub->id,
            'url'             => $sub->url,
            'events'          => $sub->events,
            'description'     => $sub->description,
            'is_active'       => $sub->is_active,
            'has_secret'      => !empty($sub->secret_enc),
            'deliveries_count'=> $sub->deliveries_count ?? 0,
            'created_at'      => $sub->created_at,
            'updated_at'      => $sub->updated_at,
        ];
    }
}
