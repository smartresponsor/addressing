<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
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

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
    }

    /**
     * @throws \Exception
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        if (!$container->hasParameter('kernel.project_dir')) {
            $container->setParameter('kernel.project_dir', $this->getProjectDir());
        }

        $projectConfigDir = $this->projectConfigDir();
        $loader->load($projectConfigDir.'/packages/*.yaml', 'glob');
        if ('test' === $this->environment) {
            $loader->load($projectConfigDir.'/packages/test/*.yaml', 'glob');
        }
        $loader->load($projectConfigDir.'/addressing_services.yaml');
    }

    #[\Override]
    public function getCacheDir(): string
    {
        $runtimeVarDir = $this->configuredRuntimeVarDir();
        if (null === $runtimeVarDir) {
            return parent::getCacheDir();
        }

        return $runtimeVarDir.'/cache/'.$this->environment;
    }

    #[\Override]
    public function getLogDir(): string
    {
        $runtimeVarDir = $this->configuredRuntimeVarDir();
        if (null === $runtimeVarDir) {
            return parent::getLogDir();
        }

        return $runtimeVarDir.'/log';
    }

    private function projectConfigDir(): string
    {
        return $this->getProjectDir().'/config';
    }

    private function configuredRuntimeVarDir(): ?string
    {
        $configuredVarDir = $_SERVER['APP_VAR_DIR'] ?? $_ENV['APP_VAR_DIR'] ?? getenv('APP_VAR_DIR');
        if (is_string($configuredVarDir) && '' !== trim($configuredVarDir)) {
            return rtrim($configuredVarDir, '/');
        }

        return null;
    }
}
