<?php

namespace Modules\HumanResource\Services;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class QrAttendanceTokenService
{
    public static function generate(array $claims): string
    {
        $payload = [
            'v' => 1,
            'typ' => 'attendance_qr',
            'wid' => (int) ($claims['wid'] ?? 0),
            'iat' => (int) ($claims['iat'] ?? now()->timestamp),
            'exp' => (int) ($claims['exp'] ?? now()->addMinutes((int) config('humanresource.attendance.qr_default_expiry_minutes', 2))->timestamp),
            'uid' => isset($claims['uid']) ? (int) $claims['uid'] : null,
            'jti' => (string) ($claims['jti'] ?? Str::uuid()->toString()),
        ];

        if ($payload['wid'] <= 0) {
            throw ValidationException::withMessages([
                'workplace_id' => 'Invalid workplace for QR token generation.',
            ]);
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encodedPayload = self::base64UrlEncode($json ?: '{}');
        $signature = hash_hmac('sha256', $encodedPayload, self::secret(), true);

        return $encodedPayload . '.' . self::base64UrlEncode($signature);
    }

    public static function verify(string $token): array
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'qr_token' => 'Invalid QR token format.',
            ]);
        }

        [$encodedPayload, $providedSignature] = $parts;

        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, self::secret(), true)
        );

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw ValidationException::withMessages([
                'qr_token' => 'Invalid QR token signature.',
            ]);
        }

        $decodedPayload = self::base64UrlDecode($encodedPayload);
        $payload = json_decode($decodedPayload, true);

        if (!is_array($payload)) {
            throw ValidationException::withMessages([
                'qr_token' => 'Invalid QR token payload.',
            ]);
        }

        if (($payload['typ'] ?? null) !== 'attendance_qr') {
            throw ValidationException::withMessages([
                'qr_token' => 'Unsupported QR token type.',
            ]);
        }

        if ((int) ($payload['wid'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'qr_token' => 'Invalid QR workplace.',
            ]);
        }

        if ((int) ($payload['exp'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR token expired.',
            ]);
        }

        return $payload;
    }

    private static function secret(): string
    {
        $configured = (string) config('humanresource.attendance.qr_token_secret', '');
        if ($configured !== '') {
            return $configured;
        }

        return (string) config('app.key', 'hrm-attendance-qr-secret');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);
        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}

