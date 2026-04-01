<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Persistence\AddressRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AddressRepositoryTenantScopeIntegrationTest extends TestCase
{
    public function testGetThrowsWhenTenantScopeMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $repo = new AddressRepository($pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant_scope_required');

        $repo->get('addr-1', null, null);
    }
}
