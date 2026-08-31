<?php

namespace App\Domains\Dashboard\Services;

final class CallGeographyNumberNormalizer
{
    public function normalize(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $candidate = trim($number);

        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^(?:tel|sip):/i', '', $candidate) ?? '';
        $candidate = explode('@', $candidate, 2)[0];
        $candidate = preg_replace('/[\s().-]+/', '', $candidate) ?? '';

        if (str_starts_with($candidate, '00')) {
            $candidate = '+'.mb_substr($candidate, 2);
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $candidate) === 1
            ? $candidate
            : null;
    }
}
