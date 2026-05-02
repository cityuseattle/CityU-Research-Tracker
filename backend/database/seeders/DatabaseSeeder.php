<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // â”€â”€ Organization settings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        DB::table('organization_settings')->insertOrIgnore([[
            'id'             => 1,
            'org_name'       => 'City University Research Review Portal',
            'org_short_name' => 'CityU RRP',
            'primary_color'  => '#1E40AF',
            'timezone'       => 'Asia/Hong_Kong',
            'locale'         => 'en',
            'date_format'    => 'YYYY-MM-DD',
            'support_email'  => 'rrp-support@example.com',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]]);

        // â”€â”€ Email settings (log driver for dev) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        DB::table('email_settings')->insertOrIgnore([[
            'id'           => 1,
            'driver'       => 'log',
            'from_address' => 'noreply@cityurrp.example.com',
            'from_name'    => 'CityU Research Review Portal',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]]);

        // â”€â”€ Password policy â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        DB::table('password_policy')->insertOrIgnore([[
            'id'                       => 1,
            'min_length'               => 12,
            'require_uppercase'        => true,
            'require_number'           => true,
            'require_special'          => true,
            'expiry_days'              => null,
            'history_count'            => 5,
            'max_login_attempts'       => 5,
            'lockout_duration_minutes' => 15,
            'session_timeout_minutes'  => 480,
            'require_2fa'              => false,
        ]]);

        // â”€â”€ Feature flags â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $flags = [
            ['key' => 'sso_enabled',             'value' => false, 'description' => 'Enable SSO login button'],
            ['key' => 'public_registration',      'value' => false, 'description' => 'Allow self-registration'],
            ['key' => 'webhooks_enabled',         'value' => false, 'description' => 'Enable webhook delivery'],
            ['key' => 'allow_appeals',            'value' => true,  'description' => 'Allow appeal requests'],
            ['key' => 'allow_meetings',           'value' => true,  'description' => 'Enable meeting scheduling'],
            ['key' => 'realtime_notifications',   'value' => false, 'description' => 'Enable WebSocket (Reverb) notifications'],
            ['key' => 'reviewer_pool_enabled',    'value' => true,  'description' => 'Enable reviewer pool management'],
            ['key' => 'audit_log_enabled',        'value' => true,  'description' => 'Log all audit events'],
            ['key' => 'file_storage_s3',          'value' => false, 'description' => 'Use S3 for file storage (else local)'],
        ];

        foreach ($flags as $flag) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $flag['key']],
                $flag
            );
        }

        // â”€â”€ Notification templates â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $templates = [
            [
                'event_type' => 'SUBMISSION_RECEIVED',
                'subject'    => 'Submission Received: {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>Your submission "<strong>{{submission_title}}</strong>" has been received and is under review.</p>',
                'body_text'  => "Dear {{user_name}},\n\nYour submission \"{{submission_title}}\" has been received.",
            ],
            [
                'event_type' => 'STAGE_ASSIGNED',
                'subject'    => 'Review Assignment: {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>You have been assigned to review "<strong>{{submission_title}}</strong>".</p><p>Due date: {{due_date}}</p>',
                'body_text'  => "Dear {{user_name}},\n\nYou have been assigned to review \"{{submission_title}}\".\nDue: {{due_date}}",
            ],
            [
                'event_type' => 'REVISION_REQUIRED',
                'subject'    => 'Revision Required: {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>Your submission "<strong>{{submission_title}}</strong>" requires revision. Please review the comments and resubmit.</p>',
                'body_text'  => "Dear {{user_name}},\n\nYour submission \"{{submission_title}}\" requires revision.",
            ],
            [
                'event_type' => 'SUBMISSION_ACCEPTED',
                'subject'    => 'Submission Accepted: {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>Congratulations! Your submission "<strong>{{submission_title}}</strong>" has been accepted.</p>',
                'body_text'  => "Dear {{user_name}},\n\nCongratulations! Your submission \"{{submission_title}}\" has been accepted.",
            ],
            [
                'event_type' => 'SUBMISSION_REJECTED',
                'subject'    => 'Submission Outcome: {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>After careful review, your submission "<strong>{{submission_title}}</strong>" was not accepted at this time.</p>',
                'body_text'  => "Dear {{user_name}},\n\nYour submission \"{{submission_title}}\" was not accepted.",
            ],
            [
                'event_type' => 'STAGE_OVERDUE',
                'subject'    => 'Action Required: Review Overdue for {{submission_title}}',
                'body_html'  => '<p>Dear {{user_name}},</p><p>Your review of "<strong>{{submission_title}}</strong>" is now overdue. Please submit your review at your earliest convenience.</p>',
                'body_text'  => "Dear {{user_name}},\n\nYour review of \"{{submission_title}}\" is overdue.",
            ],
            [
                'event_type' => 'APPEAL_SUBMITTED',
                'subject'    => 'Appeal Received for {{submission_title}}',
                'body_html'  => '<p>An appeal has been submitted for "<strong>{{submission_title}}</strong>" and is awaiting coordinator review.</p>',
                'body_text'  => "An appeal has been submitted for \"{{submission_title}}\".",
            ],
        ];

        foreach ($templates as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['event_type' => $template['event_type']],
                array_merge($template, [
                    'id'         => (string) Str::uuid(),
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // â”€â”€ Stage templates â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $stageTemplates = [
            [
                'name'              => 'Single Blind Peer Review',
                'description'       => 'Two anonymous reviewers assess the submission',
                'stage_role_label'  => 'Peer Reviewer',
                'execution_type'    => 'PARALLEL',
                'approval_strategy' => 'ALL',
                'min_approvals'     => 2,
                'is_anonymous'      => true,
                'due_days'          => 14,
                'is_system'         => true,
            ],
            [
                'name'              => 'Supervisor Approval',
                'description'       => 'Supervisor approves before the submission proceeds',
                'stage_role_label'  => 'Supervisor',
                'execution_type'    => 'SEQUENTIAL',
                'approval_strategy' => 'ALL',
                'min_approvals'     => 1,
                'is_anonymous'      => false,
                'due_days'          => 7,
                'is_system'         => true,
            ],
            [
                'name'              => 'Ethics Board Review',
                'description'       => 'Ethics committee reviews for human/animal subjects',
                'stage_role_label'  => 'Ethics Reviewer',
                'execution_type'    => 'PARALLEL',
                'approval_strategy' => 'MAJORITY',
                'min_approvals'     => 2,
                'is_anonymous'      => false,
                'due_days'          => 21,
                'is_system'         => true,
            ],
            [
                'name'              => 'Coordinator Final Check',
                'description'       => 'Coordinator performs final eligibility check',
                'stage_role_label'  => 'Coordinator',
                'execution_type'    => 'SEQUENTIAL',
                'approval_strategy' => 'ALL',
                'min_approvals'     => 1,
                'is_anonymous'      => false,
                'due_days'          => 3,
                'is_system'         => true,
            ],
        ];

        foreach ($stageTemplates as $tmpl) {
            DB::table('stage_templates')->insertOrIgnore(
                array_merge($tmpl, [
                    'id'         => (string) Str::uuid(),
                    'decision_options' => json_encode([
                        ['value' => 'APPROVE', 'label' => 'Approve', 'outcome' => 'APPROVED'],
                        ['value' => 'REQUEST_CHANGES', 'label' => 'Request Changes', 'outcome' => 'REVISION'],
                        ['value' => 'REJECT', 'label' => 'Reject', 'outcome' => 'REJECTED'],
                    ]),
                    'visibility_config' => '{}',
                    'escalation_config' => '{}',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])
            );
        }
        // Users, programs, and groups are created via the admin UI after first login.
        // The emergency admin account is provisioned automatically by User::syncEmergencyAdmin().
    }
}
