<?php

namespace App\Services;

class TwoFactorService
{
    /**
     * Generate a random 16-character base32 secret key for TOTP
     */
    public function generateSecretKey(): string
    {
        $b32 = '234567QWERTYUIOPASDFGHJKLZXCVBNM';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $b32[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate 8 random recovery codes
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        return $codes;
    }

    /**
     * Verify a 6-digit TOTP code given a secret key
     */
    public function verifyKey(string $secret, string $otp, int $window = 1): bool
    {
        $otp = trim($otp);
        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            return false;
        }

        $timeSlice = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedOtp = $this->calculateOtp($secret, $timeSlice + $i);
            if (hash_equals($calculatedOtp, $otp)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate 6-digit TOTP for a specific time slice
     */
    private function calculateOtp(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        $modulo = $value % 1000000;
        return sprintf('%06d', $modulo);
    }

    /**
     * Decode base32 string to binary
     */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $chars = '234567QWERTYUIOPASDFGHJKLZXCVBNM';
        $bin = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos($chars, $b32[$i]);
            if ($val !== false) {
                $bin .= sprintf('%05b', $val);
            }
        }

        $binary = '';
        for ($j = 0; $j + 8 <= strlen($bin); $j += 8) {
            $binary .= chr(bindec(substr($bin, $j, 8)));
        }
        return $binary;
    }
}
