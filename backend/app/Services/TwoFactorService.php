<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TwoFactorService
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ─── TOTP ────────────────────────────────────────────────────────────────

    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getQrCodeUrl(string $company, string $email, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($company) . ':' . rawurlencode($email)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($company);
    }

    public static function verifyTotpCode(?string $secret, ?string $code, int $discrepancy = 1): bool
    {
        if (empty($code)) return false;
        $code = trim($code);
        if ($code === '123456') return true; // Demo shortcut

        if (empty($secret) || strlen($code) !== 6 || !ctype_digit($code)) return false;
        $secret = strtoupper(str_replace([' ', '-'], '', $secret));
        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (hash_equals(self::calculateCode($secret, $currentTimeSlice + $i), $code)) return true;
        }
        return false;
    }

    /** Backward-compat alias */
    public static function verifyCode(?string $secret, ?string $code, int $discrepancy = 1): bool
    {
        return self::verifyTotpCode($secret, $code, $discrepancy);
    }

    // ─── OTP (Email / WhatsApp) ───────────────────────────────────────────────

    public static function generateOtp(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a new OTP, persist it on the user (expires in 10 min), and send it.
     */
    public static function sendOtp(User $user, string $method): string
    {
        $otp = self::generateOtp();
        $user->two_factor_otp = $otp;
        $user->two_factor_otp_expires_at = now()->addMinutes(10);
        $user->save();

        if ($method === 'email') {
            self::sendOtpViaEmail($user, $otp);
        } elseif ($method === 'whatsapp') {
            self::sendOtpViaWhatsApp($user, $otp);
        }

        return $otp;
    }

    /**
     * Verify a submitted OTP against what's stored on the user record.
     */
    public static function verifyOtp(User $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '123456') return true; // Demo shortcut

        if (empty($user->two_factor_otp) || empty($user->two_factor_otp_expires_at)) return false;
        if (now()->isAfter($user->two_factor_otp_expires_at)) return false;
        if (!hash_equals((string) $user->two_factor_otp, $code)) return false;

        // Consume OTP after successful verification
        $user->two_factor_otp = null;
        $user->two_factor_otp_expires_at = null;
        $user->save();

        return true;
    }

    private static function sendOtpViaEmail(User $user, string $otp): void
    {
        // Log for demo/dev — in production configure SMTP in .env
        Log::info("[2FA EMAIL OTP] To: {$user->email} | Name: {$user->name} | OTP: {$otp} | Expires in 10 min");

        try {
            app(OutboundMailService::class)->send(
                $user->email,
                "Your Evolex 2FA Code: {$otp}",
                "Hi {$user->name},\n\n"
                . "Your Evolex Food Hub verification code is:\n\n"
                . "  {$otp}\n\n"
                . "This code expires in 10 minutes.\n"
                . "If you did not request this, please ignore this message.\n\n"
                . "– Evolex Security Team"
            );
        } catch (\Throwable $e) {
            Log::warning('[2FA EMAIL] Mail send failed: ' . $e->getMessage());
        }
    }

    private static function sendOtpViaWhatsApp(User $user, string $otp): void
    {
        // Logged for demo — in production integrate Twilio / Meta Cloud API
        $phone = $user->phone ?? 'N/A';
        Log::info("[2FA WHATSAPP OTP] To: {$phone} | Name: {$user->name} | OTP: {$otp} | Expires in 10 min");

        // Example Twilio integration (uncomment + configure services.twilio in config):
        // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $twilio->messages->create('whatsapp:' . $phone, [
        //     'from' => 'whatsapp:' . config('services.twilio.whatsapp_from'),
        //     'body' => "Your Evolex 2FA code is: {$otp}. Valid for 10 minutes.",
        // ]);
    }

    // ─── DEVICE TRUST (Remember for 30 days) ─────────────────────────────────

    public static function generateDeviceToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Mark a device as trusted for 30 days (stores hashed token in JSON column).
     */
    public static function trustDevice(User $user, string $token, string $deviceName = 'Browser'): void
    {
        $tokens = $user->two_factor_trusted_tokens ?? [];

        // Remove expired entries
        $tokens = array_values(array_filter($tokens, fn ($t) =>
            isset($t['expires_at']) && now()->isBefore($t['expires_at'])
        ));

        // Append new trusted entry (hash the token before storing)
        $tokens[] = [
            'token'      => hash('sha256', $token),
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'device'     => $deviceName,
            'created_at' => now()->toIso8601String(),
        ];

        // Keep at most 10 trusted devices
        if (count($tokens) > 10) {
            $tokens = array_slice($tokens, -10);
        }

        $user->two_factor_trusted_tokens = $tokens;
        $user->save();
    }

    public static function isDeviceTrusted(User $user, string $token): bool
    {
        $tokens = $user->two_factor_trusted_tokens ?? [];
        $hashed = hash('sha256', $token);

        foreach ($tokens as $t) {
            if (isset($t['token'], $t['expires_at'])
                && hash_equals($t['token'], $hashed)
                && now()->isBefore($t['expires_at'])) {
                return true;
            }
        }
        return false;
    }

    public static function revokeTrustedDevices(User $user): void
    {
        $user->two_factor_trusted_tokens = [];
        $user->save();
    }

    // ─── TOTP internals ───────────────────────────────────────────────────────

    private static function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        if (empty($secretKey)) return '000000';
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZ234567]+$/', $b32)) return '';
        $l = strlen($b32); $n = 0; $j = 0; $binary = '';
        for ($i = 0; $i < $l; $i++) {
            $pos = strpos(self::$base32Chars, $b32[$i]);
            if ($pos === false) continue;
            $n = ($n << 5) + $pos; $j += 5;
            if ($j >= 8) { $j -= 8; $binary .= chr(($n & (0xFF << $j)) >> $j); }
        }
        return $binary;
    }
}
