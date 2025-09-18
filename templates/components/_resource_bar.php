<?php

/**
 * @param array<string, array{label: string, value: int|float, perHour?: int|float, hint?: string, trend?: string}> $resources
 * @param array{baseUrl?: string, class?: string, showRates?: bool} $options
 */
return static function (array $resources, array $options = []): string {
    if ($resources === []) {
        return '';
    }

    $baseUrl = $options['baseUrl'] ?? '';
    $showRates = (bool) ($options['showRates'] ?? true);
    $class = trim('resource-bar ' . ($options['class'] ?? ''));

    $items = '';
    foreach ($resources as $key => $data) {
        if (!is_array($data)) {
            continue;
        }

        $label = $data['label'] ?? ucfirst((string) $key);
        $value = (float) ($data['value'] ?? 0);
        $perHour = $data['perHour'] ?? null;
        $hint = $data['hint'] ?? null;
        $trend = $data['trend'] ?? null;

        $valueDisplay = number_format((float) $value);
        $rateDisplay = '';
        $rateClass = '';
        if ($showRates && $perHour !== null) {
            $rate = (float) $perHour;
            $ratePrefix = ($key !== 'energy' && $rate > 0) ? '+' : '';
            $rateDisplay = $ratePrefix . number_format($rate) . '/h';
            $rateClass = $rate >= 0 ? 'is-positive' : 'is-negative';
        }

        if (is_string($trend) && $trend !== '') {
            $rateClass = $trend;
        }

        $icon = sprintf(
            '<svg class="icon icon-sm" aria-hidden="true"><use href="%s"></use></svg>',
            rtrim($baseUrl, '/') . '/assets/svg/sprite.svg#icon-' . htmlspecialchars((string) $key, ENT_QUOTES)
        );

        $hintMarkup = '';
        if ($hint) {
            $hintMarkup = sprintf('<span class="resource-meter__hint">%s</span>', htmlspecialchars((string) $hint, ENT_QUOTES));
        }

        $rateMarkup = '';
        if ($rateDisplay !== '') {
            $rateMarkup = sprintf('<span class="resource-meter__rate %s">%s</span>', htmlspecialchars($rateClass, ENT_QUOTES), htmlspecialchars($rateDisplay, ENT_QUOTES));
        }

        $items .= '<div class="resource-meter" role="group" aria-label="' . htmlspecialchars((string) $label, ENT_QUOTES) . '">';
        $items .= '<div class="resource-meter__icon">' . $icon . '</div>';
        $items .= '<div class="resource-meter__details">';
        $items .= '<span class="resource-meter__label">' . htmlspecialchars((string) $label, ENT_QUOTES) . '</span>';
        $items .= '<div class="resource-meter__values">';
        $items .= '<span class="resource-meter__value">' . htmlspecialchars($valueDisplay, ENT_QUOTES) . '</span>';
        $items .= $rateMarkup;
        $items .= '</div>';
        $items .= $hintMarkup;
        $items .= '</div>';
        $items .= '</div>';
    }

    return '<div class="' . htmlspecialchars($class, ENT_QUOTES) . '">' . $items . '</div>';
};
