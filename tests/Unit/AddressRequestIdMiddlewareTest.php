<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Addressing\Middleware\AddressRequestIdMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AddressRequestIdMiddlewareTest extends TestCase
{
    #[RunInSeparateProcess]
    #[DataProvider('invalidRequestIds')]
    public function testInvalidRequestIdIsReplaced(mixed $value): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = $value;

        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/D', AddressRequestIdMiddleware::ensure());
    }

    #[RunInSeparateProcess]
    public function testExistingRequestIdIsPreserved(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'upstream-request-123';

        self::assertSame('upstream-request-123', AddressRequestIdMiddleware::ensure());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidRequestIds(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'array' => [['invalid']];
        yield 'integer' => [123];
        yield 'header injection' => ["request\r\nX-Other: injected"];
    }
}
