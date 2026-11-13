<?php
/*
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunicationSettings
{
    public const SMS_DEFAULTS = [
        'sms_enabled' => false,
        'sms_provider' => 'arkesel',
        'sms_sender_id' => 'Evolex',
        'sms_api_key' => '',
    ];

    public static function validate(array $payload): array
    {
        // Keep the existing grouped settings API, but reject group/key reassignment.
        foreach ($payload as $group => &$values) {
            if (!is_array($values) || !in_array($group, ['general', 'email', 'sms', 'notifications', 'livestock', 'integrations', 'security', 'procurement'], true)) {
                throw ValidationException::withMessages([$group => 'Use a supported settings group.']);
            }
            unset($values['mail_password_configured'], $values['sms_api_key_configured'], $values['resend_api_key_configured']);
            $known = Setting::where('group', $group)->pluck('key')->all();
            if ($group === 'email') $known = ['mail_mailer', 'resend_api_key', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name', 'mail_reply_to', 'smtp_auth', 'send_welcome_email', 'email_footer_text'];
            if ($group === 'sms') $known = array_keys(self::SMS_DEFAULTS);
            foreach ($values as $key => $value) {
                if (!in_array($key, $known, true) || (in_array($key, Setting::SECRETS, true) && $group !== (in_array($key, ['mail_password', 'resend_api_key'], true) ? 'email' : 'sms'))) {
                    throw ValidationException::withMessages(["$group.$key" => 'Unknown setting or incorrect settings group.']);
                }
                if (!is_scalar($value) && $value !== null) {
                    throw ValidationException::withMessages(["$group.$key" => 'Setting must be a scalar value.']);
                }
            }
        }
        unset($values);

        Validator::make($payload, [
            'email.mail_mailer' => ['sometimes', Rule::in(['smtp', 'log', 'resend'])],
            'email.resend_api_key' => 'sometimes|nullable|string|max:2000',
            'email.mail_host' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'email.mail_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'email.mail_username' => 'sometimes|nullable|string|max:255',
            'email.mail_password' => 'sometimes|nullable|string|max:2000',
            'email.mail_encryption' => ['sometimes', Rule::in(['tls', 'ssl', 'none'])],
            'email.mail_from_address' => 'sometimes|required|email|max:255',
            'email.mail_from_name' => 'sometimes|required|string|max:255',
            'email.mail_reply_to' => 'sometimes|nullable|email|max:255',
            'email.smtp_auth' => 'sometimes|boolean',
            'email.send_welcome_email' => 'sometimes|boolean',
            'email.email_footer_text' => 'sometimes|nullable|string|max:2000',
            'sms.sms_enabled' => 'sometimes|boolean',
            'sms.sms_provider' => ['sometimes', Rule::in(['arkesel'])],
            'sms.sms_sender_id' => ['sometimes', 'required', 'string', 'max:11', 'regex:/^[a-zA-Z0-9 ]+$/'],
            'sms.sms_api_key' => 'sometimes|nullable|string|max:2000',
        ])->validate();

        // A blank field means keep the saved secret; never save a display mask.
        foreach (['email' => ['mail_password', 'resend_api_key'], 'sms' => ['sms_api_key']] as $group => $keys) {
            foreach ($keys as $key) {
                if (isset($payload[$group]) && (empty($payload[$group][$key]) || preg_match('/^[•*]+$/u', $payload[$group][$key] ?? ''))) {
                    unset($payload[$group][$key]);
                }
            }
        }
        return $payload;
    }
}*/



namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunicationSettings
{
    public const SMS_DEFAULTS = [
        'sms_enabled' => false,
        'sms_provider' => 'arkesel',
        'sms_sender_id' => 'Evolex',
        'sms_api_key' => '',
    ];

    /** Supported settings groups from the UI and seeder. */
    public const ALLOWED_GROUPS = [
        'general',
        'email',
        'sms',
        'notifications',
        'livestock',
        'integrations',
        'security',
        'procurement',
    ];

    public static function validate(array $payload): array
    {
        foreach ($payload as $group => &$values) {
            if (! is_array($values)) {
                throw ValidationException::withMessages([$group => 'Use a supported settings group.']);
            }

            if (! in_array($group, self::ALLOWED_GROUPS, true)) {
                throw ValidationException::withMessages([$group => 'Use a supported settings group.']);
            }

            // Strip UI-only flags that are not real settings
            foreach (array_keys($values) as $key) {
                if (str_ends_with($key, '_configured') || str_ends_with($key, '_preview')) {
                    unset($values[$key]);
                }
            }

            // Known keys for this group (from DB). Email/SMS keep explicit allow-lists
            // so secrets stay in the correct group.
            $known = Setting::where('group', $group)->pluck('key')->all();

            if ($group === 'email') {
                $known = array_values(array_unique(array_merge($known, [
                    'mail_mailer',
                    'resend_api_key',
                    'mail_host',
                    'mail_port',
                    'mail_username',
                    'mail_password',
                    'mail_encryption',
                    'mail_from_address',
                    'mail_from_name',
                    'mail_reply_to',
                    'smtp_auth',
                    'send_welcome_email',
                    'email_footer_text',
                ])));
            }

            if ($group === 'sms') {
                $known = array_values(array_unique(array_merge($known, array_keys(self::SMS_DEFAULTS))));
            }

            foreach ($values as $key => $value) {
                // Skip unknown keys instead of hard-failing (prevents UI/DB drift from blocking saves)
                if (! in_array($key, $known, true)) {
                    unset($values[$key]);
                    continue;
                }

                // Secrets must stay in their correct group
                if (in_array($key, Setting::SECRETS, true)) {
                    $expectedGroup = in_array($key, ['mail_password', 'resend_api_key'], true) ? 'email' : 'sms';
                    if ($group !== $expectedGroup) {
                        throw ValidationException::withMessages([
                            "$group.$key" => 'Unknown setting or incorrect settings group.',
                        ]);
                    }
                }

                if (! is_scalar($value) && $value !== null) {
                    throw ValidationException::withMessages([
                        "$group.$key" => 'Setting must be a scalar value.',
                    ]);
                }
            }
        }
        unset($values);

        Validator::make($payload, [
            'email.mail_mailer' => ['sometimes', Rule::in(['smtp', 'log', 'resend'])],
            'email.resend_api_key' => 'sometimes|nullable|string|max:2000',
            'email.mail_host' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'email.mail_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'email.mail_username' => 'sometimes|nullable|string|max:255',
            'email.mail_password' => 'sometimes|nullable|string|max:2000',
            'email.mail_encryption' => ['sometimes', Rule::in(['tls', 'ssl', 'none'])],
            'email.mail_from_address' => 'sometimes|required|email|max:255',
            'email.mail_from_name' => 'sometimes|required|string|max:255',
            'email.mail_reply_to' => 'sometimes|nullable|email|max:255',
            'email.smtp_auth' => 'sometimes|boolean',
            'email.send_welcome_email' => 'sometimes|boolean',
            'email.email_footer_text' => 'sometimes|nullable|string|max:2000',
            'sms.sms_enabled' => 'sometimes|boolean',
            'sms.sms_provider' => ['sometimes', Rule::in(['arkesel'])],
            'sms.sms_sender_id' => ['sometimes', 'required', 'string', 'max:11', 'regex:/^[a-zA-Z0-9 ]+$/'],
            'sms.sms_api_key' => 'sometimes|nullable|string|max:2000',
        ])->validate();

        // A blank field means keep the saved secret; never save a display mask.
        foreach (['email' => ['mail_password', 'resend_api_key'], 'sms' => ['sms_api_key']] as $group => $keys) {
            foreach ($keys as $key) {
                if (isset($payload[$group]) && (empty($payload[$group][$key]) || preg_match('/^[•*]+$/u', $payload[$group][$key] ?? ''))) {
                    unset($payload[$group][$key]);
                }
            }
        }

        // Drop empty groups after filtering
        return array_filter($payload, fn ($v) => is_array($v) && count($v) > 0);
    }
}