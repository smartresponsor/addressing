<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Command;

use App\Addressing\Service\Application\AddressGovernanceSummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exposes Addressing governance-cluster summaries through the Symfony console.
 *
 * The command keeps tenant-scope normalization at the transport boundary and delegates
 * relationship aggregation to the Addressing-owned governance summary service.
 */
#[AsCommand(name: 'address:summary:governance-cluster', description: 'Summarize linked governance relationships for an address.')]
final class AddressGovernanceClusterSummaryCommand extends Command
{
    public function __construct(private readonly AddressGovernanceSummaryService $addressGovernanceSummaryService)
    {
        parent::__construct();
    }

    /**
     * Declares the address identity and optional owner/vendor scope for the governance query.
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('address-id', InputArgument::REQUIRED)
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL);
    }

    /**
     * Resolves the scoped governance cluster and emits its stable JSON representation.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $summary = $this->addressGovernanceSummaryService->summarize(
            $this->addressId($input),
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

    private function addressId(InputInterface $input): string
    {
        return $input->getArgument('address-id');
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
