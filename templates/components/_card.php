<?php

declare(strict_types=1);

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
$tag = isset($props['tag']) && is_string($props['tag']) && $props['tag'] !== ''
    ? $props['tag']
    : 'article';

$baseClass = trim((string)($props['baseClass'] ?? 'card'));
if ($baseClass === '' || $baseClass === 'ui-card') {
    $baseClass = 'card';
}

$isBootstrapCard = $baseClass === 'card';

$classNames = trim($baseClass . ' ' . (string)($props['class'] ?? ''));
$status = $props['status'] ?? null;

if ($isBootstrapCard) {
    $headerClass = $props['headerClass']
        ?? 'card-header d-flex flex-column flex-lg-row gap-4 align-items-start align-items-lg-center';
    $bodyClass = $props['bodyClass'] ?? 'card-body d-flex flex-column gap-4';
    $footerClass = $props['footerClass']
        ?? 'card-footer d-flex flex-column flex-lg-row gap-3 align-items-start align-items-lg-center';
    $titleClass = $props['titleClass'] ?? 'card-title m-0';
    $subtitleClass = $props['subtitleClass'] ?? 'card-subtitle text-secondary mb-0';
    $eyebrowClass = $props['eyebrowClass'] ?? 'card-eyebrow text-uppercase text-secondary-emphasis fw-semibold';
    $badgeClass = $props['badgeClass'] ?? 'badge card-badge ms-lg-auto';
    $actionsClass = $props['actionsClass'] ?? 'd-flex flex-wrap gap-2 ms-lg-auto';
    $illustrationClass = $props['illustrationClass'] ?? 'card-illustration';
    $metaClass = $props['metaClass'] ?? 'card-meta d-flex flex-column gap-2';
    $titleTag = $props['titleTag'] ?? 'h2';
} else {
    $headerClass = $props['headerClass'] ?? $baseClass . '__header';
    $bodyClass = $props['bodyClass'] ?? $baseClass . '__body';
    $footerClass = $props['footerClass'] ?? $baseClass . '__footer';
    $titleClass = $props['titleClass'] ?? $baseClass . '__title';
    $subtitleClass = $props['subtitleClass'] ?? $baseClass . '__subtitle';
    $eyebrowClass = $props['eyebrowClass'] ?? $baseClass . '__eyebrow';
    $badgeClass = $props['badgeClass'] ?? $baseClass . '__badge';
    $actionsClass = $props['actionsClass'] ?? $baseClass . '__actions';
    $illustrationClass = $props['illustrationClass'] ?? $baseClass . '__illustration';
    $metaClass = $props['metaClass'] ?? $baseClass . '__heading';
    $titleTag = $props['titleTag'] ?? 'h2';
}

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

        $attributeKey = preg_replace('/[^a-z0-9_-]+/i', '', (string)$key);
        if ($attributeKey === '') {
            continue;
        }

        $attributeString .= sprintf(' %s="%s"', $attributeKey, htmlspecialchars((string)$value, ENT_QUOTES));
    }

    $renderSlot = static function (callable|string|null $slot): string {
        if ($slot === null) {
            return '';
        }

        if (is_callable($slot)) {
            ob_start();
            $slot();

            return (string)ob_get_clean();
        }

        return (string)$slot;
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
        $computedHeaderClass = $headerClass;
        if ($illustration) {
            $computedHeaderClass = trim($computedHeaderClass . ' ' . ($isBootstrapCard ? 'card-header--with-media' : 'panel__header--with-media'));
        }

        $header .= '<header class="' . htmlspecialchars($computedHeaderClass, ENT_QUOTES) . '">';
        if ($illustration) {
            $header .= '<img class="' . htmlspecialchars($illustrationClass, ENT_QUOTES) . '" src="' . htmlspecialchars($illustration, ENT_QUOTES) . '" alt="" loading="lazy" decoding="async">';
        }

        $header .= '<div class="' . htmlspecialchars($metaClass, ENT_QUOTES) . '">';
        if ($eyebrow !== '') {
            $header .= '<span class="' . htmlspecialchars($eyebrowClass, ENT_QUOTES) . '">' . htmlspecialchars($eyebrow, ENT_QUOTES) . '</span>';
        }
        if ($title !== '') {
            $tagName = preg_replace('/[^a-z0-9:-]+/i', '', (string)$titleTag) ?: 'h2';
            $header .= sprintf(
                '<%1$s class="%2$s">%3$s</%1$s>',
                $tagName,
                htmlspecialchars($titleClass, ENT_QUOTES),
                htmlspecialchars($title, ENT_QUOTES)
            );
        }
        if ($subtitle !== '') {
            $header .= '<p class="' . htmlspecialchars($subtitleClass, ENT_QUOTES) . '">' . htmlspecialchars($subtitle, ENT_QUOTES) . '</p>';
        }
        $header .= '</div>';

        if ($badge !== '') {
            $header .= '<span class="' . htmlspecialchars($badgeClass, ENT_QUOTES) . '">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>';
        }

        if ($actions !== '') {
            $header .= '<div class="' . htmlspecialchars($actionsClass, ENT_QUOTES) . '">' . $actions . '</div>';
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
