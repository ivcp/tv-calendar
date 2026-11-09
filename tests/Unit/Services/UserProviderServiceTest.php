<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DataObjects\RegisterUserData;
use App\Entity\User;
use App\Services\NtfyService;
use App\Services\UserProviderService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserProviderServiceTest extends TestCase
{
    private EntityManager&MockObject $entityManager;
    private EntityRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->userRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')->with(User::class)->willReturn($this->userRepository);
    }

    public function testNormalizesTheEmailWhenCreatingAUser(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(
                fn (User $user) => $user->getEmail() === 'user@example.com'
            ));
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->service()->createUser(new RegisterUserData(email: ' User@Example.COM '));

        $this->assertSame('user@example.com', $user->getEmail());
    }

    public function testFindsAUserByEmailCaseInsensitively(): void
    {
        $expected = new User();

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($expected);

        $queryBuilder = $this->queryBuilder();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('LOWER(u.email) = :email')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('email', 'user@example.com')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->userRepository->method('createQueryBuilder')->with('u')->willReturn($queryBuilder);

        $user = $this->service()->getByCredentials(['email' => ' User@Example.COM ']);

        $this->assertSame($expected, $user);
    }

    public function testDoesNotQueryTheDatabaseForAnEmptyEmail(): void
    {
        $this->userRepository->expects($this->never())->method('createQueryBuilder');

        $this->assertNull($this->service()->getByCredentials(['email' => '   ']));
    }

    public function testReportsThatAnEmailExistsCaseInsensitively(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(1);

        $queryBuilder = $this->queryBuilder();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('email', 'user@example.com')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->userRepository->method('createQueryBuilder')->with('u')->willReturn($queryBuilder);

        $this->assertTrue($this->service()->emailExists('User@Example.com'));
    }

    public function testReportsThatAnEmailDoesNotExist(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $queryBuilder = $this->queryBuilder();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->userRepository->method('createQueryBuilder')->with('u')->willReturn($queryBuilder);

        $this->assertFalse($this->service()->emailExists('user@example.com'));
        $this->assertFalse($this->service()->emailExists('   '));
    }

    private function queryBuilder(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();

        return $queryBuilder;
    }

    private function service(): UserProviderService
    {
        return new UserProviderService(
            $this->entityManager,
            $this->createMock(NtfyService::class)
        );
    }
}
