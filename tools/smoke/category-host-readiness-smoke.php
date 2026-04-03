<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

$host = AddressRuntimeBootstrap::hostReadiness();
$status = $host['ready'] ? 'ready' : 'blocked';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'host_readiness',
    'status' => $status,
    'host' => $host,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

exit($status === 'ready' ? 0 : 2);
