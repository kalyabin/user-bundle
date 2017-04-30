<?php

namespace UserBunde\Tests\Security;


use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Security\UserProvider;

/**
 * Тестирование юзер-провайдера для авторизации пользователей
 *
 * @package UserBundle\Tests\Security
 */
class UserProviderTest extends WebTestCase
{
    /**
     * @var UserProvider
     */
    protected $provider;

    /**
     * @var ReferenceRepository
     */
    protected $fixtures;

    public function setUp()
    {
        parent::setUp();

        $this->provider = $this->getContainer()->get('user.provider');

        $this->assertInstanceOf(UserProvider::class, $this->provider);

        $this->fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();
    }

    /**
     * Проверить, что если пользователь не существует, провайдер сгенерирует ошибку
     *
     * @covers UserProvider::loadUserByUsername()
     * @expectedException Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testWrongUsername()
    {
        $this->provider->loadUserByUsername('non-existent@email.ru');
    }

    /**
     * Тестирование получения пользователей по валидному логину
     *
     * @covers UserProvider::loadUserByUsername()
     */
    public function testValidUsername()
    {
        /** @var UserEntity $activeUser */
        $activeUser = $this->fixtures->getReference('active-user');
        /** @var UserEntity $inactiveUser */
        $inactiveUser = $this->fixtures->getReference('inactive-user');

        $loadedUser = $this->provider->loadUserByUsername($activeUser->getEmail());
        $this->assertInstanceOf(UserEntity::class, $loadedUser);
        $this->assertEquals($loadedUser->getId(), $activeUser->getId());

        $loadedUser = $this->provider->loadUserByUsername($inactiveUser->getEmail());
        $this->assertInstanceOf(UserEntity::class, $loadedUser);
        $this->assertEquals($loadedUser->getId(), $inactiveUser->getId());
    }

    /**
     * Проверить поддержку классов для авторизации
     *
     * @covers UserProvider::supportsClass()
     */
    public function testSupportsClass()
    {
        $this->assertFalse($this->provider->supportsClass(UserCheckerEntity::class));
        $this->assertTrue($this->provider->supportsClass(UserEntity::class));
    }

    /**
     * Проверить обновление пользователя в сессии
     *
     * @covers UserProvider::refreshUser()
     */
    public function testRefreshUser()
    {
        /** @var UserEntity $activeUser */
        $activeUser = $this->fixtures->getReference('active-user');

        $refreshedUser = $this->provider->refreshUser($activeUser);
        $this->assertInstanceOf(UserEntity::class, $refreshedUser);
        $this->assertEquals($refreshedUser->getId(), $activeUser->getId());
    }
}
