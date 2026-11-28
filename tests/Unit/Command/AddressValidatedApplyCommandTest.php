<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Command;

use App\Addressing\Command\AddressValidatedApplyCommand;
use App\Addressing\RepositoryInterface\AddressValidatedPersistenceRepositoryInterface;
use App\Addressing\Service\Application\AddressValidatedApplierService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class AddressValidatedApplyCommandTest extends TestCase
{
    #[DataProvider('invalidPayloads')]
    public function testRejectsNonStringKeysBeforePersistence(string $payload): void
    {
        $repository = $this->createMock(AddressValidatedPersistenceRepositoryInterface::class);
        $repository->expects(self::never())->method('findScopedAddress');
        $tester = new CommandTester(new AddressValidatedApplyCommand(new AddressValidatedApplierService($repository)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_json');
        $tester->execute(['address-id' => 'address-1', 'payload-json' => $payload]);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'list' => ['["list"]'];
        yield 'mixed keys' => ['{"line1Norm":"main","2":"numeric"}'];
        yield 'invalid JSON' => ['not-json'];
    }
}
