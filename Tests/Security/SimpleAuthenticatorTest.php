<?php

namespace UserBunde\Tests\Security;


use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserEntity;
use UserBundle\Security\Exception\SimpleAuthenticatorMessageException;
use UserBundle\Security\SimpleAuthenticator;
use UserBundle\Security\Token\SimpleAuthenticatorToken;
use UserBundle\Security\UserProvider;
use UserBundle\Utils\UserManager;

/**
 * Тестирование авторизации пользователя по логин-паролю
 *
 * @package UserBundle\Tests\Security
 */
class SimpleAuthenticatorTest extends WebTestCase
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var SimpleAuthenticator
     */
    protected $authenticator;

    protected function setUp()
    {
        parent::setUp();

        $this->userManager = $this->getContainer()->get('user.manager');
        $this->authenticator = $this->getContainer()->get('user.simple_authenticator');

        $this->assertInstanceOf(UserManager::class, $this->userManager);
        $this->assertInstanceOf(SimpleAuthenticator::class, $this->authenticator);
    }

    /**
     * Тестирование поддержки токена юзер-провайдером
     *
     * @covers SimpleAuthenticator::supportsToken()
     */
    public function testSupportsToken()
    {
        $token = new SimpleAuthenticatorToken('non-existent@email.ru', 'testingpassword', 'user.provider');
        $this->assertTrue($this->authenticator->supportsToken($token, 'user.provider'));

        $token = new UsernamePasswordToken('non-existent@email.ru', 'testingpassword', 'user.provider');
        $this->assertFalse($this->authenticator->supportsToken($token, 'user.provider'));

        $token = new UsernamePasswordToken('non-existent@email.ru', 'testingpassword', 'non-user.provider');
        $this->assertFalse($this->authenticator->supportsToken($token, 'user.provider'));
    }

    /**
     * Тестирование создания токена
     *
     * @covers SimpleAuthenticator::createToken()
     */
    public function testCreateToken()
    {
        $username = 'non-existent@email.ru';
        $password = 'testingpassword';
        $providerKey = 'user.provider';

        $token = $this->authenticator->createToken(new Request(), $username, $password, $providerKey);

        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertEquals($username, $token->getUsername());
        $this->assertEquals($password, $token->getCredentials());
        $this->assertEquals($providerKey, $token->getProviderKey());
    }

    /**
     * Тестирование неверного логина и пароля
     *
     * @covers SimpleAuthenticator::authenticateToken()
     * @depends testCreateToken
     * @expectedException UserBundle\Security\Exception\SimpleAuthenticatorMessageException
     * @expectedExceptionMessage Неверный логин или пароль
     */
    public function testAuthenticateTokenWrongUsernameAndPassword()
    {
        // загрузить пользователей
        $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        $username = 'non-existent@email.ru';
        $password = 'wrongpassword';

        /** @var UserProvider $provider */
        $provider = $this->getContainer()->get('user.provider');

        $token = $this->authenticator->createToken(new Request(), $username, $password, 'user.provider');

        $this->authenticator->authenticateToken($token, $provider, 'user.provider');
    }

    /**
     * Тестирование неверного логина и пароля
     *
     * @covers SimpleAuthenticator::authenticateToken()
     * @depends testCreateToken
     * @expectedException UserBundle\Security\Exception\SimpleAuthenticatorMessageException
     * @expectedExceptionMessage Неверный логин или пароль
     */
    public function testAuthenticateTokenWrongPassword()
    {
        // загрузить пользователей
        /** @var UserEntity $activeUser */
        $activeUser = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('active-user');

        $password = 'wrongpassword';

        /** @var UserProvider $provider */
        $provider = $this->getContainer()->get('user.provider');

        $token = $this->authenticator->createToken(new Request(), $activeUser->getEmail(), $password, 'user.provider');

        $this->authenticator->authenticateToken($token, $provider, 'user.provider');
    }

    /**
     * Тестирование авторизации неактивным пользователем
     *
     * @covers SimpleAuthenticator::authenticateToken()
     * @depends testCreateToken
     */
    public function testAuthenticateTokenIsNeedActivation()
    {
        // загрузить пользователей
        /** @var UserEntity $inactiveUser */
        $inactiveUser = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('inactive-user');

        $password = 'testpassword';

        /** @var UserProvider $provider */
        $provider = $this->getContainer()->get('user.provider');

        $token = $this->authenticator->createToken(new Request(), $inactiveUser->getEmail(), $password, 'user.provider');

        $exceptionIsNeedActivation = false;

        try {
            $this->authenticator->authenticateToken($token, $provider, 'user.provider');
        } catch (SimpleAuthenticatorMessageException $e) {
            $exceptionIsNeedActivation = $e->getIsNeedActivation();
        }

        $this->assertTrue($exceptionIsNeedActivation);
    }

    /**
     * Тестирование авторизации заблокированным пользователем
     *
     * @covers SimpleAuthenticator::authenticateToken()
     * @depends testCreateToken
     */
    public function testAuthenticateTokenIsLocked()
    {
        // загрузить пользователей
        /** @var UserEntity $lockedUser */
        $lockedUser = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('locked-user');

        $password = 'testpassword';

        /** @var UserProvider $provider */
        $provider = $this->getContainer()->get('user.provider');

        $token = $this->authenticator->createToken(new Request(), $lockedUser->getEmail(), $password, 'user.provider');

        $exceptionIsLocked = false;

        try {
            $this->authenticator->authenticateToken($token, $provider, 'user.provider');
        } catch (SimpleAuthenticatorMessageException $e) {
            $exceptionIsLocked = $e->getIsLocked();
        }

        $this->assertTrue($exceptionIsLocked);
    }

    /**
     * Тестирование нормальной полной авторизации
     *
     * @depends testCreateToken
     */
    public function testAuthenticateToken()
    {
        // загрузить пользователей
        /** @var UserEntity $activeUser */
        $activeUser = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('active-user');

        $password = 'testpassword';

        /** @var UserProvider $provider */
        $provider = $this->getContainer()->get('user.provider');

        $token = $this->authenticator->createToken(new Request(), $activeUser->getEmail(), $password, 'user.provider');

        $this->assertInternalType('string', $token->getUser());

        $token = $this->authenticator->authenticateToken($token, $provider, 'user.provider');

        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertInstanceOf(UserEntity::class, $token->getUser());
        $this->assertEquals($token->getCredentials(), $activeUser->getPassword());
        $this->assertEquals($token->getUser()->getId(), $activeUser->getId());
    }
}
