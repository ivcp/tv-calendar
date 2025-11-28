<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\NtfyService;
use DI\Container;
use PHPUnit\Framework\TestCase;

final class NtfyServiceTest extends TestCase
{
    private Container $container;
    private NtfyService $ntfyService;

    public function setUp(): void
    {
        exec('cp ./tests/Integration/test-auth.db ./storage/ntfy-test/auth.db', $_, $result);
        if ($result > 0) {
            $this->fail('swapping db failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';

        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->ntfyService = $this->container->get(NtfyService::class);
    }

    public function testGetAllUsers(): void
    {
        $users = $this->ntfyService->getAllUsers();
        $this->assertCount(2, $users);
    }

    public function testCreateUser(): void
    {
        $this->ntfyService->createUser('user@phpunit.test', 'password');
        $users = $this->ntfyService->getAllUsers();
        $this->assertCount(3, $users);

        $this->expectExceptionMessage('Username taken');
        $this->ntfyService->createUser('user@phpunit.test', 'password');

    }


}
