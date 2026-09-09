<?php

namespace App\Support;

class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    public static function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $issuerEncoded = rawurlencode($issuer);
        $accountEncoded = rawurlencode($account);

        return "otpauth://totp/{$issuerEncoded}:{$accountEncoded}?secret={$secret}&issuer={$issuerEncoded}&period=" . self::PERIOD . '&digits=' . self::DIGITS;
    }

    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalized) !== self::DIGITS) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeForCounter($secret, $counter + $offset), $normalized)) {
                return true;
            }
        }

        return false;
    }

    private static function codeForCounter(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');

        $bits = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }

            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $output .= chr(bindec($chunk));
        }

        return $output;
    }
}
