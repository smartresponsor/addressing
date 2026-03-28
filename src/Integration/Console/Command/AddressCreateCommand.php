<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Console\Command;

use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Service\Application\AddressService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:create', description: 'Create a canonical address record from CLI inputs.')]
final class AddressCreateCommand extends Command
{
    public function __construct(
        private readonly AddressService $addressService,
        private readonly AddressInputFactory $addressInputFactory,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('line1', null, InputOption::VALUE_REQUIRED)
            ->addOption('line2', null, InputOption::VALUE_OPTIONAL)
            ->addOption('city', null, InputOption::VALUE_REQUIRED)
            ->addOption('region', null, InputOption::VALUE_OPTIONAL)
            ->addOption('postal-code', null, InputOption::VALUE_OPTIONAL)
            ->addOption('country-code', null, InputOption::VALUE_REQUIRED, default: 'US')
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('source-system', null, InputOption::VALUE_OPTIONAL, default: 'symfony-cli')
            ->addOption('source-reference', null, InputOption::VALUE_OPTIONAL, default: 'address:create');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $addressManageDto = new AddressManageDto();
        $addressManageDto->line1 = $this->requiredOption($input, 'line1');
        $addressManageDto->line2 = $this->nullable($input->getOption('line2'));
        $addressManageDto->city = $this->requiredOption($input, 'city');
        $addressManageDto->region = $this->nullable($input->getOption('region'));
        $addressManageDto->postalCode = $this->nullable($input->getOption('postal-code'));
        $addressManageDto->countryCode = $this->requiredOption($input, 'country-code');
        $addressManageDto->ownerId = $this->nullable($input->getOption('owner-id'));
        $addressManageDto->vendorId = $this->nullable($input->getOption('vendor-id'));

        $addressData = $this->addressInputFactory->fromManageDto($addressManageDto, [
            'sourceSystem' => $this->requiredOption($input, 'source-system'),
            'sourceType' => 'manual',
            'sourceReference' => $this->requiredOption($input, 'source-reference'),
        ]);
        $this->addressService->create($addressData);

        $message = json_encode([
            'id' => $addressData->id(),
            'ownerId' => $addressData->ownerId(),
            'vendorId' => $addressData->vendorId(),
            'line1' => $addressData->line1(),
            'city' => $addressData->city(),
            'countryCode' => $addressData->countryCode(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $message) {
            $symfonyStyle->error('Failed to encode command output.');

            return Command::FAILURE;
        }

        $symfonyStyle->writeln($message);

        return Command::SUCCESS;
    }

    private function requiredOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            throw new \RuntimeException('invalid_option_'.$name);
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
