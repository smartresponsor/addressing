<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__.'/../tools/support/AddressRuntimeBootstrap.php';

return new Application(AddressRuntimeBootstrap::bootKernel());
