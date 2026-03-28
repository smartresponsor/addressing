<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Console\Command;

use App\Contract\Message\AddressValidated;
use App\Service\Application\AddressValidatedApplierService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'address:validated:apply', description: 'Apply a validated payload to an existing address.')]
final class AddressValidatedApplyCommand extends Command
{
    public function __construct(private readonly AddressValidatedApplierService $validatedApplier)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('address-id', InputArgument::REQUIRED)
            ->addArgument('payload-json', InputArgument::REQUIRED)
            ->addOption('owner-id', null, InputOption::VALUE_OPTIONAL)
            ->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $payload = json_decode(self::requiredArgument($input, 'payload-json'), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('invalid_json');
        }

        $validated = AddressValidated::fromArray($payload);
        $this->validatedApplier->apply(
            self::requiredArgument($input, 'address-id'),
            $validated,
            self::nullable($input->getOption('owner-id')),
            self::nullable($input->getOption('vendor-id')),
        );

        $io->success('Validated payload applied.');

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
