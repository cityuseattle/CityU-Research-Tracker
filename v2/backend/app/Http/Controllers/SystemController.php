<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\FeatureFlag;
use App\Models\NotificationTemplate;
use App\Models\OrganizationSetting;
use App\Models\PasswordPolicy;
use App\Models\SsoProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SystemController extends Controller
{
    /**
     * GET /api/system/public
     * Unauthenticated — returns branding for the login page.
     */
    public function publicInfo(): JsonResponse
    {
        $org = OrganizationSetting::current();

        return response()->json([
            'org_name'       => $org->org_name,
            'portal_name'    => $org->portal_name ?? $org->org_name,
            'org_short_name' => $org->org_short_name,
            'logo_url'       => $org->logo_path
                                    ? Storage::url($org->logo_path)
                                    : null,
            'primary_color'  => $org->primary_color,
            'sso_enabled'    => FeatureFlag::isEnabled('sso_enabled'),
        ]);
    }

    /**
     * GET /api/system/organization
     */
    public function getOrganization(): JsonResponse
    {
        return response()->json(OrganizationSetting::current());
    }

    /**
     * PATCH /api/system/organization
     */
    public function updateOrganization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'org_name'                  => ['sometimes', 'string', 'max:255'],
            'portal_name'               => ['sometimes', 'string', 'max:255', 'nullable'],
            'org_short_name'            => ['sometimes', 'string', 'max:100', 'nullable'],
            'primary_color'             => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color'              => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/', 'nullable'],
            'timezone'                  => ['sometimes', 'string', 'timezone:all'],
            'locale'                    => ['sometimes', 'string', 'max:10'],
            'date_format'               => ['sometimes', 'string', 'max:50'],
            'footer_text'               => ['sometimes', 'string', 'nullable'],
            'support_email'             => ['sometimes', 'email', 'nullable'],
            'allow_public_registration' => ['sometimes', 'boolean'],
            'archive_after_days'        => ['sometimes', 'integer', 'min:1', 'nullable'],
        ]);

        $org = OrganizationSetting::current();
        $org->update($data);

        return response()->json($org);
    }

    /**
     * POST /api/system/organization/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $path = $request->file('logo')->store('public/logos');
        $org  = OrganizationSetting::current();
        $org->update(['logo_path' => $path]);

        return response()->json(['logo_url' => Storage::url($path)]);
    }

    /**
     * GET /api/system/feature-flags
     */
    public function getFeatureFlags(): JsonResponse
    {
        return response()->json(
            FeatureFlag::all()->keyBy('key')->map(fn($f) => $f->value)
        );
    }

    /**
     * PATCH /api/system/feature-flags/{key}
     */
    public function updateFeatureFlag(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'boolean'],
        ]);

        $flag = FeatureFlag::where('key', $key)->firstOrFail();
        $flag->update(['value' => $data['value']]);

        return response()->json(['key' => $key, 'value' => $flag->value]);
    }

    /**
     * GET /api/system/password-policy
     */
    public function getPasswordPolicy(): JsonResponse
    {
        return response()->json(PasswordPolicy::find(1));
    }

    /**
     * PATCH /api/system/password-policy
     */
    public function updatePasswordPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_length'               => ['sometimes', 'integer', 'min:8', 'max:128'],
            'require_uppercase'        => ['sometimes', 'boolean'],
            'require_number'           => ['sometimes', 'boolean'],
            'require_special'          => ['sometimes', 'boolean'],
            'expiry_days'              => ['sometimes', 'integer', 'min:0', 'nullable'],
            'history_count'            => ['sometimes', 'integer', 'min:0', 'max:24'],
            'max_login_attempts'       => ['sometimes', 'integer', 'min:1', 'max:20'],
            'lockout_duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'session_timeout_minutes'  => ['sometimes', 'integer', 'min:5'],
            'require_2fa'              => ['sometimes', 'boolean'],
        ]);

        $policy = PasswordPolicy::find(1);
        $policy->update($data);

        return response()->json($policy);
    }

    // ── Email settings ────────────────────────────────────────────────────────

    /**
     * GET /api/system/email
     */
    public function getEmail(): JsonResponse
    {
        return response()->json(EmailSetting::current()->toApiArray());
    }

    /**
     * PATCH /api/system/email
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver'       => ['sometimes', 'string', 'in:log,smtp,ses,sendmail'],
            'host'         => ['sometimes', 'string', 'nullable', 'max:255'],
            'port'         => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'encryption'   => ['sometimes', 'string', 'nullable', 'in:tls,ssl,'],
            'username'     => ['sometimes', 'string', 'nullable', 'max:255'],
            'password_enc' => ['sometimes', 'string', 'nullable'],
            'from_address' => ['sometimes', 'email'],
            'from_name'    => ['sometimes', 'string', 'max:255'],
            'reply_to'     => ['sometimes', 'email', 'nullable'],
            'ses_region'   => ['sometimes', 'string', 'nullable', 'max:50'],
            'ses_key_enc'  => ['sometimes', 'string', 'nullable'],
            'ses_secret_enc' => ['sometimes', 'string', 'nullable'],
        ]);

        $setting = EmailSetting::current();
        $setting->fill($data);
        $setting->is_verified = false; // reset after any config change
        $setting->save();

        return response()->json($setting->toApiArray());
    }

    /**
     * POST /api/system/email/test
     * Sends a test email to the authenticated admin using the stored configuration.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $request->validate([
            'to' => ['sometimes', 'email'],
        ]);

        $to      = $request->input('to', $request->user()->email);
        $setting = EmailSetting::current();
        $config  = $setting->toMailerConfig();

        try {
            // Dynamically set the mailer for this request only
            config(['mail.mailers.dynamic_test' => $config]);
            config(['mail.from.address' => $setting->from_address]);
            config(['mail.from.name'    => $setting->from_name]);

            Mail::mailer('dynamic_test')->raw(
                "This is a test email from the Research Review Portal.\n\nIf you received this, your email configuration is working correctly.",
                function ($message) use ($to, $setting) {
                    $message->to($to)
                            ->subject('Test Email — ' . $setting->from_name);
                }
            );

            $setting->update(['is_verified' => true]);

            return response()->json(['message' => "Test email sent to {$to}."]);
        } catch (\Exception $e) {
            Log::warning('Email test failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Email delivery failed. Check your SMTP configuration.'], 422);
        }
    }

    // ── SSO providers ─────────────────────────────────────────────────────────

    /**
     * GET /api/system/sso
     */
    public function getSsoProviders(): JsonResponse
    {
        $providers = SsoProvider::orderBy('created_at')->get()->map(function ($p) {
            return [
                'id'                  => $p->id,
                'name'                => $p->name,
                'protocol'            => $p->protocol,
                'is_enabled'          => $p->is_enabled,
                'is_default'          => $p->is_default,
                'button_label'        => $p->button_label,
                'button_icon_url'     => $p->button_icon_url,
                'auto_provision_users' => $p->auto_provision_users,
                'default_role'        => $p->default_role,
                'config'              => $p->toApiConfig(),
                'created_at'          => $p->created_at,
            ];
        });

        return response()->json(['data' => $providers]);
    }

    /**
     * POST /api/system/sso
     */
    public function createSsoProvider(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'protocol'             => ['required', 'string', 'in:OIDC,OAUTH2,SAML2'],
            'is_enabled'           => ['sometimes', 'boolean'],
            'is_default'           => ['sometimes', 'boolean'],
            'button_label'         => ['sometimes', 'string', 'nullable', 'max:255'],
            'button_icon_url'      => ['sometimes', 'url', 'nullable'],
            'auto_provision_users' => ['sometimes', 'boolean'],
            'default_role'         => ['sometimes', 'string', 'in:student,reviewer,coordinator,admin'],
            'config'               => ['sometimes', 'array'],
        ]);

        $provider = new SsoProvider();
        $provider->fill(collect($data)->except('config')->toArray());

        if (isset($data['config'])) {
            $provider->setConfigSafely($data['config']);
        }

        // Enforce single default
        if ($provider->is_default) {
            SsoProvider::where('is_default', true)->update(['is_default' => false]);
        }

        $provider->save();

        return response()->json([
            'id'      => $provider->id,
            'name'    => $provider->name,
            'config'  => $provider->toApiConfig(),
        ], 201);
    }

    /**
     * PATCH /api/system/sso/{provider}
     */
    public function updateSsoProvider(Request $request, SsoProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'protocol'             => ['sometimes', 'string', 'in:OIDC,OAUTH2,SAML2'],
            'is_enabled'           => ['sometimes', 'boolean'],
            'is_default'           => ['sometimes', 'boolean'],
            'button_label'         => ['sometimes', 'string', 'nullable', 'max:255'],
            'button_icon_url'      => ['sometimes', 'url', 'nullable'],
            'auto_provision_users' => ['sometimes', 'boolean'],
            'default_role'         => ['sometimes', 'string', 'in:student,reviewer,coordinator,admin'],
            'config'               => ['sometimes', 'array'],
        ]);

        $provider->fill(collect($data)->except('config')->toArray());

        if (isset($data['config'])) {
            $provider->setConfigSafely($data['config']);
        }

        if ($provider->is_default) {
            SsoProvider::where('id', '!=', $provider->id)
                       ->where('is_default', true)
                       ->update(['is_default' => false]);
        }

        $provider->save();

        return response()->json([
            'id'     => $provider->id,
            'name'   => $provider->name,
            'config' => $provider->toApiConfig(),
        ]);
    }

    /**
     * DELETE /api/system/sso/{provider}
     */
    public function deleteSsoProvider(SsoProvider $provider): JsonResponse
    {
        $provider->delete();
        return response()->json(null, 204);
    }

    // ── Notification templates ────────────────────────────────────────────────

    /**
     * GET /api/system/notification-templates
     */
    public function getNotificationTemplates(): JsonResponse
    {
        $templates = NotificationTemplate::orderBy('event_type')->get()->map(function ($t) {
            return [
                'id'         => $t->id,
                'event_type' => $t->event_type,
                'subject'    => $t->subject,
                'body_html'  => $t->body_html,
                'body_text'  => $t->body_text,
                'is_active'  => $t->is_active,
                'variables'  => NotificationTemplate::variablesFor($t->event_type),
                'updated_at' => $t->updated_at,
            ];
        });

        return response()->json(['data' => $templates]);
    }

    /**
     * GET /api/system/notification-templates/{template}
     */
    public function getNotificationTemplate(NotificationTemplate $template): JsonResponse
    {
        return response()->json([
            'id'         => $template->id,
            'event_type' => $template->event_type,
            'subject'    => $template->subject,
            'body_html'  => $template->body_html,
            'body_text'  => $template->body_text,
            'is_active'  => $template->is_active,
            'variables'  => NotificationTemplate::variablesFor($template->event_type),
            'updated_at' => $template->updated_at,
        ]);
    }

    /**
     * PATCH /api/system/notification-templates/{template}
     */
    public function updateNotificationTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'subject'   => ['sometimes', 'string', 'max:500'],
            'body_html' => ['sometimes', 'string'],
            'body_text' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template->update($data);

        return response()->json([
            'id'         => $template->id,
            'event_type' => $template->event_type,
            'subject'    => $template->subject,
            'body_html'  => $template->body_html,
            'body_text'  => $template->body_text,
            'is_active'  => $template->is_active,
            'variables'  => NotificationTemplate::variablesFor($template->event_type),
            'updated_at' => $template->updated_at,
        ]);
    }

    // ── Backup & restore ──────────────────────────────────────────────────────

    /**
     * POST /api/system/backup
     * Triggers spatie/laravel-backup and returns the resulting backup file path.
     */
    public function runBackup(Request $request): JsonResponse
    {
        try {
            \Artisan::call('backup:run', ['--only-db' => false, '--disable-notifications' => true]);
            $output = \Artisan::output();

            return response()->json([
                'message' => 'Backup completed successfully.',
                'output'  => trim($output),
            ]);
        } catch (\Throwable $e) {
            Log::error('Backup failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Backup failed. Please check the server logs.'], 500);
        }
    }

    /**
     * GET /api/system/backup
     * Lists available backup files.
     */
    public function listBackups(): JsonResponse
    {
        $disk  = config('backup.backup.destination.disks', ['local'])[0] ?? 'local';
        $name  = config('backup.backup.name', config('app.name'));
        $files = Storage::disk($disk)->files($name);

        $backups = collect($files)
            ->filter(fn($f) => str_ends_with($f, '.zip'))
            ->map(fn($f) => [
                'filename'   => basename($f),
                'path'       => $f,
                'size_bytes' => Storage::disk($disk)->size($f),
                'created_at' => date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($f)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['data' => $backups]);
    }
}

