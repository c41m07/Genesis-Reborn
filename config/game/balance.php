<?php

declare(strict_types=1);

return [
    'minimum_speed_modifier' => 0.01,
    'maximum_discount' => 0.95,
    'tick_duration_seconds' => 3600,
    'rounding_tolerance' => 0.000001,
    'rounding' => [
        'resources' => 'floor',
        'capacities' => 'round',
        'production' => 'round',
        'energy' => [
            'stats' => 'round',
            'available' => 'floor',
        ],
    ],
];
