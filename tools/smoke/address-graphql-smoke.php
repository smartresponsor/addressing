<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'graphql_surface',
    'status' => 'not_applicable',
    'reason' => 'The current runtime exposes HTTP form and REST-style address endpoints only. No GraphQL surface is wired in the current slice.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
