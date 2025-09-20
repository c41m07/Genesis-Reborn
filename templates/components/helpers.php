<?php

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
