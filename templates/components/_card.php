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
$baseClass = trim((string)($props['baseClass'] ?? 'panel'));
if ($baseClass === '') {
    $baseClass = 'panel';
}
$classNames = trim($baseClass . ' ' . (string)($props['class'] ?? ''));
$headerClass = $props['headerClass'] ?? $baseClass . '__header';
$bodyClass = $props['bodyClass'] ?? $baseClass . '__body';
$footerClass = $props['footerClass'] ?? $baseClass . '__footer';
$status = $props['status'] ?? null;

$titleClass = $props['titleClass'] ?? $baseClass . '__title';
$subtitleClass = $props['subtitleClass'] ?? $baseClass . '__subtitle';
$eyebrowClass = $props['eyebrowClass'] ?? $baseClass . '__eyebrow';
$badgeClass = $props['badgeClass'] ?? $baseClass . '__badge';
$actionsClass = $props['actionsClass'] ?? $baseClass . '__actions';
$illustrationClass = $props['illustrationClass'] ?? $baseClass . '__illustration';
$metaClass = $props['metaClass'] ?? $baseClass . '__heading';
$titleTag = $props['titleTag'] ?? 'h2';

$isUiCard = str_starts_with($baseClass, 'ui-card');
if ($isUiCard) {
    $titleClass = $props['titleClass'] ?? 'ui-card__title';
    $subtitleClass = $props['subtitleClass'] ?? 'ui-card__subtitle';
    $eyebrowClass = $props['eyebrowClass'] ?? 'ui-card__eyebrow';
    $badgeClass = $props['badgeClass'] ?? 'ui-card__badge';
    $actionsClass = $props['actionsClass'] ?? 'ui-card__actions';
    $illustrationClass = $props['illustrationClass'] ?? 'ui-card__media';
    $metaClass = $props['metaClass'] ?? 'ui-card__meta';
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
    $header .= '<header class="' . htmlspecialchars($headerClass, ENT_QUOTES) . '">';
    if ($isUiCard) {
        $header .= '<div class="' . htmlspecialchars($metaClass, ENT_QUOTES) . '">';
        if ($eyebrow !== '') {
            $header .= '<span class="' . htmlspecialchars($eyebrowClass, ENT_QUOTES) . '">' . htmlspecialchars($eyebrow, ENT_QUOTES) . '</span>';
        }
        if ($title !== '') {
            $header .= sprintf(
                '<%1$s class="%2$s">%3$s</%1$s>',
                preg_replace('/[^a-z0-9:-]+/i', '', (string)$titleTag) ?: 'h2',
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

        if ($illustration) {
            $header .= '<img class="' . htmlspecialchars($illustrationClass, ENT_QUOTES) . '" src="' . htmlspecialchars($illustration, ENT_QUOTES) . '" alt="" loading="lazy" decoding="async">';
        }
    } else {
        $header .= '<div class="' . htmlspecialchars($metaClass, ENT_QUOTES) . '">';
        if ($eyebrow !== '') {
            $header .= '<span class="' . htmlspecialchars($eyebrowClass, ENT_QUOTES) . '">' . htmlspecialchars($eyebrow, ENT_QUOTES) . '</span>';
        }
        if ($title !== '') {
            $header .= '<' . htmlspecialchars($titleTag, ENT_QUOTES) . '>' . htmlspecialchars($title, ENT_QUOTES) . '</' . htmlspecialchars($titleTag, ENT_QUOTES) . '>';
        }
        if ($subtitle !== '') {
            $header .= '<p class="' . htmlspecialchars($subtitleClass, ENT_QUOTES) . '">' . htmlspecialchars($subtitle, ENT_QUOTES) . '</p>';
        }
        if ($badge !== '') {
            $header .= '<span class="' . htmlspecialchars($badgeClass, ENT_QUOTES) . '">' . htmlspecialchars($badge, ENT_QUOTES) . '</span>';
        }
        $header .= '</div>';

        if ($actions !== '') {
            $header .= '<div class="' . htmlspecialchars($actionsClass, ENT_QUOTES) . '">' . $actions . '</div>';
        }

        if ($illustration) {
            $header .= '<img class="' . htmlspecialchars($illustrationClass, ENT_QUOTES) . '" src="' . htmlspecialchars($illustration, ENT_QUOTES) . '" alt="" loading="lazy" decoding="async">';
        }
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
