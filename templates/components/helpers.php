<?php

declare(strict_types=1);

if (!function_exists('format_duration')) {
    function format_duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = sprintf('%d h', $hours);
        }
        if ($minutes > 0) {
            $parts[] = sprintf('%d min', $minutes);
        }
        if (($hours === 0 && $minutes === 0) || $remainingSeconds > 0) {
            $parts[] = sprintf('%d s', $remainingSeconds);
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('format_number')) {
    /**
     * Format a number using compact notation for large values.
     */
    function format_number(int|float $value): string
    {
        if (!is_numeric($value)) {
            $number = 0.0;
        } else {
            $number = (float) $value;
        }

        $absValue = abs($number);
        $suffix = '';
        $divisor = 1.0;
        $thresholds = [
            1_000_000_000 => 'b',
            1_000_000 => 'm',
            1_000 => 'k',
        ];

        foreach ($thresholds as $limit => $symbol) {
            if ($absValue >= $limit) {
                $suffix = $symbol;
                $divisor = (float) $limit;

                break;
            }
        }

        if ($suffix === '') {
            $hasFraction = abs($number - round($number)) >= 1e-9;
            $decimals = $hasFraction ? 2 : 0;

            return number_format($number, $decimals, ',', ' ');
        }

        $scaled = $absValue / $divisor;
        $scaled = floor($scaled * 100.0) / 100.0;
        if ($number < 0) {
            $scaled *= -1;
        }

        $isInteger = abs($scaled - round($scaled)) < 1e-9;
        $decimals = $isInteger ? 0 : 2;

        return number_format($scaled, $decimals, ',', ' ') . $suffix;
    }
}

if (!function_exists('asset_url')) {
    /**
     * Build an absolute asset URL based on the provided base URL.
     */
    function asset_url(string $path, ?string $baseUrl = null): string
    {
        $trimmedPath = trim($path);
        if ($trimmedPath === '') {
            return '';
        }

        // Absolute URLs or protocol-relative paths are returned as-is.
        if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $trimmedPath) === 1) {
            return $trimmedPath;
        }

        if ($trimmedPath[0] === '#') {
            return $trimmedPath;
        }

        $prefix = '';
        if (is_string($baseUrl) && $baseUrl !== '') {
            $prefix = rtrim($baseUrl, '/');
        }

        if ($trimmedPath[0] !== '/') {
            $trimmedPath = '/' . $trimmedPath;
        }

        return $prefix !== '' ? $prefix . $trimmedPath : $trimmedPath;
    }
}
