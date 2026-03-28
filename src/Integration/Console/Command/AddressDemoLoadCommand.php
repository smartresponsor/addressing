<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Console\Command;

use App\Fixture\AddressDemoFixtureService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:demo:load', description: 'Reset schema and load Symfony/Faker demo fixtures.')]
final class AddressDemoLoadCommand extends Command
{
    public function __construct(private readonly AddressDemoFixtureService $addressDemoFixtureService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_OPTIONAL, default: '50');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $count = max(1, $this->intOption($input, 'count', 50));
        $loaded = $this->addressDemoFixtureService->resetAndLoad($count);

        $symfonyStyle->success(sprintf('Loaded %d demo addresses.', $loaded));

        return Command::SUCCESS;
    }

    private function intOption(InputInterface $input, string $name, int $default): int
    {
        $value = $input->getOption($name);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
