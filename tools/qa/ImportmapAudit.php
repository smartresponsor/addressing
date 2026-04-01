<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$console = $projectRoot.'/bin/console';

if (!is_file($console)) {
    fwrite(STDOUT, "[importmap:audit] skipped: bin/console not found; importmap is not configured for this package.\n");
    exit(0);
}

$cmd = sprintf('php %s list --raw 2>/dev/null', escapeshellarg($console));
exec($cmd, $listOutput, $listCode);
if ($listCode !== 0 || !in_array('importmap:audit', $listOutput, true)) {
    fwrite(STDOUT, "[importmap:audit] skipped: command importmap:audit is unavailable in current Symfony app.\n");
    exit(0);
}

passthru(sprintf('php %s importmap:audit', escapeshellarg($console)), $auditCode);
exit($auditCode);
