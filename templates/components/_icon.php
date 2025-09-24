<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (string $name, array $options = []): string {
    $label = $options['label'] ?? null;
    $classes = trim('icon ' . ($options['class'] ?? ''));
    $size = $options['size'] ?? null;
    if (is_string($size) && $size !== '') {
        $classes .= ' icon--' . preg_replace('/[^a-z0-9_-]+/i', '', $size);
    }

    $attributes = $options['attributes'] ?? [];
    if (!is_array($attributes)) {
        $attributes = [];
    }

    $attributes['class'] = trim($classes);
    if ($label) {
        $attributes['role'] = $attributes['role'] ?? 'img';
        $attributes['aria-hidden'] = 'false';
    } else {
        $attributes['aria-hidden'] = $attributes['aria-hidden'] ?? 'true';
    }

    $attributeString = '';
    foreach ($attributes as $key => $value) {
        if (!is_scalar($value) && $value !== null) {
            continue;
        }

        $attributeKey = preg_replace('/[^a-z0-9_-]+/i', '', (string) $key);
        if ($attributeKey === '') {
            continue;
        }

        $attributeString .= sprintf(' %s="%s"', $attributeKey, htmlspecialchars((string) $value, ENT_QUOTES));
    }

    $iconName = (string) $name;
    $baseUrl = $options['baseUrl'] ?? null;
    $href = asset_url('assets/svg/sprite.svg#icon-' . $iconName, is_string($baseUrl) ? $baseUrl : null);
    $svg = sprintf('<svg%s><use href="%s"></use></svg>', $attributeString, htmlspecialchars($href, ENT_QUOTES));

    if ($label) {
        $svg .= sprintf('<span class="visually-hidden">%s</span>', htmlspecialchars((string) $label, ENT_QUOTES));
    }

    return $svg;
};
