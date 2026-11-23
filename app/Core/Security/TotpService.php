<?php

namespace App\Core\Security;

use Illuminate\Support\Str;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $bits = '';

        foreach (str_split($raw) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $secret .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $secret;
    }

    public function getProvisioningUri(string $issuer, string $email, string $secret): string
    {
        $label = rawurlencode($issuer.':'.$email);
        $issuerParam = rawurlencode($issuer);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";
    }

    public function verifyCode(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $normalized = preg_replace('/\s+/', '', $code);
        if (!is_string($normalized) || !preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $timeSlice = (int) floor(($timestamp ?? time()) / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForSlice($secret, $timeSlice + $offset), $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function codeForSlice(string $secret, int $timeSlice): string
    {
        $key = $this->decodeBase32($secret);
        $timeBytes = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5)).'-'.strtoupper(Str::random(5));
        }

        return $codes;
    }

    protected function decodeBase32(string $secret): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($clean) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
