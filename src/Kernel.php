<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\MicroKernelTrait;

final class Kernel extends BaseKernel implements KernelInterface
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
    }

    protected function configureContainer(LoaderInterface $loader): void
    {
        $configDir = $this->getProjectDir().'/config';
        $loader->load($configDir.'/packages/*.yaml', 'glob');
        $loader->load($configDir.'/addressing_services.yaml');
    }
}
