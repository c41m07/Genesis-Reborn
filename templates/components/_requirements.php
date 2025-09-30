<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (array $props): string {
    $title = $props['title'] ?? 'Pré-requis';
    $items = $props['items'] ?? [];
    if (!is_array($items) || $items === []) {
        return '';
    }

    $normalizedItems = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = (string)($item['label'] ?? '');
        $current = (int)($item['current'] ?? ($item['currentLevel'] ?? 0));
        $required = (int)($item['required'] ?? ($item['level'] ?? 0));

        $normalizedItems[] = [
            'label' => $label,
            'current' => $current,
            'required' => $required,
        ];
    }

    if ($normalizedItems === []) {
        return '';
    }

    static $sequence = 0;
    $sequence++;

    $panelId = 'requirements-panel-' . $sequence;
    $contentId = $panelId . '-content';
    $isOpen = !empty($props['open']);

    $panelClass = trim('requirements-panel ' . (string)($props['class'] ?? ''));
    $summaryClass = trim('requirements-panel__summary ' . (string)($props['summaryClass'] ?? ''));
    $contentClass = trim('requirements-panel__content ' . (string)($props['contentClass'] ?? ''));
    $listClass = trim('requirements-panel__list building-card__requirements ' . (string)($props['listClass'] ?? ''));

    $iconHtml = '';
    if (isset($props['icon']) && is_string($props['icon'])) {
        $iconHtml = $props['icon'];
    } elseif (isset($props['iconRenderer'], $props['iconName']) && is_callable($props['iconRenderer'])) {
        $options = $props['iconOptions'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        if (!isset($options['baseUrl']) && isset($props['baseUrl'])) {
            $options['baseUrl'] = $props['baseUrl'];
        }

        $iconHtml = (string)$props['iconRenderer']((string)$props['iconName'], $options);
    }

    $titleHtml = htmlspecialchars($title, ENT_QUOTES);
    $summaryIcon = $iconHtml !== ''
        ? '<span class="requirements-panel__icon">' . $iconHtml . '</span>'
        : '';

    $listItemsHtml = '';
    foreach ($normalizedItems as $item) {
        $label = htmlspecialchars($item['label'], ENT_QUOTES);
        $current = format_number($item['current']);
        $required = format_number($item['required']);

        $listItemsHtml .= '<li class="requirements-panel__item">';
        $listItemsHtml .= '<span class="requirements-panel__name building-card__requirement-name">' . $label . '</span>';
        $listItemsHtml .= '<span class="requirements-panel__progress building-card__requirement-progress">(';
        $listItemsHtml .= $current . '/' . $required . ')</span>';
        $listItemsHtml .= '</li>';
    }

    $details = '<details class="' . htmlspecialchars($panelClass, ENT_QUOTES) . '" data-requirements-panel';
    if ($isOpen) {
        $details .= ' open';
    }
    $details .= '>';

    $ariaExpanded = $isOpen ? 'true' : 'false';
    $ariaHidden = $isOpen ? 'false' : 'true';

    $details .= '<summary class="' . htmlspecialchars($summaryClass, ENT_QUOTES) . '" id="' . $panelId . '"';
    $details .= ' data-requirements-summary aria-controls="' . $contentId . '" aria-expanded="' . $ariaExpanded . '">';
    $details .= $summaryIcon;
    $details .= '<span class="requirements-panel__title">' . $titleHtml . '</span>';
    $details .= '<span class="requirements-panel__chevron" aria-hidden="true"></span>';
    $details .= '</summary>';

    $details .= '<div class="' . htmlspecialchars($contentClass, ENT_QUOTES) . '" id="' . $contentId . '"';
    $details .= ' data-requirements-content role="region" aria-labelledby="' . $panelId . '" aria-hidden="' . $ariaHidden . '">';
    $details .= '<ul class="' . htmlspecialchars($listClass, ENT_QUOTES) . '">';
    $details .= $listItemsHtml;
    $details .= '</ul>';
    $details .= '</div>';
    $details .= '</details>';

    return $details;
};
