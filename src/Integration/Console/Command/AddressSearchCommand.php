<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Console\Command;

use App\Contract\Message\AddressRecordPolicy;
use App\Service\Application\AddressService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:search', description: 'Search canonical addresses with operational filters.')]
final class AddressSearchCommand extends Command
{
    public function __construct(private readonly AddressService $addressService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('country-code', null, InputOption::VALUE_OPTIONAL)
            ->addOption('query', 'q', InputOption::VALUE_OPTIONAL)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, default: '25')
            ->addOption('cursor', null, InputOption::VALUE_OPTIONAL)
            ->addOption('source-type', null, InputOption::VALUE_OPTIONAL)
            ->addOption('governance-status', null, InputOption::VALUE_OPTIONAL)
            ->addOption('revalidation-policy', null, InputOption::VALUE_OPTIONAL)
            ->addOption('has-evidence', null, InputOption::VALUE_OPTIONAL)
            ->addOption('revalidation-due-before', null, InputOption::VALUE_OPTIONAL)
            ->addOption('expected-normalization-version', null, InputOption::VALUE_OPTIONAL);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $result = $this->addressService->search(
            $this->nullable($input->getOption('owner-id')),
            $this->nullable($input->getOption('vendor-id')),
            $this->nullableUpper($input->getOption('country-code')),
            $this->nullable($input->getOption('query')),
            max(1, $this->intOption($input, 'limit', 25)),
            $this->nullable($input->getOption('cursor')),
            [
                'sourceType' => AddressRecordPolicy::normalizeSourceType($this->nullable($input->getOption('source-type'))),
                'governanceStatus' => null !== $this->nullable($input->getOption('governance-status'))
                    ? AddressRecordPolicy::normalizeGovernanceStatus($this->nullable($input->getOption('governance-status')))
                    : null,
                'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy($this->nullable($input->getOption('revalidation-policy'))),
                'hasEvidence' => $this->nullableBool($input->getOption('has-evidence')),
                'revalidationDueBefore' => $this->nullable($input->getOption('revalidation-due-before')),
                'expectedNormalizationVersion' => $this->nullable($input->getOption('expected-normalization-version')),
            ],
        );

        $items = array_map(static fn ($address): array => [
            'id' => $address->id(),
            'line1' => $address->line1(),
            'city' => $address->city(),
            'countryCode' => $address->countryCode(),
            'validationStatus' => $address->validationStatus(),
            'governanceStatus' => $address->governanceStatus(),
            'revalidationPolicy' => $address->revalidationPolicy(),
        ], $result['items']);

        $payload = json_encode([
            'nextCursor' => $result['nextCursor'],
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $payload) {
            throw new \RuntimeException('invalid_search_payload');
        }

        $symfonyStyle->writeln($payload);

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

    private function nullable(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return null === $value ? null : strtoupper($value);
    }

    private function nullableBool(mixed $value): ?bool
    {
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }
}
