<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OutboundMailService
{
    /** Return the actual transport result, never claim inbox delivery. */
    public function send(string $recipient, string $subject, string $body): string
    {
        $driver = Setting::get('mail_mailer', config('mail.default', 'smtp'));
        if (!in_array($driver, ['smtp', 'log'], true)) {
            throw new RuntimeException('Unsupported mail driver. Select SMTP or Log in Settings.');
        }
        $auth = (bool) Setting::get('smtp_auth', true);
        $host = Setting::get('mail_host', config('mail.mailers.smtp.host', 'smtp.gmail.com'));
        $port = (int) Setting::get('mail_port', config('mail.mailers.smtp.port', 465));
        $encryption = Setting::get('mail_encryption', 'ssl');
        $username = $auth ? Setting::get('mail_username', config('mail.mailers.smtp.username')) : null;
        $password = $auth ? Setting::get('mail_password', config('mail.mailers.smtp.password')) : null;

        if ($password !== null) {
            $password = trim((string) $password);
            // Google 16-character App Passwords are generated and often pasted with spaces (e.g. "njek wolc izks ageg").
            // Automatically strip spaces for Gmail or if it matches the 16-letter pattern.
            if (str_contains(strtolower($host ?? ''), 'gmail') ||
                str_contains(strtolower($host ?? ''), 'google') ||
                preg_match('/^[a-z]{4}\s+[a-z]{4}\s+[a-z]{4}\s+[a-z]{4}$/i', $password)) {
                $password = str_replace(' ', '', $password);
            }
        }

        $scheme = ($encryption === 'ssl' || $port === 465) ? 'smtps' : 'smtp';

        config(['mail.mailers.communications' => $driver === 'log' ? ['transport' => 'log'] : [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'require_tls' => $encryption === 'tls' && $scheme === 'smtp',
            'auto_tls' => $encryption !== 'none',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'timeout' => 20,
        ]]);
        // Long-lived workers must not reuse a transport with old credentials.
        Mail::purge('communications');
        $footer = Setting::get('email_footer_text', '');
        Mail::mailer('communications')->raw($body.($footer ? "\n\n".$footer : ''), function (Message $mail) use ($recipient, $subject) {
            $fromAddress = Setting::get('mail_from_address', config('mail.from.address', 'evolexfoods@gmail.com'));
            $fromName = Setting::get('mail_from_name', config('mail.from.name', 'Evolex Operations Hub'));
            $mail->to($recipient)->subject($subject)->from($fromAddress, $fromName);
            if ($reply = Setting::get('mail_reply_to')) $mail->replyTo($reply);
        });
        return $driver === 'log' ? 'logged' : 'accepted';
    }
}
