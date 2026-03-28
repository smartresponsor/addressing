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

#[AsCommand(name: 'address:summary:portfolio', description: 'Summarize country/source/validation/normalization portfolios.')]
final class AddressPortfolioSummaryCommand extends Command
{
    public function __construct(private readonly AddressService $addressService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('kind', InputArgument::REQUIRED, 'country|source|validation|normalization')
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('country-code', null, InputOption::VALUE_OPTIONAL)
            ->addOption('query', 'q', InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kind = self::requiredArgument($input, 'kind');
        $ownerId = self::nullable($input->getOption('owner-id'));
        $vendorId = self::nullable($input->getOption('vendor-id'));
        $countryCode = self::nullable($input->getOption('country-code'));
        $query = self::nullable($input->getOption('query'));

        $summary = match ($kind) {
            'country' => $this->addressService->countryPortfolioSummary($ownerId, $vendorId, $query),
            'source' => $this->addressService->sourcePortfolioSummary($ownerId, $vendorId, $countryCode, $query),
            'validation' => $this->addressService->validationPortfolioSummary($ownerId, $vendorId, $countryCode, $query),
            'normalization' => $this->addressService->normalizationPortfolioSummary($ownerId, $vendorId, $countryCode, $query),
            default => throw new \RuntimeException('invalid_portfolio_kind'),
        };

        $payload = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $payload) {
            throw new \RuntimeException('invalid_summary_payload');
        }

        $io->writeln($payload);

        return Command::SUCCESS;
    }

    private static function requiredArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        if (!is_string($value)) {
            throw new \RuntimeException('invalid_argument_'.$name);
        }

        return $value;
    }

    private static function nullable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
