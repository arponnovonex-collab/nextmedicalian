<?php

namespace App\Services;

class PhoneNormalizationService
{
    /**
     * Normalize a Bangladeshi mobile phone number into canonical 11-digit format: 01XXXXXXXXX
     *
     * Supported formats:
     * - 01712345678
     * - 8801712345678
     * - +8801712345678
     * - 1712345678
     */
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Remove whitespace, dashes, dots, parentheses, and other non-digits except initial +
        $cleaned = trim($phone);
        $cleaned = preg_replace('/[^\d+]/', '', $cleaned);

        // Remove leading '+'
        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        // If starts with '8801', strip '88'
        if (str_starts_with($cleaned, '8801') && strlen($cleaned) === 13) {
            $cleaned = substr($cleaned, 2);
        }

        // If starts with '1' and length is 10, prepend '0'
        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '1')) {
            $cleaned = '0' . $cleaned;
        }

        // Validate canonical format: 11 digits, begins with 013, 014, 015, 016, 017, 018, 019
        if ($this->isValidCanonical($cleaned)) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Validate whether a string is a valid canonical 11-digit Bangladeshi mobile number.
     */
    public function isValidCanonical(string $phone): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', $phone);
    }

    /**
     * Generate student registration number by removing the leading '0' from canonical phone.
     * Example: 01712345678 -> 1712345678
     */
    public function toRegistrationNumber(string $canonicalPhone): string
    {
        if (str_starts_with($canonicalPhone, '0')) {
            return substr($canonicalPhone, 1);
        }

        return $canonicalPhone;
    }

    /**
     * Extract canonical phone and derived registration number from any raw input.
     * Returns null if phone number cannot be parsed/validated.
     *
     * @return array{canonical_phone: string, registration_number: string, username: string}|null
     */
    public function parse(string $rawPhone): ?array
    {
        $canonical = $this->normalize($rawPhone);

        if (! $canonical) {
            return null;
        }

        $regNo = $this->toRegistrationNumber($canonical);

        return [
            'canonical_phone' => $canonical,
            'registration_number' => $regNo,
            'username' => $regNo,
        ];
    }
}
