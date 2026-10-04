<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Command;

use App\Addressing\Service\Application\AddressEvidenceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exposes Addressing evidence-history summaries through the Symfony console.
 *
 * Tenant-scope inputs are normalized at the CLI boundary before the application service
 * resolves the persisted validation-evidence history for the requested address.
 */
#[AsCommand(name: 'address:summary:evidence', description: 'Summarize evidence history for an address.')]
final class AddressEvidenceSummaryCommand extends Command
{
    public function __construct(private readonly AddressEvidenceService $addressEvidenceService)
    {
        parent::__construct();
    }

    /**
     * Declares the address identity and optional owner/vendor scope used by the summary query.
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
     * Loads the scoped evidence summary and emits a stable JSON representation to stdout.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $summary = $this->addressEvidenceService->historySummary(
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
