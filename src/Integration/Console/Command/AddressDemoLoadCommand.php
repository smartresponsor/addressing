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
    public function __construct(private readonly AddressDemoFixtureService $fixtureService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_OPTIONAL, default: '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = max(1, self::intOption($input, 'count', 50));
        $loaded = $this->fixtureService->resetAndLoad($count);

        $io->success(sprintf('Loaded %d demo addresses.', $loaded));

        return Command::SUCCESS;
    }

    private static function intOption(InputInterface $input, string $name, int $default): int
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
