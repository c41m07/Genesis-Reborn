<?php

return static function (string $name, array $options = []): string {
    $baseUrl = $options['baseUrl'] ?? '';
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

    $href = rtrim($baseUrl, '/') . '/assets/svg/sprite.svg#icon-' . htmlspecialchars($name, ENT_QUOTES);
    $svg = sprintf('<svg%s><use href="%s"></use></svg>', $attributeString, $href);

    if ($label) {
        $svg .= sprintf('<span class="visually-hidden">%s</span>', htmlspecialchars((string) $label, ENT_QUOTES));
    }

    return $svg;
};
