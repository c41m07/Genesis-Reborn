<?php

/**
 * @param array{
 *     title?: string,
 *     subtitle?: string,
 *     eyebrow?: string,
 *     badge?: string,
 *     actions?: callable|string|null,
 *     illustration?: string|null,
 *     body?: callable|string|null,
 *     footer?: callable|string|null,
 *     status?: string|null,
 *     class?: string|null,
 *     baseClass?: string|null,
 *     headerClass?: string|null,
 *     bodyClass?: string|null,
 *     footerClass?: string|null,
 *     attributes?: array<string, scalar|null>,
 * } $props
 */
return static function (array $props): string {
    $tag = 'article';
    $baseClass = $props['baseClass'] ?? 'panel';
    $classNames = trim($baseClass . ' ' . ($props['class'] ?? ''));
    $headerClass = $props['headerClass'] ?? $baseClass . '__header';
    $bodyClass = $props['bodyClass'] ?? $baseClass . '__body';
    $footerClass = $props['footerClass'] ?? $baseClass . '__footer';
    $status = $props['status'] ?? null;

    if (is_string($status) && $status !== '') {
        $classNames .= ' ' . $status;
    }

    $attributes = $props['attributes'] ?? [];
    if (!is_array($attributes)) {
        $attributes = [];
    }

    $attributes['class'] = trim($classNames);

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

    $renderSlot = static function (callable|string|null $slot): string {
        if ($slot === null) {
            return '';
        }

        if (is_callable($slot)) {
            ob_start();
            $slot();

            return (string) ob_get_clean();
        }

        return (string) $slot;
    };

    $title = $props['title'] ?? '';
    $subtitle = $props['subtitle'] ?? '';
    $eyebrow = $props['eyebrow'] ?? '';
    $badge = $props['badge'] ?? '';
    $actions = $renderSlot($props['actions'] ?? null);
    $illustration = $props['illustration'] ?? null;
    $body = $renderSlot($props['body'] ?? null);
    $footer = $renderSlot($props['footer'] ?? null);

    $header = '';
    if ($title !== '' || $subtitle !== '' || $eyebrow !== '' || $badge !== '' || $actions !== '' || $illustration) {
        $header .= '<header class="' . htmlspecialchars($headerClass, ENT_QUOTES) . '">';
        $header .= '<div class="panel__heading">';
        if ($eyebrow !== '') {
            $header .= '<span class="panel__eyebrow">' . htmlspecialchars($eyebrow, ENT_QUOTES) . '</span>';
        }
        if ($title !== '') {
            $header .= '<h2>' . htmlspecialchars($title, ENT_QUOTES) . '</h2>';
        }
        if ($subtitle !== '') {
            $header .= '<p class="panel__subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES) . '</p>';
        }
        if ($badge !== '') {
            $header .= '<span class="panel__badge">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>';
        }
        $header .= '</div>';

        if ($actions !== '') {
            $header .= '<div class="panel__actions">' . $actions . '</div>';
        }

        if ($illustration) {
            $header .= '<img class="panel__illustration" src="' . htmlspecialchars($illustration, ENT_QUOTES) . '" alt="">';
        }

        $header .= '</header>';
    }

    $output = sprintf('<%s%s>', $tag, $attributeString);
    $output .= $header;
    if ($body !== '') {
        $output .= '<div class="' . htmlspecialchars($bodyClass, ENT_QUOTES) . '">' . $body . '</div>';
    }
    if ($footer !== '') {
        $output .= '<footer class="' . htmlspecialchars($footerClass, ENT_QUOTES) . '">' . $footer . '</footer>';
    }
    $output .= sprintf('</%s>', $tag);

    return $output;
};
