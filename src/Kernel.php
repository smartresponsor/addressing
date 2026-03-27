<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class Kernel extends BaseKernel implements KernelInterface
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $configDir = $this->getProjectDir().'/config';
        $loader->load($configDir.'/packages/*.yaml', 'glob');
        $loader->load($configDir.'/addressing_services.yaml');
    }

    public function getCacheDir(): string
    {
        $baseDir = $this->runtimeVarDir();

        return $baseDir.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->runtimeVarDir().'/log';
    }

    private function runtimeVarDir(): string
    {
        $customDir = $_SERVER['APP_VAR_DIR'] ?? $_ENV['APP_VAR_DIR'] ?? getenv('APP_VAR_DIR');
        if (is_string($customDir) && '' !== trim($customDir)) {
            return rtrim($customDir, '/');
        }

        return $this->getProjectDir().'/var';
    }
}
