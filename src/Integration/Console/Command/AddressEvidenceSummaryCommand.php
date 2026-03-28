<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Console\Command;

use App\Service\Application\AddressService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:summary:evidence', description: 'Summarize evidence history for an address.')]
final class AddressEvidenceSummaryCommand extends Command
{
    public function __construct(private readonly AddressService $addressService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('address-id', InputArgument::REQUIRED)
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $summary = $this->addressService->evidenceHistorySummary(
            $this->requiredArgument($input, 'address-id'),
            $this->nullable($input->getOption('owner-id')),
            $this->nullable($input->getOption('vendor-id')),
        );

        $payload = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $payload) {
            throw new \RuntimeException('invalid_summary_payload');
        }

        $symfonyStyle->writeln($payload);

        return Command::SUCCESS;
    }

    private function requiredArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        if (!is_string($value)) {
            throw new \RuntimeException('invalid_argument_'.$name);
        }

        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
