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
        private readonly AddressInputFactory $inputFactory,
    ) {
        parent::__construct();
    }

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dto = new AddressManageDto();
        $dto->line1 = (string) $input->getOption('line1');
        $dto->line2 = self::nullable($input->getOption('line2'));
        $dto->city = (string) $input->getOption('city');
        $dto->region = self::nullable($input->getOption('region'));
        $dto->postalCode = self::nullable($input->getOption('postal-code'));
        $dto->countryCode = (string) $input->getOption('country-code');
        $dto->ownerId = self::nullable($input->getOption('owner-id'));
        $dto->vendorId = self::nullable($input->getOption('vendor-id'));

        $address = $this->inputFactory->fromManageDto($dto, [
            'sourceSystem' => (string) $input->getOption('source-system'),
            'sourceType' => 'manual',
            'sourceReference' => (string) $input->getOption('source-reference'),
        ]);
        $this->addressService->create($address);

        $io->writeln(json_encode([
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'line1' => $address->line1(),
            'city' => $address->city(),
            'countryCode' => $address->countryCode(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
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
