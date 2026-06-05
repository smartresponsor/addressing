<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Command;

use App\Service\Fixture\AddressDemoFixtureService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:demo:load', description: 'Reset schema and load Symfony/Faker demo fixtures.')]
final class AddressDemoLoadCommand extends Command
{
    private const int DEFAULT_COUNT = 50;

    public function __construct(private readonly AddressDemoFixtureService $addressDemoFixtureService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('count', null, InputOption::VALUE_OPTIONAL, default: (string) self::DEFAULT_COUNT);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = max(1, $this->countOption($input));
        $loaded = $this->addressDemoFixtureService->resetAndLoad($count);

        $io->success(sprintf('Loaded %d demo addresses.', $loaded));

        return Command::SUCCESS;
    }

    private function countOption(InputInterface $input): int
    {
        $value = $input->getOption('count');
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return self::DEFAULT_COUNT;
    }
}
