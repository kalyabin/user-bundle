<?php

namespace UserBunde\Tests\Entity\Repository;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserEntity;
use UserBundle\Entity\Repository\UserRepository;


/**
 * Тестирование класса UserRepository
 *
 * @package UserBundle\Tests\Entity\Repository
 */
class UserRepositoryTest extends WebTestCase
{
    /**
     * @var \UserBundle\Entity\Repository\UserRepository
     */
    protected $repository;

    protected function setUp()
    {
        static::bootKernel();

        $this->repository = static::$kernel->getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(UserEntity::class);
    }

    /**
     * Получение пользователя по идентификатору
     *
     * @covers UserRepository::findOneById()
     */
    public function testFindOneById()
    {
        /** @var UserEntity $user */
        $user = $this->loadFixtures([
            UserTestFixture::class,
        ])->getReferenceRepository()->getReference('active-user');

        $expectedUser = $this->repository->findOneById($user->getId());

        $this->assertNotNull($expectedUser);
        $this->assertInstanceOf(UserEntity::class, $expectedUser);
        $this->assertEquals($expectedUser->getId(), $user->getId());

        $unexpectedUser = $this->repository->findOneById(0);
        $this->assertNull($unexpectedUser);
    }

    /**
     * Получение пользователя по e-mail
     *
     * @covers UserRepository::findOneByEmail()
     */
    public function testFindOneByEmail()
    {
        /** @var UserEntity $user */
        $user = $this->loadFixtures([
            UserTestFixture::class,
        ])->getReferenceRepository()->getReference('active-user');

        $expectedUser = $this->repository->findOneByEmail($user->getEmail());

        $this->assertNotNull($expectedUser);
        $this->assertInstanceOf(UserEntity::class, $expectedUser);
        $this->assertEquals($expectedUser->getId(), $user->getId());

        $unexpectedUser = $this->repository->findOneByEmail('non-existent@email.ru');
        $this->assertNull($unexpectedUser);
    }

    /**
     * Тестирование существования пользователя по e-mail
     *
     * @covers UserRepository::userIsExistsByEmail()
     */
    public function testUserIsExistsByEmail()
    {
        /** @var UserEntity $user */
        $user = $this->loadFixtures([
            UserTestFixture::class,
        ])->getReferenceRepository()->getReference('active-user');

        $this->assertTrue($this->repository->userIsExistsByEmail($user->getEmail()));
        $this->assertFalse($this->repository->userIsExistsByEmail('non-existent@email.ru'));
        $this->assertFalse($this->repository->userIsExistsByEmail($user->getEmail(), $user->getId()));
    }
}
