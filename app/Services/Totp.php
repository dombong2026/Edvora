<?php

namespace App\Services;

use InvalidArgumentException;

/** RFC 6238 TOTP using the SHA-1 / six digit / 30 second authenticator profile. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function newSecret(): string
    {
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public function provisioningUri(string $secret, string $account): string
    {
        return 'otpauth://totp/'.rawurlencode('Edvora:'.$account)
            .'?secret='.rawurlencode($secret).'&issuer=Edvora&algorithm=SHA1&digits=6&period=30';
    }

    public function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $msg = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $digest = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($digest[19]) & 15;
        $binary = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Return a never-before-used time step, or null. */
    public function verify(string $secret, string $input, ?int $lastCounter, ?int $time = null): ?int
    {
        if (! preg_match('/^[0-9]{6}$/D', $input)) {
            return null;
        }

        $now = intdiv($time ?? time(), 30);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step >= 0
                && ($lastCounter === null || $step > $lastCounter)
                && hash_equals($this->codeAt($secret, $step), $input)) {
                return $step;
            }
        }

        return null;
    }

    private function base32Decode(string $value): string
    {
        $value = strtoupper(rtrim($value, '='));
        if ($value === '' || preg_match('/[^A-Z2-7]/', $value)) {
            throw new InvalidArgumentException('Invalid authenticator secret.');
        }

        $bits = 0;
        $buffer = 0;
        $decoded = '';
        foreach (str_split($value) as $character) {
            $buffer = ($buffer << 5) | strpos(self::ALPHABET, $character);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $decoded;
    }
}
