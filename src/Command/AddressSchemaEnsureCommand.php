<?php

declare(strict_types=1);

namespace App\Addressing\Command;

use App\Addressing\Service\AddressDoctrineSchemaManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ensures the Addressing-owned Doctrine schema exists without dropping data.
 */
#[AsCommand(name: 'address:schema:ensure', description: 'Ensure the Addressing Doctrine schema exists without resetting it.')]
final class AddressSchemaEnsureCommand extends Command
{
    public function __construct(private readonly AddressDoctrineSchemaManager $addressDoctrineSchemaManager)
    {
        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->addressDoctrineSchemaManager->ensureSchema();
        (new SymfonyStyle($input, $output))->success('Addressing schema is ready.');

        return Command::SUCCESS;
    }
}
