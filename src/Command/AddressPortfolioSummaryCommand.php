<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Command;

use App\Service\Application\AddressPortfolioSummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:summary:portfolio', description: 'Summarize country/source/validation/normalization portfolios.')]
final class AddressPortfolioSummaryCommand extends Command
{
    public function __construct(private readonly AddressPortfolioSummaryService $addressPortfolioSummaryService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('kind', InputArgument::REQUIRED, 'country|source|validation|normalization')
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('country-code', null, InputOption::VALUE_OPTIONAL)
            ->addOption('query', null, InputOption::VALUE_OPTIONAL);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $kind = $this->portfolioKind($input);
        $ownerId = $this->nullable($input->getOption('owner-id'));
        $vendorId = $this->nullable($input->getOption('vendor-id'));
        $countryCode = $this->nullable($input->getOption('country-code'));
        $query = $this->nullable($input->getOption('query'));

        $summary = match ($kind) {
            'country' => $this->addressPortfolioSummaryService->country($ownerId, $vendorId, $query),
            'source' => $this->addressPortfolioSummaryService->source($ownerId, $vendorId, $countryCode, $query),
            'validation' => $this->addressPortfolioSummaryService->validation($ownerId, $vendorId, $countryCode, $query),
            'normalization' => $this->addressPortfolioSummaryService->normalization($ownerId, $vendorId, $countryCode, $query),
            default => throw new \RuntimeException('invalid_portfolio_kind'),
        };

        $payload = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $payload) {
            throw new \RuntimeException('invalid_summary_payload');
        }

        $symfonyStyle->writeln($payload);

        return Command::SUCCESS;
    }

    private function portfolioKind(InputInterface $input): string
    {
        $value = $input->getArgument('kind');

        if (!is_string($value)) {
            throw new \RuntimeException('invalid_argument_kind');
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
