<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

/**
 * Geocode config sample (no secrets committed).
 * Env:
 *   GEOCODE_NOMINATIM_URL=https://nominatim.openstreetmap.org
 *   GEOCODE_USER_AGENT=Smartresponsor-Address/1.0 (+https://your.org)
 */
return [
    'nominatim' => [
        'base_url' => getenv('GEOCODE_NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org',
        'user_agent' => getenv('GEOCODE_USER_AGENT') ?: 'Smartresponsor-Address/1.0 (+https://example.org)',
    ],
];
